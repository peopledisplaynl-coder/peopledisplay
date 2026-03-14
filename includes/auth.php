<?php
// Authentication Helper
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
