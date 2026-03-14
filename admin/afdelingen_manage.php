<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: afdelingen_manage.php
 * LOCATIE:      /admin/afdelingen_manage.php
 * BESCHRIJVING: Manage afdelingen/departments
 * ═══════════════════════════════════════════════════════════════════
 */

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/license_check.php';
require_once __DIR__ . '/auth_helper.php';

requireAdmin();

$message = '';
if (!empty($_SESSION['pd_flash'])) {
    $message = $_SESSION['pd_flash'];
    unset($_SESSION['pd_flash']);
}
$error      = '';
$limitAlert = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['afdeling_name'] ?? '');
        $code = trim($_POST['afdeling_code'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name)) {
            $error = 'Afdeling naam is verplicht';
        } elseif (!canAddDepartment()) {
            $limits = getTierLimits();
            $limitAlert = getLimitExceededMessage('departments', $limits['max_departments']);
        } else {
            $stmt = $db->prepare("INSERT INTO afdelingen (afdeling_name, afdeling_code, description) VALUES (?, ?, ?)");
            if ($stmt->execute([$name, $code, $description])) {
                $_SESSION['pd_flash'] = "✅ Afdeling toegevoegd: $name";
                header('Location: afdelingen_manage.php');
                exit;
            } else {
                $error = "❌ Fout: Afdeling bestaat mogelijk al";
            }
        }
    }
    
    elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $name = trim($_POST['afdeling_name'] ?? '');
        $code = trim($_POST['afdeling_code'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name)) {
            $error = 'Afdeling naam is verplicht';
        } else {
            // Haal oude naam op VOOR de update
            $stmt = $db->prepare("SELECT afdeling_name FROM afdelingen WHERE id = ?");
            $stmt->execute([$id]);
            $oude_naam = $stmt->fetchColumn();

            // Update de afdeling zelf
            $stmt = $db->prepare("UPDATE afdelingen SET afdeling_name = ?, afdeling_code = ?, description = ? WHERE id = ?");
            if ($stmt->execute([$name, $code, $description, $id])) {
                // Cascade: medewerkers automatisch meenemen
                $stmt2 = $db->prepare("UPDATE employees SET afdeling = ? WHERE afdeling = ?");
                $stmt2->execute([$name, $oude_naam]);
                $bijgewerkt = $stmt2->rowCount();

                $extra = $bijgewerkt > 0 ? " ($bijgewerkt medewerker(s) automatisch bijgewerkt)" : "";
                $_SESSION['pd_flash'] = "✅ Afdeling bijgewerkt: $name$extra";
                header('Location: afdelingen_manage.php');
                exit;
            } else {
                $error = "❌ Fout bij updaten";
            }
        }
    }
    
    elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        $stmt = $db->prepare("UPDATE afdelingen SET active = 0 WHERE id = ?");
        if ($stmt->execute([$id])) {
            $_SESSION['pd_flash'] = "✅ Afdeling gedeactiveerd";
            header('Location: afdelingen_manage.php');
            exit;
        } else {
            $error = "❌ Fout bij verwijderen";
        }
    }
    
    elseif ($action === 'reorder') {
        $orders = json_decode($_POST['orders'] ?? '[]', true);
        foreach ($orders as $item) {
            $stmt = $db->prepare("UPDATE afdelingen SET sort_order = ? WHERE id = ?");
            $stmt->execute([$item['order'], $item['id']]);
        }
        $message = "✅ Volgorde opgeslagen";
    }
}

