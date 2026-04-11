<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
error_reporting(0);
ini_set('display_errors', '0');
/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: employees_api.php
 * LOCATIE:      /admin/api/employees_api.php
 * VERSIE:       3.1 - SUB-STATUS + AUTO-EXPIRY
 * ═══════════════════════════════════════════════════════════════════
 * 
 * NIEUWE FEATURES:
 * - ✅ Sub-status kolom support
 * - ✅ Status + SubStatus in response
 * - ✅ Smart update: IN + OVERLEG tegelijk mogelijk
 * - ✅ AUTO-EXPIRY: Reset verlopen sub-statussen (NIEUW!)
 * ═══════════════════════════════════════════════════════════════════
 */

session_start();
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

// ============================================================================
// 🕐 SUB-STATUS AUTO-EXPIRY
// ============================================================================

/**
 * Reset alle verlopen sub-statussen naar NULL
 * Draait automatisch bij elke API call
 */
function resetExpiredSubStatuses($db) {
    try {
        $stmt = $db->prepare("
            UPDATE employees 
            SET 
                sub_status = NULL,
                sub_status_type = NULL,
                sub_status_until = NULL,
                tijdstip = NOW(),
                updated_at = NOW()
            WHERE 
                sub_status_until IS NOT NULL
                AND sub_status_until < NOW()
                AND actief = 1
        ");
        
        $stmt->execute();
        $count = $stmt->rowCount();
        
        // Log voor debugging (alleen als er daadwerkelijk statuses gereset zijn)
        if ($count > 0) {
            error_log("🕐 Auto-reset $count expired sub-statuses at " . date('Y-m-d H:i:s'));
        }
        
        return $count;
        
    } catch (PDOException $e) {
        // Stil falen - expiry check mag API niet breken
        error_log("⚠️ Expiry check failed: " . $e->getMessage());
        return 0;
    }
}

// 🔄 RESET VERLOPEN STATUSSEN (elke API call)
resetExpiredSubStatuses($db);

// ============================================================================
// 🎯 API ENDPOINTS
// ============================================================================

try {
    $action = $_GET['action'] ?? 'getemployees';
    
    if ($action === 'updatestatus') {
        // ✅ UPDATE STATUS (met sub-status + temp_location support)
        $id = $_GET['id'] ?? null;
        $status = $_GET['status'] ?? null;
        $subStatus = $_GET['substatus'] ?? null;  // 🆕 SUB-STATUS
        $tempLocation = $_GET['temp_location'] ?? null;  // 🆕 MANUAL LOCATION
        
        if (!$id || !$status) {
            throw new Exception('Missing id or status');
        }
        
        // Normalize status to uppercase
        $status = strtoupper($status);
        
        // 🔧 Business rule: Als OUT → clear sub_status
        if ($status === 'OUT') {
            $subStatus = null;
        }
        
        // 🔧 Als alleen sub_status verandert (zonder status parameter expliciet)
        // Dan huidige status behouden + alleen sub_status updaten
        if ($subStatus !== null && $status === null) {
            // Haal huidige status op
            $stmt = $db->prepare("SELECT status FROM employees WHERE employee_id = ? AND actief = 1");
            $stmt->execute([$id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($current) {
                $status = $current['status'];
            } else {
                throw new Exception('Employee not found');
            }
        }
        
        // Normalize sub_status
        if ($subStatus !== null) {
            $subStatus = strtoupper(trim($subStatus));
            if ($subStatus === '' || $subStatus === 'NULL') {
                $subStatus = null;
            }
        }
        
        // 🔧 Update query - met OPTIONELE locatie override voor manual check-in OF reset bij checkout
        // temp_location kan meegegeven worden bij IN (nieuwe locatie) OF OUT (reset naar origineel)
        if ($tempLocation !== null) {
            $stmt = $db->prepare("
                UPDATE employees 
                SET status = ?, 
                    sub_status = ?,
                    locatie = ?,
                    tijdstip = NOW() 
                WHERE employee_id = ? AND actief = 1
            ");

            $stmt->execute([$status, $subStatus, $tempLocation, $id]);
        } else {
            // Normale update zonder locatie wijziging
            $stmt = $db->prepare("
                UPDATE employees 
                SET status = ?, 
                    sub_status = ?, 
                    tijdstip = NOW() 
                WHERE employee_id = ? AND actief = 1
            ");

            $stmt->execute([$status, $subStatus, $id]);
        }
        
        echo json_encode([
            'success' => true,
            'id' => $id,
            'status' => $status,
            'subStatus' => $subStatus,
            'tempLocation' => $tempLocation,
            'timestamp' => date('Y-m-d H:i:s'),
            'rowsAffected' => $stmt->rowCount()
        ]);
        exit;
    }
    
    // ✅ GET EMPLOYEES (met sub_status + manual location fields)
    $stmt = $db->query("
        SELECT 
            id,
            employee_id as ID,
            naam as Naam,
            voornaam as Voornaam,
            achternaam as Achternaam,
            status as Status,
            sub_status as SubStatus,
            sub_status_until,
            locatie as Locatie,
            foto_url as FotoURL,
            functie as Functie,
            afdeling as Afdeling,
            bhv as BHV,
            tijdstip as Tijdstip,
            visible_locations,
            allow_manual_location_change
        FROM employees
        WHERE actief = 1
        ORDER BY achternaam, voornaam
    ");
    
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'employees' => $employees
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
