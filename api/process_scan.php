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
 * BESTANDSNAAM: process_scan.php
 * LOCATIE:      /api/process_scan.php
 * VERSIE:       2.0 - Met locatie validatie
 * 
 * BESCHRIJVING: Process QR/Barcode scans met locatie validatie
 * - Toggle IN/OUT status
 * - Locatie validatie VERPLICHT
 * - Foutmeldingen bij ongeldige locatie
 * ═══════════════════════════════════════════════════════════════════
 */

header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Niet ingelogd'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$code = $input['code'] ?? '';

if (empty($code)) {
    echo json_encode([
        'success' => false,
        'error' => 'Geen code ontvangen'
    ]);
    exit;
}

try {
    // === STAP 1: HAAL EMPLOYEE OP ===
    $stmt = $db->prepare("
        SELECT 
            id,
            employee_id,
            naam,
            voornaam,
            achternaam,
            locatie as default_locatie,
            status,
            actief
        FROM employees 
        WHERE employee_id = ?
    ");
    $stmt->execute([$code]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        echo json_encode([
            'success' => false,
            'error' => '❌ Employee ID niet gevonden: ' . $code
        ]);
        exit;
    }
    
    if (!$employee['actief']) {
        echo json_encode([
            'success' => false,
            'error' => '❌ Employee is niet actief'
        ]);
        exit;
    }
    
    // === STAP 2: BEPAAL NIEUWE STATUS ===
    $current_status = $employee['status'];
    $new_status = ($current_status === 'OUT') ? 'IN' : 'OUT';
    
    // === STAP 3: LOCATIE VALIDATIE (ALLEEN BIJ CHECK-IN) ===
    if ($new_status === 'IN') {
        // Check-in → LOCATIE VALIDATIE VEREIST
        
        $target_location = $employee['default_locatie'];
        
        // Als geen default locatie
        if (empty($target_location)) {
            echo json_encode([
                'success' => false,
                'error' => '⚠️ Geen standaard locatie ingesteld. Ga naar Medewerkers beheren → Edit → Stel locatie in.',
                'requires_location_setup' => true
            ]);
            exit;
        }
        
        // Valideer of locatie bestaat en actief is
        $stmt = $db->prepare("
            SELECT location_name 
            FROM locations 
            WHERE location_name = ? AND active = 1
        ");
        $stmt->execute([$target_location]);
        $valid_location = $stmt->fetch();
        
        if (!$valid_location) {
            // Locatie bestaat niet of is inactief
            
            // Haal alle geldige locaties op voor foutmelding
            $stmt = $db->query("
                SELECT location_name 
                FROM locations 
                WHERE active = 1 
                ORDER BY location_name
            ");
            $available_locations = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($available_locations) === 0) {
                echo json_encode([
                    'success' => false,
                    'error' => '❌ SYSTEEMFOUT: Geen actieve locaties beschikbaar! Neem contact op met beheerder.'
                ]);
                exit;
            }
            
            echo json_encode([
                'success' => false,
                'error' => "⚠️ Ongeldige locatie: '{$target_location}'. Ga naar Medewerkers beheren om een geldige locatie in te stellen.",
                'invalid_location' => $target_location,
                'available_locations' => $available_locations,
                'requires_location_setup' => true
            ]);
            exit;
        }
        
        // === LOCATIE IS GELDIG - DOE CHECK-IN ===
        $stmt = $db->prepare("
            UPDATE employees 
            SET status = 'IN',
                locatie = ?,
                tijdstip = NOW()
            WHERE employee_id = ?
        ");
        $stmt->execute([$target_location, $code]);
        
        // Voeg audit log toe
        $stmt = $db->prepare("
            INSERT INTO employee_audit 
            (employee_id, action, field_changed, old_value, new_value, changed_by, changed_at)
            VALUES (?, 'STATUS_CHANGE', 'status', ?, 'IN', ?, NOW())
        ");
        $stmt->execute([
            $code,
            $current_status,
            $_SESSION['user_id']
        ]);
        
        $full_name = $employee['naam'] ?: ($employee['voornaam'] . ' ' . $employee['achternaam']);
        
        echo json_encode([
            'success' => true,
            'message' => "✅ Ingecheckt op {$target_location}",
            'employee' => [
                'id' => $employee['employee_id'],
                'name' => $full_name,
                'old_status' => $current_status,
                'new_status' => 'IN',
                'location' => $target_location
            ]
        ]);
        
    } else {
        // === CHECK-OUT (GEEN LOCATIE VALIDATIE NODIG) ===
        $stmt = $db->prepare("
            UPDATE employees 
            SET status = 'OUT',
                tijdstip = NOW()
            WHERE employee_id = ?
        ");
        $stmt->execute([$code]);
        
        // Voeg audit log toe
        $stmt = $db->prepare("
            INSERT INTO employee_audit 
            (employee_id, action, field_changed, old_value, new_value, changed_by, changed_at)
            VALUES (?, 'STATUS_CHANGE', 'status', 'IN', 'OUT', ?, NOW())
        ");
        $stmt->execute([
            $code,
            $_SESSION['user_id']
        ]);
        
        $full_name = $employee['naam'] ?: ($employee['voornaam'] . ' ' . $employee['achternaam']);
        
        echo json_encode([
            'success' => true,
            'message' => "✅ Uitgecheckt",
            'employee' => [
                'id' => $employee['employee_id'],
                'name' => $full_name,
                'old_status' => 'IN',
                'new_status' => 'OUT',
                'location' => null
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log('Process scan error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => '❌ Database fout: ' . $e->getMessage()
    ]);
}
