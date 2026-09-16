<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * ============================================================
 * PeopleDisplay — Restore vanaf volledige back-up
 * ============================================================
 * Location: /restore.php (root of the application)
 *
 * Zet een ZIP terug die is gemaakt via admin/backup.php (type "Volledige
 * backup"): database + bestanden. Bedoeld voor verhuizing naar een ander
 * domein of een andere hosting-partij, zonder tussenkomst nodig van
 * peopledisplay.nl.
 *
 * Steps:
 *   1. Back-up ZIP aanleveren (upload of al ge-FTP't bestand)
 *   2. Database gegevens
 *   3. Bevestigen & uitvoeren
 *   4. Klaar
 *
 * SECURITY:
 *   Alleen bruikbaar zolang er nog geen admin/db_config.php bestaat EN de
 *   installer nog niet vergrendeld is (install/.installed) — exact dezelfde
 *   voorwaarde als install.php. Na afloop wordt dezelfde vergrendeling gezet,
 *   dus restore.php sluit zichzelf net als install.php automatisch af.
 * ============================================================
 */

declare(strict_types=1);

// ============================================================
// LOCK CHECK — zelfde voorwaarde als install.php
// ============================================================
$installedMarker = __DIR__ . '/install/.installed';
$dbConfigFile     = __DIR__ . '/admin/db_config.php';
$isLocked         = file_exists($installedMarker) || file_exists($dbConfigFile);

if ($isLocked) {
    ?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PeopleDisplay — Restore</title>
<style>
body { font-family: Arial, sans-serif; background: #f0f4f8; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
.card { background: white; border-radius: 12px; padding: 40px; max-width: 480px; text-align: center; box-shadow: 0 4px 24px rgba(0,0,0,0.1); }
.icon { font-size: 48px; margin-bottom: 16px; }
h1 { color: #2d3748; margin-bottom: 8px; font-size: 22px; }
p { color: #718096; margin-bottom: 24px; line-height: 1.6; }
a.btn { display: inline-block; padding: 12px 28px; background: #667eea; color: white; border-radius: 8px; text-decoration: none; font-weight: 600; }
a.btn-secondary { background: #e2e8f0; color: #4a5568; margin-left: 8px; }
</style>
</head>
<body>
<div class="card">
    <div class="icon">🔒</div>
    <h1>Restore vergrendeld</h1>
    <p>Er staat al een installatie op dit domein (er is al een <code>admin/db_config.php</code>
    of de installer is al vergrendeld). Restore.php werkt alleen op een verse, lege installatie
    &mdash; net als install.php.</p>
    <p>Wilt u een bestaande installatie overschrijven met een back-up? Verwijder dan eerst
    <code>admin/db_config.php</code> en <code>install/.installed</code> handmatig via FTP.
    Doe dit alleen als u zeker weet dat u de huidige data wilt vervangen.</p>
    <a href="index.php" class="btn">Naar de applicatie</a>
    <a href="admin/dashboard.php" class="btn btn-secondary">Admin panel</a>
</div>
</body>
</html>
    <?php
    exit;
}

session_start();

$uploadDir = __DIR__ . '/restore_upload';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0750, true);
}
// Bestanden in restore_upload/ nooit direct via de browser serveren.
$htaccessGuard = $uploadDir . '/.htaccess';
if (!file_exists($htaccessGuard)) {
    @file_put_contents($htaccessGuard, "Require all denied\n");
}

$step   = (int)($_POST['step'] ?? $_GET['step'] ?? 1);
$errors  = [];
$success = [];

// ============================================================
// Helper: quote-aware SQL statement splitter.
// A naive explode(';') corrupts real customer data that happens to
// contain a literal semicolon inside a text field — this respects
// single/double/backtick-quoted strings and backslash escapes.
// ============================================================
function pdSplitSqlStatements(string $sql): array {
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $sql = preg_replace('#/\*.*?\*/#s', '', $sql);

    $statements = [];
    $current    = '';
    $len        = strlen($sql);
    $inSingle   = false;
    $inDouble   = false;
    $inBacktick = false;

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $current .= $ch;

        if ($ch === '\\' && ($inSingle || $inDouble) && $i + 1 < $len) {
            $i++;
            $current .= $sql[$i];
            continue;
        }

        if ($ch === "'" && !$inDouble && !$inBacktick) {
            $inSingle = !$inSingle;
        } elseif ($ch === '"' && !$inSingle && !$inBacktick) {
            $inDouble = !$inDouble;
        } elseif ($ch === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
        } elseif ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $stmt = trim(substr($current, 0, -1));
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $current = '';
        }
    }
    $tail = trim($current);
    if ($tail !== '') {
        $statements[] = $tail;
    }
    return $statements;
}

// ============================================================
// Helper: veilig ZIP-bestanden uitpakken (zip-slip bescherming).
// ============================================================
function pdSafeExtractZip(ZipArchive $zip, string $destDir): int {
    $count = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name === false || $name === '') {
            continue;
        }
        $normalized = str_replace('\\', '/', $name);
        if (str_contains($normalized, '../') || str_starts_with($normalized, '/') || preg_match('#^[A-Za-z]:#', $normalized)) {
            continue; // verdachte entry — overslaan
        }
        $target = $destDir . '/' . $normalized;

        if (substr($normalized, -1) === '/') {
            @mkdir($target, 0755, true);
            continue;
        }
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        $stream = $zip->getStream($name);
        if ($stream === false) {
            continue;
        }
        $out = @fopen($target, 'wb');
        if ($out === false) {
            fclose($stream);
            continue;
        }
        stream_copy_to_stream($stream, $out);
        fclose($stream);
        fclose($out);
        $count++;
    }
    return $count;
}

// ============================================================
// Helper: recursief kopiëren, met paden om over te slaan.
// ============================================================
function pdCopyRecursive(string $src, string $dst, array $skipRelPaths): void {
    $src = rtrim($src, '/');
    $dst = rtrim($dst, '/');
    if (!is_dir($src)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $rel = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($src))), '/');
        foreach ($skipRelPaths as $skip) {
            if ($rel === $skip || str_starts_with($rel, $skip . '/')) {
                continue 2;
            }
        }
        $target = $dst . '/' . $rel;
        if ($item->isDir()) {
            if (!is_dir($target)) {
                @mkdir($target, 0755, true);
            }
        } else {
            $targetDir = dirname($target);
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }
            @copy($item->getPathname(), $target);
        }
    }
}

