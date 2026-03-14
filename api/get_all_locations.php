<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: get_all_locations.php
 * LOCATIE:      /api/get_all_locations.php
 * DOEL:         Haal alle actieve locaties op voor multi-location selector
 * ═══════════════════════════════════════════════════════════════════
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

try {
    // Haal alle actieve locaties op
    $stmt = $db->prepare("
        SELECT 
            id,
            location_name,
            location_code,
            sort_order
        FROM locations
        WHERE active = 1
        ORDER BY sort_order ASC, location_name ASC
    ");
    
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'locations' => $locations,
        'count' => count($locations)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
