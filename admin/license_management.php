<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * PeopleDisplay License Management
 * Admin-only page for viewing license status, usage, and tier features.
 * File: /admin/license_management.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/license_check.php';

// ── Auth: admin or superadmin only ───────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
$userRole = $_SESSION['role'] ?? 'user';
if (!in_array($userRole, ['admin', 'superadmin'], true)) {
    header('Location: ../frontpage.php');
    exit;
}

// ── Flash messages ────────────────────────────────────────────
$flashError   = '';
$flashSuccess = '';

// Feature-not-available redirect from requireFeature()
if (!empty($_GET['error']) && $_GET['error'] === 'feature_not_available') {
    $featureNames = [
        'visitor_management'  => 'Bezoekersbeheer',
        'bhv_print'           => 'BHV Rooster Afdrukken',
        'sub_status'          => 'Sub-status beheer',
        'location_override'   => 'Locatie override',
        'kiosk_mode'          => 'Kiosk modus',
        'api_access'          => 'API-toegang',
    ];
    $feat = htmlspecialchars($_GET['feature'] ?? '');
    $featLabel = $featureNames[$feat] ?? $feat;
    $flashError = 'De functie <strong>' . $featLabel . '</strong> is niet beschikbaar in uw huidige licentiepakket. Upgrade uw licentie om toegang te krijgen.';
}

// ── Data ──────────────────────────────────────────────────────
$licenseInfo  = getLicenseInfo();
$usageStats   = getLicenseUsageStats();
$allTiers     = getAllLicenseTiers();
$isValid      = isLicenseValid();

// Feature labels for display
$featureLabels = [
    'visitor_management'  => 'Bezoekersbeheer',
    'bhv_print'           => 'BHV Rooster Afdrukken',
    'sub_status'          => 'Sub-status Beheer',
    'location_override'   => 'Locatie Override',
    'kiosk_mode'          => 'Kiosk Modus',
    'api_access'          => 'API-toegang',
];

