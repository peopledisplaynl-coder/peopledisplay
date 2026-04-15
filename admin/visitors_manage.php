<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * PRODUCTION VERSION - Zonder employees JOIN
 * Gebruikt alleen visitors.contactpersoon_naam veld
 */

ob_start();
require_once __DIR__ . '/auth_helper.php';
requireAdmin();
requireAdminFeature('manage_visitors');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/license_check.php';
requireFeature('visitor_management');

$message = '';
$messageType = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_status') {
        try {
            $id = (int)$_POST['id'];
            $status = $_POST['status'] ?? '';
            $validStatuses = ['AANGEMELD', 'BINNEN', 'VERTROKKEN', 'GEANNULEERD'];
            if (in_array($status, $validStatuses)) {
                $extraFields = $status === 'BINNEN' ? ', checked_in_at = NOW()' : ($status === 'VERTROKKEN' ? ', checked_out_at = NOW()' : '');
                $stmt = $db->prepare("UPDATE visitors SET status = ? $extraFields WHERE id = ?");
                $stmt->execute([$status, $id]);
                $message = 'Status bijgewerkt';
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $message = 'Fout: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get data - NO JOIN, just visitors table
$filterDatum = $_GET['datum'] ?? date('Y-m-d');
$filterStatus = $_GET['status'] ?? '';
$filterLocatie = $_GET['locatie'] ?? '';

$query = "SELECT v.* FROM visitors v WHERE 1=1";
$params = [];

if ($filterDatum) {
    $query .= " AND v.bezoek_datum = ?";
    $params[] = $filterDatum;
}
if ($filterStatus) {
    $query .= " AND v.status = ?";
    $params[] = $filterStatus;
}
if ($filterLocatie) {
    $query .= " AND v.locatie = ?";
    $params[] = $filterLocatie;
}

$query .= " ORDER BY v.bezoek_datum DESC, v.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get locations
$locStmt = $db->query("SELECT DISTINCT locatie FROM visitors WHERE locatie IS NOT NULL AND locatie != '' ORDER BY locatie");
$filterLocaties = $locStmt->fetchAll(PDO::FETCH_COLUMN);

// Calculate stats
$stats = ['AANGEMELD' => 0, 'BINNEN' => 0, 'VERTROKKEN' => 0];
foreach ($visitors as $v) {
    if (isset($stats[$v['status']])) {
        $stats[$v['status']]++;
    }
}

$current_display_name = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bezoekers Beheren - PeopleDisplay</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; 
            background: #f5f7fa; 
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 24px; font-weight: 600; }
        .header-right { display: flex; gap: 15px; align-items: center; }
        .btn-logout {
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
        }
        .btn-logout:hover { background: rgba(255,255,255,0.3); }
        
        .container { 
            max-width: 1400px; 
            margin: 30px auto; 
            padding: 0 20px; 
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .message.success { 
            background: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb; 
        }
        .message.error { 
            background: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb; 
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-card h3 { 
            font-size: 14px; 
            color: #718096; 
            margin-bottom: 8px; 
        }
        .stat-card .number { 
            font-size: 32px; 
            font-weight: 700; 
            color: #2d3748; 
        }
        .stat-card.aangemeld .number { color: #ed8936; }
        .stat-card.binnen .number { color: #48bb78; }
        .stat-card.vertrokken .number { color: #4299e1; }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .filters label {
            display: inline-block;
            margin-right: 15px;
            font-weight: 500;
            font-size: 14px;
        }
        .filters input,
        .filters select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            margin-left: 5px;
        }
        .filters button {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            margin-left: 10px;
        }
        .filters button[type="submit"] {
            background: #667eea;
            color: white;
        }
        .filters button[type="button"] {
            background: #ed8936;
            color: white;
        }
        
        table {
            width: 100%;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-collapse: collapse;
        }
        th, td { 
            padding: 12px 15px; 
            text-align: left; 
        }
        th {
            background: #f7fafc;
            font-weight: 600;
            color: #2d3748;
            border-bottom: 2px solid #e2e8f0;
            font-size: 13px;
            text-transform: uppercase;
        }
        tbody tr { border-bottom: 1px solid #e2e8f0; }
        tbody tr:hover { background: #f7fafc; }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-aangemeld { background: #fed7aa; color: #7c2d12; }
        .badge-binnen { background: #c6f6d5; color: #22543d; }
        .badge-vertrokken { background: #bee3f8; color: #2c5282; }
        .badge-geannuleerd { background: #fed7d7; color: #742a2a; }
        .badge-online { background: #e9d8fd; color: #553c9a; }
        .badge-fysiek { background: #e6fffa; color: #234e52; }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }
        .btn-success { background: #48bb78; color: white; }
        .btn-success:hover { background: #38a169; }
        .btn-info { background: #4299e1; color: white; }
        .btn-info:hover { background: #3182ce; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .empty-state h3 { color: #666; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>👥 Bezoekers Beheren</h1>
        <div class="header-right">
            <span>Ingelogd als: <strong><?php echo htmlspecialchars($current_display_name); ?></strong></span>
            <a href="dashboard.php" class="btn-logout">← Dashboard</a>
            <a href="logout.php" class="btn-logout">Uitloggen</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="stats">
            <div class="stat-card aangemeld">
                <h3>Aangemeld</h3>
                <div class="number"><?php echo $stats['AANGEMELD']; ?></div>
            </div>
            <div class="stat-card binnen">
                <h3>Binnen</h3>
                <div class="number"><?php echo $stats['BINNEN']; ?></div>
            </div>
            <div class="stat-card vertrokken">
                <h3>Vertrokken</h3>
                <div class="number"><?php echo $stats['VERTROKKEN']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Totaal Vandaag</h3>
                <div class="number"><?php echo count($visitors); ?></div>
            </div>
        </div>
        
        <!-- Filters -->
        <form class="filters" method="get">
            <label>📅 Datum: <input type="date" name="datum" value="<?php echo htmlspecialchars($filterDatum); ?>"></label>
            <label>📊 Status: 
                <select name="status">
                    <option value="">Alle</option>
                    <option value="AANGEMELD" <?php echo $filterStatus === 'AANGEMELD' ? 'selected' : ''; ?>>Aangemeld</option>
                    <option value="BINNEN" <?php echo $filterStatus === 'BINNEN' ? 'selected' : ''; ?>>Binnen</option>
                    <option value="VERTROKKEN" <?php echo $filterStatus === 'VERTROKKEN' ? 'selected' : ''; ?>>Vertrokken</option>
                </select>
            </label>
            <label>📍 Locatie:
                <select name="locatie">
                    <option value="">Alle</option>
                    <?php foreach ($filterLocaties as $loc): ?>
                        <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $filterLocatie === $loc ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Filter</button>
            <button type="button" onclick="window.location.href='visitors_manage.php'">Reset</button>
        </form>
        
        <!-- Table -->
        <?php if (empty($visitors)): ?>
            <div class="empty-state">
                <h3>Geen bezoekers gevonden</h3>
                <p>Voor de geselecteerde filters</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Naam</th>
                        <th>Bedrijf</th>
                        <th>Datum & Tijd</th>
                        <th>Locatie</th>
                        <th>Contactpersoon</th>
                        <th>Status</th>
                        <th>Type</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visitors as $v): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($v['naam']); ?></strong>
                                <?php if (!empty($v['functie'])): ?>
                                    <br><small style="color: #718096;"><?php echo htmlspecialchars($v['functie']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($v['bedrijf'] ?? ''); ?></td>
                            <td>
                                <?php echo date('d-m-Y', strtotime($v['bezoek_datum'])); ?>
                                <?php if (!empty($v['bezoek_tijd'])): ?>
                                    <br><small><?php echo substr($v['bezoek_tijd'], 0, 5); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($v['locatie'] ?? ''); ?></td>
                            <td>
                                <small><?php echo htmlspecialchars($v['contactpersoon_naam'] ?? '-'); ?></small>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($v['status']); ?>">
                                    <?php echo htmlspecialchars($v['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($v['registratie_type']); ?>">
                                    <?php echo htmlspecialchars($v['registratie_type']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($v['status'] === 'AANGEMELD'): ?>
                                    <button onclick="updateStatus(<?php echo $v['id']; ?>, 'BINNEN')" class="btn btn-success" title="Check In">✓</button>
                                <?php elseif ($v['status'] === 'BINNEN'): ?>
                                    <button onclick="updateStatus(<?php echo $v['id']; ?>, 'VERTROKKEN')" class="btn btn-info" title="Check Out">→</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <script>
        function updateStatus(id, status) {
            if (confirm('Status wijzigen naar: ' + status + '?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="update_status"><input type="hidden" name="id" value="' + id + '"><input type="hidden" name="status" value="' + status + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
