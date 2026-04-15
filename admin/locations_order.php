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
 * BESTANDSNAAM:  locations_order.php
 * UPLOAD NAAR:   /admin/locations_order.php (OVERSCHRIJF)
 * VERSIE:        v3.0 - Gebruikt locations TABEL ipv config
 * ============================================================================
 */

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/auth_helper.php';

requireAdmin();
requireAdminFeature('manage_locations_order');

$message = '';
$error = '';

try {
    // Handle AJAX save
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'save_order') {
            $newOrder = json_decode($_POST['order'], true);
            
            if (is_array($newOrder)) {
                // Update sort_order voor elke locatie
                foreach ($newOrder as $index => $locationId) {
                    $stmt = $db->prepare("UPDATE locations SET sort_order = ? WHERE id = ?");
                    $stmt->execute([$index + 1, $locationId]);
                }
                
                echo json_encode(['success' => true, 'message' => 'Volgorde opgeslagen!']);
                exit;
            }
        }
    }
    
    // Get all active locations from database
    $stmt = $db->query("
        SELECT id, location_name, location_code, sort_order 
        FROM locations 
        WHERE active = 1 
        ORDER BY sort_order ASC, location_name ASC
    ");
    
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = $e->getMessage();
    $locations = [];
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locatie Volgorde - PeopleDisplay</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { color: #2c3e50; margin-bottom: 20px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #3498db; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .info-box h3 {
            margin-bottom: 8px;
            color: #1976d2;
        }
        
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        
        .location-list {
            list-style: none;
            padding: 0;
        }
        
        .location-item {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: move;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.2s;
        }
        
        .location-item:hover {
            background: #e9ecef;
            border-color: #3498db;
        }
        
        .location-item.dragging {
            opacity: 0.5;
            background: #fff3cd;
            border-color: #ffc107;
        }
        
        .drag-handle {
            font-size: 24px;
            color: #6c757d;
            cursor: grab;
        }
        
        .drag-handle:active {
            cursor: grabbing;
        }
        
        .location-number {
            background: #3498db;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        
        .location-name {
            flex: 1;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .location-code {
            color: #6c757d;
            font-size: 14px;
        }
        
        .save-btn {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
            transition: all 0.3s;
            width: 100%;
        }
        
        .save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
        }
        
        .save-btn:active {
            transform: translateY(0);
        }
        
        .save-btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            box-shadow: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Terug naar Dashboard</a>
        
        <h1>📍 Locatie Volgorde</h1>
        
        <div class="info-box">
            <h3>ℹ️ Hoe werkt het?</h3>
            <p><strong>Sleep de locaties</strong> om de volgorde aan te passen. Deze volgorde wordt gebruikt in:</p>
            <ul style="margin-top: 10px; padding-left: 20px;">
                <li>Location menu op index.php (aanmeldscherm)</li>
                <li>Locatie filter dropdown</li>
                <li>Alle andere locatie selecties</li>
            </ul>
        </div>
        
        <?php if ($error): ?>
            <div class="error">❌ Fout: <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div id="message"></div>
        
        <div class="card">
            <h2 style="margin-bottom: 20px;">Sleep om Volgorde Aan te Passen</h2>
            
            <?php if (empty($locations)): ?>
                <p style="color: #6c757d; text-align: center; padding: 40px;">
                    Geen actieve locaties gevonden. Voeg eerst locaties toe in <strong>Locaties Beheren</strong>.
                </p>
            <?php else: ?>
                <ul id="location-list" class="location-list">
                    <?php foreach ($locations as $index => $loc): ?>
                        <li class="location-item" draggable="true" data-id="<?= $loc['id'] ?>">
                            <span class="drag-handle">⋮⋮</span>
                            <div class="location-number"><?= $index + 1 ?></div>
                            <div class="location-name"><?= htmlspecialchars($loc['location_name']) ?></div>
                            <?php if ($loc['location_code']): ?>
                                <div class="location-code">(<?= htmlspecialchars($loc['location_code']) ?>)</div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <button onclick="saveOrder()" class="save-btn" id="saveBtn">
                    💾 Volgorde Opslaan
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        const list = document.getElementById('location-list');
        let draggedElement = null;
        
        // Drag and drop events
        list?.querySelectorAll('.location-item').forEach(item => {
            item.addEventListener('dragstart', function() {
                draggedElement = this;
                this.classList.add('dragging');
            });
            
            item.addEventListener('dragend', function() {
                this.classList.remove('dragging');
                updateNumbers();
            });
            
            item.addEventListener('dragover', function(e) {
                e.preventDefault();
                const afterElement = getDragAfterElement(list, e.clientY);
                if (afterElement == null) {
                    list.appendChild(draggedElement);
                } else {
                    list.insertBefore(draggedElement, afterElement);
                }
            });
        });
        
        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.location-item:not(.dragging)')];
            
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }
        
        function updateNumbers() {
            document.querySelectorAll('.location-number').forEach((num, index) => {
                num.textContent = index + 1;
            });
        }
        
        async function saveOrder() {
            const items = document.querySelectorAll('.location-item');
            const order = Array.from(items).map(item => item.dataset.id);
            
            const saveBtn = document.getElementById('saveBtn');
            saveBtn.disabled = true;
            saveBtn.textContent = '💾 Opslaan...';
            
            try {
                const response = await fetch('locations_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=save_order&order=' + encodeURIComponent(JSON.stringify(order))
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage('✅ Volgorde succesvol opgeslagen!', 'success');
                } else {
                    showMessage('❌ Fout bij opslaan', 'error');
                }
            } catch (error) {
                showMessage('❌ Fout: ' + error.message, 'error');
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = '💾 Volgorde Opslaan';
            }
        }
        
        function showMessage(text, type) {
            const messageDiv = document.getElementById('message');
            messageDiv.className = type;
            messageDiv.textContent = text;
            messageDiv.style.display = 'block';
            
            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 3000);
        }
    </script>
</body>
</html>
