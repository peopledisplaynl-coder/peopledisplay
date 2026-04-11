<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * Database migrations executed on admin dashboard load.
 *
 * This script is intentionally silent and idempotent. If a schema change is already
 * applied it will do nothing and will not display errors to end users.
 *
 * It is safe to include on every admin page load.
 */

// Only run when a database connection is available.
if (!isset($db) || !($db instanceof \PDO)) {
    return;
}

// Only run for admin/superadmin users.
$role = $_SESSION['role'] ?? null;
if (!in_array($role, ['admin', 'superadmin'], true)) {
    return;
}

// Expose a small status indicator for admin UI.
$pd_migrations_status = 'Database schema gecontroleerd';
$pd_migrations_changes = [];

try {
    // Ensure the user_groups table exists.
    $db->exec("CREATE TABLE IF NOT EXISTS `user_groups` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `description` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pd_migrations_changes[] = 'user_groups table ensured';

    // Ensure license_tiers table exists (used for tier-based licensing/configuration).
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
    $pd_migrations_changes[] = 'license_tiers table ensured';

    // Ensure users.group_id column exists.
    $col = $db->query("SHOW COLUMNS FROM `users` LIKE 'group_id'")->fetch();
    if (!$col) {
        $db->exec("ALTER TABLE `users` ADD COLUMN `group_id` int(11) DEFAULT NULL AFTER `role`");
        $pd_migrations_changes[] = 'Added users.group_id column';
    }

    // Ensure foreign key constraint exists.
    $fkStmt = $db->prepare("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'group_id'
          AND REFERENCED_TABLE_NAME = 'user_groups'");
    $fkStmt->execute();
    $fkExists = (int)$fkStmt->fetchColumn();

    if ($fkExists === 0) {
        $db->exec("ALTER TABLE `users` ADD CONSTRAINT `fk_users_group_id` FOREIGN KEY (`group_id`) REFERENCES `user_groups` (`id`) ON DELETE SET NULL");
        $pd_migrations_changes[] = 'Added fk_users_group_id constraint';
    }

    // Ensure employees table has the new columns introduced in recent updates.
    $empCols = [
        'visible_locations' => "TEXT DEFAULT NULL",
        'allow_manual_location_change' => "TINYINT(1) NOT NULL DEFAULT 0",
        'sub_status' => "VARCHAR(100) DEFAULT NULL",
        'sub_status_type' => "VARCHAR(100) DEFAULT NULL",
        'sub_status_until' => "DATETIME DEFAULT NULL",
    ];

    foreach ($empCols as $colName => $definition) {
        $col = $db->query("SHOW COLUMNS FROM `employees` LIKE '$colName'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE `employees` ADD COLUMN `$colName` $definition");
            $pd_migrations_changes[] = "Added employees.$colName column";
        }
    }

    // Ensure all 6 license tiers exist in the database
    $existingTiers = $db->query("SELECT tier_code FROM `license_tiers`")->fetchAll(\PDO::FETCH_COLUMN);
    $requiredTiers = [
        'enterprise' => ['Enterprise', 'Voor grote organisaties met veel locaties', 50, 300, 25, 50, 250.00, 4],
        'corporate'  => ['Corporate', 'Voor grote organisaties met veel locaties en gebruikers', 100, 500, 50, 100, 500.00, 5],
        'unlimited'  => ['Unlimited', 'Onbeperkt gebruik voor alle functionaliteit', 9999, 9999, 9999, 9999, 999.00, 6],
    ];
    $allFeatures = '{"visitor_management":true,"bhv_print":true,"sub_status":true,"kiosk_mode":true,"badge_generator":true,"bulk_actions":true,"audit_log":true}';
    foreach ($requiredTiers as $code => $tier) {
        if (!in_array($code, $existingTiers)) {
            $stmt = $db->prepare("INSERT INTO `license_tiers` (tier_code, tier_name, tier_description, max_users, max_employees, max_locations, max_departments, features, price_eur, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$code, $tier[0], $tier[1], $tier[2], $tier[3], $tier[4], $tier[5], $allFeatures, $tier[6], $tier[7]]);
            $pd_migrations_changes[] = "Added license tier: $code";
        }
    }

    // Ensure users.role ENUM includes employee_manager and user_manager
    $roleCol = $db->query("SHOW COLUMNS FROM `users` LIKE 'role'")->fetch();
    if ($roleCol && strpos($roleCol['Type'], 'employee_manager') === false) {
        $db->exec("ALTER TABLE `users` MODIFY `role` ENUM('user','admin','superadmin','employee_manager','user_manager') NOT NULL DEFAULT 'user'");
        $pd_migrations_changes[] = 'Added employee_manager and user_manager roles to users.role ENUM';
    }

    if (count($pd_migrations_changes) > 0) {
        $pd_migrations_status = 'Migraties toegepast';
    }

    // Log migration run (even when no changes were made) to the employee_audit table.
    // This helps track when the site checked/apply schema updates.
    try {
        $action = 'MIGRATION';
        $details = count($pd_migrations_changes) > 0
            ? implode('; ', $pd_migrations_changes)
            : 'No changes needed';

        $stmt = $db->prepare("INSERT INTO employee_audit (employee_id, action, field_changed, old_value, new_value, changed_by, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            null,
            $action,
            'schema',
            null,
            $details,
            $_SESSION['user_id'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } catch (Throwable $e) {
        // Ignore audit failures; keep migrations silent.
    }

} catch (Throwable $e) {
    // Keep migrations silent for end users; log errors for debugging.
    error_log('migrations.php error: ' . $e->getMessage());
    $pd_migrations_status = 'Migratie fout (zie logs)';
}
