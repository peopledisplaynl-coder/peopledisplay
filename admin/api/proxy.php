<?php
declare(strict_types=1);

/**
 * Proxy voor Google Apps Script API
 * Haalt scriptURL uit database config (GEEN hardcoded fallbacks!)
 */

require_once __DIR__ . '/../../includes/db.php';

// Haal config uit database
$upstream = null;
$sheetID = null;

try {
    $stmt = $db->query("SELECT scriptURL, sheetID FROM config WHERE id = 1 LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($config) {
        $upstream = $config['scriptURL'] ?? null;
        $sheetID = $config['sheetID'] ?? null;
    }
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'DATABASE_ERROR',
        'message' => 'Kon configuratie niet ophalen uit database.',
        'details' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    exit;
}

// GEEN fallback! Fail duidelijk als config ontbreekt
if (!$upstream) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'NO_SCRIPT_URL_CONFIGURED',
        'message' => 'Google Sheets script URL is niet geconfigureerd. Ga naar Admin Dashboard → Config Beheer om de URL in te stellen.',
        'hint' => 'Step 1: Open Google Sheet → Extensions → Apps Script → Deploy → Copy Web App URL'
    ], JSON_PRETTY_PRINT);
    error_log('proxy.php: CRITICAL - No scriptURL in database config!');
    exit;
}

// Bouw remote URL
$qs = $_SERVER['QUERY_STRING'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$remoteUrl = $upstream . ($qs ? '?' . $qs : '');

// Default action = getemployees (was 'list')
if (strpos($remoteUrl, 'action=') === false) {
    $remoteUrl .= (strpos($remoteUrl, '?') === false ? '?' : '&') . 'action=getemployees';
}

// Voeg sheetID toe als die er is
if ($sheetID && strpos($remoteUrl, 'sheetID=') === false) {
    $remoteUrl .= '&sheetID=' . urlencode($sheetID);
}

// cURL setup
$ch = curl_init($remoteUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

// Forward method and body
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

if (in_array($method, ['POST','PUT','PATCH'], true)) {
    $body = file_get_contents('php://input');
    if ($body !== false && $body !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? 'application/json';
        $headers = ["Content-Type: {$contentType}", "User-Agent: PeopleDisplay/1.0"];
    } else {
        $headers = ["User-Agent: PeopleDisplay/1.0"];
    }
} else {
    $headers = ["User-Agent: PeopleDisplay/1.0"];
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Execute
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

// Error handling
if ($response === false || $httpCode >= 400) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'UPSTREAM_FAILED',
        'message' => 'Kon geen verbinding maken met Google Sheets',
        'details' => $curlErr ?: "HTTP $httpCode",
        'http_code' => $httpCode
    ], JSON_PRETTY_PRINT);
    exit;
}

// Output response
header('Content-Type: application/json; charset=utf-8');
echo $response;
exit;
