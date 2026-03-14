<?php
/**
 * ============================================================
 * FORCE LOGOUT USER API - COMPLETE FIX
 * ============================================================
 * Forceert een gebruiker uit te loggen via 2 methoden:
 * 1. Verwijdert session bestanden fysiek (instant effect)
 * 2. Zet database is_active op 0 (logout checker vangt op)
 * 
 * METHOD: POST
 * INPUT: user_id (int)
 * OUTPUT: JSON success/error
 * 
 * LOCATIE: /admin/api/force_logout_user.php
 * ============================================================
 */

// Start sessie en check admin rechten
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../auth_helper.php';

// Moet admin zijn
requireAdmin();

// Headers
header('Content-Type: application/json');

// Alleen POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Only POST requests allowed'
    ]);
    exit;
}

try {
    // Haal user_id op
    $input = json_decode(file_get_contents('php://input'), true);
    $target_user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;
    
    if ($target_user_id <= 0) {
        throw new Exception('Invalid user ID');
    }
    
    // Voorkom dat admin zichzelf uitlogt
    if ($target_user_id === $_SESSION['user_id']) {
        throw new Exception('Cannot force logout yourself');
    }
    
    // Check of deze user bestaat
    $stmt = $db->prepare("SELECT id, username, display_name FROM users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$target_user) {
        throw new Exception('User not found');
    }
    
    // Haal alle actieve sessies op van deze user
    $stmt = $db->prepare("
        SELECT session_id 
        FROM user_sessions 
        WHERE user_id = ? 
        AND is_active = 1
    ");
    $stmt->execute([$target_user_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $sessions_destroyed = 0;
    $session_path = '';
    
    // METHODE 1: Probeer session bestanden fysiek te verwijderen
    if (!empty($sessions)) {
        // Mogelijke session paths (afhankelijk van hosting)
        $possible_paths = [
            '/tmp/sessions',
            '/var/lib/php/sessions',
            sys_get_temp_dir(),
            ini_get('session.save_path')
        ];
        
        // Probeer elk path
        foreach ($possible_paths as $path) {
            if (empty($path) || !is_dir($path)) continue;
            
            $session_path = $path;
            
            // Probeer elk session bestand te verwijderen
            foreach ($sessions as $session_id) {
                $session_file = $path . '/sess_' . $session_id;
                
                if (file_exists($session_file)) {
                    if (@unlink($session_file)) {
                        $sessions_destroyed++;
                        error_log("Deleted session file: $session_file");
                    }
                }
            }
            
            // Als we sessies hebben vernietigd, stop
            if ($sessions_destroyed > 0) {
                break;
            }
        }
    }
    
    // METHODE 2: Zet alle sessies op inactive in database (ALTIJD doen)
    $stmt = $db->prepare("
        UPDATE user_sessions 
        SET is_active = 0
        WHERE user_id = ? 
        AND is_active = 1
    ");
    $stmt->execute([$target_user_id]);
    $db_sessions_updated = $stmt->rowCount();
    
    // Log de actie in employee_audit
    try {
        $stmt = $db->prepare("
            INSERT INTO employee_audit 
            (employee_id, action, field_changed, old_value, new_value, changed_by, ip_address, user_agent, changed_at)
            VALUES (?, 'FORCE_LOGOUT', 'session_status', 'ACTIVE', 'INACTIVE', ?, ?, ?, NOW())
        ");
        $stmt->execute([
            'USER_' . $target_user_id,
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        // Audit log failure niet blocking
        error_log("Audit log failed: " . $e->getMessage());
    }
    
    // Logging
    error_log(sprintf(
        "Force logout: Admin %d logged out user %d (%s) - %d sessions in DB, %d session files deleted",
        $_SESSION['user_id'],
        $target_user_id,
        $target_user['username'],
        $db_sessions_updated,
        $sessions_destroyed
    ));
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'User successfully logged out',
        'user' => [
            'id' => $target_user['id'],
            'username' => $target_user['username'],
            'display_name' => $target_user['display_name']
        ],
        'sessions_found' => count($sessions),
        'sessions_destroyed' => $sessions_destroyed,
        'db_sessions_updated' => $db_sessions_updated,
        'session_path' => $session_path,
        'method_1_success' => ($sessions_destroyed > 0),
        'method_2_success' => ($db_sessions_updated > 0),
        'info' => [
            'method_1' => 'Physical session files deleted (instant effect)',
            'method_2' => 'Database sessions deactivated (logout checker will catch)',
            'result' => ($sessions_destroyed > 0 ? 'User logged out immediately' : 'User will be logged out on next page load')
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
