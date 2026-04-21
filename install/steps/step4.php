<?php
/**
 * STEP 4: Admin Account Creation
 */

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['admin_user'] ?? '';
    $password = $_POST['admin_pass'] ?? '';
    $confirm = $_POST['admin_pass_confirm'] ?? '';
    $email = $_POST['admin_email'] ?? '';
    $display_name = $_POST['admin_name'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Gebruikersnaam en wachtwoord zijn verplicht';
    } elseif ($password !== $confirm) {
        $error = 'Wachtwoorden komen niet overeen';
    } elseif (strlen($password) < 8) {
        $error = 'Wachtwoord moet minimaal 8 karakters zijn';
    } else {
        try {
            $dbConfig = $_SESSION['db_config'];
            $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Update default admin user
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password_hash = ?, 
                    display_name = ?,
                    email = ?,
                    username = ?
                WHERE id = 1
            ");
            $stmt->execute([$passwordHash, $display_name, $email, $username]);
            
            $_SESSION['admin_created'] = true;
            $_SESSION['admin_username'] = $username;
            
            markStepCompleted(4);
            header('Location: ?step=5');
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<h2>Admin Account</h2>
<p style="color: #666; margin-bottom: 30px;">
    Maak je admin account aan. Dit account krijgt volledige toegang tot het systeem.
</p>

<?php if ($error): ?>
    <div class="alert alert-error">
        <strong>⚠ Fout</strong>
        <p><?php echo htmlspecialchars($error); ?></p>
    </div>
<?php endif; ?>

<form method="post" action="?step=4" class="installer-form">
    <div class="form-group">
        <label for="admin_user">
            Gebruikersnaam *
            <span class="help-text">Minimaal 3 karakters, alleen letters, cijfers en underscore</span>
        </label>
        <input 
            type="text" 
            id="admin_user" 
            name="admin_user" 
            required 
            value="admin" 
            pattern="[a-zA-Z0-9_]{3,}"
            minlength="3"
        >
    </div>
    
    <div class="form-group">
        <label for="admin_name">
            Volledige Naam *
            <span class="help-text">Wordt weergegeven in de interface</span>
        </label>
        <input 
            type="text" 
            id="admin_name" 
            name="admin_name" 
            required 
            value="Systeembeheerder"
        >
    </div>
    
    <div class="form-group">
        <label for="admin_email">
            E-mailadres (optioneel)
            <span class="help-text">Voor wachtwoord reset en notificaties</span>
        </label>
        <input 
            type="email" 
            id="admin_email" 
            name="admin_email"
            placeholder="admin@voorbeeld.nl"
        >
    </div>
    
    <div class="form-group">
        <label for="admin_pass">
            Wachtwoord *
            <span class="help-text">Minimaal 8 karakters - gebruik een sterk wachtwoord!</span>
        </label>
        <input 
            type="password" 
            id="admin_pass" 
            name="admin_pass" 
            required 
            minlength="8"
        >
        <div class="password-strength" id="password-strength"></div>
    </div>
    
    <div class="form-group">
        <label for="admin_pass_confirm">
            Bevestig Wachtwoord *
        </label>
        <input 
            type="password" 
            id="admin_pass_confirm" 
            name="admin_pass_confirm" 
            required 
            minlength="8"
        >
    </div>
    
    <div class="alert alert-info" style="margin-top: 20px;">
        <strong>🔒 Beveiliging</strong>
        <p>Gebruik een uniek, sterk wachtwoord. Dit account heeft volledige controle over het systeem.</p>
    </div>
    
    <button type="submit" class="btn btn-primary" style="margin-top: 30px;">
        Volgende: Site Configuratie →
    </button>
</form>

<script>
// Password strength indicator
document.getElementById('admin_pass').addEventListener('input', function(e) {
    const password = e.target.value;
    const strengthDiv = document.getElementById('password-strength');
    
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    
    const labels = ['Zwak', 'Zwak', 'Redelijk', 'Sterk', 'Zeer sterk'];
    const colors = ['#e74c3c', '#e67e22', '#f39c12', '#27ae60', '#2ecc71'];
    
    if (password.length > 0) {
        strengthDiv.textContent = 'Sterkte: ' + labels[strength];
        strengthDiv.style.color = colors[strength];
        strengthDiv.style.marginTop = '5px';
        strengthDiv.style.fontSize = '13px';
        strengthDiv.style.fontWeight = 'bold';
    } else {
        strengthDiv.textContent = '';
    }
});

// Password match validation
document.getElementById('admin_pass_confirm').addEventListener('input', function(e) {
    const password = document.getElementById('admin_pass').value;
    const confirm = e.target.value;
    
    if (confirm.length > 0) {
        if (password === confirm) {
            e.target.style.borderColor = '#4caf50';
        } else {
            e.target.style.borderColor = '#e74c3c';
        }
    } else {
        e.target.style.borderColor = '#e0e0e0';
    }
});
</script>
