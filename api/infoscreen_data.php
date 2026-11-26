<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * infoscreen_data.php
 * Upload naar: /api/infoscreen_data.php
 *
 * Everything the infoscreen (infoscherm.php) shows, filtered for the locations of the
 * signed-in screen account: messages, news, weather, display settings and the Google
 * Slides ID for the combined mode. Managers may pass ?as=<user id> to preview the
 * screen of another account. When sources are due, they are fetched after the
 * response has been sent (or, without PHP-FPM, briefly before it).
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/version.php';
require_once __DIR__ . '/../includes/infoscreen.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit;
}
$userId = (int)$_SESSION['user_id'];
// ?as=<id>: preview of another screen account (Infoscherm › Schermen), only for managers
if (isset($_GET['as']) && (int)$_GET['as'] !== $userId) {
    require_once __DIR__ . '/../admin/auth_helper.php';
    if (!hasAdminFeature('manage_infoscreen')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Geen toegang']);
        exit;
    }
    $userId = (int)$_GET['as'];
}
session_write_close(); // don't block other requests of this screen

try {
    pd_infoscreen_ensure_schema($db);

    $due = pd_infoscreen_refresh_due($db);
    $canFinishEarly = function_exists('fastcgi_finish_request');
    if ($due && !$canFinishEarly) {
        pd_infoscreen_refresh($db, false, 10);
        $due = false;
    }

    $stmt = $db->prepare("SELECT u.screen_mode, u.presentation_id, u.features, c.presentationID AS global_presentation
                          FROM users u LEFT JOIN config c ON c.id = 1 WHERE u.id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $base = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/x.php'))), '/');
    $data = pd_infoscreen_screen_data($db, pd_infoscreen_user_locations($db, $userId), $base,
        pd_infoscreen_screen_overrides($user['features'] ?? null));

    $presentationId = trim((string)($user['presentation_id'] ?? ''));
    if ($presentationId === '') {
        $presentationId = trim((string)($user['global_presentation'] ?? ''));
    }

    echo json_encode([
        'success'         => true,
        'version'         => PD_CURRENT_VERSION,
        'server_time'     => date('c'),
        'screen_mode'     => in_array($user['screen_mode'] ?? '', PD_IS_MODES, true) ? $user['screen_mode'] : 'slides',
        'presentation_id' => $presentationId !== '' ? $presentationId : null,
    ] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    error_log('infoscreen_data: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gegevens konden niet worden geladen']);
    exit;
}

// Fetch due sources after the screen has its answer.
if ($due && $canFinishEarly) {
    fastcgi_finish_request();
    ignore_user_abort(true);
    try {
        pd_infoscreen_refresh($db, false, 25);
    } catch (\Throwable $e) {
        error_log('infoscreen refresh: ' . $e->getMessage());
    }
}
