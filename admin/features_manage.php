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
 * BESTANDSNAAM: features_manage.php
 * LOCATIE:      /admin/features_manage.php
 * VERSIE:       2.1 - Met Sorteer Toggle Feature
 * 
 * BELANGRIJK:
 * - Voornaam en Achternaam zijn APARTE checkboxes
 * - "Naam" checkbox is VERWIJDERD
 * - 🔄 NIEUW: Sorteer Toggle Feature toegevoegd
 * ═══════════════════════════════════════════════════════════════════
 */

session_start();

// CRITICAL: NO CACHE HEADERS
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/license_check.php';
require_once __DIR__ . '/auth_helper.php';

requireAdmin();

$message = '';
$error = '';
$justSaved = false;

// Save features
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_features'])) {
    $user_id = intval($_POST['user_id']);
    
    // Visible fields
    $visibleFields = $_POST['visible_fields'] ?? [];
    
    // Extra buttons
    $extraButtons = [
        'PAUZE' => isset($_POST['btn_pauze']),
        'THUISWERKEN' => isset($_POST['btn_thuiswerken']),
        'VAKANTIE' => isset($_POST['btn_vakantie'])
    ];
    
    // Locations
    $locations = $_POST['locations'] ?? [];
    
    // ═══════════════════════════════════════════════════════════════
    // ✨ NIEUW: Sorteer Toggle Feature
    // ═══════════════════════════════════════════════════════════════
    $canToggleSort = isset($_POST['feature_canToggleSort']);
    
    // Build features JSON
    $features = json_encode([
        'visibleFields' => $visibleFields,
        'extraButtons' => $extraButtons,
        'locations' => $locations,
        'canToggleSort' => $canToggleSort  // ← NIEUW!
    ]);
    
    // Update user (alleen features, geen presentation)
    $stmt = $db->prepare("UPDATE users SET features = ? WHERE id = ?");
    if ($stmt->execute([$features, $user_id])) {
        $justSaved = true;
        $message = "✅ Features opgeslagen voor gebruiker";
    } else {
        $error = "❌ Fout bij opslaan";
    }
}

// Get all users
$users = $db->query("SELECT id, username, display_name, role FROM users WHERE active = 1 ORDER BY username")->fetchAll();

// Get all locations
$locations = $db->query("SELECT location_name FROM locations WHERE active = 1 ORDER BY sort_order, location_name")->fetchAll(PDO::FETCH_COLUMN);

// Selected user
$selected_user_id = $_GET['user_id'] ?? ($_POST['user_id'] ?? null);
$selected_user = null;
$current_features = [
    'visibleFields' => [],
    'extraButtons' => ['PAUZE' => false, 'THUISWERKEN' => false, 'VAKANTIE' => false],
    'locations' => [],
    'canToggleSort' => false  // ← NIEUW!
];

