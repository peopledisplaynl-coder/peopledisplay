<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: locations_manage.php
 * LOCATIE:      /admin/
 * UPLOAD NAAR:  /admin/locations_manage.php
 * VERSIE:       2.0 - Met WiFi IP Auto Check-in
 * ═══════════════════════════════════════════════════════════════════
 */

session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','superadmin'], true)) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/license_check.php';

$message = '';
if (!empty($_SESSION['pd_flash'])) {
    $message = $_SESSION['pd_flash'];
    unset($_SESSION['pd_flash']);
}
$error      = '';
$limitAlert = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            // Check license limit
            if (!canAddLocation()) {
                $limits = getTierLimits();
                $limitAlert = getLimitExceededMessage('locations', $limits['max_locations']);
            } else {
            // Create new location
            $stmt = $db->prepare("
                INSERT INTO locations (
                    location_name, location_code, address, 
                    primary_ip, backup_ip, ip_range_start, ip_range_end,
                    auto_checkin_enabled, active, sort_order
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 
                    (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM locations l)
                )
            ");
            
            $stmt->execute([
                $_POST['location_name'],
                $_POST['location_code'] ?? null,
                $_POST['address'] ?? null,
                $_POST['primary_ip'] ?? null,
                $_POST['backup_ip'] ?? null,
                $_POST['ip_range_start'] ?? null,
                $_POST['ip_range_end'] ?? null,
                isset($_POST['auto_checkin_enabled']) ? 1 : 0
            ]);
            
            $_SESSION['pd_flash'] = '✅ Locatie toegevoegd!';
            header('Location: locations_manage.php?t=' . time());
            exit;
            } // end canAddLocation else

        } elseif ($action === 'update') {
            // Haal oude naam op VOOR de update
            $stmt_old = $db->prepare("SELECT location_name FROM locations WHERE id = ?");
            $stmt_old->execute([$_POST['id']]);
            $oude_naam = $stmt_old->fetchColumn();

            // Update de locatie zelf
            $stmt = $db->prepare("
                UPDATE locations SET
                    location_name = ?,
                    location_code = ?,
                    address = ?,
                    primary_ip = ?,
                    backup_ip = ?,
                    ip_range_start = ?,
                    ip_range_end = ?,
                    auto_checkin_enabled = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $_POST['location_name'],
                $_POST['location_code'] ?? null,
                $_POST['address'] ?? null,
                $_POST['primary_ip'] ?? null,
                $_POST['backup_ip'] ?? null,
                $_POST['ip_range_start'] ?? null,
                $_POST['ip_range_end'] ?? null,
                isset($_POST['auto_checkin_enabled']) ? 1 : 0,
                $_POST['id']
            ]);

            // Cascade: medewerkers automatisch meenemen
            $nieuwe_naam = $_POST['location_name'];
            $stmt2 = $db->prepare("UPDATE employees SET locatie = ? WHERE locatie = ?");
            $stmt2->execute([$nieuwe_naam, $oude_naam]);
            $bijgewerkt = $stmt2->rowCount();

            $extra = $bijgewerkt > 0 ? " ($bijgewerkt medewerker(s) automatisch bijgewerkt)" : "";
            $_SESSION['pd_flash'] = "✅ Locatie bijgewerkt!$extra";
            header('Location: locations_manage.php?t=' . time());
            exit;
            
        } elseif ($action === 'delete') {
            // Soft delete
            $stmt = $db->prepare("UPDATE locations SET active = 0 WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            
            $_SESSION['pd_flash'] = '✅ Locatie verwijderd!';
            header('Location: locations_manage.php?t=' . time());
            exit;
            
        } elseif ($action === 'test_ip') {
            // Test current IP
            $currentIP = $_SERVER['REMOTE_ADDR'];
            $stmt = $db->prepare("
                SELECT location_name
                FROM locations
                WHERE active = 1
                  AND (primary_ip = ? OR backup_ip = ?)
                LIMIT 1
            ");
            $stmt->execute([$currentIP, $currentIP]);
            $match = $stmt->fetch();
            
            if ($match) {
                $message = "✅ Huidig IP ($currentIP) matched met: " . $match['location_name'];
            } else {
                $message = "⚠️ Huidig IP ($currentIP) niet geconfigureerd";
            }
        }
        
    } catch (Exception $e) {
        $error = '❌ Fout: ' . $e->getMessage();
    }
}