$currentUser = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licentiebeheer — PeopleDisplay Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container { max-width: 1100px; margin: 0 auto; }

        /* ── Header ── */
        .header {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 20px 28px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            font-size: 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }
        .header-sub { font-size: 13px; color: #718096; margin-top: 3px; }
        .back-btn {
            padding: 8px 16px;
            background: #667eea;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .back-btn:hover { background: #5a67d8; }

        /* ── Alerts ── */
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }
        .alert-error   { background: #fff5f5; border-left: 4px solid #f56565; color: #742a2a; }
        .alert-success { background: #f0fff4; border-left: 4px solid #48bb78; color: #22543d; }
        .alert-warning { background: #fffbeb; border-left: 4px solid #f6ad55; color: #7b341e; }

        /* ── Card ── */
        .card {
            background: rgba(255,255,255,0.95);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 28px;
            margin-bottom: 20px;
        }
        .card-title {
            font-size: 17px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-title .icon { font-size: 20px; }

        /* ── Detail rows ── */
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        @media (max-width: 640px) { .detail-grid { grid-template-columns: 1fr; } }

        .detail-item {
            background: #f7fafc;
            border-radius: 10px;
            padding: 14px 16px;
        }
        .detail-item .di-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #a0aec0;
            margin-bottom: 5px;
        }
        .detail-item .di-value {
            font-size: 15px;
            font-weight: 600;
            color: #2d3748;
        }
        .detail-item .di-value.mono {
            font-family: 'Courier New', monospace;
            letter-spacing: 1.5px;
            font-size: 13px;
        }

        /* ── Badges ── */
        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .badge-active  { background: #c6f6d5; color: #22543d; }
        .badge-expired { background: #fed7d7; color: #742a2a; }
        .badge-pending { background: #fefcbf; color: #744210; }
        .badge-revoked { background: #e2e8f0; color: #4a5568; }

        /* ── Usage bars ── */
        .usage-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        @media (max-width: 640px) { .usage-grid { grid-template-columns: 1fr; } }

        .usage-item { }
        .usage-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 6px;
        }
        .usage-label { font-size: 13px; font-weight: 600; color: #4a5568; }
        .usage-count { font-size: 13px; color: #718096; }
        .usage-bar-bg {
            height: 10px;
            background: #e2e8f0;
            border-radius: 99px;
            overflow: hidden;
        }
        .usage-bar-fill {
            height: 100%;
            border-radius: 99px;
            transition: width 0.5s ease;
        }
        .fill-green  { background: #48bb78; }
        .fill-yellow { background: #f6ad55; }
        .fill-red    { background: #f56565; }
        .usage-sub { font-size: 11px; color: #a0aec0; margin-top: 4px; }

        /* ── Feature list ── */
        .feature-list { display: flex; flex-direction: column; gap: 10px; }
        .feature-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #f7fafc;
            border-radius: 10px;
            font-size: 14px;
        }
        .feature-name { font-weight: 500; color: #2d3748; }
        .feat-yes { color: #38a169; font-weight: 700; font-size: 16px; }
        .feat-no  { color: #e53e3e; font-weight: 700; font-size: 16px; }

        /* ── Tiers table ── */
        .tiers-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .tiers-table th {
            background: #f7fafc;
            padding: 12px 14px;
            text-align: left;
            font-weight: 700;
            color: #4a5568;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }
        .tiers-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f0f0f0;
            color: #2d3748;
            vertical-align: top;
        }
        .tiers-table tr.current-tier td { background: #ebf8ff; }
        .tiers-table tr:last-child td { border-bottom: none; }
        .tiers-table .tier-name { font-weight: 700; }
        .tiers-table .tier-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            background: #667eea;
            color: #fff;
            margin-left: 6px;
        }

        /* ── Action buttons ── */
        .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 20px; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary { background: #667eea; color: #fff; }
        .btn-primary:hover { background: #5a67d8; }
        .btn-danger  { background: #f56565; color: #fff; }
        .btn-danger:hover { background: #e53e3e; }
        .btn-outline {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
        }
        .btn-outline:hover { background: #667eea; color: #fff; }

        /* ── Layout ── */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 760px) { .two-col { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">

    <!-- Header -->
    <div class="header">
        <div>
            <h1>Licentiebeheer</h1>
            <div class="header-sub">Ingelogd als: <?= htmlspecialchars($currentUser) ?></div>
        </div>
        <a href="dashboard.php" class="back-btn">&larr; Dashboard</a>
    </div>

    <!-- Alerts -->
    <?php if ($flashError !== ''): ?>
    <div class="alert alert-error"><?= $flashError ?></div>
    <?php endif; ?>
    <?php if ($flashSuccess !== ''): ?>
    <div class="alert alert-success"><?= $flashSuccess ?></div>
    <?php endif; ?>
    <?php if (!$isValid && $licenseInfo): ?>
    <div class="alert alert-warning">
        <strong>Licentiewaarschuwing:</strong> De huidige licentie is niet actief of geldig. Sommige functies zijn mogelijk geblokkeerd.
    </div>
    <?php endif; ?>

    <?php if (!$licenseInfo): ?>
    <!-- No license activated -->
    <div class="card">
        <div class="card-title"><span class="icon">🔑</span> Geen Actieve Licentie</div>
        <p style="color:#718096; font-size:14px; margin-bottom:20px;">
            Er is geen licentie geactiveerd op dit systeem. Activeer een licentie om alle functies van PeopleDisplay te gebruiken.
        </p>
        <a href="../activate_license.php" class="btn btn-primary">Licentie Activeren</a>
    </div>

    <?php else: ?>

    <!-- ── License Status ── -->
    <div class="card">
        <div class="card-title"><span class="icon">🔐</span> Huidige Licentie</div>

        <div class="detail-grid">
            <div class="detail-item">
                <div class="di-label">Pakket</div>
                <div class="di-value"><?= htmlspecialchars($licenseInfo['tier_name'] ?? $licenseInfo['license_tier'] ?? '—') ?></div>
            </div>
            <div class="detail-item">
                <div class="di-label">Status</div>
                <div class="di-value">
                    <?php
                    $status = $licenseInfo['license_status'] ?? 'unknown';
                    $badgeClass = match($status) {
                        'active'  => 'badge-active',
                        'expired' => 'badge-expired',
                        'revoked' => 'badge-revoked',
                        default   => 'badge-pending',
                    };
                    $statusLabel = match($status) {
                        'active'  => 'Actief',
                        'expired' => 'Verlopen',
                        'revoked' => 'Ingetrokken',
                        'pending' => 'In afwachting',
                        default   => ucfirst($status),
                    };
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= $statusLabel ?></span>
                </div>
            </div>
            <div class="detail-item">
                <div class="di-label">Licentiecode</div>
                <div class="di-value mono"><?= htmlspecialchars($licenseInfo['license_key'] ?? '—') ?></div>
            </div>
            <div class="detail-item">
                <div class="di-label">Geregistreerd Domein</div>
                <div class="di-value mono"><?= htmlspecialchars($licenseInfo['license_domain'] ?? '—') ?></div>
            </div>
            <div class="detail-item">
                <div class="di-label">Geactiveerd op</div>
                <div class="di-value">
                    <?php
                    $at = $licenseInfo['license_activated_at'] ?? '';
                    echo $at ? date('d-m-Y H:i', strtotime($at)) : '—';
                    ?>
                </div>
            </div>
            <div class="detail-item">
                <div class="di-label">Vervalt op</div>
                <div class="di-value">
                    <?php
                    $exp = $licenseInfo['license_expires_at'] ?? '';
                    if ($exp) {
                        $days = (int)((strtotime($exp) - time()) / 86400);
                        echo date('d-m-Y', strtotime($exp));
                        if ($days <= 30 && $days >= 0) {
                            echo ' <span style="color:#e53e3e;font-size:12px;">(' . $days . ' dagen resterend)</span>';
                        } elseif ($days < 0) {
                            echo ' <span style="color:#e53e3e;font-size:12px;">(verlopen)</span>';
                        }
                    } else {
                        echo '<span style="color:#38a169;">Geen vervaldatum</span>';
                    }
                    ?>
                </div>
            </div>
            <?php if (!empty($licenseInfo['tier_description'])): ?>
            <div class="detail-item" style="grid-column: 1 / -1;">
                <div class="di-label">Pakketomschrijving</div>
                <div class="di-value" style="font-weight:400;font-size:14px;"><?= htmlspecialchars($licenseInfo['tier_description']) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="actions">
            <a href="../activate_license.php" class="btn btn-outline">Nieuwe Licentie Activeren</a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <a
                href="../activate_license.php?action=deactivate"
                class="btn btn-danger"
                onclick="return confirm('Weet u zeker dat u de licentie wilt deactiveren?\nDe software werkt niet meer totdat een nieuwe licentie wordt geactiveerd.');"
            >Licentie Deactiveren</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Two-column: Usage + Features ── -->
    <div class="two-col">

        <!-- Usage Stats -->
        <div class="card">
            <div class="card-title"><span class="icon">📊</span> Gebruik</div>
            <?php if (!empty($usageStats)): ?>
            <div class="usage-grid">
                <?php
                $usageItems = [
                    'users'       => 'Gebruikers',
                    'employees'   => 'Medewerkers',
                    'locations'   => 'Locaties',
                    'departments' => 'Afdelingen',
                ];
                foreach ($usageItems as $key => $label):
                    if (!isset($usageStats[$key])) continue;
                    $stat = $usageStats[$key];
                    $cur  = (int)($stat['current']    ?? 0);
                    $max  = (int)($stat['limit']       ?? 0);
                    $pct  = $max > 0 ? min(100, round($cur / $max * 100)) : 0;
                    $fillClass = $pct >= 90 ? 'fill-red' : ($pct >= 70 ? 'fill-yellow' : 'fill-green');
                    $avail = $max - $cur;
                ?>
                <div class="usage-item">
                    <div class="usage-header">
                        <span class="usage-label"><?= $label ?></span>
                        <span class="usage-count"><?= $cur ?> / <?= $max ?></span>
                    </div>
                    <div class="usage-bar-bg">
                        <div class="usage-bar-fill <?= $fillClass ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                    <div class="usage-sub">
                        <?php if ($avail > 0): ?>
                            <?= $avail ?> beschikbaar &middot; <?= $pct ?>% gebruikt
                        <?php elseif ($max === 0): ?>
                            Limiet bereikt
                        <?php else: ?>
                            <span style="color:#e53e3e;">Limiet bereikt (<?= $pct ?>%)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:#a0aec0;font-size:14px;">Geen gebruiksgegevens beschikbaar.</p>
            <?php endif; ?>
        </div>

        <!-- Feature Availability -->
        <div class="card">
            <div class="card-title"><span class="icon">✨</span> Beschikbare Functies</div>
            <div class="feature-list">
                <?php foreach ($featureLabels as $fKey => $fLabel):
                    $available = hasFeature($fKey);
                ?>
                <div class="feature-item">
                    <span class="feature-name"><?= htmlspecialchars($fLabel) ?></span>
                    <?php if ($available): ?>
                        <span class="feat-yes" title="Inbegrepen">&#10003;</span>
                    <?php else: ?>
                        <span class="feat-no" title="Niet beschikbaar in dit pakket">&#10005;</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div><!-- /.two-col -->

    <?php endif; // $licenseInfo ?>

    <!-- ── Available Tiers (uit config) ── -->
    <?php
    // $TIERS direct definiëren — onafhankelijk van admin config
    $TIERS = [
        'starter'      => ['name'=>'Starter',      'limits'=>'3u/10e/1l/3d',    'color'=>'#3498db'],
        'professional' => ['name'=>'Professional',  'limits'=>'5u/25e/3l/6d',    'color'=>'#9b59b6'],
        'business'     => ['name'=>'Business',      'limits'=>'10u/60e/6l/10d',  'color'=>'#e74c3c'],
        'enterprise'   => ['name'=>'Enterprise',    'limits'=>'25u/120e/12l/20d','color'=>'#f39c12'],
        'corporate'    => ['name'=>'Corporate',     'limits'=>'50u/250e/25l/35d','color'=>'#16a085'],
        'unlimited'    => ['name'=>'Unlimited',     'limits'=>'∞/∞/∞/∞',        'color'=>'#2c3e50'],
    ];
    if (!empty($TIERS)):
    ?>
    <div class="card">
        <div class="card-title"><span class="icon">📦</span> Beschikbare Pakketten</div>
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(160px,1fr)); gap:12px; margin-bottom:20px;">
            <?php foreach ($TIERS as $key => $tier):
                $isCurrent = strtolower($key) === strtolower($licenseInfo['license_tier'] ?? '');
            ?>
            <div style="background:<?= $isCurrent ? '#f0fff4' : '#f7fafc' ?>; border:2px solid <?= $isCurrent ? '#48bb78' : '#e2e8f0' ?>; border-radius:10px; padding:16px; text-align:center;">
                <div style="display:inline-block; background:<?= htmlspecialchars($tier['color']) ?>; color:#fff; padding:3px 12px; border-radius:12px; font-size:12px; font-weight:700; margin-bottom:8px;">
                    <?= htmlspecialchars($tier['name']) ?>
                </div>
                <div style="font-size:12px; color:#718096;"><?= htmlspecialchars($tier['limits']) ?></div>
                <?php if ($isCurrent): ?>
                    <div style="margin-top:8px; font-size:11px; font-weight:700; color:#38a169;">✓ Huidig pakket</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="padding:14px 16px; background:#f0f4ff; border-radius:10px; border-left:4px solid #667eea; font-size:13px; color:#4a5568;">
            💡 <strong>Actuele pakketten &amp; prijzen</strong> vindt u altijd op
            <a href="https://peopledisplay.nl" target="_blank" style="color:#667eea; font-weight:600;">peopledisplay.nl</a>.
            Wilt u upgraden? Neem contact op via
            <a href="mailto:support@peopledisplay.nl" style="color:#667eea;">support@peopledisplay.nl</a>.
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.container -->
</body>
</html>
