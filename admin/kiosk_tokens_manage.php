<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * Kiosk Tokens Management Interface
 * Admin page voor beheren van kiosk auto-login tokens
 */

// Error reporting voor debugging (verwijder later in productie)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Database connectie
try {
    require_once __DIR__ . '/../includes/db.php';
} catch (Exception $e) {
    die('Database connectie fout: ' . $e->getMessage());
}

require_once __DIR__ . '/../includes/license_check.php';
requireFeature('kiosk_mode');

// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = "Kiosk Tokens Beheren";
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - PeopleDisplay</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #333;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        .btn-back:hover {
            background: #5a6268;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            font-size: 13px;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-copy {
            background: #28a745;
            color: white;
            padding: 8px 16px;
            font-size: 13px;
        }
        
        .btn-copy:hover {
            background: #218838;
        }
        
        .btn-qr {
            background: #17a2b8;
            color: white;
            padding: 8px 16px;
            font-size: 13px;
        }
        
        .btn-qr:hover {
            background: #138496;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .tokens-list {
            margin-top: 20px;
        }
        
        .token-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        
        .token-item:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateX(5px);
        }
        
        .token-item.inactive {
            opacity: 0.6;
            border-left-color: #dc3545;
        }
        
        .token-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .token-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .token-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .token-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .info-label {
            font-size: 12px;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .info-value {
            font-size: 14px;
            color: #333;
        }
        
        .token-url {
            background: white;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            word-break: break-all;
            margin-bottom: 15px;
        }
        
        .token-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 10px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            margin-bottom: 20px;
        }
        
        .modal-header h2 {
            color: #333;
            font-size: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #6c757d;
            font-size: 12px;
        }
        
        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }
        
        .alert.show {
            display: block;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state svg {
            width: 100px;
            height: 100px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .qr-code-container {
            text-align: center;
            padding: 20px;
        }
        
        #qrcode {
            display: inline-block;
            margin: 20px auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>
                🖥️ <?php echo $page_title; ?>
            </h1>
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-primary" onclick="showCreateModal()">
                    ➕ Nieuwe Kiosk Token
                </button>
                <a href="dashboard.php" class="btn btn-back">
                    ← Terug naar Dashboard
                </a>
            </div>
        </div>
        
        <!-- Alert messages -->
        <div id="alertContainer"></div>
        
        <!-- Tokens List -->
        <div class="card">
            <h2 style="margin-bottom: 20px; color: #333;">Actieve Kiosk Tokens</h2>
            <div id="tokensList" class="loading">
                Laden...
            </div>
        </div>
    </div>
    
    <!-- Create Token Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Nieuwe Kiosk Token Aanmaken</h2>
            </div>
            <form id="createForm">
                <div class="form-group">
                    <label for="user_id">Gebruiker *</label>
                    <select id="user_id" name="user_id" required>
                        <option value="">-- Selecteer gebruiker --</option>
                    </select>
                    <small>Kies de gebruiker die automatisch ingelogd moet worden</small>
                </div>
                
                <div class="form-group">
                    <label for="description">Beschrijving</label>
                    <input type="text" id="description" name="description" 
                           placeholder="Bijv: PC Receptie BSO De Vlinder">
                    <small>Optioneel: herkenbare beschrijving van de kiosk locatie</small>
                </div>
                
                <div class="form-group">
                    <label for="allowed_ip">IP-adres (optioneel)</label>
                    <input type="text" id="allowed_ip" name="allowed_ip" 
                           placeholder="192.168.1.50">
                    <small>Optioneel: beperk token tot specifiek IP-adres voor extra beveiliging</small>
                </div>
                
                <div class="form-group">
                    <label for="expires_days">Vervalt na (dagen)</label>
                    <input type="number" id="expires_days" name="expires_days" 
                           value="0" min="0" max="3650">
                    <small>0 = nooit vervallen, anders aantal dagen geldig</small>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-back" onclick="hideCreateModal()">
                        Annuleren
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Token Aanmaken
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Success Modal (shows after token creation) -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✅ Token Succesvol Aangemaakt!</h2>
            </div>
            <div style="margin-bottom: 20px;">
                <p style="margin-bottom: 15px; color: #333;">
                    Gebruik deze URL in de Edge Kiosk configuratie:
                </p>
                <div class="token-url" id="successUrl" style="margin-bottom: 15px;">
                    <!-- URL wordt hier getoond -->
                </div>
                <button class="btn btn-copy" onclick="copySuccessUrl()">
                    📋 Kopieer URL
                </button>
            </div>
            <div class="qr-code-container">
                <p style="margin-bottom: 10px; color: #666;">
                    Of scan deze QR-code met een mobiel apparaat:
                </p>
                <div id="qrcode"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="hideSuccessModal()">
                    Sluiten
                </button>
            </div>
        </div>
    </div>
    
    <!-- Edit Token Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Token Wijzigen</h2>
            </div>
            <form id="editForm">
                <input type="hidden" id="edit_token_id" name="token_id">
                
                <div class="form-group">
                    <label for="edit_description">Beschrijving</label>
                    <input type="text" id="edit_description" name="description" 
                           placeholder="Bijv: PC Receptie BSO De Vlinder">
                    <small>Herkenbare beschrijving van de kiosk locatie</small>
                </div>
                
                <div class="form-group">
                    <label for="edit_allowed_ip">IP-adres (optioneel)</label>
                    <input type="text" id="edit_allowed_ip" name="allowed_ip" 
                           placeholder="192.168.1.50">
                    <small>Laat leeg voor geen IP beperking, of vul specifiek IP in</small>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-back" onclick="hideEditModal()">
                        Annuleren
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Opslaan
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- QR Code library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <script>
        // Load tokens on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadTokens();
            loadUsers();
        });
        
        // Load all kiosk tokens
        function loadTokens() {
            fetch('api/kiosk_token_actions.php?action=list')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        renderTokens(data.tokens);
                    } else {
                        showError('Fout bij laden tokens: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error('Error loading tokens:', err);
                    showError('Fout bij laden tokens');
                });
        }
        
        // Load users for dropdown
        function loadUsers() {
            fetch('api/kiosk_token_actions.php?action=get_users')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('user_id');
                        data.users.forEach(user => {
                            const option = document.createElement('option');
                            option.value = user.id;
                            option.textContent = `${user.username} (${user.display_name}) - ${user.role}`;
                            select.appendChild(option);
                        });
                    }
                });
        }
        
        // Render tokens list
        function renderTokens(tokens) {
            const container = document.getElementById('tokensList');
            
            if (!tokens || tokens.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <h3>Geen kiosk tokens gevonden</h3>
                        <p>Klik op "Nieuwe Kiosk Token" om er een aan te maken</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = tokens.map(token => {
                const statusClass = token.is_active ? 'status-active' : 'status-inactive';
                const statusText = token.is_active ? 'Actief' : 'Inactief';
                const itemClass = token.is_active ? '' : 'inactive';
                
                const lastUsed = token.last_used 
                    ? formatDateTime(token.last_used)
                    : 'Nog niet gebruikt';
                
                const expires = token.expires_at 
                    ? formatDateTime(token.expires_at)
                    : 'Nooit';
                
                const kioskUrl = generateKioskUrl(token.token);
                
                return `
                    <div class="token-item ${itemClass}">
                        <div class="token-header">
                            <div class="token-title">
                                🖥️ ${escapeHtml(token.description || 'Kiosk Token')}
                            </div>
                            <div class="token-status ${statusClass}">
                                ${statusText}
                            </div>
                        </div>
                        
                        <div class="token-info">
                            <div class="info-item">
                                <div class="info-label">Gebruiker</div>
                                <div class="info-value">${escapeHtml(token.display_name)} (${escapeHtml(token.username)})</div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">IP-adres beperking</div>
                                <div class="info-value">${token.allowed_ip || 'Geen (alle IPs toegestaan)'}</div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Laatst gebruikt</div>
                                <div class="info-value">${lastUsed}</div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Verloopt op</div>
                                <div class="info-value">${expires}</div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Aangemaakt door</div>
                                <div class="info-value">${escapeHtml(token.created_by_name || 'Onbekend')}</div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Aangemaakt op</div>
                                <div class="info-value">${formatDateTime(token.created_at)}</div>
                            </div>
                        </div>
                        
                        <div class="token-url">${kioskUrl}</div>
                        
                        <div class="token-actions">
                            <button class="btn btn-copy" onclick="copyToClipboard('${kioskUrl}', this)">
                                📋 Kopieer URL
                            </button>
                            <button class="btn btn-qr" onclick="showQRCode('${kioskUrl}')">
                                📱 Toon QR-Code
                            </button>
                            <button class="btn btn-primary" onclick="editToken(${token.id}, '${escapeHtml(token.description || '')}', '${escapeHtml(token.allowed_ip || '')}')">
                                ✏️ Wijzigen
                            </button>
                            <button class="btn ${token.is_active ? 'btn-back' : 'btn-primary'}" 
                                    onclick="toggleTokenStatus(${token.id})">
                                ${token.is_active ? '⏸️ Deactiveren' : '▶️ Activeren'}
                            </button>
                            <button class="btn btn-danger" onclick="deleteToken(${token.id}, '${escapeHtml(token.description || 'deze token')}')">
                                🗑️ Verwijderen
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        // Generate kiosk URL
        function generateKioskUrl(token) {
            const protocol = window.location.protocol;
            const host = window.location.host;
            const pathParts = window.location.pathname.split('/');
            pathParts.pop(); // Remove filename
            pathParts.pop(); // Remove 'admin' directory
            const basePath = pathParts.join('/');
            return `${protocol}//${host}${basePath}/kiosk_login.php?token=${token}`;
        }
        
        // Format datetime
        function formatDateTime(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleString('nl-NL', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Show create modal
        function showCreateModal() {
            document.getElementById('createModal').classList.add('show');
        }
        
        // Hide create modal
        function hideCreateModal() {
            document.getElementById('createModal').classList.remove('show');
            document.getElementById('createForm').reset();
        }
        
        // Hide success modal
        function hideSuccessModal() {
            document.getElementById('successModal').classList.remove('show');
            document.getElementById('qrcode').innerHTML = '';
        }
        
        // Handle form submission
        document.getElementById('createForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'create');
            
            fetch('api/kiosk_token_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    hideCreateModal();
                    showSuccess('Token succesvol aangemaakt!');
                    
                    // Show success modal with URL and QR code
                    document.getElementById('successUrl').textContent = data.kiosk_url;
                    document.getElementById('successModal').classList.add('show');
                    
                    // Generate QR code
                    new QRCode(document.getElementById('qrcode'), {
                        text: data.kiosk_url,
                        width: 256,
                        height: 256
                    });
                    
                    // Reload tokens
                    loadTokens();
                } else {
                    showError(data.message);
                }
            })
            .catch(err => {
                console.error('Error creating token:', err);
                showError('Fout bij aanmaken token');
            });
        });
        
        // Copy to clipboard
        function copyToClipboard(text, button) {
            navigator.clipboard.writeText(text).then(() => {
                const originalText = button.innerHTML;
                button.innerHTML = '✅ Gekopieerd!';
                button.style.background = '#28a745';
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.style.background = '';
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy:', err);
                showError('Kopiëren mislukt');
            });
        }
        
        // Copy success URL
        function copySuccessUrl() {
            const url = document.getElementById('successUrl').textContent;
            navigator.clipboard.writeText(url).then(() => {
                showSuccess('URL gekopieerd naar klembord!');
            });
        }
        
        // Show QR code in modal
        function showQRCode(url) {
            document.getElementById('successUrl').textContent = url;
            document.getElementById('successModal').classList.add('show');
            document.getElementById('qrcode').innerHTML = '';
            new QRCode(document.getElementById('qrcode'), {
                text: url,
                width: 256,
                height: 256
            });
        }
        
        // Toggle token status
        function toggleTokenStatus(tokenId) {
            if (!confirm('Weet je zeker dat je de status wilt wijzigen?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('token_id', tokenId);
            
            fetch('api/kiosk_token_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data.message);
                    loadTokens();
                } else {
                    showError(data.message);
                }
            })
            .catch(err => {
                console.error('Error toggling status:', err);
                showError('Fout bij wijzigen status');
            });
        }
        
        // Delete token
        function deleteToken(tokenId, description) {
            if (!confirm(`Weet je zeker dat je "${description}" wilt verwijderen?\n\nDit kan niet ongedaan gemaakt worden!`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('token_id', tokenId);
            
            fetch('api/kiosk_token_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Token succesvol verwijderd');
                    loadTokens();
                } else {
                    showError(data.message);
                }
            })
            .catch(err => {
                console.error('Error deleting token:', err);
                showError('Fout bij verwijderen token');
            });
        }
        
        // Edit token - show modal with current values
        function editToken(tokenId, description, allowedIp) {
            document.getElementById('edit_token_id').value = tokenId;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_allowed_ip').value = allowedIp;
            document.getElementById('editModal').classList.add('show');
        }
        
        // Hide edit modal
        function hideEditModal() {
            document.getElementById('editModal').classList.remove('show');
            document.getElementById('editForm').reset();
        }
        
        // Handle edit form submission
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update');
            
            fetch('api/kiosk_token_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    hideEditModal();
                    showSuccess('Token succesvol bijgewerkt');
                    loadTokens();
                } else {
                    showError(data.message);
                }
            })
            .catch(err => {
                console.error('Error updating token:', err);
                showError('Fout bij bijwerken token');
            });
        });
        
        // Show success message
        function showSuccess(message) {
            showAlert(message, 'success');
        }
        
        // Show error message
        function showError(message) {
            showAlert(message, 'error');
        }
        
        // Show alert
        function showAlert(message, type) {
            const container = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} show`;
            alert.textContent = message;
            
            container.innerHTML = '';
            container.appendChild(alert);
            
            setTimeout(() => {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        }
        
        // Close modal on background click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>
