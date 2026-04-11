<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * BESTANDSNAAM: heartbeat.php
 * UPLOAD NAAR:  /api/heartbeat.php
 *
 * Periodieke ping vanuit de browser om sessie actief te houden
 * Wordt aangeroepen via JavaScript setInterval
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache');

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'reason' => 'not_logged_in']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

try {
    // Check of user_sessions tabel bestaat
    $tableCheck = $db->query("SHOW TABLES LIKE 'user_sessions'")->fetch();
    if (!$tableCheck) {
        // Tabel bestaat niet — maak aan
        $db->exec("CREATE TABLE IF NOT EXISTS `user_sessions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `session_id` varchar(128) NOT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` text DEFAULT NULL,
            `browser` varchar(100) DEFAULT NULL,
            `device` varchar(50) DEFAULT NULL,
            `page_url` varchar(500) DEFAULT NULL,
            `login_time` datetime DEFAULT current_timestamp(),
            `last_activity` datetime DEFAULT current_timestamp(),
            `is_active` tinyint(1) DEFAULT 1,
            PRIMARY KEY (`id`),
            UNIQUE KEY `session_id` (`session_id`),
            KEY `user_id` (`user_id`),
            KEY `last_activity` (`last_activity`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    $session_id = session_id();
    $user_id = $_SESSION['user_id'];
    $page_url = $_SERVER['HTTP_REFERER'] ?? null;

    // Update bestaande sessie of maak nieuwe aan
    $stmt = $db->prepare("
        INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, browser, device, page_url, login_time, last_activity, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1)
        ON DUPLICATE KEY UPDATE
            last_activity = NOW(),
            page_url = VALUES(page_url),
            is_active = 1
    ");

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    // Eenvoudige browser detectie
    $browser = 'Unknown';
    if (str_contains($ua, 'Edge') || str_contains($ua, 'Edg')) $browser = 'Edge';
    elseif (str_contains($ua, 'Chrome')) $browser = 'Chrome';
    elseif (str_contains($ua, 'Firefox')) $browser = 'Firefox';
    elseif (str_contains($ua, 'Safari')) $browser = 'Safari';

    // Eenvoudige device detectie
    $device = 'Desktop';
    if (preg_match('/mobile/i', $ua)) $device = 'Mobile';
    elseif (preg_match('/tablet|ipad/i', $ua)) $device = 'Tablet';

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    $stmt->execute([$user_id, $session_id, $ip, $ua, $browser, $device, $page_url]);

    echo json_encode([
        'success' => true,
        'user_id' => $user_id,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
