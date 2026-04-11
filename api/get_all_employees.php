<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * Get all employees for onboarding selection
 * /api/get_all_employees.php
 * 
 * VERSIE: 2.0 - Met sub-status expiry check
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

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
$reset_count = resetExpiredSubStatuses($db);
if ($reset_count > 0) {
    error_log("🕐 Reset $reset_count expired sub-statuses (get_all_employees)");
}

try {
    $stmt = $db->query("
        SELECT 
            employee_id,
            naam,
            functie,
            foto_url,
            sub_status,
            sub_status_until
        FROM employees
        WHERE actief = 1
        ORDER BY naam ASC
    ");
    
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'employees' => $employees,
        'count' => count($employees),
        'expired_reset' => $reset_count // ℹ️ Info voor debugging
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
