<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * infoscreen_teams.php
 * Upload naar: /api/infoscreen_teams.php
 *
 * Endpoint for a Microsoft Teams "outgoing webhook" (2.5.0). In a team, someone writes
 * "@Infoscherm Titel …" in a channel; Teams POSTs the message here, signed with HMAC-SHA256
 * and the security token Teams showed when the webhook was made. The token is stored
 * encrypted under Infoscherm → Koppelingen. ?k=<id> says which connection it is.
 * Teams shows our answer in the channel (within 5 seconds).
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/twofa.php';
require_once __DIR__ . '/../includes/infoscreen_api.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function pd_teams_reply(int $status, string $text): void
{
    http_response_code($status);
    echo json_encode(['type' => 'message', 'text' => $text], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    pd_teams_reply(405, 'Alleen POST');
}
if (!pd_infoscreen_ensure_schema($db)) {
    pd_teams_reply(503, 'Database is niet beschikbaar');
}

$stmt = $db->prepare("SELECT * FROM infoscreen_api_keys WHERE id = ? AND type = 'teams' AND active = 1");
$stmt->execute([(int)($_GET['k'] ?? 0)]);
$keyRow = $stmt->fetch(PDO::FETCH_ASSOC);
$secret = $keyRow && $keyRow['secret_enc'] ? pd_2fa_decrypt($keyRow['secret_enc']) : null;
if (!$keyRow || !$secret) {
    pd_teams_reply(401, 'Deze koppeling is niet (meer) actief. Vraag de beheerder van PeopleDisplay om het beveiligingstoken in te vullen.');
}

$raw = (string)file_get_contents('php://input', false, null, 0, 256 * 1024);
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if ($auth === '' && function_exists('getallheaders')) {
    foreach (getallheaders() as $k => $v) {
        if (strcasecmp($k, 'Authorization') === 0) $auth = $v;
    }
}
if (!pd_is_teams_verify($secret, $raw, $auth)) {
    pd_teams_reply(401, 'Ongeldige handtekening');
}
if (!pd_is_api_rate_ok($db, $keyRow)) {
    pd_teams_reply(429, 'Te veel berichten dit uur. Probeer het later opnieuw.');
}

$activity = json_decode($raw, true);
if (!is_array($activity)) {
    pd_teams_reply(400, 'Onleesbaar bericht');
}
try {
    pd_teams_reply(200, pd_is_teams_handle($db, $keyRow, $activity, dirname(__DIR__)));
} catch (\Throwable $e) {
    error_log('infoscreen_teams: ' . $e->getMessage());
    pd_teams_reply(200, 'Er ging iets mis bij het plaatsen. Probeer het nog eens of vraag de beheerder.');
}
