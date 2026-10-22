<?php
/**
 * TIJDELIJK diagnose-script voor "Migratie fout (zie logs)".
 * Upload naast dashboard.php (dus in /admin/), open in de browser terwijl je
 * bent ingelogd als admin/superadmin. Verwijder daarna weer van de server.
 *
 * Voert exact dezelfde stappen uit als includes/migrations.php, maar met een
 * losse try/catch per stap — zo zie je precies welke stap faalt en met welke
 * database-foutmelding, in plaats van de algemene "Migratie fout (zie logs)".
 *
 * Stappen die al gelukt zijn worden overgeslagen (alles is idempotent, net als
 * het origineel) — dit script is dus veilig om meerdere keren te draaien.
 */
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/auth_helper.php';

if (!isAdmin()) {
    http_response_code(403);
    echo "Alleen toegankelijk voor ingelogde admins.\n";
    exit;
}

echo "=== PeopleDisplay migratie-diagnose ===\n\n";

$stepNum = 0;
$failed = false;

function step(string $label, callable $fn): void {
    global $db, $stepNum, $failed;
    $stepNum++;
    echo "[$stepNum] $label ... ";
    try {
        $result = $fn($db);
        echo "OK" . ($result ? " ($result)" : "") . "\n";
    } catch (Throwable $e) {
        $failed = true;
        echo "FOUT\n";
        echo "    Exception klasse : " . get_class($e) . "\n";
        echo "    Foutmelding      : " . $e->getMessage() . "\n";
        if ($e instanceof PDOException && isset($e->errorInfo)) {
            echo "    SQLSTATE / driver: " . json_encode($e->errorInfo) . "\n";
        }
        echo "    (script gaat door met de volgende stap)\n";
    }
}

step('CREATE TABLE user_groups', function ($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS `user_groups` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `description` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    return null;
});

