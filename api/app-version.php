<?php
/**
 * app-version.php
 * Upload naar: /api/app-version.php
 *
 * Lichtgewicht endpoint dat alleen de huidige versie teruggeeft.
 * Wordt gebruikt door app.js om te controleren of de pagina herladen moet worden.
 */

// Geen sessie nodig — publiek leesbaar
require_once __DIR__ . '/../includes/version.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

echo json_encode([
    'version' => PD_CURRENT_VERSION,
    'ts'      => time()
]);
