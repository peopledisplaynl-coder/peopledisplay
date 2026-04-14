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
 * PeopleDisplay v2.0 — Web Installer
 * ============================================================
 * Location: /install.php (root of the application)
 *
 * Steps:
 *   1. Systeemcheck
 *   2. Gebruiksvoorwaarden (EULA)
 *   3. Licentie
 *   4. Database
 *   5. Schema
 *   6. Admin Account
 *   7. Afronden
 *   8. Klaar
 *
 * SECURITY:
 *   Locks itself after completion via install/.installed marker.
 *   Safe to leave on the server — it refuses to re-run.
 *
 * SUPPORTED HOSTS:
 *   XAMPP | Strato | cPanel | Plesk | VPS | localhost
 * ============================================================
 */

declare(strict_types=1);

// ============================================================
// INSTALLER LOCK CHECK
// ============================================================
$installedMarker = __DIR__ . '/install/.installed';
$isLocked = file_exists($installedMarker);

if ($isLocked) {
    ?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PeopleDisplay — Installer</title>
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
    <h1>Installer vergrendeld</h1>
    <p>PeopleDisplay is al geïnstalleerd op:<br>
    <strong><?php echo htmlspecialchars(file_get_contents($installedMarker)); ?></strong></p>
    <p>De installer is automatisch vergrendeld na de eerste installatie.</p>
    <a href="index.php" class="btn">Naar de applicatie</a>
    <a href="admin/dashboard.php" class="btn btn-secondary">Admin panel</a>
</div>
</body>
</html>
    <?php
    exit;
}

// ============================================================
// SESSION & LICENSE FUNCTIONS
// ============================================================
session_start();

// Load license key validation (validateLicenseKey, generateLicenseChecksum, getCurrentDomain)
// These functions need no DB — safe to load before the database is configured.
require_once __DIR__ . '/includes/license.php';

// ============================================================
// STEP HANDLING
// ============================================================
$step   = (int)($_POST['step'] ?? $_GET['step'] ?? 1);
$errors  = [];
$success = [];

// ============================================================
// STEP 2 — EULA Acceptance
// ============================================================
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['eula_accepted']) && $_POST['eula_accepted'] === '1') {
        $_SESSION['pd_install']['eula_accepted'] = true;
        $_SESSION['pd_install']['eula_version']  = '1.0';
        $step = 3;
    } else {
        $errors[] = 'U moet akkoord gaan met de gebruiksvoorwaarden om door te gaan.';
        $step = 2;
    }
}

// ============================================================
// STEP 3 — Validate license key (optional — Starter is free)
// ============================================================
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $licenseKey = strtoupper(trim($_POST['license_key'] ?? ''));

    if (empty($licenseKey)) {
        // No key: proceed as Starter (free tier — max 10 employees, 1 location, 3 users)
        $_SESSION['pd_install']['license_key']  = '';
        $_SESSION['pd_install']['license_tier'] = 'starter';
        $step = 4;
    } else {
        $validation = validateLicenseKey($licenseKey);
        if ($validation['valid']) {
            $_SESSION['pd_install']['license_key']  = $licenseKey;
            $_SESSION['pd_install']['license_tier'] = $validation['tier'];
            $step = 4;
        } else {
            $errors[] = $validation['error'];
            $step = 3;
        }
    }
}

// ============================================================
// STEP 4 — Test and save database config
// ============================================================
if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';

    if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
        $errors[] = 'Vul alle databasevelden in.';
        $step = 4;
    } else {
        try {
            $testDsn = "mysql:host={$dbHost};charset=utf8mb4";
            $testPdo = new PDO($testDsn, $dbUser, $dbPass, [PDO::ATTR_TIMEOUT => 5]);
            $check   = $testPdo->query("SHOW DATABASES LIKE " . $testPdo->quote($dbName));
            if ($check->rowCount() === 0) {
                $testPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
            $_SESSION['pd_install']['db_host'] = $dbHost;
            $_SESSION['pd_install']['db_name'] = $dbName;
            $_SESSION['pd_install']['db_user'] = $dbUser;
            $_SESSION['pd_install']['db_pass'] = $dbPass;
            $step = 5;
        } catch (PDOException $e) {
            $errors[] = 'Kan geen verbinding maken met de database: ' . $e->getMessage();
            $errors[] = 'Controleer uw gegevens en probeer het opnieuw.';
            $step = 4;
        }
    }
}

