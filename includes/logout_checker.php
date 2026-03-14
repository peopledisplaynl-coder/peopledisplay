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
            
            // Geen actieve sessie gevonden → FORCE LOGOUT
            if (!$active_session) {
                
                // Log de force logout
                error_log("Session inactive (force logged out) for user_id: " . $_SESSION['user_id']);
                
                // Vernietig de sessie VOLLEDIG
                $_SESSION = array();
                
                // Vernietig sessie cookie
                if (isset($_COOKIE[session_name()])) {
                    setcookie(session_name(), '', time()-3600, '/');
                }
                
                // Vernietig sessie
                session_destroy();
                
                // CRITICAL: Stop ALL output en clear buffer
                if (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Probeer PHP redirect
                if (!headers_sent()) {
                    header('Location: /login.php?forced_logout=1');
                    exit;
                }
                
                // Fallback: Toon logout pagina met auto-redirect
                ?>
                <!DOCTYPE html>
                <html lang="nl">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta http-equiv="refresh" content="0;url=/login.php?forced_logout=1">
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
                        <p>Je bent uitgelogd door een beheerder.</p>
                        <p>Je wordt doorgestuurd naar de login pagina...</p>
                        <p><a href="/login.php?forced_logout=1" class="btn">→ Direct naar login</a></p>
                    </div>
                    
                    <script>
                        // Force redirect after 2 seconds
                        setTimeout(function() {
                            window.location.replace('/login.php?forced_logout=1');
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
