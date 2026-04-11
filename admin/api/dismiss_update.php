<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * Dismiss update notification for a specific version.
 * File: /admin/api/dismiss_update.php
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/update_check.php';

// Admin only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Geen toegang']);
    exit;
}

$input   = json_decode(file_get_contents('php://input'), true);
$version = trim($input['version'] ?? '');

if ($version === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Geen versie opgegeven']);
    exit;
}

echo json_encode(['success' => dismissUpdate($version)]);
