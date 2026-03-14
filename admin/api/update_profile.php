<?php
/**
 * API: Update Profile Information
 * Location: /admin/api/update_profile.php
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

// Validate display_name
$displayName = trim($data['display_name'] ?? '');
if (empty($displayName)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Weergavenaam mag niet leeg zijn']);
    exit;
}

if (strlen($displayName) > 150) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Weergavenaam te lang (max 150 karakters)']);
    exit;
}

// Validate email (optional)
$email = trim($data['email'] ?? '');
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ongeldig e-mailadres']);
    exit;
}

// Update database
try {
    $stmt = $db->prepare("
        UPDATE users 
        SET 
            display_name = ?,
            email = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $displayName,
        $email ?: null,
        $userId
    ]);
    
    // Update session
    $_SESSION['display_name'] = $displayName;
    if (!empty($email)) {
        $_SESSION['email'] = $email;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Profiel succesvol bijgewerkt',
        'display_name' => $displayName,
        'email' => $email
    ]);
    
} catch (PDOException $e) {
    error_log('Profile update error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database fout bij opslaan']);
}
