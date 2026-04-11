<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
session_start();

header('Content-Type: application/json');

// Check admin rechten - compatibel met jouw systeem
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','superadmin'], true)) {
    echo json_encode(['success' => false, 'error' => 'Geen toegang']);
    exit;
}

// Database connectie via includes/db.php
require_once __DIR__ . '/../../includes/db.php';

try {
    // Check of $db variable bestaat
    if (!isset($db) || !($db instanceof PDO)) {
        throw new RuntimeException('Database connectie niet beschikbaar');
    }
    
    // Lees POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['order']) || !is_array($input['order'])) {
        echo json_encode(['success' => false, 'error' => 'Geen geldige volgorde ontvangen']);
        exit;
    }
    
    $order = $input['order'];
    $orderJson = json_encode($order);
    
    // Check of locations_order kolom bestaat, zo niet, maak deze aan
    $stmt = $db->query("SHOW COLUMNS FROM config LIKE 'locations_order'");
    if ($stmt->rowCount() == 0) {
        // Kolom bestaat niet, maak aan
        $db->exec("ALTER TABLE config ADD COLUMN locations_order TEXT NULL");
    }
    
    // Update de volgorde
    $stmt = $db->prepare("UPDATE config SET locations_order = :order WHERE id = 1");
    $stmt->execute(['order' => $orderJson]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Volgorde opgeslagen',
        'order' => $order
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database fout: ' . $e->getMessage()
    ]);
}
