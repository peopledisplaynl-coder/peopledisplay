<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * BESTANDSNAAM: visitor_register.php
 * LOCATIE: /visitor_register.php (ROOT)
 * VERSIE: 2.1 - Contactpersoon dropdown per locatie
 */

session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/license_check.php';
requireFeature('visitor_management');
require_once __DIR__ . '/includes/email_helper.php';

$message = '';
$error = '';

// Get locations from database
$locations = $db->query("SELECT location_name FROM locations WHERE active = 1 ORDER BY location_name")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voornaam = trim($_POST['voornaam']);
    $achternaam = trim($_POST['achternaam']);
    $email = trim($_POST['email']);
    $telefoon = trim($_POST['telefoon'] ?? '');
    $bedrijf = trim($_POST['bedrijf'] ?? '');
    $contactpersoon = trim($_POST['contactpersoon']);
    $locatie = $_POST['locatie'];
    $bezoek_datum = $_POST['bezoek_datum'];
    $tijd = $_POST['tijd'] ?? date('H:i');
    
    // Multi-day support
    $is_multi_day = isset($_POST['is_multi_day']) ? 1 : 0;
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    
    // Validation
    if (empty($voornaam) || empty($achternaam) || empty($email) || empty($contactpersoon) || empty($locatie)) {
        $error = 'Vul alle verplichte velden in';
    } elseif ($is_multi_day && (empty($start_date) || empty($end_date))) {
        $error = 'Vul start- en einddatum in voor meerdaagse bezoeken';
    } elseif ($is_multi_day && $start_date > $end_date) {
        $error = 'Einddatum moet na startdatum liggen';
    } else {
        try {
            // Generate tokens
            $checkinToken = generateSecureToken();
            $checkoutToken = generateSecureToken();
            $tokensValidUntil = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            // Insert visitor
            $stmt = $db->prepare("
                INSERT INTO visitors (
                    voornaam, achternaam, email, telefoon, bedrijf, 
                    contactpersoon, locatie, bezoek_datum, tijd, status,
                    is_multi_day, start_date, end_date,
                    checkin_token, checkout_token, tokens_valid_until,
                    created_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, 'AANGEMELD',
                    ?, ?, ?,
                    ?, ?, ?,
                    NOW()
                )
            ");
            
            $stmt->execute([
                $voornaam, $achternaam, $email, $telefoon, $bedrijf,
                $contactpersoon, $locatie, $bezoek_datum, $tijd,
                $is_multi_day, $start_date, $end_date,
                $checkinToken, $checkoutToken, $tokensValidUntil
            ]);
            
            $visitorId = $db->lastInsertId();
            
            // Send registration email to visitor
            sendRegistrationEmail($db, $visitorId);
            
            // Notify employee
            sendEmployeeNotification($db, $visitorId, 'registration');
            
            $message = 'Registratie succesvol! U ontvangt een bevestigingsmail met een check-in link.';
            
            // Clear form
            $_POST = [];
            
        } catch (PDOException $e) {
            $error = 'Fout bij registratie: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bezoeker Registreren - PeopleDisplay</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 700px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        h1 { color: #333; margin-bottom: 10px; text-align: center; }
        .subtitle { text-align: center; color: #666; margin-bottom: 30px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; color: #555; }
        label .required { color: #e74c3c; }
        input[type="text"], input[type="email"], input[type="tel"], input[type="date"], input[type="time"], select {
            width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; transition: border 0.3s;
        }
        input:focus, select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        button { width: 100%; padding: 15px; background: #667eea; color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; transition: all 0.3s; }
        button:hover { background: #5568d3; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102,126,234,0.4); }
        .checkbox-group { display: flex; align-items: center; gap: 10px; padding: 15px; background: #f8f9fa; border-radius: 6px; }
        .checkbox-group input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; }
        .checkbox-group label { margin: 0; font-weight: normal; cursor: pointer; }
        .multi-day-fields { display: none; margin-top: 15px; padding: 15px; background: #fff3cd; border-radius: 6px; }
        .multi-day-fields.active { display: block; }
        .privacy-link { text-align: center; margin-top: 15px; font-size: 13px; }
        .privacy-link a { color: #667eea; text-decoration: none; }
        .privacy-link a:hover { text-decoration: underline; }
        .loading { color: #666; font-style: italic; }
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
            .container { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Bezoeker Registreren</h1>
        <p class="subtitle">Vul onderstaand formulier in om uzelf te registreren als bezoeker</p>
        
        <?php if ($message): ?>
            <div class="success">✅ <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Voornaam <span class="required">*</span></label>
                    <input type="text" name="voornaam" required value="<?= htmlspecialchars($_POST['voornaam'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Achternaam <span class="required">*</span></label>
                    <input type="text" name="achternaam" required value="<?= htmlspecialchars($_POST['achternaam'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>E-mailadres <span class="required">*</span></label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Telefoonnummer</label>
                    <input type="tel" name="telefoon" value="<?= htmlspecialchars($_POST['telefoon'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Bedrijf</label>
                <input type="text" name="bedrijf" value="<?= htmlspecialchars($_POST['bedrijf'] ?? '') ?>">
            </div>
            
            <!-- STAP 1: LOCATIE EERST -->
            <div class="form-group">
                <label>Locatie <span class="required">*</span></label>
                <select name="locatie" id="locatie-select" required onchange="loadContactPersons()">
                    <option value="">-- Selecteer locatie --</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= htmlspecialchars($loc) ?>" <?= (isset($_POST['locatie']) && $_POST['locatie'] === $loc) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($loc) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- STAP 2: CONTACTPERSOON DROPDOWN (dynamisch geladen) -->
            <div class="form-group">
                <label>Contactpersoon / Medewerker <span class="required">*</span></label>
                <select name="contactpersoon" id="contactpersoon-select" required disabled>
                    <option value="">-- Selecteer eerst een locatie --</option>
                </select>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" name="is_multi_day" id="is_multi_day" onchange="toggleMultiDay()">
                <label for="is_multi_day">📅 Dit is een bezoek over meerdere dagen</label>
            </div>
            
            <div id="multi-day-fields" class="multi-day-fields">
                <div class="form-row">
                    <div class="form-group">
                        <label>Startdatum</label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Einddatum</label>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($_POST['end_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>
            </div>
            
            <div id="single-day-fields">
                <div class="form-row">
                    <div class="form-group">
                        <label>Bezoekdatum <span class="required">*</span></label>
                        <input type="date" name="bezoek_datum" required value="<?= htmlspecialchars($_POST['bezoek_datum'] ?? date('Y-m-d')) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Verwachte tijd</label>
                        <input type="time" name="tijd" value="<?= htmlspecialchars($_POST['tijd'] ?? date('H:i')) ?>">
                    </div>
                </div>
            </div>
            
            <button type="submit">Registreren</button>
            
            <div class="privacy-link">
                Door te registreren gaat u akkoord met onze <a href="/privacy.php" target="_blank">privacyverklaring</a>
            </div>
        </form>
    </div>
    
    <script>
        // Toggle multi-day fields
        function toggleMultiDay() {
            const checkbox = document.getElementById('is_multi_day');
            const multiDayFields = document.getElementById('multi-day-fields');
            const singleDayFields = document.getElementById('single-day-fields');
            
            if (checkbox.checked) {
                multiDayFields.classList.add('active');
                singleDayFields.style.display = 'none';
            } else {
                multiDayFields.classList.remove('active');
                singleDayFields.style.display = 'block';
            }
        }
        
        // Load contact persons based on selected location
        async function loadContactPersons() {
            const locatieSelect = document.getElementById('locatie-select');
            const contactSelect = document.getElementById('contactpersoon-select');
            const selectedLocatie = locatieSelect.value;
            
            // Reset dropdown
            contactSelect.innerHTML = '<option value="">-- Laden... --</option>';
            contactSelect.disabled = true;
            
            if (!selectedLocatie) {
                contactSelect.innerHTML = '<option value="">-- Selecteer eerst een locatie --</option>';
                return;
            }
            
            try {
                // Fetch employees for this location
                const response = await fetch('/api/get_employees_by_location.php?locatie=' + encodeURIComponent(selectedLocatie));
                const data = await response.json();
                
                if (data.success && data.employees && data.employees.length > 0) {
                    contactSelect.innerHTML = '<option value="">-- Selecteer medewerker --</option>';
                    
                    data.employees.forEach(emp => {
                        const option = document.createElement('option');
                        option.value = emp.naam;
                        option.textContent = emp.naam + (emp.functie ? ' (' + emp.functie + ')' : '');
                        contactSelect.appendChild(option);
                    });
                    
                    contactSelect.disabled = false;
                } else {
                    contactSelect.innerHTML = '<option value="">Geen medewerkers gevonden op deze locatie</option>';
                }
            } catch (error) {
                console.error('Error loading employees:', error);
                contactSelect.innerHTML = '<option value="">Fout bij laden medewerkers</option>';
            }
        }
        
        // Initialize on load
        toggleMultiDay();
        
        // If location already selected (after form submit error), load employees
        if (document.getElementById('locatie-select').value) {
            loadContactPersons();
        }
    </script>
</body>
</html>
