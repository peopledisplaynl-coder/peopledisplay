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
    // Prefer the session cookie value to identify the session record.
    $sessionId = session_id();
    if (!empty($_COOKIE[session_name()])) {
        $sessionId = $_COOKIE[session_name()];
    }

    $stmt = $db->prepare("SELECT is_active, last_activity FROM user_sessions WHERE session_id = ? LIMIT 1");
    $stmt->execute([$sessionId]);
    $sessionRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sessionRow && (int)$sessionRow['is_active'] === 1) {
        echo json_encode([
            'active' => true,
            'forced_logout' => false,
            'message' => 'Session active'
        ]);
        exit;
    }

    // Session is not active (or not found).
    // Determine if this was a timeout (user idle) vs an explicit forced logout.
    $forcedLogout = false;
    if ($sessionRow) {
        $lastActivity = strtotime($sessionRow['last_activity']);
        $inactiveSeconds = time() - $lastActivity;
        $sessionTimeoutSeconds = 15 * 60; // matches SessionTracker default
        if ($inactiveSeconds < $sessionTimeoutSeconds) {
            // Recent activity but session marked inactive → likely forced logout
            $forcedLogout = true;
        }
    }

    echo json_encode([
        'active' => false,
        'forced_logout' => $forcedLogout,
        'message' => $forcedLogout ? 'Force logout detected' : 'Session inactive'
    ]);

} catch (PDOException $e) {
    // Error checking - assume active to not break user experience
    error_log("Session status check error: " . $e->getMessage());

    echo json_encode([
        'active' => true,
        'forced_logout' => false,
        'error' => 'Database error'
    ]);
}
