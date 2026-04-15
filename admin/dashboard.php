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
 * BESTANDSNAAM:  dashboard.php
 * UPLOAD NAAR:   /admin/dashboard.php (OVERSCHRIJF)
 * DATUM:         2026-02-15
 * VERSIE:        v2.0 - Modern Design + Kiosk Tokens
 * ============================================================================
 */

// Voorkom browser caching van het dashboard
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/db.php'; // db.php calls session_start() after setting session path
require_once __DIR__ . '/../includes/license_check.php';
require_once __DIR__ . '/../includes/version.php';
require_once __DIR__ . '/../includes/update_check.php';
require_once __DIR__ . '/../includes/migrations.php';

$updateInfo = checkForUpdates();

// DEBUG LOGGING — disable by setting PD_DEBUG_LOG=false in db_config.php or deleting debug_logger.php
if (file_exists(__DIR__ . '/../includes/debug_logger.php')) {
    require_once __DIR__ . '/../includes/debug_logger.php';
    pd_debug_log('dashboard.php loaded', [
        'session_path' => session_save_path() ?: ini_get('session.save_path') ?: '(php-default)',
        'session_id'   => session_id(),
        'user_id_set'  => isset($_SESSION['user_id']) ? 'YES' : 'NO',
        'role'         => $_SESSION['role'] ?? '(not set)',
    ]);
    pd_debug_session();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    if (function_exists('pd_debug_redirect')) {
        pd_debug_redirect('../login.php [AUTH FAILED: user_id not in session]');
    }
    header('Location: ../login.php');
    exit;
}

// Check if user is admin or superadmin
$userRole = $_SESSION['role'] ?? 'user';
if (!in_array($userRole, ['admin', 'superadmin', 'employee_manager', 'user_manager'])) {
    header('Location: ../frontpage.php');
    exit;
}

$currentUser = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Admin';

