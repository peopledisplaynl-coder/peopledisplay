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
 * BESTANDSNAAM:  daily_reset.php
 * UPLOAD NAAR:   /cron/daily_reset.php
 * DATUM:         2024-12-04
 * VERSIE:        v1.0
 * 
 * DOEL: Dagelijkse reset om 23:00
 * Zet alle medewerkers op OUT status
 * 
 * CRON JOB SETUP:
 * 0 23 * * * php /var/www/html/peopledisplay/cron/daily_reset.php
 * 
 * OF via cPanel:
 * 0 23 * * * /usr/local/bin/php /home/username/public_html/cron/daily_reset.php
 * ============================================================================
 */

// Security: Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    // Allow browser access only with secret key
    $secretKey = $_GET['key'] ?? '';
    $expectedKey = 'INZ8U56BCZ'; // CHANGE THIS IN PRODUCTION!
    
    if ($secretKey !== $expectedKey) {
        http_response_code(403);
        die('Access denied. This script can only be run via cron or with valid key.');
    }
}

// Include database connection
require_once __DIR__ . '/../includes/db.php';

try {
    // Get current stats before reset
    $beforeStmt = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'IN' THEN 1 ELSE 0 END) as in_count
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
        VALUES ('BULK', 'STATUS_CHANGE', 'status', 'VARIOUS', 'OUT', 0, 'CRON', 'Daily Reset Script')
    ");
    $auditStmt->execute();
    
    // Log result
    $logMessage = sprintf(
        "[%s] Daily Reset Successful: %d employees set to OUT (was IN: %d)\n",
        date('Y-m-d H:i:s'),
        $affected,
        $before['in_count']
    );
    
    // Write to log file
    $logFile = __DIR__ . '/daily_reset.log';
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Output for cron email notification
    echo $logMessage;
    echo "Total active employees: " . $before['total'] . "\n";
    echo "Status: SUCCESS\n";
    
    exit(0);
    
} catch (Exception $e) {
    $errorMessage = sprintf(
        "[%s] Daily Reset FAILED: %s\n",
        date('Y-m-d H:i:s'),
        $e->getMessage()
    );
    
    // Write to log file
    $logFile = __DIR__ . '/daily_reset.log';
    file_put_contents($logFile, $errorMessage, FILE_APPEND);
    
    // Output error
    echo $errorMessage;
    echo "Status: ERROR\n";
    
    exit(1);
}
