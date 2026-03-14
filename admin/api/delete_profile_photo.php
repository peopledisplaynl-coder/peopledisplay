<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: delete_profile_photo.php
 * LOCATIE:      /admin/api/delete_profile_photo.php
 * BESCHRIJVING: Delete user profile photo
 * ═══════════════════════════════════════════════════════════════════
 */

session_start();
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get current photo
    $stmt = $db->prepare("SELECT profile_photo FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $photoPath = $stmt->fetchColumn();
    
    if (!$photoPath) {
        throw new Exception('Geen foto gevonden');
    }
    
    // Delete file
    $fullPath = __DIR__ . '/../../' . $photoPath;
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }
    
    // Update database
    $stmt = $db->prepare("UPDATE users SET profile_photo = NULL WHERE id = ?");
    $stmt->execute([$user_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Foto verwijderd'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
