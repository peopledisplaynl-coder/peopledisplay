<?php
/**
 * ============================================================
 * EMPLOYEE AUDIT LOG - SIMPLE VERSION (NO JOIN)
 * ============================================================
 * Locatie: /admin/audit_log.php
 * Versie: 2.1 - Zonder JOIN (fallback)
 * ============================================================
 */

session_start();
require_once __DIR__ . '/../includes/db.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check admin role
$stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!in_array($user['role'], ['admin', 'superadmin'])) {
    die('Access denied.');
}

// Get filters
$action_filter = $_GET['action'] ?? 'all';
$period_filter = $_GET['period'] ?? '7d';
$employee_filter = $_GET['employee'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Build WHERE
$where_clauses = [];
$params = [];

if ($action_filter !== 'all') {
    $where_clauses[] = "action = ?";
    $params[] = $action_filter;
}

if (!empty($employee_filter)) {
    // Search in both employee_id AND employee names (via subquery)
    $where_clauses[] = "(employee_id LIKE ? OR employee_id IN (
        SELECT employee_id FROM employees 
        WHERE naam LIKE ? OR voornaam LIKE ? OR achternaam LIKE ?
    ))";
    $params[] = "%{$employee_filter}%";
    $params[] = "%{$employee_filter}%";
    $params[] = "%{$employee_filter}%";
    $params[] = "%{$employee_filter}%";
}

if ($period_filter === 'custom' && !empty($date_from) && !empty($date_to)) {
    $where_clauses[] = "created_at >= ? AND created_at <= ?";
    $params[] = $date_from . ' 00:00:00';
    $params[] = $date_to . ' 23:59:59';
} elseif ($period_filter !== 'all') {
    $intervals = ['24h' => '24 HOUR', '7d' => '7 DAY', '30d' => '30 DAY'];
    if (isset($intervals[$period_filter])) {
        $where_clauses[] = "created_at >= DATE_SUB(NOW(), INTERVAL {$intervals[$period_filter]})";
    }
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get count
$count_sql = "SELECT COUNT(*) FROM employee_audit $where_sql";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Get records
$sql = "SELECT * FROM employee_audit $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$stmt = $db->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get action counts
$action_counts = ['INSERT' => 0, 'UPDATE' => 0, 'DELETE' => 0, 'STATUS_CHANGE' => 0];
$stmt = $db->query("SELECT action, COUNT(*) as count FROM employee_audit GROUP BY action");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $action_counts[$row['action']] = $row['count'];
}

