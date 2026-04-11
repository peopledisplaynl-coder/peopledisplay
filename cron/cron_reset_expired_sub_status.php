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
 * CRONJOB: Auto-Reset Verlopen Sub-Statussen
 * ============================================================================
 * Bestand: cron_reset_expired_sub_status.php
 * Locatie: /cron/cron_reset_expired_sub_status.php
 * 
 * Run elke minuut via crontab:
 * * * * * * php /path/to/cron_reset_expired_sub_status.php >> /path/to/logs/cron.log 2>&1
 * 
 * Checkt sub_status_until en reset indien verlopen
 * ============================================================================
 */

require_once __DIR__ . '/../includes/db.php';

$logFile = __DIR__ . '/../logs/sub_status_reset.log';

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$message}\n";
    
    // Ensure logs directory exists
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

try {
    logMessage("=== Starting expired sub-status check ===");
    
    // Find employees with expired sub_status_until
    $stmt = $db->query("
        SELECT 
            employee_id, 
            naam, 
            sub_status, 
            sub_status_until
        FROM employees
        WHERE actief = 1
          AND sub_status IS NOT NULL
          AND sub_status_until IS NOT NULL
          AND sub_status_until <= NOW()
    ");
    
    $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($expired)) {
        logMessage("No expired sub-statuses found");
    } else {
        logMessage("Found " . count($expired) . " expired sub-statuses");
        
        // Reset them
        $updateStmt = $db->prepare("
            UPDATE employees
            SET sub_status = NULL,
                sub_status_until = NULL,
                tijdstip = NOW()
            WHERE employee_id = ?
        ");
        
        $resetCount = 0;
        foreach ($expired as $emp) {
            $updateStmt->execute([$emp['employee_id']]);
            
            if ($updateStmt->rowCount() > 0) {
                $resetCount++;
                logMessage("  ✅ Reset: {$emp['naam']} ({$emp['employee_id']}) - {$emp['sub_status']} expired at {$emp['sub_status_until']}");
            }
        }
        
        logMessage("=== Reset complete: {$resetCount} employees updated ===");
    }
    
} catch (Exception $e) {
    logMessage("❌ ERROR: " . $e->getMessage());
}
