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
 * BESTANDSNAAM:  login_2fa.php
 * UPLOAD NAAR:   ROOT (/login_2fa.php)
 * VERSIE:        2.3.0
 *
 * Tweede stap van het inloggen: de code uit de authenticator-app (of een herstelcode).
 * Hier kom je alleen na een goed wachtwoord; de inlog is pas klaar na een goede code.
 * ============================================================================
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/twofa.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . pd_post_login_target($db, (int)$_SESSION['user_id'], (string)($_SESSION['role'] ?? 'user')));
    exit;
}

$pending = pd_2fa_get_pending();
if ($pending === null) {
    header('Location: login.php');
    exit;
}

$userId   = (int)$pending['user_id'];
$username = (string)$pending['username'];
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel'])) {
        unset($_SESSION['pd_2fa_pending']);
        header('Location: login.php');
        exit;
    }

    $code = trim((string)($_POST['code'] ?? ''));
    pd_security_ensure_schema($db);
    $wait = pd_login_wait_seconds($db, $username);

    if ($wait > 0) {
        pd_login_record($db, $username, false, 'blocked');
        $error = pd_login_blocked_message($wait);
    } elseif (pd_2fa_user_locked($db, $username)) {
        pd_login_record($db, $username, false, 'blocked');
        $error = 'Te veel mislukte codes voor dit account. Probeer het over 15 minuten opnieuw, of neem contact op met de beheerder.';
    } elseif ($code === '') {
        $error = 'Vul de code in.';
    } else {
        $method = pd_2fa_verify_user_code($db, $userId, $code);
        if ($method === null) {
            pd_login_record($db, $username, false, 'bad_2fa');
            usleep(random_int(150000, 350000));
            $error = 'Deze code klopt niet of is verlopen. Controleer de tijd op je telefoon en probeer de nieuwe code.';
        } else {
            $stmt = $db->prepare("SELECT id, username, display_name, role, active, must_change_password FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || !$user['active']) {
                unset($_SESSION['pd_2fa_pending']);
                header('Location: login.php');
                exit;
            }
            $remember = !empty($pending['remember']);
            $target = pd_complete_login($db, $user, $remember, $method === 'recovery' ? '2fa_recovery' : '2fa');
            if (!empty($_POST['trust_device'])) {
                pd_2fa_trust_device($db, $userId);
            }
            // Bijna geen herstelcodes meer over: wijs de gebruiker erop
            if ($method === 'recovery' && $target !== 'force_password_change.php' && pd_2fa_recovery_left($db, $userId) <= 3) {
                $target = 'twofa_setup.php?herstel=1';
            }
            header('Location: ' . $target);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code invoeren - PeopleDisplay</title>
    <style><?= pd_2fa_page_css() ?></style>
</head>
<body>
    <div class="box" style="max-width:440px;">
        <h1>🔐 Tweede stap</h1>
        <p class="intro">
            Open de authenticator-app op je telefoon en vul de 6 cijfers in voor PeopleDisplay.
            Geen toegang tot je telefoon? Gebruik dan een van je herstelcodes.
        </p>

        <?php if ($error !== ''): ?>
            <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="code">Code uit de app, of herstelcode</label>
                <input type="text" id="code" name="code" class="code" required autofocus
                       inputmode="text" autocomplete="one-time-code" maxlength="20" spellcheck="false"
                       placeholder="123 456">
            </div>
            <label class="check">
                <input type="checkbox" name="trust_device" value="1">
                <span>Dit apparaat 30 dagen vertrouwen
                    <small>Op dit apparaat hoef je 30 dagen geen code meer in te vullen. Kies dit alleen op je eigen computer of telefoon.</small>
                </span>
            </label>
            <button type="submit">Inloggen</button>
            <button type="submit" name="cancel" value="1" class="secondary" formnovalidate>Annuleren</button>
        </form>
    </div>
</body>
</html>
