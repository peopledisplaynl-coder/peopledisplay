<?php
// 🔄 CACHE BUSTER - Force reload
opcache_invalidate(__FILE__, true);
/**
 * BESTANDSNAAM: employees_manage.php
 * LOCATIE: /admin/employees_manage.php
 * VERSIE: 2.4 - Afdelingen fix + Cache buster
 */

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/license_check.php';
require_once 'auth_helper.php';
requireAdmin();

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
    
    if ($action === 'create') {
        // Check license limit before proceeding with creation
        if (!canAddEmployee()) {
            $limits = getTierLimits();
            $limitAlert = getLimitExceededMessage('employees', $limits['max_employees']);
        } else {
        $employee_id = 'EMP' . time() . rand(100, 999);
        $voornaam = trim($_POST['voornaam']);
        $achternaam = trim($_POST['achternaam']);
        $email = trim($_POST['email'] ?? '');
        $telefoon = trim($_POST['telefoon'] ?? '');
        $functie = trim($_POST['functie'] ?? '');
        $bhv = isset($_POST['bhv']) ? 'Ja' : 'Nee';
        $afdeling = trim($_POST['afdeling']);
        $locatie = trim($_POST['locatie']);
        $foto_url = trim($_POST['foto_url'] ?? '');
        $notities = trim($_POST['notities'] ?? '');
        
        // Create display naam from voornaam + achternaam
$naam = trim($voornaam . ' ' . $achternaam);
        
        if (empty($voornaam) || empty($achternaam) || empty($afdeling) || empty($locatie)) {
            $error = 'Vul alle verplichte velden in';
        } else {
            try {
               // 🆕 Process visible_locations
$visible_locations = [];
if (isset($_POST['visible_all_locations']) && $_POST['visible_all_locations'] == '1') {
    $visible_locations = ['ALL'];
} elseif (isset($_POST['visible_locations']) && is_array($_POST['visible_locations'])) {
    $visible_locations = array_map('intval', $_POST['visible_locations']);
}
$visible_locations_json = json_encode($visible_locations);

// 🆕 Process allow_manual_location_change
$allow_manual_location_change = isset($_POST['allow_manual_location_change']) && $_POST['allow_manual_location_change'] == '1' ? 1 : 0;

$stmt = $db->prepare("
    INSERT INTO employees (
        employee_id, naam, voornaam, achternaam, 
        email, telefoon, functie, bhv, afdeling, locatie,
        foto_url, notities, status, actief, visible_locations, allow_manual_location_change
    ) VALUES (
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, 'OUT', 1, ?, ?
    )
");

$stmt->execute([
    $employee_id, $naam, $voornaam, $achternaam,
    $email, $telefoon, $functie, $bhv, $afdeling, $locatie,
    $foto_url, $notities, $visible_locations_json, $allow_manual_location_change
]);
                
                $_SESSION['pd_flash'] = "Medewerker succesvol toegevoegd!";
                header('Location: employees_manage.php');
                exit;
            } catch (PDOException $e) {
                $error = "Fout bij toevoegen: " . $e->getMessage();
            }
        }
        } // end canAddEmployee check
    }

    if ($action === 'update') {
        $id = $_POST['id'];
        $voornaam = trim($_POST['voornaam']);
        $achternaam = trim($_POST['achternaam']);
        $email = trim($_POST['email'] ?? '');
        $telefoon = trim($_POST['telefoon'] ?? '');
        $functie = trim($_POST['functie'] ?? '');
        $bhv = isset($_POST['bhv']) ? 'Ja' : 'Nee';
        $afdeling = trim($_POST['afdeling']);
        $locatie = trim($_POST['locatie']);
        $foto_url = trim($_POST['foto_url'] ?? '');
        $notities = trim($_POST['notities'] ?? '');
        
        // Update display naam
$naam = trim($voornaam . ' ' . $achternaam);
        
        if (empty($voornaam) || empty($achternaam) || empty($afdeling) || empty($locatie)) {
            $error = 'Vul alle verplichte velden in';
        } else {
            try {
                // 🆕 Process visible_locations
$visible_locations = [];
if (isset($_POST['visible_all_locations']) && $_POST['visible_all_locations'] == '1') {
    $visible_locations = ['ALL'];
} elseif (isset($_POST['visible_locations']) && is_array($_POST['visible_locations'])) {
    $visible_locations = array_map('intval', $_POST['visible_locations']);
}
$visible_locations_json = json_encode($visible_locations);

// 🆕 Process allow_manual_location_change
$allow_manual_location_change = isset($_POST['allow_manual_location_change']) && $_POST['allow_manual_location_change'] == '1' ? 1 : 0;

$stmt = $db->prepare("
    UPDATE employees SET
        naam = ?, voornaam = ?, achternaam = ?,
        email = ?, telefoon = ?, functie = ?,
        bhv = ?, afdeling = ?, locatie = ?,
        foto_url = ?, notities = ?, visible_locations = ?, allow_manual_location_change = ?
    WHERE id = ?
");

$stmt->execute([
    $naam, $voornaam, $achternaam,
    $email, $telefoon, $functie,
    $bhv, $afdeling, $locatie,
    $foto_url, $notities, $visible_locations_json, $allow_manual_location_change, $id
]);
                
                $_SESSION['pd_flash'] = "Medewerker bijgewerkt!";
                header('Location: employees_manage.php');
                exit;
            } catch (PDOException $e) {
                $error = "Fout bij bijwerken: " . $e->getMessage();
            }
        }
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        
        try {
            $stmt = $db->prepare("UPDATE employees SET actief = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            $_SESSION['pd_flash'] = "Medewerker verwijderd (soft delete)";
            header('Location: employees_manage.php');
            exit;
        } catch (PDOException $e) {
            $error = "Fout bij verwijderen: " . $e->getMessage();
        }
    }
}

// Get all employees
$employees = $db->query("
    SELECT * FROM employees 
    WHERE actief = 1 
    ORDER BY Voornaam, Achternaam
")->fetchAll(PDO::FETCH_ASSOC);

// Get locations for dropdown
$locations = $db->query("
    SELECT location_name FROM locations WHERE active = 1 ORDER BY location_name
")->fetchAll(PDO::FETCH_COLUMN);

// 🔧 AFDELINGEN: Gebruik ALLEEN afdelingen tabel (zoals frontend!)
$afdelingen = $db->query("
    SELECT afdeling_name 
    FROM afdelingen 
    WHERE active = 1 
    ORDER BY sort_order ASC, afdeling_name ASC
")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medewerkers Beheren - PeopleDisplay</title>
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
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; color: #555; }
        label .required { color: #e74c3c; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; padding: 10px 0; }
        .checkbox-group input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; }
        .checkbox-group label { margin: 0; font-weight: normal; cursor: pointer; }
        button { padding: 10px 20px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #229954; }
        .btn-danger { background: #e74c3c; color: white; font-size: 12px; padding: 6px 12px; }
        .btn-danger:hover { background: #c0392b; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-warning:hover { background: #e67e22; }
        
        /* CSV Toolbar */
        .csv-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .csv-toolbar h2 { margin: 0; color: #2c3e50; }
        .csv-buttons { display: flex; gap: 10px; }
        .csv-buttons button { font-size: 14px; }
        
        /* Import Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
        .modal-content h2 { margin-bottom: 20px; color: #2c3e50; }
        .modal-buttons { display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end; }
        .file-input-wrapper { border: 2px dashed #3498db; padding: 30px; text-align: center; border-radius: 6px; margin: 20px 0; cursor: pointer; transition: all 0.3s; }
        .file-input-wrapper:hover { background: #ecf0f1; }
        .file-input-wrapper input[type="file"] { display: none; }
        .file-chosen { margin-top: 10px; color: #27ae60; font-weight: bold; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #3498db; color: white; }
        tr:hover { background: #f8f9fa; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .badge-bhv { background: #e74c3c; color: white; }
        .badge-email { background: #27ae60; color: white; }
        .badge-no-email { background: #95a5a6; color: white; }
        small { display: block; color: #666; font-size: 12px; margin-top: 4px; }
        
        /* INLINE ADD FORM STYLING */
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        .form-field input,
        .form-field select {
            width: 100%;
            padding: 10px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 6px;
            font-size: 14px;
            background: rgba(255,255,255,0.95);
            transition: all 0.2s;
        }
        
        .form-field input:focus,
        .form-field select:focus {
            outline: none;
            border-color: white;
            background: white;
            box-shadow: 0 0 0 3px rgba(255,255,255,0.2);
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
        }
        
        .btn-secondary {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid white;
        }
        
        .btn-secondary:hover {
            background: rgba(255,255,255,0.3);
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
        
        <h1>👥 Medewerkers Beheren</h1>
        
        <?php if ($message): ?>
            <div class="success">✅ <?= htmlspecialchars($message) ?></div>
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
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- CSV TOOLBAR -->
        <div class="csv-toolbar">
            <h2>📊 CSV Import/Export</h2>
            <div class="csv-buttons">
                <button onclick="exportCSV()" class="btn-success">📥 Export CSV</button>
                <button onclick="openImportModal()" class="btn-warning">📤 Import CSV</button>
            </div>
        </div>
        
        <!-- COMPACT INLINE ADD FORM -->
        <div class="inline-add-container">
            <button onclick="toggleAddForm()" class="toggle-add-btn" id="toggleBtn">
                ➕ Nieuwe Medewerker Toevoegen
            </button>
            
            <div id="inlineAddForm" class="inline-add-form" style="display: none;">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="inline-form-grid">
                        <div class="form-field">
                            <label>Voornaam *</label>
                            <input type="text" name="voornaam" required placeholder="Jan">
                        </div>
                        
                        <div class="form-field">
                            <label>Achternaam *</label>
                            <input type="text" name="achternaam" required placeholder="Jansen">
                        </div>
                        
                        <div class="form-field">
                            <label>Email</label>
                            <input type="email" name="email" placeholder="jan@bedrijf.nl">
                        </div>
                        
                        <div class="form-field">
                            <label>Telefoon</label>
                            <input type="tel" name="telefoon" placeholder="+31 6 12345678">
                        </div>
                        
                        <div class="form-field">
                            <label>Functie</label>
                            <input type="text" name="functie" placeholder="Pedagogisch medewerker">
                        </div>
                        
                        <div class="form-field">
                            <label>Afdeling *</label>
                            <select name="afdeling" required>
                                <option value="">Selecteer afdeling</option>
                                <?php foreach ($afdelingen as $afd): ?>
                                    <option value="<?= htmlspecialchars($afd) ?>"><?= htmlspecialchars($afd) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-field">
                            <label>Locatie *</label>
                            <select name="locatie" required>
                                <option value="">Selecteer locatie</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- 🆕 MULTI-LOCATION ZICHTBAARHEID -->
<div class="form-field" style="grid-column: 1 / -1;">
    <label style="font-size: 15px; margin-bottom: 10px; display: block;">🌍 Zichtbaar Op Locaties</label>
    <p style="color: rgba(255,255,255,0.8); font-size: 12px; margin: 0 0 15px 0;">
        Bepaal op welke locaties deze medewerker zijn/haar kaart moet verschijnen
    </p>
    
    <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 2px solid rgba(255,255,255,0.2);">
        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 14px;">
            <input 
                type="checkbox" 
                id="visible_all_locations" 
                name="visible_all_locations"
                value="1"
                onchange="toggleLocationCheckboxes(this); toggleManualLocationOption(this);"
                style="width: 20px; height: 20px; cursor: pointer;"
            >
            <span style="font-weight: 600;">
                🌐 Zichtbaar op ALLE locaties
            </span>
        </label>
        <p style="color: rgba(255,255,255,0.7); font-size: 11px; margin: 5px 0 0 30px;">
            Kaart verschijnt overal, ongeacht huidige locatie
        </p>
        
        <!-- 🆕 MANUAL LOCATION CHANGE OPTION -->
        <div id="manual-location-option" style="display: none; margin-top: 12px; margin-left: 30px; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 6px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                <input 
                    type="checkbox" 
                    id="allow_manual_location_change" 
                    name="allow_manual_location_change"
                    value="1"
                    style="width: 18px; height: 18px; cursor: pointer;"
                >
                <span style="font-weight: 500;">
                    📍 Mag handmatig locatie kiezen bij check-in
                </span>
            </label>
            <p style="color: rgba(255,255,255,0.6); font-size: 10px; margin: 4px 0 0 26px;">
                Toont 📍 knop op kaart voor check-in op andere locatie
            </p>
        </div>
    </div>
    
    <div id="specific-locations" style="display: none;">
        <p style="font-weight: 600; margin-bottom: 10px; font-size: 13px;">
            Of selecteer specifieke locaties:
        </p>
        <div id="locations-checkboxes" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px;">
            <!-- Wordt gevuld via JavaScript -->
        </div>
    </div>
</div>
                        <div class="form-field">
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" name="bhv" style="margin-right: 5px;">
                                BHV'er
                            </label>
                        </div>
                    </div>
                    
                    <div class="inline-form-actions">
                        <button type="submit" class="btn-success">✓ Opslaan</button>
                        <button type="button" onclick="toggleAddForm()" class="btn-secondary">Annuleren</button>
                    </div>
                </form>
            </div>
        </div>
        
        
        <!-- FILTER TOOLBAR -->
        <div class="filter-toolbar" style="background: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                <div style="flex: 2; min-width: 200px;">
                    <input type="text" id="search-input" placeholder="🔎 Zoeken..." style="width: 100%; padding: 10px 15px; border: 2px solid #ddd; border-radius: 6px;">
                </div>
                <select id="filter-locatie" style="padding: 10px; border: 2px solid #ddd; border-radius: 6px;"><option value="">📍 Alle locaties</option></select>
                <select id="filter-afdeling" style="padding: 10px; border: 2px solid #ddd; border-radius: 6px;">
                    <option value="">🏢 Alle afdelingen</option>
                    <?php foreach ($afdelingen as $afd): ?>
                        <option value="<?= htmlspecialchars($afd) ?>"><?= htmlspecialchars($afd) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filter-bhv" style="padding: 10px; border: 2px solid #ddd; border-radius: 6px;"><option value="">🚨 BHV</option><option value="Ja">Alleen BHV</option><option value="Nee">Geen BHV</option></select>
                <select id="filter-foto" style="padding: 10px; border: 2px solid #ddd; border-radius: 6px;"><option value="">📷 Foto</option><option value="heeft">Heeft foto</option><option value="geen">Geen foto</option></select>
                <div style="margin-left: auto; display: flex; gap: 10px;">
                    <div style="padding: 8px 15px; background: #e8f5e9; border: 2px solid #4caf50; border-radius: 6px; font-weight: bold; color: #2e7d32;"><span id="filtered-count">0</span> / <span id="total-count">0</span></div>
                    <button type="button" id="reset-filters-btn" style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600;">🔄</button>
                </div>
            </div>
        </div>
        
        <!-- EMPLOYEES TABLE -->
        <div class="card">
            <h2 style="margin-bottom: 20px;">📋 Medewerkers Lijst (<?= count($employees) ?>)</h2>
            
            <table id="employees-table">
                <thead>
                    <tr>
                        <th class="sortable" data-sort="voornaam" style="cursor: pointer;">Voornaam <span class="sort-indicator">⇅</span></th>
                        <th class="sortable" data-sort="achternaam" style="cursor: pointer;">Achternaam <span class="sort-indicator">⇅</span></th>
                        <th class="sortable" data-sort="employee_id" style="cursor: pointer; min-width: 160px;">Employee ID 🆔 <span class="sort-indicator">⇅</span></th>
                        <th style="width: 60px; text-align: center;">📷</th>
                        <th class="sortable" data-sort="email" style="cursor: pointer;">Email <span class="sort-indicator">⇅</span></th>
                        <th>Telefoon</th>
                        <th class="sortable" data-sort="afdeling" style="cursor: pointer;">Afdeling <span class="sort-indicator">⇅</span></th>
                        <th class="sortable" data-sort="locatie" style="cursor: pointer;">Locatie <span class="sort-indicator">⇅</span></th>
                        <th>BHV</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                        <tr data-employee-id="<?= $emp['id'] ?>">
                            <td>
                                <strong><?= htmlspecialchars($emp['voornaam'] ?: '') ?></strong>
                                <?php if ($emp['functie']): ?>
                                    <br><small style="color: #666;"><?= htmlspecialchars($emp['functie']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($emp['achternaam'] ?: '') ?></strong>
                            </td>
                            <td>
                                <div style="font-family: 'Courier New', monospace; font-size: 12px; background: #f0f0f0; padding: 6px 10px; border-radius: 5px; display: inline-flex; align-items: center; gap: 8px;">
                                    <span class="employee-id-value"><?= htmlspecialchars($emp['employee_id']) ?></span>
                                    <button onclick="copyEmployeeId('<?= htmlspecialchars($emp['employee_id']) ?>', this); event.stopPropagation();" title="Kopieer ID" style="background: #007bff; color: white; border: none; padding: 3px 8px; border-radius: 4px; cursor: pointer; font-size: 11px;">📋</button>
                                </div>
                            </td>
                            <td style="text-align: center; padding: 5px;">
                                <?php 
                                $fotoUrl = $emp['foto_url'] ?? '';
                                $heeftFoto = !empty($fotoUrl) && !str_contains($fotoUrl, 'default') && !str_contains($fotoUrl, 'no-photo');
                                ?>
                                <?php if ($heeftFoto): ?>
                                    <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #4caf50; cursor: pointer;" onclick="window.open('<?= htmlspecialchars($fotoUrl) ?>', '_blank')">
                                <?php else: ?>
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #f44336; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 18px; margin: 0 auto;">✗</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($emp['email']): ?>
                                    <a href="mailto:<?= htmlspecialchars($emp['email']) ?>" style="color: #007bff;">
                                        <?= htmlspecialchars($emp['email']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($emp['telefoon']): ?>
                                    <a href="tel:<?= htmlspecialchars($emp['telefoon']) ?>" style="color: #28a745;">
                                        📞 <?= htmlspecialchars($emp['telefoon']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($emp['afdeling'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($emp['locatie'] ?: '-') ?></td>
                            <td>
                                <?php if ($emp['bhv'] === 'Ja'): ?>
                                    <span class="badge badge-bhv">🚨 BHV</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button onclick="editEmployee(<?= $emp['id'] ?>)" class="btn-primary" style="font-size: 12px; padding: 6px 12px; margin-right: 5px;">✏️ Bewerken</button>
                                
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Medewerker verwijderen?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                                    <button type="submit" class="btn-danger">🗑️ Verwijderen</button>
                                </form>
                            </td>
                        </tr>
                        
                        <!-- HIDDEN EDIT FORM -->
                        <tr id="edit-form-<?= $emp['id'] ?>" style="display: none;">
                            <td colspan="10" style="background: #f8f9fa; padding: 20px;">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Voornaam <span class="required">*</span></label>
                                            <input type="text" name="voornaam" value="<?= htmlspecialchars($emp['voornaam'] ?? '') ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Achternaam <span class="required">*</span></label>
                                            <input type="text" name="achternaam" value="<?= htmlspecialchars($emp['achternaam'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input type="email" name="email" value="<?= htmlspecialchars($emp['email'] ?? '') ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Telefoon</label>
                                            <input type="tel" name="telefoon" value="<?= htmlspecialchars($emp['telefoon'] ?? '') ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Functie</label>
                                            <input type="text" name="functie" value="<?= htmlspecialchars($emp['functie'] ?? '') ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>BHV Status</label>
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="bhv" id="bhv-edit-<?= $emp['id'] ?>" <?= $emp['bhv'] === 'Ja' ? 'checked' : '' ?>>
                                                <label for="bhv-edit-<?= $emp['id'] ?>">Deze medewerker is BHV'er</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Afdeling <span class="required">*</span></label>
                                            <select name="afdeling" required>
                                                <?php foreach ($afdelingen as $afd): ?>
                                                    <option value="<?= htmlspecialchars($afd) ?>" <?= $emp['afdeling'] === $afd ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($afd) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Locatie <span class="required">*</span></label>
                                            <select name="locatie" required>
                                                <?php foreach ($locations as $loc): ?>
                                                    <option value="<?= htmlspecialchars($loc) ?>" <?= $emp['locatie'] === $loc ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($loc) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <!-- 🆕 MULTI-LOCATION ZICHTBAARHEID (EDIT FORM) -->
                                    <div class="form-group" style="grid-column: 1 / -1; background: #f8f9fa; padding: 20px; border-radius: 8px; border: 2px solid #dee2e6; margin: 15px 0;">
                                        <label style="font-size: 15px; margin-bottom: 10px; display: block; color: #2c3e50; font-weight: 700;">🌍 Zichtbaar Op Locaties</label>
                                        <p style="color: #6c757d; font-size: 12px; margin: 0 0 15px 0;">
                                            Bepaal op welke locaties deze medewerker zijn/haar kaart moet verschijnen
                                        </p>
                                        
                                        <?php 
                                        $visLocs = json_decode($emp['visible_locations'] ?? '["ALL"]', true);
                                        $isAllVisible = in_array('ALL', $visLocs);
                                        ?>
                                        
                                        <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 2px solid #e9ecef;">
                                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 14px;">
                                                <input 
                                                    type="checkbox" 
                                                    id="visible_all_locations_<?= $emp['id'] ?>" 
                                                    name="visible_all_locations"
                                                    value="1"
                                                    onchange="toggleLocationCheckboxesEdit(this, <?= $emp['id'] ?>); toggleManualLocationOptionEdit(this, <?= $emp['id'] ?>);"
                                                    style="width: 20px; height: 20px; cursor: pointer;"
                                                    <?= $isAllVisible ? 'checked' : '' ?>
                                                >
                                                <span style="font-weight: 600; color: #2c3e50;">
                                                    🌐 Zichtbaar op ALLE locaties
                                                </span>
                                            </label>
                                            <p style="color: #6c757d; font-size: 11px; margin: 5px 0 0 30px;">
                                                Kaart verschijnt overal, ongeacht huidige locatie
                                            </p>
                                            
                                            <!-- 🆕 MANUAL LOCATION CHANGE OPTION (EDIT) -->
                                            <div id="manual-location-option-<?= $emp['id'] ?>" style="display: <?= $isAllVisible ? 'block' : 'none' ?>; margin-top: 12px; margin-left: 30px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                                                    <input 
                                                        type="checkbox" 
                                                        id="allow_manual_location_change_<?= $emp['id'] ?>" 
                                                        name="allow_manual_location_change"
                                                        value="1"
                                                        style="width: 18px; height: 18px; cursor: pointer;"
                                                        <?= ($emp['allow_manual_location_change'] ?? 0) == 1 ? 'checked' : '' ?>
                                                    >
                                                    <span style="font-weight: 500; color: #495057;">
                                                        📍 Mag handmatig locatie kiezen bij check-in
                                                    </span>
                                                </label>
                                                <p style="color: #6c757d; font-size: 10px; margin: 4px 0 0 26px;">
                                                    Toont 📍 knop op kaart voor check-in op andere locatie
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div id="specific-locations-<?= $emp['id'] ?>" style="display: <?= $isAllVisible ? 'none' : 'block' ?>;">
                                            <p style="font-weight: 600; margin-bottom: 10px; font-size: 13px; color: #2c3e50;">
                                                Of selecteer specifieke locaties:
                                            </p>
                                            <div class="locations-checkboxes-edit" data-employee-id="<?= $emp['id'] ?>" data-visible-locations='<?= htmlspecialchars(json_encode($visLocs)) ?>' style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px;">
                                                <!-- Wordt gevuld via JavaScript -->
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Foto URL</label>
                                        <input type="text" name="foto_url" value="<?= htmlspecialchars($emp['foto_url'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Opmerkingen</label>
                                        <textarea name="notities" rows="3"><?= htmlspecialchars($emp['notities'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn-success">✓ Opslaan</button>
                                    <button type="button" onclick="cancelEdit(<?= $emp['id'] ?>)" class="btn-danger">✕ Annuleren</button>
                                </form>
                            </td>
                        </tr>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
            <p>Upload een CSV bestand met medewerkers. Het bestand moet minimaal de kolommen <strong>voornaam</strong> en <strong>achternaam</strong> bevatten.</p>
            
            <div class="file-input-wrapper" onclick="document.getElementById('csvFile').click()">
                <p>📁 Klik hier om een CSV bestand te selecteren</p>
                <input type="file" id="csvFile" accept=".csv">
                <p class="file-chosen" id="fileChosen"></p>
            </div>
            
            <div id="importResult" style="margin-top: 20px;"></div>
            
            <div class="modal-buttons">
                <button onclick="closeImportModal()" class="btn-danger">Annuleren</button>
                <button onclick="uploadCSV()" class="btn-success">✓ Importeren</button>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle inline add form
        function toggleAddForm() {
            const form = document.getElementById('inlineAddForm');
            const btn = document.getElementById('toggleBtn');
            
            if (form.style.display === 'none') {
                form.style.display = 'block';
                btn.textContent = '✕ Annuleren';
                btn.style.background = 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)';
            } else {
                form.style.display = 'none';
                btn.textContent = '➕ Nieuwe Medewerker Toevoegen';
                btn.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            }
        }
        
        function editEmployee(id) {
            const form = document.getElementById('edit-form-' + id);
            form.style.display = form.style.display === 'none' ? 'table-row' : 'none';
        }
        
        function cancelEdit(id) {
            document.getElementById('edit-form-' + id).style.display = 'none';
        }
        
        // CSV Export
        function exportCSV() {
            window.location.href = 'api/export_employees_csv.php';
        }
        
        // CSV Import Modal
        function openImportModal() {
            document.getElementById('importModal').classList.add('active');
            document.getElementById('importResult').innerHTML = '';
            document.getElementById('fileChosen').textContent = '';
            document.getElementById('csvFile').value = '';
        }
        
        function closeImportModal() {
            document.getElementById('importModal').classList.remove('active');
        }
        
        // Show chosen file
        document.getElementById('csvFile').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || '';
            document.getElementById('fileChosen').textContent = fileName ? '✓ ' + fileName : '';
        });
        
        // Upload CSV
        async function uploadCSV() {
            const fileInput = document.getElementById('csvFile');
            const resultDiv = document.getElementById('importResult');
            
            if (!fileInput.files[0]) {
                resultDiv.innerHTML = '<div class="error">❌ Selecteer eerst een CSV bestand</div>';
                return;
            }
            
            const formData = new FormData();
            formData.append('csv_file', fileInput.files[0]);
            
            resultDiv.innerHTML = '<p>⏳ Bezig met importeren...</p>';
            
            try {
                const response = await fetch('api/import_employees_csv.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    let html = '<div class="success">';
                    html += '<h3>✅ ' + data.message + '</h3>';
                    html += '<ul>';
                    html += '<li>Geïmporteerd: ' + data.imported + '</li>';
                    html += '<li>Overgeslagen: ' + data.skipped + '</li>';
                    html += '<li>Totaal rijen: ' + data.total_rows + '</li>';
                    html += '</ul>';
                    
                    if (data.errors && data.errors.length > 0) {
                        html += '<h4>Fouten:</h4><ul>';
                        data.errors.forEach(err => {
                            html += '<li>' + err + '</li>';
                        });
                        html += '</ul>';
                    }
                    
                    html += '<p style="margin-top: 15px;"><button onclick="location.reload()" class="btn-success">Pagina Vernieuwen</button></p>';
                    html += '</div>';
                    resultDiv.innerHTML = html;
                } else {
                    resultDiv.innerHTML = '<div class="error">❌ ' + (data.error || 'Import mislukt') + '</div>';
                }
            } catch (error) {
                resultDiv.innerHTML = '<div class="error">❌ Fout bij importeren: ' + error.message + '</div>';
            }
        }
    </script>
	<script>
// 🆕 MULTI-LOCATION JAVASCRIPT

// Laad beschikbare locaties
async function loadAvailableLocations() {
    try {
        const response = await fetch('/api/get_all_locations.php');
        const data = await response.json();
        
        if (data.success && data.locations) {
            const container = document.getElementById('locations-checkboxes');
            if (!container) return;
            
            container.innerHTML = '';
            
            data.locations.forEach(location => {
                const div = document.createElement('div');
                div.style.cssText = 'background: rgba(255,255,255,0.95); padding: 10px; border-radius: 6px; border: 2px solid rgba(255,255,255,0.3);';
                div.innerHTML = `
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input 
                            type="checkbox" 
                            name="visible_locations[]" 
                            value="${location.id}"
                            class="location-checkbox"
                            style="width: 18px; height: 18px; cursor: pointer;"
                        >
                        <span style="font-size: 13px; color: #2c3e50; font-weight: 500;">
                            ${location.location_name}
                        </span>
                    </label>
                `;
                container.appendChild(div);
            });
        }
    } catch (error) {
        console.error('Error loading locations:', error);
    }
}

// Toggle tussen "ALL" en specifieke locaties (voor add form)
function toggleLocationCheckboxes(checkbox) {
    const specificDiv = document.getElementById('specific-locations');
    const locationCheckboxes = document.querySelectorAll('.location-checkbox');
    
    if (checkbox.checked) {
        // "ALL" is aangevinkt
        specificDiv.style.display = 'none';
        locationCheckboxes.forEach(cb => {
            cb.checked = false;
            cb.disabled = true;
        });
    } else {
        // "ALL" is uitgevinkt
        specificDiv.style.display = 'block';
        locationCheckboxes.forEach(cb => {
            cb.disabled = false;
        });
    }
}

// 🆕 Toggle manual location change option (CREATE form)
function toggleManualLocationOption(checkbox) {
    const manualDiv = document.getElementById('manual-location-option');
    if (manualDiv) {
        manualDiv.style.display = checkbox.checked ? 'block' : 'none';
        // Als uitgezet, uncheck de manual location checkbox
        if (!checkbox.checked) {
            const manualCheckbox = document.getElementById('allow_manual_location_change');
            if (manualCheckbox) manualCheckbox.checked = false;
        }
    }
}

// 🆕 Toggle voor edit forms (per employee)
function toggleLocationCheckboxesEdit(checkbox, employeeId) {
    const specificDiv = document.getElementById('specific-locations-' + employeeId);
    const locationCheckboxes = document.querySelectorAll('.location-checkbox-' + employeeId);
    
    if (checkbox.checked) {
        // "ALL" is aangevinkt
        specificDiv.style.display = 'none';
        locationCheckboxes.forEach(cb => {
            cb.checked = false;
            cb.disabled = true;
        });
    } else {
        // "ALL" is uitgevinkt
        specificDiv.style.display = 'block';
        locationCheckboxes.forEach(cb => {
            cb.disabled = false;
        });
    }
}

// 🆕 Toggle manual location change option (EDIT form)
function toggleManualLocationOptionEdit(checkbox, employeeId) {
    const manualDiv = document.getElementById('manual-location-option-' + employeeId);
    if (manualDiv) {
        manualDiv.style.display = checkbox.checked ? 'block' : 'none';
        // Als uitgezet, uncheck de manual location checkbox
        if (!checkbox.checked) {
            const manualCheckbox = document.getElementById('allow_manual_location_change_' + employeeId);
            if (manualCheckbox) manualCheckbox.checked = false;
        }
    }
}


// 🆕 Load locations voor edit form
async function loadLocationsForEditForm(employeeId) {
    try {
        const response = await fetch('/api/get_all_locations.php');
        const data = await response.json();
        
        if (data.success && data.locations) {
            const container = document.querySelector(`.locations-checkboxes-edit[data-employee-id="${employeeId}"]`);
            if (!container) return;
            
            // Get visible_locations from data attribute
            const visibleLocsStr = container.getAttribute('data-visible-locations');
            let visibleLocs = [];
            try {
                visibleLocs = JSON.parse(visibleLocsStr || '["ALL"]');
            } catch (e) {
                visibleLocs = ['ALL'];
            }
            
            const isAllVisible = visibleLocs.includes('ALL');
            const allCheckbox = document.getElementById('visible_all_locations_' + employeeId);
            
            container.innerHTML = '';
            
            data.locations.forEach(location => {
                const isChecked = !isAllVisible && (
                    visibleLocs.includes(String(location.id)) || 
                    visibleLocs.includes(parseInt(location.id))
                );
                
                const div = document.createElement('div');
                div.style.cssText = 'background: white; padding: 10px; border-radius: 6px; border: 2px solid #e9ecef;';
                div.innerHTML = `
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input 
                            type="checkbox" 
                            name="visible_locations[]" 
                            value="${location.id}"
                            class="location-checkbox location-checkbox-${employeeId}"
                            style="width: 18px; height: 18px; cursor: pointer;"
                            ${isChecked ? 'checked' : ''}
                            ${allCheckbox && allCheckbox.checked ? 'disabled' : ''}
                        >
                        <span style="font-size: 13px; color: #2c3e50; font-weight: 500;">
                            ${location.location_name}
                        </span>
                    </label>
                `;
                container.appendChild(div);
            });
        }
    } catch (error) {
        console.error('Error loading locations for edit form:', error);
    }
}

// Load locations when form is shown
const originalToggleAddForm = window.toggleAddForm;
window.toggleAddForm = function() {
    if (originalToggleAddForm) originalToggleAddForm();
    // Wait for form to be visible
    setTimeout(loadAvailableLocations, 100);
};

// 🆕 Override editEmployee to load locations
const originalEditEmployee = window.editEmployee;
window.editEmployee = function(id) {
    // Call original function
    if (originalEditEmployee) {
        originalEditEmployee(id);
    } else {
        // Fallback if original doesn't exist
        const editRow = document.getElementById('edit-form-' + id);
        const dataRow = editRow ? editRow.previousElementSibling : null;
        if (editRow) {
            editRow.style.display = editRow.style.display === 'none' ? '' : 'none';
        }
        if (dataRow) {
            dataRow.style.display = dataRow.style.display === 'none' ? '' : 'none';
        }
    }
    
    // Load locations for this edit form
    setTimeout(() => {
        loadLocationsForEditForm(id);
    }, 100);
};

// Load on page load
document.addEventListener('DOMContentLoaded', () => {
    loadAvailableLocations();
});

// 📋 COPY EMPLOYEE ID FUNCTION
function copyEmployeeId(employeeId, button) {
    const tempInput = document.createElement('input');
    tempInput.value = employeeId;
    document.body.appendChild(tempInput);
    
    tempInput.select();
    tempInput.setSelectionRange(0, 99999);
    
    try {
        document.execCommand('copy');
        
        const originalText = button.textContent;
        button.textContent = '✓';
        button.style.background = '#28a745';
        
        setTimeout(() => {
            button.textContent = originalText;
            button.style.background = '#007bff';
        }, 2000);
        
    } catch (err) {
        console.error('Copy failed:', err);
        alert('Kopiëren mislukt. Selecteer en kopieer handmatig.');
    }
    
    document.body.removeChild(tempInput);
}
(function(){let a=[],f=[],s={field:'voornaam',direction:'asc'};function init(){const t=document.getElementById('employees-table');if(!t){console.error('Table not found');return}load();pop();listen();headers();apply()}function load(){const r=document.querySelectorAll('#employees-table tbody tr[data-employee-id]');a=Array.from(r).map(row=>{const c=row.querySelectorAll('td');const img=c[3]?.querySelector('img');let e=c[4]?.textContent?.trim()||'';const l=c[4]?.querySelector('a');if(l)e=l.textContent.trim();return{row:row,id:row.getAttribute('data-employee-id'),voornaam:c[0]?.textContent?.trim().split('\n')[0]||'',achternaam:c[1]?.textContent?.trim()||'',heeftFoto:img!==null,email:e,telefoon:c[5]?.textContent?.replace('📞','').trim()||'',afdeling:c[6]?.textContent?.trim()||'',locatie:c[7]?.textContent?.trim()||'',bhv:c[8]?.textContent?.includes('BHV')?'Ja':'Nee'}});console.log('Loaded '+a.length+' employees')}function pop(){const locs=[...new Set(a.map(e=>e.locatie).filter(Boolean))].sort();const sel=document.getElementById('filter-locatie');locs.forEach(l=>{const o=document.createElement('option');o.value=l;o.textContent=l;sel.appendChild(o)});console.log('Filters populated: '+locs.length+' locations (afdelingen pre-populated by PHP)')}function listen(){let t;document.getElementById('search-input').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(apply,300)});document.getElementById('filter-locatie').addEventListener('change',apply);document.getElementById('filter-afdeling').addEventListener('change',apply);document.getElementById('filter-bhv').addEventListener('change',apply);document.getElementById('filter-foto').addEventListener('change',apply);document.getElementById('reset-filters-btn').addEventListener('click',reset)}function headers(){document.querySelectorAll('#employees-table th.sortable').forEach(h=>{h.addEventListener('click',function(){const field=this.getAttribute('data-sort');if(s.field===field){s.direction=s.direction==='asc'?'desc':'asc'}else{s.field=field;s.direction='asc'}updateInd();apply();console.log('Sorted by '+field+' '+s.direction)})})}function updateInd(){document.querySelectorAll('#employees-table th.sortable').forEach(h=>{const ind=h.querySelector('.sort-indicator');const field=h.getAttribute('data-sort');if(field===s.field){ind.textContent=s.direction==='asc'?'▲':'▼';ind.style.color='#007bff';h.style.background='#e3f2fd'}else{ind.textContent='⇅';ind.style.color='#999';h.style.background=''}})}function apply(){f=[...a];const sr=document.getElementById('search-input').value.toLowerCase().trim();if(sr)f=f.filter(e=>e.voornaam.toLowerCase().includes(sr)||e.achternaam.toLowerCase().includes(sr)||e.email.toLowerCase().includes(sr)||e.telefoon.includes(sr));const loc=document.getElementById('filter-locatie').value;if(loc)f=f.filter(e=>e.locatie===loc);const afd=document.getElementById('filter-afdeling').value;if(afd)f=f.filter(e=>e.afdeling===afd);const bhv=document.getElementById('filter-bhv').value;if(bhv)f=f.filter(e=>e.bhv===bhv);const foto=document.getElementById('filter-foto').value;if(foto==='heeft')f=f.filter(e=>e.heeftFoto);else if(foto==='geen')f=f.filter(e=>!e.heeftFoto);f.sort((x,y)=>{const av=(x[s.field]||'').toString().toLowerCase();const bv=(y[s.field]||'').toString().toLowerCase();return s.direction==='asc'?av.localeCompare(bv):bv.localeCompare(av)});display();counter();console.log('Showing '+f.length+' of '+a.length+' employees')}function display(){const tbody=document.querySelector('#employees-table tbody');a.forEach(e=>{e.row.style.display='none';const form=document.getElementById('edit-form-'+e.id);if(form)form.style.display='none'});f.forEach(e=>{e.row.style.display='';tbody.appendChild(e.row);const form=document.getElementById('edit-form-'+e.id);if(form)tbody.appendChild(form)})}function counter(){document.getElementById('filtered-count').textContent=f.length;document.getElementById('total-count').textContent=a.length}function reset(){document.getElementById('search-input').value='';document.getElementById('filter-locatie').value='';document.getElementById('filter-afdeling').value='';document.getElementById('filter-bhv').value='';document.getElementById('filter-foto').value='';s={field:'voornaam',direction:'asc'};updateInd();apply();console.log('Filters reset')}if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init)}else{init()}})();
</script>

</body>
</html>
