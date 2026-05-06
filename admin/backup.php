<?php
// backup.php
// Upload naar: /admin/backup.php

declare(strict_types=1);

require_once __DIR__ . '/auth_helper.php';
requireAdmin(); // basis: moet minimaal admin zijn

require_once __DIR__ . '/../includes/db.php';

// ─── Toegangscontrole backup ──────────────────────────────────────────────────
// Superadmin: altijd toegang.
// Admin: alleen als 'create_backup' expliciet aan staat in admin_features.
// Standaard UIT voor admins (bewuste keuze — backup is gevoelig).
(function (): void {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'superadmin') {
        return; // Altijd toegang
    }
    // Admin zonder expliciete vlag → geen toegang
    $canBackup = false;
    try {
        global $db;
        $stmt = $db->prepare("SELECT features FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id'] ?? 0]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $features = json_decode($row['features'] ?? '{}', true) ?? [];
        $canBackup = !empty($features['admin_features']['create_backup']);
    } catch (Exception $e) {
        // Bij DB-fout: weiger toegang (fail closed — backup is gevoelig)
    }
    if (!$canBackup) {
        header('Location: dashboard.php?error=no_permission');
        exit;
    }
})();

// ─── Configuratie ────────────────────────────────────────────────────────────
define('BACKUP_ROOT',    realpath(__DIR__ . '/..'));   // Webroot van PeopleDisplay
define('BACKUP_MAX_SEC', 300);
define('BACKUP_MAX_MEM', '256M');

// Mappen/bestanden uitsluiten van bestandsbackup
$EXCLUDE_PATHS = [
    BACKUP_ROOT . '/tmp',
    BACKUP_ROOT . '/logs',
    BACKUP_ROOT . '/backups',
    BACKUP_ROOT . '/install',
    BACKUP_ROOT . '/.git',
    BACKUP_ROOT . '/node_modules',
];

// ─── Actieverwerking ─────────────────────────────────────────────────────────
$action = $_POST['action'] ?? '';

if ($action === 'download') {
    $type = $_POST['type'] ?? 'full';
    if (!in_array($type, ['database', 'files', 'full'], true)) {
        die('Ongeldig backup type.');
    }
    runBackup($type, $db, $EXCLUDE_PATHS);
    exit;
}

// ─── Hulpfuncties ────────────────────────────────────────────────────────────

/**
 * Genereer een pure-PHP SQL dump van alle tabellen via PDO.
 */
function generateSqlDump(PDO $db): string
{
    $sql  = "-- PeopleDisplay Database Backup\n";
    $sql .= "-- Gegenereerd op: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Server: " . gethostname() . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $sql .= "SET NAMES utf8mb4;\n\n";

    // Haal alle tabellen op
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $sql .= "-- ─────────────────────────────────────────\n";
        $sql .= "-- Tabel: `$table`\n";
        $sql .= "-- ─────────────────────────────────────────\n\n";

        // DROP + CREATE TABLE
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $create = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $createSql = $create['Create Table'] ?? $create[array_key_last($create)];
        $sql .= $createSql . ";\n\n";

        // Rijen ophalen en als INSERT dumpen
        $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            $sql .= "-- (geen rijen)\n\n";
            continue;
        }

        // Kolomnamen ophalen
        $cols = array_keys($rows[0]);
        $colList = implode('`, `', $cols);

        $chunkSize = 100; // INSERT per 100 rijen
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $sql .= "INSERT INTO `$table` (`$colList`) VALUES\n";
            $valueRows = [];
            foreach ($chunk as $row) {
                $values = array_map(function ($v) use ($db): string {
                    if ($v === null) {
                        return 'NULL';
                    }
                    return $db->quote((string)$v);
                }, array_values($row));
                $valueRows[] = '  (' . implode(', ', $values) . ')';
            }
            $sql .= implode(",\n", $valueRows) . ";\n";
        }
        $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $sql .= "-- Einde backup\n";

    return $sql;
}

/**
 * Herstel-instructies als tekstbestand.
 */
