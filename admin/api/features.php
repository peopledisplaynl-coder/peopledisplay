<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['id'] ?? null;

if (!$userId) {
  echo json_encode(['success' => false, 'error' => 'missing_id']);
  exit;
}

try {
  $stmt = $db->prepare("
    SELECT fk.id, fk.key_name AS name, fk.category,
           COALESCE(uf.visible, 0) AS enabled
    FROM feature_keys fk
    LEFT JOIN user_features uf
      ON uf.feature_key_id = fk.id AND uf.user_id = ?
    ORDER BY fk.category ASC, fk.key_name ASC
  ");
  $stmt->execute([$userId]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Groepeer per categorie
  $grouped = [];
  foreach ($rows as $r) {
    $cat = $r['category'] ?? 'Algemeen';
    if (!isset($grouped[$cat])) $grouped[$cat] = [];
    $grouped[$cat][] = [
      'id' => $r['id'],
      'name' => $r['name'],
      'enabled' => (int)$r['enabled']
    ];
  }

  echo json_encode(['groups' => $grouped]);
} catch (Throwable $e) {
  echo json_encode(['success' => false, 'error' => 'DB error']);
}