// ============================================================
// STEP 1 — ZIP aanleveren (upload of pad naar ge-FTP't bestand)
// ============================================================
if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $zipPath = null;

    if (!empty($_FILES['backup_zip']['tmp_name']) && is_uploaded_file($_FILES['backup_zip']['tmp_name'])) {
        $dest = $uploadDir . '/upload_' . time() . '.zip';
        if (move_uploaded_file($_FILES['backup_zip']['tmp_name'], $dest)) {
            $zipPath = $dest;
        } else {
            $errors[] = 'Uploaden is mislukt. Probeer het bestand via FTP naar restore_upload/ te plaatsen en het pad hieronder in te vullen.';
        }
    } elseif (!empty($_POST['server_path'])) {
        $candidate = $uploadDir . '/' . basename(trim($_POST['server_path']));
        if (file_exists($candidate)) {
            $zipPath = $candidate;
        } else {
            $errors[] = 'Bestand niet gevonden in restore_upload/. Controleer de bestandsnaam.';
        }
    } else {
        $errors[] = 'Kies een back-up ZIP om te uploaden, of geef de bestandsnaam op van een bestand dat u al via FTP naar restore_upload/ heeft geplaatst.';
    }

    if ($zipPath !== null) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $errors[] = 'Kan het ZIP-bestand niet openen — is het een geldige PeopleDisplay back-up?';
        } else {
            $hasSql   = false;
            $hasFiles = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = $zip->getNameIndex($i);
                if ($n !== false && preg_match('/^peopledisplay_database_.*\.sql$/', $n)) {
                    $hasSql = true;
                }
                if ($n !== false && str_starts_with($n, 'peopledisplay_files/')) {
                    $hasFiles = true;
                }
            }
            $zip->close();

            if (!$hasSql && !$hasFiles) {
                $errors[] = 'Dit lijkt geen PeopleDisplay back-up ZIP te zijn (geen database of bestanden gevonden).';
                @unlink($zipPath);
            } else {
                $_SESSION['pd_restore']['zip_path']  = $zipPath;
                $_SESSION['pd_restore']['has_sql']   = $hasSql;
                $_SESSION['pd_restore']['has_files'] = $hasFiles;
                $step = 2;
            }
        }
    }
}

