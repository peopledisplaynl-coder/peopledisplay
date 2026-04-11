<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * ============================================================================
 * BESTANDSNAAM:  get_locations_ordered.php
 * UPLOAD NAAR:   /admin/api/get_locations_ordered.php
 * DATUM:         2024-12-15
 * VERSIE:        2.0 - MET SORT_ORDER
 * 
 * Returns locations sorted by sort_order for building menu
 * ============================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../includes/db.php';

try {
    // Get locations sorted by sort_order
    $stmt = $db->query("
        SELECT location_name, sort_order 
        FROM locations 
        WHERE active = 1 
        ORDER BY sort_order ASC, location_name ASC
    ");
    
    $locations = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    echo json_encode([
        'success' => true,
        'locations' => $locations,
        'count' => count($locations)
    ]);
    
} catch (PDOException $e) {
    // Fallback: get from employees table if locations table doesn't exist
    try {
        $stmt = $db->query("
            SELECT DISTINCT locatie 
            FROM employees 
            WHERE locatie IS NOT NULL AND locatie != '' AND actief = 1
            ORDER BY locatie ASC
        ");
        
        $locations = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        echo json_encode([
            'success' => true,
            'locations' => $locations,
            'count' => count($locations),
            'fallback' => true
        ]);
    } catch (PDOException $e2) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e2->getMessage()
        ]);
    }
}
