<?php
/**
 * ============================================================
 * PEOPLE DISPLAY - INSTALLER ENTRY POINT
 * ============================================================
 * WordPress-style installation wizard
 * Versie: 1.0.0
 * 
 * Dit bestand redirects naar de installer wizard
 * ============================================================
 */

// Check if already installed
if (file_exists(__DIR__ . '/config.php') && file_exists(__DIR__ . '/admin/db_config.php')) {
    // Check if installer lock exists
    if (file_exists(__DIR__ . '/.installed')) {
        die('
        <!DOCTYPE html>
        <html lang="nl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Already Installed</title>
            <style>
                body {
                    font-family: system-ui, -apple-system, sans-serif;
                    background: #f0f0f1;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                    padding: 20px;
                }
                .message {
                    background: white;
                    padding: 40px;
                    border-radius: 8px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                    max-width: 500px;
                    text-align: center;
                }
                h1 { color: #d63638; margin: 0 0 20px; }
                p { color: #50575e; line-height: 1.6; }
                .btn {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 12px 24px;
                    background: #2271b1;
                    color: white;
                    text-decoration: none;
                    border-radius: 4px;
                    font-weight: 600;
                }
                .btn:hover { background: #135e96; }
            </style>
        </head>
        <body>
            <div class="message">
                <h1>✅ Already Installed</h1>
                <p><strong>People Display is already installed!</strong></p>
                <p>If you need to reinstall, please delete the following files:</p>
                <ul style="text-align: left; display: inline-block;">
                    <li><code>config.php</code></li>
                    <li><code>admin/db_config.php</code></li>
                    <li><code>.installed</code></li>
                </ul>
                <a href="login.php" class="btn">🔑 Go to Login</a>
            </div>
        </body>
        </html>
        ');
    }
}

// Redirect to installer
header('Location: install/index.php');
exit;