function generateRestoreInstructions(string $type): string
{
    $date = date('Y-m-d H:i:s');
    $txt  = <<<EOT
================================================
  PeopleDisplay — Herstel Instructies
  Backup aangemaakt op: $date
  Backup type: $type
================================================

VOORBEREIDING
─────────────
1. Upload alle bestanden via FTP naar de nieuwe server.
2. Zorg dat PHP 8.0+ en MySQL 5.7+ beschikbaar zijn.
3. Maak een nieuwe, lege MySQL database aan.
4. Kopieer /includes/db.php.example naar /includes/db.php
   en vul de database-gegevens in.
   (Of laat de installer dit doen via /install/)

DATABASE HERSTELLEN
────────────────────
Methode A — via phpMyAdmin:
  1. Open phpMyAdmin → kies de nieuwe database.
  2. Klik op "Importeren" (Import).
  3. Kies het bestand peopledisplay_database_DATUM.sql.
  4. Klik "Uitvoeren" (Go).

Methode B — via MySQL command line:
  mysql -u GEBRUIKER -p DATABASENAAM < peopledisplay_database_DATUM.sql

BESTANDEN HERSTELLEN
─────────────────────
1. Pak het ZIP-archief uit op je lokale pc.
2. Upload alle bestanden via FTP naar de webroot
   van de nieuwe server (bijv. /public_html/ of /www/).
3. Zorg dat de mappen schrijfbaar zijn:
   - /tmp/sessions/  → chmod 755
   - /uploads/       → chmod 755
   - /uploads/profiles/ → chmod 755

SESSIE-PAD CONTROLEREN
───────────────────────
Op Strato (en andere shared hosting) wordt een aangepast
sessiepad gebruikt. Controleer /includes/session_config.php
en pas het pad aan naar een schrijfbare map op de nieuwe server.

CONFIG AANPASSEN
─────────────────
Pas de volgende bestanden aan voor de nieuwe server:
  - /includes/db.php       → DB_HOST, DB_NAME, DB_USER, DB_PASS
  - /admin/db_config.php   → zelfde gegevens
  - /config.php            → APP_URL (naar nieuwe domeinnaam)

CACHE LEEGMAKEN
────────────────
Als de server OPcache gebruikt, maak deze leeg na het uploaden
van de bestanden (bijv. via hosting control panel of door
tijdelijk een PHP-bestand te plaatsen met opcache_reset()).

TESTEN
───────
1. Open de nieuwe URL in een browser.
2. Log in met je admin-account.
3. Controleer of medewerkers en locaties correct geladen worden.
4. Test check-in/check-out functionaliteit.
5. Verwijder de /install/ map als je die niet nodig hebt.

SUPPORT
────────
Vragen? Ga naar: https://peopledisplay.nl
Of stuur een e-mail naar: info@peopledisplay.nl

================================================
EOT;
    return $txt;
}

/**
 * Voeg een bestand toe aan de ZipArchive met relatief pad.
 */
function addFileToZip(ZipArchive $zip, string $filePath, string $localPath): void
{
    if (is_readable($filePath)) {
        $zip->addFile($filePath, $localPath);
    }
}

/**
 * Voeg een hele map recursief toe aan de ZipArchive.
 */
