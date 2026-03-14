<?php
/**
 * API: Change Password
 * Location: /admin/api/change_password.php
 */

require_once __DIR__ . '/../../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit;
}

$userId = $_SESSION['user_id'];

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ongeldige data']);
    exit;
}

// Validate input
$currentPassword = $data['current_password'] ?? '';
$newPassword = $data['new_password'] ?? '';

if (empty($currentPassword) || empty($newPassword)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Alle velden zijn verplicht']);
    exit;
}

if (strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Nieuw wachtwoord moet minimaal 8 karakters zijn']);
    exit;
}

// Verify current password
try {
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Gebruiker niet gevonden']);
        exit;
    }
    
    // Check current password
    if (!password_verify($currentPassword, $user['password_hash'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Huidig wachtwoord is onjuist']);
        exit;
    }
    
    // Hash new password
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update database
    $stmt = $db->prepare("
        UPDATE users 
        SET 
            password_hash = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([$newHash, $userId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Wachtwoord succesvol gewijzigd'
    ]);
    
} catch (PDOException $e) {
    error_log('Password change error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database fout bij wijzigen wachtwoord']);
}
