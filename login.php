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
 * BESTANDSNAAM:  login.php
 * UPLOAD NAAR:   ROOT (/login.php)
 * VERSIE:        FIXED - Better error handling
 * ============================================================================
 */

// Include db.php first (this already starts session!)
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

// DEBUG LOGGING — disable by setting PD_DEBUG_LOG=false in db_config.php or deleting debug_logger.php
if (file_exists(__DIR__ . '/includes/debug_logger.php')) {
    require_once __DIR__ . '/includes/debug_logger.php';
    pd_debug_log('login.php loaded', [
        'method'      => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'session_path' => session_save_path() ?: ini_get('session.save_path') ?: '(php-default)',
        'session_id'  => session_id(),
    ]);
    pd_debug_auth('login_top');
}

$error = '';

// 🆕 Koppeling gebruiker -> eigen medewerkerskaart: een gekoppeld account landt na
// inloggen altijd direct op het aanmeldscherm met de eigen kaart, voor elke rol
// (ook admin/superadmin) — anders heeft koppelen voor die rollen geen effect.
function pd_post_login_target(PDO $db, int $userId, string $role): string {
    // Verplichte wachtwoordwijziging gaat voor alles
    if (!empty($_SESSION['must_change_password'])) {
        return 'force_password_change.php';
    }
    try {
        $stmt = $db->prepare("SELECT linked_employee_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $linked = $stmt->fetchColumn();
        if (!empty($linked)) return 'index.php';
    } catch (PDOException $e) {
        // Kolom bestaat nog niet op deze installatie — gewoon geen koppeling aannemen.
    }
    return in_array($role, ['admin', 'superadmin']) ? 'admin/dashboard.php' : 'frontpage.php';
}

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . pd_post_login_target($db, (int)$_SESSION['user_id'], $_SESSION['role'] ?? 'user'));
    exit;
}

// ✅ CACHE PREVENTION (prevent back button after logout)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// ✅ LOGOUT MESSAGE
$logoutMessage = '';
if (isset($_GET['logout'])) {
    $logoutMessage = '<div style="background: #c6f6d5; border: 2px solid #48bb78; padding: 12px; border-radius: 8px; margin-bottom: 20px; color: #22543d; font-size: 14px; text-align: center;">✅ Je bent succesvol uitgelogd</div>';
}

// ✅ FORCED LOGOUT MESSAGE
if (isset($_GET['forced_logout'])) {
    $logoutMessage = '<div style="background: #fed7d7; border: 2px solid #f56565; padding: 12px; border-radius: 8px; margin-bottom: 20px; color: #742a2a; font-size: 14px; text-align: center;">⚠️ Je bent uitgelogd door een beheerder</div>';
}

