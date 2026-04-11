<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
// ============================================
// ONLINE USERS MONITOR
// ============================================
// File: admin/online_users.php
// Install location: /admin/online_users.php
// ============================================

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/auth_helper.php';

// Require admin access
requireAdmin();

$page_title = "Online Gebruikers";
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - PeopleDisplay Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 12px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #1a5490;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #1a5490;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2e74b5;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        
        .stat-icon.online {
            background: linear-gradient(135deg, #22c55e, #16a34a);
        }
        
        .stat-icon.idle {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        
        .stat-icon.away {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        
        .stat-icon.total {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
        
        .stat-content h3 {
            font-size: 14px;
            color: #666;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .stat-content .number {
            font-size: 32px;
            font-weight: 700;
            color: #1a5490;
        }
        
        .main-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 10px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #1a5490;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f8fafc;
        }
        
        th {
            padding: 15px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            border-bottom: 2px solid #e2e8f0;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        
        tbody tr {
            transition: background-color 0.2s;
        }
        
        tbody tr:hover {
            background: #f8fafc;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.online {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-badge.idle {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.away {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 2s infinite;
        }
        
        .status-dot.online {
            background: #22c55e;
        }
        
        .status-dot.idle {
            background: #f59e0b;
        }
        
        .status-dot.away {
            background: #ef4444;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .role-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .role-badge.superadmin {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .role-badge.admin {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .role-badge.user {
            background: #e5e7eb;
            color: #374151;
        }
        
        .device-icon {
            font-size: 16px;
        }
        
        .time-info {
            font-size: 12px;
            color: #64748b;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        
        .no-data-icon {
            font-size: 64px;
            margin-bottom: 15px;
        }
        
        .last-updated {
            text-align: right;
            margin-top: 20px;
            font-size: 13px;
            color: #94a3b8;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            font-size: 18px;
            color: #64748b;
        }
        
        .spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid #f3f4f6;
            border-top-color: #1a5490;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .action-btn.danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .action-btn.danger:hover {
            background: #fecaca;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <span>🌐</span>
                <?php echo $page_title; ?>
            </h1>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="refreshData()">
                    🔄 Ververs
                </button>
                <a href="dashboard.php" class="btn btn-primary">
                    ← Dashboard
                </a>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon online">✓</div>
                <div class="stat-content">
                    <h3>Online</h3>
                    <div class="number" id="count-online">0</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon idle">⏱</div>
                <div class="stat-content">
                    <h3>Idle (>1 min)</h3>
                    <div class="number" id="count-idle">0</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon away">⌛</div>
                <div class="stat-content">
                    <h3>Away (>5 min)</h3>
                    <div class="number" id="count-away">0</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon total">👥</div>
                <div class="stat-content">
                    <h3>Totaal Actief</h3>
                    <div class="number" id="count-total">0</div>
                </div>
            </div>
        </div>
        
        <div class="main-content">
            <div class="filters">
                <div class="filter-group">
                    <label>Zoek gebruiker</label>
                    <input type="text" id="search-input" placeholder="Zoek op naam..." style="width: 250px;">
                </div>
                <div class="filter-group">
                    <label>Status filter</label>
                    <select id="status-filter">
                        <option value="">Alle statussen</option>
                        <option value="online">Online</option>
                        <option value="idle">Idle</option>
                        <option value="away">Away</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Rol filter</label>
                    <select id="role-filter">
                        <option value="">Alle rollen</option>
                        <option value="superadmin">SuperAdmin</option>
                        <option value="admin">Admin</option>
                        <option value="user">User</option>
                    </select>
                </div>
            </div>
            
            <div id="loading-state" class="loading">
                <div class="spinner"></div>
                <div>Laden...</div>
            </div>
            
            <div id="table-container" class="table-container" style="display: none;">
                <table>
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Gebruiker</th>
                            <th>Rol</th>
                            <th>Apparaat</th>
                            <th>Browser</th>
                            <th>IP Adres</th>
                            <th>Ingelogd Sinds</th>
                            <th>Laatste Activiteit</th>
                            <th>Sessie Duur</th>
                            <th>Actie</th>
                        </tr>
                    </thead>
                    <tbody id="sessions-tbody">
                        <!-- Data wordt hier geladen via JavaScript -->
                    </tbody>
                </table>
                
                <div id="no-data-state" class="no-data" style="display: none;">
                    <div class="no-data-icon">😴</div>
                    <div>Geen actieve sessies gevonden</div>
                </div>
                
                <div class="last-updated">
                    Laatst bijgewerkt: <span id="last-updated">-</span>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let allSessions = [];
        let autoRefreshInterval;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadData();
            startAutoRefresh();
            
            // Filter event listeners
            document.getElementById('search-input').addEventListener('input', applyFilters);
            document.getElementById('status-filter').addEventListener('change', applyFilters);
            document.getElementById('role-filter').addEventListener('change', applyFilters);
        });
        
        // Load data from API
        function loadData() {
            fetch('api/get_online_users.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allSessions = data.sessions;
                        updateStats(data.status_counts, data.total_count);
                        renderTable(allSessions);
                        document.getElementById('last-updated').textContent = formatDateTime(data.timestamp);
                        
                        // Show table, hide loading
                        document.getElementById('loading-state').style.display = 'none';
                        document.getElementById('table-container').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error loading data:', error);
                    document.getElementById('loading-state').innerHTML = '<div style="color: #ef4444;">❌ Fout bij laden data</div>';
                });
        }
        
        // Update statistics
        function updateStats(counts, total) {
            document.getElementById('count-online').textContent = counts.online;
            document.getElementById('count-idle').textContent = counts.idle;
            document.getElementById('count-away').textContent = counts.away;
            document.getElementById('count-total').textContent = total;
        }
        
        // Render table
        function renderTable(sessions) {
            const tbody = document.getElementById('sessions-tbody');
            
            if (sessions.length === 0) {
                tbody.innerHTML = '';
                document.getElementById('no-data-state').style.display = 'block';
                return;
            }
            
            document.getElementById('no-data-state').style.display = 'none';
            
            tbody.innerHTML = sessions.map(session => `
                <tr>
                    <td>
                        <div class="status-badge ${session.status}">
                            <span class="status-dot ${session.status}"></span>
                            ${capitalizeFirst(session.status)}
                        </div>
                    </td>
                    <td>
                        <div style="font-weight: 600;">${escapeHtml(session.display_name || session.username)}</div>
                        <div class="time-info">${escapeHtml(session.username)}</div>
                    </td>
                    <td>
                        <span class="role-badge ${session.role}">${session.role}</span>
                    </td>
                    <td>
                        <span class="device-icon">${getDeviceIcon(session.device)}</span>
                        ${session.device}
                    </td>
                    <td>${session.browser}</td>
                    <td><code>${session.ip_address}</code></td>
                    <td>
                        ${formatDateTime(session.login_time)}
                    </td>
                    <td>
                        <div>${formatTimeAgo(session.idle_seconds)}</div>
                        <div class="time-info">${formatDateTime(session.last_activity)}</div>
                    </td>
                    <td>${formatDuration(session.session_duration)}</td>
                    <td>
                        <button class="action-btn danger" onclick="forceLogout(${session.user_id}, '${escapeHtml(session.username)}')">
                            🚪 Uitloggen
                        </button>
                    </td>
                </tr>
            `).join('');
        }
        
        // Apply filters
        function applyFilters() {
            const searchTerm = document.getElementById('search-input').value.toLowerCase();
            const statusFilter = document.getElementById('status-filter').value;
            const roleFilter = document.getElementById('role-filter').value;
            
            const filtered = allSessions.filter(session => {
                const matchesSearch = !searchTerm || 
                    session.username.toLowerCase().includes(searchTerm) ||
                    (session.display_name && session.display_name.toLowerCase().includes(searchTerm));
                
                const matchesStatus = !statusFilter || session.status === statusFilter;
                const matchesRole = !roleFilter || session.role === roleFilter;
                
                return matchesSearch && matchesStatus && matchesRole;
            });
            
            renderTable(filtered);
        }
        
        // Force logout user
        function forceLogout(userId, username) {
            if (!confirm(`Weet je zeker dat je ${username} wilt uitloggen?`)) {
                return;
            }
            
            fetch('api/force_logout_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`${username} is uitgelogd`);
                    loadData();
                } else {
                    alert('Fout bij uitloggen: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fout bij uitloggen');
            });
        }
        
        // Auto refresh
        function startAutoRefresh() {
            autoRefreshInterval = setInterval(() => {
                loadData();
            }, 30000); // 30 seconds
        }
        
        function refreshData() {
            loadData();
        }
        
        // Helper functions
        function formatDateTime(datetime) {
            if (!datetime) return '-';
            const date = new Date(datetime);
            return date.toLocaleString('nl-NL', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        function formatTimeAgo(seconds) {
            if (seconds < 60) return `${seconds} sec geleden`;
            if (seconds < 3600) return `${Math.floor(seconds / 60)} min geleden`;
            return `${Math.floor(seconds / 3600)} uur geleden`;
        }
        
        function formatDuration(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            if (hours > 0) return `${hours}u ${minutes}m`;
            return `${minutes} minuten`;
        }
        
        function getDeviceIcon(device) {
            if (device === 'Mobile') return '📱';
            if (device === 'Tablet') return '📱';
            return '💻';
        }
        
        function capitalizeFirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
