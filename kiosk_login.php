<?php
/**
 * Kiosk Auto-Login
 * EXACT zoals login.php het doet
 */

// Include db.php first (dit start al de session!)
require_once __DIR__ . '/includes/db.php';

// Check token
$token = $_GET['token'] ?? '';
if (!$token) {
    header('Location: login.php');
    exit;
}

// Lookup token + user
$stmt = $db->prepare("
    SELECT 
        u.id,
        u.username,
        u.display_name,
        u.role,
        u.active,
        kt.allowed_ip,
        kt.expires_at
    FROM kiosk_tokens kt
    INNER JOIN users u ON kt.user_id = u.id
    WHERE kt.token = ? 
    AND kt.is_active = 1
");

$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !$user['active']) {
    header('Location: login.php');
    exit;
}

// Check vervaldatum
if ($user['expires_at'] && strtotime($user['expires_at']) < time()) {
    header('Location: login.php?error=token_expired');
    exit;
}

// Check IP beperking
if ($user['allowed_ip']) {
    $client_ip = $_SERVER['REMOTE_ADDR'];
    if ($client_ip !== $user['allowed_ip']) {
        header('Location: login.php?error=ip_not_allowed');
        exit;
    }
}

// Update token last_used
$db->prepare("UPDATE kiosk_tokens SET last_used = NOW() WHERE token = ?")
   ->execute([$token]);

// Login successful - Set session vars (EXACT zoals login.php)
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['display_name'] = $user['display_name'];
$_SESSION['role'] = $user['role'];

// Force session write (EXACT zoals login.php)
session_write_close();
session_start();

// Update last_login
try {
    $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);
} catch (Exception $e) {
    error_log("Last login update failed: " . $e->getMessage());
}

// START SESSION TRACKING (zoals login.php)
try {
    require_once __DIR__ . '/includes/session_tracker.php';
    $tracker = new SessionTracker($db);
    $tracker->startSession($user['id']);
} catch (Exception $e) {
    error_log("Session tracking start failed: " . $e->getMessage());
}

// Redirect based on role (EXACT zoals login.php)
if (in_array($user['role'], ['admin', 'superadmin'])) {
    header('Location: admin/dashboard.php');
    exit;
} else {
    header('Location: frontpage.php');
    exit;
}
