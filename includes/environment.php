<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * ============================================================
 * PeopleDisplay — Environment Auto-Detection
 * ============================================================
 * File:    includes/environment.php
 * Purpose: Detect hosting environment and configure settings
 *          automatically for XAMPP, Strato, cPanel, Plesk,
 *          VPS, and localhost installs.
 *
 * Usage:   Required automatically by includes/db.php
 *          Do NOT call this file directly.
 * ============================================================
 */

if (!defined('PD_ENV_LOADED')) {
    define('PD_ENV_LOADED', true);
}

// ============================================================
// 1. PHP VERSION CHECK
// ============================================================
if (PHP_VERSION_ID < 70400) {
    http_response_code(500);
    die('PeopleDisplay requires PHP 7.4 or higher. Current version: ' . PHP_VERSION);
}

// ============================================================
// 2. DETECT ENVIRONMENT TYPE
// ============================================================

/**
 * Returns one of: 'xampp', 'cpanel', 'plesk', 'strato', 'vps', 'localhost', 'unknown'
 */
function pd_detect_environment(): string {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $phpSelf = $_SERVER['PHP_SELF'] ?? '';

    // XAMPP (Windows or Mac)
    if (
        stripos($docRoot, 'xampp') !== false ||
        stripos($docRoot, 'htdocs') !== false ||
        (PHP_OS_FAMILY === 'Windows' && ($host === 'localhost' || $host === '127.0.0.1'))
    ) {
        return 'xampp';
    }

    // Localhost (Linux dev, Docker, etc.)
    if ($host === 'localhost' || $host === '127.0.0.1' || substr($host, 0, 3) === '10.' || substr($host, 0, 7) === '192.168') {
        return 'localhost';
    }

    // Strato hints
    if (
        stripos($host, 'strato') !== false ||
        stripos($docRoot, 'strato') !== false ||
        stripos(php_uname('n'), 'strato') !== false
    ) {
        return 'strato';
    }

    // cPanel hints
    if (
        @file_exists('/usr/local/cpanel') ||
        stripos($docRoot, 'public_html') !== false ||
        stripos($serverSoftware, 'cpanel') !== false
    ) {
        return 'cpanel';
    }

    // Plesk hints
    if (
        @file_exists('/usr/local/psa') ||
        stripos($docRoot, 'httpdocs') !== false ||
        stripos($serverSoftware, 'plesk') !== false
    ) {
        return 'plesk';
    }

    // VPS / dedicated (Linux, not shared)
    if (PHP_OS_FAMILY === 'Linux' && (
        stripos($docRoot, '/var/www') !== false ||
        stripos($docRoot, '/srv/') !== false
    )) {
        return 'vps';
    }

    return 'unknown';
}

// ============================================================
// 3. DETECT WRITABLE SESSION PATH
// ============================================================

/**
 * Returns the best writable path for PHP sessions.
 * Tries multiple candidates in priority order.
 */
function pd_detect_session_path(): string {
    $candidates = [];

    // Project-local sessions directory (works everywhere, preferred for isolation)
    $localSessions = dirname(__DIR__) . '/tmp/sessions';
    $candidates[] = $localSessions;

    // System temp directories (platform-specific)
    if (PHP_OS_FAMILY === 'Windows') {
        $candidates[] = sys_get_temp_dir();
        $candidates[] = 'C:/Windows/Temp';
    } else {
        $candidates[] = '/var/tmp';       // Strato and most Linux
        $candidates[] = '/tmp';           // Standard Linux
        $candidates[] = sys_get_temp_dir();
    }

    foreach ($candidates as $path) {
        if ($path && @is_dir($path) && @is_writable($path)) {
            return $path;
        }
    }

    // Last resort: use PHP default (ini setting)
    return '';
}

// ============================================================
// 4. DETECT BASE PATH (for subdirectory installs)
// ============================================================

/**
 * Returns the web base path for this install.
 * Empty string = root install (domain.com/)
 * '/subdir'    = subdirectory install (domain.com/subdir/)
 */
function pd_detect_base_path(): string {
    if (PHP_SAPI === 'cli') {
        return '';
    }

    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    // Walk up through admin/, api/, includes/, cron/ etc.
    $scriptDir = preg_replace('#/(admin|api|includes|cron|user|install|bhv-print)(/.*)?$#', '', $scriptDir);
    return rtrim($scriptDir, '/');
}

// ============================================================
// 5. CHECK REQUIRED EXTENSIONS
// ============================================================

/**
 * Returns array of missing critical extensions.
 */
function pd_check_extensions(): array {
    $required = ['pdo', 'pdo_mysql', 'json', 'session'];
    $missing = [];
    foreach ($required as $ext) {
        if (!extension_loaded($ext)) {
            $missing[] = $ext;
        }
    }
    return $missing;
}

/**
 * Returns array of optional extensions with their status.
 */
function pd_check_optional_extensions(): array {
    $optional = ['gd', 'curl', 'zip', 'openssl'];
    $status = [];
    foreach ($optional as $ext) {
        $status[$ext] = extension_loaded($ext);
    }
    return $status;
}

// ============================================================
// 6. APPLY ENVIRONMENT SETTINGS
// ============================================================

$_PD_ENV = pd_detect_environment();
$_PD_SESSION_PATH = pd_detect_session_path();

// Only override the session path in local development (XAMPP / localhost).
// On production, using the project-local tmp/sessions path causes a redirect
// loop: login.php (which requires db.php first) saves the session there, but
// any file that calls session_start() before db.php reads from PHP's default
// path and sees an empty session → redirects to login → infinite loop.
// Production hosts already have a sane session.save_path in php.ini.
if (session_status() === PHP_SESSION_NONE
    && !empty($_PD_SESSION_PATH)
    && in_array($_PD_ENV, ['xampp', 'localhost'], true)
) {
    session_save_path($_PD_SESSION_PATH);
}

// Expose environment name for debugging
if (!defined('PD_ENVIRONMENT')) {
    define('PD_ENVIRONMENT', $_PD_ENV);
}

// ============================================================
// 7. DEVELOPMENT MODE DETECTION
// ============================================================

/**
 * Returns true if running in a local development environment.
 */
function pd_is_development(): bool {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $env = PD_ENVIRONMENT;
    return in_array($env, ['xampp', 'localhost']) ||
           $host === 'localhost' ||
           $host === '127.0.0.1' ||
           substr($host, -6) === '.local' ||
           substr($host, -4) === '.dev';
}

// Enable error reporting in development only
if (pd_is_development()) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

// ============================================================
// 8. ALERT ON MISSING CRITICAL EXTENSIONS
// ============================================================
$_PD_MISSING_EXTENSIONS = pd_check_extensions();
if (!empty($_PD_MISSING_EXTENSIONS) && PHP_SAPI !== 'cli') {
    http_response_code(500);
    $missing = implode(', ', $_PD_MISSING_EXTENSIONS);
    die("PeopleDisplay — Missing required PHP extensions: {$missing}\n" .
        "Please contact your hosting provider or server administrator.");
}
