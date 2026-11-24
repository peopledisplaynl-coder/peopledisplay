<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
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
 * /cron_endpoint.php?action=daily_reset&key=<sleutel uit Beheer > Automatische taken>
 * ============================================================================
 */

// Security: per-installation key (2.6.0, see includes/cron_key.php and Beheer > Automatische taken)
require_once __DIR__ . '/includes/cron_key.php';
pd_cron_require_key('endpoint:' . preg_replace('/[^a-z_]/', '', (string)($_GET['action'] ?? '')), false, true);

// Get action
$action = $_GET['action'] ?? '';

// Include database
require_once __DIR__ . '/includes/db.php';

// Log function
function logCron($message) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $logFile = $logDir . '/cron_endpoint.log';
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
            
        case 'infoscreen_refresh':
            // Infoscherm: nieuwsfeeds en weer ophalen die aan de beurt zijn (elke 15 minuten)
            require_once __DIR__ . '/includes/infoscreen.php';
            $run = pd_infoscreen_refresh($db, false, 25);
            $ok = count(array_filter($run['results'], fn($r) => $r['ok']));
            $message = $run['ran']
                ? "Infoscreen refresh: {$ok} of " . count($run['results']) . " sources updated"
                : 'Infoscreen refresh: skipped (already running)';
            logCron($message);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'action' => 'infoscreen_refresh',
                'results' => $run['results'],
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
                'error' => 'Unknown action. Valid actions: daily_reset, cleanup_tokens, infoscreen_refresh, test',
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
