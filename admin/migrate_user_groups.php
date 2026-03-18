<?php
/**
 * Migration helper: Ensure user_groups table exists and users.group_id column is present.
 *
 * Usage (CLI): php migrate_user_groups.php
 * Usage (browser): visit /admin/migrate_user_groups.php while logged in as admin/superadmin.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/auth_helper.php';

requireAdmin();

function ensureUserGroupsSchema(PDO $db): array
{
    $results = [];

    try {
        $db->exec("CREATE TABLE IF NOT EXISTS user_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $results[] = 'user_groups table ensured';

        $col = $db->query("SHOW COLUMNS FROM users LIKE 'group_id'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE users ADD COLUMN group_id INT NULL AFTER role");
            $results[] = 'Added group_id column to users';
        } else {
            $results[] = 'users.group_id already exists';
        }

        $fkStmt = $db->prepare("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = 'group_id'
              AND REFERENCED_TABLE_NAME = 'user_groups'");
        $fkStmt->execute();
        $fkExists = (int)$fkStmt->fetchColumn();

        if ($fkExists === 0) {
            $db->exec("ALTER TABLE users ADD CONSTRAINT fk_users_group_id FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE SET NULL");
            $results[] = 'Added foreign key constraint fk_users_group_id';
        } else {
            $results[] = 'Foreign key fk_users_group_id already exists';
        }
    } catch (Throwable $e) {
        $results[] = 'ERROR: ' . $e->getMessage();
    }

    return $results;
}

$results = ensureUserGroupsSchema($db);

if (php_sapi_name() === 'cli') {
    foreach ($results as $line) {
        echo $line . "\n";
    }
    exit(0);
}

?><!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Groepen Migratie</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; padding: 30px; background: #f7fafc; color: #1a202c; }
        .card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 1px 6px rgba(0,0,0,0.1); max-width: 760px; margin: 0 auto; }
        h1 { margin-top: 0; }
        ul { margin: 16px 0; padding-left: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Groepen migratie</h1>
        <p>De migratie is uitgevoerd. Hieronder vind je de uitgevoerde stappen:</p>
        <ul>
            <?php foreach ($results as $line): ?>
                <li><?= htmlspecialchars($line) ?></li>
            <?php endforeach; ?>
        </ul>
        <p><a href="users_manage.php">← Terug naar Gebruikersbeheer</a></p>
    </div>
</body>
</html>
