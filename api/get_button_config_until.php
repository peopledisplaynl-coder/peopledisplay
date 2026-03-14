<?php
/**
 * ============================================================================
 * API: Get Button Config (with ask_until settings)
 * ============================================================================
 * Bestand: get_button_config_until.php
 * Locatie: /api/get_button_config_until.php
 * 
 * Returns button names and whether to ask for until date/time
 * ============================================================================
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

try {
    $stmt = $db->query("
        SELECT 
            button1_name, button1_ask_until,
            button2_name, button2_ask_until,
            button3_name, button3_ask_until
        FROM config 
        WHERE id = 1
    ");
    
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($config) {
        echo json_encode([
            'success' => true,
            'buttons' => [
                'button1' => [
                    'name' => $config['button1_name'],
                    'ask_until' => (bool)$config['button1_ask_until']
                ],
                'button2' => [
                    'name' => $config['button2_name'],
                    'ask_until' => (bool)$config['button2_ask_until']
                ],
                'button3' => [
                    'name' => $config['button3_name'],
                    'ask_until' => (bool)$config['button3_ask_until']
                ]
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Config not found'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
