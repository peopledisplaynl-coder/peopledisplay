/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: overzicht.js
 * VERSIE:       2.0 - FIXED met fallbacks
 * UPLOAD NAAR:  /overzicht.js (ROOT, OVERSCHRIJF!)
 * 
 * FIXES:
 * - ✅ API endpoint: employees_api.php
 * - ✅ Data parsing: data.employees
 * - ✅ Field names: Voornaam/Achternaam (uppercase)
 * - ✅ User features support
 * - ✅ Fallback: show all columns if no preferences
 * - ✅ Safety: default columns if nothing visible
 * - ✅ Console logging for debugging
 * ═══════════════════════════════════════════════════════════════════
 */
// === overzicht.js ===
"use strict";

(function() {
  const BASE_PATH = window.__labee_base_path || '';
  const API_URL = BASE_PATH + '/admin/api/employees_api.php'; // ✅ FIXED: Was proxy.php
  
  let allEmployees = [];
  let filteredEmployees = [];
  let autoRefreshInterval = null;
  let orderedLocations = []; // 🆕 Voor gesorteerde locaties uit database
  let userVisibleFields = null; // 🆕 User feature preferences
  
  // === UTILITY FUNCTIONS ===
  
  function normalize(str) {
    return (str || "").toString().trim().toLowerCase();
  }
  
  function formatTime(ts) {
    if (!ts) return "";
    try {
      const d = new Date(ts);
      return d.toLocaleTimeString("nl-NL", {hour: "2-digit", minute: "2-digit"});
    } catch(e) {
      return ts;
    }
  }
  
  function getInitials(name) {
    if (!name) return "?";
    return name.split(' ')
      .map(n => n.charAt(0).toUpperCase())
      .slice(0, 2)
      .join('');
  }
  
  // === DATA LOADING ===
  
  // 🆕 Load ordered locations from database
  async function loadOrderedLocations() {
    try {
      const response = await fetch(BASE_PATH + '/admin/api/get_locations_ordered.php');
      if (response.ok) {
        const data = await response.json();
        if (data.success && data.locations) {
          orderedLocations = data.locations;
          console.log('✅ Loaded location order from database:', orderedLocations.length, 'locations');
          return true;
        }
      }
    } catch (error) {
      console.warn('⚠️ Could not load location order, will sort alphabetically');
    }
    return false;
  }
  
  // 🆕 Load user features for column visibility
  async function loadUserFeatures() {
    try {
      const response = await fetch(BASE_PATH + '/admin/api/user_features.php');
      if (response.ok) {
        const data = await response.json();
        if (data && !data.error) {
          userVisibleFields = data.visibleFields || null;
          console.log('✅ User visible fields loaded:', userVisibleFields);
          return true;
        }
      }
    } catch (error) {
      console.warn('⚠️ Could not load user features, showing all columns');
    }
    return false;
  }
  
  // 🆕 Check if field should be visible
  function isFieldVisible(fieldName) {
    // ✅ DEFAULT TO TRUE if no preferences loaded
    if (!userVisibleFields || !Array.isArray(userVisibleFields) || userVisibleFields.length === 0) {
      console.log('⚠️ No user preferences, showing all columns');
      return true; // Show all if no preferences
    }
    // Check if field is in visible list
    const visible = userVisibleFields.includes(fieldName);
    console.log(`🔍 Field "${fieldName}": ${visible ? 'visible' : 'hidden'}`);
    return visible;
  }
  
  // 🆕 Get location ID for matching (handles "05: Name" format)
  function getLocationID(locStr) {
    if (!locStr) return "";
    const match = locStr.match(/^(\d+)/);
    if (match) return match[1];
    return normalize(locStr);
  }
  
  // 🆕 Sort locations based on database order
  function sortLocationsByOrder(locations) {
    if (orderedLocations.length === 0) {
      // No order from database, sort alphabetically
      return locations.sort();
    }
    
    // Create order map
    const orderMap = new Map(
      orderedLocations.map((loc, idx) => [getLocationID(loc), idx])
    );
    
    return locations.sort((a, b) => {
      const idA = getLocationID(a);
      const idB = getLocationID(b);
      const orderA = orderMap.has(idA) ? orderMap.get(idA) : 9999;
      const orderB = orderMap.has(idB) ? orderMap.get(idB) : 9999;
      
      if (orderA !== orderB) return orderA - orderB;
      // If both not in order map, sort alphabetically
      return a.localeCompare(b);
    });
  }
  
  async function loadEmployees() {
    try {
      console.log('🔄 Loading employees...');
      
      const response = await fetch(API_URL + '?action=getemployees');
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      
      const data = await response.json();
      
      // ✅ FIXED: API returns {success: true, employees: [...]}
      const employees = data.employees || [];
      
      console.log('✅ Loaded employees:', employees.length);
      
      // Filter: alleen mensen die NIET "UIT" zijn
      allEmployees = employees.filter(emp => {
        const status = normalize(emp.Status || '');
        return status !== 'uit' && status !== 'out' && status !== '';
      });
      
      console.log('📊 Aanwezig:', allEmployees.length);
      
      // Update tijd
      const now = new Date();
      document.getElementById('last-refresh').textContent = 
        now.toLocaleTimeString('nl-NL', {hour: '2-digit', minute: '2-digit'});
      
      // Populate filters
      populateFilters();
      
      // Apply filters and render
      applyFilters();
      
    } catch (error) {
      console.error('❌ Load error:', error);
      showError('Fout bij laden van gegevens: ' + error.message);
    }
  }
  
  // === POPULATE FILTERS ===
  
  function populateFilters() {
    // Locaties
    const locaties = [...new Set(allEmployees.map(e => e.Locatie).filter(Boolean))];
    locaties.sort();
    
    const locatieSelect = document.getElementById('filter-locatie');
    locatieSelect.innerHTML = '<option value="">📍 Locatie (alles)</option>';
    locaties.forEach(loc => {
      const option = document.createElement('option');
      option.value = loc;
      option.textContent = loc;
      locatieSelect.appendChild(option);
    });
    
    // Afdelingen
    const afdelingen = [...new Set(allEmployees.map(e => e.Afdeling).filter(Boolean))];
    afdelingen.sort();
    
    const afdelingSelect = document.getElementById('filter-afdeling');
    afdelingSelect.innerHTML = '<option value="">🏢 Afdeling (alles)</option>';
    afdelingen.forEach(afd => {
      const option = document.createElement('option');
      option.value = afd;
      option.textContent = afd;
      afdelingSelect.appendChild(option);
    });
  }
  
  // === APPLY FILTERS ===
  
  function applyFilters() {
    const searchTerm = normalize(document.getElementById('search-input').value);
    const statusFilter = document.getElementById('filter-status').value;
    const bhvFilter = document.getElementById('filter-bhv').value;
    const locatieFilter = document.getElementById('filter-locatie').value;
    const afdelingFilter = document.getElementById('filter-afdeling').value;
    
    filteredEmployees = allEmployees.filter(emp => {
      // Naam filter
      if (searchTerm && !normalize(emp.Naam || '').includes(searchTerm)) {
        return false;
      }
      
      // Status filter
      if (statusFilter && normalize(emp.Status) !== normalize(statusFilter)) {
        return false;
      }
      
      // BHV filter
      if (bhvFilter) {
        const isBHV = normalize(emp.BHV || '') === 'ja';
        if (bhvFilter === 'ja' && !isBHV) return false;
        if (bhvFilter === 'nee' && isBHV) return false;
      }
      
      // Locatie filter
      if (locatieFilter && emp.Locatie !== locatieFilter) {
        return false;
      }
      
      // Afdeling filter
      if (afdelingFilter && emp.Afdeling !== afdelingFilter) {
        return false;
      }
      
      return true;
    });
    
    console.log('🔍 Filtered:', filteredEmployees.length, 'van', allEmployees.length);
    
    renderOverview();
    updateBadges();
  }
  
  // === RENDER OVERVIEW ===
  
  function renderOverview() {
    const container = document.getElementById('overview-groups');
    const loadingMsg = document.getElementById('loading-message');
    const noDataMsg = document.getElementById('no-data-message');
    const contentDiv = document.getElementById('overview-content');
    
    // Hide loading
    loadingMsg.style.display = 'none';
    
    if (filteredEmployees.length === 0) {
      contentDiv.style.display = 'none';
      noDataMsg.style.display = 'block';
      return;
    }
    
    // Show content
    noDataMsg.style.display = 'none';
    contentDiv.style.display = 'block';
    
    // Groepeer per locatie
    const groupedByLocation = {};
    
    filteredEmployees.forEach(emp => {
      const loc = emp.Locatie || 'Onbekend';
      if (!groupedByLocation[loc]) {
        groupedByLocation[loc] = [];
      }
      groupedByLocation[loc].push(emp);
    });
    
    // 🆕 Sorteer locaties op basis van database volgorde
    const sortedLocations = sortLocationsByOrder(Object.keys(groupedByLocation));
    
    // 🆕 Define visible columns based on user features
    const columns = [
      { name: 'Foto', field: 'Foto', class: 'col-photo', visible: isFieldVisible('Foto') },
      { name: 'Voornaam', field: 'Voornaam', class: 'col-voornaam', visible: isFieldVisible('Voornaam') },
      { name: 'Achternaam', field: 'Achternaam', class: 'col-achternaam', visible: isFieldVisible('Achternaam') },
      { name: 'Functie', field: 'Functie', class: 'col-functie', visible: isFieldVisible('Functie') },
      { name: 'Afdeling', field: 'Afdeling', class: 'col-afdeling', visible: isFieldVisible('Afdeling') },
      { name: 'Locatie', field: 'Locatie', class: 'col-locatie', visible: isFieldVisible('Locatie') },
      { name: 'Status', field: 'Status', class: 'col-status', visible: isFieldVisible('Status') },
      { name: 'BHV', field: 'BHV', class: 'col-bhv', visible: isFieldVisible('BHV') },
      { name: 'Tijd', field: 'Tijd', class: 'col-tijd', visible: isFieldVisible('Tijdstip') }
    ];
    
    // Filter visible columns
    let visibleColumns = columns.filter(col => col.visible);
    
    console.log('👁️ Visible columns:', visibleColumns.length, '/', columns.length);
    console.log('📋 Columns:', visibleColumns.map(c => c.name).join(', '));
    
    // ✅ SAFETY: If no columns visible, show basic set
    if (visibleColumns.length === 0) {
      console.warn('⚠️ NO COLUMNS VISIBLE! Using defaults.');
      visibleColumns = [
        { name: 'Foto', field: 'Foto', class: 'col-photo', visible: true },
        { name: 'Voornaam', field: 'Voornaam', class: 'col-voornaam', visible: true },
        { name: 'Achternaam', field: 'Achternaam', class: 'col-achternaam', visible: true },
        { name: 'Status', field: 'Status', class: 'col-status', visible: true }
      ];
    }
    
    // Render groups
    let html = '';
    
    sortedLocations.forEach(location => {
      const employees = groupedByLocation[location];
      
      // Sort by name (use Voornaam/Achternaam if available, else Naam)
      employees.sort((a, b) => {
        const nameA = a.Voornaam ? `${a.Voornaam} ${a.Achternaam || ''}` : (a.Naam || '');
        const nameB = b.Voornaam ? `${b.Voornaam} ${b.Achternaam || ''}` : (b.Naam || '');
        return nameA.localeCompare(nameB);
      });
      
      html += `
        <div class="location-group">
          <div class="location-header">
            <span>📍 ${location}</span>
            <span class="location-count">${employees.length}</span>
          </div>
          <table class="overview-table">
            <thead>
              <tr>
      `;
      
      // 🆕 Render only visible column headers
      visibleColumns.forEach(col => {
        html += `<th class="${col.class}">${col.name}</th>`;
      });
      
      html += `
              </tr>
            </thead>
            <tbody>
      `;
      
      // Render employee rows
      employees.forEach(emp => {
        const voornaam = emp.Voornaam || ''; // ✅ FIXED: Uppercase V
        const achternaam = emp.Achternaam || ''; // ✅ FIXED: Uppercase A
        const naam = voornaam || achternaam ? `${voornaam} ${achternaam}`.trim() : (emp.Naam || 'Onbekend');
        const functie = emp.Functie || '-';
        const afdeling = emp.Afdeling || '-';
        const locatie = emp.Locatie || '-';
        const status = emp.Status || 'IN';
        const tijd = formatTime(emp.Tijdstip);
        const isBHV = normalize(emp.BHV || '') === 'ja';
        const foto = emp.FotoURL || '';
        
        // Status badge class
        let statusClass = 'in';
        const statusNorm = normalize(status);
        if (statusNorm === 'pauze') statusClass = 'pauze';
        else if (statusNorm === 'vakantie') statusClass = 'vakantie';
        else if (statusNorm === 'thuiswerken') statusClass = 'thuiswerken';
        
        html += '<tr>';
        
        // 🆕 Render only visible cells
        visibleColumns.forEach(col => {
          if (col.field === 'Foto') {
            let fotoHTML = '';
            if (foto) {
              fotoHTML = `<img src="${foto}" alt="${naam}" class="table-photo" 
                           onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                          <div class="table-photo-placeholder" style="display:none;">${getInitials(naam)}</div>`;
            } else {
              fotoHTML = `<div class="table-photo-placeholder">${getInitials(naam)}</div>`;
            }
            html += `<td class="${col.class}">${fotoHTML}</td>`;
          }
          else if (col.field === 'Voornaam') {
            html += `<td class="${col.class}">${voornaam || '-'}</td>`;
          }
          else if (col.field === 'Achternaam') {
            html += `<td class="${col.class}">${achternaam || '-'}</td>`;
          }
          else if (col.field === 'Functie') {
            html += `<td class="${col.class}">${functie}</td>`;
          }
          else if (col.field === 'Afdeling') {
            html += `<td class="${col.class}">${afdeling}</td>`;
          }
          else if (col.field === 'Locatie') {
            html += `<td class="${col.class}">${locatie}</td>`;
          }
          else if (col.field === 'Status') {
            html += `<td class="${col.class}"><span class="status-badge ${statusClass}">${status.toUpperCase()}</span></td>`;
          }
          else if (col.field === 'BHV') {
            html += `<td class="${col.class}">${isBHV ? '<span class="bhv-badge">🚨 BHV</span>' : '-'}</td>`;
          }
          else if (col.field === 'Tijd') {
            html += `<td class="${col.class}">${tijd}</td>`;
          }
        });
        
        html += '</tr>';
      });
      
      html += `
            </tbody>
          </table>
        </div>
      `;
    });
    
    container.innerHTML = html;
  }
  
  // === UPDATE BADGES ===
  
  function updateBadges() {
    const total = filteredEmployees.length;
    const countIN = filteredEmployees.filter(e => normalize(e.Status) === 'in').length;
    const countBHV = filteredEmployees.filter(e => 
      normalize(e.Status) === 'in' && normalize(e.BHV || '') === 'ja'
    ).length;
    
    document.getElementById('count-total').textContent = `Totaal: ${total}`;
    document.getElementById('count-in').textContent = `IN: ${countIN}`;
    document.getElementById('count-bhv').textContent = `BHV: ${countBHV}`;
  }
  
  // === ERROR HANDLING ===
  
  function showError(message) {
    const loadingMsg = document.getElementById('loading-message');
    const noDataMsg = document.getElementById('no-data-message');
    const contentDiv = document.getElementById('overview-content');
    
    loadingMsg.style.display = 'none';
    contentDiv.style.display = 'none';
    noDataMsg.style.display = 'block';
    noDataMsg.innerHTML = `<p>❌ ${message}</p>`;
  }
  
  // === AUTO REFRESH ===
  
  function toggleAutoRefresh() {
    const checkbox = document.getElementById('auto-refresh-checkbox');
    
    if (checkbox.checked) {
      console.log('✅ Auto-refresh enabled (30s)');
      autoRefreshInterval = setInterval(() => {
        console.log('🔄 Auto-refresh...');
        loadEmployees();
      }, 30000); // 30 seconden
    } else {
      console.log('❌ Auto-refresh disabled');
      if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
      }
    }
  }
  
  // === EVENT LISTENERS ===
  
  function initEventListeners() {
    // Refresh button
    document.getElementById('refresh-btn').addEventListener('click', () => {
      loadEmployees();
    });
    
    // Search input
    document.getElementById('search-input').addEventListener('input', () => {
      applyFilters();
    });
    
    // Filter selects
    document.getElementById('filter-status').addEventListener('change', () => {
      applyFilters();
    });
    
    document.getElementById('filter-bhv').addEventListener('change', () => {
      applyFilters();
    });
    
    document.getElementById('filter-locatie').addEventListener('change', () => {
      applyFilters();
    });
    
    document.getElementById('filter-afdeling').addEventListener('change', () => {
      applyFilters();
    });
    
    // Auto-refresh checkbox
    document.getElementById('auto-refresh-checkbox').addEventListener('change', () => {
      toggleAutoRefresh();
    });
  }
  
  // === INIT ===
  
  async function init() {
    console.log('📋 Overzicht pagina geïnitialiseerd');
    initEventListeners();
    
    // 🆕 Load settings first
    await loadUserFeatures(); // Load user column preferences
    await loadOrderedLocations(); // Load location order
    await loadEmployees(); // Load and render employees
  }
  
  // Start when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  
})();
