<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
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
        SELECT *
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
    
    // Infoscherm (2.4.0): wat het scherm bij inactiviteit toont — Google Slides, het
    // Infoscherm of beide (Slides met zijbalk). De kolom bestaat pas na de migratie.
    $screenMode = in_array($user['screen_mode'] ?? '', ['slides', 'infoscreen', 'combined'], true)
        ? $user['screen_mode'] : 'slides';
    $idleSeconds = max(10, min(3600, (int)($user['presentation_idle_seconds'] ?? 120)));

    // Return settings
    echo json_encode([
        'success' => true,
        'can_show' => (bool)$user['can_show_presentation'],
        'presentation_id' => $presentationId,
        'screen_mode' => $screenMode,
        'idle_seconds' => $idleSeconds,
        // Het Infoscherm heeft geen presentatie-ID nodig (zonder ID toont 'combined' alleen het Infoscherm)
        'has_presentation' => !empty($presentationId) || $screenMode !== 'slides'
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
