<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: users_manage_v2.php
 * LOCATIE:      /admin/users_manage.php
 * VERSIE:       2.0 - Complete User Management (Table Layout)
 * 
 * FEATURES:
 * - Tabel overzicht met alle users
 * - Sorteerbaar op elke kolom
 * - Live zoeken & filteren
 * - Inline editing (expandable rows)
 * - Account + Features + Presentatie in 1 pagina
 * ═══════════════════════════════════════════════════════════════════
 */

// CRITICAL: NO CACHE HEADERS
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/license_check.php';
require_once __DIR__ . '/auth_helper.php';

requireAdmin();

$message    = '';
$error      = '';
$limitAlert = null;

if (!empty($_SESSION['pd_flash'])) {
    $message = $_SESSION['pd_flash'];
    unset($_SESSION['pd_flash']);
}
if (!empty($_SESSION['pd_limit_alert'])) {
    $alertData  = $_SESSION['pd_limit_alert'];
    unset($_SESSION['pd_limit_alert']);
    if (!function_exists('getLimitExceededMessage')) {
        require_once __DIR__ . '/../includes/license.php';
    }
    $limitAlert = getLimitExceededMessage($alertData['type'], (int)$alertData['limit']);
}

// Check if current user is superadmin
$currentUserId = $_SESSION['user_id'];
$currentUserStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$currentUserStmt->execute([$currentUserId]);
$currentUserData = $currentUserStmt->fetch();
$isSuperAdmin = ($currentUserData['role'] === 'superadmin');

/**
 * Ensure the database has the user_groups table and related column.
 * This is safe to run on every page load for admin pages.
 */
function ensureUserGroupsSchema(PDO $db): void
{
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS user_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $col = $db->query("SHOW COLUMNS FROM users LIKE 'group_id'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE users ADD COLUMN group_id INT NULL AFTER role");
        }

        // Add foreign key constraint if it doesn't exist
        $fk = $db->prepare("SELECT COUNT(*) as cnt FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'group_id' AND REFERENCED_TABLE_NAME = 'user_groups'");
        $fk->execute();
        $fkExists = (int) $fk->fetchColumn();
        if ($fkExists === 0) {
            $db->exec("ALTER TABLE users ADD CONSTRAINT fk_users_group_id FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE SET NULL");
        }
    } catch (Throwable $e) {
        error_log('User groups schema ensure failed: ' . $e->getMessage());
    }
}

ensureUserGroupsSchema($db);

// Load groups for filtering and assignment
$groupsStmt = $db->query("SELECT id, name, description FROM user_groups ORDER BY name ASC");
$groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);

// ═══════════════════════════════════════════════════════════════════
// AJAX ENDPOINTS
// ═══════════════════════════════════════════════════════════════════

// Get all groups (for dropdowns / filtering)
if (isset($_GET['action']) && $_GET['action'] === 'get_groups') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'groups' => $groups]);
    exit;
}