// ============================================================
// STEP 5 — Import SQL schema
// ============================================================
if ($step === 5 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = $_SESSION['pd_install']['db_host'] ?? '';
    $dbName = $_SESSION['pd_install']['db_name'] ?? '';
    $dbUser = $_SESSION['pd_install']['db_user'] ?? '';
    $dbPass = $_SESSION['pd_install']['db_pass'] ?? '';

    try {
        $pdo = new PDO(
            "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
            $dbUser, $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $sqlFile = __DIR__ . '/install.sql';
        if (!file_exists($sqlFile)) {
            $errors[] = 'install.sql niet gevonden. Upload dit bestand naar de root van de applicatie.';
        } else {
            $sql        = file_get_contents($sqlFile);
            $sql        = preg_replace('/--.*$/m', '', $sql);
            $sql        = preg_replace('#/\*.*?\*/#s', '', $sql);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            $tableCount = 0;

            foreach ($statements as $stmt) {
                if (!empty($stmt)) {
                    $pdo->exec($stmt);
                    if (stripos($stmt, 'CREATE TABLE') !== false) {
                        $tableCount++;
                    }
                }
            }
            $success[] = "Database schema geïmporteerd — {$tableCount} tabellen aangemaakt of geverifieerd.";
            $step = 6;
        }
    } catch (PDOException $e) {
        $errors[] = 'Fout bij importeren schema: ' . $e->getMessage();
        $step = 5;
    }
}

// ============================================================
// STEP 6 — Create admin account
// ============================================================
if ($step === 6 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminUser    = trim($_POST['admin_username'] ?? '');
    $adminPass    = $_POST['admin_password']  ?? '';
    $adminPass2   = $_POST['admin_password2'] ?? '';
    $adminEmail   = trim($_POST['admin_email']   ?? '');
    $adminDisplay = trim($_POST['admin_display'] ?? $adminUser);

    if (empty($adminUser) || empty($adminPass)) {
        $errors[] = 'Gebruikersnaam en wachtwoord zijn verplicht.';
        $step = 6;
    } elseif (strlen($adminPass) < 8) {
        $errors[] = 'Wachtwoord moet minimaal 8 tekens bevatten.';
        $step = 6;
    } elseif ($adminPass !== $adminPass2) {
        $errors[] = 'Wachtwoorden komen niet overeen.';
        $step = 6;
    } else {
        try {
            $pdo = new PDO(
                "mysql:host={$_SESSION['pd_install']['db_host']};dbname={$_SESSION['pd_install']['db_name']};charset=utf8mb4",
                $_SESSION['pd_install']['db_user'],
                $_SESSION['pd_install']['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $hash     = password_hash($adminPass, PASSWORD_DEFAULT);
            $existing = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin'")->fetchColumn();

            if ($existing > 0) {
                $success[] = 'Er bestaat al een superadmin account. Stap overgeslagen.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password_hash, role, display_name, email, active)
                    VALUES (?, ?, 'superadmin', ?, ?, 1)
                ");
                $stmt->execute([$adminUser, $hash, $adminDisplay ?: $adminUser, $adminEmail ?: null]);
                $success[] = "Admin account '{$adminUser}' aangemaakt.";
            }
            $_SESSION['pd_install']['admin_username'] = $adminUser;
            $step = 7;
        } catch (PDOException $e) {
            $errors[] = 'Fout bij aanmaken admin account: ' . $e->getMessage();
            $step = 6;
        }
    }
}

// ============================================================
// STEP 7 — Write db_config.php, activate license, lock installer
// ============================================================
if ($step === 7 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = $_SESSION['pd_install']['db_host'] ?? '';
    $dbName = $_SESSION['pd_install']['db_name'] ?? '';
    $dbUser = $_SESSION['pd_install']['db_user'] ?? '';
    $dbPass = $_SESSION['pd_install']['db_pass'] ?? '';

    $configContent = "<?php\n"
        . "\$DB_HOST='" . addslashes($dbHost) . "';\n"
        . "\$DB_NAME='" . addslashes($dbName) . "';\n"
        . "\$DB_USER='" . addslashes($dbUser) . "';\n"
        . "\$DB_PASS='" . addslashes($dbPass) . "';\n";

    $configFile = __DIR__ . '/admin/db_config.php';
    $writeOk    = file_put_contents($configFile, $configContent) !== false;

    if (!$writeOk) {
        $errors[] = 'Kan admin/db_config.php niet schrijven. Controleer de bestandsrechten (chmod 644).';
        $errors[] = 'Maak het bestand handmatig aan met de onderstaande inhoud:';
        $_SESSION['pd_install']['manual_config'] = $configContent;
        $step = 7;
    } else {
        try {
            $pdo = new PDO(
                "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
                $dbUser, $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Activate the validated license
            $licenseKey  = $_SESSION['pd_install']['license_key']  ?? '';
            $licenseTier = $_SESSION['pd_install']['license_tier'] ?? '';

            if (!empty($licenseKey) && !empty($licenseTier)) {
                $domain = getCurrentDomain();
                $stmt = $pdo->prepare("
                    UPDATE config SET
                        license_key          = ?,
                        license_tier         = ?,
                        license_domain       = ?,
                        license_activated_at = NOW(),
                        license_expires_at   = NULL,
                        license_status       = 'active'
                    WHERE id = 1
                ");
                $stmt->execute([$licenseKey, $licenseTier, $domain]);

                $logStmt = $pdo->prepare("
                    INSERT INTO license_log (license_key, action, domain, ip_address, details)
                    VALUES (?, 'activated', ?, ?, 'Activated during installation')
                ");
                $logStmt->execute([$licenseKey, $domain, $_SERVER['REMOTE_ADDR'] ?? '']);
            }

            // Save EULA acceptance
            if (!empty($_SESSION['pd_install']['eula_accepted'])) {
                $eulaVersion = $_SESSION['pd_install']['eula_version'] ?? '1.0';
                $eulaStmt = $pdo->prepare("
                    UPDATE config SET
                        eula_accepted    = 1,
                        eula_accepted_at = NOW(),
                        eula_version     = ?
                    WHERE id = 1
                ");
                $eulaStmt->execute([$eulaVersion]);
            }
        } catch (PDOException $e) {
            // Non-fatal — installation can complete, data can be re-set via admin
            error_log('[PD Install] Post-install DB update failed: ' . $e->getMessage());
        }

        // Lock installer
        if (!is_dir(__DIR__ . '/install')) {
            mkdir(__DIR__ . '/install', 0755, true);
        }
        file_put_contents($installedMarker, 'Installed on: ' . date('Y-m-d H:i:s'));

        // Clear installer session
        unset($_SESSION['pd_install']);

        $step = 8;
    }
}

// ============================================================
// HTML HELPERS
// ============================================================
function stepClass(int $n, int $current): string {
    if ($n < $current) return 'done';
    if ($n === $current) return 'active';
    return '';
}

$stepTitles = [
    1 => 'Welkom',
    2 => 'Voorwaarden',
    3 => 'Licentie',
    4 => 'Database',
    5 => 'Schema',
    6 => 'Admin',
    7 => 'Afronden',
    8 => 'Klaar',
];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PeopleDisplay — Installatie</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, Helvetica, sans-serif; background: #eef2f7; min-height: 100vh; padding: 20px; }
.installer { max-width: 680px; margin: 0 auto; }
.header { background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 12px; padding: 28px 32px; margin-bottom: 24px; color: white; }
.header h1 { font-size: 26px; font-weight: 700; }
.header p { opacity: 0.85; margin-top: 4px; font-size: 14px; }
.steps { display: flex; gap: 4px; margin-bottom: 24px; flex-wrap: wrap; }
.step-item { flex: 1; min-width: 60px; padding: 8px 4px; border-radius: 8px; text-align: center; font-size: 11px; font-weight: 600; background: white; color: #a0aec0; border: 2px solid transparent; }
.step-item.active { background: #667eea; color: white; border-color: #667eea; }
.step-item.done { background: #c6f6d5; color: #276749; border-color: #9ae6b4; }
.step-num { display: block; font-size: 16px; font-weight: 800; margin-bottom: 2px; }
.card { background: white; border-radius: 12px; padding: 32px; box-shadow: 0 2px 16px rgba(0,0,0,0.07); }
.card h2 { font-size: 20px; color: #2d3748; margin-bottom: 6px; }
.card .subtitle { color: #718096; font-size: 14px; margin-bottom: 24px; }
.alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; line-height: 1.5; }
.alert-error   { background: #fff5f5; border: 1px solid #fc8181; color: #742a2a; }
.alert-success { background: #f0fff4; border: 1px solid #68d391; color: #22543d; }
.alert-info    { background: #ebf8ff; border: 1px solid #63b3ed; color: #2a4365; }
.form-group { margin-bottom: 18px; }
.form-group label { display: block; font-weight: 600; color: #4a5568; margin-bottom: 6px; font-size: 14px; }
.form-group input[type="text"],
.form-group input[type="password"],
.form-group input[type="email"] {
    width: 100%; padding: 10px 12px; border: 2px solid #e2e8f0;
    border-radius: 8px; font-size: 15px; transition: border 0.15s;
}
.form-group input:focus { outline: none; border-color: #667eea; }
.form-group input.valid   { border-color: #48bb78; }
.form-group input.invalid { border-color: #f56565; }
.form-hint { font-size: 12px; color: #718096; margin-top: 4px; }
.btn { display: inline-block; padding: 12px 28px; border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer; border: none; text-decoration: none; transition: opacity 0.15s; }
.btn-primary   { background: #667eea; color: white; }
.btn-primary:hover { opacity: 0.9; }
.btn-success   { background: #48bb78; color: white; }
.btn-secondary { background: #e2e8f0; color: #4a5568; }
.btn:disabled  { background: #cbd5e0; cursor: not-allowed; }
.check-list { list-style: none; margin: 16px 0; }
.check-list li { padding: 8px 0; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 10px; font-size: 14px; }
.check-list li:last-child { border-bottom: none; }
.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
.badge-ok   { background: #c6f6d5; color: #22543d; }
.badge-warn { background: #fefcbf; color: #744210; }
.badge-err  { background: #fed7d7; color: #742a2a; }
.final-box { background: #f0fff4; border: 2px solid #68d391; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
.final-box h3 { color: #22543d; margin-bottom: 10px; }
.final-box a { color: #2f855a; font-weight: 600; }
pre { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; font-size: 13px; overflow-x: auto; }
.license-input { font-family: 'Courier New', monospace; text-transform: uppercase; letter-spacing: 2px; }
</style>
</head>
<body>
<div class="installer">

    <div class="header">
        <h1>PeopleDisplay Installatie</h1>
        <p>Versie 2.1 &mdash; Aanwezigheidsregistratie systeem</p>
    </div>

    <!-- Step indicators -->
    <div class="steps">
        <?php foreach ($stepTitles as $n => $title): ?>
        <div class="step-item <?php echo stepClass($n, $step); ?>">
            <span class="step-num"><?php echo ($n === $step && $step < 8) ? $n : ($n < $step ? '✓' : $n); ?></span>
            <?php echo htmlspecialchars($title); ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card">

    <?php // =========================================================
    // STEP 1: WELCOME + SYSTEM CHECK
    // =========================================================
    if ($step === 1): ?>

        <h2>Welkom bij PeopleDisplay</h2>
        <p class="subtitle">Laten we controleren of uw server aan de vereisten voldoet.</p>

        <?php
        $phpOk     = PHP_VERSION_ID >= 70400;
        $phpMatch  = PHP_VERSION_ID >= 80000;
        $extPdo    = extension_loaded('pdo') && extension_loaded('pdo_mysql');
        $extJson   = extension_loaded('json');
        $extGd     = extension_loaded('gd');
        $extCurl   = extension_loaded('curl');
        $dirTmp    = is_writable(__DIR__ . '/tmp/badge_photos');
        $dirUp     = is_writable(__DIR__ . '/uploads/profiles');
        $dirLogs   = is_writable(__DIR__ . '/logs');
        $dirAdmin  = is_writable(__DIR__ . '/admin');
        $allOk     = $phpOk && $extPdo && $extJson;
        ?>
        <ul class="check-list">
            <li>
                <span class="badge <?php echo $phpOk ? 'badge-ok' : 'badge-err'; ?>"><?php echo $phpOk ? 'OK' : 'FOUT'; ?></span>
                PHP versie: <?php echo PHP_VERSION; ?>
                <?php if (!$phpOk): ?><br><small>Vereist: PHP 7.4 of hoger</small><?php endif; ?>
                <?php if ($phpOk && !$phpMatch): ?><small style="color:#744210"> — PHP 8.0+ aanbevolen</small><?php endif; ?>
            </li>
            <li>
                <span class="badge <?php echo $extPdo ? 'badge-ok' : 'badge-err'; ?>"><?php echo $extPdo ? 'OK' : 'FOUT'; ?></span>
                PDO + PDO_MySQL extensie
                <?php if (!$extPdo): ?><br><small>Contacteer uw hostingprovider om PDO en PDO_MySQL te activeren.</small><?php endif; ?>
            </li>
            <li>
                <span class="badge <?php echo $extJson ? 'badge-ok' : 'badge-err'; ?>"><?php echo $extJson ? 'OK' : 'FOUT'; ?></span>
                JSON extensie
            </li>
            <li>
                <span class="badge <?php echo $extGd ? 'badge-ok' : 'badge-warn'; ?>"><?php echo $extGd ? 'OK' : 'OPTIONEEL'; ?></span>
                GD extensie (voor badge generatie)
            </li>
            <li>
                <span class="badge <?php echo $extCurl ? 'badge-ok' : 'badge-warn'; ?>"><?php echo $extCurl ? 'OK' : 'OPTIONEEL'; ?></span>
                cURL extensie
            </li>
            <li>
                <span class="badge <?php echo $dirTmp ? 'badge-ok' : 'badge-warn'; ?>"><?php echo $dirTmp ? 'OK' : 'LET OP'; ?></span>
                tmp/badge_photos — schrijfbaar
                <?php if (!$dirTmp): ?><br><small>Voer uit: <code>chmod 755 tmp/badge_photos</code></small><?php endif; ?>
            </li>
            <li>
                <span class="badge <?php echo $dirUp ? 'badge-ok' : 'badge-warn'; ?>"><?php echo $dirUp ? 'OK' : 'LET OP'; ?></span>
                uploads/profiles — schrijfbaar
                <?php if (!$dirUp): ?><br><small>Voer uit: <code>chmod 755 uploads/profiles</code></small><?php endif; ?>
            </li>
            <li>
                <span class="badge <?php echo $dirLogs ? 'badge-ok' : 'badge-warn'; ?>"><?php echo $dirLogs ? 'OK' : 'LET OP'; ?></span>
                logs/ — schrijfbaar
            </li>
            <li>
                <span class="badge <?php echo $dirAdmin ? 'badge-ok' : 'badge-warn'; ?>"><?php echo $dirAdmin ? 'OK' : 'LET OP'; ?></span>
                admin/ — schrijfbaar (nodig voor db_config.php)
                <?php if (!$dirAdmin): ?><br><small>Maak admin/db_config.php handmatig aan als dit niet werkt.</small><?php endif; ?>
            </li>
        </ul>

        <?php if (!$allOk): ?>
        <div class="alert alert-error">
            De installatie kan niet doorgaan. Los de rode punten hierboven op en vernieuw deze pagina.
        </div>
        <?php else: ?>
        <div class="alert alert-success">Alle vereisten zijn aanwezig. Klik op Volgende om door te gaan.</div>
        <form method="POST" action="install.php">
            <input type="hidden" name="step" value="2">
            <button type="submit" class="btn btn-primary">Volgende: Voorwaarden &rarr;</button>
        </form>
        <?php endif; ?>

    <?php // =========================================================
    // STEP 2: EULA ACCEPTANCE
    // =========================================================
    elseif ($step === 2): ?>

        <h2>Gebruiksvoorwaarden</h2>
        <p class="subtitle">Lees en accepteer de gebruiksvoorwaarden voordat u verder gaat.</p>

        <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($e); ?></div>
        <?php endforeach; ?>

        <?php
        $lang     = $_GET['lang'] ?? 'nl';
        $eulaFile = __DIR__ . '/eula_' . ($lang === 'en' ? 'en' : 'nl') . '.txt';
        $eulaText = file_exists($eulaFile) ? file_get_contents($eulaFile) : 'Gebruiksvoorwaarden bestand niet gevonden. Neem contact op via support@peopledisplay.nl';
        ?>

        <div style="margin-bottom:12px;display:flex;gap:8px;">
            <a href="?step=2&lang=nl" class="btn <?php echo $lang !== 'en' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding:6px 14px;font-size:13px;">Nederlands</a>
            <a href="?step=2&lang=en" class="btn <?php echo $lang === 'en' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding:6px 14px;font-size:13px;">English</a>
        </div>

        <textarea readonly rows="14" style="width:100%;padding:14px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;line-height:1.6;font-family:inherit;resize:vertical;background:#f7fafc;"><?php echo htmlspecialchars($eulaText); ?></textarea>

        <form method="POST" action="install.php" id="eulaForm" style="margin-top:20px;">
            <input type="hidden" name="step" value="2">
            <label style="display:flex;align-items:center;gap:12px;cursor:pointer;font-size:15px;font-weight:500;color:#2d3748;margin-bottom:20px;">
                <input type="checkbox" id="eula_checkbox" name="eula_accepted" value="1" style="width:20px;height:20px;cursor:pointer;">
                Ik heb de gebruiksvoorwaarden gelezen en ga hiermee akkoord
            </label>
            <button type="submit" class="btn btn-primary" id="eulaBtn" disabled>
                Akkoord &amp; Verder: Licentie &rarr;
            </button>
        </form>

        <script>
        document.getElementById('eula_checkbox').addEventListener('change', function () {
            document.getElementById('eulaBtn').disabled = !this.checked;
        });
        </script>

    <?php // =========================================================
    // STEP 3: LICENSE (OPTIONAL)
    // =========================================================
    elseif ($step === 3): ?>

        <h2>Licentie</h2>
        <p class="subtitle">Voer uw licentiecode in, of ga gratis door als Starter.</p>

        <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($e); ?></div>
        <?php endforeach; ?>

        <div class="alert alert-success">
            <strong>Starter versie is gratis</strong> &mdash; geen licentiecode nodig.<br>
            Limieten: max <strong>10 medewerkers</strong>, <strong>1 locatie</strong>, <strong>3 gebruikers</strong>.<br>
            <a href="https://ko-fi.com/tonlabee" target="_blank" style="color:#276749;">Vond je PeopleDisplay nuttig? Steun de ontwikkeling via Ko-fi ☕</a>
        </div>

        <form method="POST" action="install.php" id="licenseForm">
            <input type="hidden" name="step" value="3">
            <div class="form-group">
                <label for="license_key">Licentiecode <span style="font-weight:400;color:#a0aec0;">(optioneel — voor Professional t/m Unlimited)</span></label>
                <input
                    type="text"
                    id="license_key"
                    name="license_key"
                    class="license-input"
                    placeholder="PDIS-XXXX-XXXX-XXXX"
                    maxlength="19"
                    autocomplete="off"
                    spellcheck="false"
                    value="<?php echo htmlspecialchars($_POST['license_key'] ?? ''); ?>"
                >
                <div class="form-hint">Formaat: PDIS-T001-RAND-HASH &mdash; ontvangen via e-mail na aankoop</div>
            </div>

            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary" id="licenseSubmit" style="flex:1">
                    Valideer &amp; Verder &rarr;
                </button>
                <button type="submit" class="btn btn-secondary" id="starterBtn" style="flex:1"
                    onclick="document.getElementById('license_key').value='';">
                    Doorgaan als Starter (gratis) &rarr;
                </button>
            </div>
        </form>

        <p style="margin-top:20px; font-size:13px; color:#718096; text-align:center">
            Meer capaciteit nodig? <a href="https://peopledisplay.nl/prijzen" target="_blank" style="color:#667eea;">Bekijk de pakketten</a>
        </p>

        <script>
        (function () {
            const input      = document.getElementById('license_key');
            const licBtn     = document.getElementById('licenseSubmit');
            const starterBtn = document.getElementById('starterBtn');
            if (!input) return;

            function validate() {
                let raw = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                let fmt = '';
                for (let i = 0; i < raw.length && i < 16; i++) {
                    if (i === 4 || i === 8 || i === 12) fmt += '-';
                    fmt += raw[i];
                }
                input.value = fmt;
                const ok     = /^PDIS-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/.test(fmt);
                const empty  = fmt.length === 0;
                input.classList.toggle('valid',   ok);
                input.classList.toggle('invalid', fmt.length > 0 && !ok);
                // Activate button states
                licBtn.disabled     = !ok;
                starterBtn.disabled = !empty && !ok;
            }

            input.addEventListener('input', validate);
            validate(); // Run on load
        })();
        </script>

    <?php // =========================================================
    // STEP 4: DATABASE CONFIGURATION
    // =========================================================
    elseif ($step === 4): ?>

        <h2>Database Configuratie</h2>
        <p class="subtitle">Voer uw databasegegevens in.</p>

        <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($e); ?></div>
        <?php endforeach; ?>

        <div class="alert alert-info">
            <strong>Waar vindt u deze gegevens?</strong><br>
            &bull; <strong>XAMPP:</strong> host=localhost, user=root, wachtwoord=leeg<br>
            &bull; <strong>Strato:</strong> zie uw Strato klantenportaal &rarr; Databases<br>
            &bull; <strong>cPanel:</strong> via cPanel &rarr; MySQL Databases<br>
            &bull; <strong>Plesk:</strong> via Plesk &rarr; Databases
        </div>

        <form method="POST" action="install.php">
            <input type="hidden" name="step" value="4">
            <div class="form-group">
                <label>Database Server (Host)</label>
                <input type="text" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" placeholder="localhost" required>
                <div class="form-hint">Bijna altijd 'localhost'. Strato: bijv. db12345.hosting.strato.de</div>
            </div>
            <div class="form-group">
                <label>Database Naam</label>
                <input type="text" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? ''); ?>" placeholder="peopledisplay" required>
            </div>
            <div class="form-group">
                <label>Database Gebruikersnaam</label>
                <input type="text" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? ''); ?>" placeholder="root" required>
            </div>
            <div class="form-group">
                <label>Database Wachtwoord</label>
                <input type="password" name="db_pass" placeholder="(leeg bij XAMPP standaard)">
                <div class="form-hint">Bij XAMPP standaard leeg.</div>
            </div>
            <button type="submit" class="btn btn-primary">Verbinding testen &amp; Volgende &rarr;</button>
        </form>

    <?php // =========================================================
    // STEP 5: IMPORT SCHEMA
    // =========================================================
    elseif ($step === 5): ?>

        <h2>Database Schema Installeren</h2>
        <p class="subtitle">De databaseverbinding werkt. Nu worden de tabellen aangemaakt.</p>

        <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($e); ?></div>
        <?php endforeach; ?>
        <?php foreach ($success as $s): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($s); ?></div>
        <?php endforeach; ?>

        <?php if (empty($errors)): ?>
        <div class="alert alert-info">
            Dit maakt <strong>30 tabellen</strong> aan in uw database.
            Bestaande tabellen worden niet overschreven — veilig om opnieuw uit te voeren.
        </div>
        <?php if (empty($success)): ?>
        <form method="POST" action="install.php">
            <input type="hidden" name="step" value="5">
            <button type="submit" class="btn btn-primary">Database tabellen aanmaken &rarr;</button>
        </form>
        <?php else: ?>
        <form method="POST" action="install.php">
            <input type="hidden" name="step" value="6">
            <button type="submit" class="btn btn-primary">Volgende: Admin account &rarr;</button>
        </form>
        <?php endif; ?>
        <?php else: ?>
        <a href="install.php?step=4" class="btn btn-secondary">&larr; Terug naar databaseconfiguratie</a>
        <?php endif; ?>

    <?php // =========================================================
    // STEP 6: CREATE ADMIN ACCOUNT
    // =========================================================
    elseif ($step === 6): ?>

        <h2>Admin Account Aanmaken</h2>
        <p class="subtitle">Maak het eerste superadmin account aan.</p>

        <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($e); ?></div>
        <?php endforeach; ?>

        <form method="POST" action="install.php">
            <input type="hidden" name="step" value="6">
            <div class="form-group">
                <label>Gebruikersnaam</label>
                <input type="text" name="admin_username" value="<?php echo htmlspecialchars($_POST['admin_username'] ?? 'admin'); ?>" required>
                <div class="form-hint">Gebruik alleen letters, cijfers en underscores.</div>
            </div>
            <div class="form-group">
                <label>Weergavenaam</label>
                <input type="text" name="admin_display" value="<?php echo htmlspecialchars($_POST['admin_display'] ?? ''); ?>" placeholder="Beheerder">
                <div class="form-hint">Zichtbaar in het systeem (optioneel).</div>
            </div>
            <div class="form-group">
                <label>E-mailadres</label>
                <input type="email" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>" placeholder="admin@uwdomein.nl">
                <div class="form-hint">Optioneel — voor wachtwoord reset.</div>
            </div>
            <div class="form-group">
                <label>Wachtwoord</label>
                <input type="password" name="admin_password" required minlength="8">
                <div class="form-hint">Minimaal 8 tekens.</div>
            </div>
            <div class="form-group">
                <label>Wachtwoord herhalen</label>
                <input type="password" name="admin_password2" required minlength="8">
            </div>
            <button type="submit" class="btn btn-primary">Account aanmaken &rarr;</button>
        </form>

    <?php // =========================================================
    // STEP 7: WRITE CONFIG & FINALIZE
    // =========================================================
    elseif ($step === 7): ?>

        <h2>Configuratie Opslaan</h2>
        <p class="subtitle">Bijna klaar! Sla de databaseconfiguratie op en activeer uw licentie.</p>

        <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($e); ?></div>
        <?php endforeach; ?>

        <?php if (!empty($errors) && isset($_SESSION['pd_install']['manual_config'])): ?>
        <div class="alert alert-info">
            <strong>Handmatige actie vereist:</strong><br>
            Maak het bestand <code>admin/db_config.php</code> aan met de volgende inhoud:
        </div>
        <pre><?php echo htmlspecialchars($_SESSION['pd_install']['manual_config']); ?></pre>
        <p style="margin: 16px 0;">Nadat u het bestand aangemaakt heeft, klik op Voltooien.</p>
        <form method="POST" action="install.php">
            <input type="hidden" name="step" value="7">
            <button type="submit" class="btn btn-primary">Voltooien &rarr;</button>
        </form>
        <?php elseif (empty($errors)): ?>
        <?php
        $licTier = $_SESSION['pd_install']['license_tier'] ?? '';
        $tierLabels = [
            'starter'      => 'Starter',
            'professional' => 'Professional',
            'business'     => 'Business',
            'enterprise'   => 'Enterprise',
            'custom'       => 'Custom',
            'unlimited'    => 'Unlimited',
        ];
        ?>
        <div class="alert alert-success">
            Admin account aangemaakt.
            <?php if ($licTier): ?>
            Licentie <strong><?php echo htmlspecialchars($tierLabels[$licTier] ?? ucfirst($licTier)); ?></strong> wordt geactiveerd bij het voltooien.
            <?php endif; ?>
        </div>
        <form method="POST" action="install.php">
            <input type="hidden" name="step" value="7">
            <button type="submit" class="btn btn-success">Installatie voltooien &rarr;</button>
        </form>
        <?php endif; ?>

    <?php // =========================================================
    // STEP 8: COMPLETE
    // =========================================================
    elseif ($step === 8): ?>

        <h2>Installatie Voltooid!</h2>
        <p class="subtitle">PeopleDisplay is succesvol geïnstalleerd en klaar voor gebruik.</p>

        <div class="final-box">
            <h3>Volgende stappen</h3>
            <p style="margin-top: 8px; line-height: 1.8;">
                &bull; <a href="admin/dashboard.php">Open het Admin Panel</a> om locaties en afdelingen in te stellen<br>
                &bull; <a href="admin/employees_manage.php">Medewerkers toevoegen</a> via Beheer &rarr; Medewerkers<br>
                &bull; <a href="index.php">Open de aanwezigheidsweergave</a> (het hoofdscherm)<br>
                &bull; Stel cron jobs in (zie README.md) voor automatische resets
            </p>
        </div>

        <div class="alert alert-info">
            <strong>De installer is vergrendeld</strong> — install.php weigert opnieuw te starten.
            U kunt het bestand veilig op de server laten staan.
        </div>

        <div class="alert alert-success">
            <strong>Inloggen:</strong> Gebruik de gebruikersnaam en het wachtwoord die u zojuist aangemaakt heeft.<br>
            Rol: <strong>Superadmin</strong> — volledige toegang tot alle functies.
        </div>

        <a href="admin/dashboard.php" class="btn btn-primary">Naar Admin Panel &rarr;</a>
        &nbsp;
        <a href="index.php" class="btn btn-secondary">Naar aanwezigheidsscherm</a>

    <?php endif; ?>

    </div><!-- .card -->
</div><!-- .installer -->
</body>
</html>
