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
 * BESTANDSNAAM:  cleanup_tokens.php
 * UPLOAD NAAR:   /cron/cleanup_tokens.php
 * DATUM:         2024-12-04
 * VERSIE:        v1.0
 * 
 * DOEL: Verwijder verlopen remember me tokens
 * Voorkomt database bloat
 * 
 * CRON JOB SETUP:
 * 0 2 * * * php /var/www/html/peopledisplay/cron/cleanup_tokens.php
 * (Elke nacht om 02:00)
 * ============================================================================
 */

// Security: CLI, or a web call with the installation's cron key (Beheer > Automatische taken)
require_once __DIR__ . '/../includes/cron_key.php';
pd_cron_require_key('cleanup_tokens_cron');

// Include database connection
require_once __DIR__ . '/../includes/db.php';

try {
    // Count expired tokens before deletion
    $countStmt = $db->query("SELECT COUNT(*) as count FROM remember_tokens WHERE expires_at < NOW()");
    $expiredCount = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Delete expired tokens
    $stmt = $db->prepare("DELETE FROM remember_tokens WHERE expires_at < NOW()");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    
    // Log result
    $logMessage = sprintf(
        "[%s] Token Cleanup: %d expired tokens deleted\n",
        date('Y-m-d H:i:s'),
        $deleted
    );
    
    // Write to log file
    $logFile = __DIR__ . '/cleanup_tokens.log';
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Output for cron email
    echo $logMessage;
    echo "Status: SUCCESS\n";
    
    exit(0);
    
} catch (Exception $e) {
    $errorMessage = sprintf(
        "[%s] Token Cleanup FAILED: %s\n",
        date('Y-m-d H:i:s'),
        $e->getMessage()
    );
    
    // Write to log file
    $logFile = __DIR__ . '/cleanup_tokens.log';
    file_put_contents($logFile, $errorMessage, FILE_APPEND);
    
    // Output error
    echo $errorMessage;
    echo "Status: ERROR\n";
    
    exit(1);
}
