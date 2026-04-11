<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
declare(strict_types=1);

require_once __DIR__ . '/../db_config.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

// === BEGIN PATCH: session id compatibility ===
if (empty($_SESSION['id']) && !empty($_SESSION['user_id'])) {
    $_SESSION['id'] = (int) $_SESSION['user_id'];
}
// === END PATCH: session id compatibility ===

// 🔒 SECURITY: Only superadmin can delete users
if (($_SESSION['role'] ?? '') !== 'superadmin') {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'forbidden', 'message' => 'Alleen Superadmins kunnen gebruikers verwijderen']);
  exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$id = intval($data['id'] ?? 0);

if ($id <= 0) {
  echo json_encode(['success'=>false,'error'=>'invalid_id']);
  exit;
}

try {
  // 🔒 CRITICAL: Check if target user is a superadmin
  $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
  $stmt->execute([$id]);
  $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$targetUser) {
    echo json_encode(['success'=>false,'error'=>'user_not_found']);
    exit;
  }
  
  // Even superadmins cannot delete other superadmin accounts (for safety)
  // If you want to allow this, remove this check
  if ($targetUser['role'] === 'superadmin') {
    echo json_encode([
      'success'=>false,
      'error'=>'cannot_delete_superadmin',
      'message' => 'Superadmin accounts kunnen niet worden verwijderd voor veiligheid'
    ]);
    exit;
  }
  
  // Proceed with deletion
  $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
  $stmt->execute([$id]);
  echo json_encode(['success'=>true]);
  
} catch (Throwable $e) {
  error_log('users_delete.php error: ' . $e->getMessage());
  echo json_encode(['success'=>false,'error'=>'db_error', 'message' => $e->getMessage()]);
}
