<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * API: Visitor Check-Out
 * Updates visitor status from BINNEN to VERTROKKEN
 */

session_start();

// Try multiple possible paths to db.php
$db_paths = [
    __DIR__ . '/../../includes/db.php',
    __DIR__ . '/../includes/db.php',
    dirname(dirname(__DIR__)) . '/includes/db.php'
];

$db_loaded = false;
foreach ($db_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_loaded = true;
        break;
    }
}

if (!$db_loaded) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'error' => 'Database configuration file not found',
        'tried_paths' => $db_paths
    ]);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Check if $db exists
    if (!isset($db)) {
        throw new Exception('Database connection not available');
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['visitor_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing visitor_id']);
        exit;
    }
    
    $visitorId = (int)$input['visitor_id'];
    
    // Update visitor status to VERTROKKEN
    $stmt = $db->prepare("
        UPDATE visitors 
        SET 
            status = 'VERTROKKEN',
            checked_out_at = NOW()
        WHERE id = ? 
        AND status = 'BINNEN'
    ");
    
    $stmt->execute([$visitorId]);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Visitor not found or already checked out'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Visitor checked out successfully',
        'visitor_id' => $visitorId,
        'status' => 'VERTROKKEN',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
