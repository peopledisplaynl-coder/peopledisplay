<?php
/**
 * inuitbord-api.php
 * Upload naar: /admin/api/inuitbord-api.php
 *
 * API voor IN/UIT bord weergave
 * GET  ?locaties=[1,2,3]  → JSON array met medewerkers
 * POST action=updatestatus → status updaten
 *
 * Toegankelijk voor alle ingelogde gebruikers (niet alleen admins)
 */

session_start();
require_once __DIR__ . '/../../includes/db.php';

// Sessie check — geen requireAdmin, gewone users mogen ook
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// ─── GET: medewerkers ophalen ────────────────────────────────────────
if ($method === 'GET') {

    // Locatienamen van de ingelogde gebruiker (uit users.features JSON)
    $userId = (int)$_SESSION['user_id'];
    $locatieNamenVoorFilter = [];

    // Haal features JSON op uit users tabel
    $stmtUser = $db->prepare("SELECT features FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute([$userId]);
    $userRow  = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $userFeat = json_decode($userRow['features'] ?? '{}', true);

    // Locaties zijn strings (location_name) opgeslagen in features.locations
    $locatieNamenVoorFilter = $userFeat['locations'] ?? [];

    try {
        if (!empty($locatieNamenVoorFilter)) {
            // Employees filteren op locatienaam (locatie = TEXT veld, geen FK!)
            $namePlaceholders = implode(',', array_fill(0, count($locatieNamenVoorFilter), '?'));
            $stmt = $db->prepare(
                "SELECT employee_id, naam, status, bhv, locatie, afdeling
                 FROM employees
                 WHERE actief = 1
                   AND locatie IN ($namePlaceholders)
                 ORDER BY naam ASC"
            );
            $stmt->execute(array_values($locatieNamenVoorFilter));
        } else {
            // Geen locatiefilter → alle actieve medewerkers
            $stmt = $db->prepare(
                "SELECT employee_id, naam, status, bhv, locatie, afdeling
                 FROM employees
                 WHERE actief = 1
                 ORDER BY naam ASC"
            );
            $stmt->execute();
        }

        $medewerkers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normaliseer status: alles wat niet 'IN' is wordt als 'OUT' beschouwd in het bord
        // (PAUZE, THUISWERKEN, VAKANTIE worden getoond in UIT-kolom)
        foreach ($medewerkers as &$m) {
            // status blijft ongewijzigd — JS bepaalt weergave (alles != IN → UIT-kolom)
            $m['bhv'] = $m['bhv']; // 'Ja' of 'Nee'
        }
        unset($m);

        echo json_encode($medewerkers);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database fout: ' . $e->getMessage()]);
    }
    exit;
}

// ─── POST: status updaten ────────────────────────────────────────────
if ($method === 'POST') {

    $action     = $_POST['action']      ?? '';
    $employeeId = trim($_POST['employee_id'] ?? '');
    $nieuweStatus = strtoupper(trim($_POST['status'] ?? ''));

    if ($action !== 'updatestatus') {
        http_response_code(400);
        echo json_encode(['error' => 'Onbekende actie']);
        exit;
    }

    // Validatie
    $toegestaneStatussen = ['IN', 'OUT', 'PAUZE', 'THUISWERKEN', 'VAKANTIE'];
    if (empty($employeeId) || !in_array($nieuweStatus, $toegestaneStatussen, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ongeldige invoer']);
        exit;
    }

    try {
        // Controleer of medewerker bestaat en actief is
        $stmt = $db->prepare(
            "SELECT id FROM employees WHERE employee_id = ? AND actief = 1 LIMIT 1"
        );
        $stmt->execute([$employeeId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Medewerker niet gevonden']);
            exit;
        }

        // Status updaten
        $stmt = $db->prepare(
            "UPDATE employees SET status = ?, tijdstip = NOW() WHERE employee_id = ? AND actief = 1"
        );
        $stmt->execute([$nieuweStatus, $employeeId]);

        if ($stmt->rowCount() === 0) {
            http_response_code(500);
            echo json_encode(['error' => 'Update mislukt']);
            exit;
        }

        // Audit log (zelfde patroon als employees_api.php)
        try {
            $stmtAudit = $db->prepare(
                "INSERT INTO employee_audit
                    (employee_id, action, field_changed, old_value, new_value, changed_by, ip_address)
                 VALUES (?, 'STATUS_CHANGE', 'status', NULL, ?, ?, ?)"
            );
            $stmtAudit->execute([
                $employeeId,
                $nieuweStatus,
                (int)$_SESSION['user_id'],
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
        } catch (PDOException $e) {
            // Audit log fout is niet kritiek — hoofd-update is al geslaagd
            error_log('Audit log fout inuitbord: ' . $e->getMessage());
        }

        echo json_encode([
            'success'    => true,
            'employee_id' => $employeeId,
            'status'     => $nieuweStatus,
            'tijdstip'   => date('H:i:s')
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database fout: ' . $e->getMessage()]);
    }
    exit;
}

// Onbekende method
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
