<?php
/**
 * Filename: check_session.php
 * Location: /api/check_session.php
 * 
 * Simple session validity checker
 * Returns JSON: {"valid": true/false}
 */

header('Content-Type: application/json');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

session_start();

$valid = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

echo json_encode([
    'valid' => $valid,
    'user_id' => $valid ? $_SESSION['user_id'] : null
]);
