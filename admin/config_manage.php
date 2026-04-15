<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * FIX: Verberg Google Script URL en Sheet ID (v1 legacy velden)
 * v2.0 gebruikt alleen MySQL, geen Google Sheets meer
 */

// CRITICAL: NO CACHE HEADERS
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/auth_helper.php';
requireAdmin();
requireAdminFeature('manage_system_config');

$message = '';
$justSaved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Alleen button configuratie opslaan
        $button1_name = trim($_POST['button1_name'] ?? 'PAUZE');
        $button2_name = trim($_POST['button2_name'] ?? 'THUISWERKEN');
        $button3_name = trim($_POST['button3_name'] ?? 'VAKANTIE');
        $allow_user_button_names = isset($_POST['allow_user_button_names']) ? 1 : 0;
        $allow_auto_fullscreen = isset($_POST['allow_auto_fullscreen']) ? 1 : 0;
        
        $stmt = $db->prepare("
            UPDATE config SET 
                button1_name = ?,
                button2_name = ?,
                button3_name = ?,
                allow_user_button_names = ?,
                allow_auto_fullscreen = ?
            WHERE id = 1
        ");
        $stmt->execute([
            $button1_name,
            $button2_name,
            $button3_name,
            $allow_user_button_names,
            $allow_auto_fullscreen
        ]);
        
        $justSaved = true;
        $message = 'Configuratie opgeslagen!';
    } catch (Exception $e) {
        $message = 'Fout: ' . $e->getMessage();
    }
}

$stmt = $db->query("SELECT SQL_NO_CACHE * FROM config WHERE id = 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Configuratie</title>
    <?php if ($justSaved): ?>
    <meta http-equiv="refresh" content="0;url=<?php echo $_SERVER['PHP_SELF']; ?>?saved=1&t=<?php echo time(); ?>">
    <?php endif; ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f7fafc; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .header { background: white; padding: 24px; border-radius: 12px; margin-bottom: 24px; }
        .message { background: #c6f6d5; color: #22543d; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .form-box { background: white; padding: 24px; border-radius: 12px; margin-bottom: 20px; }
        .form-box h3 { margin-bottom: 15px; color: #2d3748; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 6px; }
        .form-group input[type="checkbox"] { width: auto; }
        .btn { padding: 12px 24px; background: #48bb78; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .info-box { background: #bee3f8; border: 2px solid #4299e1; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; }
    </style>
    <script>
        // ⚠️ FIX: Force reload on back button (bfcache prevention)
        window.addEventListener('pageshow', function(event) {
            if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                console.log('🔄 Config page loaded from cache - forcing reload');
                window.location.reload();
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" style="color: #667eea; text-decoration: none;">← Terug</a>
        
        <div class="header">
            <h1>⚙️ Systeem Configuratie</h1>
            <p>PeopleDisplay v2.0 - MySQL Database</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message">✅ <?php echo htmlspecialchars($message); ?></div>
        <?php elseif (isset($_GET['saved'])): ?>
            <div class="message">✅ Configuratie opgeslagen!</div>
        <?php endif; ?>
        
        <form method="POST">
            
            <div class="form-box">
                <h3>🎨 Extra Knoppen</h3>
                <div class="form-group">
                    <label>Knop 1 Naam (standaard voor users)</label>
                    <input type="text" name="button1_name" value="<?php echo htmlspecialchars($config['button1_name']); ?>" maxlength="20">
                </div>
                <div class="form-group">
                    <label>Knop 2 Naam</label>
                    <input type="text" name="button2_name" value="<?php echo htmlspecialchars($config['button2_name']); ?>" maxlength="20">
                </div>
                <div class="form-group">
                    <label>Knop 3 Naam</label>
                    <input type="text" name="button3_name" value="<?php echo htmlspecialchars($config['button3_name']); ?>" maxlength="20">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="allow_user_button_names" <?php echo $config['allow_user_button_names'] ? 'checked' : ''; ?>>
                        Gebruikers mogen eigen button namen instellen
                    </label>
                </div>
            </div>
            
            <div class="form-box">
                <h3>🖥️ Display Instellingen</h3>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="allow_auto_fullscreen" <?php echo $config['allow_auto_fullscreen'] ? 'checked' : ''; ?>>
                        Auto-fullscreen toestaan (voor kiosk mode)
                    </label>
                </div>
            </div>
            
            <button type="submit" class="btn">💾 Configuratie Opslaan</button>
        </form>
    </div>
</body>
</html>
