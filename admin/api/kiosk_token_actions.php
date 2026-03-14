<?php
/**
 * Kiosk Token Actions API
 * Handles CRUD operations for kiosk auto-login tokens
 * 
 * Actions: list, create, delete, update_status, get_users
 * Access: Admin/SuperAdmin only
 */

// Error reporting voor debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Database connectie
try {
    require_once __DIR__ . '/../../includes/db.php';
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database connectie fout: ' . $e->getMessage()
    ]);
    exit;
}

// Security: Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Niet ingelogd']);
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Geen toegang - alleen voor admins']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        
        case 'list':
            // Haal alle kiosk tokens op met user info
            try {
                $stmt = $db->prepare("
                    SELECT 
                        kt.id,
                        kt.token,
                        kt.description,
                        kt.allowed_ip,
                        kt.created_at,
                        kt.last_used,
                        kt.expires_at,
                        kt.is_active,
                        u.username,
                        u.display_name,
                        creator.display_name as created_by_name
                    FROM kiosk_tokens kt
                    INNER JOIN users u ON kt.user_id = u.id
                    LEFT JOIN users creator ON kt.created_by = creator.id
                    ORDER BY kt.created_at DESC
                ");
                $stmt->execute();
                $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'tokens' => $tokens
                ]);
            } catch (PDOException $e) {
                // Als tabel niet bestaat, return lege array
                echo json_encode([
                    'success' => true,
                    'tokens' => [],
                    'message' => 'Tabel bestaat nog niet of is leeg'
                ]);
            }
            break;
            
        case 'create':
            // Genereer nieuwe kiosk token
            $user_id = intval($_POST['user_id'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $allowed_ip = trim($_POST['allowed_ip'] ?? '');
            $expires_days = intval($_POST['expires_days'] ?? 0);
            
            if (!$user_id) {
                throw new Exception('Gebruiker is verplicht');
            }
            
            // Check if user exists
            $stmt = $db->prepare("SELECT id, username FROM users WHERE id = ? AND active = 1");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception('Gebruiker niet gevonden of niet actief');
            }
            
            // Genereer cryptografisch veilige token
            $token = bin2hex(random_bytes(32)); // 64 character hex string
            
            // Bereken expiry date
            $expires_at = null;
            if ($expires_days > 0) {
                $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_days} days"));
            }
            
            // Valideer IP (optioneel)
            if ($allowed_ip && !filter_var($allowed_ip, FILTER_VALIDATE_IP)) {
                throw new Exception('Ongeldig IP-adres formaat');
            }
            
            // Insert token
            $stmt = $db->prepare("
                INSERT INTO kiosk_tokens 
                (user_id, token, description, allowed_ip, expires_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $token,
                $description ?: null,
                $allowed_ip ?: null,
                $expires_at,
                $_SESSION['user_id']
            ]);
            
            $token_id = $db->lastInsertId();
            
            // Genereer volledige URL
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $base_path = dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))); // Ga 3 niveaus omhoog van /admin/api/filename.php
            $base_path = rtrim($base_path, '/'); // Verwijder trailing slash
            $kiosk_url = $protocol . '://' . $host . $base_path . '/kiosk_login.php?token=' . $token;
            
            echo json_encode([
                'success' => true,
                'message' => 'Kiosk token succesvol aangemaakt',
                'token_id' => $token_id,
                'token' => $token,
                'kiosk_url' => $kiosk_url,
                'username' => $user['username']
            ]);
            break;
            
        case 'delete':
            // Verwijder een kiosk token
            $token_id = intval($_POST['token_id'] ?? 0);
            
            if (!$token_id) {
                throw new Exception('Token ID is verplicht');
            }
            
            $stmt = $db->prepare("DELETE FROM kiosk_tokens WHERE id = ?");
            $stmt->execute([$token_id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Kiosk token succesvol verwijderd'
                ]);
            } else {
                throw new Exception('Token niet gevonden');
            }
            break;
            
        case 'toggle_status':
            // Toggle active status van een token
            $token_id = intval($_POST['token_id'] ?? 0);
            
            if (!$token_id) {
                throw new Exception('Token ID is verplicht');
            }
            
            $stmt = $db->prepare("
                UPDATE kiosk_tokens 
                SET is_active = NOT is_active 
                WHERE id = ?
            ");
            $stmt->execute([$token_id]);
            
            if ($stmt->rowCount() > 0) {
                // Haal nieuwe status op
                $stmt = $db->prepare("SELECT is_active FROM kiosk_tokens WHERE id = ?");
                $stmt->execute([$token_id]);
                $new_status = $stmt->fetchColumn();
                
                echo json_encode([
                    'success' => true,
                    'message' => $new_status ? 'Token geactiveerd' : 'Token gedeactiveerd',
                    'is_active' => (bool)$new_status
                ]);
            } else {
                throw new Exception('Token niet gevonden');
            }
            break;
            
        case 'update':
            // Update token IP adres en/of beschrijving
            $token_id = intval($_POST['token_id'] ?? 0);
            $allowed_ip = trim($_POST['allowed_ip'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (!$token_id) {
                throw new Exception('Token ID is verplicht');
            }
            
            // Valideer IP (optioneel, mag leeg zijn)
            if ($allowed_ip && !filter_var($allowed_ip, FILTER_VALIDATE_IP)) {
                throw new Exception('Ongeldig IP-adres formaat');
            }
            
            // Update token
            $stmt = $db->prepare("
                UPDATE kiosk_tokens 
                SET allowed_ip = ?, description = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $allowed_ip ?: null,
                $description ?: null,
                $token_id
            ]);
            
            if ($stmt->rowCount() > 0 || true) { // true = ook als niets veranderd
                echo json_encode([
                    'success' => true,
                    'message' => 'Token succesvol bijgewerkt'
                ]);
            } else {
                throw new Exception('Token niet gevonden');
            }
            break;
            
        case 'get_users':
            // Haal alle actieve users op voor dropdown
            try {
                $stmt = $db->prepare("
                    SELECT id, username, display_name, role
                    FROM users
                    WHERE active = 1
                    ORDER BY username
                ");
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'users' => $users,
                    'count' => count($users)
                ]);
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Fout bij ophalen gebruikers: ' . $e->getMessage(),
                    'users' => []
                ]);
            }
            break;
            
        default:
            throw new Exception('Onbekende actie: ' . $action);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database fout: ' . $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
