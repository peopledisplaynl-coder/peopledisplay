<?php
/**
 * ============================================================
 * PEOPLEDISPLAY - DATABASE SETUP SCRIPT
 * ============================================================
 * Creates all 18 database tables for PeopleDisplay system
 * Including remember_tokens for "Remember Me" functionality
 * 
 * Version: 1.1.0
 * Last Updated: November 2024
 * ============================================================
 */

header('Content-Type: application/json');

// Start session for installer
session_start();

// Check if this is being called from installer
if (!isset($_SESSION['installer_active'])) {
    die(json_encode([
        'success' => false,
        'error' => 'Unauthorized access. Please run installer first.'
    ]));
}

// Get database credentials from POST
$host = $_POST['db_host'] ?? 'localhost';
$dbname = $_POST['db_name'] ?? '';
$user = $_POST['db_user'] ?? '';
$pass = $_POST['db_pass'] ?? '';
$prefix = $_POST['table_prefix'] ?? '';

if (empty($dbname) || empty($user)) {
    die(json_encode([
        'success' => false,
        'error' => 'Database name and username are required'
    ]));
}

try {
    // Connect to MySQL server (without database first)
    $dsn = "mysql:host=$host;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");
    
    $tables_created = [];
    $errors = [];
    
    // ============================================================
    // 1. USERS TABLE
    // ============================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$prefix}users` (
              `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
              `username` varchar(100) NOT NULL,
              `password_hash` varchar(255) NOT NULL,
              `display_name` varchar(150) DEFAULT NULL,
              `email` varchar(255) DEFAULT NULL,
              `profile_photo` varchar(255) DEFAULT NULL,
              `role` enum('superadmin','admin','user') NOT NULL DEFAULT 'user',
              `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON format user preferences',
              `active` tinyint(1) NOT NULL DEFAULT 1,
              `can_show_presentation` tinyint(1) NOT NULL DEFAULT 0,
              `presentation_id` varchar(255) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `username` (`username`),
              KEY `idx_active` (`active`),
              KEY `idx_role` (`role`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $tables_created[] = 'users';
    } catch (PDOException $e) {
        $errors[] = "Users table: " . $e->getMessage();
    }
    
    // ============================================================
    // 2. REMEMBER_TOKENS TABLE 🆕
    // ============================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$prefix}remember_tokens` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(10) UNSIGNED NOT NULL,
              `token` varchar(64) NOT NULL,
              `expires_at` datetime NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `token` (`token`),
              KEY `idx_user_id` (`user_id`),
              KEY `idx_expires` (`expires_at`),
              CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `{$prefix}users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Tokens voor Ingelogd blijven functie (30 dagen)'
        ");
        $tables_created[] = 'remember_tokens';
    } catch (PDOException $e) {
        $errors[] = "Remember tokens table: " . $e->getMessage();
    }
    
    // ============================================================
    // 3. CONFIG TABLE
    // ============================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$prefix}config` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `scriptURL` text DEFAULT NULL COMMENT 'Google Apps Script Web App URL',
              `sheetID` text DEFAULT NULL COMMENT 'Google Sheet ID (optional)',
              `presentationID` text DEFAULT NULL COMMENT 'Google Slides ID (optional)',
              `visibleFields` text DEFAULT NULL COMMENT 'JSON array of visible fields',
              `locations` text DEFAULT NULL COMMENT 'JSON array of all locations',
              `locations_order` text DEFAULT NULL COMMENT 'JSON array with sort order',
              `extraButtons` text DEFAULT NULL COMMENT 'JSON object with extra button settings',
              `button1_name` varchar(50) DEFAULT 'PAUZE' COMMENT 'Global name for button 1',
              `button2_name` varchar(50) DEFAULT 'THUISWERKEN' COMMENT 'Global name for button 2',
              `button3_name` varchar(50) DEFAULT 'VAKANTIE' COMMENT 'Global name for button 3',
              `allow_user_button_names` tinyint(1) DEFAULT 0 COMMENT 'Allow users to set custom button names',
              `allow_auto_fullscreen` tinyint(1) NOT NULL DEFAULT 0,
              `presentationAutoShowMs` int(11) DEFAULT NULL COMMENT 'Auto-show presentation after X milliseconds',
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $tables_created[] = 'config';
        
        // Insert default config
        $pdo->exec("
            INSERT IGNORE INTO `{$prefix}config` 
            (`id`, `button1_name`, `button2_name`, `button3_name`, `allow_user_button_names`) 
            VALUES (1, 'PAUZE', 'THUISWERKEN', 'VAKANTIE', 1)
        ");
    } catch (PDOException $e) {
        $errors[] = "Config table: " . $e->getMessage();
    }
    
    // ============================================================
    // 4. USER_BUTTON_NAMES TABLE
    // ============================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$prefix}user_button_names` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `button1_name` varchar(20) DEFAULT NULL COMMENT 'Custom name for button 1 (max 20 chars)',
              `button2_name` varchar(20) DEFAULT NULL COMMENT 'Custom name for button 2 (max 20 chars)',
              `button3_name` varchar(20) DEFAULT NULL COMMENT 'Custom name for button 3 (max 20 chars)',
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_user` (`user_id`),
              KEY `idx_user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Custom button names per user (overrides global names)'
        ");
        $tables_created[] = 'user_button_names';
    } catch (PDOException $e) {
        $errors[] = "User button names table: " . $e->getMessage();
    }
    
    // ============================================================
    // 5. BUTTON_CONFIG TABLE
    // ============================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$prefix}button_config` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `button_key` varchar(50) NOT NULL COMMENT 'button1, button2, button3',
              `name` varchar(50) NOT NULL DEFAULT 'BUTTON',
              `color` varchar(20) DEFAULT '#cccccc',
              `enabled` tinyint(1) NOT NULL DEFAULT 1,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `button_key` (`button_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $tables_created[] = 'button_config';
        
        // Insert default button configuration
        $pdo->exec("
            INSERT IGNORE INTO `{$prefix}button_config` (`button_key`, `name`, `color`, `enabled`) VALUES
            ('button1', 'PAUZE', '#ff69b4', 1),
            ('button2', 'THUISWERKEN', '#9370db', 1),
            ('button3', 'VAKANTIE', '#9acd32', 1)
        ");
    } catch (PDOException $e) {
        $errors[] = "Button config table: " . $e->getMessage();
    }
    
    // ============================================================
    // 6. ADMINS TABLE (Legacy)
    // ============================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$prefix}admins` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `username` varchar(100) NOT NULL,
              `password_hash` varchar(255) NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Legacy table for backwards compatibility'
        ");
        $tables_created[] = 'admins';
    } catch (PDOException $e) {
        $errors[] = "Admins table: " . $e->getMessage();
    }
    
    // ============================================================
    // 7. FEATURE_KEYS TABLE
    // ============================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$prefix}feature_keys` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `feature_key` varchar(100) NOT NULL,
              `feature_name` varchar(150) DEFAULT NULL,
              `feature_group` varchar(50) DEFAULT NULL COMMENT 'fields, buttons, locations',
              `description` text DEFAULT NULL,
              `is_active` tinyint(1) NOT NULL DEFAULT 1,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `feature_key` (`feature_key`),
              KEY `idx_group` (`feature_group`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $tables_created[] = 'feature_keys';
    } catch (PDOException $e) {
        $errors[] = "Feature keys table: " . $e->getMessage();
    }
    
    // ============================================================
    // 8. FEATURE_AUDIT TABLE
    // ============================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$prefix}feature_audit` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `feature_key` varchar(100) NOT NULL,
              `old_value` text DEFAULT NULL,
              `new_value` text DEFAULT NULL,
              `changed_by` varchar(100) DEFAULT NULL,
              `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_user` (`user_id`),
              KEY `idx_feature` (`feature_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Audit log for feature changes'
        ");
        $tables_created[] = 'feature_audit';
    } catch (PDOException $e) {
        $errors[] = "Feature audit table: " . $e->getMessage();
    }
    
    // ============================================================
    // 9. FILTERS TABLE
    // ============================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$prefix}filters` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `filter_key` varchar(100) NOT NULL,
              `filter_name` varchar(150) DEFAULT NULL,
              `filter_type` varchar(50) DEFAULT NULL COMMENT 'dropdown, checkbox, text',
              `options` text DEFAULT NULL COMMENT 'JSON array of options',
              `is_active` tinyint(1) NOT NULL DEFAULT 1,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `filter_key` (`filter_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $tables_created[] = 'filters';
    } catch (PDOException $e) {
        $errors[] = "Filters table: " . $e->getMessage();
    }
    
    // ============================================================
    // 10. LABEE_CONFIG TABLE (Legacy)
    // ============================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$prefix}labee_config` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `key_name` varchar(100) NOT NULL,
              `key_value` text DEFAULT NULL,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `key_name` (`key_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Legacy configuration table'
        ");
        $tables_created[] = 'labee_config';
    } catch (PDOException $e) {
        $errors[] = "Labee config table: " . $e->getMessage();
    }
    
    // ============================================================
    // 11. LOCATIONS TABLE
    // ============================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$prefix}locations` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `location_code` varchar(20) DEFAULT NULL COMMENT 'Numeric code (e.g., 05, 100)',
              `location_name` varchar(255) NOT NULL,
              `location_full` varchar(255) DEFAULT NULL COMMENT 'Full name with code',
              `is_active` tinyint(1) NOT NULL DEFAULT 1,
              `sort_order` int(11) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `location_name` (`location_name`),
              KEY `idx_code` (`location_code`),
              KEY `idx_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $tables_created[] = 'locations';
    } catch (PDOException $e) {
        $errors[] = "Locations table: " . $e->getMessage();
    }
    
    // ============================================================
    // 12. ROLES TABLE
    // ============================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$prefix}roles` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `role_key` varchar(50) NOT NULL,
              `role_name` varchar(100) NOT NULL,
              `description` text DEFAULT NULL,
              `level` int(11) NOT NULL DEFAULT 0 COMMENT 'Higher = more privileges',
              `is_active` tinyint(1) NOT NULL DEFAULT 1,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `role_key` (`role_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $tables_created[] = 'roles';
        
        // Insert default roles
        $pdo->exec("
            INSERT IGNORE INTO `{$prefix}roles` (`role_key`, `role_name`, `level`) VALUES
            ('superadmin', 'SuperAdmin', 100),
            ('admin', 'Admin', 50),
            ('user', 'User', 10)
        ");
    } catch (PDOException $e) {
        $errors[] = "Roles table: " . $e->getMessage();
    }
    
    // ============================================================
    // 13. ROLES_PERMISSIONS TABLE
    // ============================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$prefix}roles_permissions` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `role_id` int(11) NOT NULL,
              `permission_key` varchar(100) NOT NULL,
              `permission_value` tinyint(1) NOT NULL DEFAULT 1,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `role_permission` (`role_id`,`permission_key`),
              KEY `idx_role` (`role_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $tables_created[] = 'roles_permissions';
    } catch (PDOException $e) {
        $errors[] = "Roles permissions table: " . $e->getMessage();
    }
    
    // ============================================================
    // 14. USERS_ROLES TABLE
    // ============================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$prefix}users_roles` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `role_id` int(11) NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `user_role` (`user_id`,`role_id`),
              KEY `idx_user` (`user_id`),
              KEY `idx_role` (`role_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $tables_created[] = 'users_roles';
    } catch (PDOException $e) {
        $errors[] = "Users roles table: " . $e->getMessage();
    }
    
    // ============================================================
    // 15. USER_FEATURES TABLE
    // ============================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$prefix}user_features` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `feature_key` varchar(100) NOT NULL,
              `feature_value` text DEFAULT NULL,
              `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `user_feature` (`user_id`,`feature_key`),
              KEY `idx_user` (`user_id`),
              KEY `idx_feature` (`feature_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $tables_created[] = 'user_features';
    } catch (PDOException $e) {
        $errors[] = "User features table: " . $e->getMessage();
    }
    
    // ============================================================
    // 16. USER_FILTERS TABLE
    // ============================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$prefix}user_filters` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `filter_key` varchar(100) NOT NULL,
              `filter_value` text DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `user_filter` (`user_id`,`filter_key`),
              KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $tables_created[] = 'user_filters';
    } catch (PDOException $e) {
        $errors[] = "User filters table: " . $e->getMessage();
    }
    
    // ============================================================
    // 17. USER_LOCATIONS TABLE
    // ============================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$prefix}user_locations` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `location_id` int(11) DEFAULT NULL,
              `location_name` varchar(255) DEFAULT NULL COMMENT 'For backwards compatibility',
              `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_user` (`user_id`),
              KEY `idx_location` (`location_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $tables_created[] = 'user_locations';
    } catch (PDOException $e) {
        $errors[] = "User locations table: " . $e->getMessage();
    }
    
    // ============================================================
    // 18. USER_MENU TABLE
    // ============================================================
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$prefix}user_menu` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `menu_key` varchar(100) NOT NULL,
              `menu_order` int(11) DEFAULT NULL,
              `is_visible` tinyint(1) NOT NULL DEFAULT 1,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `user_menu` (`user_id`,`menu_key`),
              KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $tables_created[] = 'user_menu';
    } catch (PDOException $e) {
        $errors[] = "User menu table: " . $e->getMessage();
    }
    
    // ============================================================
    // STORE DATABASE CREDENTIALS
    // ============================================================
    
    // Create includes directory if not exists
    $includesDir = __DIR__ . '/../../includes';
    if (!file_exists($includesDir)) {
        mkdir($includesDir, 0755, true);
    }
    
    // Create db.php config file
    $dbConfigContent = "<?php
/**
 * Database Configuration
 * Auto-generated by PeopleDisplay installer
 * Generated: " . date('Y-m-d H:i:s') . "
 */

\$host = '$host';
\$dbname = '$dbname';
\$user = '$user';
\$pass = '$pass';

try {
    \$dsn = \"mysql:host=\$host;dbname=\$dbname;charset=utf8mb4\";
    \$options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    \$db = new PDO(\$dsn, \$user, \$pass, \$options);
    
    // Session configuration for Strato compatibility
    session_save_path(__DIR__ . '/../tmp/sessions');
    if (!file_exists(__DIR__ . '/../tmp/sessions')) {
        mkdir(__DIR__ . '/../tmp/sessions', 0755, true);
    }
    
} catch (PDOException \$e) {
    die('Database connection failed: ' . \$e->getMessage());
}
";
    
    file_put_contents($includesDir . '/db.php', $dbConfigContent);
    
    // Create admin db_config.php for backwards compatibility
    $adminDir = __DIR__ . '/../../admin';
    if (!file_exists($adminDir)) {
        mkdir($adminDir, 0755, true);
    }
    
    $adminDbConfigContent = "<?php
/**
 * Admin Database Configuration
 * Auto-generated by PeopleDisplay installer
 * Generated: " . date('Y-m-d H:i:s') . "
 */

\$host = '$host';
\$dbname = '$dbname';
\$user = '$user';
\$pass = '$pass';

try {
    \$dsn = \"mysql:host=\$host;dbname=\$dbname;charset=utf8mb4\";
    \$options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    
    \$pdo = new PDO(\$dsn, \$user, \$pass, \$options);
    
} catch (PDOException \$e) {
    die('Database connection failed: ' . \$e->getMessage());
}
";
    
    file_put_contents($adminDir . '/db_config.php', $adminDbConfigContent);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Database setup completed successfully',
        'tables_created' => count($tables_created),
        'tables' => $tables_created,
        'errors' => $errors,
        'includes_path' => $includesDir,
        'db_config_created' => file_exists($includesDir . '/db.php')
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database setup failed: ' . $e->getMessage(),
        'tables_created' => $tables_created ?? [],
        'errors' => $errors ?? []
    ]);
}
