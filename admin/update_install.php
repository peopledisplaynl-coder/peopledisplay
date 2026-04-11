<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * BESTANDSNAAM:  update_install.php
 * UPLOAD NAAR:   /admin/update_install.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/license_check.php';
require_once __DIR__ . '/../includes/version.php';
require_once __DIR__ . '/../includes/update_check.php';

// Auth: alleen admin/superadmin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php'); exit;
}
$userRole = $_SESSION['role'] ?? 'user';
if (!in_array($userRole, ['admin', 'superadmin'], true)) {
    header('Location: ../frontpage.php'); exit;
}

// Bestanden die NOOIT overschreven worden
const PROTECTED_FILES = [
    'includes/db_config.php',
    'includes/config.php',
    'data/',
    'uploads/',
];

$step    = $_GET['step'] ?? 'confirm';
$error   = '';
$message = '';
$updateInfo = checkForUpdates();

// CSRF token
if (empty($_SESSION['update_csrf'])) {
    $_SESSION['update_csrf'] = bin2hex(random_bytes(16));
}

// ── Stap: Installeren ────────────────────────────────────────
if ($step === 'install' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['update_csrf']) {
        $error = 'Ongeldige sessie, probeer opnieuw.';
        $step  = 'confirm';
    } elseif (empty($updateInfo['available'])) {
        $error = 'Geen update beschikbaar.';
        $step  = 'confirm';
    } else {
        $result = runUpdate($updateInfo);
        if ($result['success']) {
            $step    = 'done';
            $message = $result['message'];
            // Cache wissen zodat banner verdwijnt
            @unlink(sys_get_temp_dir() . '/pd_update_cache.json');
            unset($_SESSION['update_csrf']);
        } else {
            $error = $result['error'];
            $step  = 'confirm';
        }
    }
}

/**
 * Voer de update uit
 */
function runUpdate(array $updateInfo): array
{
    $downloadUrl = $updateInfo['download_url'] ?? '';
    $newVersion  = $updateInfo['version']      ?? '';

    if (!$downloadUrl || !$newVersion) {
        return ['success' => false, 'error' => 'Ongeldige update informatie.'];
    }

    // 1. Controleer vereisten
    if (!class_exists('ZipArchive')) {
        return ['success' => false, 'error' => 'PHP ZipArchive extensie niet beschikbaar op deze server. Vraag uw hostingprovider dit in te schakelen.'];
    }
    if (!ini_get('allow_url_fopen')) {
        return ['success' => false, 'error' => 'allow_url_fopen is uitgeschakeld op deze server. Update handmatig via FTP.'];
    }

    // 2. Download ZIP
    $tmpZip = sys_get_temp_dir() . '/pd_update_' . preg_replace('/[^a-z0-9.]/', '', $newVersion) . '.zip';
    $ctx    = stream_context_create(['http' => ['timeout' => 60, 'ignore_errors' => true]]);
    $zip    = @file_get_contents($downloadUrl, false, $ctx);

    if (!$zip) {
        return ['success' => false, 'error' => 'Download mislukt. Controleer de verbinding met de update server.'];
    }
    file_put_contents($tmpZip, $zip);

    // 3. Checksum validatie
    if (!empty($updateInfo['checksum'])) {
        if (md5_file($tmpZip) !== $updateInfo['checksum']) {
            @unlink($tmpZip);
            return ['success' => false, 'error' => 'Checksum fout — download mogelijk beschadigd. Probeer opnieuw.'];
        }
    }

    // 4. Uitpakken
    $tmpDir = sys_get_temp_dir() . '/pd_update_' . $newVersion . '/';
    @mkdir($tmpDir, 0755, true);

    $zipArchive = new ZipArchive();
    if ($zipArchive->open($tmpZip) !== true) {
        return ['success' => false, 'error' => 'ZIP bestand kan niet worden geopend.'];
    }
    $zipArchive->extractTo($tmpDir);
    $zipArchive->close();

    // 5. Backup van huidige installatie
    $installRoot = realpath(__DIR__ . '/../') . '/';
    $backupDir   = $installRoot . 'data/backups/';
    @mkdir($backupDir, 0755, true);
    $backupFile  = $backupDir . 'backup_v' . PD_CURRENT_VERSION . '_' . date('Ymd_His') . '.zip';
    makeBackup($installRoot, $backupFile);

    // 6. Kopieer nieuwe bestanden
    copyFiles($tmpDir, $installRoot);

    // 7. Versienummer bijwerken (beide constanten)
    file_put_contents(
        __DIR__ . '/../includes/version.php',
        "<?php\n" .
        "define('PD_CURRENT_VERSION',   '" . addslashes($newVersion) . "');\n" .
        "define('PEOPLEDISPLAY_VERSION', '" . addslashes($newVersion) . "');\n"
    );

    // 8. Opruimen
    @unlink($tmpZip);
    deleteDir($tmpDir);

    return [
        'success' => true,
        'message' => "Update naar v{$newVersion} succesvol geïnstalleerd! Backup opgeslagen in /data/backups/.",
    ];
}