function addDirToZip(ZipArchive $zip, string $dirPath, string $zipBase, array $excludePaths): void
{
    $realDir = realpath($dirPath);
    if ($realDir === false || !is_dir($realDir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($realDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        $filePath = $file->getRealPath();
        if ($filePath === false) {
            continue;
        }

        // Uitsluiten van bepaalde paden
        foreach ($excludePaths as $excl) {
            $realExcl = realpath($excl);
            if ($realExcl !== false && strpos($filePath, $realExcl) === 0) {
                continue 2;
            }
        }

        // Relatief pad voor in de ZIP
        $relativePath = $zipBase . '/' . ltrim(substr($filePath, strlen($realDir)), DIRECTORY_SEPARATOR);
        $relativePath = str_replace('\\', '/', $relativePath);

        if ($file->isFile() && $file->isReadable()) {
            $zip->addFile($filePath, $relativePath);
        }
    }
}

/**
 * Hoofdfunctie: bouw de backup en stuur als download.
 */
function runBackup(string $type, PDO $db, array $excludePaths): void
{
    @set_time_limit(BACKUP_MAX_SEC);
    @ini_set('memory_limit', BACKUP_MAX_MEM);

    $dateStr  = date('Y-m-d_His');
    $tmpDir   = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
    $zipName  = "peopledisplay_backup_{$type}_{$dateStr}.zip";
    $zipPath  = $tmpDir . DIRECTORY_SEPARATOR . $zipName;

    // ZipArchive aanmaken
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        echo 'Kon geen tijdelijk ZIP-bestand aanmaken. Controleer schrijfrechten op ' . htmlspecialchars($tmpDir);
        return;
    }

    // ── Database ──────────────────────────────────────────────────────────────
    if (in_array($type, ['database', 'full'], true)) {
        $sqlDump = generateSqlDump($db);
        $zip->addFromString("peopledisplay_database_{$dateStr}.sql", $sqlDump);
    }

    // ── Bestanden ─────────────────────────────────────────────────────────────
    if (in_array($type, ['files', 'full'], true)) {
        $root     = BACKUP_ROOT;
        $zipBase  = 'peopledisplay_files';

        // Root-bestanden (*.php, *.js, *.css, *.json, *.xml, .htaccess, ...)
        $rootFiles = array_merge(
            glob($root . '/*.php')  ?: [],
            glob($root . '/*.js')   ?: [],
            glob($root . '/*.css')  ?: [],
            glob($root . '/*.json') ?: [],
            glob($root . '/*.xml')  ?: [],
            glob($root . '/*.txt')  ?: [],
            glob($root . '/.htaccess') ?: []
        );
        foreach ($rootFiles as $rf) {
            if (is_file($rf) && is_readable($rf)) {
                $zip->addFile($rf, $zipBase . '/' . basename($rf));
            }
        }

        // Submappen (alles behalve uitgesloten paden)
        $subdirs = glob($root . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($subdirs as $subdir) {
            $realSub = realpath($subdir);
            // Controleer of map uitgesloten is
            $skip = false;
            foreach ($excludePaths as $excl) {
                $realExcl = realpath($excl);
                if ($realExcl !== false && $realSub === $realExcl) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }
            $folderName = basename($subdir);
            addDirToZip($zip, $subdir, $zipBase . '/' . $folderName, $excludePaths);
        }
    }

    // ── Herstel instructies ───────────────────────────────────────────────────
    if ($type === 'full') {
        $instructions = generateRestoreInstructions($type);
        $zip->addFromString('HERSTEL_INSTRUCTIES.txt', $instructions);
    }

    $zip->close();

    // ── Download sturen ───────────────────────────────────────────────────────
    if (!file_exists($zipPath) || filesize($zipPath) === 0) {
        http_response_code(500);
        echo 'ZIP-bestand is leeg of kon niet worden aangemaakt.';
        return;
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-Length: ' . filesize($zipPath));
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($zipPath);

    // Opruimen
    @unlink($zipPath);
}

// ─── Statistieken voor de UI ─────────────────────────────────────────────────
$dbStats = [];
try {
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $totalRows = 0;
    foreach ($tables as $tbl) {
        $cnt = $db->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
        $totalRows += (int)$cnt;
    }
    $dbStats = [
        'tables'     => count($tables),
        'total_rows' => $totalRows,
    ];
} catch (PDOException $e) {
    $dbStats = ['tables' => 0, 'total_rows' => 0];
}

// Schatting bestandsgrootte
function dirSize(string $dir, array $excludes = []): int
{
    $size = 0;
    if (!is_dir($dir)) {
        return 0;
    }
    try {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iter as $file) {
            $fp = $file->getRealPath();
            if ($fp === false) {
                continue;
            }
            foreach ($excludes as $ex) {
                $rex = realpath($ex);
                if ($rex !== false && strpos($fp, $rex) === 0) {
                    continue 2;
                }
            }
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
    } catch (Exception $e) {
        // Niet kritiek
    }
    return $size;
}

function formatBytes(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }
    return round($bytes / 1024, 2) . ' KB';
}

$filesSize = dirSize(BACKUP_ROOT, $EXCLUDE_PATHS);

// ─── HTML ─────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup — PeopleDisplay Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
            color: #1a1a2e;
            min-height: 100vh;
        }

        /* ── Header ── */
        .pd-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
            box-shadow: 0 2px 12px rgba(0,0,0,.3);
        }
        .pd-header h1 { font-size: 1.1rem; font-weight: 600; letter-spacing: .3px; }
        .pd-header a {
            color: rgba(255,255,255,.75);
            text-decoration: none;
            font-size: .85rem;
            transition: color .2s;
        }
        .pd-header a:hover { color: #fff; }

        /* ── Layout ── */
        .page-wrap {
            max-width: 860px;
            margin: 36px auto;
            padding: 0 16px 60px;
        }

        .page-title {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 4px;
            color: #1a1a2e;
        }
        .page-subtitle {
            color: #6b7280;
            font-size: .93rem;
            margin-bottom: 32px;
        }

        /* ── Stat cards ── */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 1px 6px rgba(0,0,0,.07);
        }
        .stat-card .label { font-size: .78rem; color: #9ca3af; text-transform: uppercase; letter-spacing: .5px; }
        .stat-card .value { font-size: 1.5rem; font-weight: 700; margin-top: 4px; color: #1a1a2e; }
        .stat-card .sub   { font-size: .78rem; color: #9ca3af; margin-top: 2px; }

        /* ── Backup cards ── */
        .backup-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
            margin-bottom: 32px;
        }
        .backup-card {
            background: #fff;
            border-radius: 14px;
            padding: 28px 24px;
            box-shadow: 0 1px 6px rgba(0,0,0,.08);
            border: 2px solid transparent;
            transition: border-color .2s, box-shadow .2s, transform .15s;
            cursor: pointer;
            position: relative;
        }
        .backup-card:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 20px rgba(59,130,246,.15);
            transform: translateY(-2px);
        }
        .backup-card.selected {
            border-color: #3b82f6;
            background: #eff6ff;
        }
        .backup-card input[type="radio"] { display: none; }

        .backup-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 14px;
        }
        .icon-db    { background: #dbeafe; }
        .icon-files { background: #d1fae5; }
        .icon-full  { background: #ede9fe; }

        .backup-card h3 { font-size: 1rem; font-weight: 700; margin-bottom: 6px; }
        .backup-card p  { font-size: .84rem; color: #6b7280; line-height: 1.5; }

        .badge-recommended {
            position: absolute;
            top: 14px;
            right: 14px;
            background: #7c3aed;
            color: #fff;
            font-size: .68rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 99px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        /* ── Knop ── */
        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px 32px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .2s, transform .15s;
            box-shadow: 0 4px 14px rgba(59,130,246,.4);
        }
        .btn-download:hover   { opacity: .92; transform: translateY(-1px); }
        .btn-download:active  { transform: translateY(0); }
        .btn-download:disabled { opacity: .6; cursor: not-allowed; }
        .btn-download svg { width: 20px; height: 20px; }

        /* ── Info box ── */
        .info-box {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 10px;
            padding: 14px 18px;
            font-size: .86rem;
            color: #92400e;
            margin-top: 20px;
            line-height: 1.6;
        }
        .info-box strong { display: block; margin-bottom: 4px; color: #78350f; }

        /* ── Voortgangsbalk ── */
        .progress-wrap {
            display: none;
            margin-top: 20px;
            background: #e5e7eb;
            border-radius: 99px;
            overflow: hidden;
            height: 6px;
        }
        .progress-wrap.active { display: block; }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
            border-radius: 99px;
            animation: progAnim 2s ease-in-out infinite;
            width: 60%;
        }
        @keyframes progAnim {
            0%   { transform: translateX(-100%); }
            100% { transform: translateX(250%); }
        }

        .status-msg {
            display: none;
            margin-top: 10px;
            font-size: .87rem;
            color: #374151;
        }
        .status-msg.visible { display: block; }

        /* ── Tabel ── */
        .exclude-list {
            background: #fff;
            border-radius: 12px;
            padding: 20px 24px;
            box-shadow: 0 1px 6px rgba(0,0,0,.07);
            margin-top: 32px;
        }
        .exclude-list h4 { font-size: .93rem; font-weight: 700; margin-bottom: 12px; color: #374151; }
        .exclude-list ul { padding-left: 18px; }
        .exclude-list li { font-size: .83rem; color: #6b7280; line-height: 1.8; font-family: monospace; }

        @media (max-width: 600px) {
            .backup-grid { grid-template-columns: 1fr; }
            .stat-grid   { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<header class="pd-header">
    <h1>🗄️ Backup &amp; Herstel</h1>
    <a href="dashboard.php">← Terug naar dashboard</a>
</header>

<div class="page-wrap">

    <h2 class="page-title">Backup aanmaken</h2>
    <p class="page-subtitle">Kies het type backup en download direct als ZIP-bestand.</p>

    <!-- Statistieken -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="label">Database tabellen</div>
            <div class="value"><?= $dbStats['tables'] ?></div>
            <div class="sub"><?= number_format($dbStats['total_rows']) ?> rijen totaal</div>
        </div>
        <div class="stat-card">
            <div class="label">Bestandsgrootte</div>
            <div class="value"><?= formatBytes($filesSize) ?></div>
            <div class="sub">Geschatte ZIP &asymp; <?= formatBytes((int)($filesSize * 0.6)) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">PHP tijdslimiet</div>
            <div class="value"><?= BACKUP_MAX_SEC ?>s</div>
            <div class="sub">Memory: <?= BACKUP_MAX_MEM ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Server</div>
            <div class="value" style="font-size:1rem;margin-top:6px;">
                <?= htmlspecialchars(php_uname('n') ?: gethostname()) ?>
            </div>
            <div class="sub">PHP <?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?></div>
        </div>
    </div>

    <!-- Keuze -->
    <form id="backupForm" method="POST" action="backup.php">
        <input type="hidden" name="action" value="download">

        <div class="backup-grid">

            <!-- Database -->
            <label class="backup-card" id="card-database" onclick="selectCard('database')">
                <input type="radio" name="type" value="database" id="type-database">
                <div class="backup-icon icon-db">🗃️</div>
                <h3>Database only</h3>
                <p>Alleen de MySQL database als .sql bestand.<br>
                   Inclusief alle tabellen en data.</p>
            </label>

            <!-- Bestanden -->
            <label class="backup-card" id="card-files" onclick="selectCard('files')">
                <input type="radio" name="type" value="files" id="type-files">
                <div class="backup-icon icon-files">📁</div>
                <h3>Bestanden only</h3>
                <p>Alle PHP, JS, CSS en overige bestanden als ZIP.<br>
                   Exclusief tmp/, logs/ en install/.</p>
            </label>

            <!-- Volledig -->
            <label class="backup-card selected" id="card-full" onclick="selectCard('full')">
                <span class="badge-recommended">Aanbevolen</span>
                <input type="radio" name="type" value="full" id="type-full" checked>
                <div class="backup-icon icon-full">📦</div>
                <h3>Volledige backup</h3>
                <p>Database + bestanden + herstel­instructies in één ZIP.<br>
                   Ideaal voor verhuizing naar andere server.</p>
            </label>

        </div>

        <button type="submit" class="btn-download" id="downloadBtn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2
                         M7 10l5 5 5-5
                         M12 15V3"/>
            </svg>
            Backup downloaden
        </button>

        <div class="progress-wrap" id="progressWrap">
            <div class="progress-bar"></div>
        </div>
        <p class="status-msg" id="statusMsg">⏳ Backup wordt aangemaakt, even geduld&hellip;</p>

        <div class="info-box">
            <strong>⚠️ Let op</strong>
            Een volledige backup van grote installaties kan 1–3 minuten duren.
            Sluit het browser­venster <em>niet</em> totdat de download start.
            Downloads worden tijdelijk in <code><?= htmlspecialchars(sys_get_temp_dir()) ?></code> gebufferd en daarna gewist.
        </div>
    </form>

    <!-- Uitgesloten paden -->
    <div class="exclude-list">
        <h4>🚫 Uitgesloten van bestandsbackup</h4>
        <ul>
            <?php foreach ($EXCLUDE_PATHS as $ep): ?>
                <li><?= htmlspecialchars(str_replace(BACKUP_ROOT, '', $ep) ?: '/') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

</div><!-- /page-wrap -->

<script>
function selectCard(type) {
    document.querySelectorAll('.backup-card').forEach(c => c.classList.remove('selected'));
    document.getElementById('card-' + type).classList.add('selected');
    document.getElementById('type-' + type).checked = true;
}

document.getElementById('backupForm').addEventListener('submit', function () {
    const btn = document.getElementById('downloadBtn');
    btn.disabled = true;
    btn.innerHTML = '⏳ Bezig met aanmaken…';

    document.getElementById('progressWrap').classList.add('active');
    document.getElementById('statusMsg').classList.add('visible');

    // Na 5 sec knop weer inschakelen zodat gebruiker opnieuw kan proberen
    setTimeout(function () {
        btn.disabled = false;
        btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
            stroke="currentColor" stroke-width="2.2" style="width:20px;height:20px">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
            Opnieuw downloaden`;
        document.getElementById('progressWrap').classList.remove('active');
        document.getElementById('statusMsg').textContent = '✅ Download zou gestart moeten zijn.';
    }, 5000);
});
</script>

</body>
</html>