// Create or update a group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['create_group', 'update_group'], true)) {
    header('Content-Type: application/json');

    if (!$isSuperAdmin) {
        echo json_encode(['success' => false, 'error' => 'Alleen SuperAdmin kan groepen beheren']);
        exit;
    }

    $groupName = trim($_POST['name'] ?? '');
    $groupDesc = trim($_POST['description'] ?? '');

    if ($groupName === '') {
        echo json_encode(['success' => false, 'error' => 'Groepsnaam is verplicht']);
        exit;
    }

    try {
        if ($_POST['action'] === 'create_group') {
            $stmt = $db->prepare("INSERT INTO user_groups (name, description) VALUES (?, ?)");
            $stmt->execute([$groupName, $groupDesc]);
            echo json_encode(['success' => true, 'message' => 'Groep aangemaakt']);
        } else {
            $groupId = intval($_POST['group_id'] ?? 0);
            if ($groupId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Ongeldige groep']);
                exit;
            }
            $stmt = $db->prepare("UPDATE user_groups SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$groupName, $groupDesc, $groupId]);
            echo json_encode(['success' => true, 'message' => 'Groep bijgewerkt']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database fout: ' . $e->getMessage()]);
    }
    exit;
}

// Delete a group
if (isset($_GET['action']) && $_GET['action'] === 'delete_group' && isset($_GET['group_id'])) {
    header('Content-Type: application/json');

    if (!$isSuperAdmin) {
        echo json_encode(['success' => false, 'error' => 'Alleen SuperAdmin kan groepen verwijderen']);
        exit;
    }

    $groupId = intval($_GET['group_id']);
    if ($groupId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Ongeldige groep']);
        exit;
    }

    try {
        $stmt = $db->prepare("DELETE FROM user_groups WHERE id = ?");
        $stmt->execute([$groupId]);
        echo json_encode(['success' => true, 'message' => 'Groep verwijderd']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database fout: ' . $e->getMessage()]);
    }
    exit;
}

// Get user details (for editing)
if (isset($_GET['action']) && $_GET['action'] === 'get_user' && isset($_GET['user_id'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    
    $userId = intval($_GET['user_id']);
    $stmt = $db->prepare("SELECT SQL_NO_CACHE * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Parse features JSON
        $features = json_decode($user['features'] ?? '{}', true) ?: [];
        
        // Get all locations for checkboxes
        $locationsStmt = $db->query("SELECT location_name FROM locations WHERE active = 1 ORDER BY sort_order, location_name");
        $allLocations = $locationsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get all afdelingen for checkboxes
        $afdelingenStmt = $db->query("SELECT id, afdeling_name FROM afdelingen WHERE active = 1 ORDER BY sort_order, afdeling_name");
        $allAfdelingen = $afdelingenStmt->fetchAll(PDO::FETCH_ASSOC);
        $allAfdelingenFormatted = array_map(function($afd) {
            return ['id' => $afd['id'], 'name' => $afd['afdeling_name']];
        }, $allAfdelingen);
        
        // Get user's selected afdelingen
        $userAfdelingenStmt = $db->prepare("SELECT afdeling_id FROM user_afdelingen WHERE user_id = ?");
        $userAfdelingenStmt->execute([$userId]);
        $userAfdelingen = $userAfdelingenStmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode([
            'success' => true,
            'user' => $user,
            'features' => $features,
            'allLocations' => $allLocations,
            'allAfdelingen' => $allAfdelingenFormatted,
            'afdelingen' => array_map('intval', $userAfdelingen)  // Convert to integers
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found']);
    }
    exit;
}

// Save user (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_user') {
    header('Content-Type: application/json');
    
    // DEBUG LOGGING - Write to accessible file
    $logFile = __DIR__ . '/../users_manage_debug.log';
    $logData = "\n=== SAVE USER DEBUG - " . date('Y-m-d H:i:s') . " ===\n";
    $logData .= "POST data: " . json_encode($_POST) . "\n";
    $logData .= "btn_pauze isset: " . (isset($_POST['btn_pauze']) ? 'TRUE' : 'FALSE') . "\n";
    $logData .= "btn_thuiswerken isset: " . (isset($_POST['btn_thuiswerken']) ? 'TRUE' : 'FALSE') . "\n";
    $logData .= "btn_vakantie isset: " . (isset($_POST['btn_vakantie']) ? 'TRUE' : 'FALSE') . "\n";
    
    $userId = intval($_POST['user_id']);
    $displayName = trim($_POST['display_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $active = isset($_POST['active']) ? 1 : 0;
    $groupId = isset($_POST['group_id']) && $_POST['group_id'] !== '' ? intval($_POST['group_id']) : null;
    
    // Presentatie settings
    $canShowPresentation = isset($_POST['can_show_presentation']) ? 1 : 0;
    $presentationId = trim($_POST['presentation_id'] ?? '');
    $presentationIdleSeconds = intval($_POST['presentation_idle_seconds'] ?? 120);
    
    // Scanner settings
    $canUseScanner = isset($_POST['can_use_scanner']) ? 1 : 0;
    
    // Features
    $visibleFields = $_POST['visible_fields'] ?? [];
    $extraButtons = [
        'PAUZE' => ($_POST['btn_pauze'] ?? '0') === '1',
        'THUISWERKEN' => ($_POST['btn_thuiswerken'] ?? '0') === '1',
        'VAKANTIE' => ($_POST['btn_vakantie'] ?? '0') === '1'
    ];
    $logData .= "btn_pauze value: " . ($_POST['btn_pauze'] ?? 'NOT SET') . "\n";
    $logData .= "btn_thuiswerken value: " . ($_POST['btn_thuiswerken'] ?? 'NOT SET') . "\n";
    $logData .= "btn_vakantie value: " . ($_POST['btn_vakantie'] ?? 'NOT SET') . "\n";
    $logData .= "extraButtons array: " . json_encode($extraButtons) . "\n";
    
    $locations = $_POST['locations'] ?? [];
    
    // ✨ Sorteer Toggle Feature
    $sorteerFunctie = isset($_POST['can_toggle_sort']) ? true : false;
    
    $features = json_encode([
        'visibleFields' => $visibleFields,
        'extraButtons' => $extraButtons,
        'locations' => $locations,
        'sorteerFunctie' => $sorteerFunctie  // ← CORRECT FIELD NAME!
    ]);
    $logData .= "features JSON: " . $features . "\n";
    $logData .= "sorteerFunctie: " . ($sorteerFunctie ? 'TRUE' : 'FALSE') . "\n";
    $logData .= "User ID: " . $userId . "\n";
    $logData .= "======================\n";
    
    file_put_contents($logFile, $logData, FILE_APPEND);
    
    // Validation
    if (empty($displayName)) {
        echo json_encode(['success' => false, 'error' => 'Display name is verplicht']);
        exit;
    }
    
    // Check permissions
    $targetUser = $db->query("SELECT role FROM users WHERE id = $userId")->fetch();
    if (!$isSuperAdmin && $targetUser['role'] === 'superadmin') {
        echo json_encode(['success' => false, 'error' => 'Alleen SuperAdmin kan andere SuperAdmins bewerken']);
        exit;
    }
    if (!$isSuperAdmin && $role === 'superadmin') {
        echo json_encode(['success' => false, 'error' => 'Alleen SuperAdmin kan gebruikers tot SuperAdmin maken']);
        exit;
    }
    
    try {
        $logData .= "=== STARTING SAVE ===\n";
        
        // NO TRANSACTION - Direct update
        $logData .= "Preparing UPDATE statement...\n";
        
        $stmt = $db->prepare("
            UPDATE users 
            SET display_name = ?, 
                email = ?, 
                role = ?, 
                group_id = ?,
                active = ?, 
                features = ?,
                can_show_presentation = ?, 
                presentation_id = ?, 
                presentation_idle_seconds = ?,
                can_use_scanner = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $logData .= "Executing UPDATE...\n";
        $result = $stmt->execute([
            $displayName, 
            $email, 
            $role, 
            $groupId,
            $active, 
            $features,
            $canShowPresentation, 
            $presentationId, 
            $presentationIdleSeconds,
            $canUseScanner,
            $userId
        ]);
        
        $rowsAffected = $stmt->rowCount();
        $logData .= "UPDATE executed. Result: " . ($result ? 'TRUE' : 'FALSE') . ", Rows affected: $rowsAffected\n";
        
        // CRITICAL: Verify IMMEDIATELY in separate query
        $logData .= "Verifying saved data...\n";
        $verifyStmt = $db->prepare("SELECT presentation_idle_seconds, updated_at, LENGTH(features) as flen FROM users WHERE id = ?");
        $verifyStmt->execute([$userId]);
        $savedData = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        $logData .= "VERIFY RESULT:\n";
        $logData .= "  - presentation_idle_seconds: " . $savedData['presentation_idle_seconds'] . "\n";
        $logData .= "  - updated_at: " . $savedData['updated_at'] . "\n";
        $logData .= "  - features length: " . $savedData['flen'] . "\n";
        
        // Update password if provided
        if (!empty($_POST['new_password'])) {
            $passwordHash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?")->execute([$passwordHash, $userId]);
            $logData .= "Password updated\n";
        }
        
        // Update user_afdelingen
        $afdelingen = $_POST['afdelingen'] ?? [];
        $logData .= "Afdelingen to save: " . json_encode($afdelingen) . "\n";
        
        // Delete existing afdelingen for this user
        $deleteResult = $db->prepare("DELETE FROM user_afdelingen WHERE user_id = ?")->execute([$userId]);
        $logData .= "Deleted existing afdelingen\n";
        
        // Insert new afdelingen
        if (!empty($afdelingen)) {
            $insertStmt = $db->prepare("INSERT INTO user_afdelingen (user_id, afdeling_id) VALUES (?, ?)");
            foreach ($afdelingen as $afdelingId) {
                $insertStmt->execute([$userId, intval($afdelingId)]);
            }
            $logData .= "Inserted " . count($afdelingen) . " afdelingen\n";
        }
        
        $logData .= "=== SAVE COMPLETE ===\n";
        file_put_contents($logFile, $logData, FILE_APPEND);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Gebruiker succesvol bijgewerkt',
            'debug' => [
                'userId' => $userId,
                'rowsAffected' => $rowsAffected,
                'verifiedIdleSeconds' => $savedData['presentation_idle_seconds'],
                'verifiedUpdatedAt' => $savedData['updated_at'],
                'sentIdleSeconds' => $presentationIdleSeconds
            ]
        ]);
    } catch (PDOException $e) {
        $logData .= "DATABASE ERROR: " . $e->getMessage() . "\n";
        $logData .= "Stack trace: " . $e->getTraceAsString() . "\n";
        file_put_contents($logFile, $logData, FILE_APPEND);
        echo json_encode(['success' => false, 'error' => 'Database fout: ' . $e->getMessage()]);
    }
    exit;
}

// Create new user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    header('Content-Type: application/json');
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $displayName = trim($_POST['display_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    
    // Validation
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Username en wachtwoord verplicht']);
        exit;
    }
    
    if (!$isSuperAdmin && $role === 'superadmin') {
        echo json_encode(['success' => false, 'error' => 'Alleen SuperAdmin kan andere SuperAdmins maken']);
        exit;
    }
    
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $db->prepare("
            INSERT INTO users (username, password_hash, display_name, email, role, active, features)
            VALUES (?, ?, ?, ?, ?, 1, '{}')
        ");
        $stmt->execute([$username, $passwordHash, $displayName, $email, $role]);
        
        echo json_encode(['success' => true, 'message' => "Gebruiker '$username' succesvol aangemaakt"]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo json_encode(['success' => false, 'error' => 'Username bestaat al']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database fout: ' . $e->getMessage()]);
        }
    }
    exit;
}

// Delete user
if (isset($_GET['action']) && $_GET['action'] === 'delete_user' && isset($_GET['user_id'])) {
    header('Content-Type: application/json');
    
    $userId = intval($_GET['user_id']);
    
    // Check permissions
    $targetUser = $db->query("SELECT role FROM users WHERE id = $userId")->fetch();
    if (!$isSuperAdmin && $targetUser['role'] === 'superadmin') {
        echo json_encode(['success' => false, 'error' => 'Alleen SuperAdmin kan andere SuperAdmins verwijderen']);
        exit;
    }
    
    try {
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
        echo json_encode(['success' => true, 'message' => 'Gebruiker verwijderd']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Fout bij verwijderen: ' . $e->getMessage()]);
    }
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// GET ALL USERS FOR TABLE
// ═══════════════════════════════════════════════════════════════════

// FORCE NO CACHE - get fresh data every time
$usersStmt = $db->query("
    SELECT SQL_NO_CACHE u.id, u.username, u.display_name, u.email, u.role, u.active, 
           u.group_id, g.name AS group_name,
           u.can_show_presentation, u.presentation_id, u.presentation_idle_seconds,
           u.can_use_scanner,
           u.features,
           u.created_at,
           u.updated_at
    FROM users u
    LEFT JOIN user_groups g ON u.group_id = g.id
    ORDER BY u.username ASC
");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gebruikers Beheren v2.0</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: #f7fafc;
            padding: 20px;
            color: #2d3748;
        }
        
        .container { 
            max-width: 1600px; 
            margin: 0 auto; 
        }
        
        /* ═══ HEADER ═══ */
        .page-header {
            background: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .page-header h1 {
            font-size: 28px;
            color: #1a202c;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-back {
            background: #718096;
            color: white;
        }
        
        .btn-back:hover {
            background: #4a5568;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: #48bb78;
            color: white;
        }
        
        .btn-success:hover {
            background: #38a169;
        }
        
        .btn-danger {
            background: #f56565;
            color: white;
        }
        
        .btn-danger:hover {
            background: #e53e3e;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        /* ═══ TOOLBAR ═══ */
        .toolbar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .filter-group select {
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }
        
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .checkbox-filter {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            background: #f7fafc;
            border-radius: 8px;
        }
        
        .checkbox-filter input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        /* ═══ TABLE ═══ */
        .users-table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .users-table th {
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            user-select: none;
            position: relative;
        }
        
        .users-table th:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .users-table th.sortable::after {
            content: ' ↕';
            opacity: 0.5;
            font-size: 12px;
        }
        
        .users-table th.sorted-asc::after {
            content: ' ↑';
            opacity: 1;
        }
        
        .users-table th.sorted-desc::after {
            content: ' ↓';
            opacity: 1;
        }
        
        .users-table tbody tr {
            border-bottom: 1px solid #e2e8f0;
            transition: background 0.2s;
        }
        
        .users-table tbody tr:hover {
            background: #f7fafc;
        }
        
        .users-table td {
            padding: 12px;
            font-size: 14px;
        }
        
        .user-role {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .role-superadmin {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .role-admin {
            background: #4299e1;
            color: white;
        }
        
        .role-user {
            background: #48bb78;
            color: white;
        }

        .user-group-badge {
            display: inline-block;
            margin-left: 8px;
            padding: 4px 10px;
            border-radius: 10px;
            background: #e2e8f0;
            color: #2d3748;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-inactive {
            background: #fed7d7;
            color: #c53030;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-icon {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.2s;
            background: #e2e8f0;
        }
        
        .btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .btn-edit {
            background: #4299e1;
            color: white;
        }
        
        .btn-delete {
            background: #f56565;
            color: white;
        }
        
        /* ═══ EXPANDABLE ROW ═══ */
        .edit-row {
            display: none;
            background: #f7fafc;
        }
        
        .edit-row.active {
            display: table-row;
        }
        
        .edit-content {
            padding: 30px;
        }
        
        .edit-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .edit-header h3 {
            font-size: 20px;
            color: #1a202c;
        }
        
        .edit-actions {
            display: flex;
            gap: 10px;
        }
        
        .edit-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .edit-section h4 {
            font-size: 16px;
            color: #2d3748;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        /* TAB NAVIGATION */
        .tabs-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .tabs-header {
            display: flex;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            padding: 0;
            margin: 0;
            overflow-x: auto;
            gap: 4px;
        }
        
        .tab-button {
            flex: 1;
            min-width: 140px;
            padding: 16px 20px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            color: #64748b;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap;
        }
        
        .tab-button:hover {
            background: rgba(102, 126, 234, 0.08);
            color: #667eea;
        }
        
        .tab-button.active {
            background: white;
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            display: none;
            padding: 24px;
            background: white;
        }
        
        .tab-content.active {
            display: block;
            animation: tabFadeIn 0.3s ease;
        }
        
        .tab-content h4 {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #667eea;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        @keyframes tabFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ═══ GROUP FILTER ═══ */
        .group-filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .group-filter-button {
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: white;
            color: #4a5568;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .group-filter-button:hover {
            background: rgba(102, 126, 234, 0.1);
            border-color: #667eea;
        }
        
        .group-filter-button.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #4a5568;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-item label {
            cursor: pointer;
            font-size: 14px;
            margin: 0;
        }
        
        .idle-time-input {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .idle-time-input input {
            width: 120px;
            padding: 12px;
            font-size: 18px;
            font-weight: 600;
            text-align: center;
            border: 2px solid #3182ce;
            border-radius: 8px;
        }
        
        .idle-time-label {
            font-weight: 600;
            color: #2d3748;
            font-size: 16px;
        }
        
        .idle-time-preview {
            color: #718096;
            font-size: 14px;
        }
        
        .idle-time-preview span {
            font-weight: 600;
            color: #3182ce;
        }
        
        .info-text {
            color: #4a5568;
            font-size: 13px;
            line-height: 1.5;
            margin-top: 8px;
        }
        
        /* ═══ MODAL ═══ */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .modal-header h3 {
            font-size: 20px;
            color: #1a202c;
        }
        
        .modal-footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #e2e8f0;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        /* ═══ TOAST NOTIFICATIONS ═══ */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 2000;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .toast-success {
            background: #48bb78;
        }
        
        .toast-error {
            background: #f56565;
        }
        
        /* ═══ RESPONSIVE ═══ */
        @media (max-width: 1024px) {
            .users-table {
                font-size: 13px;
            }
            
            .users-table th,
            .users-table td {
                padding: 10px 8px;
            }
        }
        
        @media (max-width: 768px) {
            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                width: 100%;
            }
            
            .filter-group {
                flex-direction: column;
                width: 100%;
            }
            
            .filter-group select {
                width: 100%;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .license-limit-alert {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 20px;
            margin: 20px 0;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        .license-limit-alert .alert-icon { font-size: 48px; opacity: 0.9; }
        .license-limit-alert .alert-content { flex: 1; }
        .license-limit-alert .alert-content h3 { margin: 0 0 10px 0; font-size: 20px; }
        .license-limit-alert .alert-content p { margin: 5px 0; opacity: 0.95; }
        .license-limit-alert .alert-actions { flex-shrink: 0; }
        .btn-upgrade {
            background: white;
            color: #667eea;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            white-space: nowrap;
            transition: transform 0.2s;
            display: inline-block;
        }
        .btn-upgrade:hover { transform: scale(1.05); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="page-header">
            <div>
                <a href="dashboard.php" class="btn btn-back">← Dashboard</a>
                <h1 style="margin-top: 10px;">👥 Gebruikers Beheren</h1>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <?php if ($isSuperAdmin): ?>
                <button onclick="openGroupModal()" class="btn btn-secondary">⚙️ Groepen beheren</button>
                <?php endif; ?>
                <button onclick="openCreateModal()" class="btn btn-primary">+ Nieuwe Gebruiker</button>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div style="background:#d4edda;color:#155724;padding:15px;border-radius:6px;margin-bottom:20px;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($limitAlert): ?>
            <div class="license-limit-alert">
                <div class="alert-icon"><?= $limitAlert['icon'] ?></div>
                <div class="alert-content">
                    <h3><?= $limitAlert['title'] ?></h3>
                    <p><?= $limitAlert['message'] ?></p>
                    <p><?= $limitAlert['upgradeMessage'] ?></p>
                </div>
                <div class="alert-actions">
                    <a href="license_management.php" class="btn-upgrade">Upgrade Pakket</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- TOOLBAR -->
        <div class="toolbar">
            <div class="search-box">
                <input type="text" id="search-input" placeholder="🔍 Zoek op naam, username of email...">
            </div>
            
            <div class="filter-group">
                <select id="filter-role">
                    <option value="">Alle rollen</option>
                    <option value="superadmin">👑 SuperAdmin</option>
                    <option value="admin">🔧 Admin</option>
                    <option value="user">👤 User</option>
                </select>
                
                <div class="checkbox-filter">
                    <input type="checkbox" id="filter-active" checked>
                    <label for="filter-active">Alleen actieve users</label>
                </div>
            </div>
        </div>

        <!-- GROUP FILTER -->
        <div class="group-filter-bar" id="group-filter-bar">
            <button class="group-filter-button active" data-group-id="">Alle gebruikers</button>
            <?php foreach ($groups as $group): ?>
                <button class="group-filter-button" data-group-id="<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></button>
            <?php endforeach; ?>
        </div>
        
        <!-- TABLE -->
        <div class="users-table-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th class="sortable" data-sort="display_name">Naam</th>
                        <th class="sortable" data-sort="username">Username</th>
                        <th class="sortable" data-sort="email">Email</th>
                        <th class="sortable" data-sort="role">Rol</th>
                        <th class="sortable" data-sort="active">Status</th>
                        <th class="sortable" data-sort="can_show_presentation">Presentatie</th>
                        <th class="sortable" data-sort="can_use_scanner">Scanner</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody id="users-table-body">
                    <?php foreach ($users as $user): ?>
                    <tr class="user-row" data-user-id="<?= $user['id'] ?>" data-role="<?= $user['role'] ?>" data-active="<?= $user['active'] ?>" data-group-id="<?= $user['group_id'] ?? '' ?>">
                        <td>
                            <strong><?= htmlspecialchars($user['display_name'] ?: $user['username']) ?></strong>
                            <?php if (!empty($user['group_name'])): ?>
                                <span class="user-group-badge"><?= htmlspecialchars($user['group_name']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email'] ?: '-') ?></td>
                        <td>
                            <?php if ($user['role'] === 'superadmin'): ?>
                                <span class="user-role role-superadmin">👑 SuperAdmin</span>
                            <?php elseif ($user['role'] === 'admin'): ?>
                                <span class="user-role role-admin">🔧 Admin</span>
                            <?php else: ?>
                                <span class="user-role role-user">👤 User</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['active']): ?>
                                <span class="status-badge status-active">✅ Actief</span>
                            <?php else: ?>
                                <span class="status-badge status-inactive">❌ Inactief</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['can_show_presentation'] && $user['presentation_id']): ?>
                                <span title="Presentatie: <?= htmlspecialchars($user['presentation_id']) ?>">🎬 <?= $user['presentation_idle_seconds'] ?>s</span>
                            <?php else: ?>
                                <span style="color: #cbd5e0;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['can_use_scanner']): ?>
                                <span class="status-badge" style="background: #10b981; color: white;">📷 Aan</span>
                            <?php else: ?>
                                <span class="status-badge" style="background: #cbd5e0; color: #64748b;">📷 Uit</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon btn-edit" onclick="toggleEditRow(<?= $user['id'] ?>)" title="Bewerken">✏️</button>
                                <?php if ($user['id'] != $currentUserId): ?>
                                <button class="btn-icon btn-delete" onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')" title="Verwijderen">🗑️</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr class="edit-row" id="edit-row-<?= $user['id'] ?>">
                        <td colspan="7">
                            <div class="edit-content" id="edit-content-<?= $user['id'] ?>">
                                <!-- Content loaded via AJAX -->
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- CREATE USER MODAL -->
    <div class="modal" id="create-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>➕ Nieuwe Gebruiker Aanmaken</h3>
            </div>
            
            <form id="create-user-form">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" required>
                </div>
                
                <div class="form-group">
                    <label>Wachtwoord *</label>
                    <input type="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label>Display Naam</label>
                    <input type="text" name="display_name">
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email">
                </div>
                
                <div class="form-group">
                    <label>Rol</label>
                    <select name="role">
                        <option value="user">👤 User</option>
                        <option value="admin">🔧 Admin</option>
                        <?php if ($isSuperAdmin): ?>
                        <option value="superadmin">👑 SuperAdmin</option>
                        <?php endif; ?>
                    </select>
                </div>
            </form>
            
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeCreateModal()">Annuleren</button>
                <button class="btn btn-success" onclick="createUser()">✓ Aanmaken</button>
            </div>
        </div>
    </div>

    <!-- GROUP MANAGEMENT MODAL -->
    <div class="modal" id="group-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>👥 Groepen Beheren</h3>
            </div>
            <div class="modal-body">
                <form id="group-form" onsubmit="saveGroup(); return false;">
                    <input type="hidden" name="group_id" id="group-id" value="">
                    <div class="form-group">
                        <label>Naam</label>
                        <input type="text" name="name" id="group-name" required>
                    </div>
                    <div class="form-group">
                        <label>Beschrijving</label>
                        <input type="text" name="description" id="group-description">
                    </div>
                    <div class="form-group" style="margin-top: 10px;">
                        <button type="submit" class="btn btn-success">💾 Opslaan</button>
                        <button type="button" class="btn btn-secondary" onclick="resetGroupForm()">✚ Nieuwe groep</button>
                    </div>
                </form>

                <div style="margin-top: 20px;">
                    <h4 style="margin-bottom: 10px;">Bestaande groepen</h4>
                    <div id="group-list"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeGroupModal()">Sluiten</button>
            </div>
        </div>
    </div>

    <script>
        // ═══════════════════════════════════════════════════════════════════
        // JAVASCRIPT
        // ═══════════════════════════════════════════════════════════════════
        
        // ⚠️ FIX: Force reload on back button (bfcache prevention)
        window.addEventListener('pageshow', function(event) {
            if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                console.log('🔄 Page loaded from cache - forcing reload');
                window.location.reload();
            }
        });
        
        const allUsers = <?= json_encode($users) ?>;
        const allGroups = <?= json_encode($groups) ?>;
        const isSuperAdmin = <?= json_encode($isSuperAdmin) ?>;

        let currentGroupFilter = '';
        
        let currentSort = { column: 'display_name', order: 'asc' };
        let openEditRow = null;
        
        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
        
        // ═══ FILTERING & SORTING ═══
        
        function applyFilters() {
            const searchTerm = document.getElementById('search-input').value.toLowerCase();
            const roleFilter = document.getElementById('filter-role').value;
            const activeOnly = document.getElementById('filter-active').checked;
            
            document.querySelectorAll('.user-row').forEach(row => {
                const userId = row.dataset.userId;
                const user = allUsers.find(u => u.id == userId);
                
                if (!user) return;
                
                // Search filter
                const searchMatch = !searchTerm || 
                    (user.display_name && user.display_name.toLowerCase().includes(searchTerm)) ||
                    (user.username && user.username.toLowerCase().includes(searchTerm)) ||
                    (user.email && user.email.toLowerCase().includes(searchTerm));
                
                // Role filter
                const roleMatch = !roleFilter || user.role === roleFilter;
                
                // Active filter
                const activeMatch = !activeOnly || user.active == 1;

                // Group filter
                const groupMatch = !currentGroupFilter || (user.group_id && user.group_id.toString() === currentGroupFilter);

                // Show/hide row
                const editRow = document.getElementById(`edit-row-${userId}`);
                if (searchMatch && roleMatch && activeMatch && groupMatch) {
                    row.style.display = '';
                    if (editRow && editRow.classList.contains('active')) {
                        editRow.style.display = '';
                    }
                } else {
                    row.style.display = 'none';
                    if (editRow) {
                        editRow.style.display = 'none';
                    }
                }
            });
        }
        
        function sortTable(column) {
            // Toggle sort order
            if (currentSort.column === column) {
                currentSort.order = currentSort.order === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.column = column;
                currentSort.order = 'asc';
            }
            
            // Update header classes
            document.querySelectorAll('.users-table th').forEach(th => {
                th.classList.remove('sorted-asc', 'sorted-desc');
            });
            
            const header = document.querySelector(`th[data-sort="${column}"]`);
            if (header) {
                header.classList.add(`sorted-${currentSort.order}`);
            }
            
            // Sort rows
            const tbody = document.getElementById('users-table-body');
            const rows = Array.from(tbody.querySelectorAll('.user-row, .edit-row'));
            
            // Group pairs (user-row + edit-row)
            const pairs = [];
            for (let i = 0; i < rows.length; i += 2) {
                pairs.push([rows[i], rows[i + 1]]);
            }
            
            pairs.sort((a, b) => {
                const userA = allUsers.find(u => u.id == a[0].dataset.userId);
                const userB = allUsers.find(u => u.id == b[0].dataset.userId);
                
                let valA = userA[column];
                let valB = userB[column];
                
                // Handle different types
                if (column === 'active' || column === 'can_show_presentation') {
                    valA = parseInt(valA) || 0;
                    valB = parseInt(valB) || 0;
                } else if (typeof valA === 'string') {
                    valA = valA.toLowerCase();
                    valB = (valB || '').toLowerCase();
                }
                
                if (valA < valB) return currentSort.order === 'asc' ? -1 : 1;
                if (valA > valB) return currentSort.order === 'asc' ? 1 : -1;
                return 0;
            });
            
            // Re-append sorted pairs
            pairs.forEach(pair => {
                tbody.appendChild(pair[0]);
                tbody.appendChild(pair[1]);
            });
        }
        
        // ═══ EDIT ROW ═══
        
        async function toggleEditRow(userId) {
            const editRow = document.getElementById(`edit-row-${userId}`);
            const editContent = document.getElementById(`edit-content-${userId}`);
            
            // Close other open rows
            if (openEditRow && openEditRow !== userId) {
                document.getElementById(`edit-row-${openEditRow}`).classList.remove('active');
            }
            
            // Toggle current row
            if (editRow.classList.contains('active')) {
                editRow.classList.remove('active');
                openEditRow = null;
            } else {
                // Load user data
                editContent.innerHTML = '<p style="text-align:center;padding:20px;">⏳ Laden...</p>';
                editRow.classList.add('active');
                openEditRow = userId;
                
                try {
                    // FORCE NO CACHE - add timestamp
                    const timestamp = Date.now();
                    const response = await fetch(`?action=get_user&user_id=${userId}&_=${timestamp}`, {
                        method: 'GET',
                        headers: {
                            'Cache-Control': 'no-cache, no-store, must-revalidate',
                            'Pragma': 'no-cache',
                            'Expires': '0'
                        }
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        renderEditForm(userId, data.user, data.features, data.allLocations, data.allAfdelingen, data.afdelingen);
                    } else {
                        editContent.innerHTML = `<p style="color:red;">❌ Fout: ${data.error}</p>`;
                    }
                } catch (error) {
                    editContent.innerHTML = `<p style="color:red;">❌ Fout bij laden: ${error.message}</p>`;
                }
            }
        }
        
        function renderEditForm(userId, user, features, allLocations, allAfdelingen, afdelingen) {
            const editContent = document.getElementById(`edit-content-${userId}`);
            
            const visibleFields = features.visibleFields || [];
            const extraButtons = features.extraButtons || {};
            const locations = features.locations || [];
            
            const allFields = ['Foto', 'Voornaam', 'Achternaam', 'Functie', 'Afdeling', 'Locatie', 'Status', 'BHV', 'Tijdstip'];
            
            const idleMinutes = Math.round((user.presentation_idle_seconds || 120) / 60 * 10) / 10;
            
            editContent.innerHTML = `
                <form id="edit-form-${userId}" onsubmit="saveUser(${userId}); return false;">
                    <div class="edit-header">
                        <h3>✏️ ${user.display_name || user.username}</h3>
                        <div class="edit-actions">
                            <button type="button" class="btn btn-secondary" onclick="toggleEditRow(${userId})">❌ Annuleren</button>
                            <button type="submit" class="btn btn-success">💾 Opslaan</button>
                        </div>
                    </div>
                    
                    <!-- TAB NAVIGATION -->
                    <div class="tabs-container">
                        <div class="tabs-header">
                            <button type="button" class="tab-button active" onclick="switchTab(event, 'account-${userId}')">
                                📋 Account
                            </button>
                            <button type="button" class="tab-button" onclick="switchTab(event, 'features-${userId}')">
                                🎨 Features
                            </button>
                            <button type="button" class="tab-button" onclick="switchTab(event, 'presentation-${userId}')">
                                🎬 Presentatie
                            </button>
                            <button type="button" class="tab-button" onclick="switchTab(event, 'scanner-${userId}')">
                                📷 Scanner
                            </button>
                        </div>
                        
                        <!-- TAB 1: ACCOUNT INFO -->
                        <div id="account-${userId}" class="tab-content active">
                            <h4>📋 Account Informatie</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Display Naam</label>
                                <input type="text" name="display_name" value="${user.display_name || ''}" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" value="${user.email || ''}">
                            </div>
                            
                            <div class="form-group">
                                <label>Rol</label>
                                <select name="role">
                                    <option value="user" ${user.role === 'user' ? 'selected' : ''}>👤 User</option>
                                    <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>🔧 Admin</option>
                                    ${isSuperAdmin ? `<option value="superadmin" ${user.role === 'superadmin' ? 'selected' : ''}>👑 SuperAdmin</option>` : ''}
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Groep</label>
                                <select name="group_id">
                                    <option value="">— Geen groep —</option>
                                    ${allGroups.map(group => `
                                        <option value="${group.id}" ${user.group_id == group.id ? 'selected' : ''}>${group.name}</option>
                                    `).join('')}
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Nieuw Wachtwoord (optioneel)</label>
                                <input type="password" name="new_password" placeholder="Leeg laten om niet te wijzigen">
                            </div>
                        </div>
                        
                        <div style="margin-top: 15px;">
                            <div class="checkbox-item">
                                <input type="checkbox" id="active-${userId}" name="active" ${user.active ? 'checked' : ''}>
                                <label for="active-${userId}">✓ Account actief</label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- TAB 2: FEATURES -->
                    <div id="features-${userId}" class="tab-content">
                        <h4>🎨 Features & Rechten</h4>
                        
                        <div style="margin-bottom: 20px;">
                            <strong style="display: block; margin-bottom: 10px;">Zichtbare Velden:</strong>
                            <div class="checkbox-grid">
                                ${allFields.map(field => `
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="field-${userId}-${field}" name="visible_fields[]" value="${field}" ${visibleFields.includes(field) ? 'checked' : ''}>
                                        <label for="field-${userId}-${field}">${field}</label>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <strong style="display: block; margin-bottom: 10px;">Extra Knoppen:</strong>
                            <div class="checkbox-grid">
                                <div class="checkbox-item">
                                    <input type="checkbox" id="btn-pauze-${userId}" onchange="document.getElementById('hidden-btn-pauze-${userId}').value = this.checked ? '1' : '0'" ${extraButtons.PAUZE ? 'checked' : ''}>
                                    <input type="hidden" id="hidden-btn-pauze-${userId}" name="btn_pauze" value="${extraButtons.PAUZE ? '1' : '0'}">
                                    <label for="btn-pauze-${userId}">🌸 PAUZE</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="btn-thuiswerken-${userId}" onchange="document.getElementById('hidden-btn-thuiswerken-${userId}').value = this.checked ? '1' : '0'" ${extraButtons.THUISWERKEN ? 'checked' : ''}>
                                    <input type="hidden" id="hidden-btn-thuiswerken-${userId}" name="btn_thuiswerken" value="${extraButtons.THUISWERKEN ? '1' : '0'}">
                                    <label for="btn-thuiswerken-${userId}">💜 THUISWERKEN</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="btn-vakantie-${userId}" onchange="document.getElementById('hidden-btn-vakantie-${userId}').value = this.checked ? '1' : '0'" ${extraButtons.VAKANTIE ? 'checked' : ''}>
                                    <input type="hidden" id="hidden-btn-vakantie-${userId}" name="btn_vakantie" value="${extraButtons.VAKANTIE ? '1' : '0'}">
                                    <label for="btn-vakantie-${userId}">🌿 VAKANTIE</label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- ✨ SORTEER TOGGLE FEATURE (NIEUW) -->
                        <div style="margin-bottom: 20px;">
                            <strong style="display: block; margin-bottom: 10px;">⚙️ Extra Functies:</strong>
                            <div style="background: #f7fafc; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                <div class="checkbox-item" style="align-items: flex-start;">
                                    <input type="checkbox" id="can-toggle-sort-${userId}" name="can_toggle_sort" value="1" ${features.sorteerFunctie ? 'checked' : ''}>
                                    <label for="can-toggle-sort-${userId}" style="flex: 1;">
                                        <strong style="display: block; margin-bottom: 4px;">🔄 Sorteer Toggle Knop</strong>
                                        <span style="font-size: 12px; color: #718096; line-height: 1.4;">
                                            User kan zelf switchen tussen voornaam/achternaam sortering via header knop
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <strong style="display: block; margin-bottom: 10px;">Zichtbare Locaties:</strong>
                            <div class="checkbox-grid">
                                ${allLocations.map(loc => `
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="loc-${userId}-${loc.replace(/\s+/g, '-')}" name="locations[]" value="${loc}" ${locations.includes(loc) ? 'checked' : ''}>
                                        <label for="loc-${userId}-${loc.replace(/\s+/g, '-')}">${loc}</label>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <strong style="display: block; margin-bottom: 10px;">Zichtbare Afdelingen:</strong>
                            <div class="checkbox-grid">
                                ${allAfdelingen.map(afd => `
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="afd-${userId}-${afd.id}" name="afdelingen[]" value="${afd.id}" ${afdelingen.includes(afd.id) ? 'checked' : ''}>
                                        <label for="afd-${userId}-${afd.id}">${afd.name}</label>
                                    </div>
                                `).join('')}
                            </div>
                            <p style="font-size: 12px; color: #718096; margin-top: 8px;">
                                💡 Als geen afdelingen geselecteerd: alle afdelingen worden getoond
                            </p>
                        </div>
                    </div>
                    
                    <!-- TAB 3: PRESENTATION -->
                    <div id="presentation-${userId}" class="tab-content">
                        <h4>🎬 Presentatie Instellingen</h4>
                        
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label>Presentatie ID</label>
                            <input type="text" name="presentation_id" value="${user.presentation_id || ''}" placeholder="Bijv: 1zmun1n-2QltJxLJSUs1yclo5wppg-l8yBtTTVkq15KY" style="font-family: monospace; font-size: 13px;">
                            <div class="info-text">
                                💡 De ID van de Google Slides presentatie (uit de URL: docs.google.com/presentation/d/<strong>ID</strong>/edit)
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label>Idle tijd voordat presentatie start</label>
                            <div class="idle-time-input">
                                <input type="number" name="presentation_idle_seconds" value="${user.presentation_idle_seconds || 120}" min="10" max="600" id="idle-input-${userId}" oninput="updateIdlePreview(${userId})">
                                <span class="idle-time-label">seconden</span>
                                <span class="idle-time-preview">(≈ <span id="idle-minutes-${userId}">${idleMinutes}</span> min)</span>
                            </div>
                            <div class="info-text">
                                💡 Presentatie start automatisch na deze tijd van inactiviteit<br>
                                🎯 Aanbevolen: 60-180 seconden (1-3 minuten)
                            </div>
                        </div>
                        
                        <div class="checkbox-item">
                            <input type="checkbox" id="can-show-${userId}" name="can_show_presentation" ${user.can_show_presentation ? 'checked' : ''}>
                            <label for="can-show-${userId}">✓ Presentatie auto-show inschakelen</label>
                        </div>
                    </div>
                    
                    <!-- TAB 4: SCANNER -->
                    <div id="scanner-${userId}" class="tab-content">
                        <h4>📷 QR/Barcode Scanner</h4>
                        <div class="checkbox-item">
                            <input type="checkbox" id="can-scan-${userId}" name="can_use_scanner" ${user.can_use_scanner ? 'checked' : ''}>
                            <label for="can-scan-${userId}">✓ QR/Barcode scanner toegang</label>
                        </div>
                        <div class="info-text">
                            💡 Gebruiker kan QR-codes en barcodes scannen voor snelle check-in/out<br>
                            📷 Vereist camera of externe scanner
                        </div>
                    </div>
                    
                    </div> <!-- END tabs-container -->
                </form>
            `;
        }
        
        // Tab switching
        function switchTab(event, tabId) {
            const container = event.target.closest('.tabs-container');
            
            // Hide all tabs in this container
            container.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Deactivate all tab buttons
            container.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Activate clicked tab
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }
        
        function updateIdlePreview(userId) {
            const input = document.getElementById(`idle-input-${userId}`);
            const preview = document.getElementById(`idle-minutes-${userId}`);
            const minutes = Math.round(input.value / 60 * 10) / 10;
            preview.textContent = minutes;
        }
        
        async function saveUser(userId) {
            console.log('🔧 Saving user:', userId);
            
            const form = document.getElementById(`edit-form-${userId}`);
            const formData = new FormData(form);
            formData.append('action', 'save_user');
            formData.append('user_id', userId);
            
            // DEBUG: Log ALL form data
            console.log('📝 Form data being sent:');
            const formDataObj = {};
            for (let [key, value] of formData.entries()) {
                console.log(`  ${key}:`, value);
                formDataObj[key] = value;
            }
            
            // Log to see if checkboxes are included
            console.log('🔍 Button checkboxes:');
            console.log('  btn_pauze:', formData.get('btn_pauze'));
            console.log('  btn_thuiswerken:', formData.get('btn_thuiswerken'));
            console.log('  btn_vakantie:', formData.get('btn_vakantie'));
            console.log('  presentation_idle_seconds:', formData.get('presentation_idle_seconds'));
            
            try {
                console.log('📤 Sending request...');
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                console.log('📥 Response status:', response.status);
                const responseText = await response.text();
                console.log('📄 Raw response:', responseText);
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    console.error('❌ Failed to parse JSON:', e);
                    console.log('Response was:', responseText);
                    showToast('Server response was not JSON: ' + responseText.substring(0, 100), 'error');
                    return;
                }
                
                console.log('📊 Response data:', data);
                
                if (data.success) {
                    showToast(data.message || 'Opgeslagen!', 'success');
                    
                    // Show debug info if available
                    if (data.debug) {
                        console.log('🐛 Server debug info:', data.debug);
                    }
                    
                    // Reload page to reflect changes
                    setTimeout(() => {
                        console.log('🔄 Reloading page...');
                        window.location.reload();
                    }, 1000);
                } else {
                    console.error('❌ Save failed:', data.error);
                    showToast(data.error || 'Opslaan mislukt', 'error');
                }
            } catch (error) {
                console.error('❌ Exception:', error);
                showToast('Fout bij opslaan: ' + error.message, 'error');
            }
        }
        
        // ═══ CREATE USER ═══
        
        function openCreateModal() {
            document.getElementById('create-modal').classList.add('active');
        }
        
        function closeCreateModal() {
            document.getElementById('create-modal').classList.remove('active');
            document.getElementById('create-user-form').reset();
        }
        
        async function createUser() {
            const form = document.getElementById('create-user-form');
            const formData = new FormData(form);
            formData.append('action', 'create_user');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    closeCreateModal();
                    
                    // Reload page
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(data.error, 'error');
                }
            } catch (error) {
                showToast('Fout bij aanmaken: ' + error.message, 'error');
            }
        }

        function openGroupModal() {
            document.getElementById('group-modal').classList.add('active');
            resetGroupForm();
            renderGroupList();
        }

        function closeGroupModal() {
            document.getElementById('group-modal').classList.remove('active');
        }

        function resetGroupForm() {
            document.getElementById('group-id').value = '';
            document.getElementById('group-name').value = '';
            document.getElementById('group-description').value = '';
        }

        function renderGroupList() {
            const container = document.getElementById('group-list');
            container.innerHTML = '';

            if (!allGroups || allGroups.length === 0) {
                container.innerHTML = '<p style="color: #718096;">Geen groepen gevonden.</p>';
                return;
            }

            const list = document.createElement('div');
            list.style.display = 'grid';
            list.style.gridTemplateColumns = '1fr 1fr 160px';
            list.style.gap = '10px';
            list.style.alignItems = 'center';

            allGroups.forEach(group => {
                const name = document.createElement('div');
                name.textContent = group.name;
                name.style.fontWeight = '600';

                const desc = document.createElement('div');
                desc.textContent = group.description || '—';
                desc.style.color = '#4a5568';
                desc.style.fontSize = '13px';

                const actions = document.createElement('div');
                actions.style.display = 'flex';
                actions.style.gap = '8px';

                const editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'btn btn-secondary';
                editBtn.textContent = 'Bewerken';
                editBtn.onclick = () => {
                    document.getElementById('group-id').value = group.id;
                    document.getElementById('group-name').value = group.name;
                    document.getElementById('group-description').value = group.description || '';
                };

                const deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'btn btn-danger';
                deleteBtn.textContent = 'Verwijderen';
                deleteBtn.onclick = () => deleteGroup(group.id, group.name);

                actions.appendChild(editBtn);
                actions.appendChild(deleteBtn);

                list.appendChild(name);
                list.appendChild(desc);
                list.appendChild(actions);
            });

            container.appendChild(list);
        }

        async function saveGroup() {
            const groupId = document.getElementById('group-id').value;
            const name = document.getElementById('group-name').value.trim();
            const description = document.getElementById('group-description').value.trim();

            if (!name) {
                showToast('Groepsnaam is verplicht', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', groupId ? 'update_group' : 'create_group');
            if (groupId) formData.append('group_id', groupId);
            formData.append('name', name);
            formData.append('description', description);

            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.error, 'error');
                }
            } catch (error) {
                showToast('Fout bij opslaan: ' + error.message, 'error');
            }
        }

        async function deleteGroup(groupId, groupName) {
            if (!confirm(`Weet je zeker dat je groep '${groupName}' wilt verwijderen?`)) {
                return;
            }

            try {
                const response = await fetch(`?action=delete_group&group_id=${groupId}`);
                const data = await response.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.error, 'error');
                }
            } catch (error) {
                showToast('Fout bij verwijderen: ' + error.message, 'error');
            }
        }
        
        // ═══ DELETE USER ═══
        
        async function deleteUser(userId, username) {
            if (!confirm(`Weet je zeker dat je gebruiker '${username}' wilt verwijderen?\n\nDit kan niet ongedaan gemaakt worden!`)) {
                return;
            }
            
            try {
                const response = await fetch(`?action=delete_user&user_id=${userId}`);
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    
                    // Remove row from table
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(data.error, 'error');
                }
            } catch (error) {
                showToast('Fout bij verwijderen: ' + error.message, 'error');
            }
        }
        
        // ═══ EVENT LISTENERS ═══
        
        function setGroupFilter(groupId) {
            currentGroupFilter = groupId;
            document.querySelectorAll('.group-filter-button').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.groupId === groupId);
            });
            applyFilters();
        }

        document.getElementById('search-input').addEventListener('input', applyFilters);
        document.getElementById('filter-role').addEventListener('change', applyFilters);
        document.getElementById('filter-active').addEventListener('change', applyFilters);

        document.querySelectorAll('.group-filter-button').forEach(btn => {
            btn.addEventListener('click', () => setGroupFilter(btn.dataset.groupId));
        });
        
        document.querySelectorAll('.users-table th.sortable').forEach(th => {
            th.addEventListener('click', () => {
                sortTable(th.dataset.sort);
            });
        });
        
        // Close modal on background click
        document.getElementById('create-modal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                closeCreateModal();
            }
        });
        document.getElementById('group-modal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                closeGroupModal();
            }
        });
        
        // Initial sort
        sortTable('display_name');
    </script>
</body>
</html>
