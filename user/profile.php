<?php
/**
 * BESTANDSNAAM: profile.php
 * LOCATIE: /user/profile.php
 * VERSIE: 2.1 - Foto URL wijzigen toegevoegd
 */

session_start();

// CRITICAL: NO CACHE HEADERS
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/license_check.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// ✅ CHECK FOR FORCED LOGOUT
require_once __DIR__ . '/../includes/logout_checker.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';
$justSavedButtons = false;

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: ../logout.php');
    exit;
}

// Get global button config to check if custom names are allowed
$configStmt = $db->query("SELECT button1_name, button2_name, button3_name, allow_user_button_names FROM config WHERE id = 1");
$globalButtons = $configStmt->fetch(PDO::FETCH_ASSOC);
$allowCustomNames = $globalButtons && $globalButtons['allow_user_button_names'] == 1;

// Get custom button names (only if allowed)
$customNames = null;
if ($allowCustomNames) {
    $customNamesStmt = $db->prepare("
        SELECT button1_name, button2_name, button3_name 
        FROM user_button_names 
        WHERE user_id = ?
    ");
    $customNamesStmt->execute([$user_id]);
    $customNames = $customNamesStmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $display_name = trim($_POST['display_name']);
        $email = trim($_POST['email']);
        
        if (empty($display_name)) {
            $error = 'Naam mag niet leeg zijn';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET display_name = ?, email = ?
                    WHERE id = ?
                ");
                $stmt->execute([$display_name, $email, $user_id]);
                
                $message = 'Profiel bijgewerkt!';
                $user['display_name'] = $display_name;
                $user['email'] = $email;
            } catch (PDOException $e) {
                $error = 'Fout bij bijwerken: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'update_password') {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        if (empty($current) || empty($new) || empty($confirm)) {
            $error = 'Vul alle wachtwoord velden in';
        } elseif ($new !== $confirm) {
            $error = 'Nieuwe wachtwoorden komen niet overeen';
        } elseif (strlen($new) < 8) {
            $error = 'Nieuw wachtwoord moet minimaal 8 karakters zijn';
        } elseif (!password_verify($current, $user['password_hash'])) {
            $error = 'Huidig wachtwoord is onjuist';
        } else {
            try {
                $new_hash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$new_hash, $user_id]);
                
                $message = 'Wachtwoord gewijzigd!';
            } catch (PDOException $e) {
                $error = 'Fout bij wijzigen wachtwoord: ' . $e->getMessage();
            }
        }
    }
    
    // ✅ Update foto URL (gebruik bestaande profile_photo kolom)
    if ($action === 'update_photo') {
        $foto_url = trim($_POST['foto_url']);
        
        try {
            $stmt = $db->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
            $stmt->execute([$foto_url, $user_id]);
            
            $message = 'Foto URL bijgewerkt!';
            $user['profile_photo'] = $foto_url;
        } catch (PDOException $e) {
            $error = 'Fout bij bijwerken foto: ' . $e->getMessage();
        }
    }
    
    // ✅ Upload foto (gebruik bestaande profile_photo kolom)
    if ($action === 'upload_photo') {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                $error = 'Alleen JPG, PNG en GIF toegestaan';
            } elseif ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                $error = 'Bestand te groot (max 5MB)';
            } else {
                $upload_dir = __DIR__ . '/../uploads/profiles/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $new_filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                    $foto_url = '/uploads/profiles/' . $new_filename;
                    
                    $stmt = $db->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                    $stmt->execute([$foto_url, $user_id]);
                    
                    $message = 'Foto geüpload!';
                    $user['profile_photo'] = $foto_url;
                } else {
                    $error = 'Upload mislukt';
                }
            }
        } else {
            $error = 'Geen bestand geselecteerd';
        }
    }
    
    // ✅ NIEUW: Update custom button names
    if ($action === 'update_button_names') {
        $button1 = trim($_POST['button1_name'] ?? '');
        $button2 = trim($_POST['button2_name'] ?? '');
        $button3 = trim($_POST['button3_name'] ?? '');
        
        // Truncate to 20 chars max
        $button1 = substr($button1, 0, 20);
        $button2 = substr($button2, 0, 20);
        $button3 = substr($button3, 0, 20);
        
        try {
            // Check if record exists
            $checkStmt = $db->prepare("SELECT id FROM user_button_names WHERE user_id = ?");
            $checkStmt->execute([$user_id]);
            $exists = $checkStmt->fetch();
            
            if ($exists) {
                // Update existing
                $stmt = $db->prepare("
                    UPDATE user_button_names 
                    SET button1_name = ?, button2_name = ?, button3_name = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$button1, $button2, $button3, $user_id]);
            } else {
                // Insert new
                $stmt = $db->prepare("
                    INSERT INTO user_button_names (user_id, button1_name, button2_name, button3_name)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $button1, $button2, $button3]);
            }
            
            $message = 'Button namen opgeslagen!';
            $justSavedButtons = true;
            
            // Reload custom names
            $customNamesStmt->execute([$user_id]);
            $customNames = $customNamesStmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $error = 'Fout bij opslaan button namen: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mijn Profiel - PeopleDisplay</title>
    <?php if ($justSavedButtons): ?>
    <meta http-equiv="refresh" content="0;url=<?php echo $_SERVER['PHP_SELF']; ?>?saved=buttons&t=<?php echo time(); ?>">
    <?php endif; ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { color: #2c3e50; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 30px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #3498db; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card h2 { color: #2c3e50; margin-bottom: 20px; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; color: #555; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        button { padding: 12px 24px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .info-box { background: #e8f4f8; padding: 15px; border-left: 4px solid #3498db; border-radius: 4px; margin-bottom: 20px; }
        .photo-preview { max-width: 200px; max-height: 200px; border-radius: 8px; margin-top: 10px; }
        small { display: block; color: #666; margin-top: 4px; font-size: 12px; }
    </style>
    <script>
        // ⚠️ FIX: Force reload on back button (bfcache prevention)
        window.addEventListener('pageshow', function(event) {
            if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                console.log('🔄 Profile page loaded from cache - forcing reload');
                window.location.reload();
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <a href="../frontpage.php" class="back-link">← Terug naar Frontpage</a>
        
        <h1>👤 Mijn Profiel</h1>
        <p class="subtitle">Beheer je persoonlijke gegevens</p>
        
        <?php if ($message): ?>
            <div class="success">✅ <?= htmlspecialchars($message) ?></div>
        <?php elseif (isset($_GET['saved']) && $_GET['saved'] === 'buttons'): ?>
            <div class="success">✅ Button namen opgeslagen!</div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- PROFIEL INFO -->
        <div class="card">
            <h2>📋 Profielgegevens</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label>Weergavenaam</label>
                    <input type="text" name="display_name" value="<?= htmlspecialchars($user['display_name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>E-mailadres</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                </div>
                
                <div class="info-box">
                    <strong>Rol:</strong> <?= htmlspecialchars($user['role']) ?><br>
                    <strong>Gebruikersnaam:</strong> <?= htmlspecialchars($user['username']) ?><br>
                    <strong>Aangemaakt:</strong> <?= date('d-m-Y H:i', strtotime($user['created_at'])) ?>
                </div>
                
                <button type="submit" class="btn-primary">✓ Profiel Opslaan</button>
            </form>
        </div>
        
        <!-- FOTO (altijd beschikbaar voor alle users) -->
        <div class="card">
            <h2>📷 Profielfoto</h2>
            
            <?php if (!empty($user['profile_photo'])): ?>
                <img src="<?= htmlspecialchars($user['profile_photo']) ?>" alt="Profielfoto" class="photo-preview" onerror="this.style.display='none'">
            <?php endif; ?>
            
            <!-- Upload foto -->
            <form method="POST" enctype="multipart/form-data" style="margin-bottom: 20px;">
                <input type="hidden" name="action" value="upload_photo">
                
                <div class="form-group">
                    <label>📁 Upload Foto</label>
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/gif" required>
                    <small>JPG, PNG of GIF - Max 5MB</small>
                </div>
                
                <button type="submit" class="btn-primary">↑ Upload Foto</button>
            </form>
            
            <hr style="margin: 20px 0; border: 1px solid #ddd;">
            
            <!-- Of via URL -->
            <form method="POST">
                <input type="hidden" name="action" value="update_photo">
                
                <div class="form-group">
                    <label>🔗 Of via URL</label>
                    <input type="text" name="foto_url" value="<?= htmlspecialchars($user['profile_photo'] ?? '') ?>" placeholder="https://...">
                    <small>Plak hier de URL van je profielfoto</small>
                </div>
                
                <button type="submit" class="btn-primary">✓ URL Opslaan</button>
            </form>
        </div>
        
        <!-- CUSTOM BUTTON NAMEN -->
        <?php if ($allowCustomNames): ?>
        <div class="card">
            <h2>🎨 Custom Button Namen</h2>
            <p style="color: #666; margin-bottom: 15px;">Pas de namen van de extra knoppen aan naar jouw voorkeur (max 20 karakters).</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_button_names">
                
                <div class="form-group">
                    <label>🌸 Knop 1 Naam</label>
                    <input type="text" 
                           name="button1_name" 
                           value="<?= htmlspecialchars($customNames['button1_name'] ?? '') ?>" 
                           placeholder="Standaard: <?= htmlspecialchars($globalButtons['button1_name'] ?? 'PAUZE') ?>"
                           maxlength="20">
                    <small>Leeg laten = standaard naam gebruiken (<?= htmlspecialchars($globalButtons['button1_name'] ?? 'PAUZE') ?>)</small>
                </div>
                
                <div class="form-group">
                    <label>💜 Knop 2 Naam</label>
                    <input type="text" 
                           name="button2_name" 
                           value="<?= htmlspecialchars($customNames['button2_name'] ?? '') ?>" 
                           placeholder="Standaard: <?= htmlspecialchars($globalButtons['button2_name'] ?? 'THUISWERKEN') ?>"
                           maxlength="20">
                    <small>Leeg laten = standaard naam gebruiken (<?= htmlspecialchars($globalButtons['button2_name'] ?? 'THUISWERKEN') ?>)</small>
                </div>
                
                <div class="form-group">
                    <label>🌿 Knop 3 Naam</label>
                    <input type="text" 
                           name="button3_name" 
                           value="<?= htmlspecialchars($customNames['button3_name'] ?? '') ?>" 
                           placeholder="Standaard: <?= htmlspecialchars($globalButtons['button3_name'] ?? 'VAKANTIE') ?>"
                           maxlength="20">
                    <small>Leeg laten = standaard naam gebruiken (<?= htmlspecialchars($globalButtons['button3_name'] ?? 'VAKANTIE') ?>)</small>
                </div>
                
                <div class="info-box">
                    💡 <strong>Tip:</strong> Op de kaarten worden max 8 karakters getoond. Langere namen krijgen een tooltip met de volledige naam.
                </div>
                
                <button type="submit" class="btn-primary">✓ Button Namen Opslaan</button>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- WACHTWOORD WIJZIGEN -->
        <div class="card">
            <h2>🔒 Wachtwoord Wijzigen</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_password">
                
                <div class="form-group">
                    <label>Huidig Wachtwoord</label>
                    <input type="password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label>Nieuw Wachtwoord</label>
                    <input type="password" name="new_password" required minlength="8">
                    <small>Minimaal 8 karakters</small>
                </div>
                
                <div class="form-group">
                    <label>Bevestig Nieuw Wachtwoord</label>
                    <input type="password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn-primary">✓ Wachtwoord Wijzigen</button>
            </form>
        </div>
    </div>
    
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
                setTimeout(() => window.location.replace('../login.php?forced_logout=1'), 2000);
                return;
            }

            if (hasRememberToken()) {
                window.location.reload();
                return;
            }

            showTimeoutOverlay();
            setTimeout(() => window.location.replace('../login.php'), 2000);
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
</body>
</html>