// Helper function to get employee name from cache
$employee_cache = [];
function getEmployeeName($employee_id, $db, &$cache) {
    if (empty($employee_id)) return '-';
    
    if (!isset($cache[$employee_id])) {
        $stmt = $db->prepare("SELECT naam, voornaam, achternaam FROM employees WHERE employee_id = ? LIMIT 1");
        $stmt->execute([$employee_id]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($emp) {
            $cache[$employee_id] = $emp['naam'] ?: ($emp['voornaam'] . ' ' . $emp['achternaam']);
        } else {
            $cache[$employee_id] = $employee_id; // Fallback to ID
        }
    }
    
    return $cache[$employee_id];
}

// Helper to get user name
function getUserName($user_id, $db) {
    if (empty($user_id)) return 'Systeem';
    
    static $user_cache = [];
    
    if (!isset($user_cache[$user_id])) {
        $stmt = $db->prepare("SELECT display_name, username FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_cache[$user_id] = $user ? ($user['display_name'] ?: $user['username']) : 'Onbekend';
    }
    
    return $user_cache[$user_id];
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📋 Employee Audit Log</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f7fa; color: #2d3748; }
        .header { background: white; padding: 20px 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .header h1 { font-size: 28px; color: #2c3e50; margin-bottom: 5px; }
        .header .subtitle { color: #718096; font-size: 14px; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 30px 30px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #667eea; text-decoration: none; font-weight: 600; }
        .stats-bar { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card .label { color: #718096; font-size: 13px; margin-bottom: 5px; }
        .stat-card .value { font-size: 28px; font-weight: bold; color: #2c3e50; }
        .filters-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .filters-card h2 { font-size: 18px; margin-bottom: 15px; color: #2c3e50; }
        .filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 13px; font-weight: 600; color: #4a5568; }
        select, input { padding: 8px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px; }
        select:focus, input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #718096; color: white; }
        .audit-table { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f7fafc; }
        th { padding: 12px; text-align: left; font-size: 12px; font-weight: 700; color: #4a5568; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        tr:hover { background: #f7fafc; }
        .action-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .action-INSERT { background: #c6f6d5; color: #22543d; }
        .action-UPDATE { background: #bee3f8; color: #2c5282; }
        .action-DELETE { background: #fed7d7; color: #742a2a; }
        .action-STATUS_CHANGE { background: #feebc8; color: #7c2d12; }
        .timestamp { color: #718096; font-size: 13px; }
        .employee-id { font-family: 'Courier New', monospace; background: #f7fafc; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
        .pagination { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px; padding: 15px; background: white; border-radius: 8px; }
        .pagination-btn { padding: 6px 12px; border: 2px solid #e2e8f0; background: white; border-radius: 6px; text-decoration: none; color: inherit; font-weight: 600; }
        .pagination-btn.disabled { opacity: 0.5; pointer-events: none; }
        .no-records { text-align: center; padding: 40px; color: #718096; }
    </style>
</head>
<body>

<div class="header">
    <h1>📋 Employee Audit Log</h1>
    <p class="subtitle">Overzicht van alle employee wijzigingen</p>
</div>

<div class="container">
    <a href="dashboard.php" class="back-link">← Dashboard</a>
    
    <div class="stats-bar">
        <div class="stat-card">
            <div class="label">Totaal</div>
            <div class="value"><?= number_format($total_records) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Toegevoegd</div>
            <div class="value" style="color: #38a169;"><?= number_format($action_counts['INSERT']) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Gewijzigd</div>
            <div class="value" style="color: #3182ce;"><?= number_format($action_counts['UPDATE']) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Status</div>
            <div class="value" style="color: #d69e2e;"><?= number_format($action_counts['STATUS_CHANGE']) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Verwijderd</div>
            <div class="value" style="color: #e53e3e;"><?= number_format($action_counts['DELETE']) ?></div>
        </div>
    </div>
    
    <div class="filters-card">
        <h2>🔍 Filters</h2>
        <form method="GET">
            <div class="filters-grid">
                <div class="filter-group">
                    <label>Actie</label>
                    <select name="action">
                        <option value="all" <?= $action_filter === 'all' ? 'selected' : '' ?>>Alle</option>
                        <option value="INSERT" <?= $action_filter === 'INSERT' ? 'selected' : '' ?>>Toegevoegd</option>
                        <option value="UPDATE" <?= $action_filter === 'UPDATE' ? 'selected' : '' ?>>Gewijzigd</option>
                        <option value="STATUS_CHANGE" <?= $action_filter === 'STATUS_CHANGE' ? 'selected' : '' ?>>Status</option>
                        <option value="DELETE" <?= $action_filter === 'DELETE' ? 'selected' : '' ?>>Verwijderd</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Periode</label>
                    <select name="period" id="period" onchange="toggleCustom()">
                        <option value="24h" <?= $period_filter === '24h' ? 'selected' : '' ?>>24 uur</option>
                        <option value="7d" <?= $period_filter === '7d' ? 'selected' : '' ?>>7 dagen</option>
                        <option value="30d" <?= $period_filter === '30d' ? 'selected' : '' ?>>30 dagen</option>
                        <option value="all" <?= $period_filter === 'all' ? 'selected' : '' ?>>Alles</option>
                        <option value="custom" <?= $period_filter === 'custom' ? 'selected' : '' ?>>Aangepast</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Zoeken</label>
                    <input type="text" name="employee" placeholder="Naam of Employee ID..." value="<?= htmlspecialchars($employee_filter) ?>">
                </div>
                <div class="filter-group" id="date-from" style="<?= $period_filter === 'custom' ? '' : 'display:none' ?>">
                    <label>Van</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="filter-group" id="date-to" style="<?= $period_filter === 'custom' ? '' : 'display:none' ?>">
                    <label>Tot</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">🔍 Toepassen</button>
            <a href="audit_log.php" class="btn btn-secondary">Reset</a>
        </form>
    </div>
    
    <?php if (count($records) > 0): ?>
        <div class="audit-table">
            <table>
                <thead>
                    <tr>
                        <th>Tijdstip</th>
                        <th>Employee ID</th>
                        <th>Naam</th>
                        <th>Actie</th>
                        <th>Veld</th>
                        <th>Oud → Nieuw</th>
                        <th>Door</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td class="timestamp"><?= date('Y-m-d H:i:s', strtotime($r['created_at'])) ?></td>
                            <td><span class="employee-id"><?= htmlspecialchars($r['employee_id']) ?></span></td>
                            <td><?= htmlspecialchars(getEmployeeName($r['employee_id'], $db, $employee_cache)) ?></td>
                            <td><span class="action-badge action-<?= $r['action'] ?>"><?= $r['action'] ?></span></td>
                            <td><?= htmlspecialchars($r['field_changed'] ?? '-') ?></td>
                            <td style="font-size: 13px;">
                                <span style="color: #e53e3e;"><?= htmlspecialchars($r['old_value'] ?? '-') ?></span> 
                                → 
                                <span style="color: #38a169;"><?= htmlspecialchars($r['new_value'] ?? '-') ?></span>
                            </td>
                            <td><?= htmlspecialchars(getUserName($r['changed_by'], $db)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])) ?>" 
                   class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>">← Vorige</a>
                <span>Pagina <?= $page ?> / <?= $total_pages ?> (<?= number_format($total_records) ?>)</span>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])) ?>" 
                   class="pagination-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">Volgende →</a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="audit-table">
            <div class="no-records">
                <div style="font-size: 48px; margin-bottom: 10px;">📭</div>
                <h3>Geen Records</h3>
                <p>Geen audit records gevonden met de huidige filters.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleCustom() {
    const val = document.getElementById('period').value;
    document.getElementById('date-from').style.display = val === 'custom' ? 'block' : 'none';
    document.getElementById('date-to').style.display = val === 'custom' ? 'block' : 'none';
}
</script>

</body>
</html>
