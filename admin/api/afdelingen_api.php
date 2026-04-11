<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: afdelingen_api.php
 * LOCATIE:      admin/api/
 * UPLOAD NAAR:  /admin/api/afdelingen_api.php
 * ═══════════════════════════════════════════════════════════════════
 * 
 * Afdelingen API - Provides department data for dropdowns
 * Returns sorted, active departments only
 * 
 * ═══════════════════════════════════════════════════════════════════
 */

header('Content-Type: application/json');

session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

try {
    // Haal alle actieve afdelingen uit database, gesorteerd
    $stmt = $db->query("
        SELECT 
            id,
            afdeling_name,
            afdeling_code,
            sort_order
        FROM afdelingen 
        WHERE active = 1
        ORDER BY sort_order ASC, afdeling_name ASC
    ");
    
    $afdelingen = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Extract alleen de afdeling_name voor frontend dropdown
    $afdelingNames = array_map(function($afd) {
        return $afd['afdeling_name'];
    }, $afdelingen);
    
    echo json_encode([
        'success' => true,
        'afdelingen' => $afdelingNames,
        'count' => count($afdelingNames)
    ]);
    
} catch (PDOException $e) {
    error_log('afdelingen_api.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'afdelingen' => []
    ]);
}
