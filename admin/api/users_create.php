<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

// === BEGIN PATCH: session id compatibility ===
if (empty($_SESSION['id']) && !empty($_SESSION['user_id'])) {
    $_SESSION['id'] = (int) $_SESSION['user_id'];
}
// === END PATCH: session id compatibility ===

// 🔒 SECURITY: Both admin and superadmin can create users
$currentRole = $_SESSION['role'] ?? '';
if (!in_array($currentRole, ['admin', 'superadmin'], true)) {
  http_response_code(403);
  echo 'Verboden';
  exit;
}

$isSuperadmin = ($currentRole === 'superadmin');

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'user';
$presentation_id = trim($_POST['presentation_id'] ?? '');

if ($username === '' || $password === '' || !in_array($role, ['user','admin','superadmin'], true)) {
  echo 'Ongeldige invoer';
  exit;
}

// 🔒 SECURITY: Only superadmin can create superadmin accounts
if ($role === 'superadmin' && !$isSuperadmin) {
  echo 'Alleen Superadmins kunnen Superadmin accounts aanmaken';
  exit;
}

// Check license limit before creating user
require_once __DIR__ . '/../../includes/license.php';
if (!canAddUser()) {
    $limits = getTierLimits();
    $_SESSION['pd_limit_alert'] = ['type' => 'users', 'limit' => $limits['max_users']];
    header('Location: ' . BASE_PATH . '/admin/users_manage.php');
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
  $stmt = $db->prepare("INSERT INTO users (username, password_hash, role, presentation_id) VALUES (?, ?, ?, ?)");
  $stmt->execute([$username, $hash, $role, $presentation_id]);
  header('Location: ' . BASE_PATH . '/admin/users_manage.php');
  exit;
} catch (Throwable $e) {
  error_log('users_create.php error: ' . $e->getMessage());
  echo 'Fout bij toevoegen: ' . $e->getMessage();
}
