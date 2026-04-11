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
 * BESTANDSNAAM: name_display_config.php
 * LOCATIE:      /admin/name_display_config.php
 * BESCHRIJVING: Configureer hoe namen weergegeven worden
 * ═══════════════════════════════════════════════════════════════════
 */

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/auth_helper.php';

requireAdmin();

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_display_option = $_POST['name_display_option'] ?? 'volledig';
    
    $stmt = $db->prepare("UPDATE config SET name_display_option = ? WHERE id = 1");
    if ($stmt->execute([$name_display_option])) {
        $message = "✅ Naam weergave optie bijgewerkt naar: $name_display_option";
    } else {
        $error = "❌ Fout bij opslaan";
    }
}

// Get current setting
$stmt = $db->query("SELECT name_display_option FROM config WHERE id = 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC);
$current_option = $config['name_display_option'] ?? 'volledig';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Naam Weergave Configuratie</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body {
            background: #f7fafc;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .btn-back {
            background: #718096;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
        }
        
        .form-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .message {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .message.success {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .message.error {
            background: #fed7d7;
            color: #c53030;
        }
        
        .option-card {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .option-card:hover {
            border-color: #667eea;
            background: #f7fafc;
        }
        
        .option-card.selected {
            border-color: #667eea;
            background: #ebf4ff;
        }
        
        .option-card input[type="radio"] {
            margin-right: 10px;
        }
        
        .option-title {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 8px;
            color: #2d3748;
        }
        
        .option-description {
            color: #718096;
            font-size: 14px;
        }
        
        .example {
            background: #f7fafc;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-family: monospace;
            color: #4a5568;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 20px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="btn-back">← Terug naar Dashboard</a>
            <h1 style="margin: 15px 0 0 0;">👤 Naam Weergave Configuratie</h1>
        </div>
        
        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="form-section">
            <h2>Kies hoe namen weergegeven worden op de kaarten:</h2>
            <p style="color: #718096; margin-bottom: 30px;">
                Deze instelling bepaalt hoe medewerker namen op de aanmeldkaarten verschijnen.
            </p>
            
            <form method="POST" id="name-form">
                <div class="option-card <?= $current_option === 'volledig' ? 'selected' : '' ?>" onclick="selectOption('volledig')">
                    <label style="cursor: pointer; display: block;">
                        <input type="radio" name="name_display_option" value="volledig" <?= $current_option === 'volledig' ? 'checked' : '' ?>>
                        <span class="option-title">Volledige Naam</span>
                        <div class="option-description">
                            Voornaam en achternaam worden beide getoond
                        </div>
                        <div class="example">Voorbeeld: Jan Jansen</div>
                    </label>
                </div>
                
                <div class="option-card <?= $current_option === 'voornaam' ? 'selected' : '' ?>" onclick="selectOption('voornaam')">
                    <label style="cursor: pointer; display: block;">
                        <input type="radio" name="name_display_option" value="voornaam" <?= $current_option === 'voornaam' ? 'checked' : '' ?>>
                        <span class="option-title">Alleen Voornaam</span>
                        <div class="option-description">
                            Alleen de voornaam wordt getoond (informeler)
                        </div>
                        <div class="example">Voorbeeld: Jan</div>
                    </label>
                </div>
                
                <div class="option-card <?= $current_option === 'achternaam' ? 'selected' : '' ?>" onclick="selectOption('achternaam')">
                    <label style="cursor: pointer; display: block;">
                        <input type="radio" name="name_display_option" value="achternaam" <?= $current_option === 'achternaam' ? 'checked' : '' ?>>
                        <span class="option-title">Alleen Achternaam</span>
                        <div class="option-description">
                            Alleen de achternaam wordt getoond (formeler)
                        </div>
                        <div class="example">Voorbeeld: Jansen</div>
                    </label>
                </div>
                
                <div class="option-card <?= $current_option === 'initiaal_achternaam' ? 'selected' : '' ?>" onclick="selectOption('initiaal_achternaam')">
                    <label style="cursor: pointer; display: block;">
                        <input type="radio" name="name_display_option" value="initiaal_achternaam" <?= $current_option === 'initiaal_achternaam' ? 'checked' : '' ?>>
                        <span class="option-title">Initiaal + Achternaam</span>
                        <div class="option-description">
                            Eerste letter van voornaam + achternaam (compact & professioneel)
                        </div>
                        <div class="example">Voorbeeld: J. Jansen</div>
                    </label>
                </div>
                
                <button type="submit" class="btn-primary">💾 Opslaan</button>
            </form>
        </div>
    </div>
    
    <script>
    function selectOption(value) {
        // Update radio button
        document.querySelector(`input[value="${value}"]`).checked = true;
        
        // Update selected class
        document.querySelectorAll('.option-card').forEach(card => {
            card.classList.remove('selected');
        });
        event.currentTarget.classList.add('selected');
    }
    </script>
</body>
</html>
