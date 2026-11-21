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
 * BESTANDSNAAM:  force_password_change.php
 * UPLOAD NAAR:   ROOT (/force_password_change.php)
 * VERSIE:        2.2.0
 *
 * Verplichte wachtwoordwijziging: een beheerder heeft aangevinkt dat deze gebruiker
 * een nieuw wachtwoord moet kiezen. Zolang dat niet gedaan is, stuurt includes/db.php
 * elke pagina door naar deze pagina (zie pd_enforce_password_change()).
 * ============================================================================
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/twofa.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

/** Waar gaat de gebruiker heen na het wijzigen? (zelfde logica als bij inloggen) */
function pd_force_target(PDO $db, int $userId, string $role): string
{
    try {
        $stmt = $db->prepare("SELECT linked_employee_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        if (!empty($stmt->fetchColumn())) {
            return 'index.php';
        }
    } catch (PDOException $e) {
        // kolom bestaat nog niet
    }
    return in_array($role, ['admin', 'superadmin'], true) ? 'admin/dashboard.php' : 'frontpage.php';
}

pd_security_ensure_schema($db);

// Is wijzigen (nog) verplicht? Zo niet, dan hoort deze pagina niet in beeld te zijn.
$mustChange = false;
try {
    $stmt = $db->prepare("SELECT must_change_password FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $mustChange = ((int)$stmt->fetchColumn() === 1);
} catch (Throwable $e) {
    $mustChange = false;
}
if (!$mustChange) {
    unset($_SESSION['must_change_password']);
    header('Location: ' . pd_force_target($db, $userId, (string)($_SESSION['role'] ?? 'user')));
    exit;
}
$_SESSION['must_change_password'] = true;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    // Te veel verkeerde "huidige wachtwoorden" tellen mee voor de blokkade van het inloggen
    $username = (string)($_SESSION['username'] ?? '');
    $wait = pd_login_wait_seconds($db, $username);

    if ($wait > 0) {
        $error = pd_login_blocked_message($wait);
    } elseif ($current === '' || $new === '' || $confirm === '') {
        $error = 'Vul alle velden in.';
    } else {
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $hash = (string)$stmt->fetchColumn();

        if ($hash === '' || !password_verify($current, $hash)) {
            pd_login_record($db, $username, false, 'bad_password');
            $error = 'Het huidige wachtwoord is onjuist.';
        } elseif ($new !== $confirm) {
            $error = 'De twee nieuwe wachtwoorden zijn niet gelijk.';
        } elseif ($new === $current) {
            $error = 'Kies een ander wachtwoord dan het huidige.';
        } elseif (($policyError = pd_password_policy_error($new)) !== null) {
            $error = $policyError;
        } else {
            try {
                $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
                pd_password_changed($db, $userId);
                // "Ingelogd blijven"-cookies van dit apparaat horen bij de oude tokens
                if (!empty($_COOKIE['remember_selector'])) {
                    pd_set_remember_cookie('remember_selector', '', time() - 3600);
                    pd_set_remember_cookie('remember_token', '', time() - 3600);
                }
                // Nieuw sessie-ID; het bijbehorende regeltje in user_sessions (online-gebruikers,
                // sessiecontrole) moet mee, anders denkt de sessiecontrole dat je bent uitgelogd.
                $oldSessionId = session_id();
                session_regenerate_id(true);
                try {
                    $db->prepare("UPDATE user_sessions SET session_id = ?, last_activity = NOW(), is_active = 1 WHERE session_id = ?")
                       ->execute([session_id(), $oldSessionId]);
                } catch (Throwable $e) {
                    error_log('force_password_change session id update: ' . $e->getMessage());
                }
                // Zelfde bestemming als na het inloggen: daarin zit ook de uitnodiging voor 2FA (sinds 2.3.0)
                header('Location: ' . pd_post_login_target($db, $userId, (string)($_SESSION['role'] ?? 'user')));
                exit;
            } catch (Throwable $e) {
                error_log('force_password_change: ' . $e->getMessage());
                $error = 'Het wijzigen is niet gelukt. Probeer het opnieuw of neem contact op met de beheerder.';
            }
        }
    }
}

$minLen = PD_MIN_PASSWORD_LENGTH;
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nieuw wachtwoord kiezen - PeopleDisplay</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .box {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            width: 100%;
            max-width: 460px;
        }
        h1 { font-size: 24px; color: #2d3748; margin-bottom: 8px; text-align: center; }
        .intro { color: #4a5568; font-size: 14px; line-height: 1.5; margin-bottom: 24px; text-align: center; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-weight: 600; color: #2d3748; margin-bottom: 6px; font-size: 14px; }
        input[type="password"] {
            width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0;
            border-radius: 8px; font-size: 15px;
        }
        input[type="password"]:focus { outline: none; border-color: #667eea; }
        .hint { font-size: 12px; color: #718096; margin-top: 4px; }
        .error {
            background: #fed7d7; color: #c53030; padding: 12px 16px; border-radius: 8px;
            margin-bottom: 18px; font-size: 14px; border-left: 4px solid #c53030;
        }
        button {
            width: 100%; padding: 14px; border: none; border-radius: 8px; cursor: pointer;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; font-size: 16px; font-weight: 600;
        }
        .out { text-align: center; margin-top: 18px; font-size: 13px; }
        .out a { color: #718096; }
    </style>
</head>
<body>
    <div class="box">
        <h1>🔑 Kies een nieuw wachtwoord</h1>
        <p class="intro">
            Een beheerder heeft gevraagd dat je een nieuw wachtwoord kiest voordat je verder kunt.
            Je gebruikt dit wachtwoord voortaan om in te loggen.
        </p>

        <?php if ($error !== ''): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="current_password">Huidig wachtwoord</label>
                <input type="password" id="current_password" name="current_password" required autofocus autocomplete="current-password">
            </div>
            <div class="form-group">
                <label for="new_password">Nieuw wachtwoord</label>
                <input type="password" id="new_password" name="new_password" required minlength="<?= (int)$minLen ?>" autocomplete="new-password">
                <div class="hint">Minimaal <?= (int)$minLen ?> tekens. Een zin van drie of vier woorden is makkelijk te onthouden en sterk.</div>
            </div>
            <div class="form-group">
                <label for="confirm_password">Nieuw wachtwoord nogmaals</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="<?= (int)$minLen ?>" autocomplete="new-password">
            </div>
            <button type="submit">Wachtwoord opslaan</button>
        </form>

        <p class="out"><a href="logout.php">Uitloggen</a></p>
    </div>
</body>
</html>