// ============================================================
// STEP 2 — Database gegevens testen
// ============================================================
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['db_host'])) {
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';

    if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
        $errors[] = 'Vul alle databasevelden in.';
        $step = 2;
    } else {
        try {
            $testDsn = "mysql:host={$dbHost};charset=utf8mb4";
            $testPdo = new PDO($testDsn, $dbUser, $dbPass, [PDO::ATTR_TIMEOUT => 5]);
            $check   = $testPdo->query("SHOW DATABASES LIKE " . $testPdo->quote($dbName));
            if ($check->rowCount() === 0) {
                $testPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
            $_SESSION['pd_restore']['db_host'] = $dbHost;
            $_SESSION['pd_restore']['db_name'] = $dbName;
            $_SESSION['pd_restore']['db_user'] = $dbUser;
            $_SESSION['pd_restore']['db_pass'] = $dbPass;
            $step = 3;
        } catch (PDOException $e) {
            $errors[] = 'Kan geen verbinding maken met de database: ' . $e->getMessage();
            $step = 2;
        }
    }
}
if ($step === 2 && empty($_SESSION['pd_restore']['zip_path'])) {
    $step = 1;
    $errors[] = 'Begin opnieuw — er is geen back-up ZIP bekend voor deze sessie.';
}

// ============================================================
// STEP 3 — Uitvoeren: uitpakken, database importeren, bestanden herstellen
// ============================================================
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_restore'])) {
    @set_time_limit(300);
    @ini_set('memory_limit', '256M');

    $zipPath = $_SESSION['pd_restore']['zip_path'] ?? '';
    $dbHost  = $_SESSION['pd_restore']['db_host']  ?? '';
    $dbName  = $_SESSION['pd_restore']['db_name']  ?? '';
    $dbUser  = $_SESSION['pd_restore']['db_user']  ?? '';
    $dbPass  = $_SESSION['pd_restore']['db_pass']  ?? '';

    if (!file_exists($zipPath)) {
        $errors[] = 'De back-up ZIP is niet meer beschikbaar. Begin opnieuw.';
        $step = 1;
    } else {
        $tmpExtract = rtrim(sys_get_temp_dir(), '/') . '/pd_restore_' . time() . '_' . bin2hex(random_bytes(4));
        @mkdir($tmpExtract, 0750, true);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $errors[] = 'Kan het ZIP-bestand niet openen.';
            $step = 1;
        } else {
            pdSafeExtractZip($zip, $tmpExtract);
            $zip->close();

            // ── Bewaar PD_LICENSE_SALT / PD_CONTINUITY_SECRET uit de oude db_config.php ──
            $preservedDefines = '';
            $oldDbConfig = $tmpExtract . '/peopledisplay_files/admin/db_config.php';
            if (file_exists($oldDbConfig)) {
                $oldContent = (string)file_get_contents($oldDbConfig);
                foreach (['PD_LICENSE_SALT', 'PD_CONTINUITY_SECRET'] as $constName) {
                    if (preg_match('/define\s*\(\s*[\'"]' . $constName . '[\'"]\s*,\s*[\'"](.*?)[\'"]\s*\)/s', $oldContent, $m)) {
                        $preservedDefines .= "define('{$constName}', '" . addslashes($m[1]) . "');\n";
                    }
                }
            }

            // ── Database importeren ──
            $importedTables = 0;
            $sqlFiles = glob($tmpExtract . '/peopledisplay_database_*.sql') ?: [];
            if (!empty($sqlFiles)) {
                try {
                    $pdo = new PDO(
                        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
                        $dbUser, $dbPass,
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    $sql = (string)file_get_contents($sqlFiles[0]);
                    $statements = pdSplitSqlStatements($sql);
                    foreach ($statements as $stmt) {
                        if ($stmt === '' || stripos($stmt, 'SET FOREIGN_KEY_CHECKS') === 0 || stripos($stmt, 'SET SQL_MODE') === 0 || stripos($stmt, 'SET NAMES') === 0) {
                            // deze SET-statements voeren we bewust zelf uit, hieronder, i.p.v. uit de dump
                            continue;
                        }
                        $pdo->exec($stmt);
                        if (stripos($stmt, 'CREATE TABLE') !== false) {
                            $importedTables++;
                        }
                    }
                    $success[] = "Database geïmporteerd — {$importedTables} tabellen aangemaakt.";
                } catch (PDOException $e) {
                    $errors[] = 'Fout bij importeren van de database: ' . $e->getMessage();
                }
            } else {
                $errors[] = 'Geen database-dump gevonden in de ZIP — alleen bestanden hersteld (indien aanwezig).';
            }

            // ── Bestanden herstellen (behalve db_config.php, restore.php zelf, install/) ──
            $filesRoot = $tmpExtract . '/peopledisplay_files';
            if (is_dir($filesRoot)) {
                pdCopyRecursive($filesRoot, __DIR__, [
                    'admin/db_config.php',
                    'restore.php',
                    'restore_upload',
                    'install',
                    '.git',
                ]);
                $success[] = 'Bestanden (inclusief uploads/foto\'s) hersteld.';
            }

            // ── Nieuwe admin/db_config.php schrijven ──
            $configContent = "<?php\n"
                . "\$DB_HOST='" . addslashes($dbHost) . "';\n"
                . "\$DB_NAME='" . addslashes($dbName) . "';\n"
                . "\$DB_USER='" . addslashes($dbUser) . "';\n"
                . "\$DB_PASS='" . addslashes($dbPass) . "';\n"
                . $preservedDefines;

            if (!is_dir(__DIR__ . '/admin')) {
                @mkdir(__DIR__ . '/admin', 0755, true);
            }
            $writeOk = @file_put_contents($dbConfigFile, $configContent) !== false;
            if (!$writeOk) {
                $errors[] = 'Kan admin/db_config.php niet schrijven. Controleer de bestandsrechten (chmod 644) en maak het handmatig aan.';
                $_SESSION['pd_restore']['manual_config'] = $configContent;
            }

            // ── Opruimen ──
            @unlink($zipPath);
            pdRrmdir($tmpExtract);

            if (empty($errors)) {
                // Vergrendelen — zelfde marker als install.php, zodat install.php en restore.php
                // beide correct weten dat deze installatie nu in gebruik is.
                if (!is_dir(__DIR__ . '/install')) {
                    @mkdir(__DIR__ . '/install', 0755, true);
                }
                @file_put_contents($installedMarker, 'Restored on: ' . date('Y-m-d H:i:s'));
                unset($_SESSION['pd_restore']);
                $step = 4;
            } else {
                $step = 3; // blijf op bevestigingsscherm met foutmelding zichtbaar
            }
        }
    }
}
if ($step === 3 && empty($_SESSION['pd_restore']['db_host'])) {
    $step = 1;
    $errors[] = 'Begin opnieuw — er zijn geen databasegegevens bekend voor deze sessie.';
}

