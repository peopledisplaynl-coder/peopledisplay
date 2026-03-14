<?php
/**
 * ============================================================
 * File: api/update_sub_status_until.php
 * Location: /api/update_sub_status_until.php
 * 
 * VERSION: 2.1 - FIXED PARAMETER NAME + EXPIRY SUPPORT
 * LAST UPDATED: 2026-01-24
 * 
 * FIX: Changed until_date to until (matches app.js)
 * ============================================================
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get parameters
$employee_id = $_POST['employee_id'] ?? null;
$button_number = $_POST['button_number'] ?? null;
$until = $_POST['until'] ?? null;  // ✅ FIXED: was "until_date", now "until"

// Debug logging (optioneel, kan uitgezet)
error_log("📝 update_sub_status_until: employee=$employee_id, button=$button_number, until=$until");

if (!$employee_id || !$button_number) {
    echo json_encode(['success' => false, 'error' => 'Missing employee_id or button_number']);
    exit;
}

// Map button NUMBER to LABEL (database waarde)
$label_map = [
    '1' => 'BUTTON1',  // Button 1 → BUTTON1 (roze)
    '2' => 'BUTTON2',  // Button 2 → BUTTON2 (paars)
    '3' => 'BUTTON3'   // Button 3 → BUTTON3 (groen)
];

if (!isset($label_map[$button_number])) {
    echo json_encode(['success' => false, 'error' => 'Invalid button_number: ' . $button_number]);
    exit;
}

// Database label (BUTTON1/2/3)
$sub_status_label = $label_map[$button_number];

try {
    // Update employee met sub_status EN sub_status_until
    $stmt = $db->prepare("
        UPDATE employees 
        SET 
            status = 'IN',
            sub_status = ?,
            sub_status_type = ?,
            sub_status_until = ?,
            tijdstip = NOW(),
            updated_at = NOW()
        WHERE employee_id = ?
          AND actief = 1
    ");
    
    $stmt->execute([
        $sub_status_label,  // BUTTON1/2/3
        $button_number,     // 1/2/3
        $until,            // 2026-01-24 17:00:00
        $employee_id
    ]);
    
    $rows_affected = $stmt->rowCount();
    
    error_log("✅ Sub-status until set: $employee_id → $sub_status_label until $until (rows: $rows_affected)");
    
    if ($rows_affected > 0) {
        echo json_encode([
            'success' => true,
            'employee_id' => $employee_id,
            'sub_status' => $sub_status_label,
            'sub_status_until' => $until,
            'button_number' => $button_number,
            'rows_affected' => $rows_affected
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No rows updated - employee not found: ' . $employee_id
        ]);
    }
    
} catch (PDOException $e) {
    error_log("❌ Database error in update_sub_status_until: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
