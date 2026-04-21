<?php
/**
 * AJAX: Test Database Connection
 * Location: /install/ajax/test-database.php
 */

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$host = trim($_POST['db_host'] ?? '');
$name = trim($_POST['db_name'] ?? '');
$user = trim($_POST['db_user'] ?? '');
$pass = $_POST['db_pass'] ?? '';
$prefix = trim($_POST['db_prefix'] ?? '');

// Validate inputs
if (empty($host) || empty($name) || empty($user)) {
    echo json_encode(['success' => false, 'error' => 'Please fill in all required fields']);
    exit;
}

// Test connection
try {
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    // Test query
    $stmt = $pdo->query('SELECT DATABASE() as db, VERSION() as version');
    $result = $stmt->fetch();
    
    // Save to session for next step
    $_SESSION['db_config'] = [
        'host' => $host,
        'name' => $name,
        'user' => $user,
        'pass' => $pass,
        'prefix' => $prefix
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Connection successful!',
        'details' => [
            'database' => $result['db'],
            'version' => $result['version']
        ]
    ]);
    
} catch (PDOException $e) {
    // Don't expose detailed error to user
    $errorMsg = 'Could not connect to database';
    
    // Add hint based on error code
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        $errorMsg .= ': Invalid username or password';
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        $errorMsg .= ': Database does not exist';
    } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
        $errorMsg .= ': Cannot reach database server';
    }
    
    echo json_encode([
        'success' => false,
        'error' => $errorMsg
    ]);
}