// Admin feature checks voor dashboard tegels
$canManageLocations      = hasAdminFeature('manage_locations');
$canManageDepartments    = hasAdminFeature('manage_departments');
$canManageLocationsOrder = hasAdminFeature('manage_locations_order');
$canManageDepartmentsOrder = hasAdminFeature('manage_departments_order');
$canManageKiosk          = hasAdminFeature('manage_kiosk_tokens');
$canManageVisitors       = hasAdminFeature('manage_visitors');
$canManageBadges         = hasAdminFeature('manage_badges');
$canBulkActions          = hasAdminFeature('manage_bulk_actions');
$canViewAuditLog         = hasAdminFeature('view_audit_log');
$canManageConfig         = hasAdminFeature('manage_system_config');
$canManageSubstatus      = hasAdminFeature('manage_substatus_dates');
$canManageUsers          = hasAdminFeature('manage_users');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PeopleDisplay</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideDown 0.5s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header-left h1 {
            font-size: 28px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 6px;
            font-weight: 700;
        }
        
        .header-left p {
            color: #718096;
            font-size: 14px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .migration-badge {
            display: inline-block;
            background: rgba(72, 187, 120, 0.12);
            color: #2f855a;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid rgba(72, 187, 120, 0.3);
            margin-top: 8px;
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #f56565 0%, #c53030 100%);
            color: white;
            padding: 10px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(245, 101, 101, 0.3);
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 101, 101, 0.4);
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
            animation: fadeIn 0.6s ease-out 0.2s both;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .menu-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-decoration: none;
            color: #2d3748;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .menu-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s;
        }
        
        .menu-card:hover::before {
            transform: scaleX(1);
        }
        
        .menu-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
            border-color: rgba(102, 126, 234, 0.3);
        }
        
        .menu-icon {
            font-size: 32px;
            margin-bottom: 12px;
            display: block;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }
        
        .menu-card h3 {
            font-size: 18px;
            margin-bottom: 6px;
            color: #2d3748;
            font-weight: 700;
        }
        
        .menu-card p {
            font-size: 13px;
            color: #718096;
            line-height: 1.4;
        }
        
        .section-header {
            color: white;
            font-size: 20px;
            font-weight: 700;
            margin: 24px 0 16px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-header:first-of-type {
            margin-top: 0;
        }
        
        .section-header::before {
            content: '';
            width: 4px;
            height: 24px;
            background: white;
            border-radius: 2px;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .user-info {
                text-align: center;
                margin-top: 20px;
            }
            
            .menu-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Update notification banner */
        .update-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            box-shadow: 0 4px 16px rgba(102,126,234,0.4);
            animation: slideDown 0.4s ease-out;
        }
        .update-banner .ub-icon  { font-size: 22px; }
        .update-banner .ub-text  { flex: 1; font-size: 14px; font-weight: 500; }
        .update-banner .ub-text strong { font-weight: 700; }
        .badge-critical { background: #ff5252; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; margin-left: 8px; }
        .ub-btn { background: white; color: #667eea; padding: 7px 16px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 13px; transition: transform 0.15s; white-space: nowrap; }
        .ub-btn:hover { transform: scale(1.04); }
        .ub-link { color: rgba(255,255,255,0.85); text-decoration: underline; font-size: 13px; white-space: nowrap; }
        .ub-link:hover { color: white; }
        .ub-dismiss { background: transparent; border: 1.5px solid rgba(255,255,255,0.6); color: white; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; font-size: 16px; line-height: 1; padding: 0; flex-shrink: 0; transition: background 0.15s; }
        .ub-dismiss:hover { background: rgba(255,255,255,0.2); }

        /* Dashboard footer */
        .dashboard-footer {
            text-align: center;
            padding: 24px 0 8px;
            color: #a0aec0;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .dashboard-footer a { color: #667eea; text-decoration: none; }
        .dashboard-footer a:hover { text-decoration: underline; }
        .dashboard-footer .sep { color: #e2e8f0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <h1>🎛️ Admin Dashboard</h1>
                <p>Welkom, <?php echo htmlspecialchars($currentUser); ?>! Beheer je PeopleDisplay systeem.</p>
            </div>
            <div class="user-info">
                <div class="user-badge">
                    <?php
                    if ($userRole === 'superadmin') echo '⭐ SuperAdmin';
                    elseif ($userRole === 'admin') echo '🔧 Admin';
                    elseif ($userRole === 'employee_manager') echo '👷 Medewerker Beheerder';
                    elseif ($userRole === 'user_manager') echo '👥 Gebruiker Beheerder';
                    else echo '🔧 Admin';
                    ?>
                </div>
                <?php if (!empty($pd_migrations_status)): ?>
                    <div class="migration-badge" title="<?= htmlspecialchars($pd_migrations_status) ?>">
                        ✅ <?= htmlspecialchars($pd_migrations_status) ?>
                    </div>
                <?php endif; ?>
                <br>
                <a href="../logout.php" class="logout-btn">🚪 Uitloggen</a>
            </div>
        </div>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'no_permission'): ?>
        <div class="alert alert-error" style="margin-bottom: 20px; background: #fff5f5; border: 1px solid #feb2b2; padding: 12px 16px; border-radius: 8px; color: #c53030;">
            <strong style="display: block; margin-bottom: 6px;">⛔ Geen Toegang</strong>
            Je hebt geen rechten voor deze functie. Neem contact op met de SuperAdmin als je denkt dat dit een fout is.
        </div>
        <?php endif; ?>

        <?php if (!empty($updateInfo) && $updateInfo['available']): ?>
        <div class="update-banner" id="updateBanner">
            <span class="ub-icon">🚀</span>
            <span class="ub-text">
                Nieuwe versie <strong><?= htmlspecialchars($updateInfo['version']) ?></strong> beschikbaar!
                <?php if ($updateInfo['critical']): ?><span class="badge-critical">BELANGRIJK</span><?php endif; ?>
                <?php if (!empty($updateInfo['message'])): ?> — <?= htmlspecialchars($updateInfo['message']) ?><?php endif; ?>
            </span>
            <a href="<?= htmlspecialchars($updateInfo['changelog_url']) ?>" class="ub-link" target="_blank">Wat is nieuw?</a>
            <a href="<?= htmlspecialchars($updateInfo['download_url']) ?>" class="ub-btn" target="_blank">Download</a>
            <button class="ub-dismiss" title="Verberg melding" onclick="dismissUpdateBanner('<?= htmlspecialchars($updateInfo['version']) ?>')">&#x2715;</button>
        </div>
        <script>
        // Verberg banner direct als deze versie al dismissed is — voorkomt flits
        (function() {
            try {
                var dismissed = localStorage.getItem('pd_dismissed_update');
                var current = '<?= htmlspecialchars($updateInfo['version'] ?? '') ?>';
                if (dismissed === current) {
                    var el = document.getElementById('updateBanner');
                    if (el) el.style.display = 'none';
                }
            } catch(e) {}
        })();
        </script>
        <?php endif; ?>

        <div class="section-header">📍 Navigatie</div>
        <div class="menu-grid">
            <a href="../frontpage.php" class="menu-card navigation">
                <div class="menu-icon">🏠</div>
                <h3>Frontpage</h3>
                <p>Ga naar het welkomstscherm</p>
            </a>
            
            <a href="../index.php" class="menu-card navigation">
                <div class="menu-icon">👥</div>
                <h3>Aanmeldscherm</h3>
                <p>Medewerker check-in en check-out</p>
            </a>
            
            <a href="../overzicht.php" class="menu-card navigation">
                <div class="menu-icon">📊</div>
                <h3>Overzicht</h3>
                <p>Live overzicht van alle medewerkers</p>
            </a>
        </div>
        
        <div class="section-header">👥 Personeelsbeheer</div>
        <div class="menu-grid">
            <a href="employees_manage.php" class="menu-card employees">
                <div class="menu-icon">👤</div>
                <h3>Medewerkers</h3>
                <p>Beheer medewerkers en hun gegevens</p>
            </a>
            
            <?php if (in_array($userRole, ['admin', 'superadmin', 'user_manager'])): ?>
            <a href="users_manage.php" class="menu-card users">
                <div class="menu-icon">🔐</div>
                <h3>Gebruikers</h3>
                <p>Beheer admin gebruikers en rechten</p>
            </a>
            <?php endif; ?>

            <?php if ($canManageBadges): ?>
            <a href="badges_generate.php" class="menu-card badges">
                <div class="menu-icon">🎫</div>
                <h3>Badge Generator</h3>
                <p>Genereer employee badges met QR codes</p>
            </a>
            <?php endif; ?>

            <?php if ($canBulkActions): ?>
            <a href="bulk_actions.php" class="menu-card bulk">
                <div class="menu-icon">🌙</div>
                <h3>Bulk Acties</h3>
                <p>Zet alle medewerkers op UIT</p>
            </a>
            <?php endif; ?>
        </div>
        
        <?php if (in_array($userRole, ['admin', 'superadmin'])): ?>
        <div class="section-header">🏢 Bezoekers & Locaties</div>
        <div class="menu-grid">
            <?php if (hasFeature('visitor_management')): ?>
            <a href="visitors_manage.php" class="menu-card visitors">
                <div class="menu-icon">🎫</div>
                <h3>Bezoekers</h3>
                <p>Beheer bezoekers en aanmeldingen</p>
            </a>

            <?php if ($canManageVisitors): ?>
            <a href="visitor_email_config.php" class="menu-card visitors">
                <div class="menu-icon">📧</div>
                <h3>Bezoeker Emails</h3>
                <p>Configureer email notificaties</p>
            </a>
            <?php endif; ?>

            <a href="../visitor_register.php" class="menu-card visitors" target="_blank">
                <div class="menu-icon">🔗</div>
                <h3>Registratieformulier</h3>
                <p>Open het bezoekersregistratie formulier</p>
            </a>
            <?php else: ?>
            <div class="menu-card visitors" style="opacity:0.5;cursor:not-allowed;" title="Niet beschikbaar in uw pakket">
                <div class="menu-icon">🎫</div>
                <h3>Bezoekers <span style="font-size:11px;background:#eee;color:#888;padding:1px 6px;border-radius:10px;font-weight:400;">Upgrade</span></h3>
                <p>Upgrade naar Professional voor bezoekersbeheer</p>
            </div>
            <?php endif; ?>

            <?php if ($canManageLocations): ?>
            <a href="locations_manage.php" class="menu-card locations">
                <div class="menu-icon">📍</div>
                <h3>Locaties</h3>
                <p>Beheer locaties en vestigingen</p>
            </a>
            <?php endif; ?>

            <?php if ($canManageLocationsOrder): ?>
            <a href="locations_order.php" class="menu-card locations">
                <div class="menu-icon">🔢</div>
                <h3>Locatie Volgorde</h3>
                <p>Sorteer locaties in menu</p>
            </a>
            <?php endif; ?>

            <?php if ($canManageDepartments): ?>
            <a href="afdelingen_manage.php" class="menu-card departments">
                <div class="menu-icon">🏢</div>
                <h3>Afdelingen</h3>
                <p>Beheer afdelingen en teams</p>
            </a>
            <?php endif; ?>

            <?php if ($canManageDepartmentsOrder): ?>
            <a href="afdelingen_order.php" class="menu-card departments">
                <div class="menu-icon">🔢</div>
                <h3>Afdelingen Volgorde</h3>
                <p>Sorteer afdelingen in menu</p>
            </a>
            <?php endif; ?>
        </div>
        
        <div class="section-header">⚙️ Systeem Configuratie</div>
        <div class="menu-grid">
            <a href="online_users.php" class="menu-card config">
                <div class="menu-icon">🌐</div>
                <h3>Online Gebruikers</h3>
                <p>Bekijk wie er nu online is</p>
            </a>

            <?php if ($canManageConfig): ?>
            <a href="config_manage.php" class="menu-card config">
                <div class="menu-icon">⚙️</div>
                <h3>Systeemconfiguratie</h3>
                <p>Algemene systeem instellingen</p>
            </a>
            <?php endif; ?>

            <?php if ($canManageKiosk && hasFeature('kiosk_mode')): ?>
            <a href="kiosk_tokens_manage.php" class="menu-card config">
                <div class="menu-icon">🖥️</div>
                <h3>Kiosk Tokens</h3>
                <p>Auto-login tokens voor kiosk PC's</p>
            </a>
            <?php else: ?>
            <div class="menu-card config" style="opacity:0.5;cursor:not-allowed;" title="Niet beschikbaar in uw pakket">
                <div class="menu-icon">🖥️</div>
                <h3>Kiosk Tokens <span style="font-size:11px;background:#eee;color:#888;padding:1px 6px;border-radius:10px;font-weight:400;">Upgrade</span></h3>
                <p>Upgrade naar Business voor kiosk modus</p>
            </div>
            <?php endif; ?>

            <?php if ($canViewAuditLog): ?>
            <a href="audit_log.php" class="menu-card config">
                <div class="menu-icon">📝</div>
                <h3>Audit Log</h3>
                <p>Bekijk systeem activiteiten</p>
            </a>
            <?php endif; ?>

            <?php if ($canManageSubstatus): ?>
            <a href="substatus_date_settings.php" class="menu-card config">
                <div class="menu-icon">📅</div>
                <h3>Sub-Status Datum Settings</h3>
                <p>Configureer datum/tijd per button</p>
            </a>
            <?php endif; ?>

            <a href="license_management.php" class="menu-card config">
                <div class="menu-icon">🔑</div>
                <h3>Licentiebeheer</h3>
                <p>Licentiestatus, gebruik en pakketten</p>
            </a>
        </div>

        <?php endif; ?>

        <div class="dashboard-footer">
            <span>PeopleDisplay v<?= PEOPLEDISPLAY_VERSION ?></span>
            <span class="sep">•</span>
            <a href="https://peopledisplay.nl" target="_blank">peopledisplay.nl</a>
            <span class="sep">•</span>
            <a href="license_management.php">Licentiebeheer</a>
        </div>
    </div>

<script>
function dismissUpdateBanner(version) {
    // Direct verbergen in UI
    var el = document.getElementById('updateBanner');
    if (el) el.style.display = 'none';

    // Opslaan in localStorage zodat banner wegblijft ook na cache
    try { localStorage.setItem('pd_dismissed_update', version); } catch(e) {}

    // Ook server-side opslaan (best effort)
    fetch('api/dismiss_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ version: version })
    }).catch(() => {});
}
</script>
</body>
</html>
