<?php
/**
 * BESTANDSNAAM: get_employees_by_location.php
 * LOCATIE: /api/get_employees_by_location.php
 * VERSIE: 2.2 - Voornaam + Achternaam + Sub-status expiry check
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

$locatie = $_GET['locatie'] ?? '';

if (empty($locatie)) {
    echo json_encode([
        'success' => false,
        'error' => 'Geen locatie opgegeven'
    ]);
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
$reset_count = resetExpiredSubStatuses($db);
if ($reset_count > 0) {
    error_log("🕐 Reset $reset_count expired sub-statuses (get_employees_by_location)");
}

try {
    // Get employees for this location
    $stmt = $db->prepare("
        SELECT 
            employee_id,
            CONCAT(TRIM(Voornaam), ' ', TRIM(Achternaam)) as naam,
            functie,
            locatie,
            email,
            status,
            sub_status,
            sub_status_until
        FROM employees
        WHERE locatie = ?
        AND actief = 1
        AND (status IN ('IN', 'OUT') OR status IS NULL)
        ORDER BY Voornaam, Achternaam
    ");
    
    $stmt->execute([$locatie]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter out empty names
    $employees = array_filter($employees, function($emp) {
        return !empty(trim($emp['naam']));
    });
    
    echo json_encode([
        'success' => true,
        'count' => count($employees),
        'employees' => array_values($employees),
        'expired_reset' => $reset_count // ℹ️ Info voor debugging
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
