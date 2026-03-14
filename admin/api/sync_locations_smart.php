<?php
/**
 * Smart Sync voor Locaties
 * 
 * Deze sync:
 * 1. Haalt nieuwe locaties uit Google Sheet
 * 2. Update config.locations
 * 3. BEHOUDT user vinkjes en settings
 * 4. Voegt alleen NIEUWE locaties toe aan users
 */

session_start();

header('Content-Type: application/json');

// Check admin rechten
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','superadmin'], true)) {
    echo json_encode(['success' => false, 'error' => 'Geen toegang']);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

try {
    if (!isset($db) || !($db instanceof PDO)) {
        throw new RuntimeException('Database connectie niet beschikbaar');
    }
    
    // Stap 1: Haal config op
    $stmt = $db->query("SELECT scriptURL, locations FROM config WHERE id = 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (empty($config['scriptURL'])) {
        echo json_encode(['success' => false, 'error' => 'Geen scriptURL geconfigureerd']);
        exit;
    }
    
    $oldLocations = json_decode($config['locations'] ?? '[]', true) ?: [];
    
    // Stap 2: Haal data uit Google Sheet via proxy
    $proxyURL = 'http://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['REQUEST_URI'])) . '/api/proxy.php?action=getemployees';
    
    $ch = curl_init($proxyURL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        echo json_encode(['success' => false, 'error' => 'Kon geen data ophalen uit Google Sheet']);
        exit;
    }
    
    $data = json_decode($response, true);
    
    // DEBUG: Check of data geldig is
    if (!is_array($data) || empty($data)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Ongeldige data van Google Sheet',
            'debug' => [
                'http_code' => $httpCode,
                'response_preview' => substr($response, 0, 500),
                'json_error' => json_last_error_msg()
            ]
        ]);
        exit;
    }
    
    // Stap 3: Extraheer unieke locaties
    // Data kan direct een array zijn, of een wrapper met 'data' key hebben
    $employees = isset($data['data']) ? $data['data'] : $data;
    
    $newLocations = [];
    foreach ($employees as $employee) {
        if (is_array($employee)) {
            // Check beide varianten: Locatie (hoofdletter) en locatie (kleine letter)
            $locatie = $employee['Locatie'] ?? $employee['locatie'] ?? null;
            if (!empty($locatie)) {
                $newLocations[] = $locatie;
            }
        }
    }
    $newLocations = array_values(array_unique($newLocations));
    
    if (empty($newLocations)) {
        echo json_encode(['success' => false, 'error' => 'Geen locaties gevonden in Google Sheet']);
        exit;
    }
    
    // Stap 4: Update config.locations
    $stmt = $db->prepare("UPDATE config SET locations = :locations WHERE id = 1");
    $stmt->execute(['locations' => json_encode($newLocations)]);
    
    // Stap 5: Bepaal nieuwe locaties (die nog niet in oude lijst stonden)
    $addedLocations = array_diff($newLocations, $oldLocations);
    $removedLocations = array_diff($oldLocations, $newLocations);
    
    // Stap 6: Update alle users - SMART MODE
    $stmt = $db->query("SELECT id, locations FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updatedUsers = 0;
    
    foreach ($users as $user) {
        $userLocations = json_decode($user['locations'] ?? '[]', true) ?: [];
        
        // BELANGRIJK: Behoud bestaande vinkjes
        $existingChecked = array_intersect($userLocations, $newLocations);
        
        // Voeg ALLEEN nieuwe locaties toe (unchecked by default)
        $updatedLocations = array_merge($existingChecked, $addedLocations);
        $updatedLocations = array_values(array_unique($updatedLocations));
        
        // Update user
        $stmt = $db->prepare("UPDATE users SET locations = :locations WHERE id = :id");
        $stmt->execute([
            'locations' => json_encode($updatedLocations),
            'id' => $user['id']
        ]);
        
        $updatedUsers++;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Locaties gesynchroniseerd',
        'stats' => [
            'total_locations' => count($newLocations),
            'added' => count($addedLocations),
            'removed' => count($removedLocations),
            'users_updated' => $updatedUsers
        ],
        'locations' => $newLocations,
        'added_locations' => array_values($addedLocations),
        'removed_locations' => array_values($removedLocations)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server fout: ' . $e->getMessage()
    ]);
}
