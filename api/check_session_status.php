<?php
/**
 * ============================================================
 * CHECK SESSION STATUS API
 * ============================================================
 * Checkt of de huidige sessie nog actief is in database
 * Gebruikt door JavaScript polling voor real-time logout detection
 * 
 * METHOD: GET
 * OUTPUT: JSON { "active": true/false, "forced_logout": true/false }
 * 
 * LOCATIE: /api/check_session_status.php
 * ============================================================
 */

session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'active' => false,
        'forced_logout' => false,
        'message' => 'Not logged in'
    ]);
    exit;
}

try {
    // Check if this session is still active in database
    $stmt = $db->prepare("
        SELECT is_active
        FROM user_sessions 
        WHERE user_id = ? 
        AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $active_session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($active_session) {
        // Session is still active
        echo json_encode([
            'active' => true,
            'forced_logout' => false,
            'message' => 'Session active'
        ]);
    } else {
        // Session is NOT active - force logout detected!
        echo json_encode([
            'active' => false,
            'forced_logout' => true,
            'message' => 'Force logout detected'
        ]);
    }
    
} catch (PDOException $e) {
    // Error checking - assume active to not break user experience
    error_log("Session status check error: " . $e->getMessage());
    
    echo json_encode([
        'active' => true,
        'forced_logout' => false,
        'error' => 'Database error'
    ]);
}
