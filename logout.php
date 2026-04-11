<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: logout.php
 * LOCATIE:      ROOT (/)
 * VERSIE:       v2.0 - Complete session + client state cleanup
 * ═══════════════════════════════════════════════════════════════════
 */

// CRITICAL: Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Past date

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Store username before destroying (for logging/message)
$username = $_SESSION['username'] ?? 'Unknown';

// ✅ END SESSION TRACKING (before destroying session)
try {
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/session_tracker.php';
    $tracker = new SessionTracker($db);
    $tracker->endSession();
} catch (Exception $e) {
    // Silent fail
    error_log("Session tracking end failed: " . $e->getMessage());
}

// Destroy ALL session data
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Delete remember me cookies if they exist
if (isset($_COOKIE['remember_selector'])) {
    setcookie('remember_selector', '', time() - 3600, '/', '', false, true);
}
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// Optional: Clean up remember tokens from database
try {
    require_once __DIR__ . '/includes/db.php';
    
    if (isset($db)) {
        $stmt = $db->prepare("DELETE FROM remember_tokens WHERE username = ? AND expires_at < NOW()");
        $stmt->execute([$username]);
    }
} catch (Exception $e) {
    // Silent fail - not critical
}

// Redirect with JavaScript cleanup
$timestamp = time();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uitloggen...</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .logout-message {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 400px;
        }
        .logout-message h1 {
            margin: 0 0 20px 0;
            color: #2d3748;
            font-size: 24px;
        }
        .logout-message p {
            margin: 0;
            color: #718096;
            font-size: 16px;
        }
        .spinner {
            margin: 20px auto;
            width: 40px;
            height: 40px;
            border: 4px solid #e2e8f0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="logout-message">
        <h1>✓ Uitgelogd</h1>
        <div class="spinner"></div>
        <p>Je wordt doorgestuurd naar de login pagina...</p>
    </div>

    <script>
        // CRITICAL: Clear ALL client-side state
        
        // 1. Clear localStorage (incl. temp locations)
        try {
            localStorage.clear();
            console.log('✅ localStorage cleared');
        } catch (e) {
            console.warn('localStorage clear failed:', e);
        }
        
        // 2. Clear sessionStorage
        try {
            sessionStorage.clear();
            console.log('✅ sessionStorage cleared');
        } catch (e) {
            console.warn('sessionStorage clear failed:', e);
        }
        
        // 3. Unregister service worker (if exists)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(function(registrations) {
                for (let registration of registrations) {
                    registration.unregister().then(function(success) {
                        console.log('✅ Service worker unregistered:', success);
                    });
                }
            });
        }
        
        // 4. Clear all caches
        if ('caches' in window) {
            caches.keys().then(function(cacheNames) {
                return Promise.all(
                    cacheNames.map(function(cacheName) {
                        return caches.delete(cacheName);
                    })
                );
            }).then(function() {
                console.log('✅ All caches cleared');
            });
        }
        
        // 5. Redirect to login after 2 seconds
        setTimeout(function() {
            // Use replace to prevent back button issues
            window.location.replace('login.php?logout=success&t=<?php echo $timestamp; ?>');
        }, 2000);
        
        // 6. Prevent back button
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };
    </script>
</body>
</html>
