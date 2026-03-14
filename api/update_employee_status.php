<?php
/**
 * Update Employee Status (WiFi Auto Check-in)
 * /api/update_employee_status.php
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$employee_id = $data['employee_id'] ?? '';
$status = $data['status'] ?? '';
$locatie = $data['locatie'] ?? '';

if (empty($employee_id) || empty($status)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    // Update employee status
    $stmt = $db->prepare("
        UPDATE employees
        SET status = ?,
            locatie = ?,
            tijdstip = NOW()
        WHERE employee_id = ? AND actief = 1
    ");
    
    $stmt->execute([$status, $locatie, $employee_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'employee_id' => $employee_id,
            'status' => $status,
            'locatie' => $locatie,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Employee not found or already has this status'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
