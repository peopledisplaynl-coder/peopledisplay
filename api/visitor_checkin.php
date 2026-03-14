<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: visitor_checkin.php
 * LOCATIE:      api/
 * UPLOAD NAAR:  /api/visitor_checkin.php
 * ═══════════════════════════════════════════════════════════════════
 * 
 * Visitor Check-in API
 * Handles visitor check-in from aanmeldscherm
 * Updates status: AANGEMELD → BINNEN
 * 
 * ═══════════════════════════════════════════════════════════════════
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
require_once __DIR__ . '/../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Niet ingelogd'
    ]);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Alleen POST toegestaan'
    ]);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['visitor_id'])) {
        throw new Exception('Visitor ID ontbreekt');
    }
    
    $visitor_id = (int)$input['visitor_id'];
    $action = $input['action'] ?? 'checkin';
    
    // Validate action
    if ($action === 'checkin') {
        // Check in: AANGEMELD → BINNEN
        $stmt = $db->prepare("
            UPDATE visitors 
            SET status = 'BINNEN',
                checked_in_at = NOW()
            WHERE id = ?
              AND status = 'AANGEMELD'
        ");
        
        $stmt->execute([$visitor_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Bezoeker niet gevonden of al ingecheckt');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Bezoeker succesvol ingecheckt',
            'visitor_id' => $visitor_id,
            'status' => 'BINNEN',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } elseif ($action === 'checkout') {
        // Check out: BINNEN → VERTROKKEN
        $stmt = $db->prepare("
            UPDATE visitors 
            SET status = 'VERTROKKEN',
                checked_out_at = NOW()
            WHERE id = ?
              AND status = 'BINNEN'
        ");
        
        $stmt->execute([$visitor_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Bezoeker niet gevonden of al uitgecheckt');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Bezoeker succesvol uitgecheckt',
            'visitor_id' => $visitor_id,
            'status' => 'VERTROKKEN',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } else {
        throw new Exception('Ongeldige actie');
    }
    
} catch (Exception $e) {
    error_log('visitor_checkin.php error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
