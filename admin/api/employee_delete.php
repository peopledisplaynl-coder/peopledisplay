<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

// Check admin rechten
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','superadmin'])) {
    echo json_encode(['success' => false, 'error' => 'Geen toegang']);
    exit;
}

try {
    // Lees POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    // Validatie
    $id = $data['id'] ?? $data['ID'] ?? null;
    
    if (empty($id)) {
        throw new Exception('ID is verplicht');
    }
    
    // Haal script URL uit database
    $stmt = $db->query("SELECT scriptURL FROM config WHERE id = 1");
    $config = $stmt->fetch();
    
    if (!$config || empty($config['scriptURL'])) {
        throw new Exception('Script URL niet gevonden in configuratie');
    }
    
    $scriptURL = $config['scriptURL'];
    
    // Bouw POST request naar Google Apps Script
    // 🔧 Send as JSON in POST body (Google Apps Script will parse via e.postData.contents)
    $postData = json_encode(['id' => $id]);
    
    $ch = curl_init($scriptURL . '?action=deleteemployee');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=UTF-8'
    ]);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // 🔧 FIX: Force HTTP/1.1 to prevent PROTOCOL_ERROR with Google Apps Script
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    if ($response === false) {
        curl_close($ch);
        throw new Exception('cURL error: ' . $curlError);
    }
    
    curl_close($ch);
    
    // Debug logging
    error_log("=== EMPLOYEE DELETE DEBUG ===");
    error_log("HTTP Code: " . $httpCode);
    error_log("Request Data: " . $postData);
    error_log("Response: " . $response);
    error_log("============================");
    
    if ($httpCode !== 200) {
        throw new Exception("HTTP error: " . $httpCode . " - Response: " . substr($response, 0, 200));
    }
    
    $result = json_decode($response, true);
    
    if (!$result) {
        throw new Exception('Invalid response from Google Script');
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
