<?php
declare(strict_types=1);
header('Content-Type: application/json');

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

try {
  require_once __DIR__ . '/../../includes/db.php';

  $stmt = $db->query("SELECT id, username AS name FROM users ORDER BY username ASC");
  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['users' => $users]);
} catch (Throwable $e) {
  echo json_encode([
    'success' => false,
    'error' => 'DB error',
    'details' => $e->getMessage()
  ]);
}
