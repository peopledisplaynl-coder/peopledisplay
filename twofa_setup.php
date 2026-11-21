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
 * BESTANDSNAAM:  twofa_setup.php
 * UPLOAD NAAR:   ROOT (/twofa_setup.php)
 * VERSIE:        2.3.0
 *
 * Tweestapsverificatie instellen en beheren voor de ingelogde gebruiker:
 * authenticator-app koppelen (QR-code), herstelcodes, vertrouwde apparaten, uitzetten.
 * Hier komt een gebruiker ook als 2FA verplicht is gesteld en het uitstel voorbij is.
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

$userId   = (int)$_SESSION['user_id'];
$username = (string)($_SESSION['username'] ?? '');
$role     = (string)($_SESSION['role'] ?? 'user');

/** Gewone startpagina van deze gebruiker (zelfde keuze als na het inloggen, zonder 2FA-uitnodiging). */
function pd_2fa_home(PDO $db, int $userId, string $role): string
{
    try {
        $stmt = $db->prepare("SELECT linked_employee_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        if (!empty($stmt->fetchColumn())) {
            return 'index.php';
        }
    } catch (Throwable $e) {
        // kolom bestaat nog niet
    }
    return in_array($role, ['admin', 'superadmin'], true) ? 'admin/dashboard.php' : 'frontpage.php';
}
$home = pd_2fa_home($db, $userId, $role);

// Kiosk- en tabletaccounts gebruiken geen 2FA
if (!empty($_SESSION['kiosk_login'])) {
    header('Location: ' . $home);
    exit;
}

$available = pd_2fa_available() && pd_2fa_ensure_schema($db);
$message = '';
$error = '';
$newCodes = null;

$enabled  = $available && pd_2fa_enabled($db, $userId);
$required = $available && pd_2fa_required($db, $userId);
$deadline = ($available && $required && !$enabled) ? pd_2fa_grace_deadline($db, $userId) : null;
$inGrace  = $deadline !== null && time() < $deadline;
$daysLeft = $inGrace ? max(1, (int)ceil(($deadline - time()) / 86400)) : 0;

/** Wachtwoord van de ingelogde gebruiker controleren (telt mee voor de blokkade bij fouten). */
function pd_2fa_password_ok(PDO $db, int $userId, string $username, string $password, string &$error): bool
{
    $wait = pd_login_wait_seconds($db, $username);
    if ($wait > 0) {
        $error = pd_login_blocked_message($wait);
        return false;
    }
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $hash = (string)$stmt->fetchColumn();
    if ($password === '' || $hash === '' || !password_verify($password, $hash)) {
        pd_login_record($db, $username, false, 'bad_password');
        $error = 'Het wachtwoord klopt niet.';
        return false;
    }
    return true;
}

$setupHost = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
$setupIssuer = 'PeopleDisplay' . ($setupHost !== '' ? ' ' . $setupHost : '');
$setupAccountLabel = $setupIssuer . ' (' . $username . ')';

