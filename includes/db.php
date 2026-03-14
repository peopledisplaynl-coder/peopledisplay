<?php
declare(strict_types=1);

// Environment detection (cross-platform session path, dev mode, etc.)
require_once __DIR__ . '/environment.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
