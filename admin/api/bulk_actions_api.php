<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * PROJECT:      PeopleDisplay v2.0
 * BESTAND:      bulk_actions_api.php
 * LOCATIE:      /admin/api/bulk_actions_api.php
 * VERSIE:       2.1 - FIXED voor BUTTON1/2/3 support
 * ═══════════════════════════════════════════════════════════════════
 * 
 * API endpoint voor bulk acties:
 * - set_all_out: Zet alle employees op OUT + clear sub_status
 * - reset_sub_status: Reset specifieke sub-status (BUTTON1/2/3)
 * - reset_visitors: Zet alle visitors op UIT
 * 
 * FIXES in v2.1:
 * - Accepteert nu BUTTON1, BUTTON2, BUTTON3 (ipv PAUZE, THUISWERKEN, VAKANTIE)
 * - Betere validatie
 * - Audit logging
 * 
 * ═══════════════════════════════════════════════════════════════════
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../auth_helper.php';

// Check admin access
try {
    requireAdmin();
} catch (Exception $e) {
    echo json_encode(['error' => 'Geen toegang']);
    exit;
}

// Get action
$action = $_POST['action'] ?? '';

// Get current user for audit logging
$userId = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? 'unknown';

// Process action
switch ($action) {
    case 'set_all_out':
        setAllOut($db, $userId, $username);
        break;
    
    case 'reset_sub_status':
        $subStatus = $_POST['sub_status'] ?? '';
        resetSubStatus($db, $subStatus, $userId, $username);
        break;
    
    case 'reset_visitors':
        resetVisitors($db, $userId, $username);
        break;
    
    default:
        echo json_encode(['error' => 'Ongeldige actie']);
        exit;
}

/**
 * Set all employees to OUT and clear sub_status
 */
function setAllOut($db, $userId, $username) {
    try {
        // Count affected employees
        $countStmt = $db->query("SELECT COUNT(*) as count FROM employees WHERE actief=1 AND (status='IN' OR sub_status IS NOT NULL)");
        $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Update all active employees
        $stmt = $db->prepare("
            UPDATE employees 
            SET status = 'OUT', 
                sub_status = NULL, 
                sub_status_until = NULL,
                tijdstip = NOW() 
            WHERE actief = 1
        ");
        $stmt->execute();
        
        // Audit log
        logAudit($db, 'BULK_RESET_ALL', "Alle medewerkers op OUT gezet ($count affected)", $userId, $username);
        
        echo json_encode([
            'success' => true,
            'message' => "Alle medewerkers zijn op OUT gezet ($count medewerkers)",
            'affected' => $count
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Database fout: ' . $e->getMessage()]);
    }
}

/**
 * Reset specific sub-status (BUTTON1, BUTTON2, or BUTTON3)
 */
function resetSubStatus($db, $subStatus, $userId, $username) {
    // Validate sub-status
    $validSubStatuses = ['BUTTON1', 'BUTTON2', 'BUTTON3'];
    
    if (!in_array($subStatus, $validSubStatuses)) {
        echo json_encode([
            'error' => 'Ongeldige sub-status',
            'received' => $subStatus,
            'expected' => 'BUTTON1, BUTTON2, or BUTTON3'
        ]);
        return;
    }
    
    try {
        // Count affected employees
        $countStmt = $db->prepare("SELECT COUNT(*) as count FROM employees WHERE actief=1 AND sub_status=?");
        $countStmt->execute([$subStatus]);
        $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count == 0) {
            echo json_encode([
                'success' => true,
                'message' => "Geen medewerkers met deze sub-status",
                'affected' => 0
            ]);
            return;
        }
        
        // Reset sub-status for affected employees
        $stmt = $db->prepare("
            UPDATE employees 
            SET sub_status = NULL,
                sub_status_until = NULL
            WHERE actief = 1 
            AND sub_status = ?
        ");
        $stmt->execute([$subStatus]);
        
        // Get button name from config
        $buttonNum = substr($subStatus, -1); // Extract 1, 2, or 3
        $configStmt = $db->query("SELECT button{$buttonNum}_name FROM config WHERE id=1");
        $buttonName = $configStmt->fetch(PDO::FETCH_ASSOC)["button{$buttonNum}_name"] ?? $subStatus;
        
        // Audit log
        logAudit($db, 'BULK_RESET_SUBSTATUS', "Sub-status $subStatus ({$buttonName}) gereset voor $count medewerkers", $userId, $username);
        
        echo json_encode([
            'success' => true,
            'message' => "Sub-status {$buttonName} gereset voor {$count} medewerkers",
            'affected' => $count,
            'sub_status' => $subStatus
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Database fout: ' . $e->getMessage()]);
    }
}

/**
 * Reset all visitors (set status to OUT)
 */
function resetVisitors($db, $userId, $username) {
    try {
        // Check if visitors table exists
        $tables = $db->query("SHOW TABLES LIKE 'visitors'")->fetchAll();
        
        if (empty($tables)) {
            echo json_encode(['error' => 'Bezoekers tabel niet gevonden']);
            return;
        }
        
        // Count visitors
        $countStmt = $db->query("SELECT COUNT(*) as count FROM visitors WHERE status='BINNEN'");
        $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count == 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Geen actieve bezoekers',
                'affected' => 0
            ]);
            return;
        }
        
        // Update visitors
        $stmt = $db->prepare("
            UPDATE visitors 
            SET status = 'UIT'
            WHERE status = 'BINNEN'
        ");
        $stmt->execute();
        
        // Audit log
        logAudit($db, 'BULK_RESET_VISITORS', "Alle bezoekers uit-gecheckt ($count affected)", $userId, $username);
        
        echo json_encode([
            'success' => true,
            'message' => "Alle bezoekers zijn uit-gecheckt ({$count} bezoekers)",
            'affected' => $count
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Database fout: ' . $e->getMessage()]);
    }
}

/**
 * Log audit entry
 */
function logAudit($db, $action, $description, $userId, $username) {
    try {
        $stmt = $db->prepare("
            INSERT INTO employee_audit 
            (employee_id, action, field_changed, new_value, changed_by, ip_address, user_agent, changed_at) 
            VALUES 
            (NULL, ?, 'bulk_action', ?, ?, ?, ?, NOW())
        ");
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->execute([
            $action,
            $description,
            $userId,
            $ipAddress,
            $userAgent
        ]);
    } catch (Exception $e) {
        // Silently fail audit logging (don't break main functionality)
        error_log("Audit log failed: " . $e->getMessage());
    }
}
