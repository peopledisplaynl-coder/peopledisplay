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
 * BESTANDSNAAM:  employees_edit.php
 * UPLOAD NAAR:   /admin/employees_edit.php
 * VERSIE:        2.0 - MET VOORNAAM/ACHTERNAAM
 * ============================================================================
 */

require_once __DIR__ . '/auth_helper.php';
requireAdmin();

require_once __DIR__ . '/../includes/db.php';

$id = $_GET['id'] ?? null;
$error = '';
$success = '';

if (!$id) {
    header('Location: employees_manage.php');
    exit;
}

// Get employee
$stmt = $db->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: employees_manage.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voornaam = trim($_POST['voornaam'] ?? '');
    $achternaam = trim($_POST['achternaam'] ?? '');
    $functie = trim($_POST['functie'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefoon = trim($_POST['telefoon'] ?? '');
    $afdeling = trim($_POST['afdeling'] ?? '');
    $locatie = trim($_POST['locatie'] ?? '');
    $bhv = $_POST['bhv'] ?? 'Nee';
    $notities = trim($_POST['notities'] ?? '');
    
    // Validation
    if (empty($voornaam) || empty($achternaam)) {
        $error = 'Voornaam en achternaam zijn verplicht!';
    } else {
        // Update naam field (combinatie van voornaam + achternaam)
        $naam = $voornaam . ' ' . $achternaam;
        
        try {
            $stmt = $db->prepare("
                UPDATE employees 
                SET voornaam = ?, 
                    achternaam = ?, 
                    naam = ?,
                    functie = ?, 
                    email = ?, 
                    telefoon = ?,
                    afdeling = ?, 
                    locatie = ?, 
                    bhv = ?,
                    notities = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $voornaam,
                $achternaam,
                $naam,
                $functie,
                $email,
                $telefoon,
                $afdeling,
                $locatie,
                $bhv,
                $notities,
                $id
            ]);
            
            $success = 'Medewerker succesvol bijgewerkt!';
            
            // Reload employee data
            $stmt = $db->prepare("SELECT * FROM employees WHERE id = ?");
            $stmt->execute([$id]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $error = 'Fout bij opslaan: ' . $e->getMessage();
        }
    }
}

// Get all locations for dropdown
$stmt = $db->query("SELECT location_name FROM locations WHERE active = 1 ORDER BY sort_order ASC, location_name ASC");
$locations = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get all afdelingen for dropdown
$stmt = $db->query("SELECT afdeling_name FROM afdelingen WHERE active = 1 ORDER BY sort_order ASC, afdeling_name ASC");
$afdelingen = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medewerker Bewerken</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        h1 {
            font-size: 24px;
            color: #2d3748;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-secondary {
            background: #718096;
            color: white;
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2d3748;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="email"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        
        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #4299e1;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn-primary {
            background: #4299e1;
            color: white;
            width: 100%;
            padding: 12px;
            font-size: 16px;
        }
        
        .btn-primary:hover {
            background: #3182ce;
        }
        
        .required {
            color: #e53e3e;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✏️ Medewerker Bewerken</h1>
            <a href="employees_manage.php" class="btn btn-secondary">← Terug</a>
        </div>
        
        <div class="form-container">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Voornaam <span class="required">*</span></label>
                        <input type="text" name="voornaam" value="<?= htmlspecialchars($employee['voornaam'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Achternaam <span class="required">*</span></label>
                        <input type="text" name="achternaam" value="<?= htmlspecialchars($employee['achternaam'] ?? '') ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Functie</label>
                    <input type="text" name="functie" value="<?= htmlspecialchars($employee['functie'] ?? '') ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($employee['email'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Telefoon</label>
                        <input type="text" name="telefoon" value="<?= htmlspecialchars($employee['telefoon'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Afdeling</label>
                        <select name="afdeling">
                            <option value="">-- Selecteer afdeling --</option>
                            <?php foreach ($afdelingen as $afd): ?>
                                <option value="<?= htmlspecialchars($afd) ?>" <?= $employee['afdeling'] == $afd ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($afd) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Locatie</label>
                        <select name="locatie">
                            <option value="">-- Selecteer locatie --</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= htmlspecialchars($loc) ?>" <?= $employee['locatie'] == $loc ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>BHV</label>
                    <select name="bhv">
                        <option value="Nee" <?= $employee['bhv'] == 'Nee' ? 'selected' : '' ?>>Nee</option>
                        <option value="Ja" <?= $employee['bhv'] == 'Ja' ? 'selected' : '' ?>>Ja</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Notities</label>
                    <textarea name="notities"><?= htmlspecialchars($employee['notities'] ?? '') ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">💾 Opslaan</button>
            </form>
        </div>
    </div>
</body>
</html>
