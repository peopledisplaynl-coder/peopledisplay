<?php
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
