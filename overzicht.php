<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/license_check.php';

$role = $_SESSION['role'] ?? null;

// 🔒 Niet ingelogd? Naar login
if (!$role) {
  header('Location: ' . BASE_PATH . '/login.php');
  exit;
}

// ✅ CHECK FOR FORCED LOGOUT
require_once __DIR__ . '/includes/logout_checker.php';

// ✅ Iedereen kan overzicht zien (users, admins, superadmins)
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#4CAF50">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="PeopleDisplay">
<link rel="apple-touch-icon" href="/images/icons/icon-152x152.png">
<link rel="icon" type="image/png" sizes="32x32" href="/images/icons/icon-32x32.png">
  <meta charset="UTF-8">
  <title>Overzicht Aanwezigen</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="overzicht-style.css">
</head>
<body>

  <!-- HEADER -->
  <header>
    <div class="header-left">
      <a href="<?= BASE_PATH ?>/" class="btn-back">← Terug</a>
    </div>
    <div class="header-center">
      <h1 style="margin:0;font-size:20px;color:white;font-weight:bold;">📋 Overzicht Aanwezigen</h1>
    </div>
    <div class="header-right">
      <div class="header-badges">
        <div id="count-total" class="badge badge-total">Totaal: 0</div>
        <div id="count-in" class="badge badge-in">IN: 0</div>
        <div id="count-bhv" class="badge badge-bhv">BHV: 0</div>
      </div>
    </div>
  </header>

  <!-- FILTERS -->
  <div id="filters" class="overview-filters">
    <input type="text" id="search-input" placeholder="🔍 Zoek op naam...">
    
    <select id="filter-status">
      <option value="">📊 Status (alles)</option>
      <option value="IN">✅ IN</option>
      <option value="PAUZE">🌸 PAUZE</option>
      <option value="THUISWERKEN">💜 THUISWERKEN</option>
      <option value="VAKANTIE">🌿 VAKANTIE</option>
    </select>
    
    <select id="filter-bhv">
      <option value="">🚨 BHV (alles)</option>
      <option value="ja">✅ BHV</option>
      <option value="nee">❌ Niet BHV</option>
    </select>
    
    <select id="filter-locatie">
      <option value="">📍 Locatie (alles)</option>
    </select>
    
    <select id="filter-afdeling">
      <option value="">🏢 Afdeling (alles)</option>
    </select>
    
    <button id="refresh-btn" class="btn-refresh" type="button">🔄 Ververs</button>
  </div>

  <!-- OVERVIEW TABLE -->
  <div id="overview-container">
    <div id="loading-message" class="loading-message">
      <div class="spinner"></div>
      <p>Gegevens laden...</p>
    </div>
    
    <div id="overview-content" style="display:none;">
      <!-- Per locatie: groepen -->
      <div id="overview-groups"></div>
    </div>
    
    <div id="no-data-message" class="no-data-message" style="display:none;">
      <p>😴 Niemand aanwezig op dit moment</p>
    </div>
  </div>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="footer-main">
      <div class="footer-info">
        <span class="footer-refresh">Laatst ververst om <span id="last-refresh">--:--</span></span>
      </div>
      <div class="footer-actions">
        <label class="auto-refresh-toggle">
          <input type="checkbox" id="auto-refresh-checkbox">
          🔄 Auto-refresh (30s)
        </label>
      </div>
    </div>
  </footer>

<script>
  // Config from PHP
  window.__labee_page_type = 'overview';
  window.__labee_base_path = <?= json_encode(BASE_PATH) ?>;
</script>
<script src="<?= BASE_PATH ?>/overzicht.js"></script>
<script src="<?= BASE_PATH ?>/pwa-init.js" defer></script>
<script src="<?= BASE_PATH ?>/ios-install-prompt-fixed.js"></script>

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
        fetch(API_ENDPOINT, { credentials: 'same-origin' }).then(r => r.json()).then(data => {
            if (!data.active) {
                if (data.forced_logout) {
                    handleLogout(true);
                } else {
                    handleLogout(false);
                }
            }
        }).catch(() => {});
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