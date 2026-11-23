<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * cron_infoscreen.php
 * Upload naar: /cron/cron_infoscreen.php
 *
 * Fetches the infoscreen news feeds and weather that are due (every 15 minutes at most
 * per source). Optional: screens also trigger a fetch when the data is older than
 * 15 minutes, so the infoscreen works without this cron job — the cron job just keeps
 * screens from ever waiting for it.
 *
 * Hosting panel (every 15 minutes):  php /pad/naar/peopledisplay/cron/cron_infoscreen.php
 * Without CLI cron: cron_endpoint.php?action=infoscreen_refresh&key=…  (cron-job.org)
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Alleen via cron (CLI). Gebruik cron_endpoint.php?action=infoscreen_refresh voor een web-cron.');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/infoscreen.php';

$force = in_array('--force', $argv ?? [], true);
$run = pd_infoscreen_refresh($db, $force, 50);

if (!$run['ran']) {
    echo '[' . date('Y-m-d H:i:s') . "] Infoscherm: niets gedaan (al bezig of geen database)\n";
    exit(0);
}
foreach ($run['results'] as $id => $r) {
    echo '[' . date('Y-m-d H:i:s') . "] Bron #$id {$r['name']}: " . ($r['ok'] ? 'OK' : 'FOUT') . " — {$r['message']}\n";
}
echo '[' . date('Y-m-d H:i:s') . '] Infoscherm: ' . count($run['results']) . " bron(nen) bijgewerkt\n";
exit(0);