step('CREATE TABLE license_tiers', function ($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS `license_tiers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tier_code` varchar(10) NOT NULL,
        `tier_name` varchar(100) NOT NULL,
        `tier_description` text DEFAULT NULL,
        `max_users` int(11) NOT NULL DEFAULT 0,
        `max_employees` int(11) NOT NULL DEFAULT 0,
        `max_locations` int(11) NOT NULL DEFAULT 0,
        `max_departments` int(11) NOT NULL DEFAULT 0,
        `features` longtext DEFAULT NULL,
        `price_eur` decimal(10,2) DEFAULT NULL,
        `sort_order` int(11) DEFAULT 0,
        `active` tinyint(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `tier_code` (`tier_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    return null;
});

step('users.group_id kolom', function ($db) {
    $col = $db->query("SHOW COLUMNS FROM `users` LIKE 'group_id'")->fetch();
    if (!$col) {
        $db->exec("ALTER TABLE `users` ADD COLUMN `group_id` int(11) DEFAULT NULL AFTER `role`");
        return 'toegevoegd';
    }
    return 'bestond al';
});

step('fk_users_group_id constraint', function ($db) {
    $fkStmt = $db->prepare("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'group_id'
          AND REFERENCED_TABLE_NAME = 'user_groups'");
    $fkStmt->execute();
    $fkExists = (int)$fkStmt->fetchColumn();
    if ($fkExists === 0) {
        $db->exec("ALTER TABLE `users` ADD CONSTRAINT `fk_users_group_id` FOREIGN KEY (`group_id`) REFERENCES `user_groups` (`id`) ON DELETE SET NULL");
        return 'toegevoegd';
    }
    return 'bestond al';
});

$empCols = [
    'visible_locations' => "TEXT DEFAULT NULL",
    'allow_manual_location_change' => "TINYINT(1) NOT NULL DEFAULT 0",
    'sub_status' => "VARCHAR(100) DEFAULT NULL",
    'sub_status_type' => "VARCHAR(100) DEFAULT NULL",
    'sub_status_until' => "DATETIME DEFAULT NULL",
    'home_locatie' => "VARCHAR(100) DEFAULT NULL COMMENT 'Vaste thuislocatie'",
];
foreach ($empCols as $colName => $definition) {
    step("employees.$colName kolom", function ($db) use ($colName, $definition) {
        $col = $db->query("SHOW COLUMNS FROM `employees` LIKE '$colName'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE `employees` ADD COLUMN `$colName` $definition");
            return 'toegevoegd';
        }
        return 'bestond al';
    });
}

step('Backfill employees.home_locatie', function ($db) {
    $db->exec("UPDATE `employees` SET `home_locatie` = `locatie` WHERE `home_locatie` IS NULL AND `locatie` IS NOT NULL AND `locatie` != ''");
    return null;
});

step('license_tiers standaardrijen (enterprise/corporate/unlimited)', function ($db) {
    $existingTiers = $db->query("SELECT tier_code FROM `license_tiers`")->fetchAll(\PDO::FETCH_COLUMN);
    $requiredTiers = [
        'enterprise' => ['Enterprise', 'Voor grote organisaties met veel locaties', 50, 300, 25, 50, 250.00, 4],
        'corporate'  => ['Corporate', 'Voor grote organisaties met veel locaties en gebruikers', 100, 500, 50, 100, 500.00, 5],
        'unlimited'  => ['Unlimited', 'Onbeperkt gebruik voor alle functionaliteit', 9999, 9999, 9999, 9999, 999.00, 6],
    ];
    $allFeatures = '{"visitor_management":true,"bhv_print":true,"sub_status":true,"kiosk_mode":true,"badge_generator":true,"bulk_actions":true,"audit_log":true}';
    $added = [];
    foreach ($requiredTiers as $code => $tier) {
        if (!in_array($code, $existingTiers)) {
            $stmt = $db->prepare("INSERT INTO `license_tiers` (tier_code, tier_name, tier_description, max_users, max_employees, max_locations, max_departments, features, price_eur, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$code, $tier[0], $tier[1], $tier[2], $tier[3], $tier[4], $tier[5], $allFeatures, $tier[6], $tier[7]]);
            $added[] = $code;
        }
    }
    return $added ? ('toegevoegd: ' . implode(', ', $added)) : 'bestonden al';
});

step('users.role ENUM (employee_manager/user_manager)', function ($db) {
    $roleCol = $db->query("SHOW COLUMNS FROM `users` LIKE 'role'")->fetch();
    if ($roleCol && strpos($roleCol['Type'], 'employee_manager') === false) {
        $db->exec("ALTER TABLE `users` MODIFY `role` ENUM('user','admin','superadmin','employee_manager','user_manager') NOT NULL DEFAULT 'user'");
        return 'uitgebreid';
    }
    return 'bevatte al employee_manager';
});

$configCols = [
    'continuity_unlocked'    => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Domeincheck permanent uitgeschakeld via continuity-sleutel'",
    'continuity_unlocked_at' => "DATETIME DEFAULT NULL",
];
foreach ($configCols as $colName => $definition) {
    step("config.$colName kolom", function ($db) use ($colName, $definition) {
        $col = $db->query("SHOW COLUMNS FROM `config` LIKE '$colName'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE `config` ADD COLUMN `$colName` $definition");
            return 'toegevoegd';
        }
        return 'bestond al';
    });
}

step('license_log.action ENUM (continuity_unlocked)', function ($db) {
    $actionCol = $db->query("SHOW COLUMNS FROM `license_log` LIKE 'action'")->fetch();
    if ($actionCol && strpos($actionCol['Type'], 'continuity_unlocked') === false) {
        $db->exec("ALTER TABLE `license_log` MODIFY `action` ENUM('activated','deactivated','validated','failed','upgraded','expired','continuity_unlocked') NOT NULL");
        return 'uitgebreid';
    }
    return 'bevatte al continuity_unlocked';
});

echo "\n";
if ($failed) {
    echo "=== Er ging minstens 1 stap FOUT — zie hierboven exact welke en waarom. ===\n";
} else {
    echo "=== Alle stappen OK. De migratie zou nu ook via het dashboard zonder fout moeten lopen. ===\n";
}
echo "=== Verwijder dit bestand weer van de server. ===\n";
