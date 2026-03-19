<?php
// Authentication Helper
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Attempt an auto-login using the remember me token.
 *
 * This function is safe to call on every page load. It will only log in the user
 * if a valid remember token is present in cookies and no user is currently logged in.
 *
 * @param PDO $db
 * @return bool True when the session was restored, false otherwise.
 */
function pd_try_remember_me_login(PDO $db): bool {
    if (!empty($_SESSION['user_id'])) {
        return false; // already logged in
    }

    if (empty($_COOKIE['remember_selector']) || empty($_COOKIE['remember_token'])) {
        return false;
    }

    $selector = $_COOKIE['remember_selector'];
    $token = $_COOKIE['remember_token'];

    try {
        // Check if remember_tokens table exists
        $checkTable = $db->query("SHOW TABLES LIKE 'remember_tokens'");
        if (!$checkTable || $checkTable->rowCount() === 0) {
            return false;
        }

        $stmt = $db->prepare("SELECT rt.user_id, rt.token, u.username, u.display_name, u.role, u.active
            FROM remember_tokens rt
            JOIN users u ON rt.user_id = u.id
            WHERE rt.selector = ? AND rt.expires_at > NOW()");
        $stmt->execute([$selector]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokenData || !hash_equals($tokenData['token'], hash('sha256', $token))) {
            // Invalid token: delete any matching selector for hygiene
            $deleteStmt = $db->prepare("DELETE FROM remember_tokens WHERE selector = ?");
            $deleteStmt->execute([$selector]);
            return false;
        }

        if (empty($tokenData['active'])) {
            return false;
        }

        // Valid token: restore session
        $_SESSION['user_id'] = $tokenData['user_id'];
        $_SESSION['username'] = $tokenData['username'];
        $_SESSION['display_name'] = $tokenData['display_name'];
        $_SESSION['role'] = $tokenData['role'];
        $_SESSION['remember_me'] = true;

        // Update last_login
        try {
            $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$tokenData['user_id']]);
        } catch (Exception $e) {
            error_log("Last login update failed: " . $e->getMessage());
        }

        // Start session tracking for restored session
        try {
            require_once __DIR__ . '/session_tracker.php';
            $tracker = new SessionTracker($db);
            $tracker->startSession($tokenData['user_id']);
        } catch (Exception $e) {
            error_log("Session tracking start failed: " . $e->getMessage());
        }

        return true;

    } catch (Exception $e) {
        error_log("Remember me check failed: " . $e->getMessage());
        // Clear cookies to avoid repeated failures
        setcookie('remember_selector', '', time() - 3600, '/', '', false, true);
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        return false;
    }
}

// Authentication check function
function requireAuth() {
    if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
        return false;
    }
    return true;
}

// Admin check function  
function requireAdmin() {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
        return false;
    }
    return true;
}
