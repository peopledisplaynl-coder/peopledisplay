<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * Get single employee by ID
 * /api/get_employee.php?id=EMP123
 * 
 * VERSIE: 2.0 - Met sub-status expiry check
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

$employee_id = $_GET['id'] ?? '';

if (empty($employee_id)) {
    echo json_encode(['success' => false, 'error' => 'No employee ID provided']);
    exit;
}

/**
 * Reset verlopen sub-statussen
 */
function resetExpiredSubStatuses($db) {
    try {
        $query = "
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
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        return $stmt->rowCount();
        
    } catch (PDOException $e) {
        error_log("Error resetting expired statuses: " . $e->getMessage());
        return 0;
    }
}

// ✅ RESET EXPIRED STATUSES VOOR WE DATA OPHALEN
resetExpiredSubStatuses($db);

try {
    $stmt = $db->prepare("
        SELECT 
            employee_id,
            naam,
            functie,
            afdeling,
            locatie,
            foto_url,
            bhv,
            status,
            sub_status,
            sub_status_until
        FROM employees
        WHERE employee_id = ? AND actief = 1
        LIMIT 1
    ");
    
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($employee) {
        echo json_encode([
            'success' => true,
            'employee' => $employee
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Employee not found'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
