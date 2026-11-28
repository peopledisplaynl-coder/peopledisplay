<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * infoscreen_web_preview.php
 * Upload naar: /admin/api/infoscreen_web_preview.php
 *
 * Website source preview (2.7.0): fetches a page once and returns the feeds it links to and
 * the lists of news it recognises, so the admin can pick one. POST url, csrf and optionally
 * css[item|title|link|image|date]. Admins with "Infoscherm beheren" only.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../../includes/infoscreen_web.php';
require_once __DIR__ . '/../../includes/infoscreen.php';

if (!isset($_SESSION['user_id']) || !pd_infoscreen_scope_can(pd_infoscreen_scope($db, (int)$_SESSION['user_id']), 'news')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Geen toegang']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !pd_infoscreen_csrf_ok($_POST['csrf'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'De pagina was verlopen. Laad hem opnieuw.']);
    exit;
}
session_write_close(); // the fetch can take a few seconds

$css = [];
foreach (['item', 'title', 'link', 'image', 'date'] as $f) {
    $css[$f] = mb_substr(trim((string)($_POST['css'][$f] ?? '')), 0, 200);
}
$cfg = ($css['item'] !== '') ? ['mode' => 'css', 'css' => $css] : [];

try {
    $r = pd_is_web_preview((string)($_POST['url'] ?? ''), $cfg);
    $out = fn(array $items) => array_map(fn($i) => [
        'title' => $i['title'], 'summary' => mb_strimwidth((string)$i['summary'], 0, 120, '…'), 'image' => $i['image'],
        'link' => $i['link'], 'date' => $i['published'] ? date('j-n-Y', strtotime($i['published'])) : null,
    ], $items);
    echo json_encode([
        'ok' => true,
        'feeds' => array_map(fn($f) => ['url' => $f['url'], 'title' => $f['title'], 'items' => $out($f['items'])], $r['feeds']),
        'groups' => array_map(fn($g) => ['xpath' => $g['xpath'], 'count' => $g['count'], 'items' => $out($g['items'])], $r['groups']),
        'css_items' => $r['css_items'] === null ? null : $out($r['css_items']),
        'js_only' => $r['js_only'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e instanceof RuntimeException ? $e->getMessage() : 'Ophalen is mislukt']);
}