// Attempt remember-me auto-login before showing the login form.
if (function_exists('pd_try_remember_me_login') && pd_try_remember_me_login($db)) {
    header('Location: ' . pd_post_login_target($db, (int)$_SESSION['user_id'], $_SESSION['role'] ?? 'user'));
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember_me']);
    
    if ($username && $password) {
        $waitSeconds = pd_login_wait_seconds($db, $username);
        if ($waitSeconds > 0) {
            // Geblokkeerd: het wachtwoord wordt niet eens gecontroleerd
            pd_login_record($db, $username, false, 'blocked');
            $error = pd_login_blocked_message($waitSeconds);
        } else {
        try {
            pd_security_ensure_schema($db);
            $stmt = $db->prepare("SELECT id, username, password_hash, display_name, role, active, must_change_password FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Ook bij een onbekende gebruikersnaam een wachtwoordcontrole doen, zodat de
            // reactietijd niets verraadt over welke accounts bestaan.
            $pdDummyHash = '$2y$10$wwV4le6iIVQbHIxdhHX7B.X25K.JGZbiSvWnG.T8ErgLmJw.13TXi';
            $passwordOk = password_verify($password, $user ? $user['password_hash'] : $pdDummyHash);

            if ($user && $passwordOk) {
                if ($user['active']) {
                    pd_login_record($db, $username, true, 'login');
                    pd_login_start_session($db, $user);

                    try {
                        $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        $updateStmt->execute([$user['id']]);
                    } catch (Exception $e) {
                        error_log("Last login update failed: " . $e->getMessage());
                    }

                    // START SESSION TRACKING
                    try {
                        require_once __DIR__ . '/includes/session_tracker.php';
                        $tracker = new SessionTracker($db);
                        $tracker->startSession($user['id']);
                    } catch (Exception $e) {
                        error_log("Session tracking start failed: " . $e->getMessage());
                    }

                    // Remember me: token in de database + cookies (Secure/HttpOnly/SameSite)
                    if ($remember) {
                        try {
                            $checkTable = $db->query("SHOW TABLES LIKE 'remember_tokens'");
                            if ($checkTable && $checkTable->rowCount() > 0) {
                                $selector = bin2hex(random_bytes(16));
                                $token = bin2hex(random_bytes(32));
                                $hashedToken = hash('sha256', $token);
                                $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days

                                $tokenStmt = $db->prepare("
                                    INSERT INTO remember_tokens (user_id, selector, token, expires_at)
                                    VALUES (?, ?, ?, ?)
                                ");
                                $tokenStmt->execute([$user['id'], $selector, $hashedToken, $expiresAt]);

                                $cookieExpire = time() + (30 * 24 * 60 * 60);
                                pd_set_remember_cookie('remember_selector', $selector, $cookieExpire);
                                pd_set_remember_cookie('remember_token', $token, $cookieExpire);
                            }
                        } catch (Exception $e) {
                            error_log("Remember me token creation failed: " . $e->getMessage());
                        }
                    }

                    $pdRedirectTarget = pd_post_login_target($db, (int)$user['id'], $user['role']);
                    if (function_exists('pd_debug_log')) {
                        pd_debug_log('LOGIN SUCCESS — redirecting', [
                            'user_id'  => $_SESSION['user_id'] ?? '?',
                            'role'     => $_SESSION['role'] ?? '?',
                            'username' => $_SESSION['username'] ?? '?',
                            'session_path' => session_save_path() ?: '(php-default)',
                        ]);
                    }
                    if (function_exists('pd_debug_redirect')) { pd_debug_redirect($pdRedirectTarget); }
                    header('Location: ' . $pdRedirectTarget);
                    exit;
                } else {
                    pd_login_record($db, $username, false, 'inactive');
                    $error = 'Account is gedeactiveerd. Neem contact op met de beheerder.';
                }
            } else {
                pd_login_record($db, $username, false, $user ? 'bad_password' : 'unknown_user');
                usleep(random_int(150000, 350000));
                $error = 'Ongeldige gebruikersnaam of wachtwoord.';
            }
        } catch (PDOException $e) {
            // Database error - be more specific
            error_log("Login database error: " . $e->getMessage());
            $error = 'Database fout. Neem contact op met de beheerder.';
        } catch (Exception $e) {
            // Other error
            error_log("Login error: " . $e->getMessage());
            $error = 'Er ging iets mis bij het inloggen. Probeer het opnieuw of neem contact op met de beheerder.';
        }
        } // einde: niet geblokkeerd
    } else {
        $error = 'Vul alle velden in.';
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Inloggen - PeopleDisplay</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            width: 100%;
            max-width: 420px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            font-size: 28px;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: #718096;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.2s;
        }
        
        .form-group input[type="text"]:focus,
        .form-group input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
            padding: 12px;
            background: #f7fafc;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
        }
        
        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .remember-me label {
            font-size: 14px;
            color: #2d3748;
            cursor: pointer;
            user-select: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .remember-me-info {
            font-size: 12px;
            color: #718096;
            margin-top: 4px;
            padding-left: 26px;
        }
        
        .error-message {
            background: #fed7d7;
            color: #c53030;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #c53030;
        }
        
        .login-button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .login-button:active {
            transform: translateY(0);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
            color: #718096;
            font-size: 13px;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
            
            .login-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 PeopleDisplay</h1>
            <p>Log in om door te gaan</p>
        </div>
        
        <?php echo $logoutMessage; ?>
        
        <?php if ($error): ?>
            <div class="error-message">
                ⚠️ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Gebruikersnaam</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    required 
                    autofocus
                    autocomplete="username"
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                >
            </div>
            
            <div class="form-group">
                <label for="password">Wachtwoord</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    autocomplete="current-password"
                >
            </div>
            
            <div class="remember-me">
                <input 
                    type="checkbox" 
                    id="remember_me" 
                    name="remember_me"
                    value="1"
                >
                <div>
                    <label for="remember_me">
                        💚 Blijf ingelogd (30 dagen)
                    </label>
                    <div class="remember-me-info">
                        Vink aan voor kiosk/tablet gebruik
                    </div>
                </div>
            </div>
            
            <button type="submit" class="login-button">
                Inloggen
            </button>
        </form>
        
        <div class="login-footer">
            PeopleDisplay v2.0 © <?php echo date('Y'); ?>
        </div>
    </div>
    
    <script>
    // Prevent back button after logout
    window.history.pushState(null, "", window.location.href);
    window.onpopstate = function() {
        window.history.pushState(null, "", window.location.href);
    };
    </script>
</body>
</html>
