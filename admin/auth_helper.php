<?php
/**
 * ============================================================================
 * BESTANDSNAAM:  auth_helper.php
 * UPLOAD NAAR:   /admin/auth_helper.php (OVERSCHRIJF)
 * DATUM:         2024-12-04
 * VERSIE:        FIXED - Redirects naar ../login.php
 * 
 * FIX: Changed login.html → ../login.php
 * ============================================================================
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Require user to be logged in
 * Redirects to login page if not authenticated
 */
if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isset($_SESSION['user_id'])) {
            // FIXED: Was login.html, now ../login.php
            header('Location: ../login.php');
            exit;
        }
    }
}

/**
 * Require user to have admin or superadmin role
 * Redirects to login page if not admin
 */
if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        requireLogin(); // First check if logged in
        
        $userRole = $_SESSION['role'] ?? 'user';
        
        if (!in_array($userRole, ['admin', 'superadmin'])) {
            // User is logged in but not admin - redirect to frontpage
            header('Location: ../frontpage.php');
            exit;
        }

        // Prevent stale admin page caches in browser
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }
}

/**
 * Require user to have superadmin role
 * Redirects appropriately if not superadmin
 */
if (!function_exists('requireSuperAdmin')) {
    function requireSuperAdmin() {
        requireLogin(); // First check if logged in
        
        $userRole = $_SESSION['role'] ?? 'user';
        
        if ($userRole !== 'superadmin') {
            // Not superadmin - redirect based on role
            if ($userRole === 'admin') {
                header('Location: dashboard.php'); // Admin → dashboard
            } else {
                header('Location: ../frontpage.php'); // User → frontpage
            }
            exit;
        }

        // Prevent stale admin page caches in browser
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }
}

/**
 * Check if user is logged in (without redirect)
 * @return bool
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

/**
 * Check if user is admin (without redirect)
 * @return bool
 */
if (!function_exists('isAdmin')) {
    function isAdmin() {
        if (!isLoggedIn()) {
            return false;
        }
        
        $userRole = $_SESSION['role'] ?? 'user';
        return in_array($userRole, ['admin', 'superadmin']);
    }
}

/**
 * Check if user is superadmin (without redirect)
 * @return bool
 */
if (!function_exists('isSuperAdmin')) {
    function isSuperAdmin() {
        if (!isLoggedIn()) {
            return false;
        }
        
        return ($_SESSION['role'] ?? '') === 'superadmin';
    }
}

/**
 * Get current user ID
 * @return int|null
 */
if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}

/**
 * Get current user role
 * @return string|null
 */
if (!function_exists('getCurrentUserRole')) {
    function getCurrentUserRole() {
        return $_SESSION['role'] ?? null;
    }
}

/**
 * Get current username
 * @return string|null
 */
if (!function_exists('getCurrentUsername')) {
    function getCurrentUsername() {
        return $_SESSION['username'] ?? null;
    }
}

/**
 * Get current display name
 * @return string|null
 */
if (!function_exists('getCurrentDisplayName')) {
    function getCurrentDisplayName() {
        return $_SESSION['display_name'] ?? null;
    }
}