if ($available && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!pd_2fa_csrf_ok()) {
        $error = 'De pagina was verlopen. Probeer het opnieuw.';
    } elseif ($action === 'later') {
        if ($inGrace) {
            $_SESSION['pd_2fa_invited_at'] = time();
            header('Location: ' . $home);
            exit;
        }
        $error = 'Het uitstel is voorbij; je moet 2FA nu instellen.';
    } elseif ($action === 'enable' && !$enabled) {
        // De sleutel die op het scherm stond (versleuteld meegestuurd in het formulier) is leidend:
        // daar is de QR-code mee gemaakt. De sessie is alleen een terugval.
        $sessionSecret = (string)($_SESSION['pd_2fa_setup_secret'] ?? '');
        $secret = pd_2fa_setup_token_read((string)($_POST['setup_token'] ?? ''), $userId);
        if ($secret !== '' && $sessionSecret !== '' && $secret !== $sessionSecret) {
            error_log('twofa_setup: sleutel in formulier wijkt af van sessie (user ' . $userId . ')');
        }
        if ($secret === '') {
            $secret = $sessionSecret;
        }
        $code = (string)($_POST['code'] ?? '');
        $wait = pd_login_wait_seconds($db, $username);
        if ($wait > 0) {
            $error = pd_login_blocked_message($wait);
        } elseif ($secret === '') {
            $error = 'De sleutel is verlopen. Scan de nieuwe QR-code hieronder.';
        } else {
            // Blijf bij de getoonde sleutel, ook als deze code fout was
            $_SESSION['pd_2fa_setup_secret'] = $secret;
            $counter = pd_totp_verify($secret, $code, 0);
            if ($counter === null) {
                pd_login_record($db, $username, false, 'bad_2fa');
                $offset = pd_totp_clock_offset($secret, $code);
                if ($offset !== null) {
                    $min = max(1, (int)round(abs($offset) * 30 / 60));
                    $error = 'Deze code hoort bij de juiste sleutel, maar de klok van je telefoon loopt ongeveer ' . $min
                        . ' minuten ' . ($offset > 0 ? 'voor' : 'achter') . '. Zet in je telefoon de automatische tijd aan (bij Google Authenticator: Instellingen → Tijdcorrectie voor codes → Nu synchroniseren) en probeer de nieuwe code.';
                } else {
                    $error = 'Deze code klopt niet of is verlopen. Controleer of je de code hebt uit het account "' . $setupAccountLabel
                        . '" in je app (heb je dit account eerder gescand, dan kunnen er meerdere bijna gelijke accounts staan) en probeer de nieuwe code.';
                }
            } else {
                try {
                    $newCodes = pd_2fa_enable($db, $userId, $secret, $counter);
                    unset($_SESSION['pd_2fa_setup_secret'], $_SESSION['pd_2fa_state'], $_SESSION['pd_2fa_invited_at']);
                    // De oude "ingelogd blijven"-cookies van dit apparaat horen bij de oude tokens
                    if (!empty($_COOKIE['remember_selector'])) {
                        pd_set_remember_cookie('remember_selector', '', time() - 3600);
                        pd_set_remember_cookie('remember_token', '', time() - 3600);
                    }
                    $enabled = true;
                    $deadline = null;
                    $inGrace = false;
                    pd_login_record($db, $username, true, '2fa_enabled');
                } catch (Throwable $e) {
                    error_log('twofa_setup enable: ' . $e->getMessage());
                    $error = 'Het instellen is niet gelukt. Probeer het opnieuw of neem contact op met de beheerder.';
                }
            }
        }
    } elseif ($action === 'regen' && $enabled) {
        if (pd_2fa_password_ok($db, $userId, $username, (string)($_POST['password'] ?? ''), $error)) {
            try {
                $newCodes = pd_2fa_new_recovery_codes($db, $userId);
            } catch (Throwable $e) {
                error_log('twofa_setup regen: ' . $e->getMessage());
                $error = 'Nieuwe codes maken is niet gelukt.';
            }
        }
    } elseif ($action === 'disable' && $enabled) {
        if ($required) {
            $error = 'Een beheerder heeft 2FA voor jouw account verplicht gesteld; uitzetten kan alleen door de beheerder.';
        } elseif (pd_2fa_password_ok($db, $userId, $username, (string)($_POST['password'] ?? ''), $error)) {
            try {
                pd_2fa_disable($db, $userId);
                unset($_SESSION['pd_2fa_state']);
                if (!empty($_COOKIE[PD_2FA_COOKIE])) {
                    pd_2fa_set_cookie('', time() - 3600);
                }
                $enabled = false;
                $message = 'Tweestapsverificatie staat uit. Je kunt het hier altijd weer aanzetten.';
            } catch (Throwable $e) {
                error_log('twofa_setup disable: ' . $e->getMessage());
                $error = 'Uitzetten is niet gelukt.';
            }
        }
    } elseif ($action === 'revoke' && $enabled) {
        pd_2fa_revoke_devices($db, $userId, false);
        if (!empty($_COOKIE[PD_2FA_COOKIE])) {
            pd_2fa_set_cookie('', time() - 3600);
        }
        $message = 'Alle vertrouwde apparaten zijn afgemeld. Bij de volgende keer inloggen is overal weer een code nodig.';
    }
}

