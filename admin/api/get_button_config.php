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
 * BESTANDSNAAM: get_button_config.php
 * LOCATIE:      /admin/api/get_button_config.php
 * VERSIE:       2.0 - Met name_display_option
 * ═══════════════════════════════════════════════════════════════════
 */

session_start();
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

try {
    $stmt = $db->query("
        SELECT 
            button1_name, 
            button2_name, 
            button3_name, 
            allow_user_button_names,
            name_display_option
        FROM config 
        WHERE id = 1
    ");
    
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        echo json_encode([
            'success' => false,
            'error' => 'Config not found'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'buttons' => [
            'button1' => [
                'name' => $config['button1_name'] ?? 'PAUZE',
                'color' => '#ff69b4',
                'enabled' => true
            ],
            'button2' => [
                'name' => $config['button2_name'] ?? 'THUISWERKEN',
                'color' => '#9370db',
                'enabled' => true
            ],
            'button3' => [
                'name' => $config['button3_name'] ?? 'VAKANTIE',
                'color' => '#9acd32',
                'enabled' => true
            ]
        ],
        'allowUserCustom' => (bool)($config['allow_user_button_names'] ?? 0),
        'nameDisplayOption' => $config['name_display_option'] ?? 'volledig'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
