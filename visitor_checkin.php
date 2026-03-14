<?php
/**
 * BESTANDSNAAM: visitor_checkin.php
 * LOCATIE: /visitor_checkin.php (ROOT)
 * BESCHRIJVING: Handle check-in via email token
 */

session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/email_helper.php';

$token = $_GET['token'] ?? '';
$message = '';
$error = '';
$visitor = null;

if (empty($token)) {
    $error = 'Ongeldige check-in link';
} else {
    // Find visitor by token
    $stmt = $db->prepare("
        SELECT * FROM visitors 
        WHERE checkin_token = ? 
        AND (tokens_valid_until IS NULL OR tokens_valid_until > NOW())
    ");
    $stmt->execute([$token]);
    $visitor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visitor) {
        $error = 'Check-in link is ongeldig of verlopen';
    }
}

// Handle privacy acceptance and check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $visitor) {
    $privacyAccepted = isset($_POST['privacy_accepted']) ? 1 : 0;
    
    if (!$privacyAccepted) {
        $error = 'U moet akkoord gaan met de privacyverklaring om in te checken';
    } else {
        // Update visitor: check in!
        $stmt = $db->prepare("
            UPDATE visitors 
            SET status = 'BINNEN',
                privacy_accepted = 1,
                privacy_accepted_at = NOW(),
                tijd = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$visitor['id']]);
        
        // Send checkout email
        sendCheckoutEmail($db, $visitor['id']);
        
        // Notify employee
        sendEmployeeNotification($db, $visitor['id'], 'checkin');
        
        $message = 'U bent succesvol ingecheckt! Een uitcheck-link is naar uw e-mail verzonden.';
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inchecken - PeopleDisplay</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-width: 600px; width: 100%; }
        h1 { color: #333; margin-bottom: 20px; text-align: center; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
        .visitor-info { background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 20px; }
        .visitor-info h3 { margin-bottom: 15px; color: #495057; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #dee2e6; }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-weight: bold; color: #6c757d; }
        .privacy-box { background: #e8f5e9; padding: 20px; border-left: 4px solid #4CAF50; margin: 20px 0; border-radius: 4px; }
        .privacy-box h3 { color: #2e7d32; margin-bottom: 10px; }
        .privacy-box p { font-size: 14px; line-height: 1.6; color: #1b5e20; margin-bottom: 10px; }
        .checkbox-group { display: flex; align-items: flex-start; gap: 10px; margin: 20px 0; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; }
        .checkbox-group input[type="checkbox"] { margin-top: 3px; width: 20px; height: 20px; cursor: pointer; }
        .checkbox-group label { cursor: pointer; font-size: 14px; line-height: 1.5; }
        button { width: 100%; padding: 15px; background: #4CAF50; color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; transition: all 0.3s; }
        button:hover { background: #45a049; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(76,175,80,0.3); }
        button:disabled { background: #ccc; cursor: not-allowed; transform: none; }
        .link { color: #667eea; text-decoration: none; font-weight: bold; }
        .link:hover { text-decoration: underline; }
        .warning { color: #e65100; font-size: 13px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
            <p style="text-align: center;"><a href="/" class="link">← Terug naar homepage</a></p>
        <?php elseif ($message): ?>
            <div class="success">✅ <?= htmlspecialchars($message) ?></div>
            <p style="text-align: center; margin-top: 20px;">U kunt deze pagina nu sluiten.</p>
        <?php elseif ($visitor): ?>
            <h1>✓ Welkom bij <?= htmlspecialchars($visitor['locatie']) ?></h1>
            
            <div class="visitor-info">
                <h3>Uw bezoekgegevens</h3>
                <div class="info-row">
                    <span class="info-label">Naam:</span>
                    <span><?= htmlspecialchars($visitor['voornaam'] . ' ' . $visitor['achternaam']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Bedrijf:</span>
                    <span><?= htmlspecialchars($visitor['bedrijf']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Contactpersoon:</span>
                    <span><?= htmlspecialchars($visitor['contactpersoon']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Datum:</span>
                    <span>
                        <?php if ($visitor['is_multi_day']): ?>
                            <?= date('d-m-Y', strtotime($visitor['start_date'])) ?> t/m <?= date('d-m-Y', strtotime($visitor['end_date'])) ?>
                        <?php else: ?>
                            <?= date('d-m-Y', strtotime($visitor['bezoek_datum'])) ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            
            <div class="privacy-box">
                <h3>🔒 Privacyverklaring</h3>
                <p>Uw persoonlijke gegevens worden uitsluitend gebruikt voor bezoekersregistratie en het informeren van uw gastheer. Alle gegevens worden na 7 dagen automatisch uit ons systeem verwijderd.</p>
                <p><a href="/privacy.php" target="_blank" class="link">Lees onze volledige privacyverklaring →</a></p>
            </div>
            
            <form method="POST">
                <div class="checkbox-group">
                    <input type="checkbox" name="privacy_accepted" id="privacy_accepted" required>
                    <label for="privacy_accepted">
                        Ik ga akkoord met de privacyverklaring en geef toestemming voor het verwerken van mijn gegevens voor bezoekersregistratie.
                    </label>
                </div>
                
                <button type="submit">✓ Inchecken</button>
                
                <p class="warning">⚠️ U moet akkoord gaan met de privacyverklaring om in te checken</p>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
