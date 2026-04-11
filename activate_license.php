<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * PeopleDisplay License Activation
 * Accessible WITHOUT a valid license — exempt from license_check.php
 * File: /activate_license.php
 */

declare(strict_types=1);

// Load session + DB + license functions
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/license.php';
// Do NOT include license_check.php here — this page is the exempt entry point

$error   = '';
$success = '';

// ── Reason messages from redirect ────────────────────────────
$reason = htmlspecialchars($_GET['reason'] ?? '');
$reasonMessages = [
    'not_activated'  => 'Geen actieve licentie gevonden. Voer uw licentiecode in om te activeren.',
    'expired'        => 'Uw licentie is verlopen. Neem contact op voor verlenging.',
    'revoked'        => 'Deze licentie is ingetrokken. Neem contact op met support.',
    'domain_mismatch'=> 'Deze licentie is geregistreerd voor een ander domein: <strong>' . htmlspecialchars($_GET['registered'] ?? 'onbekend') . '</strong>',
    'invalid'        => 'Ongeldige licentie. Activeer opnieuw.',
];
if ($reason !== '' && isset($reasonMessages[$reason])) {
    $error = $reasonMessages[$reason];
}

// ── Handle deactivation ───────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'deactivate') {
    // Require session auth before allowing deactivation
    if (isset($_SESSION['user_id'])) {
        deactivateLicense();
        header('Location: activate_license.php?reason=not_activated');
        exit;
    } else {
        $error = 'U moet ingelogd zijn om de licentie te deactiveren.';
    }
}

// ── Handle activation form submission ────────────────────────
$activationResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['license_key'])) {
    $licenseKey      = strtoupper(trim($_POST['license_key']));
    $activationResult = activateLicense($licenseKey);

    if ($activationResult['success']) {
        $info    = getLicenseInfo();
        $tierName = $info['tier_name'] ?? $activationResult['tier'];
        $success  = 'Licentie succesvol geactiveerd! Pakket: <strong>' . htmlspecialchars($tierName) . '</strong>';
        $error    = ''; // clear any prior error
        header('Refresh: 2; url=admin/dashboard.php');
    } else {
        $error = htmlspecialchars($activationResult['error']);
    }
}

