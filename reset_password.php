<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
// reset_password.php
// TIJDELIJK SCRIPT - verwijder na gebruik!

require_once __DIR__ . '/includes/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    
    if ($username && $newPassword) {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        try {
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
            $stmt->execute([$passwordHash, $username]);
            
            if ($stmt->rowCount() > 0) {
                $message = "✅ Wachtwoord voor '$username' is gereset naar: $newPassword";
            } else {
                $message = "❌ Gebruiker '$username' niet gevonden.";
            }
        } catch (Exception $e) {
            $message = "❌ Fout: " . $e->getMessage();
        }
    }
}

// Toon alle gebruikers
$users = $db->query("SELECT id, username, role FROM users ORDER BY id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Wachtwoord Reset</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #d32f2f; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f0f0; font-weight: bold; }
        input[type=text], input[type=password] { width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background: #0078d7; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-top: 10px; }
        button:hover { background: #005a9e; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .message { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .success { background: #d4edda; border: 1px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #dc3545; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚠️ Wachtwoord Reset Tool</h1>
        
        <div class="warning">
            <strong>LET OP:</strong> Dit is een ontwikkel-tool. Verwijder dit bestand na gebruik!
        </div>
        
        <?php if ($message): ?>
            <div class="message <?= strpos($message, '✅') !== false ? 'success' : 'error' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <h2>Beschikbare gebruikers:</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Rol</th>
            </tr>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?= $user['id'] ?></td>
                <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                <td><?= htmlspecialchars($user['role']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <h2>Reset wachtwoord:</h2>
        <form method="post">
            <label>Username:</label>
            <input type="text" name="username" required placeholder="bijv: TestKees">
            
            <label>Nieuw wachtwoord:</label>
            <input type="password" name="new_password" required placeholder="bijv: test123">
            
            <button type="submit">🔑 Reset wachtwoord</button>
        </form>
        
        <hr style="margin: 30px 0;">
        <p><a href="<?= BASE_PATH ?>/login.php">→ Naar login pagina</a></p>
    </div>
</body>
</html>