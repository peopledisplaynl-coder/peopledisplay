<?php
// admin/api/features_update.php — aangepast op jouw structuur
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/db.php';

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if ($payload === null) $payload = $_POST;

$userId = isset($payload['user_id']) ? intval($payload['user_id']) : 0;
$featureId = isset($payload['feature_id']) ? intval($payload['feature_id']) : 0;
$enabled = isset($payload['enabled']) ? (bool)$payload['enabled'] : null;

if ($userId <= 0 || $featureId <= 0 || !is_bool($enabled)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid_input']);
    exit;
}

try {
    $stmt = $db->prepare("
        INSERT INTO user_features (user_id, feature_key_id, visible)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE visible = VALUES(visible)
    ");
    $stmt->execute([$userId, $featureId, $enabled ? 1 : 0]);

    echo json_encode(['success' => true]);
    exit;
} catch (Throwable $ex) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'server_exception',
        'message' => $ex->getMessage()
    ]);
    exit;
}
