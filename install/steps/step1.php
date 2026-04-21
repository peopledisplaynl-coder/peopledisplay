<?php
/**
 * STEP 1: Welcome & System Requirements Check
 */

$phpOK = checkPHPVersion();
$missingExtensions = checkPHPExtensions();
$permissionIssues = checkPermissions();

$allChecksPass = $phpOK && empty($missingExtensions) && empty($permissionIssues);

?>

<h2>Welkom bij PeopleDisplay</h2>
<p style="color: #666; margin-bottom: 30px;">
    Deze wizard helpt je bij het installeren van PeopleDisplay v2.1.1 op je webserver.
    Controleer eerst of je systeem aan alle vereisten voldoet.
</p>

<div class="requirements-check">
    <h3>Systeem Vereisten</h3>

    <!-- PHP Version -->
    <div class="check-item <?php echo $phpOK ? 'check-pass' : 'check-fail'; ?>">
        <div class="check-icon"><?php echo $phpOK ? '✓' : '✗'; ?></div>
        <div class="check-content">
            <strong>PHP Versie <?php echo MIN_PHP_VERSION; ?>+</strong>
            <p>Huidige versie: <?php echo PHP_VERSION; ?></p>
        </div>
    </div>

    <!-- PHP Extensions -->
    <div class="check-item <?php echo empty($missingExtensions) ? 'check-pass' : 'check-fail'; ?>">
        <div class="check-icon"><?php echo empty($missingExtensions) ? '✓' : '✗'; ?></div>
        <div class="check-content">
            <strong>PHP Extensions</strong>
            <?php if (empty($missingExtensions)): ?>
                <p>Alle vereiste extensies zijn geïnstalleerd</p>
            <?php else: ?>
                <p style="color: #e74c3c;">Ontbrekende extensies:</p>
                <ul style="margin: 5px 0 0 20px;">
                    <?php foreach ($missingExtensions as $ext): ?>
                        <li><?php echo htmlspecialchars($ext); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p style="font-size: 12px; color: #999; margin-top: 10px;">
                    Installeer deze via je hosting control panel of vraag je hosting provider.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- File Permissions -->
    <div class="check-item <?php echo empty($permissionIssues) ? 'check-pass' : 'check-warning'; ?>">
        <div class="check-icon"><?php echo empty($permissionIssues) ? '✓' : '⚠'; ?></div>
        <div class="check-content">
            <strong>Bestandsrechten</strong>
            <?php if (empty($permissionIssues)): ?>
                <p>Alle directories zijn schrijfbaar</p>
            <?php else: ?>
                <p style="color: #ff9800;">Sommige directories zijn niet schrijfbaar:</p>
                <ul style="margin: 5px 0 0 20px;">
                    <?php foreach ($permissionIssues as $dir => $purpose): ?>
                        <li><code><?php echo htmlspecialchars($dir); ?></code> — <?php echo htmlspecialchars($purpose); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p style="font-size: 12px; color: #999; margin-top: 10px;">
                    Stel rechten in via FTP: chmod 755 op de genoemde mappen.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- MySQL Check -->
    <div class="check-item check-info">
        <div class="check-icon">ℹ</div>
        <div class="check-content">
            <strong>MySQL Database</strong>
            <p>Je hebt toegang nodig tot een MySQL of MariaDB database (versie 5.7+)</p>
        </div>
    </div>

    <!-- install.sql check -->
    <div class="check-item <?php echo file_exists(__DIR__ . '/../sql/install.sql') ? 'check-pass' : 'check-fail'; ?>">
        <div class="check-icon"><?php echo file_exists(__DIR__ . '/../sql/install.sql') ? '✓' : '✗'; ?></div>
        <div class="check-content">
            <strong>Installatie bestanden</strong>
            <?php if (file_exists(__DIR__ . '/../sql/install.sql')): ?>
                <p>install.sql gevonden in de sql/ map</p>
            <?php else: ?>
                <p style="color: #e74c3c;">install.sql niet gevonden in de sql/ map. Upload de installer bestanden opnieuw.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($allChecksPass && file_exists(__DIR__ . '/../sql/install.sql')): ?>
    <div class="alert alert-success" style="margin-top: 30px;">
        <strong>✓ Systeem voldoet aan alle vereisten!</strong>
        <p>Je kunt doorgaan met de installatie van PeopleDisplay v2.1.1.</p>
    </div>

    <form method="post" action="?step=2" style="margin-top: 20px;">
        <?php markStepCompleted(1); ?>
        <button type="submit" class="btn btn-primary">
            Volgende: Database Configuratie →
        </button>
    </form>
<?php else: ?>
    <div class="alert alert-error" style="margin-top: 30px;">
        <strong>⚠ Systeem voldoet niet aan alle vereisten</strong>
        <p>Los de bovenstaande problemen op voordat je verder gaat.</p>
    </div>

    <button type="button" onclick="location.reload()" class="btn btn-secondary" style="margin-top: 20px;">
        🔄 Opnieuw Controleren
    </button>
<?php endif; ?>

<style>
.requirements-check { background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0; }
.check-item { display: flex; gap: 15px; padding: 15px; background: white; border-radius: 6px; margin-bottom: 10px; border-left: 4px solid #e0e0e0; }
.check-item.check-pass { border-left-color: #4caf50; }
.check-item.check-fail { border-left-color: #e74c3c; }
.check-item.check-warning { border-left-color: #ff9800; }
.check-item.check-info { border-left-color: #2196f3; }
.check-icon { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: bold; flex-shrink: 0; }
.check-pass .check-icon { background: #d4edda; color: #4caf50; }
.check-fail .check-icon { background: #f8d7da; color: #e74c3c; }
.check-warning .check-icon { background: #fff3cd; color: #ff9800; }
.check-info .check-icon { background: #d1ecf1; color: #2196f3; }
.check-content { flex: 1; }
.check-content strong { display: block; margin-bottom: 5px; }
.check-content p { margin: 0; font-size: 14px; color: #666; }
.alert { padding: 15px 20px; border-radius: 6px; border-left: 4px solid; }
.alert-success { background: #d4edda; border-left-color: #4caf50; color: #155724; }
.alert-error { background: #f8d7da; border-left-color: #e74c3c; color: #721c24; }
.alert strong { display: block; margin-bottom: 5px; }
.alert p { margin: 0; font-size: 14px; }
code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; font-size: 13px; }
</style>
