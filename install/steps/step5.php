<?php
/**
 * STEP 5: Site Configuration
 */

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $dbConfig = $_SESSION['db_config'];
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Update config
        $stmt = $pdo->prepare("
            UPDATE config 
            SET button1_name = ?,
                button2_name = ?,
                button3_name = ?,
                allow_user_button_names = ?,
                allow_auto_fullscreen = ?
            WHERE id = 1
        ");
        $stmt->execute([
            $_POST['button1_name'] ?? 'PAUZE',
            $_POST['button2_name'] ?? 'THUISWERKEN',
            $_POST['button3_name'] ?? 'VAKANTIE',
            isset($_POST['allow_user_buttons']) ? 1 : 0,
            isset($_POST['allow_fullscreen']) ? 1 : 0
        ]);
        
        markStepCompleted(5);
        header('Location: ?step=6');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<h2>Site Configuratie</h2>
<p style="color: #666; margin-bottom: 30px;">
    Configureer de basis instellingen. Je kunt alles later aanpassen via het admin panel.
</p>

<?php if ($error): ?>
    <div class="alert alert-error">
        <strong>⚠ Fout</strong>
        <p><?php echo htmlspecialchars($error); ?></p>
    </div>
<?php endif; ?>

<form method="post" action="?step=5" class="installer-form">
    <h3 style="margin-bottom: 20px;">📋 Extra Status Knoppen</h3>
    <p style="color: #666; margin-bottom: 20px;">
        Naast de standaard IN/OUT knoppen kun je 3 extra status knoppen configureren (bijv. PAUZE, THUISWERKEN, VAKANTIE).
    </p>
    
    <div class="form-group">
        <label for="button1_name">
            🌸 Knop 1 Naam
            <span class="help-text">Maximaal 20 karakters</span>
        </label>
        <input 
            type="text" 
            id="button1_name" 
            name="button1_name" 
            value="PAUZE" 
            maxlength="20"
            placeholder="bijv. PAUZE, LUNCH, CURSUS"
        >
    </div>
    
    <div class="form-group">
        <label for="button2_name">
            💜 Knop 2 Naam
            <span class="help-text">Maximaal 20 karakters</span>
        </label>
        <input 
            type="text" 
            id="button2_name" 
            name="button2_name" 
            value="THUISWERKEN" 
            maxlength="20"
            placeholder="bijv. THUISWERKEN, REMOTE"
        >
    </div>
    
    <div class="form-group">
        <label for="button3_name">
            🌿 Knop 3 Naam
            <span class="help-text">Maximaal 20 karakters</span>
        </label>
        <input 
            type="text" 
            id="button3_name" 
            name="button3_name" 
            value="VAKANTIE" 
            maxlength="20"
            placeholder="bijv. VAKANTIE, VERLOF, ZIEK"
        >
    </div>
    
    <div class="form-group" style="margin-top: 20px;">
        <label style="display: flex; align-items: center; cursor: pointer;">
            <input 
                type="checkbox" 
                name="allow_user_buttons" 
                checked
                style="width: auto; margin-right: 10px;"
            >
            <span>
                Sta gebruikers toe eigen button namen in te stellen
                <span class="help-text" style="display: block; margin-left: 28px;">
                    Elke gebruiker kan dan zijn eigen namen kiezen voor deze knoppen
                </span>
            </span>
        </label>
    </div>
    
    <hr style="margin: 40px 0; border: none; border-top: 1px solid #e0e0e0;">
    
    <h3 style="margin-bottom: 20px;">⚙️ Weergave Opties</h3>
    
    <div class="form-group">
        <label style="display: flex; align-items: center; cursor: pointer;">
            <input 
                type="checkbox" 
                name="allow_fullscreen"
                style="width: auto; margin-right: 10px;"
            >
            <span>
                Auto-fullscreen bij inactiviteit
                <span class="help-text" style="display: block; margin-left: 28px;">
                    Scherm gaat automatisch naar fullscreen na 60 seconden inactiviteit (handig voor tablets)
                </span>
            </span>
        </label>
    </div>
    
    <div class="alert alert-info" style="margin-top: 30px;">
        <strong>💡 Tip</strong>
        <p>Je kunt deze instellingen later aanpassen via Admin → Configuratie Beheren</p>
    </div>
    
    <button type="submit" class="btn btn-primary" style="margin-top: 30px;">
        Volgende: Voltooien →
    </button>
</form>

<style>
h3 {
    color: #333;
    font-size: 18px;
}

input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

hr {
    margin: 40px 0;
}
</style>
