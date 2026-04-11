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
 * WiFi Location Lookup API - PRODUCTION
 * ═══════════════════════════════════════════════════════════════════
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    require_once __DIR__ . '/../includes/db.php';
    
    if (!isset($db) || !($db instanceof PDO)) {
        throw new Exception('Database connection not available');
    }
    
    // Get IP from request or auto-detect
    $ip = $_GET['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Validate IP
    $ip = filter_var($ip, FILTER_VALIDATE_IP);
    
    if (!$ip) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid IP address'
        ]);
        exit;
    }
    
    // Look for exact match on primary_ip or backup_ip
    $stmt = $db->prepare("
        SELECT 
            id,
            location_name,
            location_code,
            primary_ip,
            backup_ip,
            auto_checkin_enabled
        FROM locations
        WHERE active = 1
          AND auto_checkin_enabled = 1
          AND (primary_ip = ? OR backup_ip = ?)
        LIMIT 1
    ");
    
    $stmt->execute([$ip, $ip]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If not found, try IP range match
    if (!$location) {
        $stmt = $db->prepare("
            SELECT 
                id,
                location_name,
                location_code,
                ip_range_start,
                ip_range_end,
                auto_checkin_enabled
            FROM locations
            WHERE active = 1
              AND auto_checkin_enabled = 1
              AND ip_range_start IS NOT NULL
              AND ip_range_end IS NOT NULL
              AND INET_ATON(?) BETWEEN INET_ATON(ip_range_start) AND INET_ATON(ip_range_end)
            LIMIT 1
        ");
        
        $stmt->execute([$ip]);
        $location = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($location) {
        // Location found!
        echo json_encode([
            'success' => true,
            'found' => true,
            'location' => [
                'id' => (int)$location['id'],
                'name' => $location['location_name'],
                'code' => $location['location_code']
            ],
            'matched_ip' => $ip,
            'match_type' => isset($location['primary_ip']) && $ip === $location['primary_ip'] ? 'primary' : 
                           (isset($location['backup_ip']) && $ip === $location['backup_ip'] ? 'backup' : 'range')
        ]);
    } else {
        // No location found
        echo json_encode([
            'success' => true,
            'found' => false,
            'ip_checked' => $ip
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