function copyFiles(string $src, string $dst): void
{
    $src = rtrim($src, '/') . '/';
    $dst = rtrim($dst, '/') . '/';

    // Sommige ZIPs hebben één submap als root — die overslaan
    $entries = array_diff(scandir($src), ['.', '..']);
    $subdirs = array_filter($entries, fn($e) => is_dir($src . $e) && !str_starts_with($e, '.'));
    if (count($subdirs) === 1 && !file_exists($src . 'index.php')) {
        $src = $src . reset($subdirs) . '/';
    }

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iter as $item) {
        $rel = str_replace($src, '', $item->getPathname());

        // Beschermde bestanden overslaan
        foreach (PROTECTED_FILES as $p) {
            if (str_starts_with($rel, $p)) continue 2;
        }

        $target = $dst . $rel;
        if ($item->isDir()) {
            @mkdir($target, 0755, true);
        } else {
            @copy($item->getPathname(), $target);
        }
    }
}

function makeBackup(string $src, string $dest): void
{
    if (!class_exists('ZipArchive')) return;
    $zip = new ZipArchive();
    if ($zip->open($dest, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return;
    $src  = rtrim(realpath($src), '/') . '/';
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        if (!$file->isFile()) continue;
        $rel = str_replace($src, '', $file->getPathname());
        if (!str_starts_with($rel, 'data/backups/')) {
            $zip->addFile($file->getPathname(), $rel);
        }
    }
    $zip->close();
}

function deleteDir(string $dir): void
{
    if (!is_dir($dir)) return;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

$currentVersion = PD_CURRENT_VERSION;
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Installeren — PeopleDisplay</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 760px; margin: 0 auto; }
        .card {
            background: rgba(255,255,255,0.97);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            padding: 36px;
            margin-bottom: 20px;
        }
        .card-title {
            font-size: 22px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 24px;
        }
        .version-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }
        .version-box {
            background: #f7fafc;
            border-radius: 10px;
            padding: 16px;
            text-align: center;
        }
        .version-box .label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #a0aec0;
            margin-bottom: 6px;
        }
        .version-box .val {
            font-size: 20px;
            font-weight: 700;
        }
        .val.old { color: #e53e3e; }
        .val.new { color: #38a169; }
        .val.date { color: #2d3748; font-size: 16px; }
        .changelog {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        .changelog h3 {
            font-size: 14px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 10px;
        }
        .changelog ul {
            padding-left: 18px;
            color: #4a5568;
        }
        .changelog li {
            font-size: 14px;
            margin-bottom: 5px;
        }
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-warning { background: #fffbeb; border-left: 4px solid #f6ad55; color: #7b341e; }
        .alert-error   { background: #fff5f5; border-left: 4px solid #f56565; color: #742a2a; }
        .alert-success { background: #f0fff4; border-left: 4px solid #48bb78; color: #276749; }
        .btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102,126,234,0.4);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102,126,234,0.5); }
        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            margin-left: 12px;
        }
        .btn-secondary:hover { background: #f0f4ff; }
        .success-icon { font-size: 64px; text-align: center; display: block; margin-bottom: 20px; }
        .protected-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }
        .protected-list span {
            background: #e2e8f0;
            color: #4a5568;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-family: monospace;
        }
        .spinner {
            display: none;
            text-align: center;
            padding: 20px;
            color: #667eea;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="container">

    <?php if ($step === 'done'): ?>
    <!-- ── SUCCES ── -->
    <div class="card" style="text-align:center; padding: 48px 36px;">
        <span class="success-icon">✅</span>
        <div class="card-title" style="text-align:center;">Update geslaagd!</div>
        <p style="color:#4a5568; margin-bottom:28px;"><?= htmlspecialchars($message) ?></p>
        <a href="dashboard.php" class="btn btn-primary">🎛️ Terug naar Dashboard</a>
    </div>

    <?php elseif (!empty($updateInfo['available'])): ?>
    <!-- ── BEVESTIGEN ── -->
    <div class="card">
        <div class="card-title">🔄 Update Installeren</div>

        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="version-row">
            <div class="version-box">
                <div class="label">Huidige versie</div>
                <div class="val old">v<?= htmlspecialchars($currentVersion) ?></div>
            </div>
            <div class="version-box">
                <div class="label">Nieuwe versie</div>
                <div class="val new">v<?= htmlspecialchars($updateInfo['version']) ?></div>
            </div>
            <div class="version-box">
                <div class="label">Releasedatum</div>
                <div class="val date"><?= htmlspecialchars($updateInfo['release_date'] ?? '—') ?></div>
            </div>
        </div>

        <!-- Changelog -->
        <?php
        $remoteData = json_decode(@file_get_contents(sys_get_temp_dir() . '/pd_update_cache.json'), true);
        $changelog  = $remoteData['changelog'] ?? [];
        $newEntries = array_filter($changelog, fn($r) => version_compare($r['version'], $currentVersion, '>'));
        ?>
        <?php if (!empty($newEntries)): ?>
        <div class="changelog">
            <h3>📋 Wat is er nieuw?</h3>
            <ul>
                <?php foreach ($newEntries as $entry):
                    foreach ($entry['changes'] as $change): ?>
                    <li><?= htmlspecialchars($change) ?></li>
                <?php endforeach; endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Waarschuwing -->
        <div class="alert alert-warning">
            ⚠️ <strong>Let op:</strong> Er wordt automatisch een backup gemaakt voor de update begint.
            De volgende bestanden worden <strong>nooit</strong> overschreven:
            <div class="protected-list">
                <?php foreach (PROTECTED_FILES as $f): ?>
                    <span><?= htmlspecialchars($f) ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Installeer formulier -->
        <form method="POST" action="update_install.php?step=install"
              onsubmit="document.getElementById('spinner').style.display='block'; document.getElementById('install-btn').disabled=true; return true;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['update_csrf']) ?>">
            <button type="submit" class="btn btn-primary" id="install-btn">
                🚀 Installeer v<?= htmlspecialchars($updateInfo['version']) ?>
            </button>
            <a href="dashboard.php" class="btn btn-secondary">Annuleren</a>
        </form>

        <div class="spinner" id="spinner">
            ⏳ Update wordt geïnstalleerd, even geduld...
        </div>
    </div>

    <?php else: ?>
    <!-- ── ACTUEEL ── -->
    <div class="card" style="text-align:center; padding: 48px 36px;">
        <span class="success-icon">✅</span>
        <div class="card-title" style="text-align:center;">U heeft de nieuwste versie</div>
        <p style="color:#4a5568; margin-bottom:28px;">PeopleDisplay v<?= htmlspecialchars($currentVersion) ?> is actueel.</p>
        <a href="dashboard.php" class="btn btn-primary">🎛️ Terug naar Dashboard</a>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
