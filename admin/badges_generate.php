<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: badges_generate.php
 * LOCATIE:      /admin/badges_generate.php
 * VERSIE:       1.0 - Basis Badge Generator
 * 
 * BESCHRIJVING: Genereer printbare employee badges met QR-codes
 * ═══════════════════════════════════════════════════════════════════
 */

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/auth_helper.php';

requireAdmin();

// Get all active employees
$stmt = $db->query("
    SELECT id, employee_id, naam, voornaam, achternaam, functie, afdeling, locatie, foto_url, bhv
    FROM employees
    WHERE actief = 1
    ORDER BY naam ASC
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique locations and afdelingen for filters
$locations = $db->query("SELECT DISTINCT locatie FROM employees WHERE actief = 1 AND locatie IS NOT NULL AND locatie != '' ORDER BY locatie")->fetchAll(PDO::FETCH_COLUMN);

// Try to get afdelingen from afdelingen table first, fallback to employees table
try {
    $afdelingen = $db->query("SELECT afdeling_naam FROM afdelingen WHERE active = 1 ORDER BY sort_order, afdeling_naam")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($afdelingen)) {
        // Fallback to employees table
        $afdelingen = $db->query("SELECT DISTINCT afdeling FROM employees WHERE actief = 1 AND afdeling IS NOT NULL AND afdeling != '' ORDER BY afdeling")->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    // afdelingen table doesn't exist, use employees table
    $afdelingen = $db->query("SELECT DISTINCT afdeling FROM employees WHERE actief = 1 AND afdeling IS NOT NULL AND afdeling != '' ORDER BY afdeling")->fetchAll(PDO::FETCH_COLUMN);
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎫 Badge Generator - PeopleDisplay</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 28px;
            color: #1a202c;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-back {
            background: #718096;
            color: white;
        }
        
        .btn-back:hover {
            background: #4a5568;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 16px;
            padding: 15px 30px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .card h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filters {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            font-weight: 600;
            font-size: 14px;
            color: #4a5568;
        }
        
        select, input {
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        select:focus, input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .search-box {
            margin-bottom: 15px;
        }
        
        .search-box input {
            width: 100%;
        }
        
        .employee-list {
            max-height: 500px;
            overflow-y: auto;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
        }
        
        .employee-item {
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .employee-item:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
        }
        
        .employee-item.selected {
            background: #e6fffa;
            border-color: #38b2ac;
        }
        
        .employee-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .employee-photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background: #e2e8f0;
        }
        
        .employee-info {
            flex: 1;
        }
        
        .employee-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 2px;
        }
        
        .employee-details {
            font-size: 12px;
            color: #718096;
        }
        
        .selection-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
            padding: 8px 16px;
            font-size: 13px;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        .stats {
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        
        .stat-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 14px;
            color: #718096;
        }
        
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .badge-preview {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            background: white;
        }
        
        .badge-preview img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin-bottom: 10px;
            object-fit: cover;
        }
        
        .badge-preview .name {
            font-weight: 600;
            font-size: 14px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .badge-preview .function {
            font-size: 12px;
            color: #718096;
        }
        
        .generate-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }
        
        .info-box {
            background: #ebf8ff;
            border-left: 4px solid #4299e1;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .info-box p {
            font-size: 14px;
            color: #2c5282;
            line-height: 1.6;
        }
        
        @media (max-width: 1024px) {
            .container {
                grid-template-columns: 1fr;
            }
        }
        
        .template-option:hover {
            border-color: #a0aec0 !important;
            background: #f7fafc !important;
        }
        
        .template-option input[type="radio"]:checked + div {
            color: #2d3748;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎫 Badge Generator</h1>
        <a href="dashboard.php" class="btn btn-back">← Terug naar Dashboard</a>
    </div>
    
    <div class="container">
        <!-- LEFT: Employee Selection -->
        <div class="card">
            <h2>👥 Selecteer Employees</h2>
            
            <!-- Filters -->
            <div class="filters">
                <div class="filter-group">
                    <label>Locatie:</label>
                    <select id="filter-location">
                        <option value="">Alle locaties</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Afdeling:</label>
                    <select id="filter-afdeling">
                        <option value="">Alle afdelingen</option>
                        <?php foreach ($afdelingen as $afd): ?>
                            <option value="<?= htmlspecialchars($afd) ?>"><?= htmlspecialchars($afd) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Search -->
            <div class="search-box">
                <input type="text" id="search-input" placeholder="🔍 Zoek op naam...">
            </div>
            
            <!-- Selection Actions -->
            <div class="selection-actions">
                <button class="btn btn-secondary" onclick="selectAll()">✓ Alles</button>
                <button class="btn btn-secondary" onclick="deselectAll()">✗ Niets</button>
                <button class="btn btn-secondary" onclick="selectFiltered()">✓ Gefilterd</button>
                <button class="btn btn-secondary" onclick="selectBHV()" style="background: #dc2626; color: white;">🚨 BHV'ers</button>
            </div>
            
            <!-- Employee List -->
            <div class="employee-list" id="employee-list">
                <?php foreach ($employees as $emp): ?>
                    <div class="employee-item" 
                         data-id="<?= $emp['id'] ?>"
                         data-employee-id="<?= htmlspecialchars($emp['employee_id']) ?>"
                         data-location="<?= htmlspecialchars($emp['locatie'] ?? '') ?>"
                         data-afdeling="<?= htmlspecialchars($emp['afdeling'] ?? '') ?>"
                         data-name="<?= htmlspecialchars($emp['naam']) ?>"
                         data-functie="<?= htmlspecialchars($emp['functie'] ?? '') ?>"
                         data-foto="<?= htmlspecialchars($emp['foto_url'] ?? '') ?>"
                         data-bhv="<?= htmlspecialchars($emp['bhv'] ?? 'Nee') ?>"
                         onclick="toggleEmployee(this)">
                        
                        <input type="checkbox" onclick="event.stopPropagation();">
                        
                        <?php if ($emp['foto_url']): ?>
                            <img src="<?= htmlspecialchars($emp['foto_url']) ?>" class="employee-photo" alt="">
                        <?php else: ?>
                            <div class="employee-photo" style="background: #cbd5e0; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                <?= strtoupper(substr($emp['naam'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="employee-info">
                            <div class="employee-name"><?= htmlspecialchars($emp['naam']) ?></div>
                            <div class="employee-details">
                                <?= htmlspecialchars($emp['functie'] ?? '-') ?> 
                                <?php if ($emp['locatie']): ?>
                                    • <?= htmlspecialchars($emp['locatie']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- RIGHT: Preview & Generate -->
        <div class="card">
            <h2>📋 Preview & Genereren</h2>
            
            <!-- Stats -->
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-number" id="total-count"><?= count($employees) ?></div>
                    <div class="stat-label">Totaal</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="selected-count">0</div>
                    <div class="stat-label">Geselecteerd</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="pages-count">0</div>
                    <div class="stat-label">Pagina's (A4)</div>
                </div>
            </div>
            
            <!-- Info -->
            <div class="info-box">
                <p>
                    📄 <strong>A4 formaat:</strong> 10 badges per pagina<br>
                    📏 <strong>Badge formaat:</strong> 85.6 x 54mm (creditcard)<br>
                    🔖 <strong>Inhoud:</strong> Foto, Naam, Functie, Locatie, QR-code<br>
                    💡 <strong>Let op:</strong> Preview is indicatief, PDF toont volledige layout
                </p>
            </div>
            
            <!-- Badge Options -->
            <div style="background: #f7fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="font-size: 16px; margin-bottom: 12px; color: #2c3e50;">🎨 Template:</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; border: 2px solid #e2e8f0; border-radius: 6px; transition: all 0.2s;" class="template-option">
                        <input type="radio" name="template" value="professional" checked onchange="updateTemplate(this.value)">
                        <div>
                            <div style="font-weight: 600; color: #667eea;">💼 Professional</div>
                            <div style="font-size: 11px; color: #718096;">Paars gradient</div>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; border: 2px solid #e2e8f0; border-radius: 6px; transition: all 0.2s;" class="template-option">
                        <input type="radio" name="template" value="colorful" onchange="updateTemplate(this.value)">
                        <div>
                            <div style="font-weight: 600; color: #f093fb;">🌸 Colorful</div>
                            <div style="font-size: 11px; color: #718096;">Roze voor KDV</div>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; border: 2px solid #e2e8f0; border-radius: 6px; transition: all 0.2s;" class="template-option">
                        <input type="radio" name="template" value="minimalist" onchange="updateTemplate(this.value)">
                        <div>
                            <div style="font-weight: 600; color: #2d3748;">⬜ Minimalist</div>
                            <div style="font-size: 11px; color: #718096;">Zwart/wit clean</div>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; border: 2px solid #e2e8f0; border-radius: 6px; transition: all 0.2s;" class="template-option">
                        <input type="radio" name="template" value="emergency" onchange="updateTemplate(this.value)">
                        <div>
                            <div style="font-weight: 600; color: #dc2626;">🚨 Emergency</div>
                            <div style="font-size: 11px; color: #718096;">Rood voor BHV</div>
                        </div>
                    </label>
                </div>
            </div>
            
            
            <!-- Logo Upload -->
            <div style="background: #f7fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="font-size: 16px; margin-bottom: 12px; color: #2c3e50;">🎨 Logo (Optioneel):</h3>
                <input type="file" id="logo-upload" accept="image/*" style="display: none;" onchange="handleLogoUpload(event)">
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" onclick="document.getElementById('logo-upload').click()" style="padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer;">
                        📁 Selecteer Logo
                    </button>
                    <span id="logo-name" style="font-size: 14px; color: #718096;">Geen logo geselecteerd</span>
                    <button type="button" id="logo-remove" onclick="removeLogo()" style="display: none; padding: 6px 12px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer;">✕</button>
                </div>
                <div id="logo-preview" style="margin-top: 10px; display: none;">
                    <img id="logo-img" style="max-width: 100px; max-height: 100px; border: 1px solid #e2e8f0; border-radius: 4px;">
                </div>
            </div>
            
            <div style="background: #f7fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="font-size: 16px; margin-bottom: 12px; color: #2c3e50;">📱 Code Opties:</h3>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="code-type" value="qr" checked onchange="updateCodeType(this.value)">
                        <span>🔲 Alleen QR-code</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="code-type" value="barcode" onchange="updateCodeType(this.value)">
                        <span>📊 Alleen Barcode (Code128)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="code-type" value="both" onchange="updateCodeType(this.value)">
                        <span>🔲📊 Beide (QR + Barcode)</span>
                    </label>
                </div>
            </div>
            
            <!-- Preview -->
            <h3 style="margin-bottom: 15px; color: #4a5568;">Geselecteerde Employees:</h3>
            <div class="preview-grid" id="preview-grid">
                <div style="grid-column: 1/-1; text-align: center; color: #cbd5e0; padding: 40px;">
                    Geen employees geselecteerd
                </div>
            </div>
            
            <!-- Generate Button -->
            <div class="generate-section" style="display: flex; gap: 10px;">
                <button id="btn-generate" class="btn btn-primary" style="flex: 1;" onclick="generateBadges()" disabled>
                    🎫 Genereer Badges PDF
                </button>
                <button id="btn-generate-png" class="btn btn-primary" style="flex: 1; background: #10b981;" onclick="generateBadgesPNG()" disabled>
                    📱 Exporteer als PNG
                </button>
            </div>
        </div>
    </div>
    
    <!-- Hidden canvas for PNG generation -->
    <canvas id="badge-canvas" style="display: none;"></canvas>
    
    <script>
        let selectedEmployees = new Set();
        const allEmployees = <?= json_encode($employees) ?>;
        let codeType = 'qr'; // Default: QR only
        let selectedTemplate = 'professional'; // Default template
        let logoFile = null;
        let logoDataUrl = null;
        
        // Logo handling
        function handleLogoUpload(event) {
            const file = event.target.files[0];
            if (!file) return;
            if (!file.type.startsWith('image/')) {
                alert('Selecteer een geldig afbeeldingsbestand');
                return;
            }
            logoFile = file;
            const reader = new FileReader();
            reader.onload = function(e) {
                logoDataUrl = e.target.result;
                document.getElementById('logo-name').textContent = file.name;
                document.getElementById('logo-img').src = e.target.result;
                document.getElementById('logo-preview').style.display = 'block';
                document.getElementById('logo-remove').style.display = 'inline-block';
            };
            reader.readAsDataURL(file);
        }
        
        function removeLogo() {
            logoFile = null;
            logoDataUrl = null;
            document.getElementById('logo-upload').value = '';
            document.getElementById('logo-name').textContent = 'Geen logo geselecteerd';
            document.getElementById('logo-preview').style.display = 'none';
            document.getElementById('logo-remove').style.display = 'none';
        }
        
        // Update code type
        function updateCodeType(type) {
            codeType = type;
            console.log('Code type changed to:', type);
        }
        
        // Update template
        function updateTemplate(template) {
            selectedTemplate = template;
            console.log('Template changed to:', template);
            
            // Update visual feedback
            document.querySelectorAll('.template-option').forEach(el => {
                el.style.borderColor = '#e2e8f0';
                el.style.background = 'transparent';
            });
            event.target.closest('.template-option').style.borderColor = '#667eea';
            event.target.closest('.template-option').style.background = '#f0f4ff';
        }
        
        // Update counts
        function updateCounts() {
            const count = selectedEmployees.size;
            document.getElementById('selected-count').textContent = count;
            document.getElementById('pages-count').textContent = Math.ceil(count / 10);
            document.getElementById('btn-generate').disabled = count === 0;
            document.getElementById('btn-generate-png').disabled = count === 0;
            
            updatePreview();
        }
        
        // Toggle employee selection
        function toggleEmployee(element) {
            const id = element.dataset.id;
            const checkbox = element.querySelector('input[type="checkbox"]');
            
            if (selectedEmployees.has(id)) {
                selectedEmployees.delete(id);
                element.classList.remove('selected');
                checkbox.checked = false;
            } else {
                selectedEmployees.add(id);
                element.classList.add('selected');
                checkbox.checked = true;
            }
            
            updateCounts();
        }
        
        // Select all
        function selectAll() {
            document.querySelectorAll('.employee-item').forEach(item => {
                const id = item.dataset.id;
                selectedEmployees.add(id);
                item.classList.add('selected');
                item.querySelector('input[type="checkbox"]').checked = true;
            });
            updateCounts();
        }
        
        // Deselect all
        function deselectAll() {
            selectedEmployees.clear();
            document.querySelectorAll('.employee-item').forEach(item => {
                item.classList.remove('selected');
                item.querySelector('input[type="checkbox"]').checked = false;
            });
            updateCounts();
        }
        
        // Select filtered
        function selectFiltered() {
            document.querySelectorAll('.employee-item').forEach(item => {
                if (item.style.display !== 'none') {
                    const id = item.dataset.id;
                    selectedEmployees.add(id);
                    item.classList.add('selected');
                    item.querySelector('input[type="checkbox"]').checked = true;
                }
            });
            updateCounts();
        }
        
        // Select only BHV employees
        function selectBHV() {
            deselectAll(); // Clear first
            
            let bhvCount = 0;
            document.querySelectorAll('.employee-item').forEach(item => {
                const bhv = item.dataset.bhv;
                if (bhv === 'Ja') {
                    const id = item.dataset.id;
                    selectedEmployees.add(id);
                    item.classList.add('selected');
                    item.querySelector('input[type="checkbox"]').checked = true;
                    bhvCount++;
                }
            });
            
            updateCounts();
            
            if (bhvCount === 0) {
                alert('Geen BHV\'ers gevonden in de lijst');
            } else {
                console.log(`✅ ${bhvCount} BHV'ers geselecteerd`);
            }
        }
        
        // Filter employees
        function filterEmployees() {
            const location = document.getElementById('filter-location').value.toLowerCase();
            const afdeling = document.getElementById('filter-afdeling').value.toLowerCase();
            const search = document.getElementById('search-input').value.toLowerCase();
            
            document.querySelectorAll('.employee-item').forEach(item => {
                const itemLocation = (item.dataset.location || '').toLowerCase();
                const itemAfdeling = (item.dataset.afdeling || '').toLowerCase();
                const itemName = (item.dataset.name || '').toLowerCase();
                
                const matchLocation = !location || itemLocation === location;
                const matchAfdeling = !afdeling || itemAfdeling === afdeling;
                const matchSearch = !search || itemName.includes(search);
                
                item.style.display = (matchLocation && matchAfdeling && matchSearch) ? 'flex' : 'none';
            });
        }
        
        // Update preview
        function updatePreview() {
            const previewGrid = document.getElementById('preview-grid');
            
            if (selectedEmployees.size === 0) {
                previewGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #cbd5e0; padding: 40px;">Geen employees geselecteerd</div>';
                return;
            }
            
            const selectedList = allEmployees.filter(emp => selectedEmployees.has(emp.id.toString()));
            
            previewGrid.innerHTML = selectedList.map(emp => `
                <div class="badge-preview">
                    ${emp.foto_url ? 
                        `<img src="${emp.foto_url}" alt="" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                         <div style="width: 60px; height: 60px; border-radius: 50%; background: #cbd5e0; margin: 0 auto 10px; display: none; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 24px;">${emp.naam.charAt(0).toUpperCase()}</div>` : 
                        `<div style="width: 60px; height: 60px; border-radius: 50%; background: #cbd5e0; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 24px;">${emp.naam.charAt(0).toUpperCase()}</div>`
                    }
                    <div class="name">${emp.naam}</div>
                    <div class="function">${emp.functie || '-'}</div>
                    ${emp.locatie ? `<div style="font-size: 11px; color: #999; margin-top: 3px;">${emp.locatie}</div>` : ''}
                    <div style="font-size: 10px; color: #cbd5e0; margin-top: 5px;">📱 QR-code</div>
                </div>
            `).join('');
        }
        
        // Generate badges
        async function generateBadges() {
            if (selectedEmployees.size === 0) {
                alert('Selecteer eerst employees!');
                return;
            }
            
            // DEBUG: Log logo status
            console.log('🎨 Logo check:');
            console.log('  - logoFile:', logoFile);
            console.log('  - logoDataUrl:', logoDataUrl ? 'EXISTS (length: ' + logoDataUrl.length + ')' : 'NULL');
            console.log('  - Will send logo:', !!logoDataUrl);
            
            const btn = document.getElementById('btn-generate');
            btn.disabled = true;
            btn.textContent = '⏳ Genereren...';
            
            try {
                const response = await fetch('/api/generate_badges.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        employee_ids: Array.from(selectedEmployees),
                        code_type: codeType,
                        template: selectedTemplate,
                        logo: logoDataUrl  // Send logo as base64
                    })
                });
                
                if (!response.ok) {
                    throw new Error('Server error');
                }
                
                // Download PDF
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `badges_${new Date().getTime()}.pdf`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                btn.textContent = '✅ PDF Gedownload!';
            
            } catch (error) {
                console.error('Generate error:', error);
                alert('Fout bij genereren PDF: ' + error.message);
                btn.textContent = '🎫 Genereer Badges PDF';
                btn.disabled = false;
            }
        }

        async function generateBadgesPNG() {
            if (selectedEmployees.size === 0) {
                alert('Selecteer eerst employees!');
                return;
            }

            const btn = document.getElementById('btn-generate-png');
            btn.disabled = true;
            btn.textContent = '⏳ Genereren...';

            try {
                const zip = new JSZip();
                const canvas = document.getElementById('badge-canvas');
                const ctx = canvas.getContext('2d');
                canvas.width = 600;
                canvas.height = 400;

                // Template colors
                const templateColors = {
                    professional: { header: [51, 102, 204], bg: [230, 236, 255] },
                    colorful: { header: [252, 112, 156], bg: [255, 237, 241] },
                    minimalist: { header: [40, 40, 40], bg: [255, 255, 255] },
                    emergency: { header: [220, 38, 38], bg: [255, 236, 238] }
                };

                const selectedList = allEmployees.filter(emp => selectedEmployees.has(emp.id.toString()));

                for (const employee of selectedList) {
                    // Clear canvas
                    ctx.clearRect(0, 0, 600, 400);

                    const colors = templateColors[selectedTemplate] || templateColors.professional;

                    // Background
                    ctx.fillStyle = `rgb(${colors.bg[0]}, ${colors.bg[1]}, ${colors.bg[2]})`;
                    ctx.fillRect(0, 0, 600, 400);

                    // Header bar (0,0 to 600,60)
                    ctx.fillStyle = `rgb(${colors.header[0]}, ${colors.header[1]}, ${colors.header[2]})`;
                    ctx.fillRect(0, 0, 600, 60);

                    // Logo in header (if uploaded)
                    if (logoDataUrl) {
                        const logoImg = new Image();
                        logoImg.src = logoDataUrl;
                        await new Promise(resolve => {
                            logoImg.onload = resolve;
                        });
                        ctx.drawImage(logoImg, 10, 10, 40, 40);
                    }

                    // Employee name in header (centered white)
                    const name = (employee.voornaam + ' ' + employee.achternaam).trim() || employee.naam || 'Onbekend';
                    ctx.fillStyle = 'white';
                    ctx.font = 'bold 20px Arial';
                    ctx.textAlign = 'center';
                    ctx.fillText(name, 300, 38);

                    // Footer bar (0,340 to 600,400) - slightly darker
                    if (codeType === 'barcode' || codeType === 'both') {
                        const footerR = Math.max(0, colors.header[0] - 30);
                        const footerG = Math.max(0, colors.header[1] - 30);
                        const footerB = Math.max(0, colors.header[2] - 30);
                        ctx.fillStyle = `rgb(${footerR}, ${footerG}, ${footerB})`;
                        ctx.fillRect(0, 340, 600, 60);
                    }

                    // Profile photo (circular 120x120px, centered in left column at y:80 start)
                    ctx.save();
                    ctx.beginPath();
                    ctx.arc(100, 130, 60, 0, 2 * Math.PI);
                    ctx.clip();

                    if (employee.foto_url) {
                        const img = new Image();
                        await new Promise((resolve, reject) => {
                            img.crossOrigin = 'anonymous';
                            img.onload = () => {
                                ctx.drawImage(img, 40, 70, 120, 120);
                                resolve();
                            };
                            img.onerror = () => {
                                // Fallback to initials
                                ctx.restore();
                                drawInitials(employee);
                                resolve();
                            };
                            img.src = employee.foto_url;
                        });
                        ctx.restore();
                    } else {
                        ctx.restore();
                        drawInitials(employee);
                    }

                    // Text fields (middle column, y:90,120,150)
                    ctx.fillStyle = 'black';
                    ctx.textAlign = 'left';
                    
                    // Functie
                    ctx.font = 'bold 16px Arial';
                    ctx.fillText('Functie: ', 210, 90);
                    ctx.font = '16px Arial';
                    ctx.fillText(employee.functie || 'Onbekend', 210 + ctx.measureText('Functie: ').width, 90);
                    
                    // Afdeling
                    ctx.font = 'bold 16px Arial';
                    ctx.fillText('Afdeling: ', 210, 120);
                    ctx.font = '16px Arial';
                    ctx.fillText(employee.afdeling || 'Onbekend', 210 + ctx.measureText('Afdeling: ').width, 120);
                    
                    // Locatie
                    ctx.font = 'bold 16px Arial';
                    ctx.fillText('Locatie: ', 210, 150);
                    ctx.font = '16px Arial';
                    ctx.fillText(employee.locatie || 'Onbekend', 210 + ctx.measureText('Locatie: ').width, 150);

                    // Codes
                    const employeeId = employee.employee_id || employee.id;
                    
                    if (codeType === 'qr' || codeType === 'both') {
                        // QR code (120x120 at 450,80)
                        const qrDiv = document.createElement('div');
                        qrDiv.style.display = 'none';
                        document.body.appendChild(qrDiv);
                        
                        new QRCode(qrDiv, {
                            text: employeeId,
                            width: 120,
                            height: 120
                        });
                        
                        // Wait for QR generation
                        await new Promise(resolve => setTimeout(resolve, 100));
                        
                        // Get the QR image
                        const qrImg = qrDiv.querySelector('img') || qrDiv.querySelector('canvas');
                        if (qrImg) {
                            ctx.drawImage(qrImg, 460, 210, 120, 120);
                        }
                        
                        // Remove temp div
                        document.body.removeChild(qrDiv);
                    }
                    
                    if (codeType === 'barcode' || codeType === 'both') {
                        // Barcode in footer (centered, height 50px)
                        const tempCanvas = document.createElement('canvas');
                        tempCanvas.width = 300;
                        tempCanvas.height = 50;
                        JsBarcode(tempCanvas, employeeId, {
                            format: 'CODE128',
                            width: 2,
                            height: 40,
                            displayValue: true,
                            fontSize: 12
                        });
                        
                        ctx.drawImage(tempCanvas, 150, 340, 300, 50);
                    }

                    // BHV badge (top-right of header, x:560, y:15, radius:20)
                    if (employee.bhv && employee.bhv.toLowerCase() === 'ja') {
                        ctx.fillStyle = 'red';
                        ctx.beginPath();
                        ctx.arc(560, 15, 20, 0, 2 * Math.PI);
                        ctx.fill();
                        ctx.fillStyle = 'white';
                        ctx.font = 'bold 12px Arial';
                        ctx.textAlign = 'center';
                        ctx.fillText('BHV', 560, 20);
                    }

                    // Convert to blob
                    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
                    const fileName = `badge_${name.replace(/[^a-zA-Z0-9_-]/g, '_')}.png`;
                    zip.file(fileName, blob);
                }

                // Generate ZIP
                const zipBlob = await zip.generateAsync({ type: 'blob' });
                const url = URL.createObjectURL(zipBlob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `badges_png_${new Date().getTime()}.zip`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                btn.textContent = '✅ ZIP Gedownload!';

                setTimeout(() => {
                    btn.textContent = '📱 Exporteer als PNG';
                    btn.disabled = false;
                }, 3000);
            } catch (error) {
                console.error('Generate PNG error:', error);
                alert('Fout bij genereren PNG: ' + error.message);
                btn.textContent = '📱 Exporteer als PNG';
                btn.disabled = false;
            }
        }

        function drawInitials(employee) {
            const ctx = document.getElementById('badge-canvas').getContext('2d');
            ctx.fillStyle = '#e2e8f0';
            ctx.beginPath();
            ctx.arc(100, 130, 60, 0, 2 * Math.PI);
            ctx.fill();
            ctx.fillStyle = '#4a5568';
            ctx.font = 'bold 36px Arial';
            ctx.textAlign = 'center';
            const initials = ((employee.voornaam || '')[0] || '') + ((employee.achternaam || '')[0] || '');
            ctx.fillText(initials.toUpperCase() || '?', 100, 140);
        }

        // Event listeners
        document.getElementById('filter-location').addEventListener('change', filterEmployees);
        document.getElementById('filter-afdeling').addEventListener('change', filterEmployees);
        document.getElementById('search-input').addEventListener('input', filterEmployees);
        
        // Initialize
        updateCounts();
    </script>
</body>
</html>
