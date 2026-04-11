<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * ============================================================
 * PEOPLEDISPLAY - REMEMBER TOKENS BEHEER
 * ============================================================
 * Voor beheerders om actieve "Ingelogd blijven" tokens te bekijken
 * Versie: 2.1
 * ============================================================
 */

require_once __DIR__ . '/../includes/db.php';

// Alleen superadmins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: ' . BASE_PATH . '/login.php?error=geen_toegang');
    exit;
}

$message = '';
$messageType = '';

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'delete' && isset($_POST['token_id'])) {
            $tokenId = (int)$_POST['token_id'];
            $db->prepare("DELETE FROM remember_tokens WHERE id = ?")->execute([$tokenId]);
            $message = "✅ Token verwijderd";
            $messageType = 'success';
        }
        
        if ($action === 'delete_user_tokens' && isset($_POST['user_id'])) {
            $userId = (int)$_POST['user_id'];
            $stmt = $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
            $stmt->execute([$userId]);
            $count = $stmt->rowCount();
            $message = "✅ $count token(s) verwijderd voor deze gebruiker";
            $messageType = 'success';
        }
        
        if ($action === 'cleanup_expired') {
            $stmt = $db->exec("DELETE FROM remember_tokens WHERE expires_at < NOW()");
            $message = "✅ $stmt verlopen token(s) verwijderd";
            $messageType = 'success';
        }
        
        if ($action === 'delete_all') {
            $stmt = $db->exec("DELETE FROM remember_tokens");
            $message = "✅ Alle tokens verwijderd ($stmt stuks)";
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = "❌ Fout: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Haal alle tokens op
try {
    $stmt = $db->query("
        SELECT 
            rt.id,
            rt.user_id,
            rt.selector,
            rt.expires_at,
            rt.created_at,
            rt.last_used_at,
            rt.ip_address,
            rt.user_agent,
            u.username,
            u.display_name,
            u.role
        FROM remember_tokens rt
        JOIN users u ON rt.user_id = u.id
        ORDER BY rt.last_used_at DESC, rt.created_at DESC
    ");
    $tokens = $stmt->fetchAll();
    
    // Statistieken
    $totalTokens = count($tokens);
    $activeTokens = 0;
    $expiredTokens = 0;
    $now = time();
    
    foreach ($tokens as $token) {
        if (strtotime($token['expires_at']) > $now) {
            $activeTokens++;
        } else {
            $expiredTokens++;
        }
    }
} catch (Exception $e) {
    $tokens = [];
    $message = "❌ Kon tokens niet ophalen: " . $e->getMessage();
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remember Tokens Beheer</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/style.css">
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 30px auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid;
        }
        
        .stat-card.total { border-color: #0078d7; }
        .stat-card.active { border-color: #28a745; }
        .stat-card.expired { border-color: #dc3545; }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #6c757d;
            font-size: 14px;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #333;
        }
        
        .alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 6px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-primary { background: #0078d7; color: #fff; }
        .btn-danger { background: #dc3545; color: #fff; }
        .btn-warning { background: #ffc107; color: #000; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            font-size: 13px;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .expired-row {
            opacity: 0.6;
            background: #fff3cd !important;
        }
        
        .token-selector {
            font-family: monospace;
            font-size: 12px;
            color: #6c757d;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-active { background: #10b981; color: white; }
        .badge-expired { background: #ef4444; color: white; }
        .badge-role {
            background: #6b7280;
            color: white;
            text-transform: uppercase;
        }
        
        .toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
<div class="container">
    <a href="<?= BASE_PATH ?>/admin/dashboard.php" style="display:inline-block;margin-bottom:15px;color:#0078d7;text-decoration:none;font-weight:600">← Terug naar dashboard</a>
    
    <h1 style="margin-top:0;color:#333">
        🔐 Remember Tokens Beheer
    </h1>
    
    <p style="color:#6c757d;margin-bottom:30px;">
        Overzicht van alle actieve "Ingelogd blijven" tokens. Deze tokens stellen gebruikers in staat automatisch in te loggen zonder wachtwoord.
    </p>
    
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    
    <!-- Stats -->
    <div class="stats">
        <div class="stat-card total">
            <h3>📊 Totaal Tokens</h3>
            <div class="number"><?= $totalTokens ?></div>
        </div>
        <div class="stat-card active">
            <h3>✅ Actief</h3>
            <div class="number"><?= $activeTokens ?></div>
        </div>
        <div class="stat-card expired">
            <h3>⏰ Verlopen</h3>
            <div class="number"><?= $expiredTokens ?></div>
        </div>
    </div>
    
    <!-- Toolbar -->
    <div class="toolbar">
        <?php if ($expiredTokens > 0): ?>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="cleanup_expired">
                <button type="submit" class="btn btn-warning">
                    🧹 Opruimen (<?= $expiredTokens ?> verlopen)
                </button>
            </form>
        <?php endif; ?>
        
        <?php if ($totalTokens > 0): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Weet je zeker dat je ALLE tokens wilt verwijderen? Alle gebruikers moeten opnieuw inloggen!')">
                <input type="hidden" name="action" value="delete_all">
                <button type="submit" class="btn btn-danger">
                    ⚠️ Verwijder Alles
                </button>
            </form>
        <?php endif; ?>
    </div>
    
    <!-- Tokens Table -->
    <?php if (empty($tokens)): ?>
        <div style="text-align:center;padding:60px 20px;color:#6b7280">
            <div style="font-size:64px;margin-bottom:20px">🔐</div>
            <h3>Geen actieve tokens</h3>
            <p>Er zijn momenteel geen "Ingelogd blijven" tokens actief.</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Gebruiker</th>
                    <th>Rol</th>
                    <th>Status</th>
                    <th>Aangemaakt</th>
                    <th>Laatst Gebruikt</th>
                    <th>Verloopt</th>
                    <th>IP Address</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tokens as $token): ?>
                <?php 
                    $isExpired = strtotime($token['expires_at']) < time();
                    $daysRemaining = ceil((strtotime($token['expires_at']) - time()) / 86400);
                ?>
                <tr class="<?= $isExpired ? 'expired-row' : '' ?>">
                    <td><?= $token['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($token['display_name'] ?? $token['username']) ?></strong>
                        <br>
                        <span class="token-selector"><?= htmlspecialchars($token['username']) ?></span>
                    </td>
                    <td>
                        <span class="badge badge-role"><?= $token['role'] ?></span>
                    </td>
                    <td>
                        <?php if ($isExpired): ?>
                            <span class="badge badge-expired">Verlopen</span>
                        <?php else: ?>
                            <span class="badge badge-active">Actief (<?= $daysRemaining ?>d)</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d-m-Y H:i', strtotime($token['created_at'])) ?></td>
                    <td><?= $token['last_used_at'] ? date('d-m-Y H:i', strtotime($token['last_used_at'])) : '-' ?></td>
                    <td><?= date('d-m-Y H:i', strtotime($token['expires_at'])) ?></td>
                    <td>
                        <?= htmlspecialchars($token['ip_address'] ?? '-') ?>
                        <br>
                        <span style="font-size:11px;color:#6c757d"><?= htmlspecialchars(substr($token['user_agent'] ?? '', 0, 50)) ?></span>
                    </td>
                    <td>
                        <form method="post" style="display:inline" onsubmit="return confirm('Weet je zeker dat je deze token wilt verwijderen?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="token_id" value="<?= $token['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <div style="margin-top:30px;padding:20px;background:#fff3cd;border-left:4px solid #856404;border-radius:6px">
        <h3 style="color:#856404;margin-top:0">ℹ️ Over Remember Tokens</h3>
        <ul style="color:#856404;line-height:1.8">
            <li><strong>Security:</strong> Tokens worden veilig opgeslagen met SHA256 hashing</li>
            <li><strong>Expiry:</strong> Tokens verlopen automatisch na 30 dagen</li>
            <li><strong>Rotation:</strong> Bij elk gebruik wordt een nieuw token gegenereerd</li>
            <li><strong>IP Tracking:</strong> IP adres wordt gelogd voor audit doeleinden</li>
            <li><strong>Logout:</strong> Bij uitloggen wordt de token automatisch verwijderd</li>
        </ul>
    </div>
</div>
</body>
</html>
