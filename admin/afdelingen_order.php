<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * ============================================================================
 * BESTANDSNAAM:  afdelingen_order.php
 * UPLOAD NAAR:   /admin/afdelingen_order.php
 * DATUM:         2024-12-15
 * VERSIE:        1.0 - Drag & Drop Sorting
 * ============================================================================
 */

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/auth_helper.php';

requireAdmin();
requireAdminFeature('manage_departments_order');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user is admin or superadmin
$userRole = $_SESSION['role'] ?? 'user';
if (!in_array($userRole, ['admin', 'superadmin'])) {
    header('Location: ../frontpage.php');
    exit;
}

// Handle AJAX save request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_order') {
    header('Content-Type: application/json');
    
    $order = json_decode($_POST['order'], true);
    
    if (!$order || !is_array($order)) {
        echo json_encode(['success' => false, 'error' => 'Invalid order data']);
        exit;
    }
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("UPDATE afdelingen SET sort_order = ? WHERE id = ?");
        
        foreach ($order as $index => $id) {
            $stmt->execute([$index, $id]);
        }
        
        $db->commit();
        
        echo json_encode(['success' => true]);
        exit;
        
    } catch (PDOException $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Load afdelingen
try {
    $stmt = $db->query("SELECT * FROM afdelingen ORDER BY sort_order ASC, id ASC");
    $afdelingen = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$currentUser = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afdelingen Sorteren - PeopleDisplay</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            margin-bottom: 24px;
        }
        
        .header h1 {
            font-size: 28px;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        .header p {
            color: #718096;
            font-size: 14px;
        }
        
        .actions {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .btn-secondary:hover {
            background: #f7fafc;
        }
        
        .info-box {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            border-left: 4px solid #4299e1;
        }
        
        .info-box h3 {
            color: #2d3748;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .info-box p {
            color: #718096;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .departments-list {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        
        .department-item {
            background: #f7fafc;
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 12px;
            cursor: move;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .department-item:hover {
            background: #edf2f7;
            border-color: #667eea;
        }
        
        .department-item.dragging {
            opacity: 0.5;
            transform: scale(0.95);
        }
        
        .drag-handle {
            font-size: 24px;
            color: #a0aec0;
            cursor: grab;
        }
        
        .drag-handle:active {
            cursor: grabbing;
        }
        
        .department-info {
            flex: 1;
        }
        
        .department-name {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 4px;
        }
        
        .department-meta {
            font-size: 13px;
            color: #718096;
        }
        
        .order-badge {
            background: #667eea;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        
        .alert {
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
            animation: slideIn 0.3s;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
        
        .alert.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏢 Afdelingen Sorteren</h1>
            <p>Sleep afdelingen om de volgorde aan te passen</p>
        </div>
        
        <div class="actions">
            <button id="saveBtn" class="btn btn-primary">
                💾 Volgorde Opslaan
            </button>
            <a href="afdelingen_manage.php" class="btn btn-secondary">
                ← Terug naar Afdelingen
            </a>
        </div>
        
        <div id="alert" class="alert"></div>
        
        <div class="info-box">
            <h3>📝 Hoe werkt het?</h3>
            <p>Sleep de afdelingen naar de gewenste volgorde. De volgorde wordt gebruikt in dropdown menu's en filters. Klik op "Volgorde Opslaan" om de wijzigingen op te slaan.</p>
        </div>
        
        <div class="departments-list">
            <?php if (empty($afdelingen)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>Geen afdelingen gevonden</h3>
                    <p>Voeg eerst afdelingen toe via Afdelingen Beheer</p>
                </div>
            <?php else: ?>
                <div id="sortableList">
                    <?php foreach ($afdelingen as $index => $dept): ?>
                        <div class="department-item" draggable="true" data-id="<?= $dept['id'] ?>">
                            <div class="drag-handle">⋮⋮</div>
                            <div class="department-info">
                                <div class="department-name">
                                    <?= htmlspecialchars($dept['afdeling_name'] ?? 'Onbekend') ?>
                                </div>
                                <div class="department-meta">
                                    ID: <?= $dept['id'] ?> 
                                    • Code: <?= htmlspecialchars($dept['afdeling_code'] ?? '-') ?>
                                    <?php if (isset($dept['active'])): ?>
                                        • Status: <?= $dept['active'] ? 'Actief' : 'Inactief' ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="order-badge">#<?= $index + 1 ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Drag and drop functionality
        const sortableList = document.getElementById('sortableList');
        const saveBtn = document.getElementById('saveBtn');
        const alert = document.getElementById('alert');
        
        let draggedElement = null;
        let orderChanged = false;
        
        if (sortableList) {
            const items = sortableList.querySelectorAll('.department-item');
            
            items.forEach(item => {
                item.addEventListener('dragstart', handleDragStart);
                item.addEventListener('dragend', handleDragEnd);
                item.addEventListener('dragover', handleDragOver);
                item.addEventListener('drop', handleDrop);
                item.addEventListener('dragenter', handleDragEnter);
                item.addEventListener('dragleave', handleDragLeave);
            });
        }
        
        function handleDragStart(e) {
            draggedElement = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', this.innerHTML);
        }
        
        function handleDragEnd(e) {
            this.classList.remove('dragging');
            
            // Remove all drag-over classes
            const items = sortableList.querySelectorAll('.department-item');
            items.forEach(item => {
                item.classList.remove('drag-over');
            });
            
            // Update order badges
            updateOrderBadges();
            orderChanged = true;
        }
        
        function handleDragOver(e) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            e.dataTransfer.dropEffect = 'move';
            return false;
        }
        
        function handleDragEnter(e) {
            if (this !== draggedElement) {
                this.classList.add('drag-over');
            }
        }
        
        function handleDragLeave(e) {
            this.classList.remove('drag-over');
        }
        
        function handleDrop(e) {
            if (e.stopPropagation) {
                e.stopPropagation();
            }
            
            if (draggedElement !== this) {
                // Get positions
                const allItems = Array.from(sortableList.querySelectorAll('.department-item'));
                const draggedIndex = allItems.indexOf(draggedElement);
                const targetIndex = allItems.indexOf(this);
                
                // Reorder
                if (draggedIndex < targetIndex) {
                    this.parentNode.insertBefore(draggedElement, this.nextSibling);
                } else {
                    this.parentNode.insertBefore(draggedElement, this);
                }
            }
            
            return false;
        }
        
        function updateOrderBadges() {
            const items = sortableList.querySelectorAll('.department-item');
            items.forEach((item, index) => {
                const badge = item.querySelector('.order-badge');
                if (badge) {
                    badge.textContent = '#' + (index + 1);
                }
            });
        }
        
        // Save button
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                if (!orderChanged) {
                    showAlert('Geen wijzigingen om op te slaan', 'error');
                    return;
                }
                
                const items = sortableList.querySelectorAll('.department-item');
                const order = Array.from(items).map(item => item.dataset.id);
                
                saveBtn.disabled = true;
                saveBtn.textContent = '💾 Bezig met opslaan...';
                
                // Send to server
                const formData = new FormData();
                formData.append('action', 'save_order');
                formData.append('order', JSON.stringify(order));
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('✅ Volgorde succesvol opgeslagen!', 'success');
                        orderChanged = false;
                    } else {
                        showAlert('❌ Fout bij opslaan: ' + (data.error || 'Onbekende fout'), 'error');
                    }
                })
                .catch(error => {
                    showAlert('❌ Fout bij opslaan: ' + error.message, 'error');
                })
                .finally(() => {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '💾 Volgorde Opslaan';
                });
            });
        }
        
        function showAlert(message, type) {
            alert.textContent = message;
            alert.className = 'alert alert-' + type + ' show';
            
            setTimeout(() => {
                alert.classList.remove('show');
            }, 5000);
        }
    </script>
</body>
</html>