// Get all afdelingen
$stmt = $db->query("SELECT * FROM afdelingen WHERE active = 1 ORDER BY sort_order ASC, afdeling_name ASC");
$afdelingen = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afdelingen Beheren</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body {
            background: #f7fafc;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .btn-back {
            background: #718096;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
        }
        
        .form-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr 3fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2d3748;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #4299e1;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: #3182ce;
        }
        
        .message {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .message.success {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .message.error {
            background: #fed7d7;
            color: #c53030;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        th {
            background: #f7fafc;
            font-weight: 600;
            color: #2d3748;
        }
        
        .btn-edit, .btn-delete {
            padding: 6px 12px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            margin-right: 5px;
        }
        
        .btn-edit {
            background: #4299e1;
            color: white;
        }
        
        .btn-delete {
            background: #fc8181;
            color: white;
        }
        
        .drag-handle {
            cursor: move;
            color: #718096;
        }

        .license-limit-alert {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 20px;
            margin: 20px 0;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        .license-limit-alert .alert-icon { font-size: 48px; opacity: 0.9; }
        .license-limit-alert .alert-content { flex: 1; }
        .license-limit-alert .alert-content h3 { margin: 0 0 10px 0; font-size: 20px; }
        .license-limit-alert .alert-content p { margin: 5px 0; opacity: 0.95; }
        .license-limit-alert .alert-actions { flex-shrink: 0; }
        .btn-upgrade {
            background: white;
            color: #667eea;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            white-space: nowrap;
            transition: transform 0.2s;
            display: inline-block;
        }
        .btn-upgrade:hover { transform: scale(1.05); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="btn-back">← Terug naar Dashboard</a>
            <h1 style="margin: 15px 0 0 0;">🏢 Afdelingen Beheren</h1>
        </div>
        
        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($limitAlert): ?>
            <div class="license-limit-alert">
                <div class="alert-icon"><?= $limitAlert['icon'] ?></div>
                <div class="alert-content">
                    <h3><?= $limitAlert['title'] ?></h3>
                    <p><?= $limitAlert['message'] ?></p>
                    <p><?= $limitAlert['upgradeMessage'] ?></p>
                </div>
                <div class="alert-actions">
                    <a href="license_management.php" class="btn-upgrade">Upgrade Pakket</a>
                </div>
            </div>
        <?php elseif ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- ADD NEW AFDELING -->
        <div class="form-section">
            <h2>➕ Nieuwe Afdeling Toevoegen</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Afdeling Naam *</label>
                        <input type="text" name="afdeling_name" required placeholder="bijv. Kinderopvang">
                    </div>
                    
                    <div class="form-group">
                        <label>Code</label>
                        <input type="text" name="afdeling_code" placeholder="bijv. KDV">
                    </div>
                    
                    <div class="form-group">
                        <label>Omschrijving</label>
                        <input type="text" name="description" placeholder="Optionele omschrijving">
                    </div>
                </div>
                
                <button type="submit" class="btn-primary">💾 Toevoegen</button>
            </form>
        </div>
        
        <!-- EXISTING AFDELINGEN -->
        <div class="form-section">
            <h2>📋 Bestaande Afdelingen (<?= count($afdelingen) ?>)</h2>
            
            <?php if (empty($afdelingen)): ?>
                <p style="color: #718096;">Nog geen afdelingen aangemaakt.</p>
            <?php else: ?>
                <table id="afdelingen-table">
                    <thead>
                        <tr>
                            <th width="30">↕️</th>
                            <th>Naam</th>
                            <th>Code</th>
                            <th>Omschrijving</th>
                            <th width="150">Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($afdelingen as $afd): ?>
                        <tr data-id="<?= $afd['id'] ?>">
                            <td class="drag-handle">⋮⋮</td>
                            <td><?= htmlspecialchars($afd['afdeling_name']) ?></td>
                            <td><?= htmlspecialchars($afd['afdeling_code'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($afd['description'] ?? '-') ?></td>
                            <td>
                                <button class="btn-edit" onclick='editAfdeling(<?= json_encode($afd) ?>)'>✏️ Edit</button>
                                <button class="btn-delete" onclick="deleteAfdeling(<?= $afd['id'] ?>, '<?= htmlspecialchars($afd['afdeling_name']) ?>')">🗑️</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- EDIT MODAL -->
    <div id="edit-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>✏️ Afdeling Bewerken</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                
                <div class="form-group">
                    <label>Afdeling Naam *</label>
                    <input type="text" name="afdeling_name" id="edit-name" required>
                </div>
                
                <div class="form-group">
                    <label>Code</label>
                    <input type="text" name="afdeling_code" id="edit-code">
                </div>
                
                <div class="form-group">
                    <label>Omschrijving</label>
                    <input type="text" name="description" id="edit-description">
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-primary">💾 Opslaan</button>
                    <button type="button" class="btn-back" onclick="closeModal()">Annuleren</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- DELETE FORM -->
    <form id="delete-form" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete-id">
    </form>
    
    <script>
    function editAfdeling(afd) {
        document.getElementById('edit-id').value = afd.id;
        document.getElementById('edit-name').value = afd.afdeling_name;
        document.getElementById('edit-code').value = afd.afdeling_code || '';
        document.getElementById('edit-description').value = afd.description || '';
        document.getElementById('edit-modal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('edit-modal').style.display = 'none';
    }
    
    function deleteAfdeling(id, name) {
        if (confirm(`Weet je zeker dat je "${name}" wilt verwijderen?`)) {
            document.getElementById('delete-id').value = id;
            document.getElementById('delete-form').submit();
        }
    }
    
    // Modal styling
    const modal = document.getElementById('edit-modal');
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100%';
    modal.style.height = '100%';
    modal.style.background = 'rgba(0,0,0,0.5)';
    modal.style.display = 'none';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    modal.style.zIndex = '1000';
    
    const modalContent = modal.querySelector('.modal-content');
    modalContent.style.background = 'white';
    modalContent.style.padding = '30px';
    modalContent.style.borderRadius = '12px';
    modalContent.style.maxWidth = '500px';
    modalContent.style.width = '90%';
    
    // Close on outside click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
    </script>
</body>
</html>
