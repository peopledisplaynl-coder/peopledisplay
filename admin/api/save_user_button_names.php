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
 * BESTANDSNAAM: save_user_button_names.php
 * LOCATIE:      /admin/api/save_user_button_names.php
 * BESCHRIJVING: Save custom button names for logged-in user
 * ═══════════════════════════════════════════════════════════════════
 */

session_start();
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get POST data
$button1_name = $_POST['button1_name'] ?? null;
$button2_name = $_POST['button2_name'] ?? null;
$button3_name = $_POST['button3_name'] ?? null;

// Validate: max 20 characters each
if ($button1_name && strlen($button1_name) > 20) {
    echo json_encode(['success' => false, 'error' => 'Button 1 name te lang (max 20 karakters)']);
    exit;
}
if ($button2_name && strlen($button2_name) > 20) {
    echo json_encode(['success' => false, 'error' => 'Button 2 name te lang (max 20 karakters)']);
    exit;
}
if ($button3_name && strlen($button3_name) > 20) {
    echo json_encode(['success' => false, 'error' => 'Button 3 name te lang (max 20 karakters)']);
    exit;
}

try {
    // Check if record exists
    $stmt = $db->prepare("SELECT id FROM user_button_names WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Update existing
        $stmt = $db->prepare("
            UPDATE user_button_names 
            SET button1_name = ?, 
                button2_name = ?, 
                button3_name = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([
            $button1_name ?: null,
            $button2_name ?: null,
            $button3_name ?: null,
            $user_id
        ]);
    } else {
        // Insert new
        $stmt = $db->prepare("
            INSERT INTO user_button_names 
            (user_id, button1_name, button2_name, button3_name) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $button1_name ?: null,
            $button2_name ?: null,
            $button3_name ?: null
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Custom button namen opgeslagen',
        'button_names' => [
            'button1' => $button1_name,
            'button2' => $button2_name,
            'button3' => $button3_name
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database fout: ' . $e->getMessage()
    ]);
}
