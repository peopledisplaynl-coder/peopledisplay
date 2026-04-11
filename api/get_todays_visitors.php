<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * BESTANDSNAAM: get_todays_visitors.php
 * LOCATIE: /api/get_todays_visitors.php
 * BESCHRIJVING: Get today's visitors + multi-day visitors
 * VERSIE: 2.0 - Multi-day support
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

try {
    // Get visitors for today OR multi-day visitors within their date range
    $stmt = $db->prepare("
        SELECT 
            id,
            voornaam,
            achternaam,
            bedrijf,
            locatie,
            tijd,
            status,
            bezoek_datum,
            is_multi_day,
            start_date,
            end_date,
            created_at
        FROM visitors
        WHERE (
            (is_multi_day = 0 AND DATE(bezoek_datum) = CURDATE())
            OR
            (is_multi_day = 1 AND CURDATE() BETWEEN DATE(start_date) AND DATE(end_date))
        )
        AND status IN ('AANGEMELD', 'BINNEN')
        ORDER BY 
            CASE status
                WHEN 'BINNEN' THEN 1
                WHEN 'AANGEMELD' THEN 2
            END,
            tijd ASC
    ");
    
    $stmt->execute();
    $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format output
    $formatted = array_map(function($v) {
        return [
            'id' => $v['id'],
            'naam' => $v['voornaam'] . ' ' . $v['achternaam'],
            'bedrijf' => $v['bedrijf'] ?: '-',
            'locatie' => $v['locatie'],
            'tijd' => date('H:i', strtotime($v['tijd'])),
            'status' => $v['status'],
            'is_multi_day' => (bool)$v['is_multi_day'],
            'date_display' => $v['is_multi_day'] 
                ? date('d-m', strtotime($v['start_date'])) . ' t/m ' . date('d-m', strtotime($v['end_date']))
                : date('d-m-Y', strtotime($v['bezoek_datum']))
        ];
    }, $visitors);
    
    echo json_encode([
        'success' => true,
        'count' => count($formatted),
        'visitors' => $formatted
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