// Nieuwe sleutel voor de koppeling (pas opgeslagen als de eerste code klopt)
$secret = '';
$qr = '';
$setupToken = '';
if ($available && !$enabled) {
    if (empty($_SESSION['pd_2fa_setup_secret'])) {
        $_SESSION['pd_2fa_setup_secret'] = pd_2fa_generate_secret();
    }
    $secret = $_SESSION['pd_2fa_setup_secret'];
    $qr = pd_2fa_qr_svg(pd_2fa_otpauth_uri($username, $secret, $setupIssuer));
    $setupToken = pd_2fa_setup_token_make($secret, $userId);
    // Deze pagina bevat een geheime sleutel: nooit in de cache of bij "terug" tonen
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

$recoveryLeft = $enabled ? pd_2fa_recovery_left($db, $userId) : 0;
$enabledAt = '';
if ($enabled && $newCodes === null) {
    try {
        $stmt = $db->prepare("SELECT enabled_at FROM user_2fa WHERE user_id = ?");
        $stmt->execute([$userId]);
        $ts = $stmt->fetchColumn();
        $enabledAt = $ts ? date('d-m-Y', strtotime((string)$ts)) : '';
    } catch (Throwable $e) {
        // niet erg
    }
}
$trustedCount = 0;
if ($enabled) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_trusted_devices WHERE user_id = ? AND expires_at > NOW()");
        $stmt->execute([$userId]);
        $trustedCount = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        // niet erg
    }
}
$csrf = pd_2fa_csrf_token();
$h = static function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tweestapsverificatie - PeopleDisplay</title>
    <style><?= pd_2fa_page_css() ?></style>
</head>
<body>
<div class="box">
    <h1>🔐 Tweestapsverificatie</h1>

<?php if (!$available): ?>
    <div class="error">Tweestapsverificatie is op deze server niet beschikbaar (de PHP-uitbreiding OpenSSL ontbreekt). Neem contact op met de beheerder.</div>
    <p class="links"><a href="<?= $h($home) ?>">Terug naar PeopleDisplay</a></p>

<?php elseif ($newCodes !== null): ?>
    <div class="ok">✅ Tweestapsverificatie staat aan.</div>
    <h2>Bewaar je herstelcodes</h2>
    <p class="text">Raak je je telefoon kwijt, dan log je in met een van deze codes in plaats van de app-code. <strong>Elke code werkt één keer.</strong> Bewaar ze op een veilige plek, bijvoorbeeld in je wachtwoordmanager of uitgeprint. Ze worden hierna niet meer getoond.</p>
    <div class="codes" id="codes">
        <?php foreach ($newCodes as $c): ?><div><?= $h($c) ?></div><?php endforeach; ?>
    </div>
    <div class="noprint">
        <button type="button" class="secondary" onclick="window.print()">🖨️ Printen</button>
        <button type="button" class="secondary" onclick="pdCopyCodes(this)">📋 Kopiëren</button>
        <button type="button" class="secondary" onclick="pdDownloadCodes()">💾 Downloaden als tekstbestand</button>
        <label class="check" style="margin-top:18px;">
            <input type="checkbox" id="saved" onchange="document.getElementById('go').style.display = this.checked ? 'block' : 'none'">
            <span>Ik heb mijn herstelcodes op een veilige plek bewaard</span>
        </label>
        <a id="go" class="btn" style="display:none;" href="<?= $h($home) ?>">Verder naar PeopleDisplay</a>
    </div>
    <script>
    var pdCodes = <?= json_encode(array_values($newCodes)) ?>;
    function pdCopyCodes(btn) {
        var t = pdCodes.join('\n');
        if (navigator.clipboard) { navigator.clipboard.writeText(t).then(function(){ btn.textContent = '✅ Gekopieerd'; }); }
        else { var a = document.createElement('textarea'); a.value = t; document.body.appendChild(a); a.select(); document.execCommand('copy'); a.remove(); btn.textContent = '✅ Gekopieerd'; }
    }
    function pdDownloadCodes() {
        var blob = new Blob(['PeopleDisplay herstelcodes\n\n' + pdCodes.join('\n') + '\n'], {type: 'text/plain'});
        var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'peopledisplay-herstelcodes.txt';
        document.body.appendChild(a); a.click(); a.remove();
    }
    </script>

