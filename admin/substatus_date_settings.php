<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * ============================================================================
 * ADMIN: Sub-Status Datum Instellingen
 * ============================================================================
 * Bestand: substatus_date_settings.php
 * Locatie: /admin/substatus_date_settings.php
 * 
 * Configureer per button of datum/tijd gevraagd moet worden
 * ============================================================================
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/auth_helper.php';
requireAdmin();
requireAdminFeature('manage_substatus_dates');

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $button1_ask = isset($_POST['button1_ask_until']) ? 1 : 0;
    $button2_ask = isset($_POST['button2_ask_until']) ? 1 : 0;
    $button3_ask = isset($_POST['button3_ask_until']) ? 1 : 0;
    
    $stmt = $db->prepare("
        UPDATE config 
        SET button1_ask_until = ?,
            button2_ask_until = ?,
            button3_ask_until = ?
        WHERE id = 1
    ");
    
    $stmt->execute([$button1_ask, $button2_ask, $button3_ask]);
    
    $success = "Instellingen opgeslagen!";
}

// Get current settings
$stmt = $db->query("
    SELECT 
        button1_name, button1_ask_until,
        button2_name, button2_ask_until,
        button3_name, button3_ask_until
    FROM config 
    WHERE id = 1
");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

// Get statistics
$statsStmt = $db->query("
    SELECT 
        COUNT(*) as total_with_until,
        SUM(CASE WHEN sub_status_until > NOW() THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN sub_status_until <= NOW() THEN 1 ELSE 0 END) as expired
    FROM employees
    WHERE actief = 1 
      AND sub_status_until IS NOT NULL
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sub-Status Datum Instellingen - PeopleDisplay</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f7fafc; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        
        .header { background: white; padding: 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header h1 { font-size: 28px; color: #2d3748; margin-bottom: 8px; }
        .header p { color: #718096; font-size: 14px; }
        
        .back-link { color: #667eea; text-decoration: none; display: inline-block; margin-bottom: 12px; font-size: 14px; }
        .back-link:hover { text-decoration: underline; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #cbd5e0;
        }
        
        .stat-card.active { border-left-color: #48bb78; }
        .stat-card.expired { border-left-color: #fc8181; }
        
        .stat-value { font-size: 36px; font-weight: 700; color: #2d3748; margin-bottom: 6px; }
        .stat-label { font-size: 12px; color: #718096; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        
        .settings-box { background: white; padding: 32px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .settings-box h2 { margin-bottom: 24px; color: #2d3748; font-size: 22px; }
        
        .info-box {
            background: #e6fffa;
            border: 2px solid #81e6d9;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            color: #234e52;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .button-setting {
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.2s;
        }
        
        .button-setting:hover {
            border-color: #cbd5e0;
        }
        
        .button-setting.enabled {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .button-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 12px;
        }
        
        .button-icon {
            font-size: 32px;
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        
        .button-info {
            flex: 1;
        }
        
        .button-name {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 4px;
        }
        
        .button-desc {
            font-size: 13px;
            color: #718096;
        }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .checkbox-wrapper:hover {
            background: #edf2f7;
        }
        
        .checkbox-wrapper input[type="checkbox"] {
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        
        .checkbox-label {
            font-size: 15px;
            font-weight: 600;
            color: #2d3748;
            cursor: pointer;
        }
        
        .save-btn {
            padding: 16px 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
        }
        
        .success-message {
            background: #c6f6d5;
            border: 2px solid #48bb78;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            color: #22543d;
            font-weight: 600;
        }
        
        .preview-example {
            background: #edf2f7;
            padding: 16px;
            border-radius: 8px;
            margin-top: 12px;
            font-size: 14px;
            color: #4a5568;
        }
        
        .preview-example strong {
            color: #2d3748;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Terug naar Dashboard</a>
        
        <div class="header">
            <h1>📅 Sub-Status Datum Instellingen</h1>
            <p>Configureer of er een datum/tijd gevraagd wordt bij sub-status knoppen</p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_with_until']; ?></div>
                <div class="stat-label">Met Datum/Tijd</div>
            </div>
            <div class="stat-card active">
                <div class="stat-value"><?php echo $stats['active']; ?></div>
                <div class="stat-label">Nog Actief</div>
            </div>
            <div class="stat-card expired">
                <div class="stat-value"><?php echo $stats['expired']; ?></div>
                <div class="stat-label">Verlopen</div>
            </div>
        </div>
        
        <!-- Settings Form -->
        <div class="settings-box">
            <h2>⚙️ Button Instellingen</h2>
            
            <?php if (isset($success)): ?>
            <div class="success-message">
                ✅ <?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <div class="info-box">
                💡 <strong>Hoe werkt het?</strong><br>
                Als "Vraag datum/tijd" is ingeschakeld, krijgt de gebruiker een popup met een datum/tijd kiezer wanneer ze op deze button klikken. De sub-status wordt dan automatisch gereset wanneer de datum/tijd is bereikt.
            </div>
            
            <form method="POST">
                <!-- Button 1 -->
                <div class="button-setting <?php echo $config['button1_ask_until'] ? 'enabled' : ''; ?>">
                    <div class="button-header">
                        <div class="button-icon">☕</div>
                        <div class="button-info">
                            <div class="button-name"><?php echo htmlspecialchars($config['button1_name']); ?></div>
                            <div class="button-desc">Knop 1 - Meestal voor korte afwezigheid</div>
                        </div>
                    </div>
                    
                    <label class="checkbox-wrapper">
                        <input type="checkbox" 
                               name="button1_ask_until" 
                               <?php echo $config['button1_ask_until'] ? 'checked' : ''; ?>
                               onchange="this.closest('.button-setting').classList.toggle('enabled', this.checked)">
                        <span class="checkbox-label">📅 Vraag datum/tijd bij deze button</span>
                    </label>
                    
                    <?php if ($config['button1_ask_until']): ?>
                    <div class="preview-example">
                        <strong>Voorbeeld:</strong> "☕ Tot wanneer <?php echo $config['button1_name']; ?>?" → Kies tijd → "Tot 15-01 14:30"
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Button 2 -->
                <div class="button-setting <?php echo $config['button2_ask_until'] ? 'enabled' : ''; ?>">
                    <div class="button-header">
                        <div class="button-icon">🏠</div>
                        <div class="button-info">
                            <div class="button-name"><?php echo htmlspecialchars($config['button2_name']); ?></div>
                            <div class="button-desc">Knop 2 - Meestal voor thuiswerken</div>
                        </div>
                    </div>
                    
                    <label class="checkbox-wrapper">
                        <input type="checkbox" 
                               name="button2_ask_until" 
                               <?php echo $config['button2_ask_until'] ? 'checked' : ''; ?>
                               onchange="this.closest('.button-setting').classList.toggle('enabled', this.checked)">
                        <span class="checkbox-label">📅 Vraag datum/tijd bij deze button</span>
                    </label>
                    
                    <?php if ($config['button2_ask_until']): ?>
                    <div class="preview-example">
                        <strong>Voorbeeld:</strong> "🏠 Tot wanneer <?php echo $config['button2_name']; ?>?" → Kies tijd → "Tot 15-01 17:00"
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Button 3 -->
                <div class="button-setting <?php echo $config['button3_ask_until'] ? 'enabled' : ''; ?>">
                    <div class="button-header">
                        <div class="button-icon">🌴</div>
                        <div class="button-info">
                            <div class="button-name"><?php echo htmlspecialchars($config['button3_name']); ?></div>
                            <div class="button-desc">Knop 3 - Meestal voor vakantie/verlof</div>
                        </div>
                    </div>
                    
                    <label class="checkbox-wrapper">
                        <input type="checkbox" 
                               name="button3_ask_until" 
                               <?php echo $config['button3_ask_until'] ? 'checked' : ''; ?>
                               onchange="this.closest('.button-setting').classList.toggle('enabled', this.checked)">
                        <span class="checkbox-label">📅 Vraag datum/tijd bij deze button</span>
                    </label>
                    
                    <?php if ($config['button3_ask_until']): ?>
                    <div class="preview-example">
                        <strong>Voorbeeld:</strong> "🌴 Tot wanneer <?php echo $config['button3_name']; ?>?" → Kies datum → "Tot 22-01 23:59"
                    </div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" name="save" class="save-btn">
                    💾 Instellingen Opslaan
                </button>
            </form>
        </div>
    </div>
</body>
</html>
