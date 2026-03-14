<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: update_status.php
 * LOCATIE:      /admin/api/update_status.php
 * FUNCTIE:      Update employee status
 * ═══════════════════════════════════════════════════════════════════
 */

session_start();
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $employee_id = $input['employee_id'] ?? $input['id'] ?? null;
    $new_status = $input['status'] ?? null;
    
    if (!$employee_id || !$new_status) {
        throw new Exception('Missing employee_id or status');
    }
    
    // Validate status
    $valid_statuses = ['IN', 'OUT', 'PAUZE', 'THUISWERKEN', 'VAKANTIE'];
    $new_status = strtoupper(trim($new_status));
    
    if (!in_array($new_status, $valid_statuses)) {
        throw new Exception('Invalid status: ' . $new_status);
    }
    
    // Update employee status
    $stmt = $db->prepare("
        UPDATE employees 
        SET status = ?, 
            tijdstip = NOW() 
        WHERE employee_id = ?
    ");
    
    $stmt->execute([$new_status, $employee_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Employee not found or no change');
    }
    
    // Get updated employee data
    $stmt = $db->prepare("
        SELECT 
            employee_id as ID,
            naam as Naam,
            voornaam as Voornaam,
            achternaam as Achternaam,
            status as Status,
            locatie as Locatie,
            tijdstip as Tijdstip
        FROM employees
        WHERE employee_id = ?
    ");
    
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'employee' => $employee,
        'message' => 'Status updated successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
