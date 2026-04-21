<?php
/**
 * STEP 6: Installation Complete
 * Schrijft db.php met Strato-compatibele session handler en PD_LICENSE_SALT
 */

$dbConfig = $_SESSION['db_config'] ?? null;
$configWritten = false;
$configError = null;
$tableCount = $_SESSION['table_count'] ?? '30+';

if ($dbConfig) {
    $rootDir = dirname(__DIR__);
    $includesDir = $rootDir . '/includes';
    $targetPath = $includesDir . '/db.php';

    if (!is_dir($includesDir)) {
        @mkdir($includesDir, 0755, true);
    }

    if (!is_writable($includesDir)) {
        $configError = "De map <code>includes/</code> is niet schrijfbaar. Stel de rechten in op 755 via FTP.";
    } else {
        $dbHost = addslashes($dbConfig['host']);
        $dbName = addslashes($dbConfig['name']);
        $dbUser = addslashes($dbConfig['user']);
        $dbPass = addslashes($dbConfig['pass']);

        // Genereer een willekeurige salt
        $salt = 'PEOPLEDISPLAY_SALT_' . strtoupper(bin2hex(random_bytes(8)));

        $configContent = '<?php
/**
 * Database configuratie
 * Gegenereerd door PeopleDisplay installer op ' . date('Y-m-d H:i:s') . '
 * 
 * BESTANDSNAAM: db.php
 * LOCATIE:      /includes/db.php
 * 
 * BELANGRIJK: Verwijder de install/ map na installatie!
 */

// Database credentials
$DB_HOST = \'' . $dbHost . '\';
$DB_NAME = \'' . $dbName . '\';
$DB_USER = \'' . $dbUser . '\';
$DB_PASS = \'' . $dbPass . '\';

// PeopleDisplay licentie salt — nooit wijzigen na activering!
if (!defined(\'PD_LICENSE_SALT\')) {
    define(\'PD_LICENSE_SALT\', \'' . $salt . '\');
}

// Session configuratie — compatibel met Strato en andere shared hosting
if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = __DIR__ . \'/../tmp/sessions\';
    if (!file_exists($sessionPath)) {
        @mkdir($sessionPath, 0755, true);
    }
    if (is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }
    session_set_cookie_params([
        \'lifetime\' => 0,
        \'path\'     => \'/\',
        \'secure\'   => isset($_SERVER[\'HTTPS\']) && $_SERVER[\'HTTPS\'] !== \'off\',
        \'httponly\' => true,
        \'samesite\' => \'Lax\',
    ]);
    session_start();
}

// Database verbinding
try {
    $db = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log(\'PeopleDisplay DB error: \' . $e->getMessage());
    die(\'Database verbinding mislukt. Controleer de instellingen in includes/db.php.\');
}

// Base path detectie
if (!defined(\'BASE_PATH\')) {
    $scriptPath = $_SERVER[\'SCRIPT_NAME\'] ?? \'\';
    $base = \'\';
    foreach ([\'/peopledisplay\', \'/onsteam\', \'/app\'] as $candidate) {
        if (strpos($scriptPath, $candidate . \'/\') !== false) {
            $base = $candidate;
            break;
        }
    }
    define(\'BASE_PATH\', $base);
}
';

        $bytesWritten = @file_put_contents($targetPath, $configContent);

        if ($bytesWritten === false) {
            $configError = "Kon <code>includes/db.php</code> niet schrijven. Controleer de bestandsrechten.";
        } else {
            $configWritten = true;
        }
    }
}

// Maak lock bestand aan
@file_put_contents(__DIR__ . '/../.installed', 'Installed on: ' . date('Y-m-d H:i:s') . PHP_EOL . 'Version: 2.1.1');

$adminUsername = $_SESSION['admin_username'] ?? 'admin';
$dbName = $_SESSION['db_config']['name'] ?? '';

// Sessie opruimen
session_destroy();
?>

<div style="text-align: center; padding: 20px 0;">
    <div style="font-size: 64px; margin-bottom: 16px;">🎉</div>
    <h2 style="font-size: 28px; color: #4caf50; margin-bottom: 12px;">Installatie Voltooid!</h2>
    <p style="font-size: 16px; color: #666;">
        PeopleDisplay v2.1.1 is succesvol geïnstalleerd en klaar voor gebruik!
    </p>
</div>

<?php if ($configError): ?>
    <div style="background: #fff3cd; padding: 20px; border-radius: 8px; border-left: 4px solid #ff9800; color: #856404; margin: 20px 0;">
        <strong>⚠️ Configuratie waarschuwing</strong>
        <p style="margin: 10px 0 0 0;"><?php echo $configError; ?></p>
        <p style="margin: 10px 0 0 0; font-size: 13px;">
            Maak <code>includes/db.php</code> handmatig aan met de database gegevens.
        </p>
    </div>
<?php elseif ($configWritten): ?>
    <div style="background: #d4edda; padding: 16px 20px; border-radius: 8px; border-left: 4px solid #4caf50; color: #155724; margin: 20px 0;">
        <strong>✓ Database configuratie aangemaakt</strong>
        <p style="margin: 5px 0 0 0; font-size: 14px;"><code>includes/db.php</code> is succesvol aangemaakt met session handler en licentie salt.</p>
    </div>
<?php endif; ?>

<div style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); padding: 25px; border-radius: 8px; border-left: 5px solid #4caf50; margin: 20px 0;">
    <h3 style="color: #155724; margin: 0 0 15px 0;">✅ Wat is geïnstalleerd:</h3>
    <ul style="margin: 0; padding-left: 20px; line-height: 1.9; color: #155724;">
        <li><strong>Database:</strong> <?php echo htmlspecialchars($dbName); ?> met <?php echo $tableCount; ?> tabellen</li>
        <li><strong>Admin account:</strong> <?php echo htmlspecialchars($adminUsername); ?> (superadmin)</li>
        <li><strong>Licenties:</strong> Starter gratis, Professional t/m Unlimited beschikbaar</li>
        <li><strong>Configuratie:</strong> db.php met session handler en licentie salt</li>
        <li><strong>Standaard instellingen:</strong> Knoppen, features en rollen</li>
    </ul>
</div>

<div style="background: #f8d7da; padding: 20px; border-radius: 8px; border-left: 4px solid #e74c3c; color: #721c24; margin: 20px 0;">
    <strong>🔒 KRITISCH — Beveilig je installatie!</strong>
    <p style="margin: 10px 0 0 0;">Verwijder de <code>install/</code> map <strong>direct</strong> via FTP na deze stap.</p>
    <p style="margin: 8px 0 0 0; font-size: 13px;">Zolang deze map aanwezig is kan iedereen de installer opnieuw uitvoeren!</p>
</div>

<div style="background: #f9f9f9; padding: 25px; border-radius: 8px; border: 1px solid #e0e0e0; margin: 20px 0;">
    <h3 style="margin: 0 0 15px 0; color: #333;">📋 Volgende stappen:</h3>
    <ol style="margin: 0; padding-left: 25px; line-height: 2; color: #555; font-size: 14px;">
        <li>Verwijder de <code>install/</code> map via FTP</li>
        <li>Login met je admin account: <strong><?php echo htmlspecialchars($adminUsername); ?></strong></li>
        <li>Activeer een licentie via Admin → Licentiebeheer (Starter is gratis)</li>
        <li>Voeg medewerkers toe via Admin → Medewerkers</li>
        <li>Configureer locaties via Admin → Locaties</li>
        <li>Test het aanmeldscherm op tablet of desktop</li>
    </ol>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin: 20px 0;">
    <div style="background: #f0f7ff; padding: 20px; border-radius: 8px; border-left: 4px solid #2196f3;">
        <h4 style="margin: 0 0 8px 0; color: #1976d2; font-size: 14px;">📱 PWA</h4>
        <p style="margin: 0; font-size: 13px; color: #555;">Installeerbaar als app op telefoon en tablet. Open in Chrome/Safari → Voeg toe aan beginscherm.</p>
    </div>
    <div style="background: #f0f7ff; padding: 20px; border-radius: 8px; border-left: 4px solid #2196f3;">
        <h4 style="margin: 0 0 8px 0; color: #1976d2; font-size: 14px;">🔑 Licentie</h4>
        <p style="margin: 0; font-size: 13px; color: #555;">De Starter versie is gratis (max 10 medewerkers, 1 locatie). Upgrade via peopledisplay.nl.</p>
    </div>
    <div style="background: #f0f7ff; padding: 20px; border-radius: 8px; border-left: 4px solid #2196f3;">
        <h4 style="margin: 0 0 8px 0; color: #1976d2; font-size: 14px;">🔄 Updates</h4>
        <p style="margin: 0; font-size: 13px; color: #555;">Automatische updatemeldingen in het dashboard. Updates installeren met één klik.</p>
    </div>
</div>

<div style="text-align: center; margin: 40px 0;">
    <a href="../login.php" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 16px 48px; border-radius: 8px; font-size: 18px; font-weight: 700; text-decoration: none; box-shadow: 0 4px 20px rgba(102,126,234,0.4);">
        🚀 Ga naar Login
    </a>
</div>

<p style="text-align: center; color: #999; font-size: 13px; margin-top: 20px;">
    PeopleDisplay v2.1.1 — Open Core · © 2026 Ton Labee · peopledisplay.nl
</p>
