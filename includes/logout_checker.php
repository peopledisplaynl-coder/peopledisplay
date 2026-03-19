<?php
/**
 * ============================================================
 * LOGOUT CHECKER - Force Logout Detection v3.0
 * ============================================================
 * Checkt bij elke page load of de sessie nog actief is
 * Als inactive → Auto logout + redirect naar login
 * 
 * v3.0: Met output buffering + JavaScript fallback
 * 
 * GEBRUIK: Voeg toe aan ELKE pagina NA session_start():
 * require_once __DIR__ . '/includes/logout_checker.php';
 * ============================================================
 */

// Alleen uitvoeren als sessie actief is EN user ingelogd
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    
    // Check of database beschikbaar is
    if (!isset($db)) {
        // Probeer db.php te laden
        $possible_db_paths = [
            __DIR__ . '/db.php',
            __DIR__ . '/../includes/db.php',
            dirname(__DIR__) . '/includes/db.php'
        ];
        
        foreach ($possible_db_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                break;
            }
        }
    }
    
    // Als we database hebben, check sessie status
    if (isset($db)) {
        try {
            // Check of deze sessie nog actief is in database
            $stmt = $db->prepare("
                SELECT is_active
                FROM user_sessions 
                WHERE user_id = ? 
                AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $active_session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Geen actieve sessie gevonden → force logout of timeout
            if (!$active_session) {

                // Determine whether this was a forced logout or simply a session timeout.
                $forcedLogout = false;
                $timeoutSeconds = 15 * 60; // matches SessionTracker default
                $sessionId = session_id();
                if (!empty($_COOKIE[session_name()])) {
                    $sessionId = $_COOKIE[session_name()];
                }

                try {
                    $stmt2 = $db->prepare("SELECT is_active, last_activity FROM user_sessions WHERE session_id = ? LIMIT 1");
                    $stmt2->execute([$sessionId]);
                    $row = $stmt2->fetch(PDO::FETCH_ASSOC);

                    if ($row) {
                        $lastActivity = strtotime($row['last_activity']);
                        $inactiveSeconds = time() - $lastActivity;
                        if ($inactiveSeconds < $timeoutSeconds) {
                            // Recent activity but still inactive => likely forced logout
                            $forcedLogout = true;
                        }
                    }
                } catch (Exception $e) {
                    // Ignore - we still want to log the session out.
                }

                // Log the event for debugging
                error_log("Session inactive (force logged out) for user_id: " . $_SESSION['user_id'] . " (forced=" . ($forcedLogout ? 'yes' : 'no') . ")");

                // Destroy session completely
                $_SESSION = array();

                // Destroy session cookie
                if (isset($_COOKIE[session_name()])) {
                    setcookie(session_name(), '', time() - 3600, '/');
                }

                // Destroy session
                session_destroy();

                // Clear output buffer
                if (ob_get_level()) {
                    ob_end_clean();
                }

                // Redirect to login; only show forced-logout UI when explicitly forced.
                $redirectUrl = '/login.php' . ($forcedLogout ? '?forced_logout=1' : '');
                if (!headers_sent()) {
                    header('Location: ' . $redirectUrl);
                    exit;
                }

                // Fallback: Show a simple logout message with redirect
                ?>
                <!DOCTYPE html>
                <html lang="nl">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($redirectUrl) ?>">
                    <title>Uitgelogd...</title>
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
                        .logout-box {
                            background: white;
                            padding: 40px;
                            border-radius: 12px;
                            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                            text-align: center;
                            max-width: 400px;
                        }
                        .logout-box h1 {
                            margin: 0 0 20px 0;
                            color: #742a2a;
                            font-size: 24px;
                        }
                        .logout-box p {
                            margin: 0 0 20px 0;
                            color: #718096;
                        }
                        .spinner {
                            margin: 20px auto;
                            width: 40px;
                            height: 40px;
                            border: 4px solid #e2e8f0;
                            border-top-color: #f56565;
                            border-radius: 50%;
                            animation: spin 0.8s linear infinite;
                        }
                        @keyframes spin {
                            to { transform: rotate(360deg); }
                        }
                        .btn {
                            display: inline-block;
                            padding: 12px 24px;
                            background: #667eea;
                            color: white;
                            text-decoration: none;
                            border-radius: 6px;
                            font-weight: 600;
                        }
                    </style>
                </head>
                <body>
                    <div class="logout-box">
                        <h1>⚠️ Je bent uitgelogd</h1>
                        <div class="spinner"></div>
                        <p><?= $forcedLogout ? 'Je bent uitgelogd door een beheerder.' : 'Je sessie is verlopen.' ?></p>
                        <p>Je wordt doorgestuurd naar de login pagina...</p>
                        <p><a href="<?= htmlspecialchars($redirectUrl) ?>" class="btn">→ Direct naar login</a></p>
                    </div>
                    
                    <script>
                        // Force redirect after 2 seconds
                        setTimeout(function() {
                            window.location.replace('<?= htmlspecialchars($redirectUrl) ?>');
                        }, 2000);
                        
                        // Clear all storage
                        try {
                            localStorage.clear();
                            sessionStorage.clear();
                        } catch(e) {}
                    </script>
                </body>
                </html>
                <?php
                exit;
            }
            
        } catch (PDOException $e) {
            // Silent fail - log error maar break applicatie niet
            error_log("Logout checker error: " . $e->getMessage());
        }
    }
}
