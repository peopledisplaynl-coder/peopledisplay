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

    if (count($pd_migrations_changes) > 0) {
        $pd_migrations_status = 'Migraties toegepast';
    }
} catch (Throwable $e) {
    // Keep migrations silent for end users; log errors for debugging.
    error_log('migrations.php error: ' . $e->getMessage());
    $pd_migrations_status = 'Migratie fout (zie logs)';
}
