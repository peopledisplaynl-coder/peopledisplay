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
 * BESTANDSNAAM: export_employees_csv.php
 * LOCATIE:      /admin/api/export_employees_csv.php
 * BESCHRIJVING: Export employees to CSV
 * ═══════════════════════════════════════════════════════════════════
 */

session_start();
require_once __DIR__ . '/../../includes/db.php';

// Check admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? 'user', ['admin', 'superadmin'])) {
    die('Unauthorized');
}

try {
    // Get all active employees
    $stmt = $db->query("
        SELECT 
            employee_id,
            voornaam,
            achternaam,
            naam,
            functie,
            afdeling,
            locatie,
            bhv,
            foto_url,
            notities,
            status
        FROM employees
        WHERE actief = 1
        ORDER BY achternaam, voornaam
    ");
    
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="employees_export_' . date('Y-m-d_His') . '.csv"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV Headers
    fputcsv($output, [
        'employee_id',
        'voornaam',
        'achternaam',
        'naam',
        'functie',
        'afdeling',
        'locatie',
        'bhv',
        'foto_url',
        'notities',
        'status'
    ]);
    
    // Write data
    foreach ($employees as $emp) {
        fputcsv($output, [
            $emp['employee_id'],
            $emp['voornaam'],
            $emp['achternaam'],
            $emp['naam'],
            $emp['functie'],
            $emp['afdeling'],
            $emp['locatie'],
            $emp['bhv'],
            $emp['foto_url'],
            $emp['notities'],
            $emp['status']
        ]);
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    die('Export failed: ' . $e->getMessage());
}
