<?php
/**
 * AJAX: Create Admin Account
 */

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

if (empty($_SESSION['db_setup_complete'])) {
    echo json_encode(['success' => false, 'error' => 'Database setup not complete']);
    exit;
}

$username    = trim($_POST['admin_username'] ?? '');
$displayName = trim($_POST['admin_display_name'] ?? '');
$email       = trim($_POST['admin_email'] ?? '');
$password    = $_POST['admin_password'] ?? '';
$passwordConfirm = $_POST['admin_password_confirm'] ?? '';

if (empty($username) || empty($displayName) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Vul alle verplichte velden in']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_]{3,}$/', $username)) {
    echo json_encode(['success' => false, 'error' => 'Gebruikersnaam minimaal 3 karakters, alleen letters, cijfers en underscore']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Ongeldig e-mailadres']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'error' => 'Wachtwoord minimaal 8 karakters']);
    exit;
}

if ($password !== $passwordConfirm) {
    echo json_encode(['success' => false, 'error' => 'Wachtwoorden komen niet overeen']);
    exit;
}

try {
    $rootDir = dirname(__DIR__, 2);
    require_once $rootDir . '/includes/db.php';

    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    if (!$stmt->fetch()) {
        throw new Exception('Users tabel niet gevonden. Voltooi eerst de database installatie.');
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Gebruikersnaam bestaat al']);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // INSERT met alle huidige kolommen
    $stmt = $db->prepare("
        INSERT INTO users (username, password_hash, role, display_name, email, active, created_at)
        VALUES (?, ?, 'superadmin', ?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE
            password_hash = VALUES(password_hash),
            display_name = VALUES(display_name),
            email = VALUES(email)
    ");

    $stmt->execute([$username, $passwordHash, $displayName, $email]);

    $_SESSION['admin_username'] = $username;
    $_SESSION['admin_created']  = true;

    echo json_encode(['success' => true, 'message' => 'Admin account aangemaakt!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Fout: ' . $e->getMessage()]);
}
