<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * BESTANDSNAAM: visitor_checkout.php
 * LOCATIE: /visitor_checkout.php (ROOT)
 */

session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/license_check.php';
requireFeature('visitor_management');

$token = $_GET['token'] ?? '';
$message = '';
$error = '';

if (empty($token)) {
    $error = 'Ongeldige check-out link';
} else {
    $stmt = $db->prepare("
        SELECT * FROM visitors 
        WHERE checkout_token = ? 
        AND (tokens_valid_until IS NULL OR tokens_valid_until > NOW())
    ");
    $stmt->execute([$token]);
    $visitor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visitor) {
        $error = 'Check-out link is ongeldig of verlopen';
    } elseif ($visitor['status'] !== 'BINNEN') {
        $error = 'U bent al uitgecheckt';
    } else {
        // Check out!
        $stmt = $db->prepare("UPDATE visitors SET status = 'VERTROKKEN' WHERE id = ?");
        $stmt->execute([$visitor['id']]);
        $message = 'U bent succesvol uitgecheckt. Bedankt voor uw bezoek!';
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uitchecken - PeopleDisplay</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-width: 500px; width: 100%; text-align: center; }
        h1 { color: #333; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; padding: 20px; border-radius: 6px; margin: 20px 0; font-size: 18px; }
        .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 6px; margin: 20px 0; }
        .icon { font-size: 60px; margin: 20px 0; }
        p { font-size: 16px; color: #666; margin: 15px 0; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($error): ?>
            <div class="icon">❌</div>
            <h1>Check-out niet mogelijk</h1>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php else: ?>
            <div class="icon">✅</div>
            <h1>Tot ziens!</h1>
            <div class="success"><?= htmlspecialchars($message) ?></div>
            <p>U kunt deze pagina nu sluiten.</p>
        <?php endif; ?>
    </div>
</body>
</html>
