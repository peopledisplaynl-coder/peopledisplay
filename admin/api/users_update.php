<?php
declare(strict_types=1);

require_once __DIR__ . '/../db_config.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

// === BEGIN PATCH: session id compatibility ===
if (empty($_SESSION['id']) && !empty($_SESSION['user_id'])) {
    $_SESSION['id'] = (int) $_SESSION['user_id'];
}
// === END PATCH: session id compatibility ===

// 🔒 SECURITY: Both admin and superadmin can access, but with restrictions
$currentRole = $_SESSION['role'] ?? '';
if (!in_array($currentRole, ['admin', 'superadmin'], true)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'forbidden']);
  exit;
}

$isSuperadmin = ($currentRole === 'superadmin');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$id = intval($data['id'] ?? 0);
$name = trim($data['display_name'] ?? '');
$role = $data['role'] ?? '';
$presentation_id = trim($data['presentation_id'] ?? '');

if ($id <= 0 || $name === '' || !in_array($role, ['user','admin','superadmin'], true)) {
  echo json_encode(['success'=>false,'error'=>'invalid_input']);
  exit;
}

try {
  // 🔒 CRITICAL: Get target user's current role
  $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
  $stmt->execute([$id]);
  $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$targetUser) {
    echo json_encode(['success'=>false,'error'=>'user_not_found']);
    exit;
  }
  
  $targetIsSuperadmin = ($targetUser['role'] === 'superadmin');
  
  // 🔒 SECURITY CHECK: Admin cannot modify superadmin accounts
  if (!$isSuperadmin && $targetIsSuperadmin) {
    echo json_encode([
      'success'=>false,
      'error'=>'forbidden',
      'message'=>'Je kunt geen Superadmin accounts wijzigen'
    ]);
    exit;
  }
  
  // 🔒 SECURITY CHECK: Admin cannot change anyone's role to superadmin
  if (!$isSuperadmin && $role === 'superadmin') {
    echo json_encode([
      'success'=>false,
      'error'=>'forbidden',
      'message'=>'Alleen Superadmins kunnen Superadmin rechten toekennen'
    ]);
    exit;
  }
  
  // 🔒 SECURITY CHECK: Admin cannot demote a superadmin
  if (!$isSuperadmin && $targetIsSuperadmin && $role !== 'superadmin') {
    echo json_encode([
      'success'=>false,
      'error'=>'forbidden',
      'message'=>'Je kunt geen Superadmin downgraden'
    ]);
    exit;
  }
  
  // All checks passed - proceed with update
  $stmt = $db->prepare("UPDATE users SET display_name = ?, role = ?, presentation_id = ? WHERE id = ?");
  $stmt->execute([$name, $role, $presentation_id, $id]);
  echo json_encode(['success'=>true]);
  
} catch (Throwable $e) {
  error_log('users_update.php error: ' . $e->getMessage());
  echo json_encode(['success'=>false,'error'=>'db_error']);
}
