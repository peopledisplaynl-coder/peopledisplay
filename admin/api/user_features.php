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
 * BESTANDSNAAM: user_features.php
 * LOCATIE:      /admin/api/user_features.php
 * BESCHRIJVING: Get user features + custom button names
 * ═══════════════════════════════════════════════════════════════════
 */

session_start();
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get user data
    $stmt = $db->prepare("SELECT username, display_name, role, features, profile_photo FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    // Parse features JSON
    $features = json_decode($user['features'] ?? '{}', true) ?: [];
    
    // Get custom button names
    $stmt = $db->prepare("
        SELECT button1_name, button2_name, button3_name 
        FROM user_button_names 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $customNames = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Build response
    $response = [
        'username' => $user['username'],
        'display_name' => $user['display_name'] ?? $user['username'],
        'role' => $user['role'],
        'profile_photo' => $user['profile_photo'] ?? null,
        'visibleFields' => $features['visibleFields'] ?? [],
        'extraButtons' => $features['extraButtons'] ?? [],
        'locations' => $features['locations'] ?? [],
        'customButtonNames' => null
    ];
    
    // Add custom names if they exist
    if ($customNames) {
        $response['customButtonNames'] = [
            'button1' => $customNames['button1_name'],
            'button2' => $customNames['button2_name'],
            'button3' => $customNames['button3_name']
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
