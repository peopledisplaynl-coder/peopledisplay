<?php
/**
 * ============================================================================
 * BESTANDSNAAM:  change_password.php
 * UPLOAD NAAR:   /admin/change_password.php (OVERSCHRIJF)
 * DATUM:         2024-12-04
 * VERSIE:        FIXED - Line 25
 * 
 * FIX: Changed /admin/login.html → ../login.php (Line 25)
 * ============================================================================
 */

session_start();
require_once __DIR__ . '/../includes/db.php';

$message = '';
$error = '';

// Check if user is logged in - FIXED LINE 25
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');  // ✅ FIXED: Was /admin/login.html
    exit;
}

$userId = $_SESSION['user_id'];

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if ($currentPassword && $newPassword && $confirmPassword) {
        // Verify current password
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($currentPassword, $user['password_hash'])) {
            // Check new passwords match
            if ($newPassword === $confirmPassword) {
                // Check password strength (min 8 characters)
                if (strlen($newPassword) >= 8) {
                    // Update password
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateStmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$newHash, $userId]);
                    
                    $message = 'Wachtwoord succesvol gewijzigd!';
                } else {
                    $error = 'Nieuw wachtwoord moet minimaal 8 karakters bevatten.';
                }
            } else {
                $error = 'Nieuwe wachtwoorden komen niet overeen.';
            }
        } else {
            $error = 'Huidig wachtwoord is onjuist.';
        }
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
    <title>Wachtwoord Wijzigen - PeopleDisplay</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7fafc;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 40px auto;
        }
        
        .card {
            background: white;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        h1 {
            font-size: 24px;
            color: #2d3748;
            margin-bottom: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .button {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .button:hover {
            background: #5568d3;
        }
        
        .message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .message.success {
            background: #c6f6d5;
            color: #22543d;
            border: 2px solid #48bb78;
        }
        
        .message.error {
            background: #fed7d7;
            color: #c53030;
            border: 2px solid #fc8181;
        }
        
        .back-link {
            display: inline-block;
            color: #667eea;
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Terug naar Dashboard</a>
        
        <div class="card">
            <h1>🔐 Wachtwoord Wijzigen</h1>
            
            <?php if ($message): ?>
                <div class="message success">
                    ✅ <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="current_password">Huidig Wachtwoord</label>
                    <input 
                        type="password" 
                        id="current_password" 
                        name="current_password" 
                        required
                        autocomplete="current-password"
                    >
                </div>
                
                <div class="form-group">
                    <label for="new_password">Nieuw Wachtwoord (min. 8 karakters)</label>
                    <input 
                        type="password" 
                        id="new_password" 
                        name="new_password" 
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Bevestig Nieuw Wachtwoord</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >
                </div>
                
                <button type="submit" class="button">
                    Wachtwoord Wijzigen
                </button>
            </form>
        </div>
    </div>
</body>
</html>
