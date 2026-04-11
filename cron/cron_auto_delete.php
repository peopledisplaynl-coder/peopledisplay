<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * BESTANDSNAAM: cron_auto_delete.php
 * LOCATIE: /cron/cron_auto_delete.php
 * BESCHRIJVING: GDPR - Delete visitor data na 7 dagen
 * CRONJOB: Elke dag om 02:00
 * 
 * Cron instelling (cron-job.org of server crontab):
 * 0 2 * * * /usr/bin/php /path/to/peopledisplay/cron/cron_auto_delete.php
 */

require_once __DIR__ . '/../includes/db.php';

// Security: alleen via command line of met secret key
$secretKey = 'INZ8U56BCZ'; // Verander dit!
if (php_sapi_name() !== 'cli' && ($_GET['key'] ?? '') !== $secretKey) {
    die('Unauthorized');
}

try {
    // GDPR: Delete alle visitor records ouder dan 7 dagen
    $stmt = $db->prepare("
        DELETE FROM visitors 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    
    $stmt->execute();
    $deleted = $stmt->rowCount();
    
    echo date('Y-m-d H:i:s') . " - GDPR Auto delete: $deleted visitor records verwijderd (ouder dan 7 dagen)\n";
    
    // Log voor compliance
    $logEntry = date('Y-m-d H:i:s') . " - GDPR deletion: $deleted records\n";
    file_put_contents(__DIR__ . '/../logs/gdpr_deletions.log', $logEntry, FILE_APPEND);
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
