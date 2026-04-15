<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * ═══════════════════════════════════════════════════════════════════
 * PROJECT:      PeopleDisplay v2.0
 * BESTAND:      bulk_actions.php
 * LOCATIE:      /admin/bulk_actions.php
 * VERSIE:       3.1 - FIXED JavaScript mismatch
 * ═══════════════════════════════════════════════════════════════════
 * 
 * Bulk acties voor dagelijkse reset
 * Features:
 * - Zet alle medewerkers op OUT (hoofdstatus)
 * - Reset individuele sub-statussen (BUTTON1, BUTTON2, BUTTON3)
 * - Zet alle bezoekers op OUT
 * - Uitgebreide statistieken dashboard
 * 
 * FIXES in v3.1:
 * - JavaScript counts object gebruikt nu button1/2/3_count
 * - Icons object gebruikt BUTTON1/2/3 keys
 * - Sub-status labels dynamisch opgehaald
 * 
 * ═══════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/auth_helper.php';
requireAdmin();
requireAdminFeature('manage_bulk_actions');

// Get employee counts by main status
$empStmt = $db->query("
    SELECT 
        COUNT(*) as total, 
        SUM(CASE WHEN status='IN' THEN 1 ELSE 0 END) as in_count, 
        SUM(CASE WHEN status='OUT' THEN 1 ELSE 0 END) as out_count 
    FROM employees 
    WHERE actief=1
");
$empStats = $empStmt->fetch(PDO::FETCH_ASSOC);

// Get employee counts by custom button status (stored in sub_status as BUTTON1/2/3)
$subStatusStmt = $db->query("
    SELECT 
        SUM(CASE WHEN sub_status='BUTTON1' THEN 1 ELSE 0 END) as button1_count,
        SUM(CASE WHEN sub_status='BUTTON2' THEN 1 ELSE 0 END) as button2_count,
        SUM(CASE WHEN sub_status='BUTTON3' THEN 1 ELSE 0 END) as button3_count
    FROM employees 
    WHERE actief=1
");
$subStats = $subStatusStmt->fetch(PDO::FETCH_ASSOC);

// Get button names from config
$configStmt = $db->query("SELECT button1_name, button2_name, button3_name FROM config WHERE id=1");
$buttonNames = $configStmt->fetch(PDO::FETCH_ASSOC);

// Get visitor count
$visitorCount = 0;
try {
    $visStmt = $db->query("SELECT COUNT(*) as count FROM visitors WHERE status='BINNEN'");
    $visitorCount = $visStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $visitorCount = 0;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Acties - PeopleDisplay</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f7fafc; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; padding: 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header h1 { font-size: 28px; color: #2d3748; margin-bottom: 8px; }
        .header p { color: #718096; font-size: 14px; }
        .back-link { color: #667eea; text-decoration: none; display: inline-block; margin-bottom: 12px; font-size: 14px; }
        .back-link:hover { text-decoration: underline; }
        
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); 
            gap: 16px; 
            margin-bottom: 30px; 
        }
        
        .stat-card { 
            background: white; 
            padding: 20px; 
            border-radius: 12px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #cbd5e0;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .stat-card.total { border-left-color: #667eea; }
        .stat-card.in { border-left-color: #48bb78; }
        .stat-card.out { border-left-color: #fc8181; }
        .stat-card.pauze { border-left-color: #ff69b4; }
        .stat-card.thuiswerken { border-left-color: #9370db; }
        .stat-card.vakantie { border-left-color: #9acd32; }
        .stat-card.visitors { border-left-color: #f093fb; }
        
        .stat-icon { font-size: 28px; margin-bottom: 8px; }
        .stat-value { font-size: 36px; font-weight: 700; color: #2d3748; margin-bottom: 6px; line-height: 1; }
        .stat-label { font-size: 12px; color: #718096; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        
        .section-title {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 16px;
            font-weight: 600;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .actions-grid {
            display: grid;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .action-box { 
            background: white; 
            padding: 24px; 
            border-radius: 12px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
        }
        
        .action-box h3 { 
            margin-bottom: 16px; 
            color: #2d3748;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .warning-box { 
            background: #fed7d7; 
            border: 2px solid #fc8181; 
            padding: 14px; 
            border-radius: 8px; 
            margin-bottom: 16px; 
            color: #c53030;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }
        
        .info-box {
            background: #e6fffa;
            border: 2px solid #81e6d9;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            color: #234e52;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .button-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .btn { 
            padding: 12px 24px; 
            border: none; 
            border-radius: 8px; 
            font-size: 14px; 
            font-weight: 600; 
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover { 
            transform: translateY(-2px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-main { 
            background: linear-gradient(135deg, #f56565 0%, #c53030 100%);
            color: white; 
            box-shadow: 0 4px 12px rgba(245, 101, 101, 0.3);
            flex: 1;
            min-width: 250px;
        }
        
        .btn-main:hover { 
            box-shadow: 0 6px 16px rgba(245, 101, 101, 0.4);
        }
        
        .btn-pauze {
            background: linear-gradient(135deg, #ff69b4 0%, #ff1493 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(255, 105, 180, 0.3);
        }
        
        .btn-thuiswerken {
            background: linear-gradient(135deg, #9370db 0%, #6a5acd 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(147, 112, 219, 0.3);
        }
        
        .btn-vakantie {
            background: linear-gradient(135deg, #9acd32 0%, #7cb305 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(154, 205, 50, 0.3);
        }
        
        .btn-visitors {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(240, 147, 251, 0.3);
        }
        
        .count-badge {
            background: rgba(255,255,255,0.3);
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
        }
        
        .success-message {
            margin-top: 16px;
            padding: 12px;
            background: #c6f6d5;
            border: 2px solid #48bb78;
            border-radius: 8px;
            color: #22543d;
            font-weight: 600;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-link">← Terug naar Dashboard</a>
            <h1>⚙️ Bulk Acties</h1>
            <p>Reset medewerker statussen en bezoekers in bulk</p>
        </div>
        
        <h2 class="section-title">📊 Huidige Statistieken</h2>
        
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo $empStats['total']; ?></div>
                <div class="stat-label">Totaal Actief</div>
            </div>
            
            <div class="stat-card in">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?php echo $empStats['in_count']; ?></div>
                <div class="stat-label">Status IN</div>
            </div>
            
            <div class="stat-card out">
                <div class="stat-icon">❌</div>
                <div class="stat-value"><?php echo $empStats['out_count']; ?></div>
                <div class="stat-label">Status OUT</div>
            </div>
            
            <div class="stat-card pauze">
                <div class="stat-icon">☕</div>
                <div class="stat-value"><?php echo $subStats['button1_count']; ?></div>
                <div class="stat-label">Knop 1</div>
            </div>
            
            <div class="stat-card thuiswerken">
                <div class="stat-icon">🏠</div>
                <div class="stat-value"><?php echo $subStats['button2_count']; ?></div>
                <div class="stat-label">Knop 2</div>
            </div>
            
            <div class="stat-card vakantie">
                <div class="stat-icon">🌴</div>
                <div class="stat-value"><?php echo $subStats['button3_count']; ?></div>
                <div class="stat-label">Knop 3</div>
            </div>
            
            <div class="stat-card visitors">
                <div class="stat-icon">🎫</div>
                <div class="stat-value"><?php echo $visitorCount; ?></div>
                <div class="stat-label">Bezoekers</div>
            </div>
        </div>
        
        <h2 class="section-title">⚙️ Bulk Reset Opties</h2>
        
        <div class="actions-grid">
            <!-- MAIN RESET -->
            <div class="action-box">
                <h3>🌙 Hoofdstatus Reset</h3>
                
                <div class="warning-box">
                    <span style="font-size: 20px;">⚠️</span>
                    <div>
                        <strong>Let op:</strong> Zet alle medewerkers op status OUT en verwijdert alle sub-statussen.
                    </div>
                </div>
                
                <div class="info-box">
                    💡 Deze actie wordt automatisch uitgevoerd om 23:00 via de cron job.
                </div>
                
                <button class="btn btn-main" onclick="resetMain()">
                    <span>🌙 Zet Alles op OUT</span>
                    <span class="count-badge"><?php echo $empStats['in_count']; ?> medewerkers</span>
                </button>
                
                <div id="success-main" class="success-message"></div>
            </div>
            
            <!-- SUB-STATUS RESETS -->
            <div class="action-box">
                <h3>🎯 Sub-Status Reset (Individueel)</h3>
                
                <div class="info-box">
                    💡 Reset alleen specifieke sub-statussen, zonder de hoofdstatus (IN/OUT) te wijzigen.
                </div>
                
                <div class="button-group">
                    <button class="btn btn-pauze" onclick="resetSubStatus('BUTTON1')" <?php if($subStats['button1_count'] == 0) echo 'disabled'; ?>>
                        <span>☕ Reset Knop 1</span>
                        <span class="count-badge"><?php echo $subStats['button1_count']; ?></span>
                    </button>
                    
                    <button class="btn btn-thuiswerken" onclick="resetSubStatus('BUTTON2')" <?php if($subStats['button2_count'] == 0) echo 'disabled'; ?>>
                        <span>🏠 Reset Knop 2</span>
                        <span class="count-badge"><?php echo $subStats['button2_count']; ?></span>
                    </button>
                    
                    <button class="btn btn-vakantie" onclick="resetSubStatus('BUTTON3')" <?php if($subStats['button3_count'] == 0) echo 'disabled'; ?>>
                        <span>🌴 Reset Knop 3</span>
                        <span class="count-badge"><?php echo $subStats['button3_count']; ?></span>
                    </button>
                </div>
                
                <div id="success-sub" class="success-message"></div>
            </div>
            
            <!-- VISITORS RESET -->
            <div class="action-box">
                <h3>🎫 Bezoekers Reset</h3>
                
                <div class="info-box">
                    💡 Zet alle bezoekers op status UIT (out-checked).
                </div>
                
                <button class="btn btn-visitors" onclick="resetVisitors()" <?php if($visitorCount == 0) echo 'disabled'; ?>>
                    <span>🎫 Zet Bezoekers op UIT</span>
                    <span class="count-badge"><?php echo $visitorCount; ?></span>
                </button>
                
                <div id="success-visitors" class="success-message"></div>
            </div>
        </div>
    </div>
    
    <script>
    // Configuration from PHP
    const buttonCounts = {
        'BUTTON1': <?php echo $subStats['button1_count']; ?>,
        'BUTTON2': <?php echo $subStats['button2_count']; ?>,
        'BUTTON3': <?php echo $subStats['button3_count']; ?>
    };
    
    const buttonNames = {
        'BUTTON1': <?php echo json_encode($buttonNames['button1_name'] ?? 'Knop 1'); ?>,
        'BUTTON2': <?php echo json_encode($buttonNames['button2_name'] ?? 'Knop 2'); ?>,
        'BUTTON3': <?php echo json_encode($buttonNames['button3_name'] ?? 'Knop 3'); ?>
    };
    
    const buttonIcons = {
        'BUTTON1': '☕',
        'BUTTON2': '🏠',
        'BUTTON3': '🌴'
    };
    
    // Main reset (status OUT + clear sub_status)
    function resetMain() {
        const count = <?php echo $empStats['in_count']; ?>;
        
        if (!confirm(`Weet je zeker dat je alle medewerkers op OUT wilt zetten?\n\nDit beïnvloedt ${count} medewerkers en verwijdert alle sub-statussen.`)) {
            return;
        }
        
        const btn = document.querySelector('.btn-main');
        btn.disabled = true;
        btn.innerHTML = '<span>⏳ Bezig met resetten...</span>';
        
        fetch('api/bulk_actions_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=set_all_out'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showSuccess('success-main', data.message);
                setTimeout(() => location.reload(), 2000);
            } else {
                alert('❌ Fout: ' + data.error);
                btn.disabled = false;
                btn.innerHTML = `<span>🌙 Zet Alles op OUT</span><span class="count-badge">${count} medewerkers</span>`;
            }
        })
        .catch(err => {
            alert('❌ Fout: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = `<span>🌙 Zet Alles op OUT</span><span class="count-badge">${count} medewerkers</span>`;
        });
    }
    
    // Sub-status reset
    function resetSubStatus(buttonId) {
        const count = buttonCounts[buttonId];
        const name = buttonNames[buttonId];
        const icon = buttonIcons[buttonId];
        
        if (!confirm(`Weet je zeker dat je alle "${name}" sub-statussen wilt resetten?\n\nDit beïnvloedt ${count} medewerkers.\n\nDe hoofdstatus (IN/OUT) blijft ongewijzigd.`)) {
            return;
        }
        
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span>⏳ Bezig...</span>';
        
        fetch('api/bulk_actions_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=reset_sub_status&sub_status=${buttonId}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showSuccess('success-sub', `${icon} ${data.message}`);
                setTimeout(() => location.reload(), 2000);
            } else {
                alert('❌ Fout: ' + data.error);
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        })
        .catch(err => {
            alert('❌ Fout: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        });
    }
    
    // Visitors reset
    function resetVisitors() {
        const count = <?php echo $visitorCount; ?>;
        
        if (!confirm(`Weet je zeker dat je alle bezoekers wilt uit-checken?\n\nDit beïnvloedt ${count} bezoekers.`)) {
            return;
        }
        
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span>⏳ Bezig...</span>';
        
        fetch('api/bulk_actions_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=reset_visitors'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showSuccess('success-visitors', data.message);
                setTimeout(() => location.reload(), 2000);
            } else {
                alert('❌ Fout: ' + data.error);
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        })
        .catch(err => {
            alert('❌ Fout: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        });
    }
    
    function showSuccess(elementId, message) {
        const el = document.getElementById(elementId);
        el.textContent = '✅ ' + message;
        el.style.display = 'block';
    }
    </script>
</body>
</html>
