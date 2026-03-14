<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: index_with_visitors.php
 * LOCATIE:      ROOT (/)
 * UPLOAD NAAR:  /index.php (BACKUP OUDE EERST!)
 * ═══════════════════════════════════════════════════════════════════
 * 
 * PeopleDisplay Aanmeldscherm v2.1
 * MET: Verwachte Bezoekers sectie
 * 
 * Features:
 * - Employee check-in/out (bestaand)
 * - Verwachte bezoekers vandaag (NIEUW!)
 * - Check-in button per bezoeker (NIEUW!)
 * 
 * ═══════════════════════════════════════════════════════════════════
 */

// CRITICAL: Prevent browser caching of authenticated pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Session en auth check
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/license_check.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ✅ CHECK FOR FORCED LOGOUT
require_once __DIR__ . '/includes/logout_checker.php';

// ✅ UPDATE ACTIVITY TRACKING
try {
    require_once __DIR__ . '/includes/session_tracker.php';
    $tracker = new SessionTracker($db);
    $tracker->updateActivity($_SERVER['REQUEST_URI']);
} catch (Exception $e) {
    // Silent fail - don't block page load
    error_log("Activity tracking failed: " . $e->getMessage());
}

$current_user = $_SESSION['username'] ?? 'User';
$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PeopleDisplay - Aanmeldscherm</title>
    <link rel="stylesheet" href="style.css">
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="PeopleDisplay">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" sizes="152x152" href="/images/icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/icons/icon-180x180.png">
    <link rel="apple-touch-icon" sizes="192x192" href="/images/icons/icon-192x192.png">
    
    <!-- Favicons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/images/icons/icon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/icons/icon-16x16.png">
    
    <!-- iOS Install Prompt Script -->
    <script src="/ios-install-prompt-fixed.js"></script>
    <style>
        /* === VISITORS SECTIE STYLING === */
        .visitors-section {
            margin: 30px 0;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .visitors-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            color: white;
        }
        
        .visitors-header h2 {
            font-size: 22px;
            font-weight: 600;
            margin: 0;
        }
        
        .visitors-count {
            background: rgba(255,255,255,0.3);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .visitors-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 15px;
        }
        
        .visitor-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            align-items: flex-start;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .visitor-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .visitor-time {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            min-width: 70px;
            flex-shrink: 0;
        }
        
        .visitor-time .time {
            font-size: 18px;
            font-weight: 700;
            display: block;
        }
        
        .visitor-time .icon {
            font-size: 20px;
            display: block;
            margin-bottom: 5px;
        }
        
        .visitor-info {
            flex: 1;
        }
        
        .visitor-name {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .visitor-company {
            font-size: 13px;
            color: #718096;
            margin-bottom: 8px;
        }
        
        .visitor-contact {
            font-size: 12px;
            color: #4a5568;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 12px;
        }
        
        .visitor-contact::before {
            content: "→";
            font-weight: 700;
            color: #667eea;
        }
        
        .visitor-checkin-btn {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }
        
        .visitor-checkin-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(72, 187, 120, 0.3);
        }
        
        .visitor-checkin-btn:active {
            transform: translateY(0);
        }
        
        .no-visitors {
            background: white;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            color: #718096;
        }
        
        .no-visitors h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
        }
        
        .no-visitors p {
            margin: 0;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .visitors-list {
                grid-template-columns: 1fr;
            }
            
            .visitor-card {
                flex-direction: column;
                align-items: stretch;
            }
            
            .visitor-time {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }
            
            .visitor-time .icon,
            .visitor-time .time {
                display: inline;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header class="header">
        <!-- LINKS: Titel (klein) -->
        <div class="header-left">
            <h1 style="font-size: 18px; margin: 0;">PeopleDisplay</h1>
        </div>
        
        <!-- MIDDEN: Badges (icon only) -->
        <div class="header-center">
            <div class="header-badges">
                <div id="count-in-top" class="badge badge-in"><span class="badge-icon">✓</span></div>
                <div id="count-out-top" class="badge badge-out"><span class="badge-icon">✗</span></div>
                <div id="count-bhv-top" class="badge badge-bhv"><span class="badge-icon">🚨</span></div>
            </div>
        </div>
        
        <!-- RECHTS: Knoppen -->
        <div class="header-right">
            <!-- ═══════════════════════════════════════════════════════════════
                 ✨ SORTEER TOGGLE - COMPACT (A/V ICOON)
                 ═══════════════════════════════════════════════════════════════ -->
            <div id="sort-toggle-container" style="display: none; position: relative;">
                <button id="sort-toggle-btn" class="sort-toggle-compact" title="Sorteervolgorde wijzigen">
                    <svg class="sort-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="4" y1="6" x2="20" y2="6"></line>
                        <line x1="4" y1="12" x2="20" y2="12"></line>
                        <line x1="4" y1="18" x2="14" y2="18"></line>
                    </svg>
                    <span id="sort-letter" class="sort-letter">A</span>
                </button>
                
                <!-- Dropdown menu -->
                <div id="sort-dropdown" class="sort-dropdown-compact">
                    <div class="sort-option" data-sort="achternaam">
                        <span class="sort-option-icon">📋</span>
                        <span class="sort-option-text">Achternaam A→Z</span>
                    </div>
                    <div class="sort-option" data-sort="voornaam">
                        <span class="sort-option-icon">👤</span>
                        <span class="sort-option-text">Voornaam A→Z</span>
                    </div>
                    <div class="sort-option" data-sort="status">
                        <span class="sort-option-icon">✓</span>
                        <span class="sort-option-text">Status (IN eerst)</span>
                    </div>
                </div>
            </div>
            
            <button onclick="window.location.href='overzicht.php'" class="btn btn-secondary">📋 Overzicht</button>
            <button id="fullscreen-btn-header" class="btn btn-primary">🖥️ FULLSCREEN</button>
        </div>
    </header>
    
    <!-- MENU -->
    <div class="controls">
        <button id="toggle-menu-btn">TOON MENU</button>
    </div>
    
    <div id="building-menu" style="display:none;"></div>
    
    <!-- FILTERS - ALTIJD ZICHTBAAR -->
    <div id="filters" class="filters-section">
        <input type="text" id="search-input" placeholder="🔍 Zoek op naam..." class="search-input">
        
        <select id="filter-status" class="filter-select">
            <option value="">📊 Status (alles)</option>
            <option value="IN">IN</option>
            <option value="OUT">OUT</option>
        </select>
        
        <select id="filter-bhv" class="filter-select">
            <option value="">🚨 BHV (alles)</option>
            <option value="ja">Alleen BHV</option>
            <option value="nee">Geen BHV</option>
        </select>
        
        <select id="filter-locatie" class="filter-select">
            <option value="">📍 Locatie (alles)</option>
        </select>
        
        <select id="filter-afdeling" class="filter-select">
            <option value="">🏢 Afdeling (alles)</option>
        </select>
    </div>
    
    <!-- === VISITORS SECTIE === -->
    <style>
        /* Visitors Sectie Styling */
        .visitors-section {
            margin: 30px auto;
            max-width: 1400px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .visitors-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            color: white;
        }
        
        .visitors-header h2 {
            font-size: 22px;
            font-weight: 600;
            margin: 0;
        }
        
        .visitors-count {
            background: rgba(255,255,255,0.3);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .visitors-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 15px;
        }
        
        .visitor-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            align-items: flex-start;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .visitor-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .visitor-time {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            min-width: 70px;
            flex-shrink: 0;
        }
        
        .visitor-time .time {
            font-size: 18px;
            font-weight: 700;
            display: block;
        }
        
        .visitor-time .icon {
            font-size: 20px;
            display: block;
            margin-bottom: 5px;
        }
        
        .visitor-info {
            flex: 1;
        }
        
        .visitor-name {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .visitor-company {
            font-size: 13px;
            color: #718096;
            margin-bottom: 8px;
        }
        
        .visitor-contact {
            font-size: 12px;
            color: #4a5568;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 12px;
        }
        
        .visitor-contact::before {
            content: "→";
            font-weight: 700;
            color: #667eea;
        }
        
        .visitor-checkin-btn {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }
        
        .visitor-checkin-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(72, 187, 120, 0.3);
        }
        
        .visitor-checkin-btn:active {
            transform: translateY(0);
        }
        
        .no-visitors {
            background: white;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            color: #718096;
        }
        
        .no-visitors h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
        }
        
        .no-visitors p {
            margin: 0;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .visitors-list {
                grid-template-columns: 1fr;
            }
            
            .visitor-card {
                flex-direction: column;
                align-items: stretch;
            }
            
            .visitor-time {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }
            
            .visitor-time .icon,
            .visitor-time .time {
                display: inline;
                margin: 0;
            }
        }
    </style>
 <!-- EMPLOYEE CARDS CONTAINER -->
   <div id="employee-list"></div>
   
    <!-- Visitors section wordt dynamisch gemaakt door app.js -->
    
    
    <!-- FOOTER -->
    <footer>
        <div class="footer-info">
            <span class="footer-refresh">Laatst ververst om --:--</span>
        </div>
    </footer>
    
    <!-- SCRIPTS -->
    <script src="app.js"></script>
    <script src="index-refresh.js"></script>
    
    <!-- 🎬 Presentation Controller (idle auto-show) -->
    <script src="presentation-controller.js"></script>
    
    <!-- PWA Installer -->
    <script src="/pwa-installer.js"></script>
    
    <!-- Service Worker Registration (inline) -->
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/service-worker.js')
                .then(reg => console.log('✅ SW registered'))
                .catch(err => console.log('❌ SW failed:', err));
        });
    }
    </script>
    
    <!-- WiFi Auto Check-in (ALLEEN MOBIEL PWA) -->
    <script src="/wifi-auto-checkin.js"></script>
    
    <!-- Employee Onboarding (ALLEEN MOBIEL PWA) -->
    <script src="/employee-onboarding.js"></script>
    
    <script>
        // Fullscreen knop in header
        document.getElementById('fullscreen-btn-header')?.addEventListener('click', function() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen();
            } else {
                document.exitFullscreen();
            }
        });
    </script>
	
    <!-- FORCE LOGOUT DETECTOR - Real-time check every 10 seconds -->
    <script>
    (function() {
        const CHECK_INTERVAL = 10000;
        const API_ENDPOINT = '/api/check_session_status.php';
        let isRedirecting = false;
        
        function checkSession() {
            if (isRedirecting) return;
            fetch(API_ENDPOINT, { method: 'GET', credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (data.forced_logout || !data.active) {
                        console.warn('🚨 Force logout detected!');
                        handleLogout();
                    }
                })
                .catch(err => console.error('Session check failed:', err));
        }
        
        function handleLogout() {
            if (isRedirecting) return;
            isRedirecting = true;
            try { localStorage.clear(); sessionStorage.clear(); } catch(e) {}
            
            const overlay = document.createElement('div');
            overlay.innerHTML = '<div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);display:flex;justify-content:center;align-items:center;z-index:999999;font-family:system-ui,sans-serif"><div style="background:white;padding:40px;border-radius:12px;text-align:center;max-width:400px"><h2 style="margin:0 0 20px 0;color:#742a2a;font-size:24px">⚠️ Je bent uitgelogd</h2><div style="margin:20px auto;width:40px;height:40px;border:4px solid #e2e8f0;border-top-color:#f56565;border-radius:50%;animation:spin 0.8s linear infinite"></div><p style="margin:0 0 20px 0;color:#718096">Je bent uitgelogd door een beheerder.</p><p style="margin:0;color:#718096;font-size:14px">Je wordt doorgestuurd...</p></div></div><style>@keyframes spin{to{transform:rotate(360deg)}}</style>';
            document.body.appendChild(overlay);
            setTimeout(() => window.location.replace('/login.php?forced_logout=1'), 2000);
        }
        
        checkSession();
        setInterval(checkSession, CHECK_INTERVAL);
    })();
    </script>
	
</body></html>
