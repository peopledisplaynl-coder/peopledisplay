<?php
// ============================================
// GET ONLINE USERS API
// ============================================
// File: admin/api/get_online_users.php
// Install location: /admin/api/get_online_users.php
// ============================================

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session_tracker.php';
require_once __DIR__ . '/../auth_helper.php';

// Require admin access
requireAdmin();

header('Content-Type: application/json');

try {
    $tracker = new SessionTracker($db);
    
    // Cleanup expired sessions first
    $tracker->cleanupExpiredSessions();
    
    // Get all active sessions
    $sessions = $tracker->getActiveSessions();
    
    // Get total count
    $total_count = $tracker->getActiveSessionCount();
    
    // Count by status
    $status_counts = [
        'online' => 0,
        'idle' => 0,
        'away' => 0
    ];
    
    foreach ($sessions as $session) {
        if (isset($status_counts[$session['status']])) {
            $status_counts[$session['status']]++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'sessions' => $sessions,
        'total_count' => $total_count,
        'status_counts' => $status_counts,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
