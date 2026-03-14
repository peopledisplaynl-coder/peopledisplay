<?php
/**
 * ============================================================================
 * BESTANDSNAAM:  cron_endpoint.php
 * UPLOAD NAAR:   ROOT (/cron_endpoint.php)
 * DATUM:         2024-12-04
 * VERSIE:        v1.0
 * 
 * PUBLIC ENDPOINT voor externe cron services zoals cron-job.org
 * 
 * GEBRUIK:
 * https://onsteam.peopledisplay.nl/cron_endpoint.php?action=daily_reset&key=JOUW_SECRET_KEY
 * ============================================================================
 */

// Security: Require secret key
$providedKey = $_GET['key'] ?? '';
$expectedKey = 'INZ8U56BCZ';  // âš ï¸ WIJZIG DIT IN PRODUCTIE!

if ($providedKey !== $expectedKey) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Invalid or missing security key',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Get action
$action = $_GET['action'] ?? '';

// Include database
require_once __DIR__ . '/includes/db.php';

// Log function
function logCron($message) {
    $logFile = __DIR__ . '/cron_endpoint.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    switch ($action) {
        case 'daily_reset':
            // Get stats before reset
            $beforeStmt = $db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status != 'OUT' THEN 1 ELSE 0 END) as not_out
                FROM employees
                WHERE actief = 1
            ");
            $before = $beforeStmt->fetch(PDO::FETCH_ASSOC);
            
            // Update all active employees to OUT
            $stmt = $db->prepare("
                UPDATE employees 
                SET status = 'OUT', tijdstip = NOW() 
                WHERE actief = 1 AND status != 'OUT'
            ");
            $stmt->execute();
            $affected = $stmt->rowCount();
            
            // Log to employee_audit
            $auditStmt = $db->prepare("
                INSERT INTO employee_audit 
                (employee_id, action, field_changed, old_value, new_value, changed_by, ip_address, user_agent)
                VALUES ('BULK_CRON', 'STATUS_CHANGE', 'status', 'VARIOUS', 'OUT', 0, ?, 'External Cron Service')
            ");
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $auditStmt->execute([$ipAddress]);
            
            $message = "Daily reset successful: {$affected} employees set to OUT (was not OUT: {$before['not_out']})";
            logCron($message);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'action' => 'daily_reset',
                'affected' => $affected,
                'before_not_out' => $before['not_out'],
                'total_employees' => $before['total'],
                'message' => $message,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'cleanup_tokens':
            // Delete expired remember me tokens
            $countStmt = $db->query("SELECT COUNT(*) as count FROM remember_tokens WHERE expires_at < NOW()");
            $expiredCount = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $db->prepare("DELETE FROM remember_tokens WHERE expires_at < NOW()");
            $stmt->execute();
            $deleted = $stmt->rowCount();
            
            $message = "Token cleanup: {$deleted} expired tokens deleted";
            logCron($message);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'action' => 'cleanup_tokens',
                'deleted' => $deleted,
                'message' => $message,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'test':
            // Test endpoint
            $message = "Test successful - cron endpoint is working";
            logCron($message);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'action' => 'test',
                'message' => $message,
                'server_time' => date('Y-m-d H:i:s'),
                'php_version' => phpversion(),
                'database_connected' => true
            ]);
            break;
            
        default:
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Unknown action. Valid actions: daily_reset, cleanup_tokens, test',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
    }
    
} catch (Exception $e) {
    $errorMessage = "Cron error: " . $e->getMessage();
    logCron($errorMessage);
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
