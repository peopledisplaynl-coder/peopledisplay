<?php
/**
 * tests/db_smoke.php
 *
 * Optional database check, run on a developer machine with PHP + a disposable
 * MySQL/MariaDB server (never against a real PeopleDisplay database):
 *
 *   PD_TEST_DSN="mysql:host=127.0.0.1;port=3307" PD_TEST_USER=pd PD_TEST_PASS=pd php tests/db_smoke.php
 *
 * It imports install.sql the way BOTH installers do (install.php and the install/
 * wizard each split the file differently), checks the two results are identical,
 * then runs includes/migrations.php on top and checks a fresh install needs no
 * schema changes. Pass an older schema file as argument to also check that the
 * migrations bring that up to the same schema:
 *
 *   php tests/db_smoke.php path/to/old_install.sql
 *
 * Uses throw-away databases pd_smoke_*; drops them afterwards.
 */

declare(strict_types=1);

$dsn  = getenv('PD_TEST_DSN') ?: '';
$user = getenv('PD_TEST_USER') ?: '';
$pass = getenv('PD_TEST_PASS') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "PD_TEST_DSN not set — skipped.\n");
    exit(0);
}

$root = dirname(__DIR__);
$server = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$failures = 0;

function check(bool $ok, string $label): void
{
    global $failures;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $label . "\n";
    if (!$ok) $failures++;
}

function freshDb(PDO $server, string $dsn, string $user, string $pass, string $name): PDO
{
    $server->exec("DROP DATABASE IF EXISTS `$name`");
    $server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    return new PDO("$dsn;dbname=$name;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

/** Same splitting as install.php (root installer). */
function importLikeInstallPhp(PDO $pdo, string $sql): void
{
    $sql = preg_replace('/--.*$/m', '', $sql);
    $sql = preg_replace('#/\*.*?\*/#s', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt !== '') $pdo->exec($stmt);
    }
}

/** Same splitting as install/steps/step3.php (wizard). */
function importLikeWizard(PDO $pdo, string $sql): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $current = '';
    $inString = false;
    $stringChar = '';
    for ($i = 0; $i < strlen($sql); $i++) {
        $char = $sql[$i];
        if (!$inString && ($char === '"' || $char === "'")) {
            $inString = true;
            $stringChar = $char;
        } elseif ($inString && $char === $stringChar && $sql[$i - 1] !== '\\') {
            $inString = false;
        }
        $current .= $char;
        if (!$inString && $char === ';') {
            $stmt = trim($current);
            $withoutComments = preg_replace('/\/\*.*?\*\//s', '', preg_replace('/--[^\n]*\n/', '', $stmt));
            if (trim($withoutComments) !== ';' && trim($withoutComments) !== '') {
                $pdo->exec($stmt);
            }
            $current = '';
        }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

/** table => [column => type], for comparing schemas. */
function schemaOf(PDO $pdo): array
{
    $out = [];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
        foreach ($pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $out[$t][$c['Field']] = strtolower($c['Type']) . ($c['Null'] === 'NO' ? ' not null' : '');
        }
    }
    ksort($out);
    return $out;
}

function runMigrations(PDO $db, string $root): array
{
    $_SESSION = ['role' => 'superadmin', 'user_id' => null];
    $pd_migrations_changes = [];
    $pd_migrations_status = '';
    include $root . '/includes/migrations.php';
    return [$pd_migrations_status, $pd_migrations_changes];
}

$sql = file_get_contents($root . '/install.sql');

echo "Fresh install via install.php splitting\n";
$a = freshDb($server, $dsn, $user, $pass, 'pd_smoke_a');
importLikeInstallPhp($a, $sql);
$schemaA = schemaOf($a);
preg_match('/-- Tables created: (\d+)/', $sql, $m);
check(count($schemaA) === (int)$m[1], count($schemaA) . ' tables created (expected ' . $m[1] . ')');

echo "Fresh install via wizard splitting (install/sql/install.sql)\n";
$b = freshDb($server, $dsn, $user, $pass, 'pd_smoke_b');
importLikeWizard($b, file_get_contents($root . '/install/sql/install.sql'));
check(schemaOf($b) === $schemaA, 'wizard produces exactly the same schema as install.php');

echo "Migrations on a fresh install\n";
[$status, $changes] = runMigrations($a, $root);
check(!str_contains($status, 'fout'), "migrations ran without error ($status)");
$schemaChanges = array_filter($changes, fn($c) => str_starts_with($c, 'Added'));
check($schemaChanges === [], 'fresh install already has every migrated column/table'
    . ($schemaChanges ? ': ' . implode('; ', $schemaChanges) : ''));
check(schemaOf($a) === $schemaA, 'schema unchanged after migrations');
[, $changes2] = runMigrations($a, $root);
check(array_filter($changes2, fn($c) => str_starts_with($c, 'Added')) === [], 'migrations are idempotent (second run changes nothing)');

if (isset($argv[1])) {
    echo "Older schema ({$argv[1]}) + migrations\n";
    $c = freshDb($server, $dsn, $user, $pass, 'pd_smoke_c');
    importLikeWizard($c, file_get_contents($argv[1]));
    [$status, $changes] = runMigrations($c, $root);
    check(!str_contains($status, 'fout'), "migrations ran without error ($status)");
    echo '    applied: ' . implode('; ', array_filter($changes, fn($x) => str_starts_with($x, 'Added'))) . "\n";
    $after = schemaOf($c);
    $missing = [];
    foreach ($schemaA as $t => $cols) {
        foreach ($cols as $col => $type) {
            if (!isset($after[$t][$col])) $missing[] = "$t.$col";
        }
    }
    check($missing === [], 'upgraded schema has every column of a fresh install' . ($missing ? ': ' . implode(', ', $missing) : ''));
    $server->exec('DROP DATABASE IF EXISTS `pd_smoke_c`');
}

$server->exec('DROP DATABASE IF EXISTS `pd_smoke_a`');
$server->exec('DROP DATABASE IF EXISTS `pd_smoke_b`');

echo $failures ? "\n✗ $failures check(s) failed\n" : "\n✓ database checks passed\n";
exit($failures ? 1 : 0);