// Get all locations
$stmt = $db->query("
    SELECT * FROM locations
    WHERE active = 1
    ORDER BY sort_order ASC, location_name ASC
");
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locaties Beheren - PeopleDisplay</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { color: #2c3e50; margin-bottom: 20px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #3498db; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
        
        .inline-add-container { margin-bottom: 20px; }
        .toggle-add-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            font-size: 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            transition: all 0.3s;
            width: 100%;
            text-align: left;
            font-weight: 600;
        }
        .toggle-add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .inline-add-form {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            border-radius: 8px;
            margin-top: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .inline-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: white;
            font-size: 13px;
        }
        
        .form-field input, .form-field select {
            width: 100%;
            padding: 10px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 6px;
            font-size: 14px;
            background: rgba(255,255,255,0.95);
            transition: all 0.2s;
        }
        
        .form-field input:focus {
            outline: none;
            border-color: white;
            background: white;
            box-shadow: 0 0 0 3px rgba(255,255,255,0.2);
        }
        
        .form-section {
            border-top: 2px solid rgba(255,255,255,0.3);
            margin-top: 20px;
            padding-top: 20px;
        }
        
        .form-section-title {
            color: white;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-field {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: rgba(255,255,255,0.1);
            border-radius: 6px;
        }
        
        .checkbox-field input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .checkbox-field label {
            margin: 0;
            cursor: pointer;
        }
        
        .inline-form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .inline-form-actions button {
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-secondary {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid white;
        }
        
        .btn-secondary:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .btn-test {
            background: #f39c12;
            color: white;
        }
        
        .btn-test:hover {
            background: #e67e22;
        }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #3498db; color: white; font-weight: 600; }
        tr:hover { background: #f8f9fa; }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-enabled {
            background: #27ae60;
            color: white;
        }
        
        .badge-disabled {
            background: #95a5a6;
            color: white;
        }
        
        .ip-display {
            font-family: monospace;
            font-size: 12px;
            color: #666;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            margin-right: 5px;
            transition: all 0.2s;
        }
        
        .btn-edit {
            background: #3498db;
            color: white;
        }
        
        .btn-edit:hover {
            background: #2980b9;
        }
        
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c0392b;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .info-box h3 {
            margin-bottom: 8px;
            color: #1976d2;
        }
        
        .info-box ul {
            margin-left: 20px;
            color: #555;
        }
        
        .edit-row {
            display: none;
            background: #f8f9fa !important;
        }
        
        .edit-form {
            padding: 20px;
        }
        
        small {
            display: block;
            color: rgba(255,255,255,0.8);
            font-size: 11px;
            margin-top: 3px;
        }

        .license-limit-alert {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 20px;
            margin: 20px 0;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        .license-limit-alert .alert-icon { font-size: 48px; opacity: 0.9; }
        .license-limit-alert .alert-content { flex: 1; }
        .license-limit-alert .alert-content h3 { margin: 0 0 10px 0; font-size: 20px; }
        .license-limit-alert .alert-content p { margin: 5px 0; opacity: 0.95; }
        .license-limit-alert .alert-actions { flex-shrink: 0; }
        .btn-upgrade {
            background: white;
            color: #667eea;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            white-space: nowrap;
            transition: transform 0.2s;
            display: inline-block;
        }
        .btn-upgrade:hover { transform: scale(1.05); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Terug naar Dashboard</a>
        
        <h1>📍 Locaties Beheren</h1>
        
        <?php if ($message): ?>
            <div class="success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($limitAlert): ?>
            <div class="license-limit-alert">
                <div class="alert-icon"><?= $limitAlert['icon'] ?></div>
                <div class="alert-content">
                    <h3><?= $limitAlert['title'] ?></h3>
                    <p><?= $limitAlert['message'] ?></p>
                    <p><?= $limitAlert['upgradeMessage'] ?></p>
                </div>
                <div class="alert-actions">
                    <a href="license_management.php" class="btn-upgrade">Upgrade Pakket</a>
                </div>
            </div>
        <?php elseif ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>💡 WiFi Auto Check-in</h3>
            <p>Configureer het externe IP-adres van elke locatie voor automatische check-in via WiFi detectie.</p>
            <ul style="margin-top: 10px;">
                <li><strong>Primary IP:</strong> Hoofd extern IP adres van de locatie</li>
                <li><strong>Backup IP:</strong> Alternatief IP (optioneel)</li>
                <li><strong>IP Range:</strong> Voor DHCP pools (optioneel)</li>
                <li><strong>Auto Check-in:</strong> Schakel in om PWA app automatisch check-in te laten voorstellen</li>
            </ul>
        </div>
        
        <!-- ADD FORM -->
        <div class="inline-add-container">
            <button onclick="toggleAddForm()" class="toggle-add-btn" id="toggleBtn">
                ➕ Nieuwe Locatie Toevoegen
            </button>
            
            <div id="inlineAddForm" class="inline-add-form" style="display: none;">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-section-title">📍 Basis Informatie</div>
                    <div class="inline-form-grid">
                        <div class="form-field">
                            <label>Locatie Naam *</label>
                            <input type="text" name="location_name" required placeholder="BSO De Vlinder">
                        </div>
                        
                        <div class="form-field">
                            <label>Locatie Code</label>
                            <input type="text" name="location_code" placeholder="BSO-VL">
                            <small>Optioneel: Korte code voor intern gebruik</small>
                        </div>
                        
                        <div class="form-field" style="grid-column: 1 / -1;">
                            <label>Adres</label>
                            <input type="text" name="address" placeholder="Hoofdstraat 123, 1234 AB Plaats">
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="form-section-title">🌐 WiFi IP Configuratie</div>
                        <div class="inline-form-grid">
                            <div class="form-field">
                                <label>Primary IP Adres</label>
                                <input type="text" name="primary_ip" placeholder="82.123.45.1" pattern="^(\d{1,3}\.){3}\d{1,3}$">
                                <small>Extern IP adres van locatie WiFi</small>
                            </div>
                            
                            <div class="form-field">
                                <label>Backup IP Adres</label>
                                <input type="text" name="backup_ip" placeholder="82.123.45.2" pattern="^(\d{1,3}\.){3}\d{1,3}$">
                                <small>Alternatief IP (optioneel)</small>
                            </div>
                            
                            <div class="form-field">
                                <label>IP Range Start</label>
                                <input type="text" name="ip_range_start" placeholder="82.123.45.1" pattern="^(\d{1,3}\.){3}\d{1,3}$">
                                <small>Voor DHCP pools (optioneel)</small>
                            </div>
                            
                            <div class="form-field">
                                <label>IP Range Eind</label>
                                <input type="text" name="ip_range_end" placeholder="82.123.45.10" pattern="^(\d{1,3}\.){3}\d{1,3}$">
                                <small>Eind van IP range (optioneel)</small>
                            </div>
                        </div>
                        
                        <div class="checkbox-field">
                            <input type="checkbox" name="auto_checkin_enabled" id="auto_checkin_create">
                            <label for="auto_checkin_create">✅ Auto Check-in inschakelen (PWA app stelt check-in voor bij detectie)</label>
                        </div>
                    </div>
                    
                    <div class="inline-form-actions">
                        <button type="submit" class="btn-success">✓ Opslaan</button>
                        <button type="button" onclick="toggleAddForm()" class="btn-secondary">Annuleren</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- TEST BUTTON -->
        <div class="card" style="padding: 15px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong>🔍 Test Huidig IP:</strong>
                <span class="ip-display"><?= htmlspecialchars($_SERVER['REMOTE_ADDR']) ?></span>
            </div>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="test_ip">
                <button type="submit" class="btn-test">Test IP Match</button>
            </form>
        </div>
        
        <!-- LOCATIONS LIST -->
        <div class="card">
            <h2 style="margin-bottom: 20px;">📋 Locaties (<?= count($locations) ?>)</h2>
            
            <?php if (empty($locations)): ?>
                <p style="text-align: center; color: #999; padding: 40px;">
                    Nog geen locaties. Voeg de eerste locatie toe!
                </p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Locatie</th>
                            <th>Code</th>
                            <th>IP Configuratie</th>
                            <th>Auto Check-in</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locations as $loc): ?>
                            <tr id="row-<?= $loc['id'] ?>">
                                <td>
                                    <strong><?= htmlspecialchars($loc['location_name']) ?></strong>
                                    <?php if ($loc['address']): ?>
                                        <br><small style="color: #666;"><?= htmlspecialchars($loc['address']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($loc['location_code'] ?? '-') ?></td>
                                <td>
                                    <?php if ($loc['primary_ip']): ?>
                                        <span class="ip-display">
                                            📡 <?= htmlspecialchars($loc['primary_ip']) ?>
                                            <?php if ($loc['backup_ip']): ?>
                                                <br>🔄 <?= htmlspecialchars($loc['backup_ip']) ?>
                                            <?php endif; ?>
                                            <?php if ($loc['ip_range_start'] && $loc['ip_range_end']): ?>
                                                <br>📊 <?= htmlspecialchars($loc['ip_range_start']) ?> - <?= htmlspecialchars($loc['ip_range_end']) ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999;">Niet geconfigureerd</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($loc['auto_checkin_enabled']): ?>
                                        <span class="badge badge-enabled">✅ Enabled</span>
                                    <?php else: ?>
                                        <span class="badge badge-disabled">⏸️ Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button onclick="editLocation(<?= $loc['id'] ?>)" class="btn-small btn-edit">✏️ Bewerken</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Weet je zeker dat je deze locatie wilt verwijderen?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $loc['id'] ?>">
                                        <button type="submit" class="btn-small btn-delete">🗑️ Verwijderen</button>
                                    </form>
                                </td>
                            </tr>
                            
                            <!-- EDIT ROW (Hidden, direct onder deze locatie) -->
                            <tr id="edit-row-<?= $loc['id'] ?>" class="edit-row">
                                <td colspan="5">
                                    <div class="edit-form">
                                        <h3 style="margin: 0 0 15px 0; color: #2c3e50;">✏️ <?= htmlspecialchars($loc['location_name']) ?> bewerken</h3>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="id" value="<?= $loc['id'] ?>">
                                            
                                            <div class="inline-form-grid">
                                                <div class="form-field">
                                                    <label style="color: #333; font-weight: 600;">Locatie Naam *</label>
                                                    <input type="text" name="location_name" value="<?= htmlspecialchars($loc['location_name']) ?>" required style="background: white; border: 2px solid #ddd;">
                                                </div>
                                                
                                                <div class="form-field">
                                                    <label style="color: #333; font-weight: 600;">Locatie Code</label>
                                                    <input type="text" name="location_code" value="<?= htmlspecialchars($loc['location_code'] ?? '') ?>" placeholder="BSO-VL" style="background: white; border: 2px solid #ddd;">
                                                </div>
                                                
                                                <div class="form-field" style="grid-column: 1 / -1;">
                                                    <label style="color: #333; font-weight: 600;">Adres</label>
                                                    <input type="text" name="address" value="<?= htmlspecialchars($loc['address'] ?? '') ?>" placeholder="Hoofdstraat 123" style="background: white; border: 2px solid #ddd;">
                                                </div>
                                                
                                                <div class="form-field">
                                                    <label style="color: #333; font-weight: 600;">Primary IP Adres</label>
                                                    <input type="text" name="primary_ip" value="<?= htmlspecialchars($loc['primary_ip'] ?? '') ?>" pattern="^(\d{1,3}\.){3}\d{1,3}$" placeholder="82.123.45.1" style="background: white; border: 2px solid #ddd;">
                                                    <small style="color: #666;">Extern IP van locatie WiFi</small>
                                                </div>
                                                
                                                <div class="form-field">
                                                    <label style="color: #333; font-weight: 600;">Backup IP Adres</label>
                                                    <input type="text" name="backup_ip" value="<?= htmlspecialchars($loc['backup_ip'] ?? '') ?>" pattern="^(\d{1,3}\.){3}\d{1,3}$" placeholder="82.123.45.2" style="background: white; border: 2px solid #ddd;">
                                                    <small style="color: #666;">Alternatief IP (optioneel)</small>
                                                </div>
                                                
                                                <div class="form-field">
                                                    <label style="color: #333; font-weight: 600;">IP Range Start</label>
                                                    <input type="text" name="ip_range_start" value="<?= htmlspecialchars($loc['ip_range_start'] ?? '') ?>" pattern="^(\d{1,3}\.){3}\d{1,3}$" placeholder="82.123.45.1" style="background: white; border: 2px solid #ddd;">
                                                    <small style="color: #666;">Voor DHCP pools (optioneel)</small>
                                                </div>
                                                
                                                <div class="form-field">
                                                    <label style="color: #333; font-weight: 600;">IP Range Eind</label>
                                                    <input type="text" name="ip_range_end" value="<?= htmlspecialchars($loc['ip_range_end'] ?? '') ?>" pattern="^(\d{1,3}\.){3}\d{1,3}$" placeholder="82.123.45.10" style="background: white; border: 2px solid #ddd;">
                                                    <small style="color: #666;">Eind van range (optioneel)</small>
                                                </div>
                                            </div>
                                            
                                            <div class="checkbox-field" style="background: #e3f2fd; margin: 15px 0;">
                                                <input type="checkbox" name="auto_checkin_enabled" id="auto_checkin_<?= $loc['id'] ?>" <?= $loc['auto_checkin_enabled'] ? 'checked' : '' ?>>
                                                <label for="auto_checkin_<?= $loc['id'] ?>" style="color: #333;">✅ Auto Check-in inschakelen</label>
                                            </div>
                                            
                                            <div style="display: flex; gap: 10px;">
                                                <button type="submit" class="btn-small btn-success">✓ Opslaan</button>
                                                <button type="button" onclick="cancelEdit(<?= $loc['id'] ?>)" class="btn-small btn-secondary" style="background: #95a5a6; color: white;">✕ Annuleren</button>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleAddForm() {
            const form = document.getElementById('inlineAddForm');
            const btn = document.getElementById('toggleBtn');
            
            if (form.style.display === 'none') {
                form.style.display = 'block';
                btn.textContent = '✕ Annuleren';
                btn.style.background = 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)';
            } else {
                form.style.display = 'none';
                btn.textContent = '➕ Nieuwe Locatie Toevoegen';
                btn.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            }
        }
        
        function editLocation(id) {
            // Hide all edit rows
            document.querySelectorAll('.edit-row').forEach(row => {
                row.style.display = 'none';
            });
            
            // Show this edit row
            const row = document.getElementById('edit-row-' + id);
            row.style.display = 'table-row';
        }
        
        function cancelEdit(id) {
            const row = document.getElementById('edit-row-' + id);
            row.style.display = 'none';
        }
    </script>
</body>
</html>
