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


if (($_SESSION['role'] ?? '') !== 'superadmin') {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'forbidden']);
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
  $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
  $stmt->execute([$id]);
  echo json_encode(['success'=>true]);
} catch (Throwable $e) {
  echo json_encode(['success'=>false,'error'=>'db_error']);
}
