<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  session_set_cookie_params([
    'lifetime'=>0,'path'=>'/','domain'=>$_SERVER['HTTP_HOST'],
    'secure'=>$secure,'httponly'=>true,'samesite'=>'Lax'
  ]);
  session_start();
  // === BEGIN PATCH: session id compatibility ===
if (empty($_SESSION['id']) && !empty($_SESSION['user_id'])) {
    $_SESSION['id'] = (int) $_SESSION['user_id'];
}
// === END PATCH: session id compatibility ===

}
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'forbidden']);
  exit;
}

require_once __DIR__ . '/../db_config.php'; // DB_INCLUDE

$id = (int)($_POST['id'] ?? 0);
$new_password = (string)($_POST['new_password'] ?? '');

if ($id <= 0 || $new_password === '') {
  echo json_encode(['success'=>false,'error'=>'missing_fields']);
  exit;
}

try {
  $hash = password_hash($new_password, PASSWORD_DEFAULT);
  $stmt = $pdo->prepare('UPDATE users SET password_hash = :p WHERE id = :id');
  $stmt->execute([':p'=>$hash,':id'=>$id]);
  echo json_encode(['success'=>true]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'db_error']);
}
