<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * Diagnostic Check Script
 * Location: /api/check_button_names_api.php
 * 
 * Run this to check if the API is properly set up
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Button Names API Diagnostic</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .check { margin: 10px 0; padding: 10px; background: white; border-radius: 4px; }
        .ok { border-left: 4px solid #28a745; }
        .error { border-left: 4px solid #dc3545; }
        .warning { border-left: 4px solid #ffc107; }
        h1 { color: #333; }
        pre { background: #f8f9fa; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Button Names API Diagnostic</h1>
    
    <?php
    $checks = [];
    
    // Check 1: File structure
    $checks[] = [
        'name' => 'API Directory',
        'test' => is_dir(__DIR__),
        'message' => 'API directory exists: ' . __DIR__
    ];
    
    // Check 2: save_user_button_names.php exists
    $apiFile = __DIR__ . '/save_user_button_names.php';
    $checks[] = [
        'name' => 'API File',
        'test' => file_exists($apiFile),
        'message' => 'save_user_button_names.php exists at: ' . $apiFile
    ];
    
    // Check 3: db.php exists
    $dbFile = __DIR__ . '/../includes/db.php';
    $checks[] = [
        'name' => 'Database Config',
        'test' => file_exists($dbFile),
        'message' => 'includes/db.php exists at: ' . $dbFile
    ];
    
    // Check 4: Load database
    $dbLoaded = false;
    try {
        if (file_exists($dbFile)) {
            require_once $dbFile;
            $dbLoaded = isset($db) && ($db instanceof PDO);
        }
    } catch (Exception $e) {
        $checks[] = [
            'name' => 'Database Connection',
            'test' => false,
            'message' => 'Error loading database: ' . $e->getMessage()
        ];
    }
    
    $checks[] = [
        'name' => 'Database Connection',
        'test' => $dbLoaded,
        'message' => $dbLoaded ? 'Database connected successfully' : 'Database not available'
    ];
    
    // Check 5: Config table check
    if ($dbLoaded) {
        try {
            $stmt = $db->query("SELECT users_can_customize_buttons FROM config WHERE id = 1");
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $enabled = !empty($config['users_can_customize_buttons']);
            $checks[] = [
                'name' => 'Custom Buttons Setting',
                'test' => $enabled,
                'message' => $enabled 
                    ? 'users_can_customize_buttons is ENABLED (1)' 
                    : 'users_can_customize_buttons is DISABLED (0) - Enable in Features Beheren!'
            ];
        } catch (Exception $e) {
            $checks[] = [
                'name' => 'Config Table',
                'test' => false,
                'message' => 'Error reading config: ' . $e->getMessage()
            ];
        }
    }
    
    // Check 6: Session
    $checks[] = [
        'name' => 'Session Active',
        'test' => session_status() === PHP_SESSION_ACTIVE,
        'message' => 'Session status: ' . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT ACTIVE')
    ];
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        $checks[] = [
            'name' => 'User Logged In',
            'test' => isset($_SESSION['user_id']),
            'message' => isset($_SESSION['user_id']) 
                ? 'User ID: ' . $_SESSION['user_id'] 
                : 'No user_id in session - not logged in?'
        ];
    }
    
    // Check 7: File permissions
    if (file_exists($apiFile)) {
        $perms = substr(sprintf('%o', fileperms($apiFile)), -4);
        $checks[] = [
            'name' => 'File Permissions',
            'test' => is_readable($apiFile),
            'message' => "save_user_button_names.php permissions: $perms (should be 644 or 664)"
        ];
    }
    
    // Check 8: Test API endpoint accessibility
    $apiUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
            . "://$_SERVER[HTTP_HOST]" 
            . dirname($_SERVER['REQUEST_URI']) 
            . '/save_user_button_names.php';
    
    $checks[] = [
        'name' => 'API URL',
        'test' => true, // Just info
        'message' => 'API endpoint URL: ' . $apiUrl,
        'type' => 'info'
    ];
    
    // Display results
    foreach ($checks as $check) {
        $class = 'check ';
        if (isset($check['type']) && $check['type'] === 'info') {
            $class .= 'warning';
        } else {
            $class .= $check['test'] ? 'ok' : 'error';
        }
        
        $icon = $check['test'] ? '✅' : '❌';
        if (isset($check['type']) && $check['type'] === 'info') {
            $icon = 'ℹ️';
        }
        
        echo "<div class='$class'>";
        echo "<strong>$icon {$check['name']}</strong><br>";
        echo $check['message'];
        echo "</div>";
    }
    
    // Summary
    $totalChecks = count(array_filter($checks, function($c) { return !isset($c['type']); }));
    $passedChecks = count(array_filter($checks, function($c) { return $c['test'] && !isset($c['type']); }));
    
    echo "<div class='check " . ($passedChecks === $totalChecks ? 'ok' : 'error') . "'>";
    echo "<strong>Summary</strong><br>";
    echo "Passed: $passedChecks / $totalChecks checks";
    echo "</div>";
    
    // Recommendations
    echo "<h2>📝 Recommendations</h2>";
    
    if ($passedChecks < $totalChecks) {
        echo "<div class='check error'>";
        echo "<strong>⚠️ Issues Found</strong><br>";
        echo "<ol>";
        
        if (!file_exists($apiFile)) {
            echo "<li>Upload <code>save_user_button_names.php</code> to <code>/api/</code></li>";
        }
        
        if (!file_exists($dbFile)) {
            echo "<li>Check that <code>/includes/db.php</code> exists</li>";
        }
        
        if (!$dbLoaded) {
            echo "<li>Fix database connection in <code>includes/db.php</code></li>";
        }
        
        if ($dbLoaded && isset($config) && empty($config['users_can_customize_buttons'])) {
            echo "<li>Enable custom buttons in admin: Features Beheren → ☑️ Gebruikers mogen eigen button namen instellen</li>";
            echo "<li>Or run SQL: <code>UPDATE config SET users_can_customize_buttons = 1 WHERE id = 1;</code></li>";
        }
        
        if (session_status() !== PHP_SESSION_ACTIVE) {
            echo "<li>Session not active - check session configuration</li>";
        }
        
        if (session_status() === PHP_SESSION_ACTIVE && !isset($_SESSION['user_id'])) {
            echo "<li>Log in first before testing the API</li>";
        }
        
        echo "</ol>";
        echo "</div>";
    } else {
        echo "<div class='check ok'>";
        echo "<strong>✅ All Checks Passed!</strong><br>";
        echo "The API should work correctly now. Try saving button names from the user profile page.";
        echo "</div>";
    }
    
    // Debug info
    echo "<h2>🐛 Debug Info</h2>";
    echo "<div class='check warning'>";
    echo "<strong>Server Info</strong>";
    echo "<pre>";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
    echo "Script Path: " . __FILE__ . "\n";
    echo "API Path: " . $apiFile . "\n";
    echo "Session ID: " . (session_id() ?: 'NO SESSION') . "\n";
    echo "</pre>";
    echo "</div>";
    
    if ($dbLoaded) {
        echo "<div class='check warning'>";
        echo "<strong>Database Info</strong>";
        echo "<pre>";
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM users");
            $count = $stmt->fetch();
            echo "Users in database: " . $count['count'] . "\n";
            
            $stmt = $db->query("SHOW COLUMNS FROM config LIKE 'users_can_customize_buttons'");
            $col = $stmt->fetch();
            if ($col) {
                echo "Column 'users_can_customize_buttons' exists: YES\n";
            } else {
                echo "Column 'users_can_customize_buttons' exists: NO (run migration!)\n";
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
        echo "</pre>";
        echo "</div>";
    }
    ?>
    
    <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
        <strong>💡 Next Steps:</strong>
        <ol>
            <li>Fix any errors shown above</li>
            <li>Replace <code>save_user_button_names.php</code> with the DEBUG version</li>
            <li>Check error logs: <code>/var/log/apache2/error.log</code> or <code>/var/log/php_errors.log</code></li>
            <li>Try saving button names from user profile</li>
            <li>Check browser console for errors</li>
        </ol>
    </div>
</body>
</html>