if ($selected_user_id) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$selected_user_id]);
    $selected_user = $stmt->fetch();
    
    if ($selected_user) {
        // ✅ FIXED: Better JSON handling met error checking
        if (!empty($selected_user['features'])) {
            $decoded = json_decode($selected_user['features'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Merge met defaults om ontbrekende keys te voorkomen
                $current_features['visibleFields'] = $decoded['visibleFields'] ?? [];
                $current_features['extraButtons'] = array_merge(
                    $current_features['extraButtons'],
                    $decoded['extraButtons'] ?? []
                );
                $current_features['locations'] = $decoded['locations'] ?? [];
                $current_features['canToggleSort'] = $decoded['canToggleSort'] ?? false;  // ← NIEUW!
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Features Beheren</title>
    <?php if ($justSaved && $selected_user_id): ?>
    <meta http-equiv="refresh" content="0;url=<?php echo $_SERVER['PHP_SELF']; ?>?user_id=<?php echo $selected_user_id; ?>&saved=1&t=<?php echo time(); ?>">
    <?php endif; ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui; background: #f7fafc; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .btn-back { background: #718096; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; }
        .section { background: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; }
        .message { padding: 12px 20px; border-radius: 6px; margin-bottom: 20px; }
        .message.success { background: #c6f6d5; color: #22543d; }
        .message.error { background: #fed7d7; color: #c53030; }
        .debug { background: #edf2f7; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-family: monospace; font-size: 12px; }
        select, button { padding: 10px; font-size: 14px; border: 1px solid #cbd5e0; border-radius: 6px; }
        select { width: 300px; margin-bottom: 20px; }
        button.btn-primary { background: #4299e1; color: white; border: none; cursor: pointer; padding: 12px 24px; font-weight: 600; }
        button.btn-primary:hover { background: #3182ce; }
        .checkbox-group { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin: 15px 0; }
        .checkbox-item { display: flex; align-items: center; gap: 8px; }
        .checkbox-item input { width: 18px; height: 18px; cursor: pointer; }
        .checkbox-item label { cursor: pointer; font-size: 14px; }
        h3 { margin-bottom: 15px; color: #2d3748; font-size: 16px; }
        .form-section { margin-bottom: 30px; }
        
        /* ═══════════════════════════════════════════════════════════════
           ✨ NIEUW: Feature Item Styling voor Sorteer Toggle
           ═══════════════════════════════════════════════════════════════ */
        .feature-item {
            margin-bottom: 12px;
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            transition: background-color 0.2s;
        }
        
        .feature-item:hover {
            background-color: #f9f9f9;
        }
        
        .feature-checkbox {
            display: flex;
            align-items: flex-start;
            cursor: pointer;
        }
        
        .feature-checkbox input[type="checkbox"] {
            margin-right: 12px;
            margin-top: 2px;
            cursor: pointer;
            width: 18px;
            height: 18px;
        }
        
        .feature-label {
            display: flex;
            flex-direction: column;
        }
        
        .feature-label strong {
            color: #333;
            font-size: 15px;
            margin-bottom: 4px;
        }
        
        .feature-description {
            color: #666;
            font-size: 13px;
            line-height: 1.4;
        }
    </style>
    <script>
        // ⚠️ FIX: Force reload on back button (bfcache prevention)
        window.addEventListener('pageshow', function(event) {
            if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                console.log('🔄 Features page loaded from cache - forcing reload');
                window.location.reload();
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <?php if ($selected_user && isset($_GET['debug'])): ?>
        <div class="debug">
            <strong>🐛 DEBUG INFO:</strong><br>
            User ID: <?= $selected_user['id'] ?><br>
            Username: <?= htmlspecialchars($selected_user['username']) ?><br>
            Features Raw: <?= htmlspecialchars(substr($selected_user['features'] ?? 'NULL', 0, 200)) ?><br>
            JSON Valid: <?= empty($selected_user['features']) ? 'EMPTY' : (json_last_error() === JSON_ERROR_NONE ? 'YES' : 'NO - Error: '.json_last_error_msg()) ?><br>
            Visible Fields: <?= count($current_features['visibleFields']) ?><br>
            Extra Buttons: <?= json_encode($current_features['extraButtons']) ?><br>
            Locations: <?= count($current_features['locations']) ?><br>
            Can Toggle Sort: <?= $current_features['canToggleSort'] ? 'YES' : 'NO' ?>
        </div>
        <?php endif; ?>
        
        <div class="header">
            <a href="dashboard.php" class="btn-back">← Terug</a>
            <h1 style="margin: 15px 0 0 0;">🎨 Features Beheren</h1>
        </div>
        
        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php elseif (isset($_GET['saved'])): ?>
            <div class="message success">✅ Features opgeslagen!</div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="section">
            <h3>Selecteer Gebruiker</h3>
            <form method="GET">
                <select name="user_id" onchange="this.form.submit()">
                    <option value="">-- Kies gebruiker --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= $selected_user_id == $user['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['display_name'] ?: $user['username']) ?> (<?= $user['role'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        
        <?php if ($selected_user): ?>
        <form method="POST">
            <input type="hidden" name="user_id" value="<?= $selected_user['id'] ?>">
            
            <div class="section">
                <div class="form-section">
                    <h3>👁️ Zichtbare Velden</h3>
                    <div class="checkbox-group">
                        <?php
                        $fields = ['Foto', 'Voornaam', 'Achternaam', 'Functie', 'Afdeling', 'Locatie', 'Status', 'BHV', 'Tijdstip'];
                        foreach ($fields as $field):
                            $checked = in_array($field, $current_features['visibleFields']) ? 'checked' : '';
                        ?>
                            <div class="checkbox-item">
                                <input type="checkbox" id="field_<?= $field ?>" name="visible_fields[]" value="<?= $field ?>" <?= $checked ?>>
                                <label for="field_<?= $field ?>"><?= $field ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>🎨 Extra Knoppen</h3>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="btn_pauze" name="btn_pauze" <?= ($current_features['extraButtons']['PAUZE'] ?? false) ? 'checked' : '' ?>>
                            <label for="btn_pauze">🌸 PAUZE</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="btn_thuiswerken" name="btn_thuiswerken" <?= ($current_features['extraButtons']['THUISWERKEN'] ?? false) ? 'checked' : '' ?>>
                            <label for="btn_thuiswerken">💜 THUISWERKEN</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="btn_vakantie" name="btn_vakantie" <?= ($current_features['extraButtons']['VAKANTIE'] ?? false) ? 'checked' : '' ?>>
                            <label for="btn_vakantie">🌿 VAKANTIE</label>
                        </div>
                    </div>
                </div>
                
                <!-- ═══════════════════════════════════════════════════════════════
                     ✨ NIEUW: Sorteer Toggle Feature
                     ═══════════════════════════════════════════════════════════════ -->
                <div class="form-section">
                    <h3>⚙️ Extra Functies</h3>
                    <div class="feature-item">
                        <label class="feature-checkbox">
                            <input type="checkbox" 
                                   id="feature_canToggleSort" 
                                   name="feature_canToggleSort" 
                                   value="1"
                                   <?= $current_features['canToggleSort'] ? 'checked' : '' ?>>
                            <span class="feature-label">
                                <strong>🔄 Sorteer Toggle Knop</strong>
                                <small class="feature-description">
                                    User kan zelf switchen tussen voornaam/achternaam sortering via header knop
                                </small>
                            </span>
                        </label>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>📍 Zichtbare Locaties</h3>
                    <div class="checkbox-group">
                        <?php foreach ($locations as $loc): 
                            $checked = in_array($loc, $current_features['locations']) ? 'checked' : '';
                        ?>
                            <div class="checkbox-item">
                                <input type="checkbox" id="loc_<?= md5($loc) ?>" name="locations[]" value="<?= htmlspecialchars($loc) ?>" <?= $checked ?>>
                                <label for="loc_<?= md5($loc) ?>"><?= htmlspecialchars($loc) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <button type="submit" name="save_features" class="btn-primary">💾 Features Opslaan</button>
            </div>
        </form>
        <?php else: ?>
        <div class="section">
            <p style="color: #718096;">Selecteer een gebruiker om features te beheren.</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
