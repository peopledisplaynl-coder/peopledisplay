<?php
declare(strict_types=1);

// Environment detection (cross-platform session path, dev mode, etc.)
require_once __DIR__ . '/environment.php';

// Extend session lifetime when a "remember me" cookie is present.
// This ensures the PHP session cookie stays alive for the same period as the remember token.
define('PD_REMEMBER_ME_SECONDS', 30 * 24 * 60 * 60);
$rememberCookiePresent = !empty($_COOKIE['remember_selector']) && !empty($_COOKIE['remember_token']);

if (session_status() === PHP_SESSION_NONE) {
    if ($rememberCookiePresent) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => PD_REMEMBER_ME_SECONDS,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        @ini_set('session.gc_maxlifetime', (string)PD_REMEMBER_ME_SECONDS);
    }
    session_start();
}

// If a remember-me session is active, refresh the cookie expiration on every request.
if (!empty($_SESSION['remember_me']) || $rememberCookiePresent) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(session_name(), session_id(), time() + PD_REMEMBER_ME_SECONDS, '/', $_SERVER['HTTP_HOST'] ?? '', $secure, true);
}

// Attempt to auto-login via "remember me" on every request when not already logged in.
require_once __DIR__ . '/auth.php';
if (empty($_SESSION['user_id'])) {
    pd_try_remember_me_login($db);
}

$cfg = __DIR__ . '/../admin/db_config.php';
if (!file_exists($cfg)) exit('DB config not found. Run install.php first.');
include $cfg;
if (!isset($DB_HOST, $DB_NAME, $DB_USER, $DB_PASS)) exit('DB config incomplete');

define('DB_HOST', $DB_HOST);
define('DB_NAME', $DB_NAME);
define('DB_USER', $DB_USER);
define('DB_PASS', $DB_PASS);
define('DB_PREFIX', '');

// BASE_PATH: empty for root installs, '/subdir' for subdirectory installs
if (!defined('BASE_PATH')) {
    define('BASE_PATH', pd_detect_base_path());
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $DB_HOST, $DB_NAME);
try {
    $db = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('DB error: ' . $e->getMessage());
    http_response_code(500);
    exit(pd_is_development() ? 'DB error: ' . $e->getMessage() : 'DB error');
}