<?php elseif ($enabled): ?>
    <?php if (isset($_GET['herstel'])): ?>
        <div class="warn">Je hebt zojuist een herstelcode gebruikt. Er <?= $recoveryLeft === 1 ? 'is nog 1 herstelcode' : 'zijn nog ' . (int)$recoveryLeft . ' herstelcodes' ?> over. Maak hieronder nieuwe codes aan.</div>
    <?php endif; ?>
    <?php if ($message !== ''): ?><div class="ok"><?= $h($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="error">⚠️ <?= $h($error) ?></div><?php endif; ?>

    <div class="ok">✅ Tweestapsverificatie staat aan<?= $enabledAt !== '' ? ' sinds ' . $h($enabledAt) : '' ?>.</div>
    <p class="text">Herstelcodes over: <strong><?= (int)$recoveryLeft ?></strong> van 10. Vertrouwde apparaten: <strong><?= (int)$trustedCount ?></strong>.</p>

    <h2>Nieuwe herstelcodes maken</h2>
    <p class="text">De oude codes werken daarna niet meer.</p>
    <form method="POST" action="">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="action" value="regen">
        <div class="form-group"><label for="pw1">Je wachtwoord</label><input type="password" id="pw1" name="password" required autocomplete="current-password"></div>
        <button type="submit" class="secondary">Nieuwe herstelcodes maken</button>
    </form>

    <?php if ($trustedCount > 0): ?>
    <h2>Vertrouwde apparaten</h2>
    <p class="text">Op deze apparaten hoef je geen code in te vullen. Meld ze allemaal af als je een apparaat kwijt bent.</p>
    <form method="POST" action="">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="action" value="revoke">
        <button type="submit" class="secondary">Alle vertrouwde apparaten afmelden</button>
    </form>
    <?php endif; ?>

    <h2>Tweestapsverificatie uitzetten</h2>
    <?php if ($required): ?>
        <p class="text">Een beheerder heeft 2FA voor jouw account verplicht gesteld. Uitzetten kan alleen door de beheerder.</p>
    <?php else: ?>
        <form method="POST" action="" onsubmit="return confirm('Weet je zeker dat je tweestapsverificatie wilt uitzetten? Je account is dan alleen met je wachtwoord beveiligd.');">
            <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="disable">
            <div class="form-group"><label for="pw2">Je wachtwoord</label><input type="password" id="pw2" name="password" required autocomplete="current-password"></div>
            <button type="submit" class="danger">Uitzetten</button>
        </form>
    <?php endif; ?>
    <p class="links"><a href="<?= $h($home) ?>">Terug naar PeopleDisplay</a></p>

<?php else: ?>
    <?php if ($message !== ''): ?><div class="ok"><?= $h($message) ?></div><?php endif; ?>
    <?php if ($required && !$inGrace): ?>
        <div class="warn">Een beheerder heeft tweestapsverificatie voor jouw account verplicht gesteld. Stel het nu in om verder te gaan.</div>
    <?php elseif ($required && $inGrace): ?>
        <div class="warn">Een beheerder heeft tweestapsverificatie voor jouw account verplicht gesteld. Je hebt nog <strong><?= (int)$daysLeft ?> <?= $daysLeft === 1 ? 'dag' : 'dagen' ?></strong> om het in te stellen.</div>
    <?php endif; ?>
    <p class="intro">Een tweede beveiligingsstap naast je wachtwoord. Bij het inloggen vul je ook een code in uit een app op je telefoon. Het instellen duurt ongeveer twee minuten.</p>
    <?php if ($error !== ''): ?><div class="error">⚠️ <?= $h($error) ?></div><?php endif; ?>

    <h2>Stap 1: installeer een authenticator-app</h2>
    <p class="text">Bijvoorbeeld Google Authenticator, Microsoft Authenticator, Authy of 1Password. Heb je al zo'n app, dan kun je die gebruiken.</p>

    <h2>Stap 2: koppel de app</h2>
    <p class="text">Kies in de app "Account toevoegen" en scan deze QR-code.</p>
    <div class="center"><?= $qr ?></div>
    <p class="text center" style="margin-top:12px;">Lukt scannen niet? Kies in de app voor handmatig invoeren en gebruik deze sleutel:</p>
    <p class="center"><span class="secret"><?= $h(trim(chunk_split($secret, 4, ' '))) ?></span></p>
    <p class="text center" style="font-size:13px;">In je app verschijnt het account <strong><?= $h($setupAccountLabel) ?></strong>. Staat er al een oud account met dezelfde naam in je app (van een eerdere poging), verwijder dat dan eerst, zodat je niet de verkeerde code overtypt.</p>

    <h2>Stap 3: vul de code uit de app in</h2>
    <form method="POST" action="">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="action" value="enable">
        <input type="hidden" name="setup_token" value="<?= $h($setupToken) ?>">
        <div class="form-group">
            <label for="code">Code van 6 cijfers</label>
            <input type="text" id="code" name="code" class="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123 456">
        </div>
        <button type="submit">Bevestigen en aanzetten</button>
    </form>
    <?php if ($inGrace): ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="later">
            <button type="submit" class="secondary">Later instellen</button>
        </form>
    <?php endif; ?>
    <p class="links">
        <?php if (!$required): ?><a href="<?= $h($home) ?>">Terug naar PeopleDisplay</a> · <?php endif; ?>
        <a href="logout.php">Uitloggen</a>
    </p>
<?php endif; ?>
</div>
</body>
</html>
