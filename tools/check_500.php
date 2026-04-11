<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Voeg dit toe HELEMAAL BOVENAAN, vóór de require_once regels
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/admin/users_manage.php';

function dump($label, $value) {
    echo "<h2>" . htmlentities($label) . "</h2>\n";
    echo "<pre>" . htmlentities(print_r($value, true)) . "</pre>\n";
}

function safeQuery(PDO $db, string $sql, array $params = []) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return ['__error' => $e->getMessage()];
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Check 500 Diagnostic</title>
    <style>
        body { font-family: system-ui, sans-serif; padding: 24px; background: #f7fafc; color: #111; }
        pre { background: #111; color: #eee; padding: 12px; border-radius: 8px; overflow-x: auto; }
        .box { background: white; border: 1px solid #ddd; padding: 18px; border-radius: 10px; margin-bottom: 24px; }
        .warn { background: #fff4e5; border-color: #ffae42; }
    </style>
</head>
<body>
<h1>Diagnostics: HTTP 500 troubleshooting</h1>

<div class="box">
    <h2>PHP Environment</h2>
    <?php dump('PHP Version', PHP_VERSION); ?>
    <?php dump('PDO extensions', ['pdo' => extension_loaded('pdo'), 'pdo_mysql' => extension_loaded('pdo_mysql')]); ?>
</div>

<div class="box">
    <h2>Database Connection</h2>
    <?php
    dump('DB object class', get_class($db));
    try {
        $ver = $db->query('SELECT VERSION() AS v')->fetchColumn();
        dump('MySQL version', $ver);
    } catch (Throwable $e) {
        dump('MySQL version query FAILED', $e->getMessage());
    }
    ?>
</div>

<div class="box warn">
    <h2>Users table</h2>
    <?php
    dump('users table exists?', safeQuery($db, "SHOW TABLES LIKE 'users'"));
    dump('users columns', safeQuery($db, "SHOW COLUMNS FROM `users`"));
    dump('group_id column exists?', safeQuery($db, "SHOW COLUMNS FROM `users` LIKE 'group_id'"));
    ?>
</div>

<div class="box warn">
    <h2>Employees table</h2>
    <?php
    dump('employees table exists?', safeQuery($db, "SHOW TABLES LIKE 'employees'"));
    dump('employees columns', safeQuery($db, "SHOW COLUMNS FROM `employees`"));
    dump('visible_locations column?', safeQuery($db, "SHOW COLUMNS FROM `employees` LIKE 'visible_locations'"));
    dump('allow_manual_location_change column?', safeQuery($db, "SHOW COLUMNS FROM `employees` LIKE 'allow_manual_location_change'"));
    ?>
</div>

<div class="box warn">
    <h2>user_groups table</h2>
    <?php
    dump('user_groups table exists?', safeQuery($db, "SHOW TABLES LIKE 'user_groups'"));
    dump('user_groups sample', safeQuery($db, "SELECT * FROM user_groups LIMIT 5"));
    ?>
</div>

<div class="box">
    <h2>Sample queries</h2>
    <?php
    dump('Session user_id', $_SESSION['user_id'] ?? '(not set)');
    dump('employees sample', safeQuery($db, 'SELECT employee_id, naam, status FROM employees WHERE actief = 1 LIMIT 3'));
    dump('users sample', safeQuery($db, 'SELECT id, username, role FROM users LIMIT 3'));
    ?>
</div>

<div class="box">
    <h2>Migration status</h2>
    <?php
    dump('pd_migrations_status', $pd_migrations_status ?? '(not set)');
    dump('pd_migrations_changes', $pd_migrations_changes ?? '(not set)');
    ?>
</div>

</body>
</html>
