<?php
/**
 * STEP 3: Database Installation
 * Gebruikt install.sql uit de sql/ map
 */

$error = null;
$dbConfig = $_SESSION['db_config'] ?? null;

if (!$dbConfig) {
    header('Location: ?step=2');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Lees install.sql
        $sqlFile = __DIR__ . '/../sql/install.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception('install.sql niet gevonden in sql/ map. Controleer de installatie bestanden.');
        }

        $sql = file_get_contents($sqlFile);
        if (empty($sql)) {
            throw new Exception('install.sql is leeg.');
        }

        // Voer SQL statements uit
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        // Split op ; maar niet binnen strings
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';

        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];

            if (!$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringChar = $char;
            } elseif ($inString && $char === $stringChar && $sql[$i-1] !== '\\') {
                $inString = false;
            }

            $current .= $char;

            if (!$inString && $char === ';') {
                $stmt = trim($current);
                if (!empty($stmt) && $stmt !== ';') {
                    // Sla commentaar-only statements over
                    $withoutComments = preg_replace('/--[^\n]*\n/', '', $stmt);
                    $withoutComments = preg_replace('/\/\*.*?\*\//s', '', $withoutComments);
                    if (trim($withoutComments) !== ';' && !empty(trim($withoutComments))) {
                        $statements[] = $stmt;
                    }
                }
                $current = '';
            }
        }

        $executed = 0;
        $errors = [];
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (empty($stmt) || $stmt === ';') continue;
            try {
                $pdo->exec($stmt);
                $executed++;
            } catch (PDOException $e) {
                // Negeer duplicate key errors bij INSERT IGNORE
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    $errors[] = substr($stmt, 0, 80) . '... → ' . $e->getMessage();
                }
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        // Controleer aantal tabellen
        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$dbConfig['name']}'");
        $tableCount = (int)$stmt->fetchColumn();

        if ($tableCount < 20) {
            throw new Exception("Slechts {$tableCount} tabellen aangemaakt. Verwacht minimaal 20. Fouten: " . implode('; ', array_slice($errors, 0, 3)));
        }

        $_SESSION['db_installed'] = true;
        $_SESSION['table_count'] = $tableCount;
        markStepCompleted(3);
        header('Location: ?step=4');
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<h2>Database Installatie</h2>
<p style="color: #666; margin-bottom: 30px;">
    De database wordt aangemaakt met alle benodigde tabellen voor PeopleDisplay v2.1.
</p>

<?php if ($error): ?>
    <div class="alert alert-error">
        <strong>⚠ Fout bij database installatie</strong>
        <p><?php echo htmlspecialchars($error); ?></p>
    </div>
    <button onclick="location.reload()" class="btn btn-secondary" style="margin-top: 15px;">🔄 Opnieuw proberen</button>
<?php else: ?>
    <div class="alert alert-info">
        <strong>ℹ️ Klaar om te installeren</strong>
        <p>De volgende stap installeert alle tabellen in database <strong><?php echo htmlspecialchars($dbConfig['name']); ?></strong>.</p>
    </div>

    <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <h3 style="margin: 0 0 15px 0; font-size: 16px;">Wat wordt geïnstalleerd:</h3>
        <ul style="margin: 0; padding-left: 20px; line-height: 1.8; color: #555; font-size: 14px;">
            <li>30+ database tabellen</li>
            <li>Medewerkers, locaties en afdelingen</li>
            <li>Gebruikers en rollen systeem</li>
            <li>Bezoekersregistratie</li>
            <li>Kiosk tokens en sessies</li>
            <li>Licentie systeem (alle 6 tiers)</li>
            <li>Audit log</li>
            <li>Standaard configuratie</li>
        </ul>
    </div>

    <form method="post" style="margin-top: 20px;">
        <button type="submit" id="install-btn" class="btn btn-primary" onclick="startInstall()">
            🚀 Installeer Database
        </button>
    </form>
<?php endif; ?>

<div id="installing-msg" style="display:none; margin-top: 20px;">
    <div style="background: #d1ecf1; padding: 20px; border-radius: 8px; border-left: 4px solid #2196f3; color: #0c5460;">
        <strong>⏳ Database wordt geïnstalleerd...</strong>
        <p style="margin: 5px 0 0 0;">Even geduld, dit kan een moment duren.</p>
    </div>
</div>

<style>
.alert { padding: 15px 20px; border-radius: 6px; border-left: 4px solid; margin-bottom: 20px; }
.alert-error { background: #f8d7da; border-left-color: #e74c3c; color: #721c24; }
.alert-info { background: #d1ecf1; border-left-color: #2196f3; color: #0c5460; }
.alert strong { display: block; margin-bottom: 5px; }
.alert p { margin: 0; font-size: 14px; }
</style>

<script>
function startInstall() {
    document.getElementById('install-btn').disabled = true;
    document.getElementById('install-btn').textContent = '⏳ Bezig...';
    document.getElementById('installing-msg').style.display = 'block';
}
</script>
