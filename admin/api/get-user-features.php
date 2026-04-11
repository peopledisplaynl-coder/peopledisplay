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
 * BESTANDSNAAM: get-user-features.php
 * LOCATIE:      /admin/api/get-user-features.php
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
    
    // Normaliseer profile_photo pad (verwijder leading slash voor consistentie)
    $profilePhoto = null;
    if (!empty($user['profile_photo'])) {
        $profilePhoto = ltrim($user['profile_photo'], '/');
    }
    
    // Get global config to check if custom names are allowed
    $configStmt = $db->query("SELECT allow_user_button_names FROM config WHERE id = 1");
    $config = $configStmt->fetch(PDO::FETCH_ASSOC);
    $allowCustomNames = $config && $config['allow_user_button_names'] == 1;
    
    // Get custom button names (only if allowed)
    $customNames = null;
    if ($allowCustomNames) {
        $stmt = $db->prepare("
            SELECT button1_name, button2_name, button3_name 
            FROM user_button_names 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $customNames = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get user's selected afdelingen
    $afdelingen = [];
    try {
        $stmt = $db->prepare("
            SELECT a.afdeling_name 
            FROM user_afdelingen ua
            JOIN afdelingen a ON ua.afdeling_id = a.id
            WHERE ua.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $afdelingen = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // Table might not exist in older installations
        $afdelingen = [];
    }
    
    // Build response
    $response = [
        'username' => $user['username'],
        'display_name' => $user['display_name'] ?? $user['username'],
        'profile_photo' => $profilePhoto,
        'role' => $user['role'],
        'visibleFields' => $features['visibleFields'] ?? [],
        'extraButtons' => $features['extraButtons'] ?? [],
        'locations' => $features['locations'] ?? [],
        'afdelingen' => $afdelingen, // ✅ NIEUW!
        'customButtonNames' => null,
        'sorteerFunctie' => isset($features['sorteerFunctie']) && $features['sorteerFunctie'] ? true : false  // ✅ SORTEER TOGGLE FEATURE
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
