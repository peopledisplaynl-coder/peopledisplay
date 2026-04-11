<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * Filename: frontpage.php
 * Location: /frontpage.php
 * Version: v2.0 - With anti-cache headers
 * 
 * Welkomstscherm voor gebruikers met navigatie knoppen
 */

// CRITICAL: Prevent caching of authenticated pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

session_start();
require_once __DIR__ . '/includes/db.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ✅ CHECK FOR FORCED LOGOUT
require_once __DIR__ . '/includes/logout_checker.php';

// Get user info from database
$stmt = $db->prepare("SELECT username, display_name, role, can_use_scanner FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$userName = $user['display_name'] ?: $user['username'];
$isAdmin = in_array($user['role'], ['admin', 'superadmin', 'employee_manager', 'user_manager']);
$canUseScanner = (bool)($user['can_use_scanner'] ?? false);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PeopleDisplay - Welkom</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            color: white;
        }
        
        .header h1 {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .header p {
            font-size: 20px;
            opacity: 0.9;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .menu-card {
            background: white;
            border-radius: 16px;
            padding: 35px;
            text-align: center;
            text-decoration: none;
            color: #2d3748;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            cursor: pointer;
        }
        
        .menu-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.2);
        }
        
        .menu-card.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .menu-card.admin {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .menu-card-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .menu-card-title {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .menu-card-description {
            font-size: 14px;
            opacity: 0.8;
            line-height: 1.5;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
        }
        
        .logout-btn {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid rgba(255,255,255,0.3);
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 32px;
            }
            
            .menu-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👋 Welkom, <?php echo htmlspecialchars($userName); ?>!</h1>
            <p>Wat wil je doen?</p>
        </div>
        
        <div class="menu-grid">
            <a href="index.php" class="menu-card primary">
                <div class="menu-card-icon">📱</div>
                <div class="menu-card-title">Aanmeldscherm</div>
                <div class="menu-card-description">
                    Check-in en check-out van medewerkers
                </div>
            </a>
            
            <a href="overzicht.php" class="menu-card">
                <div class="menu-card-icon">👥</div>
                <div class="menu-card-title">Overzicht</div>
                <div class="menu-card-description">
                    Bekijk alle medewerkers en hun status
                </div>
            </a>
            
            <a href="user/profile.php" class="menu-card">
                <div class="menu-card-icon">⚙️</div>
                <div class="menu-card-title">Mijn Profiel</div>
                <div class="menu-card-description">
                    Wijzig je instellingen en voorkeuren
                </div>
            </a>
            
            <?php if ($canUseScanner): ?>
            <a href="scan.php" class="menu-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                <div class="menu-card-icon">📷</div>
                <div class="menu-card-title">QR/Barcode Scanner</div>
                <div class="menu-card-description">
                    Scan badges voor snelle check-in/out
                </div>
            </a>
            <?php endif; ?>
            
            <?php if ($isAdmin): ?>
            <a href="admin/dashboard.php" class="menu-card admin">
                <div class="menu-card-icon">🔧</div>
                <div class="menu-card-title">Beheer</div>
                <div class="menu-card-description">
                    <?php
                    if ($user['role'] === 'superadmin') echo 'Volledige toegang tot het systeem';
                    elseif ($user['role'] === 'admin') echo 'Beheer het systeem';
                    elseif ($user['role'] === 'employee_manager') echo 'Medewerkers beheren';
                    elseif ($user['role'] === 'user_manager') echo 'Medewerkers en gebruikers beheren';
                    ?>
                </div>
            </a>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <a href="logout.php" class="logout-btn">🚪 Uitloggen</a>
        </div>
    </div>
    
    <script>
        // Prevent back button cache issues
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
        
        // Check session validity
        setInterval(function() {
            fetch('api/check_session.php')
                .then(r => r.json())
                .then(data => {
                    if (!data.valid) {
                        alert('Je sessie is verlopen. Je wordt uitgelogd.');
                        window.location.href = 'logout.php';
                    }
                })
                .catch(() => {
                    // Silently fail - don't annoy user
                });
        }, 60000); // Check every minute
    </script>
    
    <!-- FORCE LOGOUT DETECTOR -->
    <script>
    (function() {
        const CHECK_INTERVAL = 10000;
        const API_ENDPOINT = '/api/check_session_status.php';
        let intervalId = null;
        let isRedirecting = false;

        function hasRememberToken() {
            return /(^|; )remember_selector=/.test(document.cookie) && /(^|; )remember_token=/.test(document.cookie);
        }

        function showForcedLogoutOverlay() {
            const overlay = document.createElement('div');
            overlay.innerHTML = '<div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);display:flex;justify-content:center;align-items:center;z-index:999999"><div style="background:white;padding:40px;border-radius:12px;text-align:center"><h2 style="color:#742a2a">⚠️ Je bent uitgelogd</h2><div style="margin:20px auto;width:40px;height:40px;border:4px solid #e2e8f0;border-top-color:#f56565;border-radius:50%;animation:spin 0.8s linear infinite"></div><p>Je bent uitgelogd door een beheerder.</p></div></div><style>@keyframes spin{to{transform:rotate(360deg)}}</style>';
            document.body.appendChild(overlay);
        }

        function showTimeoutOverlay() {
            const overlay = document.createElement('div');
            overlay.innerHTML = '<div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);display:flex;justify-content:center;align-items:center;z-index:999999"><div style="background:white;padding:40px;border-radius:12px;text-align:center"><h2 style="color:#742a2a">⚠️ Je bent uitgelogd</h2><div style="margin:20px auto;width:40px;height:40px;border:4px solid #e2e8f0;border-top-color:#f56565;border-radius:50%;animation:spin 0.8s linear infinite"></div><p>Je sessie is verlopen.</p></div></div><style>@keyframes spin{to{transform:rotate(360deg)}}</style>';
            document.body.appendChild(overlay);
        }

        function handleLogout(forced) {
            if (isRedirecting) return;
            isRedirecting = true;
            try { localStorage.clear(); sessionStorage.clear(); } catch(e) {}

            if (forced) {
                showForcedLogoutOverlay();
                setTimeout(() => window.location.replace('/login.php?forced_logout=1'), 2000);
                return;
            }

            if (hasRememberToken()) {
                window.location.reload();
                return;
            }

            showTimeoutOverlay();
            setTimeout(() => window.location.replace('/login.php'), 2000);
        }

        function checkSession() {
            if (isRedirecting) return;
            fetch(API_ENDPOINT, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (!data.active) {
                        if (data.forced_logout) {
                            handleLogout(true);
                        } else {
                            handleLogout(false);
                        }
                    }
                })
                .catch(() => {});
        }

        function startChecker() {
            if (intervalId) clearInterval(intervalId);
            intervalId = setInterval(checkSession, CHECK_INTERVAL);
        }

        function stopChecker() {
            if (intervalId) {
                clearInterval(intervalId);
                intervalId = null;
            }
        }

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopChecker();
            } else {
                setTimeout(checkSession, 1000);
                startChecker();
            }
        });

        startChecker();
        checkSession();
    })();
    </script>

<script>
// Heartbeat — houdt sessie actief in online gebruikers overzicht
(function() {
    function heartbeat() {
        fetch('/api/heartbeat.php', { method: 'POST', credentials: 'same-origin' }).catch(() => {});
    }
    heartbeat();
    setInterval(heartbeat, 60000); // Elke minuut
})();
</script>
</body>
</html>
