<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
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
    // Haal oude status op voor audit log
    $oldStmt = $db->prepare("SELECT status FROM employees WHERE employee_id = ? AND actief = 1");
    $oldStmt->execute([$employee_id]);
    $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC);
    $oldStatus = $oldData['status'] ?? 'UNKNOWN';

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
        // Audit log WiFi auto check-in
        try {
            $auditStmt = $db->prepare("INSERT INTO employee_audit (employee_id, action, field_changed, old_value, new_value, changed_by, ip_address, user_agent) VALUES (?, 'STATUS_CHANGE', 'status', ?, ?, NULL, ?, ?)");
            $auditStmt->execute([
                $employee_id,
                $oldStatus,
                $status . ' (WiFi auto)',
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $ae) {
            error_log('Audit log failed: ' . $ae->getMessage());
        }

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
