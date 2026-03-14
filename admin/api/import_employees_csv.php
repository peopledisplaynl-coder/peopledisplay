<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: import_employees_csv.php
 * LOCATIE:      /admin/api/import_employees_csv.php
 * BESCHRIJVING: Import employees from CSV
 * ═══════════════════════════════════════════════════════════════════
 */

session_start();
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

// Check admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? 'user', ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Check if file was uploaded
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Geen bestand geüpload of upload fout');
    }
    
    $file = $_FILES['csv_file'];
    
    // Validate file type
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        throw new Exception('Alleen CSV bestanden toegestaan');
    }
    
    // Read CSV
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        throw new Exception('Kan bestand niet lezen');
    }
    
    // Read header
    $header = fgetcsv($handle);
    if (!$header) {
        throw new Exception('Leeg bestand of geen header');
    }
    
    // Validate required columns
    $requiredCols = ['voornaam', 'achternaam'];
    $headerLower = array_map('strtolower', $header);
    
    foreach ($requiredCols as $col) {
        if (!in_array($col, $headerLower)) {
            throw new Exception("Verplichte kolom ontbreekt: $col");
        }
    }
    
    // Map header to indices
    $colMap = array_flip($headerLower);
    
    // Parse rows
    $imported = 0;
    $skipped = 0;
    $errors = [];
    $rowNum = 1;
    
    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;
        
        // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }
        
        try {
            // Extract data
            $voornaam = trim($row[$colMap['voornaam']] ?? '');
            $achternaam = trim($row[$colMap['achternaam']] ?? '');
            
            if (empty($voornaam) || empty($achternaam)) {
                $skipped++;
                $errors[] = "Rij $rowNum: Voornaam en achternaam verplicht";
                continue;
            }
            
            $naam = $voornaam . ' ' . $achternaam;
            $functie = trim($row[$colMap['functie'] ?? -1] ?? '');
            $afdeling = trim($row[$colMap['afdeling'] ?? -1] ?? '');
            $locatie = trim($row[$colMap['locatie'] ?? -1] ?? '');
            $bhv = trim($row[$colMap['bhv'] ?? -1] ?? 'Nee');
            $foto_url = trim($row[$colMap['foto_url'] ?? -1] ?? '');
            $notities = trim($row[$colMap['notities'] ?? -1] ?? '');
            $status = strtoupper(trim($row[$colMap['status'] ?? -1] ?? 'OUT'));
            
            // Validate BHV
            if (!in_array($bhv, ['Ja', 'Nee'])) {
                $bhv = 'Nee';
            }
            
            // Validate status
            $validStatuses = ['IN', 'OUT', 'PAUZE', 'THUISWERKEN', 'VAKANTIE'];
            if (!in_array($status, $validStatuses)) {
                $status = 'OUT';
            }
            
            // Check if employee already exists (by naam)
            $checkStmt = $db->prepare("SELECT id FROM employees WHERE naam = ? AND actief = 1");
            $checkStmt->execute([$naam]);
            
            if ($checkStmt->fetch()) {
                // Update existing
                $stmt = $db->prepare("
                    UPDATE employees 
                    SET voornaam = ?, achternaam = ?, functie = ?, afdeling = ?, 
                        locatie = ?, bhv = ?, foto_url = ?, notities = ?, status = ?
                    WHERE naam = ? AND actief = 1
                ");
                
                $stmt->execute([
                    $voornaam, $achternaam, $functie, $afdeling, 
                    $locatie, $bhv, $foto_url, $notities, $status,
                    $naam
                ]);
                
                $imported++;
            } else {
                // Insert new
                $employee_id = 'EMP' . time() . rand(100, 999);
                
                $stmt = $db->prepare("
                    INSERT INTO employees 
                    (employee_id, naam, voornaam, achternaam, functie, afdeling, locatie, bhv, foto_url, notities, status, actief)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                
                $stmt->execute([
                    $employee_id, $naam, $voornaam, $achternaam, $functie, 
                    $afdeling, $locatie, $bhv, $foto_url, $notities, $status
                ]);
                
                $imported++;
            }
            
            // Small delay to ensure unique employee_id
            usleep(1000);
            
        } catch (Exception $e) {
            $skipped++;
            $errors[] = "Rij $rowNum: " . $e->getMessage();
        }
    }
    
    fclose($handle);
    
    // Build response
    $response = [
        'success' => true,
        'imported' => $imported,
        'skipped' => $skipped,
        'total_rows' => $rowNum - 1,
        'message' => "✅ Import voltooid: $imported toegevoegd/bijgewerkt, $skipped overgeslagen"
    ];
    
    if (!empty($errors) && count($errors) <= 10) {
        $response['errors'] = $errors;
    } elseif (count($errors) > 10) {
        $response['errors'] = array_slice($errors, 0, 10);
        $response['errors'][] = "... en " . (count($errors) - 10) . " meer fouten";
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
