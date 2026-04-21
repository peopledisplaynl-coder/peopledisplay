<?php
/**
 * STEP 2: Database Configuration
 */

$error = null;
$success = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'] ?? '';
    $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    $db_prefix = $_POST['db_prefix'] ?? '';
    
    // Validate input
    if (empty($db_host) || empty($db_name) || empty($db_user)) {
        $error = 'Vul alle verplichte velden in';
    } else {
        // Test connection
        $result = testDatabaseConnection($db_host, $db_name, $db_user, $db_pass);
        
        if ($result['success']) {
            // Save to session
            $_SESSION['db_config'] = [
                'host' => $db_host,
                'name' => $db_name,
                'user' => $db_user,
                'pass' => $db_pass,
                'prefix' => $db_prefix
            ];
            
            markStepCompleted(2);
            
            // Redirect to next step
            header('Location: ?step=3');
            exit;
        } else {
            $error = 'Database verbinding mislukt: ' . $result['error'];
        }
    }
}

// Load saved values from session
$db_host = $_SESSION['db_config']['host'] ?? 'localhost';
$db_name = $_SESSION['db_config']['name'] ?? 'peopledisplay_db';
$db_user = $_SESSION['db_config']['user'] ?? '';
$db_pass = $_SESSION['db_config']['pass'] ?? '';
$db_prefix = $_SESSION['db_config']['prefix'] ?? '';

?>

<h2>Database Configuratie</h2>
<p style="color: #666; margin-bottom: 30px;">
    Vul de gegevens in van je MySQL database. Als de database nog niet bestaat, wordt deze automatisch aangemaakt.
</p>

<?php if ($error): ?>
    <div class="alert alert-error">
        <strong>⚠ Fout</strong>
        <p><?php echo htmlspecialchars($error); ?></p>
    </div>
<?php endif; ?>

<form method="post" action="?step=2" class="installer-form">
    <div class="form-group">
        <label for="db_host">
            Database Host *
            <span class="help-text">Meestal "localhost"</span>
        </label>
        <input 
            type="text" 
            id="db_host" 
            name="db_host" 
            value="<?php echo htmlspecialchars($db_host); ?>"
            required
            placeholder="localhost"
        >
    </div>
    
    <div class="form-group">
        <label for="db_name">
            Database Naam *
            <span class="help-text">Naam van de database (wordt aangemaakt als deze niet bestaat)</span>
        </label>
        <input 
            type="text" 
            id="db_name" 
            name="db_name" 
            value="<?php echo htmlspecialchars($db_name); ?>"
            required
            placeholder="peopledisplay_db"
            pattern="[a-zA-Z0-9_]+"
            title="Alleen letters, cijfers en underscores"
        >
    </div>
    
    <div class="form-group">
        <label for="db_user">
            Database Gebruiker *
            <span class="help-text">MySQL gebruikersnaam met CREATE DATABASE rechten</span>
        </label>
        <input 
            type="text" 
            id="db_user" 
            name="db_user" 
            value="<?php echo htmlspecialchars($db_user); ?>"
            required
            placeholder="root"
        >
    </div>
    
    <div class="form-group">
        <label for="db_pass">
            Database Wachtwoord
            <span class="help-text">Laat leeg als er geen wachtwoord is</span>
        </label>
        <input 
            type="password" 
            id="db_pass" 
            name="db_pass" 
            value="<?php echo htmlspecialchars($db_pass); ?>"
            placeholder="••••••••"
        >
        <button type="button" onclick="togglePassword()" class="btn-toggle-password">
            👁️ Toon wachtwoord
        </button>
    </div>
    
    <div class="form-group">
        <label for="db_prefix">
            Tabel Prefix (optioneel)
            <span class="help-text">Voeg een prefix toe aan alle tabelnamen (bijv. "pd_")</span>
        </label>
        <input 
            type="text" 
            id="db_prefix" 
            name="db_prefix" 
            value="<?php echo htmlspecialchars($db_prefix); ?>"
            placeholder="Geen prefix"
            pattern="[a-zA-Z0-9_]*"
            title="Alleen letters, cijfers en underscores"
        >
    </div>
    
    <div class="alert alert-info" style="margin-top: 20px;">
        <strong>ℹ️ Tip</strong>
        <p>Deze gegevens kun je vinden in je hosting control panel (cPanel, Plesk, etc.) of bij je hosting provider.</p>
    </div>
    
    <div style="margin-top: 30px;">
        <button type="submit" class="btn btn-primary">
            Volgende: Test Verbinding & Installeer Database →
        </button>
    </div>
</form>

<style>
.installer-form {
    max-width: 600px;
}

.form-group {
    margin-bottom: 20px;
    position: relative;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #333;
}

.help-text {
    display: block;
    font-weight: normal;
    font-size: 13px;
    color: #999;
    margin-top: 4px;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.form-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-toggle-password {
    position: absolute;
    right: 10px;
    top: 66px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 12px;
    color: #667eea;
    padding: 5px 10px;
}

.btn-toggle-password:hover {
    background: #f5f5f5;
    border-radius: 4px;
}

.alert-info {
    background: #d1ecf1;
    border-left-color: #2196f3;
    color: #0c5460;
}
</style>

<script>
function togglePassword() {
    const input = document.getElementById('db_pass');
    const btn = document.querySelector('.btn-toggle-password');
    
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🙈 Verberg wachtwoord';
    } else {
        input.type = 'password';
        btn.textContent = '👁️ Toon wachtwoord';
    }
}
</script>
