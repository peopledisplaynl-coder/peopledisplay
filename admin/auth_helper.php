<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
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
        
        if (!in_array($userRole, ['admin', 'superadmin', 'employee_manager', 'user_manager'])) {
            // User is logged in but not admin - redirect to frontpage
            header('Location: ../frontpage.php');
            exit;
        }

        // Prevent stale admin page caches in browser
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

        // Heartbeat — registreer activiteit voor online gebruikers overzicht
        // Alleen bij GET requests om dubbele pings bij POST te vermijden
        if (isset($_SESSION['user_id']) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            try {
                global $db;
                if ($db instanceof PDO) {
                    $sid = session_id();
                    $uid = $_SESSION['user_id'];
                    $ip  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                    $url = $_SERVER['REQUEST_URI'] ?? null;
                    $browser = 'Unknown';
                    if (str_contains($ua, 'Edg')) $browser = 'Edge';
                    elseif (str_contains($ua, 'Chrome')) $browser = 'Chrome';
                    elseif (str_contains($ua, 'Firefox')) $browser = 'Firefox';
                    elseif (str_contains($ua, 'Safari')) $browser = 'Safari';
                    $device = preg_match('/mobile/i', $ua) ? 'Mobile' : (preg_match('/tablet|ipad/i', $ua) ? 'Tablet' : 'Desktop');
                    $stmt = $db->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, browser, device, page_url, login_time, last_activity, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1) ON DUPLICATE KEY UPDATE last_activity = NOW(), page_url = VALUES(page_url), is_active = 1");
                    $stmt->execute([$uid, $sid, $ip, $ua, $browser, $device, $url]);
                }
            } catch (Throwable $e) {
                // Nooit de admin pagina blokkeren door heartbeat fout
            }
        }
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
 * Check if the logged-in admin has a specific feature right.
 * SuperAdmins always have access.
 * Admins only if the feature is in their admin_features JSON, or if admin_features is empty (all allowed).
 * @param string $feature Feature key, e.g. 'manage_locations'
 * @return bool
 */
if (!function_exists('hasAdminFeature')) {
    function hasAdminFeature(string $feature): bool {
        global $db;
        $role = $_SESSION['role'] ?? '';

        // SuperAdmin mag altijd alles
        if ($role === 'superadmin') return true;

        // Geen admin = geen toegang
        if (!in_array($role, ['admin', 'employee_manager', 'user_manager'])) return false;

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) return false;

        try {
            $stmt = $db->prepare("SELECT features FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || empty($row['features'])) return true; // Leeg = alles toegestaan

            $features = json_decode($row['features'], true) ?? [];

            // Als admin_features niet bestaat = alles toegestaan (backward compatible)
            if (!isset($features['admin_features'])) return true;

            return !empty($features['admin_features'][$feature]);
        } catch (Exception $e) {
            return true; // Bij fout: toegang verlenen (fail open)
        }
    }
}

/**
 * Redirect naar dashboard als admin de feature niet heeft.
 * @param string $feature Feature key, e.g. 'manage_locations'
 * @return void
 */
if (!function_exists('requireAdminFeature')) {
    function requireAdminFeature(string $feature): void {
        if (!hasAdminFeature($feature)) {
            header('Location: dashboard.php?error=no_permission');
            exit;
        }
    }
}
