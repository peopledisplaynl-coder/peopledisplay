<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM:  badges_manage.php
 * LOCATIE:       /admin/badges_manage.php
 * BESCHRIJVING:  Badge Generator - Complete systeem voor badge printing
 * VERSIE:        1.0
 * ═══════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../includes/db.php';
require_once 'auth_helper.php';

// Require admin access
requireAdmin();

$pageTitle = "Badge Generator";

// Get all employees
$stmt = $db->query("
    SELECT employee_id, voornaam, achternaam, functie, afdeling, locatie, bhv, foto_url, actief
    FROM employees 
    WHERE actief = 1 
    ORDER BY achternaam, voornaam
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique locations
$stmtLoc = $db->query("SELECT DISTINCT locatie FROM employees WHERE actief = 1 AND locatie IS NOT NULL ORDER BY locatie");
$locations = $stmtLoc->fetchAll(PDO::FETCH_COLUMN);

// Get unique departments
$stmtDept = $db->query("SELECT DISTINCT afdeling FROM employees WHERE actief = 1 AND afdeling IS NOT NULL ORDER BY afdeling");
$departments = $stmtDept->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - PeopleDisplay Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 8px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .back-link {
            display: inline-block;
            color: white;
            text-decoration: none;
            margin-bottom: 15px;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 6px;
            transition: background 0.3s;
        }
        
        .back-link:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .wizard {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .step {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .step:last-child {
            border-bottom: none;
        }
        
        .step-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            margin-right: 15px;
        }
        
        .step-title {
            font-size: 22px;
            font-weight: 600;
            color: #2d3748;
        }
        
        .template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .template-card {
            border: 3px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .template-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }
        
        .template-card.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .template-card input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .template-preview {
            width: 100%;
            height: 160px;
            background: #f7fafc;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
        }
        
        .template-name {
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 5px;
            color: #2d3748;
        }
        
        .template-desc {
            font-size: 14px;
            color: #718096;
        }
        
        .fields-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .field-checkbox {
            display: flex;
            align-items: center;
            padding: 12px;
            background: #f7fafc;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .field-checkbox:hover {
            background: #edf2f7;
        }
        
        .field-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            cursor: pointer;
        }
        
        .field-checkbox label {
            cursor: pointer;
            user-select: none;
            font-size: 15px;
            color: #2d3748;
        }
        
        .filter-options {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .filter-option {
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-option:hover {
            background: #edf2f7;
        }
        
        .filter-option.active {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .filter-option input[type="radio"] {
            margin-right: 10px;
        }
        
        .filter-option label {
            cursor: pointer;
            font-weight: 500;
        }
        
        .filter-details {
            margin-top: 10px;
            padding-left: 30px;
        }
        
        .filter-details select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 15px;
        }
        
        .employee-list {
            max-height: 400px;
            overflow-y: auto;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
        }
        
        .employee-item {
            padding: 10px;
            background: #f7fafc;
            margin-bottom: 8px;
            border-radius: 6px;
            display: flex;
            align-items: center;
        }
        
        .employee-item input[type="checkbox"] {
            margin-right: 12px;
            width: 18px;
            height: 18px;
        }
        
        .preview-area {
            background: #f7fafc;
            border: 2px dashed #cbd5e0;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .preview-placeholder {
            color: #a0aec0;
            font-size: 18px;
        }
        
        .badge-preview {
            width: 320px;
            height: 200px;
            margin: 0 auto;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 14px 32px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .btn-secondary:hover {
            background: #f0f4ff;
        }
        
        .stats-bar {
            display: flex;
            gap: 20px;
            padding: 20px;
            background: #f0f4ff;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .stat {
            flex: 1;
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 14px;
            color: #718096;
            margin-top: 5px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-info {
            background: #e6f7ff;
            border-left: 4px solid #1890ff;
            color: #0050b3;
        }
        
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 40px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-link">← Terug naar Dashboard</a>
            <h1>🎨 Badge Generator</h1>
            <p>Genereer professionele employee badges met QR codes en barcodes</p>
        </div>
        
        <div class="wizard">
            <!-- STEP 1: Template Selection -->
            <div class="step" id="step1">
                <div class="step-header">
                    <div class="step-number">1</div>
                    <div class="step-title">Kies Template</div>
                </div>
                
                <div class="template-grid">
                    <div class="template-card" onclick="selectTemplate('professional')">
                        <input type="radio" name="template" value="professional" id="template-professional">
                        <div class="template-preview">💼</div>
                        <div class="template-name">Professional</div>
                        <div class="template-desc">Modern blauw/wit design voor alle medewerkers</div>
                    </div>
                    
                    <div class="template-card" onclick="selectTemplate('colorful')">
                        <input type="radio" name="template" value="colorful" id="template-colorful">
                        <div class="template-preview">🌈</div>
                        <div class="template-name">Colorful</div>
                        <div class="template-desc">Kinderopvang-vriendelijk met gradient</div>
                    </div>
                    
                    <div class="template-card" onclick="selectTemplate('minimalist')">
                        <input type="radio" name="template" value="minimalist" id="template-minimalist">
                        <div class="template-preview">⚪</div>
                        <div class="template-name">Minimalist</div>
                        <div class="template-desc">Simpel zwart/wit design</div>
                    </div>
                    
                    <div class="template-card" onclick="selectTemplate('emergency')">
                        <input type="radio" name="template" value="emergency" id="template-emergency">
                        <div class="template-preview">⚠️</div>
                        <div class="template-name">Emergency (BHV)</div>
                        <div class="template-desc">Rood accent voor BHV medewerkers</div>
                    </div>
                </div>
            </div>
            
            <!-- STEP 2: Field Selection -->
            <div class="step" id="step2">
                <div class="step-header">
                    <div class="step-number">2</div>
                    <div class="step-title">Selecteer Velden</div>
                </div>
                
                <div class="fields-grid">
                    <div class="field-checkbox">
                        <input type="checkbox" id="field-foto" name="fields[]" value="foto" checked>
                        <label for="field-foto">📷 Foto</label>
                    </div>
                    
                    <div class="field-checkbox">
                        <input type="checkbox" id="field-naam" name="fields[]" value="naam" checked>
                        <label for="field-naam">👤 Naam</label>
                    </div>
                    
                    <div class="field-checkbox">
                        <input type="checkbox" id="field-functie" name="fields[]" value="functie" checked>
                        <label for="field-functie">💼 Functie</label>
                    </div>
                    
                    <div class="field-checkbox">
                        <input type="checkbox" id="field-afdeling" name="fields[]" value="afdeling">
                        <label for="field-afdeling">🏢 Afdeling</label>
                    </div>
                    
                    <div class="field-checkbox">
                        <input type="checkbox" id="field-locatie" name="fields[]" value="locatie">
                        <label for="field-locatie">📍 Locatie</label>
                    </div>
                    
                    <div class="field-checkbox">
                        <input type="checkbox" id="field-bhv" name="fields[]" value="bhv">
                        <label for="field-bhv">⚠️ BHV Status</label>
                    </div>
                    
                    <div class="field-checkbox">
                        <input type="checkbox" id="field-qr" name="fields[]" value="qr_code" checked>
                        <label for="field-qr">📱 QR Code</label>
                    </div>
                    
                    <div class="field-checkbox">
                        <input type="checkbox" id="field-barcode" name="fields[]" value="barcode" checked>
                        <label for="field-barcode">📊 Barcode</label>
                    </div>
                    
                    <div class="field-checkbox">
                        <input type="checkbox" id="field-employee-id" name="fields[]" value="employee_id">
                        <label for="field-employee-id">🔢 Employee ID</label>
                    </div>
                </div>
            </div>
            
            <!-- STEP 3: Filter Employees -->
            <div class="step" id="step3">
                <div class="step-header">
                    <div class="step-number">3</div>
                    <div class="step-title">Filter Medewerkers</div>
                </div>
                
                <div class="filter-options">
                    <div class="filter-option active" onclick="selectFilter('all')">
                        <input type="radio" name="filter" value="all" id="filter-all" checked>
                        <label for="filter-all">👥 Alle medewerkers (<?php echo count($employees); ?>)</label>
                    </div>
                    
                    <div class="filter-option" onclick="selectFilter('location')">
                        <input type="radio" name="filter" value="location" id="filter-location">
                        <label for="filter-location">📍 Per locatie</label>
                        <div class="filter-details" id="location-details" style="display:none;">
                            <select id="location-select" onchange="updateEmployeeCount()">
                                <option value="">Selecteer locatie...</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-option" onclick="selectFilter('department')">
                        <input type="radio" name="filter" value="department" id="filter-department">
                        <label for="filter-department">🏢 Per afdeling</label>
                        <div class="filter-details" id="department-details" style="display:none;">
                            <select id="department-select" onchange="updateEmployeeCount()">
                                <option value="">Selecteer afdeling...</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-option" onclick="selectFilter('bhv')">
                        <input type="radio" name="filter" value="bhv" id="filter-bhv">
                        <label for="filter-bhv">⚠️ Alleen BHV medewerkers</label>
                    </div>
                    
                    <div class="filter-option" onclick="selectFilter('custom')">
                        <input type="radio" name="filter" value="custom" id="filter-custom">
                        <label for="filter-custom">✅ Custom selectie</label>
                        <div class="filter-details" id="custom-details" style="display:none;">
                            <div class="employee-list" id="employee-list">
                                <!-- Populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="stats-bar">
                    <div class="stat">
                        <div class="stat-value" id="selected-count">0</div>
                        <div class="stat-label">Geselecteerd</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value" id="badges-count">0</div>
                        <div class="stat-label">Badges</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value" id="pages-count">0</div>
                        <div class="stat-label">A4 Pagina's</div>
                    </div>
                </div>
            </div>
            
            <!-- STEP 4: Preview -->
            <div class="step" id="step4">
                <div class="step-header">
                    <div class="step-number">4</div>
                    <div class="step-title">Preview</div>
                </div>
                
                <div class="alert alert-info">
                    <span>ℹ️</span>
                    <span>Preview toont de eerste badge. Klik "Genereren" voor de volledige PDF.</span>
                </div>
                
                <div class="preview-area" id="preview-area">
                    <div class="preview-placeholder">
                        Selecteer een template en medewerkers om preview te zien
                    </div>
                </div>
            </div>
            
            <!-- STEP 5: Generate -->
            <div class="step" id="step5">
                <div class="step-header">
                    <div class="step-number">5</div>
                    <div class="step-title">Genereren</div>
                </div>
                
                <div style="padding: 20px; background: #f7fafc; border-radius: 12px;">
                    <h3 style="margin-bottom: 15px;">Print Opties:</h3>
                    
                    <div style="margin-bottom: 15px;">
                        <input type="checkbox" id="option-cutlines" checked>
                        <label for="option-cutlines">Snijlijnen toevoegen</label>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <input type="checkbox" id="option-numbering">
                        <label for="option-numbering">Badge nummering (voor tracking)</label>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label>Formaat:</label>
                        <select id="paper-format" style="margin-left: 10px; padding: 8px;">
                            <option value="a4">A4 (10 badges per pagina)</option>
                            <option value="letter">Letter (8 badges per pagina)</option>
                        </select>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-secondary" onclick="updatePreview()">
                        👁️ Preview Vernieuwen
                    </button>
                    <button class="btn btn-primary" onclick="generatePDF()">
                        📥 Download PDF
                    </button>
                </div>
                
                <div class="loading-spinner" id="loading">
                    <div class="spinner"></div>
                    <p>PDF wordt gegenereerd...</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Employee data from PHP
        const employees = <?php echo json_encode($employees); ?>;
        
        // State
        let selectedTemplate = 'professional';
        let selectedFields = ['foto', 'naam', 'functie', 'qr_code', 'barcode'];
        let selectedFilter = 'all';
        let selectedEmployees = [];
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            selectTemplate('professional');
            updateEmployeeList();
            updateStats();
        });
        
        function selectTemplate(template) {
            selectedTemplate = template;
            
            // Update UI
            document.querySelectorAll('.template-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            const selectedCard = document.querySelector(`#template-${template}`).closest('.template-card');
            selectedCard.classList.add('selected');
            
            document.querySelector(`#template-${template}`).checked = true;
            
            updatePreview();
        }
        
        function selectFilter(filter) {
            selectedFilter = filter;
            
            // Update UI
            document.querySelectorAll('.filter-option').forEach(opt => {
                opt.classList.remove('active');
            });
            
            const selectedOpt = document.querySelector(`#filter-${filter}`).closest('.filter-option');
            selectedOpt.classList.add('active');
            
            document.querySelector(`#filter-${filter}`).checked = true;
            
            // Show/hide details
            document.querySelectorAll('.filter-details').forEach(det => {
                det.style.display = 'none';
            });
            
            if (filter === 'location') {
                document.getElementById('location-details').style.display = 'block';
            } else if (filter === 'department') {
                document.getElementById('department-details').style.display = 'block';
            } else if (filter === 'custom') {
                document.getElementById('custom-details').style.display = 'block';
                populateEmployeeList();
            }
            
            updateEmployeeList();
            updateStats();
        }
        
        function updateEmployeeList() {
            if (selectedFilter === 'all') {
                selectedEmployees = employees;
            } else if (selectedFilter === 'location') {
                const location = document.getElementById('location-select').value;
                selectedEmployees = location ? employees.filter(e => e.locatie === location) : [];
            } else if (selectedFilter === 'department') {
                const department = document.getElementById('department-select').value;
                selectedEmployees = department ? employees.filter(e => e.afdeling === department) : [];
            } else if (selectedFilter === 'bhv') {
                selectedEmployees = employees.filter(e => e.bhv === 'Ja');
            } else if (selectedFilter === 'custom') {
                // Get checked employees
                const checkboxes = document.querySelectorAll('#employee-list input[type="checkbox"]:checked');
                const ids = Array.from(checkboxes).map(cb => cb.value);
                selectedEmployees = employees.filter(e => ids.includes(e.employee_id));
            }
            
            updateStats();
        }
        
        function populateEmployeeList() {
            const list = document.getElementById('employee-list');
            list.innerHTML = '';
            
            employees.forEach(emp => {
                const div = document.createElement('div');
                div.className = 'employee-item';
                div.innerHTML = `
                    <input type="checkbox" 
                           value="${emp.employee_id}" 
                           onchange="updateEmployeeList()"
                           id="emp-${emp.employee_id}">
                    <label for="emp-${emp.employee_id}">
                        ${emp.voornaam} ${emp.achternaam} - ${emp.functie || 'Geen functie'}
                    </label>
                `;
                list.appendChild(div);
            });
        }
        
        function updateEmployeeCount() {
            updateEmployeeList();
        }
        
        function updateStats() {
            const count = selectedEmployees.length;
            const badgesPerPage = 10;
            const pages = Math.ceil(count / badgesPerPage);
            
            document.getElementById('selected-count').textContent = count;
            document.getElementById('badges-count').textContent = count;
            document.getElementById('pages-count').textContent = pages;
        }
        
        function updatePreview() {
            if (selectedEmployees.length === 0) {
                document.getElementById('preview-area').innerHTML = `
                    <div class="preview-placeholder">
                        Selecteer minimaal één medewerker om preview te zien
                    </div>
                `;
                return;
            }
            
            // Get selected fields
            selectedFields = Array.from(document.querySelectorAll('input[name="fields[]"]:checked'))
                .map(cb => cb.value);
            
            // Show preview of first employee
            const employee = selectedEmployees[0];
            
            // Generate preview HTML based on template
            const previewHtml = generateBadgeHTML(employee, selectedTemplate, selectedFields);
            
            document.getElementById('preview-area').innerHTML = previewHtml;
        }
        
        function generateBadgeHTML(employee, template, fields) {
            // This would generate actual HTML based on template
            // For now, showing placeholder
            return `
                <div class="badge-preview" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px;">
                    <div style="text-align: center;">
                        <h2>${employee.voornaam} ${employee.achternaam}</h2>
                        <p>${employee.functie || ''}</p>
                        <div style="margin-top: 20px; font-size: 12px; opacity: 0.8;">
                            Template: ${template}
                        </div>
                    </div>
                </div>
            `;
        }
        
        async function generatePDF() {
            if (selectedEmployees.length === 0) {
                alert('Selecteer minimaal één medewerker!');
                return;
            }
            
            // Show loading
            document.getElementById('loading').style.display = 'block';
            
            // Get options
            const cutlines = document.getElementById('option-cutlines').checked;
            const numbering = document.getElementById('option-numbering').checked;
            const format = document.getElementById('paper-format').value;
            
            // Get selected fields
            selectedFields = Array.from(document.querySelectorAll('input[name="fields[]"]:checked'))
                .map(cb => cb.value);
            
            // Prepare data
            const data = {
                template: selectedTemplate,
                fields: selectedFields,
                employees: selectedEmployees.map(e => e.employee_id),
                options: {
                    cutlines: cutlines,
                    numbering: numbering,
                    format: format
                }
            };
            
            try {
                // Call API to generate PDF
                const response = await fetch('/api/generate_badges.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                if (!response.ok) {
                    throw new Error('PDF generatie gefaald');
                }
                
                // Download PDF
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `badges_${Date.now()}.pdf`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                alert('✅ PDF succesvol gegenereerd!');
                
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Fout bij genereren PDF: ' + error.message);
            } finally {
                document.getElementById('loading').style.display = 'none';
            }
        }
    </script>
</body>
</html>
