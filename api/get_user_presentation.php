<?php
/**
 * ============================================================
 * GET USER PRESENTATION SETTINGS API
 * ============================================================
 * Bestand: get_user_presentation.php
 * Locatie: /api/
 * 
 * Returns presentation settings voor ingelogde user
 * ============================================================
 */

session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Not logged in'
    ]);
    exit;
}

// Database connection
require_once __DIR__ . '/../includes/db.php';

try {
    $userId = $_SESSION['user_id'];
    
    // Get user presentation settings
    $stmt = $db->prepare("
        SELECT 
            can_show_presentation,
            presentation_id
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'error' => 'User not found'
        ]);
        exit;
    }
    
    // Get global presentation ID if user doesn't have one
    $presentationId = $user['presentation_id'];
    
    if (empty($presentationId)) {
        // Fallback to global config
        $stmt = $db->query("SELECT presentationID FROM config WHERE id = 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($config && !empty($config['presentationID'])) {
            $presentationId = $config['presentationID'];
        }
    }
    
    // Return settings
    echo json_encode([
        'success' => true,
        'can_show' => (bool)$user['can_show_presentation'],
        'presentation_id' => $presentationId,
        'has_presentation' => !empty($presentationId)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
