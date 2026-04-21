<?php
/**
 * ============================================================================
 * PEOPLEDISPLAY v2.0 - WEB INSTALLER
 * ============================================================================
 * 
 * HOTFIX: Headers already sent - Output buffering implemented
 * 
 * This version prevents "headers already sent" errors by:
 * 1. Starting output buffering before including step files
 * 2. Capturing step content
 * 3. Allowing redirects to happen before any HTML output
 * 
 * ============================================================================
 */

// Prevent running if already installed
if (file_exists(__DIR__ . '/.installed')) {
    die('
        <!DOCTYPE html>
        <html>
        <head>
            <title>Already Installed</title>
            <style>
                body { font-family: Arial; background: #f5f5f5; padding: 50px; text-align: center; }
                .box { background: white; max-width: 500px; margin: 0 auto; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h1 { color: #e74c3c; }
                .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="box">
                <h1>⚠️ Already Installed</h1>
                <p>PeopleDisplay is already installed on this server.</p>
                <p>To reinstall, delete the file:<br><code>/install/.installed</code></p>
                <a href="../admin/dashboard.php" class="btn">→ Go to Dashboard</a>
            </div>
        </body>
        </html>
    ');
}

// Start session for installer
session_start();

// Enable error reporting for installer
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Disable any existing auth checks
define('INSTALLER_RUNNING', true);
$_SESSION['installer_bypass'] = true;

// ============================================================================
// CONFIGURATION
// ============================================================================

define('INSTALLER_VERSION', '2.1.1');
define('MIN_PHP_VERSION', '8.0.0');
define('MIN_MYSQL_VERSION', '5.7.0');

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function getCurrentStep() {
    return isset($_GET['step']) ? (int)$_GET['step'] : 1;
}

function canAccessStep($step) {
    if ($step === 1) return true;
    $completedSteps = $_SESSION['completed_steps'] ?? [];
    return in_array($step - 1, $completedSteps);
}

function markStepCompleted($step) {
    if (!isset($_SESSION['completed_steps'])) {
        $_SESSION['completed_steps'] = [];
    }
    if (!in_array($step, $_SESSION['completed_steps'])) {
        $_SESSION['completed_steps'][] = $step;
    }
}

function checkPHPVersion() {
    return version_compare(PHP_VERSION, MIN_PHP_VERSION, '>=');
}

function checkPHPExtensions() {
    $required = ['mysqli', 'pdo', 'pdo_mysql', 'json', 'session', 'curl', 'fileinfo'];
    $missing = [];
    
    foreach ($required as $ext) {
        if (!extension_loaded($ext)) {
            $missing[] = $ext;
        }
    }
    
    return $missing;
}

function checkPermissions() {
    $dirs = [
        '../includes' => 'Write config files',
        '../uploads' => 'Upload files',
        '../tmp' => 'Session storage'
    ];
    
    $issues = [];
    
    foreach ($dirs as $dir => $purpose) {
        $fullPath = __DIR__ . '/' . $dir;
        
        if (!file_exists($fullPath)) {
            @mkdir($fullPath, 0755, true);
        }
        
        if (!is_writable($fullPath)) {
            $issues[$dir] = $purpose;
        }
    }
    
    return $issues;
}

function testDatabaseConnection($host, $name, $user, $pass) {
    try {
        $dsn = "mysql:host=$host;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        return ['success' => true, 'pdo' => $pdo];
        
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ============================================================================
// MAIN ROUTER
// ============================================================================

$currentStep = getCurrentStep();

// Validate step access
if (!canAccessStep($currentStep)) {
    header('Location: ?step=1');
    exit;
}

// Load step file
$stepFile = __DIR__ . "/steps/step{$currentStep}.php";

if (!file_exists($stepFile)) {
    die("Error: Step file not found: step{$currentStep}.php");
}

// ============================================================================
// HANDLE POST REQUESTS BEFORE ANY OUTPUT (PREVENTS HEADER ERRORS!)
// ============================================================================

// Start output buffering if not already started
if (!ob_get_level()) {
    ob_start();
}

// Include step file to execute any POST logic
include $stepFile;

// Capture step content
$stepContent = ob_get_clean();

// If step did a redirect, we're done
if (empty($stepContent) && headers_sent()) {
    exit;
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PeopleDisplay Installatie - Stap <?php echo $currentStep; ?></title>
    <link rel="stylesheet" href="install.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .installer-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .installer-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .installer-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .installer-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .progress-bar {
            height: 4px;
            background: rgba(255,255,255,0.3);
            position: relative;
        }
        .progress-bar-fill {
            height: 100%;
            background: white;
            transition: width 0.3s ease;
        }
        .installer-content {
            padding: 40px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e0e0e0;
            z-index: 0;
        }
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            position: relative;
            z-index: 1;
            color: #999;
        }
        .step.completed {
            background: #4caf50;
            color: white;
        }
        .step.active {
            background: #667eea;
            color: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #f5f5f5;
            color: #333;
        }
        .installer-footer {
            padding: 20px 40px;
            background: #f9f9f9;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="installer-container">
        <div class="installer-header">
            <h1>👥 PeopleDisplay Installatie</h1>
            <p>Versie <?php echo INSTALLER_VERSION; ?> - Stap <?php echo $currentStep; ?> van 6</p>
        </div>
        
        <div class="progress-bar">
            <div class="progress-bar-fill" style="width: <?php echo ($currentStep / 6) * 100; ?>%"></div>
        </div>
        
        <div class="installer-content">
            <div class="step-indicator">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                    <div class="step <?php 
                        echo $i < $currentStep ? 'completed' : '';
                        echo $i === $currentStep ? 'active' : '';
                    ?>">
                        <?php echo $i < $currentStep ? '✓' : $i; ?>
                    </div>
                <?php endfor; ?>
            </div>
            
            <div class="step-content">
                <?php echo $stepContent; ?>
            </div>
        </div>
        
        <div class="installer-footer">
            <div>
                <small style="color: #999;">PeopleDisplay v<?php echo INSTALLER_VERSION; ?> © 2026</small>
            </div>
            <div>
                <?php if ($currentStep > 1 && $currentStep < 6): ?>
                    <a href="?step=<?php echo $currentStep - 1; ?>" class="btn btn-secondary">← Vorige</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Bezig...';
                    }
                });
            });
        });
    </script>
</body>
</html>
