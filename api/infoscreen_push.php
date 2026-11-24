<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * infoscreen_push.php
 * Upload naar: /api/infoscreen_push.php
 *
 * Berichten-API for the infoscreen (2.5.0). No login session: every request carries the key
 * of a connection made under Infoscherm → Koppelingen ("Authorization: Bearer pdis_…" or
 * "X-Api-Key: pdis_…"). JSON or form fields.
 *
 *   POST {"title": "…", "body": "…", "image_url"|"image_base64": "…", "locations": ["…"],
 *         "starts_at": "…", "ends_at": "…", "priority": true, "color": "rood",
 *         "duration_seconds": 15, "external_id": "…", "author": "…"}   → create or update
 *   POST {"action": "delete", "external_id": "…"} or {"action": "delete", "id": 12}
 *   GET  (or POST {"action": "list"})                                   → own current messages
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/request_guard.php';
require_once __DIR__ . '/../includes/infoscreen_api.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function pd_push_reply(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
if (!pd_infoscreen_ensure_schema($db)) {
    pd_push_reply(503, ['success' => false, 'error' => 'Database is niet beschikbaar']);
}

$keyRow = pd_is_api_find_key($db, pd_is_api_request_key());
if (!$keyRow) {
    header('WWW-Authenticate: Bearer');
    pd_push_reply(401, ['success' => false, 'error' => 'Ongeldige of ontbrekende API-sleutel']);
}
if (!pd_is_api_rate_ok($db, $keyRow)) {
    header('Retry-After: 3600');
    pd_push_reply(429, ['success' => false, 'error' => 'Te veel verzoeken: maximaal ' . PD_IS_API_PER_HOUR . ' per uur per koppeling']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$in = $method === 'GET' ? $_GET : pd_read_json_or_post();
$action = $method === 'GET' ? 'list' : ($method === 'DELETE' ? 'delete' : strtolower((string)($in['action'] ?? 'save')));
if ($method === 'DELETE') {
    $in += $_GET;
}
$appRoot = dirname(__DIR__);

try {
    switch ($action) {
        case 'list':
            pd_push_reply(200, ['success' => true, 'messages' => pd_is_api_list_messages($db, $keyRow)]);

        case 'delete':
            $id = isset($in['id']) && $in['id'] !== '' ? (int)$in['id'] : null;
            $ext = isset($in['external_id']) && $in['external_id'] !== '' ? (string)$in['external_id'] : null;
            if (!$id && $ext === null) pd_push_reply(400, ['success' => false, 'error' => 'Geef id of external_id op']);
            $ok = pd_is_api_delete_message($db, $keyRow, $id, $ext, $appRoot);
            pd_push_reply($ok ? 200 : 404, $ok ? ['success' => true, 'deleted' => true] : ['success' => false, 'error' => 'Bericht niet gevonden']);

        case 'save':
        case 'create':
        case 'update':
            if ($method !== 'POST') pd_push_reply(405, ['success' => false, 'error' => 'Gebruik POST']);
            $r = pd_is_api_save_message($db, $keyRow, $in, $appRoot, 'api');
            pd_push_reply($r['updated'] ? 200 : 201, ['success' => true] + $r);

        default:
            pd_push_reply(400, ['success' => false, 'error' => 'Onbekende action (save, delete of list)']);
    }
} catch (RuntimeException $e) {
    pd_push_reply(400, ['success' => false, 'error' => $e->getMessage()]);
} catch (\Throwable $e) {
    error_log('infoscreen_push: ' . $e->getMessage());
    pd_push_reply(500, ['success' => false, 'error' => 'Er ging iets mis bij het opslaan']);
}