function pdRrmdir(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($dir);
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PeopleDisplay — Restore vanaf back-up</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.container {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    width: 100%;
    max-width: 560px;
    padding: 40px;
}
.logo { text-align: center; margin-bottom: 28px; }
.logo h1 { color: #1a1a2e; font-size: 24px; margin-bottom: 4px; }
.logo p { color: #718096; font-size: 14px; }
.steps { display: flex; justify-content: center; gap: 8px; margin-bottom: 28px; }
.steps span {
    width: 28px; height: 28px; border-radius: 50%; background: #e2e8f0; color: #718096;
    display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700;
}
.steps span.active { background: #3b82f6; color: #fff; }
.steps span.done { background: #48bb78; color: #fff; }
.alert { padding: 14px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; line-height: 1.5; }
.alert-error   { background: #fff5f5; border-left: 4px solid #f56565; color: #742a2a; }
.alert-success { background: #f0fff4; border-left: 4px solid #48bb78; color: #22543d; }
.alert-info    { background: #ebf8ff; border-left: 4px solid #4299e1; color: #2a4365; }
.alert ul { margin: 6px 0 0 18px; }
.form-group { margin-bottom: 18px; }
label { display: block; margin-bottom: 6px; color: #4a5568; font-weight: 600; font-size: 14px; }
input[type="text"], input[type="password"], input[type="file"] {
    width: 100%; padding: 11px 13px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px;
}
input:focus { outline: none; border-color: #3b82f6; }
.form-hint { font-size: 12px; color: #a0aec0; margin-top: 5px; }
.btn {
    display: block; width: 100%; padding: 13px; background: #3b82f6; color: #fff; border: none;
    border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer; text-align: center;
    text-decoration: none;
}
.btn:hover { background: #2563eb; }
.btn-green { background: #48bb78; }
.btn-green:hover { background: #38a169; }
.divider { text-align: center; color: #a0aec0; font-size: 12px; margin: 18px 0; text-transform: uppercase; letter-spacing: .5px; }
code { background: #f7fafc; padding: 1px 6px; border-radius: 4px; font-size: 13px; }
.footer { text-align: center; margin-top: 24px; padding-top: 18px; border-top: 1px solid #e2e8f0; font-size: 13px; color: #a0aec0; }
</style>
</head>
<body>
<div class="container">

    <div class="logo">
        <h1>♻️ PeopleDisplay Restore</h1>
        <p>Volledige back-up terugzetten op deze installatie</p>
    </div>

    <div class="steps">
        <span class="<?= $step === 1 ? 'active' : ($step > 1 ? 'done' : '') ?>">1</span>
        <span class="<?= $step === 2 ? 'active' : ($step > 2 ? 'done' : '') ?>">2</span>
        <span class="<?= $step === 3 ? 'active' : ($step > 3 ? 'done' : '') ?>">3</span>
        <span class="<?= $step === 4 ? 'active' : '' ?>">4</span>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
    <div class="alert alert-success"><ul><?php foreach ($success as $s): ?><li><?= htmlspecialchars($s) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
        <p style="font-size:14px; color:#4a5568; margin-bottom:18px; line-height:1.6;">
            Zet hier een volledige back-up terug (gemaakt via <strong>Beheer → Backup &amp; Herstel</strong>
            op de oude installatie). Gebruik dit alleen op een verse, nog niet geconfigureerde
            PeopleDisplay-installatie — bijvoorbeeld net via FTP geüpload op een nieuw domein of bij
            een andere hosting-partij.
        </p>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="step" value="1">
            <div class="form-group">
                <label for="backup_zip">Back-up ZIP uploaden</label>
                <input type="file" id="backup_zip" name="backup_zip" accept=".zip">
                <div class="form-hint">Werkt voor kleinere back-ups. Grotere back-ups lopen mogelijk tegen de upload-limiet van de server aan.</div>
            </div>
            <div class="divider">of</div>
            <div class="form-group">
                <label for="server_path">Bestandsnaam in <code>restore_upload/</code></label>
                <input type="text" id="server_path" name="server_path" placeholder="peopledisplay_backup_full_2026-09-16_120000.zip" autocomplete="off">
                <div class="form-hint">Plaats het ZIP-bestand eerst via FTP in de map <code>restore_upload/</code> naast dit bestand, en vul hier de bestandsnaam in.</div>
            </div>
            <button type="submit" class="btn">Volgende &rarr;</button>
        </form>

    <?php elseif ($step === 2): ?>
        <p style="font-size:14px; color:#4a5568; margin-bottom:18px;">
            Vul de gegevens van de (nieuwe) database op deze server in. Bestaat de database nog niet,
            dan wordt hij automatisch aangemaakt.
        </p>
        <form method="POST">
            <input type="hidden" name="step" value="2">
            <div class="form-group"><label for="db_host">Database host</label><input type="text" id="db_host" name="db_host" required></div>
            <div class="form-group"><label for="db_name">Database naam</label><input type="text" id="db_name" name="db_name" required></div>
            <div class="form-group"><label for="db_user">Database gebruiker</label><input type="text" id="db_user" name="db_user" required></div>
            <div class="form-group"><label for="db_pass">Database wachtwoord</label><input type="password" id="db_pass" name="db_pass"></div>
            <button type="submit" class="btn">Testen &amp; volgende &rarr;</button>
        </form>

    <?php elseif ($step === 3): ?>
        <div class="alert alert-info">
            <strong>Let op:</strong> dit overschrijft bestanden in deze installatie met de inhoud
            van de back-up (behalve <code>admin/db_config.php</code> en dit restore-bestand zelf),
            en importeert de database in de zojuist opgegeven database. Dit kan enkele minuten duren
            — sluit dit venster niet.
        </div>
        <form method="POST">
            <input type="hidden" name="step" value="3">
            <button type="submit" name="confirm_restore" value="1" class="btn btn-green">Restore uitvoeren</button>
        </form>

    <?php elseif ($step === 4): ?>
        <div class="alert alert-success">
            <strong>Restore voltooid.</strong> De installatie is teruggezet en vergrendeld
            (net als na een normale installatie).
        </div>
        <p style="font-size:14px; color:#4a5568; margin-bottom:18px; line-height:1.6;">
            Laatste stap: de licentie staat nog geregistreerd op het oude domein. Ga naar
            <strong>Licentie activeren</strong> en voer opnieuw uw licentiecode in — of, als u die
            destijds heeft bewaard, uw <strong>continuity-sleutel</strong>, zodat deze licentie voortaan
            op elk domein blijft werken.
        </p>
        <a href="activate_license.php" class="btn btn-green">Naar licentie-activatie &rarr;</a>
        <p style="font-size:12px; color:#a0aec0; margin-top:18px; text-align:center;">
            Verwijder <code>restore.php</code> en de map <code>restore_upload/</code> als u ze niet meer nodig heeft.
        </p>
    <?php endif; ?>

    <div class="footer">
        <p>Vragen? <a href="mailto:support@peopledisplay.nl" style="color:#3b82f6;">support@peopledisplay.nl</a></p>
    </div>

</div>
</body>
</html>
