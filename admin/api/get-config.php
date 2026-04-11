<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

try {
    $stmt = $db->prepare(
        'SELECT scriptURL, sheetID, presentationID, visibleFields, locations, extraButtons,
                allow_auto_fullscreen, presentationAutoShowMs
         FROM config
         WHERE id = 1
         LIMIT 1'
    );
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(500);
        echo json_encode(['error' => 'No config found']);
        exit;
    }

    // Parse JSON velden
    $row['visibleFields'] = json_decode($row['visibleFields'] ?? '[]', true);
    $row['locations']     = json_decode($row['locations'] ?? '[]', true);
    $row['extraButtons']  = json_decode($row['extraButtons'] ?? '[]', true);

    // ✅ GEFIXED: Gebruik de ECHTE scriptURL uit database!
    // Alleen als scriptURL leeg is, gebruik proxy als fallback
    if (empty($row['scriptURL'])) {
        $row['scriptURL'] = BASE_PATH . '/admin/api/proxy.php';
    }
    // Anders: gebruik gewoon de scriptURL uit de database zoals ingevuld!

    // Normaliseer types
    $row['allow_auto_fullscreen'] = !empty($row['allow_auto_fullscreen']) ? true : false;
    
    if (isset($row['presentationAutoShowMs'])) {
        $row['presentationAutoShowMs'] = is_numeric($row['presentationAutoShowMs'])
            ? (int)$row['presentationAutoShowMs']
            : null;
    } else {
        $row['presentationAutoShowMs'] = null;
    }

    echo json_encode($row, JSON_PRETTY_PRINT);
    exit;

} catch (Throwable $e) {
    error_log('[get-config.php] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'details' => $e->getMessage()]);
    exit;
}
