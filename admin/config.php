<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * Configuration Management - V1 Compatible
 * Works with existing v1 config table structure
 */

require_once __DIR__ . '/auth_helper.php';
requireAdmin();

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get current config row (should be only one)
        $stmt = $db->query("SELECT * FROM config LIMIT 1");
        $currentConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentConfig) {
            // No config row exists, create one
            $db->exec("INSERT INTO config (id) VALUES (1)");
            $currentConfig = ['id' => 1];
        }
        
        // Update fields
        $updates = [];
        $values = [];
        
        if (isset($_POST['button1_name'])) {
            $updates[] = "button1_name = ?";
            $values[] = $_POST['button1_name'];
        }
        
        if (isset($_POST['button2_name'])) {
            $updates[] = "button2_name = ?";
            $values[] = $_POST['button2_name'];
        }
        
        if (isset($_POST['button3_name'])) {
            $updates[] = "button3_name = ?";
            $values[] = $_POST['button3_name'];
        }
        
        if (isset($_POST['allow_user_button_names'])) {
            $updates[] = "allow_user_button_names = ?";
            $values[] = isset($_POST['allow_user_button_names']) ? 1 : 0;
        }
        
        if (isset($_POST['allow_auto_fullscreen'])) {
            $updates[] = "allow_auto_fullscreen = ?";
            $values[] = isset($_POST['allow_auto_fullscreen']) ? 1 : 0;
        }
        
        if (isset($_POST['presentationAutoShowMs'])) {
            $updates[] = "presentationAutoShowMs = ?";
            $values[] = $_POST['presentationAutoShowMs'] ?: null;
        }
        
        if (!empty($updates)) {
            $sql = "UPDATE config SET " . implode(', ', $updates) . " WHERE id = 1";
            $stmt = $db->prepare($sql);
            $stmt->execute($values);
            
            $message = 'Configuratie succesvol opgeslagen';
            $messageType = 'success';
        }
        
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get current config
$stmt = $db->query("SELECT * FROM config LIMIT 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    $config = [
        'button1_name' => 'PAUZE',
        'button2_name' => 'THUISWERKEN',
        'button3_name' => 'VAKANTIE',
        'allow_user_button_names' => 0,
        'allow_auto_fullscreen' => 0,
        'presentationAutoShowMs' => null,
    ];
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuratie - PeopleDisplay Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header h1 { font-size: 24px; }
        .btn-logout { background: rgba(255,255,255,0.2); color: white; padding: 8px 16px; border: 1px solid rgba(255,255,255,0.3); border-radius: 6px; text-decoration: none; font-size: 14px; }
        .container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
        .message { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border-left: 4px solid #4caf50; }
        .message.error { background: #f8d7da; color: #721c24; border-left: 4px solid #f44336; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group small { display: block; margin-top: 5px; color: #666; font-size: 13px; }
        input[type="text"], input[type="number"] { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; }
        input[type="checkbox"] { margin-right: 8px; width: 18px; height: 18px; }
        .checkbox-label { display: flex; align-items: center; font-weight: normal; }
        .btn { padding: 12px 24px; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-secondary { background: #6c757d; color: white; margin-left: 10px; }
        h3 { color: #333; margin: 30px 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #e0e0e0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚙️ Configuratie</h1>
        <div>
            <span style="margin-right: 20px;">Ingelogd als: <strong><?php echo htmlspecialchars($current_display_name); ?></strong></span>
            <a href="dashboard.php" class="btn-logout">← Dashboard</a>
            <a href="logout.php" class="btn-logout">Uitloggen</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2 style="margin-bottom: 20px;">PeopleDisplay Instellingen</h2>
            
            <form method="post">
                <h3>🔘 Button Labels</h3>
                <p style="color: #666; margin-bottom: 15px;">Pas de namen van de extra buttons aan op het aanmeldscherm</p>
                
                <div class="form-group">
                    <label>Button 1 Naam</label>
                    <input type="text" name="button1_name" value="<?php echo htmlspecialchars($config['button1_name']); ?>" maxlength="50">
                    <small>Standaard: PAUZE</small>
                </div>
                
                <div class="form-group">
                    <label>Button 2 Naam</label>
                    <input type="text" name="button2_name" value="<?php echo htmlspecialchars($config['button2_name']); ?>" maxlength="50">
                    <small>Standaard: THUISWERKEN</small>
                </div>
                
                <div class="form-group">
                    <label>Button 3 Naam</label>
                    <input type="text" name="button3_name" value="<?php echo htmlspecialchars($config['button3_name']); ?>" maxlength="50">
                    <small>Standaard: VAKANTIE</small>
                </div>
                
                <h3>🎛️ Display Opties</h3>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="allow_user_button_names" <?php echo $config['allow_user_button_names'] ? 'checked' : ''; ?>>
                        Sta gebruikers toe om button namen te wijzigen
                    </label>
                    <small>Als ingeschakeld kunnen gebruikers zelf de button labels aanpassen</small>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="allow_auto_fullscreen" <?php echo $config['allow_auto_fullscreen'] ? 'checked' : ''; ?>>
                        Automatisch fullscreen modus
                    </label>
                    <small>Display gaat automatisch naar fullscreen bij opstarten</small>
                </div>
                
                <div class="form-group">
                    <label>Presentatie Auto-Show (milliseconden)</label>
                    <input type="number" name="presentationAutoShowMs" value="<?php echo htmlspecialchars($config['presentationAutoShowMs'] ?? ''); ?>" placeholder="bijv. 5000">
                    <small>Tijd voordat presentatie automatisch start (leeg = uit)</small>
                </div>
                
                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">💾 Instellingen Opslaan</button>
                    <a href="dashboard.php" class="btn btn-secondary">Annuleren</a>
                </div>
            </form>
        </div>
        
        <div class="card" style="background: #e7f3ff; border-left: 4px solid #2196F3;">
            <h3 style="border: none; margin-top: 0; color: #1976d2;">💡 Over deze instellingen</h3>
            <ul style="margin-left: 20px; color: #555; line-height: 1.8;">
                <li>Button labels worden direct getoond op het aanmeldscherm</li>
                <li>Wijzigingen zijn direct actief (geen herstart nodig)</li>
                <li>Google Sheets integratie instellingen worden beheerd via de v1 interface</li>
            </ul>
        </div>
    </div>
</body>
</html>