// ── Current license state ────────────────────────────────────
$currentLicense    = getLicenseInfo();
$alreadyActivated  = $currentLicense
    && !empty($currentLicense['license_key'])
    && $currentLicense['license_status'] === 'active';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licentie Activeren — PeopleDisplay</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
            width: 100%;
            max-width: 500px;
            padding: 40px;
        }

        /* Header */
        .logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .logo h1 {
            color: #667eea;
            font-size: 26px;
            margin-bottom: 4px;
        }
        .logo p { color: #718096; font-size: 14px; }

        /* Alerts */
        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }
        .alert-error   { background: #fff5f5; border-left: 4px solid #f56565; color: #742a2a; }
        .alert-success { background: #f0fff4; border-left: 4px solid #48bb78; color: #22543d; }
        .alert-info    { background: #ebf8ff; border-left: 4px solid #4299e1; color: #2a4365; }

        /* Form */
        .form-group { margin-bottom: 20px; }

        label {
            display: block;
            margin-bottom: 6px;
            color: #4a5568;
            font-weight: 600;
            font-size: 14px;
        }

        input[type="text"] {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
            font-family: 'Courier New', 'Consolas', monospace;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: border-color 0.2s;
        }
        input[type="text"]:focus  { outline: none; border-color: #667eea; }
        input[type="text"].valid   { border-color: #48bb78; }
        input[type="text"].invalid { border-color: #f56565; }

        .form-hint { font-size: 12px; color: #a0aec0; margin-top: 5px; }

        /* Buttons */
        .btn {
            display: block;
            width: 100%;
            padding: 13px;
            background: #667eea;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn:hover    { background: #5a67d8; }
        .btn:disabled { background: #cbd5e0; cursor: not-allowed; }
        .btn-green    { background: #48bb78; }
        .btn-green:hover { background: #38a169; }

        /* Current license panel */
        .license-panel {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .license-panel h3 {
            color: #2d3748;
            margin-bottom: 16px;
            font-size: 16px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-row .label     { color: #718096; }
        .detail-row .value     { font-weight: 600; color: #2d3748; }

        .badge-active  { background: #c6f6d5; color: #22543d; padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .badge-expired { background: #fed7d7; color: #742a2a; padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }

        .deactivate-link {
            display: block;
            text-align: center;
            margin-top: 14px;
            color: #f56565;
            font-size: 13px;
            text-decoration: none;
        }
        .deactivate-link:hover { text-decoration: underline; }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            font-size: 13px;
            color: #a0aec0;
        }
        .footer a { color: #667eea; text-decoration: none; }
        .footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">

    <div class="logo">
        <h1>🔐 PeopleDisplay</h1>
        <p>Licentie Activatie</p>
    </div>

    <?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
    <div class="alert alert-success">
        <?= $success ?><br>
        <small>U wordt automatisch doorgestuurd naar het dashboard&hellip;</small>
    </div>
    <?php endif; ?>

    <?php if (!$alreadyActivated && $success === ''): ?>

        <!-- ── Activation form ── -->
        <form method="POST" id="activationForm">
            <div class="form-group">
                <label for="license_key">Licentiecode</label>
                <input
                    type="text"
                    id="license_key"
                    name="license_key"
                    placeholder="PDIS-XXXX-XXXX-XXXX"
                    maxlength="19"
                    autocomplete="off"
                    spellcheck="false"
                    required
                >
                <div class="form-hint">Formaat: PDIS-XXXX-XXXX-XXXX &mdash; ontvangen via e-mail na aankoop</div>
            </div>

            <button type="submit" class="btn" id="submitBtn" disabled>
                Activeer Licentie
            </button>
        </form>

        <div class="footer">
            <p>Geen licentiecode? <a href="https://peopledisplay.nl/prijzen" target="_blank">Koop een pakket</a></p>
            <p style="margin-top:8px">Vragen? <a href="mailto:support@peopledisplay.nl">support@peopledisplay.nl</a></p>
        </div>

    <?php else: ?>

        <!-- ── Already activated: show info ── -->
        <div class="alert alert-info">
            Er is al een actieve licentie op dit domein.
        </div>

        <div class="license-panel">
            <h3>Huidige Licentie</h3>

            <div class="detail-row">
                <span class="label">Pakket</span>
                <span class="value"><?= htmlspecialchars($currentLicense['tier_name'] ?? '—') ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Licentiecode</span>
                <span class="value"><?= htmlspecialchars($currentLicense['license_key'] ?? '—') ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Domein</span>
                <span class="value"><?= htmlspecialchars($currentLicense['license_domain'] ?? '—') ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Status</span>
                <span class="value">
                    <?php if (($currentLicense['license_status'] ?? '') === 'active'): ?>
                        <span class="badge-active">Actief</span>
                    <?php else: ?>
                        <span class="badge-expired"><?= htmlspecialchars(ucfirst($currentLicense['license_status'] ?? 'onbekend')) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="label">Geactiveerd op</span>
                <span class="value">
                    <?php
                    $at = $currentLicense['license_activated_at'] ?? '';
                    echo $at ? date('d-m-Y H:i', strtotime($at)) : '—';
                    ?>
                </span>
            </div>
            <?php if (!empty($currentLicense['license_expires_at'])): ?>
            <div class="detail-row">
                <span class="label">Vervalt op</span>
                <span class="value"><?= date('d-m-Y', strtotime($currentLicense['license_expires_at'])) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <a href="admin/dashboard.php" class="btn btn-green" style="margin-top:20px">
            Naar Dashboard &rarr;
        </a>

        <?php if (isset($_SESSION['user_id'])): ?>
        <a
            href="activate_license.php?action=deactivate"
            class="deactivate-link"
            onclick="return confirm('Weet u zeker dat u de licentie wilt deactiveren?\nDe software werkt niet meer totdat een nieuwe licentie wordt geactiveerd.');"
        >
            Deactiveer licentie (voor overdracht naar ander domein)
        </a>
        <?php endif; ?>

    <?php endif; ?>

</div>

<script>
(function () {
    const input     = document.getElementById('license_key');
    const submitBtn = document.getElementById('submitBtn');
    if (!input) return;

    input.addEventListener('input', function (e) {
        // Strip everything except A-Z 0-9
        let raw = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');

        // Re-insert dashes at positions 4, 8, 12
        let formatted = '';
        for (let i = 0; i < raw.length && i < 16; i++) {
            if (i === 4 || i === 8 || i === 12) formatted += '-';
            formatted += raw[i];
        }

        e.target.value = formatted;

        const valid = /^PDIS-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/.test(formatted);

        input.classList.toggle('valid',   valid);
        input.classList.toggle('invalid', formatted.length === 19 && !valid);

        if (submitBtn) submitBtn.disabled = !valid;
    });
})();
</script>
</body>
</html>
