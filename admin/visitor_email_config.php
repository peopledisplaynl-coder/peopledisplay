<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
// /admin/visitor_email_config.php
// Admin interface voor bezoeker email instellingen

require_once '../includes/db.php';
require_once 'auth_helper.php';
requireAdmin();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fallback_email = $_POST['fallback_email'] ?? '';
    
    // Validate email
    if (!empty($fallback_email) && !filter_var($fallback_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Ongeldig email adres";
    } else {
        try {
            $stmt = $db->prepare("UPDATE config SET visitor_notification_fallback_email = ? WHERE id = 1");
            $stmt->execute([$fallback_email ?: null]);
            $success = "Fallback email adres opgeslagen!";
        } catch (PDOException $e) {
            $error = "Database fout: " . $e->getMessage();
        }
    }
}

// Load current config
try {
    $stmt = $db->query("SELECT visitor_notification_fallback_email FROM config WHERE id = 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    $fallback_email = $config['visitor_notification_fallback_email'] ?? '';
} catch (PDOException $e) {
    $error = "Kon configuratie niet laden: " . $e->getMessage();
    $fallback_email = '';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bezoeker Email Instellingen</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 4px;
        }
        .info-box h3 {
            color: #1976d2;
            margin-bottom: 8px;
            font-size: 16px;
        }
        .info-box ul {
            margin-left: 20px;
            color: #555;
        }
        .info-box li {
            margin: 5px 0;
        }
        .form-group {
            margin-bottom: 25px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            transition: border-color 0.3s;
        }
        input[type="email"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .help-text {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        button {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: white;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-link">← Terug naar Dashboard</a>
    
    <div class="container">
        <h1>📧 Bezoeker Email Instellingen</h1>
        <p class="subtitle">Configureer email notificaties voor bezoekers</p>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>📬 Hoe werkt het?</h3>
            <ul>
                <li><strong>Bezoeker meldt zich aan</strong> → Medewerker krijgt email</li>
                <li><strong>Medewerker heeft geen email?</strong> → Fallback email ontvangt het</li>
                <li><strong>Bij check-in</strong> → Ook email notificatie</li>
            </ul>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="fallback_email">
                    Fallback Email Adres
                </label>
                <input 
                    type="email" 
                    id="fallback_email" 
                    name="fallback_email" 
                    value="<?= htmlspecialchars($fallback_email) ?>"
                    placeholder="receptie@jouwbedrijf.nl"
                >
                <div class="help-text">
                    Dit email adres ontvangt notificaties wanneer de betreffende medewerker geen email adres heeft ingevuld.
                    Laat leeg om geen fallback te gebruiken.
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn-primary">
                    💾 Opslaan
                </button>
                <button type="button" class="btn-secondary" onclick="location.href='dashboard.php'">
                    Annuleren
                </button>
            </div>
        </form>
    </div>
</body>
</html>
