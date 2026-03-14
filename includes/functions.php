<?php
/**
 * Global Functions - Backward Compatibility
 * Location: /includes/functions.php
 * 
 * This file provides backward compatibility for old code
 * that uses getDbConnection() instead of the $db variable
 */

// Make sure db.php is loaded
if (!isset($db)) {
    require_once __DIR__ . '/db.php';
}

/**
 * Get database connection (backward compatibility)
 * Old code uses: $db = getDbConnection();
 * New code uses: $db variable directly from includes/db.php
 * 
 * @return PDO Database connection
 */
function getDbConnection() {
    global $db;
    
    if (!isset($db)) {
        require_once __DIR__ . '/db.php';
    }
    
    return $db;
}

/**
 * Check if user is logged in
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
}

/**
 * Check if user has required role
 */
function requireRole($role) {
    requireLogin();
    
    $allowedRoles = [
        'superadmin' => ['superadmin'],
        'admin' => ['superadmin', 'admin'],
        'user' => ['superadmin', 'admin', 'user']
    ];
    
    if (!in_array($_SESSION['role'], $allowedRoles[$role] ?? [])) {
        http_response_code(403);
        die('Access denied');
    }
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Sanitize output
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}
