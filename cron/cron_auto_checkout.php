<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
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

// Security: CLI, or a web call with the installation's cron key (Beheer > Automatische taken)
require_once __DIR__ . '/../includes/cron_key.php';
pd_cron_require_key('cron_auto_checkout');

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
