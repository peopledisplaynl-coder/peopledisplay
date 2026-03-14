<?php
/**
 * BESTANDSNAAM: cron_auto_checkout.php
 * LOCATIE: /cron/cron_auto_checkout.php
 * BESCHRIJVING: Auto checkout visitors einde van dag (behalve multi-day)
 * CRONJOB: Elke dag om 23:55
 * 
 * Cron instelling (cron-job.org of server crontab):
 * 55 23 * * * /usr/bin/php /path/to/peopledisplay/cron/cron_auto_checkout.php
 */

require_once __DIR__ . '/../includes/db.php';

// Security: alleen via command line of met secret key
$secretKey = 'INZ8U56BCZ'; // Verander dit!
if (php_sapi_name() !== 'cli' && ($_GET['key'] ?? '') !== $secretKey) {
    die('Unauthorized');
}

try {
    // Auto checkout alle BINNEN visitors behalve multi-day
    $stmt = $db->prepare("
        UPDATE visitors 
        SET status = 'VERTROKKEN'
        WHERE status = 'BINNEN'
        AND is_multi_day = 0
        AND bezoek_datum = CURDATE()
    ");
    
    $stmt->execute();
    $affected = $stmt->rowCount();
    
    echo date('Y-m-d H:i:s') . " - Auto checkout: $affected visitors uitgecheckt\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
