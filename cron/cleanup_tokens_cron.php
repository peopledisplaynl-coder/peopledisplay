<?php
/**
 * ============================================================================
 * BESTANDSNAAM:  cleanup_tokens.php
 * UPLOAD NAAR:   /cron/cleanup_tokens.php
 * DATUM:         2024-12-04
 * VERSIE:        v1.0
 * 
 * DOEL: Verwijder verlopen remember me tokens
 * Voorkomt database bloat
 * 
 * CRON JOB SETUP:
 * 0 2 * * * php /var/www/html/peopledisplay/cron/cleanup_tokens.php
 * (Elke nacht om 02:00)
 * ============================================================================
 */

// Security: Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    // Allow browser access only with secret key
    $secretKey = $_GET['key'] ?? '';
    $expectedKey = 'INZ8U56BCZ'; // CHANGE THIS IN PRODUCTION!
    
    if ($secretKey !== $expectedKey) {
        http_response_code(403);
        die('Access denied. This script can only be run via cron or with valid key.');
    }
}

// Include database connection
require_once __DIR__ . '/../includes/db.php';

try {
    // Count expired tokens before deletion
    $countStmt = $db->query("SELECT COUNT(*) as count FROM remember_tokens WHERE expires_at < NOW()");
    $expiredCount = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Delete expired tokens
    $stmt = $db->prepare("DELETE FROM remember_tokens WHERE expires_at < NOW()");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    
    // Log result
    $logMessage = sprintf(
        "[%s] Token Cleanup: %d expired tokens deleted\n",
        date('Y-m-d H:i:s'),
        $deleted
    );
    
    // Write to log file
    $logFile = __DIR__ . '/cleanup_tokens.log';
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Output for cron email
    echo $logMessage;
    echo "Status: SUCCESS\n";
    
    exit(0);
    
} catch (Exception $e) {
    $errorMessage = sprintf(
        "[%s] Token Cleanup FAILED: %s\n",
        date('Y-m-d H:i:s'),
        $e->getMessage()
    );
    
    // Write to log file
    $logFile = __DIR__ . '/cleanup_tokens.log';
    file_put_contents($logFile, $errorMessage, FILE_APPEND);
    
    // Output error
    echo $errorMessage;
    echo "Status: ERROR\n";
    
    exit(1);
}
