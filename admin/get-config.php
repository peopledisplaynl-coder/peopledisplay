<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: get-config.php
 * LOCATIE:      admin/
 * UPLOAD NAAR:  /admin/get-config.php
 * ═══════════════════════════════════════════════════════════════════
 * 
 * PeopleDisplay v2.0 - Config API (MYSQL ONLY FIX)
 * 
 * CHANGELOG v2.0:
 * - FIXED: Gebruikt MYSQL API ipv Google Sheets proxy
 * - scriptURL verwijst nu naar employees_api.php (MySQL direct)
 * - Geen Google Sheets meer!
 * 
 * ═══════════════════════════════════════════════════════════════════
 */

header('Content-Type: application/json');

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

try {
    // Get config from database
    $stmt = $db->query("SELECT * FROM config WHERE id = 1 LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        // Create default config if not exists
        $db->exec("INSERT INTO config (id) VALUES (1)");
        $stmt = $db->query("SELECT * FROM config WHERE id = 1 LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // ✅ v2.0 FIX: Gebruik MySQL API ipv Google Sheets proxy
    $response = [
        'scriptURL' => '/admin/api/employees_api.php', // ✅ MYSQL ONLY!
        'sheetID' => null, // Niet meer gebruikt in v2.0
        'presentationID' => $config['presentationID'] ?? null,
        'visibleFields' => $config['visibleFields'] ? json_decode($config['visibleFields'], true) : [],
        'locations' => $config['locations'] ? json_decode($config['locations'], true) : [],
        'locations_order' => $config['locations_order'] ? json_decode($config['locations_order'], true) : [],
        'extraButtons' => $config['extraButtons'] ? json_decode($config['extraButtons'], true) : [],
        'button1_name' => $config['button1_name'] ?? 'PAUZE',
        'button2_name' => $config['button2_name'] ?? 'THUISWERKEN',
        'button3_name' => $config['button3_name'] ?? 'VAKANTIE',
        'allow_user_button_names' => (bool)($config['allow_user_button_names'] ?? 0),
        'allow_auto_fullscreen' => (bool)($config['allow_auto_fullscreen'] ?? 0),
        'presentationAutoShowMs' => (int)($config['presentationAutoShowMs'] ?? 120000),
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log('get-config.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
