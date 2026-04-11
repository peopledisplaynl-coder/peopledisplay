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
 * BESTANDSNAAM:  get_sorted_filters.php
 * UPLOAD NAAR:   /api/get_sorted_filters.php
 * DATUM:         2024-12-15
 * VERSIE:        1.0
 * 
 * Returns locations and afdelingen sorted by sort_order for frontend filters
 * ============================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/db.php';

try {
    $response = [
        'success' => true,
        'locations' => [],
        'afdelingen' => []
    ];
    
    // Get locations sorted by sort_order
    try {
        $stmt = $db->query("
            SELECT location_name, sort_order 
            FROM locations 
            WHERE active = 1 
            ORDER BY sort_order ASC, location_name ASC
        ");
        $response['locations'] = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (PDOException $e) {
        // Fallback: get unique locations from employees if table doesn't exist
        $stmt = $db->query("
            SELECT DISTINCT locatie 
            FROM employees 
            WHERE locatie IS NOT NULL AND locatie != '' AND actief = 1
            ORDER BY locatie ASC
        ");
        $response['locations'] = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }
    
    // Get afdelingen sorted by sort_order
    try {
        $stmt = $db->query("
            SELECT afdeling_name, sort_order 
            FROM afdelingen 
            WHERE active = 1 
            ORDER BY sort_order ASC, afdeling_name ASC
        ");
        $response['afdelingen'] = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (PDOException $e) {
        // Fallback: get unique afdelingen from employees if table doesn't exist
        $stmt = $db->query("
            SELECT DISTINCT afdeling 
            FROM employees 
            WHERE afdeling IS NOT NULL AND afdeling != '' AND actief = 1
            ORDER BY afdeling ASC
        ");
        $response['afdelingen'] = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
