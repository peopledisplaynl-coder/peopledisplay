/**
 * ============================================================================
 * APP.JS - DEEL 1 VAN 3
 * ============================================================================
 * BESTANDSNAAM: app.js
 * UPLOAD NAAR:  /app.js (OVERSCHRIJF)
 * INSTRUCTIE:   Voeg ALLE 3 DELEN samen in EXACTE volgorde!
 * ============================================================================
 * 
 * HOE SAMEN TE VOEGEN:
 * 1. Open een nieuwe lege app.js file
 * 2. Kopieer HELE inhoud van app_part1_FINAL.js (ZONDER deze header!)
 * 3. Plak DIRECT daarna HELE inhoud van app_part2_FINAL.js (ZONDER header!)
 * 4. Plak DIRECT daarna HELE inhoud van app_part3_FINAL.js (ZONDER header!)
 * 5. Sla op als app.js
 * 6. Upload naar /app.js
 * 
 * ⚠️ BELANGRIJK: Verwijder DEZE comment header VOOR het samenvoegen!
 * ⚠️ Start met de regel die begint met: (function()...
 * 
 * WIJZIGINGEN IN DIT BESTAND:
 * - 📍 Manual location button toegevoegd in renderEmployees (regel ~1340)
 * - updateStatus functie uitgebreid met tempLocation parameter (regel ~710)
 * - Manual Location Selector module toegevoegd aan einde (regel ~2250)
 * 
 * ============================================================================
 * DEEL 1 BEGINT HIER (regel 1-820):
 * ============================================================================
 */

/**
 * ═══════════════════════════════════════════════════════════════════
 * APP.JS - DEEL 1 VAN 3
 * ═══════════════════════════════════════════════════════════════════
 * INSTALLATIE: Plak DEEL 1 + DEEL 2 + DEEL 3 achter elkaar
 * ═══════════════════════════════════════════════════════════════════
 */
/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: app.js
 * VERSIE:       2.0 - Voornaam/Achternaam support
 * UPLOAD NAAR:  /app.js (ROOT, OVERSCHRIJF!)
 * 
 * FIXES:
 * - ✅ Voornaam/Achternaam fields ophalen van API
 * - ✅ Naam rendering respecteert Voornaam/Achternaam checkboxes
 * - ✅ Legacy "Naam" fallback voor backwards compatibility
 * ═══════════════════════════════════════════════════════════════════
 */
// ============================================================================
// === PeopleDisplay - Main Application Script
// ============================================================================
// BESTANDSNAAM:  app.js
// UPLOAD NAAR:   ROOT (/app.js)
// DATUM FIX:     2024-12-04
// VERSIE:        AUTOREFRESH FIXED
// 
// FIX TOEGEPAST:
// - window.labeeApp export verplaatst naar BUITEN guard
// - Nu altijd beschikbaar, ook na auto-refresh
// - Gebruikt window.__labee_internal als tussenlaag
// 
// WIJZIGINGEN:
// - Regel 1604-1620: Toegevoegd window.__labee_internal export (binnen IIFE)
// - Regel 1625-1633: window.labeeApp export (BUITEN guard - KRITIEK!)
// ============================================================================

"use strict";

// ============================================================================
// === DEEL 1: INITIALISATIE & BASE CONFIGURATION
// ============================================================================

if (window.__labee_app_initialized) {
  console.warn("App already initialized - aborting second load.");
} else {
  window.__labee_app_initialized = true;

// Detecteer BASE_PATH — werkt voor root én submap installs
const BASE_PATH = (function() {
    if (typeof window.PD_BASE_PATH !== 'undefined') return window.PD_BASE_PATH;
    const path = window.location.pathname;
    return path.replace(/\/(index|login|scan|overzicht|frontpage|kiosk_login|visitor_register|visitor_checkin|visitor_checkout|privacy|reset_password|offline)\.php.*$/, '')
               .replace(/\/$/, '');
})();
console.log('BASE_PATH detected:', BASE_PATH);

  (function(){
    window.DATA_API_URL = null;
    const BASE_PHOTO_URL = "images";

    let employees = [];
    let selectedLocations = [];
    let selectedAfdelingen = []; // 🆕 Voor afdeling filtering
    let visibleFields = ["Naam","BHV","Foto","Functie","Afdeling","Locatie","Tijdstip"];
    
    // 🆕 USER FEATURES
    let userVisibleFields = null; // Komt van user_features.php
    let userLocations = null;     // Komt van user_features.php
    let userAfdelingen = null;    // 🆕 Komt van user_features.php
    let userExtraButtons = null;  // Komt van user_features.php
    let showAllLocations = false; // 🆕 Track of ALL geklikt is (toon ook niet-user locaties)
    
    // 🆕 BUTTON CONFIGURATIE (dynamisch geladen)
    
    // 🆕 BUTTON CONFIGURATIE (dynamisch geladen)
    let buttonConfig = {
      button1: { name: 'PAUZE', color: '#ff69b4', enabled: true, ask_until: false },
      button2: { name: 'THUISWERKEN', color: '#9370db', enabled: true, ask_until: false },
      button3: { name: 'VAKANTIE', color: '#9acd32', enabled: true, ask_until: true }
    };
    let buttonConfigLoaded = false;
    
    // 🆕 DATETIME PICKER VARIABLES  
    let currentEmployeeId = null;
    let currentSubStatus = null;
    let currentButtonNumber = null;
    let selectedUntil = null;
    
    // 🆕 Track optimistic updates that should override server data
    const optimisticUpdates = new Map(); // ID -> {Status, Tijdstip, timestamp}

    // Utility helpers
    function normalize(str){ 
      return (str||"").toString().trim().toLowerCase()
        .replace(/:/g, '')       // Verwijder dubbele punten
        .replace(/\s+/g, ' ')    // Normaliseer spaties (meerdere → enkele)
        .trim();                 // Trim opnieuw
    }
    
    // 🆕 Extract location ID from location string
    // "05: Brakken OP" → "05"
    // "100: Bezoeker" → "100"
    // "Pinokkio" → "pinokkio" (fallback to full name)
    function getLocationID(locStr) {
      if(!locStr) return "";
      const match = locStr.match(/^(\d+)/);
      if(match) return match[1];
      // Als geen nummer, gebruik genormaliseerde naam als fallback
      return normalize(locStr);
    }
    
    // 🆕 Check if employee location matches user location settings
    function matchesUserLocation(employeeLocation, userLocationsList) {
      if(!userLocationsList || userLocationsList.length === 0) return true;
      
      const empID = getLocationID(employeeLocation);
      
      return userLocationsList.some(userLoc => {
        const userID = getLocationID(userLoc);
        return empID === userID;
      });
    }
    
    function formatTime(ts){
      if(!ts) return "";
      try {
        const d = new Date(ts);
        return d.toLocaleTimeString("nl-NL",{hour:"2-digit",minute:"2-digit"});
      } catch(e){ return ts; }
    }

    // remove leftover email DOM elements
    function removeLeftoverEmailDOM(){
      try {
        const ids = ["email-select","email-input","email-btn","email-test-btn","email-send-btn","footer-email"];
        ids.forEach(id => {
          const el = document.getElementById(id);
          if(el && el.parentElement) el.parentElement.removeChild(el);
        });
        const texts = ["verstuur lijst via e-mail","test e-mail","geen e-mailadressen gevonden","kies e-mailadres","laden","tlabee@gmail.com"];
        const nodes = Array.from(document.querySelectorAll("button,div,span,label,select,input"));
        nodes.forEach(n => {
          try {
            const txt = (n.textContent||"").toString().toLowerCase();
            if(texts.some(t => txt.includes(t))){
              if(txt.includes("export") || txt.includes("reset")) return;
              n.remove();
            }
            if(n.className && typeof n.className === "string" && n.className.toLowerCase().includes("email")) n.remove();
          } catch(e){}
        });
        const footer = document.querySelector("footer") || document.querySelector(".footer");
        if(footer){
          Array.from(footer.querySelectorAll("*")).forEach(n=>{
            try {
              if(n.childElementCount === 0 && !(n.textContent||"").trim()) n.remove();
            } catch(e){}
          });
        }
      } catch(e){
        console.warn("removeLeftoverEmailDOM error:", e);
      }
    }

    function headerUIInit(){
      const toggleBtn = document.getElementById("toggle-menu-btn");
      const menu = document.getElementById("building-menu");
      const fields = document.getElementById("field-options"); // Kan null zijn (is verwijderd)

      if(toggleBtn && menu){
        try{ toggleBtn.replaceWith(toggleBtn.cloneNode(true)); }catch(e){}
        const btn = document.getElementById("toggle-menu-btn");
        btn.addEventListener("click",()=>{
          const hidden = (menu.style.display==="none" || menu.style.display==="" );
          if(hidden){
            menu.style.display="flex";
            if(fields) fields.style.display="block"; // Alleen als field-options bestaat
            btn.textContent="VERBERG MENU";
            if(typeof window.__labee_autoHideMenuReset === "function") window.__labee_autoHideMenuReset();
          } else {
            menu.style.display="none";
            if(fields) fields.style.display="none"; // Alleen als field-options bestaat
            btn.textContent="TOON MENU";
          }
        });
      }
      const fsBtn = document.getElementById("fullscreen-btn");
      if(fsBtn){
        try{ fsBtn.replaceWith(fsBtn.cloneNode(true)); }catch(e){}
        const btn = document.getElementById("fullscreen-btn");
        btn.addEventListener("click",()=>{
          if(!document.fullscreenElement){
            document.documentElement.requestFullscreen().catch(err=>console.error(err));
            btn.classList.add("active");
          } else {
            document.exitFullscreen();
            btn.classList.remove("active");
          }
        });
      }
    }

// ============================================================================
// === DEEL 2: USER PROFILE RENDERING
// ============================================================================
    
    // 🆕 Render user profile in header
    function renderUserProfile(userFeatures) {
      if(!userFeatures) return;
      
      const headerLeft = document.querySelector('.header-left');
      if(!headerLeft) return;
      
      // Check if already exists
      let profileDiv = document.getElementById('user-profile');
      if(!profileDiv) {
        profileDiv = document.createElement('div');
        profileDiv.id = 'user-profile';
        profileDiv.className = 'user-profile';
        headerLeft.insertBefore(profileDiv, headerLeft.firstChild);
      }
      
      const displayName = userFeatures.display_name || userFeatures.username || 'User';
      const profilePhoto = userFeatures.profile_photo;
      const username = userFeatures.username;
      const role = userFeatures.role || 'user';
      
      // Build profile HTML
      let html = '';
      
      // Profile photo or initials
      if(profilePhoto) {
        html += `<img src="${BASE_PATH}/${profilePhoto}" alt="${displayName}" class="user-profile-photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">`;
        html += `<div class="user-profile-photo placeholder" style="display:none;">${displayName.charAt(0).toUpperCase()}</div>`;
      } else {
        const initials = displayName.split(' ').map(n => n.charAt(0).toUpperCase()).slice(0, 2).join('');
        html += `<div class="user-profile-photo placeholder">${initials}</div>`;
      }
      
      // Name (hidden on mobile)
      html += `<span class="user-profile-name" style="display:none;">${displayName}</span>`;
      
      // Dropdown menu
      html += `
        <div class="user-dropdown" id="user-dropdown">
          <div class="user-dropdown-header">
            <strong>${displayName}</strong>
            <small>@${username} • ${role}</small>
          </div>
          <a href="${BASE_PATH}/user/profile.php" class="user-dropdown-item">
            👤 Mijn Profiel
          </a>
          <a href="${BASE_PATH}/frontpage.php" class="user-dropdown-item">
            🏠 Menu
          </a>
          <button class="user-dropdown-item logout" id="logout-btn">
            🚪 Uitloggen
          </button>
        </div>
      `;
      
      profileDiv.innerHTML = html;
      
      // Toggle dropdown
      profileDiv.addEventListener('click', (e) => {
        if(e.target.classList.contains('logout')) return; // Let logout button handle itself
        const dropdown = document.getElementById('user-dropdown');
        dropdown.classList.toggle('active');
      });
      
      // Close dropdown when clicking outside
      document.addEventListener('click', (e) => {
        const profile = document.getElementById('user-profile');
        const dropdown = document.getElementById('user-dropdown');
        if(profile && dropdown && !profile.contains(e.target)) {
          dropdown.classList.remove('active');
        }
      });
      
      // Logout handler
      const logoutBtn = document.getElementById('logout-btn');
      if(logoutBtn) {
        logoutBtn.addEventListener('click', () => {
          if(confirm('Weet je zeker dat je wilt uitloggen?')) {
            window.location.href = BASE_PATH + '/logout.php';
          }
        });
      }
      
      console.log('✅ User profile rendered:', displayName);
    }

// ============================================================================
// === DEEL 3: AUTO-HIDE MENU & FILTERS
// ============================================================================

    // Auto-hide building menu (2 minutes inactivity)
    (function(){
      const AUTO_HIDE_MS = 120 * 1000; // 2 minutes
      let __labee_menu_timer = null;

      function autoHideMenuStart(){
        clearTimeout(__labee_menu_timer);
        __labee_menu_timer = setTimeout(() => {
          const menu = document.getElementById("building-menu");
          const fields = document.getElementById("field-options");
          const btn = document.getElementById("toggle-menu-btn");
          if(menu) menu.style.display = "none";
          if(fields) fields.style.display = "none";
          if(btn) btn.textContent = "TOON MENU";
        }, AUTO_HIDE_MS);
      }

      function autoHideMenuReset(){
        clearTimeout(__labee_menu_timer);
        autoHideMenuStart();
      }

      window.__labee_autoHideMenuStart = autoHideMenuStart;
      window.__labee_autoHideMenuReset = autoHideMenuReset;

      if(!window.__labee_autoHide_listeners_attached){
        const onActivity = (e) => {
          const inside = e.target && (e.target.closest && (e.target.closest("#building-menu") || e.target.closest("#field-options") || e.target.closest("#filters") || e.target.closest("footer")));
          if(inside) autoHideMenuReset();
        };
        document.addEventListener("click", onActivity, {passive:true});
        document.addEventListener("input", onActivity, {passive:true});
        document.addEventListener("keydown", onActivity, {passive:true});
        window.__labee_autoHide_listeners_attached = true;
      }
    })();

    // Refresh message
    function updateRefreshMessage(){
      var el = document.querySelector(".footer-refresh");
      if(!el){
        try {
          var footer = document.querySelector("footer") || document.body;
          var infoWrap = document.createElement("div");
          infoWrap.className = "footer-info";
          var span = document.createElement("span");
          span.className = "footer-refresh";
          span.textContent = "Laatst ververst om --:--";
          infoWrap.appendChild(span);
          if(footer.firstChild) footer.insertBefore(infoWrap, footer.firstChild); else footer.appendChild(infoWrap);
          el = span;
        } catch(e){}
      }
      try {
        var now = new Date();
        el.textContent = "Laatst ververst om " + now.toLocaleTimeString("nl-NL",{hour:"2-digit",minute:"2-digit"});
        el.dataset.__labee_last_refresh = now.toISOString();
      } catch(err){
        el.textContent = "Laatst ververst";
      }
    }

    // Setup filters (locatie + afdeling) - MET SORT_ORDER! 🆕
    async function setupFilters(){
      const locSel = document.getElementById("filter-locatie");
      const afdSel = document.getElementById("filter-afdeling");
      if(!locSel || !afdSel) return;
      
      // 🆕 BEWAAR huidige selectie voordat we innerHTML resetten
      const currentLocatie = locSel.value;
      const currentAfdeling = afdSel.value;
      
      try {
        // Fetch sorted filters from API
        const response = await fetch(BASE_PATH + '/api/get_sorted_filters.php');
        const data = await response.json();
        
        if (data.success) {
          // Fill location filter with sorted locations
          locSel.innerHTML = '<option value="">📍 Locatie (alles)</option>';
          data.locations.forEach(loc => {
            const opt = document.createElement("option");
            opt.value = loc;
            opt.textContent = loc;
            locSel.appendChild(opt);
          });
          
          // Fill afdeling filter with sorted afdelingen
          afdSel.innerHTML = '<option value="">🏢 Afdeling (alles)</option>';
          data.afdelingen.forEach(afd => {
            const opt = document.createElement("option");
            opt.value = afd;
            opt.textContent = afd;
            afdSel.appendChild(opt);
          });
          
          // 🆕 HERSTEL geselecteerde waarden
          if(currentLocatie) locSel.value = currentLocatie;
          if(currentAfdeling) afdSel.value = currentAfdeling;
          
          // 🆕 ATTACH EVENT LISTENERS (opnieuw omdat innerHTML listeners verwijdert)
          locSel.addEventListener('change', debounceFilter);
          afdSel.addEventListener('change', debounceFilter);
          
          console.log('✅ Filters loaded with sort_order (selection preserved):', {
            locations: data.locations.length,
            afdelingen: data.afdelingen.length,
            currentLocatie,
            currentAfdeling
          });
        } else {
          console.error('❌ Failed to load sorted filters:', data.error);
          // Fallback to old method
          setupFiltersOld();
        }
      } catch (error) {
        console.error('❌ Error loading sorted filters:', error);
        // Fallback to old method
        setupFiltersOld();
      }
    }
    
    // Fallback: oude methode (alfabetisch uit employees)
    function setupFiltersOld(){
      const locSel = document.getElementById("filter-locatie");
      const afdSel = document.getElementById("filter-afdeling");
      if(!locSel || !afdSel) return;
      
      // 🆕 BEWAAR huidige selectie voordat we innerHTML resetten
      const currentLocatie = locSel.value;
      const currentAfdeling = afdSel.value;
      
      const uniqueLocs = [...new Set(employees.map(e => e.Locatie).filter(Boolean))].sort();
      const uniqueAfds = [...new Set(employees.map(e => e.Afdeling).filter(Boolean))].sort();
      locSel.innerHTML = '<option value="">📍 Locatie (alles)</option>';
      uniqueLocs.forEach(loc => {
        const opt = document.createElement("option");
        opt.value = loc;
        opt.textContent = loc;
        locSel.appendChild(opt);
      });
      afdSel.innerHTML = '<option value="">🏢 Afdeling (alles)</option>';
      uniqueAfds.forEach(afd => {
        const opt = document.createElement("option");
        opt.value = afd;
        opt.textContent = afd;
        afdSel.appendChild(opt);
      });
      
      // 🆕 HERSTEL geselecteerde waarden
      if(currentLocatie) locSel.value = currentLocatie;
      if(currentAfdeling) afdSel.value = currentAfdeling;
      
      // 🆕 ATTACH EVENT LISTENERS (ook in fallback)
      locSel.addEventListener('change', debounceFilter);
      afdSel.addEventListener('change', debounceFilter);
    }

    // 🆕 Load button configuration from API
    function loadButtonConfig() {
      const apiUrl = BASE_PATH + '/api/get_button_config_until.php'; // 🆕 UPDATED voor until support
      
      console.log('📋 Loading button configuration...');
      
      fetch(apiUrl, { cache: 'no-store' })
        .then(r => {
          if (!r.ok) {
            throw new Error('HTTP ' + r.status);
          }
          return r.json();
        })
        .then(data => {
          if (data.success && data.buttons) {
            buttonConfig = data.buttons;
            buttonConfigLoaded = true;
            
            console.log('✅ Button config loaded:', buttonConfig);
            console.log('   - Button 1:', buttonConfig.button1.name);
            console.log('   - Button 2:', buttonConfig.button2.name);
            console.log('   - Button 3:', buttonConfig.button3.name);
            
            // Re-render als employees al geladen zijn
            if (employees.length > 0) {
              console.log('🔄 Re-rendering with new button names...');
              renderEmployees();
            }
          } else {
            console.warn('⚠️ Button config load failed, using defaults');
          }
        })
        .catch(err => {
          console.warn('⚠️ Button config load error:', err.message, '- using defaults');
        });
    }

// ============================================================================
// === DEEL 4: EMPLOYEE DATA FETCHING & RENDERING
// ============================================================================

    // Fetch employees & trigger UI
    async function fetchEmployees(){
      if(!window.DATA_API_URL){
        console.warn("DATA_API_URL not set yet, aborting fetchEmployees");
        return;
      }
      try {
        const r = await fetch(window.DATA_API_URL + "?action=getemployees", { cache: "no-store" });
        if(!r.ok){
          throw new Error("Network response was not ok: " + r.status);
        }
        const data = await r.json();
        
        // ✅ FIXED: Support both old format (array) and new format (object with employees key)
        const rawEmployees = data.employees || (Array.isArray(data) ? data : []);
        
        employees = rawEmployees.map(e => {
          const emp = {
            ID: e.ID ?? e.Id ?? e.id ?? "",
            Naam: (e.Naam ?? e.naam ?? e.name ?? "").toString().trim(),
            Voornaam: (e.Voornaam ?? e.voornaam ?? "").toString().trim(),
            Achternaam: (e.Achternaam ?? e.achternaam ?? "").toString().trim(),
            Status: (e.Status ?? e.status ?? "").toString().trim().toUpperCase(),
            SubStatus: (e.SubStatus ?? e.sub_status ?? e.substatus ?? "").toString().trim().toUpperCase(), // 🆕 SUB-STATUS
            sub_status_until: e.sub_status_until ?? e.SubStatusUntil ?? null, // 🆕 SUB-STATUS TIME
            Locatie: (e.Locatie ?? e.Gebouw ?? e.locatie ?? "").toString().trim(),
            FotoURL: e.FotoURL ?? e.Foto ?? e.foto ?? "",
            Functie: (e.Functie ?? e.functie ?? "").toString().trim(),
            Afdeling: (e.Afdeling ?? e.afdeling ?? "").toString().trim(),
            BHV: (e.BHV ?? e.Bhv ?? e.bhv ?? "").toString().trim(),
            Tijdstip: e.Tijdstip ?? e.tijdstip ?? "",
            // 🆕 MANUAL LOCATION FIELDS
            allow_manual_location_change: e.allow_manual_location_change ?? 0,
            visible_locations: e.visible_locations ?? null
          };
          
          // 🔧 KRITIEK: Check of er een optimistische update is voor deze medewerker
          const optimisticUpdate = optimisticUpdates.get(String(emp.ID));
          if(optimisticUpdate) {
            const age = Date.now() - optimisticUpdate.timestamp;
            
            // Check of server al de nieuwe status heeft
            const serverStatus = emp.Status.toUpperCase();
            const serverSubStatus = (emp.SubStatus || "").toUpperCase();
            const optimisticStatus = optimisticUpdate.Status.toUpperCase();
            const optimisticSubStatus = (optimisticUpdate.SubStatus || "").toUpperCase();
            
            if(serverStatus === optimisticStatus && serverSubStatus === optimisticSubStatus) {
              // ✅ Server heeft de nieuwe status + substatus!
              console.log("✅ Server confirmed status for:", emp.Naam, "→", optimisticStatus, optimisticSubStatus || "");
              optimisticUpdates.delete(String(emp.ID));
            } else if(age < 30000) {
              // Server heeft het nog niet, maar update is nog geldig
              console.log("🔄 Applying optimistic update for:", emp.Naam, "→", optimisticStatus, optimisticSubStatus || "", "(age:", Math.round(age/1000), "s)");
              emp.Status = optimisticUpdate.Status;
              emp.SubStatus = optimisticUpdate.SubStatus;
              emp.Tijdstip = optimisticUpdate.Tijdstip;
            } else {
              // Te oud (>30s) en server heeft het nog steeds niet
              console.log("⏰ Optimistic update expired for:", emp.Naam);
              optimisticUpdates.delete(String(emp.ID));
            }
          }
          
          return emp;
        });
        
        // 🆕 Store for debugging
        window.__allEmployees = employees;
        
        await renderBuildings();
        await setupFilters();
        applyCurrentFilters();
        updateRefreshMessage();
        finalizeUI();
      } catch(err) {
        console.error("fetchEmployees error:", err);
        updateRefreshMessage();
      }
    }

    // Buildings menu render / helpers
    async function renderBuildings(){
      console.log("🏢 renderBuildings() called - selectedLocations:", selectedLocations);
      const menu = document.getElementById("building-menu");
      if(!menu) return;
      
      // 🆕 Haal ALLE locaties uit database (niet alleen uit employee data!)
      let orderedLocs = [];
      try {
        const response = await fetch(BASE_PATH + '/admin/api/get_locations_ordered.php');
        if (response.ok) {
          const data = await response.json();
          if (data.success && data.locations && data.locations.length > 0) {
            console.log("✅ Loaded ALL locations from database:", data.locations);
            orderedLocs = data.locations; // Gebruik database als bron!
          } else {
            console.warn("⚠️ No locations in database, falling back to employee data");
            // Fallback: haal uit employee data
            orderedLocs = [...new Set(
              employees.map(e => (e.Locatie || e.Gebouw || "").trim()).filter(Boolean)
            )].sort();
          }
        } else {
          console.warn("⚠️ Could not fetch locations, using employee data");
          // Fallback: haal uit employee data
          orderedLocs = [...new Set(
            employees.map(e => (e.Locatie || e.Gebouw || "").trim()).filter(Boolean)
          )].sort();
        }
      } catch (error) {
        console.warn("⚠️ Error fetching locations:", error, "- using employee data");
        // Fallback: haal uit employee data
        orderedLocs = [...new Set(
          employees.map(e => (e.Locatie || e.Gebouw || "").trim()).filter(Boolean)
        )].sort();
      }
      
      console.log("🏢 Final locations to render:", orderedLocs);
      menu.innerHTML = "";
      
      const allBtn = document.createElement("button");
      
      // 🆕 ALL knop status: groen als showAllLocations actief is
      allBtn.textContent = showAllLocations ? "ALL ✓" : "ALL";
      allBtn.classList.add("all-clear");
      
      // 🆕 Groene kleur als show all mode actief is
      if(showAllLocations) {
        allBtn.style.backgroundColor = "#28a745";
        allBtn.style.color = "white";
      }
      
      allBtn.addEventListener("click", () => {
        if(showAllLocations) {
          // State 2 → State 1: Terug naar user locations
          showAllLocations = false;
          if(userLocations && Array.isArray(userLocations) && userLocations.length > 0) {
            selectedLocations = userLocations.slice();
            console.log('🔄 ALL clicked - back to user locations:', selectedLocations);
          } else {
            selectedLocations = [];
            console.log('🔄 ALL clicked - no user locations, showing none');
          }
        } else {
          // State 1 → State 2: Toon en selecteer ALLE locaties
          showAllLocations = true;
          selectedLocations = [...orderedLocs];
          console.log('🔄 ALL clicked - showing and selecting all locations:', selectedLocations);
        }
        
        // Re-render menu en update filters
        renderBuildings();
        applyCurrentFilters();
        
        if(typeof window.__labee_autoHideMenuReset === "function") window.__labee_autoHideMenuReset();
      });
      menu.appendChild(allBtn);
      
      const clearBtn = document.createElement("button");
      clearBtn.textContent = "CLEAR";
      clearBtn.classList.add("all-clear");
      clearBtn.addEventListener("click", () => {
        // 🆕 Reset naar user settings (default bij inloggen)
        if(userLocations && Array.isArray(userLocations) && userLocations.length > 0) {
          selectedLocations = userLocations.slice();
          console.log('🔄 Clear clicked - reset to user locations:', selectedLocations);
        } else {
          selectedLocations = [];
          console.log('🔄 Clear clicked - no user locations, showing all');
        }
        
        // Re-render menu en update filters
        renderBuildings();
        applyCurrentFilters();
        
        if(typeof window.__labee_autoHideMenuReset === "function") window.__labee_autoHideMenuReset();
      });
      menu.appendChild(clearBtn);
      
      orderedLocs.forEach(loc => {
        const b = document.createElement("button");
        b.textContent = loc;
        
        // 🆕 Check of deze locatie in user settings staat (ID-based matching)
        const isUserLocation = userLocations && userLocations.length > 0 && 
          userLocations.some(userLoc => getLocationID(userLoc) === getLocationID(loc));
        
        // 🆕 VISIBILITY: Verberg niet-user locaties tenzij showAllLocations actief is
        if(!showAllLocations && userLocations && userLocations.length > 0 && !isUserLocation) {
          b.style.display = 'none'; // Verberg deze button
          b.classList.add('hidden-location'); // Class voor tracking
        } else {
          b.style.display = ''; // Toon button
          b.classList.remove('hidden-location');
        }
        
        if(isUserLocation) {
          b.classList.add("user-location"); // Voor styling
          b.style.border = "3px solid #28b463"; // Groene rand
          console.log("   ✅ User location matched:", loc, "↔", userLocations.find(ul => getLocationID(ul) === getLocationID(loc)));
        }
        
        // Check of deze locatie geselecteerd is
        const isSelected = selectedLocations.some(selLoc => getLocationID(selLoc) === getLocationID(loc));
        
        if(isSelected){
          b.classList.add("selected");
        }
        
        b.addEventListener("click", () => {
          const locID = getLocationID(loc);
          const alreadySelected = selectedLocations.some(selLoc => getLocationID(selLoc) === locID);
          
          if(alreadySelected){
            // Verwijder op basis van ID match
            selectedLocations = selectedLocations.filter(selLoc => getLocationID(selLoc) !== locID);
          } else {
            selectedLocations.push(loc);
          }
          applyCurrentFilters();
          refreshBuildingButtons();
          if(typeof window.__labee_autoHideMenuReset === "function") window.__labee_autoHideMenuReset();
        });
        menu.appendChild(b);
      });
      refreshBuildingButtons();
    }

    function refreshBuildingButtons(){
      const buttons = document.querySelectorAll("#building-menu button");
      buttons.forEach(b => {
        if(b.classList.contains("all-clear")) return;
        
        const btnLocID = getLocationID(b.textContent);
        const isSelected = selectedLocations.some(selLoc => getLocationID(selLoc) === btnLocID);
        
        if(isSelected){
          b.classList.add("selected");
        } else {
          b.classList.remove("selected");
        }
      });
    }

// ============================================================================
// === DEEL 5: STATUS UPDATES & DASHBOARD
// ============================================================================

    // Update status

// ═══════════════════════════════════════════════════════════════════
// EINDE DEEL 1 - GA VERDER MET DEEL 2!
// ═══════════════════════════════════════════════════════════════════
/**
 * ═══════════════════════════════════════════════════════════════════
 * APP.JS - DEEL 2 VAN 3
 * ═══════════════════════════════════════════════════════════════════
 */

    // 🆕 Track pending updates to prevent double-clicking issues
    const pendingUpdates = new Set();
    
    function updateStatus(id, status, subStatus = null, tempLocation = null){
      // Voorkom dubbele updates voor hetzelfde ID
      if(pendingUpdates.has(id)) {
        console.log("⏳ Update already pending for ID:", id);
        return;
      }
      
      pendingUpdates.add(id);
      
      const now = new Date().toISOString();
      
      // 🆕 Check for temp location in memory if not explicitly provided
      if (!tempLocation && typeof getTempLocation === 'function') {
        tempLocation = getTempLocation(id);
      }
      
      // 🔧 KRITIEK: Sla optimistische update op in Map (VOOR lokale update)
      optimisticUpdates.set(String(id), {
        Status: (status||"").toString().trim().toUpperCase(),
        SubStatus: subStatus ? subStatus.toString().trim().toUpperCase() : null, // 🆕 SUB-STATUS
        Tijdstip: now,
        timestamp: Date.now()
      });
      console.log("💾 Stored optimistic update for ID:", id, "→", status, subStatus ? `+ ${subStatus}` : "", tempLocation ? `@ ${tempLocation}` : "");
      
      // 🔧 Optimistic update: Update lokaal DIRECT
      const idx = employees.findIndex(e => String(e.ID) === String(id));
      const prevStatus = idx !== -1 ? employees[idx].Status : null; // save for rollback
      if(idx !== -1){
        employees[idx].Status = (status||"").toString().trim().toUpperCase();
        employees[idx].SubStatus = subStatus ? subStatus.toString().trim().toUpperCase() : null; // 🆕
        employees[idx].Tijdstip = now;
        console.log("✅ Optimistic update:", employees[idx].Naam, "→", status, subStatus ? `+ ${subStatus}` : "", tempLocation ? `@ ${tempLocation}` : "");
      }
      
      // 🆕 Bij checkout: reset naar originele locatie
      let checkoutResetLocation = null;
      if (status.toUpperCase() === 'OUT') {
        console.log(`📍 Checkout detected for ${id}`);
        
        if (typeof getOriginalLocation === 'function') {
          checkoutResetLocation = getOriginalLocation(id);
          console.log(`📍 Original location retrieved: ${checkoutResetLocation || 'NULL'}`);
          
          if (checkoutResetLocation) {
            console.log(`📍 Checkout: resetting ${id} to original location: ${checkoutResetLocation}`);
          } else {
            console.log(`⚠️ No original location found for ${id} - will not reset`);
          }
        } else {
          console.log(`❌ getOriginalLocation function not available`);
        }
        
        if (typeof clearTempLocation === 'function') {
          clearTempLocation(id);
        }
      }
      
      // 🔧 Update UI direct zonder te wachten op server
      applyCurrentFilters();
      
      // 🔧 Disable buttons tijdens update
      const card = document.querySelector(`[data-id="${id}"]`);
      if(card) {
        const buttons = card.querySelectorAll('button');
        buttons.forEach(btn => {
          btn.disabled = true;
          btn.style.opacity = '0.6';
          btn.style.cursor = 'wait';
        });
      }
      
      // Server update met sub_status en temp_location OF reset_location
      let url = window.DATA_API_URL + `?action=updatestatus&id=${encodeURIComponent(id)}&status=${encodeURIComponent(status)}`;
      if (subStatus) {
        url += `&substatus=${encodeURIComponent(subStatus)}`;
      }
      if (tempLocation && status.toUpperCase() === 'IN') {
        url += `&temp_location=${encodeURIComponent(tempLocation)}`;
      }
      if (checkoutResetLocation && status.toUpperCase() === 'OUT') {
        url += `&temp_location=${encodeURIComponent(checkoutResetLocation)}`;
      }
      
      fetch(url, { cache: "no-store" })
        .then(r => r.json().catch(()=>null))
        .then(data => {
          if (data === null) {
            // JSON parse failed — keep optimistic update (status stays as shown)
            console.warn("⚠️ Could not parse server response for ID:", id, "- keeping optimistic update");
          } else if (data && data.success) {
            console.log("✅ Server confirmed update for ID:", id);
          } else {
            // Explicit server rejection: {success: false} — revert status
            console.warn("⚠️ Server rejected update for ID:", id, data?.error || '');
            optimisticUpdates.delete(String(id));
            if (idx !== -1 && prevStatus !== null) {
              employees[idx].Status = prevStatus;
              // Update card CSS directly — no full re-render (keeps card reference valid)
              if (card) {
                card.className = 'employee-card ' + (prevStatus.toUpperCase() === 'IN' ? 'in' : 'out');
              }
            }
          }
          pendingUpdates.delete(id);
          // Re-enable buttons (card stays valid: no applyCurrentFilters inside callbacks)
          if (card) {
            const buttons = card.querySelectorAll('button');
            buttons.forEach(btn => {
              btn.disabled = false;
              btn.style.opacity = '1';
              btn.style.cursor = 'pointer';
            });
          }
          console.log("✅ Status update complete");
        })
        .catch(err => {
          console.error("❌ updateStatus error:", err);
          pendingUpdates.delete(id);
          optimisticUpdates.delete(String(id));
          // Revert optimistic update on network/parse error
          if (idx !== -1 && prevStatus !== null) {
            employees[idx].Status = prevStatus;
            if (card) {
              card.className = 'employee-card ' + (prevStatus.toUpperCase() === 'IN' ? 'in' : 'out');
            }
          }
          // Re-enable buttons
          if (card) {
            const buttons = card.querySelectorAll('button');
            buttons.forEach(btn => {
              btn.disabled = false;
              btn.style.opacity = '1';
              btn.style.cursor = 'pointer';
            });
          }
        });
    }
    
    // 🆕 Export updateStatus to global scope for manual location selector
    window.updateStatus = updateStatus;
    console.log('✅ updateStatus exported to window');

    // Dashboard counters
    function updateDashboard(){
      const inTop = document.getElementById("count-in-top");
      const outTop = document.getElementById("count-out-top");
      const bhvTop = document.getElementById("count-bhv-top");
      

/**
 * ============================================================================
 * EINDE DEEL 1 VAN 3
 * ============================================================================
 * GA VERDER MET: app_part2_FINAL.js
 * ============================================================================
 */
/**
 * ============================================================================
 * APP.JS - DEEL 2 VAN 3
 * ============================================================================
 * INSTRUCTIE: Plak dit DIRECT na deel 1 (ZONDER deze header!)
 * ============================================================================
 * DEEL 2 (regel 821-1640):
 * ============================================================================
 */

      let cntIn = 0, cntOut = 0, cntBhv = 0;
      let cntPauze = 0, cntVakantie = 0, cntThuiswerken = 0;
      
      const selNorm = (selectedLocations || []).map(normalize);
      
      employees.forEach(emp => {
        const empLoc = normalize(emp.Locatie || emp.Gebouw || "");
        const inScope = selNorm.length === 0 || selNorm.includes(empLoc);
        if(!inScope) return;
        
        const s = (emp.Status||"").toString().trim().toUpperCase();
        const sub = (emp.SubStatus||"").toString().trim().toUpperCase(); // 🆕 SUB-STATUS
        
        // 🔧 FIX: Tel hoofdstatus (IN/OUT)
        if(s === "IN") cntIn++;
        else if(s === "OUT") cntOut++;
        
        // 🔧 Bepaal de WERKELIJKE button namen (inclusief custom namen!)
        let btn1Name = buttonConfig.button1.name;
        let btn2Name = buttonConfig.button2.name;
        let btn3Name = buttonConfig.button3.name;
        
        if(window.__userFeatures && window.__userFeatures.customButtonNames) {
          if(window.__userFeatures.customButtonNames.button1) btn1Name = window.__userFeatures.customButtonNames.button1;
          if(window.__userFeatures.customButtonNames.button2) btn2Name = window.__userFeatures.customButtonNames.button2;
          if(window.__userFeatures.customButtonNames.button3) btn3Name = window.__userFeatures.customButtonNames.button3;
        }
        

// ═══════════════════════════════════════════════════════════════════
// 🆕 DATETIME PICKER MODAL - INJECTION
// ═══════════════════════════════════════════════════════════════════
(function injectDatetimeModal() {
  if (document.getElementById('until-modal')) return;
  
  const modalHTML = `<div id="until-modal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.7);z-index:99999;justify-content:center;align-items:center"><div style="background:white;border-radius:16px;padding:32px;max-width:500px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3)"><h2 style="margin:0 0 8px 0;font-size:24px;color:#2d3748"><span id="until-modal-icon">🌴</span> <span id="until-modal-title">Tot wanneer?</span></h2><p style="color:#718096;margin:0 0 24px 0;font-size:14px">Kies tot wanneer deze status geldig is</p><div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:24px"><button onclick="window.selectQuickDate('today_17')" class="quick-btn" style="padding:12px;background:#f7fafc;border:2px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600">🕔 Vandaag 17:00</button><button onclick="window.selectQuickDate('tomorrow_23')" class="quick-btn" style="padding:12px;background:#f7fafc;border:2px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600">🌙 Morgen 23:59</button><button onclick="window.selectQuickDate('next_week')" class="quick-btn" style="padding:12px;background:#f7fafc;border:2px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600">📅 Over 1 week</button><button onclick="window.selectQuickDate('custom')" class="quick-btn" style="padding:12px;background:#f7fafc;border:2px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600">🕐 Aangepast...</button></div><div id="custom-datetime" style="display:none;margin-bottom:24px"><label style="display:block;font-size:14px;font-weight:600;color:#4a5568;margin-bottom:8px">Datum & Tijd:</label><input type="datetime-local" id="until-datetime" style="width:100%;padding:12px;border:2px solid #e2e8f0;border-radius:8px;font-size:16px"></div><div id="until-preview" style="background:#edf2f7;padding:16px;border-radius:8px;margin-bottom:24px;display:none"><div style="font-size:13px;color:#718096;margin-bottom:4px">Preview:</div><div style="font-size:18px;font-weight:700;color:#2d3748" id="until-preview-text"></div></div><div style="display:flex;gap:12px"><button onclick="window.confirmUntilDate()" style="flex:1;padding:14px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer">✅ Bevestigen</button><button onclick="window.closeUntilModal()" style="flex:1;padding:14px;background:#e2e8f0;color:#4a5568;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer">❌ Annuleren</button></div></div></div><style>.quick-btn:hover{background:#e2e8f0!important;border-color:#667eea!important;transform:translateY(-2px)}.quick-btn:active{transform:translateY(0)}</style>`;
  
  document.body.insertAdjacentHTML('beforeend', modalHTML);
  console.log('✅ Until modal injected');
})();

// 🆕 MODAL FUNCTIONS
window.showUntilModal = function(employeeId, subStatus, buttonName, buttonNumber) {
  currentEmployeeId = employeeId;
  currentSubStatus = subStatus;
  currentButtonNumber = buttonNumber;
  selectedUntil = null;
  
  // Icon mapping - gebruik BUTTON NUMBER ipv naam voor reliability
  const buttonIcons = { 
    1: '☕',  // Button 1 (PAUZE)
    2: '🏠',  // Button 2 (THUISWERKEN)
    3: '🌿'   // Button 3 (VAKANTIE)
  };
  document.getElementById('until-modal-icon').textContent = buttonIcons[buttonNumber] || '🎯';
  
  // Title: gebruik custom naam als die er is, anders gewoon "Tot wanneer?"
  const title = buttonName ? `${buttonName} tot wanneer?` : 'Tot wanneer?';
  document.getElementById('until-modal-title').textContent = title;
  
  document.getElementById('custom-datetime').style.display = 'none';
  document.getElementById('until-preview').style.display = 'none';
  document.getElementById('until-modal').style.display = 'flex';
};

window.closeUntilModal = function() {
  document.getElementById('until-modal').style.display = 'none';
  currentEmployeeId = null;
  currentSubStatus = null;
  selectedUntil = null;
};

window.selectQuickDate = function(option) {
  let until;
  switch(option) {
    case 'today_17': until = new Date(); until.setHours(17,0,0,0); break;
    case 'tomorrow_23': until = new Date(); until.setDate(until.getDate()+1); until.setHours(23,59,0,0); break;
    case 'next_week': until = new Date(); until.setDate(until.getDate()+7); until.setHours(23,59,0,0); break;
    case 'custom':
      document.getElementById('custom-datetime').style.display = 'block';
      const dtInput = document.getElementById('until-datetime');
      const minDate = new Date();
      minDate.setMinutes(minDate.getMinutes() - minDate.getTimezoneOffset());
      dtInput.min = minDate.toISOString().slice(0,16);
      const defaultDate = new Date();
      defaultDate.setDate(defaultDate.getDate()+1);
      defaultDate.setHours(17,0,0,0);
      defaultDate.setMinutes(defaultDate.getMinutes() - defaultDate.getTimezoneOffset());
      dtInput.value = defaultDate.toISOString().slice(0,16);
      dtInput.addEventListener('input', function(){ window.updatePreview(new Date(this.value)); });
      return;
  }
  selectedUntil = until;
  window.updatePreview(until);
};

window.updatePreview = function(date) {
  selectedUntil = date;
  const d = String(date.getDate()).padStart(2,'0');
  const m = String(date.getMonth()+1).padStart(2,'0');
  const y = date.getFullYear();
  const h = String(date.getHours()).padStart(2,'0');
  const min = String(date.getMinutes()).padStart(2,'0');
  document.getElementById('until-preview-text').textContent = `Tot ${d}-${m}-${y} ${h}:${min}`;
  document.getElementById('until-preview').style.display = 'block';
};

window.confirmUntilDate = async function() {
  const customInput = document.getElementById('until-datetime');
  if (customInput.value && document.getElementById('custom-datetime').style.display !== 'none') {
    selectedUntil = new Date(customInput.value);
  }
  if (!selectedUntil) { alert('Kies eerst een datum/tijd'); return; }
  
  const y = selectedUntil.getFullYear();
  const m = String(selectedUntil.getMonth()+1).padStart(2,'0');
  const d = String(selectedUntil.getDate()).padStart(2,'0');
  const h = String(selectedUntil.getHours()).padStart(2,'0');
  const min = String(selectedUntil.getMinutes()).padStart(2,'0');
  const mysqlFormat = `${y}-${m}-${d} ${h}:${min}:00`;
  
  // 🔧 FIX v2: Send button NUMBER instead of name
  // This is more reliable and works regardless of custom or original names
  console.log('🕐 Temporal status:', {
    employeeId: currentEmployeeId,
    buttonNumber: currentButtonNumber,
    until: mysqlFormat,
    displayName: currentSubStatus
  });
  
  try {
    const response = await fetch(BASE_PATH + '/api/update_sub_status_until.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `employee_id=${currentEmployeeId}&button_number=${currentButtonNumber}&until=${encodeURIComponent(mysqlFormat)}`
    });
    const data = await response.json();
    if (data.success) {
      window.closeUntilModal();
      await fetchEmployees();
    } else {
      alert('Fout: ' + data.error);
    }
  } catch (error) {
    alert('Fout: ' + error.message);
  }
};
// ═══════════════════════════════════════════════════════════════════

        const btn1Upper = btn1Name.toUpperCase();
        const btn2Upper = btn2Name.toUpperCase();
        const btn3Upper = btn3Name.toUpperCase();
        
        // 🔧 FIX: Tel BUTTON1/2/3 (database waarde) ipv button namen
        if(sub === 'BUTTON1') cntPauze++;
        if(sub === 'BUTTON2') cntThuiswerken++;
        if(sub === 'BUTTON3') cntVakantie++;
        
        // BHV: alleen als IN
        if(s === "IN" && normalize(emp.BHV) === "ja") cntBhv++;
      });
      
      if(inTop) inTop.innerHTML = '<span class="badge-icon">✓</span>' + cntIn;
      if(outTop) outTop.innerHTML = '<span class="badge-icon">✗</span>' + cntOut;
      if(bhvTop) bhvTop.innerHTML = '<span class="badge-icon">🚨</span>' + cntBhv;
      
      updateExtraBadges(cntPauze, cntVakantie, cntThuiswerken);
    }

    function updateExtraBadges(pauze, vakantie, thuiswerken) {
      const container = document.querySelector('.header-badges');
      if (!container) return;
      
      const btn1Name = buttonConfig.button1.name;
      const btn2Name = buttonConfig.button2.name;
      const btn3Name = buttonConfig.button3.name;
      
      let pauzeBadge = document.getElementById('count-pauze-top');
      let vakantieBadge = document.getElementById('count-vakantie-top');
      let thuiswerkenBadge = document.getElementById('count-thuiswerken-top');
      
      // Check voor button3 (VAKANTIE)
      const hasBtn1 = window.__userFeatures?.extraButtons?.[btn1Name] === true || 
                      window.__userFeatures?.extraButtons?.PAUZE === true;
      
      if (!pauzeBadge && hasBtn1) {
        pauzeBadge = document.createElement('div');
        pauzeBadge.id = 'count-pauze-top';
        pauzeBadge.className = 'badge badge-pauze';
        container.appendChild(pauzeBadge);
      }
      
      // Check voor button3 (VAKANTIE)
      const hasBtn3 = window.__userFeatures?.extraButtons?.[btn3Name] === true || 
                      window.__userFeatures?.extraButtons?.VAKANTIE === true;
      
      if (!vakantieBadge && hasBtn3) {
        vakantieBadge = document.createElement('div');
        vakantieBadge.id = 'count-vakantie-top';
        vakantieBadge.className = 'badge badge-vakantie';
        container.appendChild(vakantieBadge);
      }
      
      // Check voor button2 (THUISWERKEN)
      const hasBtn2 = window.__userFeatures?.extraButtons?.[btn2Name] === true || 
                      window.__userFeatures?.extraButtons?.THUISWERKEN === true;
      
      if (!thuiswerkenBadge && hasBtn2) {
        thuiswerkenBadge = document.createElement('div');
        thuiswerkenBadge.id = 'count-thuiswerken-top';
        thuiswerkenBadge.className = 'badge badge-thuiswerken';
        container.appendChild(thuiswerkenBadge);
      }
      
      // Update badge text met iconen (kort!)
      // Iconen: ☕ Pauze, 🏖️ Vakantie, 🏠 Thuiswerken
      if (pauzeBadge) {
        const icon = getButtonIcon(btn1Name);
        pauzeBadge.innerHTML = `<span class="badge-icon">${icon}</span>${pauze}`;
        pauzeBadge.title = btn1Name + ': ' + pauze; // Tooltip met volledige naam
      }
      if (vakantieBadge) {
        const icon = getButtonIcon(btn3Name);
        vakantieBadge.innerHTML = `<span class="badge-icon">${icon}</span>${vakantie}`;
        vakantieBadge.title = btn3Name + ': ' + vakantie;
      }
      if (thuiswerkenBadge) {
        const icon = getButtonIcon(btn2Name);
        thuiswerkenBadge.innerHTML = `<span class="badge-icon">${icon}</span>${thuiswerken}`;
        thuiswerkenBadge.title = btn2Name + ': ' + thuiswerken;
      }
    }
    
    // Helper functie voor badge iconen
    function getButtonIcon(buttonName) {
      const name = buttonName.toUpperCase();
      
      // Pauze varianten
      if (name.includes('PAUZE') || name.includes('KOFFIE') || name.includes('PAUS')) return '☕';
      if (name.includes('CURSUS') || name.includes('TRAINING')) return '📚';
      if (name.includes('LUNCH') || name.includes('ETEN')) return '🍽️';
      
      // Vakantie varianten
      if (name.includes('VAKANTIE') || name.includes('VRIJ')) return '🏖️';
      if (name.includes('VERLOF')) return '🗓️';
      if (name.includes('ZIEK')) return '🤒';
      
      // Thuiswerken varianten
      if (name.includes('THUIS') || name.includes('HOME')) return '🏠';
      if (name.includes('REMOTE')) return '💻';
      if (name.includes('EXTERN')) return '🌐';
      
      // Default iconen
      return '📍'; // Fallback
    }

// ============================================================================
// === DEEL 6: FILTERS & EMPLOYEE RENDERING (🔥 8-CHAR BUTTONS!)
// ============================================================================

    // 🆕 FILTER DEBOUNCING voor betere performance
    let filterTimeout = null;
    function debounceFilter() {
      clearTimeout(filterTimeout);
      filterTimeout = setTimeout(() => {
        applyCurrentFilters();
      }, 300); // 300ms vertraging
    }
/**
 * 🆕 Check of employee zichtbaar moet zijn op huidige locatie
 * Gebruikt visible_locations veld uit database
 */
function shouldShowEmployeeOnLocation(employee, currentLocationFilter) {
    // Als "ALL" locaties filter actief of geen filter
    if (!currentLocationFilter || currentLocationFilter.trim() === '') {
        return true;
    }
    
    // Parse visible_locations van employee
    let visibleLocations = [];
    try {
        // Check of field bestaat (kan visible_locations of VisibleLocations zijn)
        const visibleLocsField = employee.visible_locations || employee.VisibleLocations;
        
        if (visibleLocsField) {
            visibleLocations = typeof visibleLocsField === 'string' 
                ? JSON.parse(visibleLocsField) 
                : visibleLocsField;
        } else {
            // Geen visible_locations veld? Default: overal zichtbaar
            visibleLocations = ['ALL'];
        }
    } catch (e) {
        console.warn('Error parsing visible_locations for employee:', employee.Naam, e);
        visibleLocations = ['ALL']; // Bij parse error: overal zichtbaar
    }
    
    // Check 1: Is employee zichtbaar op ALLE locaties?
    if (visibleLocations.includes('ALL') || visibleLocations.includes('all')) {
        return true;
    }
    
    // Check 2: Is employee's HUIDIGE locatie gelijk aan filter?
    // (Dit zorgt ervoor dat employee altijd zichtbaar is waar hij NU is)
    const empCurrentLoc = normalize(employee.Locatie || employee.Gebouw || '');
    const filterLoc = normalize(currentLocationFilter);
    
    if (empCurrentLoc === filterLoc) {
        return true; // Altijd tonen waar employee NU is
    }
    
    // Check 3: Zoek locatie ID bij naam en check visible_locations
    // Voor nu: simple string match (TODO: gebruik location ID's voor betere matching)
    const locationNameMatches = visibleLocations.some(locId => {
        // Als locId een nummer is, probeer naam op te zoeken
        // Voor nu: ook string matching toestaan
        return normalize(String(locId)) === filterLoc || 
               normalize(String(locId)).includes(filterLoc) ||
               filterLoc.includes(normalize(String(locId)));
    });
    
    if (locationNameMatches) {
        return true;
    }
    
    return false; // Niet zichtbaar op deze locatie
}
    // Apply filters
    function applyCurrentFilters(){
      const input = document.getElementById("search-input");
      const statusSel = document.getElementById("filter-status");
      const bhvSel = document.getElementById("filter-bhv");
      const locSel = document.getElementById("filter-locatie");
      const afdSel = document.getElementById("filter-afdeling");

      if(!input || !statusSel || !bhvSel || !locSel || !afdSel){
        renderEmployees();
        updateDashboard();
        return;
      }

      const q = normalize(input.value);
      const status = (statusSel.value || "").trim();
      const bhv = (bhvSel.value || "").trim();
      const locatie = (locSel.value || "").trim();
      const afdeling = (afdSel.value || "").trim();

      const filtered = employees.filter(emp => {
    const matchQ = q === "" || normalize(emp.Naam).includes(q) || normalize(emp.Functie).includes(q) || normalize(emp.Afdeling).includes(q);
    const matchS = !status || ( (emp.Status||"").trim() === status );
    const empBhv = normalize(emp.BHV);
    const matchB = !bhv || (normalize(bhv) === "ja" ? empBhv === "ja" : empBhv !== "ja");
    const matchL = !locatie || normalize(emp.Locatie || emp.Gebouw) === normalize(locatie);
    const matchA = !afdeling || normalize(emp.Afdeling) === normalize(afdeling);
    
    // 🆕 Location filtering: dropdown override user settings
    // Als dropdown gebruikt wordt → skip user settings (toon alles)
    // Als dropdown leeg → gebruik user settings
    const matchSel = locatie ? true : (
      selectedLocations.length === 0 || 
      selectedLocations.some(selLoc => getLocationID(selLoc) === getLocationID(emp.Locatie || emp.Gebouw))
    );
    
    // 🆕 Afdeling filtering: dropdown override user settings
    // Als dropdown gebruikt wordt → skip user settings (toon alles)
    // Als dropdown leeg → gebruik user settings
    const matchAfdSel = afdeling ? true : (
      selectedAfdelingen.length === 0 || 
      selectedAfdelingen.some(selAfd => normalize(selAfd) === normalize(emp.Afdeling))
    );
    
    // 🆕 Multi-location visibility check
    const matchVisible = !locatie || shouldShowEmployeeOnLocation(emp, locatie);
    
    return matchQ && matchS && matchB && matchL && matchA && matchSel && matchAfdSel && matchVisible;
});

      renderEmployees(filtered);
      updateDashboard();
      renderVisitors(); // ✅ Update visitors when location filter changes
      renderVisitorsInside(); // ✅ Update visitors inside when location filter changes
    }

    // 🆕 Field options setup - NU MET USER FEATURES SUPPORT
    function setupFieldOptions(){
      const checks = document.querySelectorAll("#field-options input[type=checkbox]");
      
      // Als user features beschikbaar zijn, gebruik die
      if(userVisibleFields && userVisibleFields.length > 0) {
        visibleFields = userVisibleFields.slice();
        // ✅ FIX: Forceer dat Foto altijd zichtbaar is
        if (!visibleFields.includes("Foto")) {
          visibleFields.push("Foto");
        }
        console.log("✅ User visible fields loaded:", visibleFields);
        
        // Update checkboxes to match user settings
        checks.forEach(cb => {
          const fieldName = cb.value;
          // Map FotoURL -> Foto voor compatibility
          const mappedName = fieldName === "FotoURL" ? "Foto" : fieldName;
          cb.checked = visibleFields.includes(mappedName) || visibleFields.includes(fieldName);
          // ✅ FIX: Forceer Foto checkbox altijd checked
          if (fieldName === "Foto" || fieldName === "FotoURL") {
            cb.checked = true;
          }
        });
      } else if(!checks.length){
        // Fallback als geen checkboxes
        visibleFields = ["Naam","BHV","Foto","Functie","Afdeling","Locatie","Tijdstip"];
      } else {
        // Anders gebruik checkboxes
        visibleFields = Array.from(checks).filter(c => c.checked).map(c => c.value);
      }
      
      // Attach listeners
      checks.forEach(cb => cb.addEventListener("change", () => {
        visibleFields = Array.from(checks).filter(c => c.checked).map(c => c.value);
        // ✅ FIX: Forceer dat Foto altijd in lijst blijft
        if (!visibleFields.includes("Foto") && !visibleFields.includes("FotoURL")) {
          visibleFields.push("Foto");
        }
        applyCurrentFilters();
        if(typeof window.__labee_autoHideMenuReset === "function") window.__labee_autoHideMenuReset();
      }));
    }

    // Render employees
    function renderEmployees(listOverride){
      console.log("👥 renderEmployees() called");
      console.log("   - selectedLocations:", selectedLocations);
      console.log("   - employees count:", employees.length);
      const list = document.getElementById("employee-list");
      if(!list) return;
      list.innerHTML = "";
      
      // 🔧 Als listOverride is gegeven, gebruik die (komt van applyCurrentFilters)
      // Anders filter op locaties
      let filtered;
      if(listOverride) {
        filtered = listOverride;
        console.log("   - using pre-filtered list:", filtered.length);
      } else {
        filtered = employees.slice();
        console.log("   - before location filter:", filtered.length);
        
        if(selectedLocations.length > 0){
          console.log("   - filtering by location IDs:", selectedLocations.map(getLocationID));
          filtered = filtered.filter(e => {
            const empLocID = getLocationID(e.Locatie || e.Gebouw);
            const matches = selectedLocations.some(selLoc => getLocationID(selLoc) === empLocID);
            return matches;
          });
        }
        
        console.log("   - after location filter:", filtered.length);
      }

      // 🔄 SORT: Apply current sort mode (voornaam or achternaam)
      // Helper function to extract name based on sort mode
      const extractName = (employee, mode) => {
        if (mode === 'voornaam') {
          // Extract Voornaam
          if (employee.Voornaam) {
            return employee.Voornaam;
          } else {
            const fullName = employee.Naam || '';
            if (fullName.includes(',')) {
              return fullName.split(',')[1]?.trim() || fullName;
            } else {
              return fullName.split(' ')[0] || fullName;
            }
          }
        } else {
          // Extract Achternaam
          if (employee.Achternaam) {
            return employee.Achternaam;
          } else {
            const fullName = employee.Naam || '';
            if (fullName.includes(',')) {
              return fullName.split(',')[0]?.trim() || fullName;
            } else {
              const parts = fullName.split(' ');
              return parts[parts.length - 1] || fullName;
            }
          }
        }
      };
      
      if (window.SortToggle && typeof window.SortToggle.getCurrentMode === 'function') {
        const sortMode = window.SortToggle.getCurrentMode();
        console.log(`🔄 Sorting employees by: ${sortMode}`);
        console.log(`   Filtered count before sort: ${filtered.length}`);
        console.log(`   First employee before sort: ${filtered[0]?.Naam}`);
        
        if (sortMode === 'voornaam') {
          // Remember this for status sorting
          if (window.SortToggle.setPreviousNameSort) {
            window.SortToggle.setPreviousNameSort('voornaam');
          }
          
          // Sort by first name (Voornaam with capital V!)
          filtered.sort((a, b) => {
            const nameA = extractName(a, 'voornaam');
            const nameB = extractName(b, 'voornaam');
            return nameA.toLowerCase().localeCompare(nameB.toLowerCase());
          });
        } else if (sortMode === 'status') {
          // Sort by Status - IN first, then by previous name sort within groups
          const previousMode = window.SortToggle.getPreviousNameSort?.() || 'achternaam';
          console.log(`   📊 Status sort using previous name mode: ${previousMode}`);
          
          filtered.sort((a, b) => {
            const statusA = (a.Status || '').toUpperCase();
            const statusB = (b.Status || '').toUpperCase();
            
            // IN comes first
            if (statusA === 'IN' && statusB !== 'IN') return -1;
            if (statusA !== 'IN' && statusB === 'IN') return 1;
            
            // Within same status group, sort by previous name mode (voornaam or achternaam)
            const nameA = extractName(a, previousMode);
            const nameB = extractName(b, previousMode);
            return nameA.toLowerCase().localeCompare(nameB.toLowerCase());
          });
        } else {
          // Sort by last name (Achternaam with capital A!)
          // Remember this for status sorting
          if (window.SortToggle.setPreviousNameSort) {
            window.SortToggle.setPreviousNameSort('achternaam');
          }
          
          filtered.sort((a, b) => {
            const nameA = extractName(a, 'achternaam');
            const nameB = extractName(b, 'achternaam');
            return nameA.toLowerCase().localeCompare(nameB.toLowerCase());
          });
        }
        
        // Debug: Log after sort
        console.log(`   First employee after sort: ${filtered[0]?.Naam}`);
        console.log(`   Last employee after sort: ${filtered[filtered.length - 1]?.Naam}`);
      } else {
        // Fallback: sort by full Naam field if SortToggle not available
        filtered.sort((a, b) => {
          const nameA = (a.Naam || '').toLowerCase();
          const nameB = (b.Naam || '').toLowerCase();
          return nameA.localeCompare(nameB);
        });
      }

      if(filtered.length === 0){
        list.innerHTML = "<p class=\"no-data\">Geen medewerkers gevonden.</p>";
        return;
      }

      filtered.forEach(emp => {
        const card = document.createElement("div");
        const s = (emp.Status||"").toString().trim().toUpperCase();
        const sub = (emp.SubStatus||"").toString().trim().toUpperCase(); // RAW database waarde
        const subOriginal = sub;  // Bewaar origineel voor kaart kleur check!
        
        // 🔍 DEBUG: Log voor eerste 3 employees met sub-status
        if (subOriginal && filtered.indexOf(emp) < 3) {
          console.log(`🔍 DEBUG Card Color for ${emp.Naam}:`);
          console.log(`   SubStatus raw: "${emp.SubStatus}"`);
          console.log(`   subOriginal: "${subOriginal}"`);
          console.log(`   Match BUTTON1: ${subOriginal === 'BUTTON1'}`);
          console.log(`   Match BUTTON2: ${subOriginal === 'BUTTON2'}`);
          console.log(`   Match BUTTON3: ${subOriginal === 'BUTTON3'}`);
        }
        
        // Kaart kleur: EERST status class, DAN kleur class
        let cardClass = "employee-card ";
        
        // STAP 1: Voeg hoofdstatus toe (in of out)
        if (s === "IN") {
          cardClass += "in ";
        } else if (s === "OUT") {
          cardClass += "out ";
        }
        
        // STAP 2: Als er sub-status is, voeg kleur class toe
        if (subOriginal) {
          if (subOriginal === 'BUTTON1') {
            cardClass += "pauze";  // → .employee-card.in.pauze (roze)
          } else if (subOriginal === 'BUTTON2') {
            cardClass += "thuiswerken";  // → .employee-card.in.thuiswerken (paars)
          } else if (subOriginal === 'BUTTON3') {
            cardClass += "vakantie";  // → .employee-card.in.vakantie (groen)
          } else {
            console.warn(`⚠️ Unknown sub-status label: "${subOriginal}" for ${emp.Naam}`);
          }
        }
        
        card.className = cardClass;
        card.setAttribute("data-id", emp.ID || "");

        let photo = "";
        if(emp.FotoURL){
          const val = emp.FotoURL.trim();
          photo = /^https?:\/\//i.test(val) ? val : BASE_PHOTO_URL + "/" + val.replace(/^.*[\\/]/, "");
        } else {
          photo = BASE_PHOTO_URL + "/no-photo.png";
        }

        let html = '<div class="card-content">';
        
        // Foto (check both "Foto" and "FotoURL")
        if((visibleFields.includes("Foto") || visibleFields.includes("FotoURL")) && photo){
          html += '<div class="emp-photo-side">' +
                    `<img src="${photo}" alt="${emp.Naam||''}" onerror="this.src='${BASE_PHOTO_URL}/no-photo.png'"/>` +
                  '</div>';
        }

        html += '<div class="emp-details">';
        
        // ✅ FIXED: Naam rendering met Voornaam/Achternaam support
        const showVoornaam = visibleFields.includes("Voornaam");
        const showAchternaam = visibleFields.includes("Achternaam");
        const showNaam = visibleFields.includes("Naam"); // Legacy fallback
        const showTijdstip = visibleFields.includes("Tijdstip");
        
        if(showVoornaam || showAchternaam || showNaam || showTijdstip){
          html += '<div class="emp-name">';
          
          // Build name display
          let nameDisplay = '';
          if (showVoornaam && emp.Voornaam) nameDisplay += emp.Voornaam;
          if (showAchternaam && emp.Achternaam) nameDisplay += (nameDisplay ? ' ' : '') + emp.Achternaam;
          if (!nameDisplay && showNaam) nameDisplay = emp.Naam || ''; // Legacy fallback
          
          if(nameDisplay) html += `<span>${nameDisplay}</span>`;
          if(showTijdstip) html += `<span class="status-label ${s==="IN"?"in":"out"}">${s} ${formatTime(emp.Tijdstip)}</span>`;
          html += '</div>';
        }

        // 📍 MANUAL LOCATION BUTTON
        if (emp.allow_manual_location_change == 1 && emp.visible_locations) {
            try {
                const visLocs = typeof emp.visible_locations === 'string' ? JSON.parse(emp.visible_locations) : emp.visible_locations;
                
                if (visLocs && visLocs.includes('ALL')) {
                    const empData = JSON.stringify(emp).replace(/"/g, '&quot;');
                    
                    html += `
                        <button 
                            class="manual-location-btn" 
                            onclick="event.stopPropagation(); showManualLocationSelector(${empData})"
                            title="Check-in op andere locatie"
                            style="
                                position: absolute;
                                top: 8px;
                                right: 80px;
                                background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
                                color: white;
                                border: none;
                                padding: 6px 10px;
                                border-radius: 6px;
                                font-size: 16px;
                                cursor: pointer;
                                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                                z-index: 10;
                            "
                        >📍</button>
                    `;
                }
            } catch(e) {
                console.warn('Failed to parse visible_locations for employee', emp.ID, e);
            }
        }

        if(visibleFields.includes("BHV") && normalize(emp.BHV)==="ja"){
          html += '<div class="bhv-label">BHV</div>';
        }
// 🆕 SUB-STATUS MET UNTIL DATE + LABEL MAPPING
if (subOriginal) {  // Gebruik origineel (BUTTON1/BUTTON2/BUTTON3)
  let displayName = subOriginal;
  
  // ✅ FIX: Get button names from buttonConfig (not hardcoded!)
  let btn1Custom = (buttonConfig.button1 && buttonConfig.button1.name) ? buttonConfig.button1.name.toUpperCase() : 'PAUZE';
  let btn2Custom = (buttonConfig.button2 && buttonConfig.button2.name) ? buttonConfig.button2.name.toUpperCase() : 'THUISWERKEN';
  let btn3Custom = (buttonConfig.button3 && buttonConfig.button3.name) ? buttonConfig.button3.name.toUpperCase() : 'VAKANTIE';
  
  // Overschrijf met user custom names als die er zijn
  if(window.__userFeatures && window.__userFeatures.customButtonNames) {
    if(window.__userFeatures.customButtonNames.button1) {
      btn1Custom = window.__userFeatures.customButtonNames.button1.toUpperCase();
    }
    if(window.__userFeatures.customButtonNames.button2) {
      btn2Custom = window.__userFeatures.customButtonNames.button2.toUpperCase();
    }
    if(window.__userFeatures.customButtonNames.button3) {
      btn3Custom = window.__userFeatures.customButtonNames.button3.toUpperCase();
    }
  }
  
  // Map LABEL (BUTTON1/BUTTON2/BUTTON3) naar display naam (UPPERCASE!)
  if (subOriginal === 'BUTTON1') displayName = btn1Custom;
  else if (subOriginal === 'BUTTON2') displayName = btn2Custom;
  else if (subOriginal === 'BUTTON3') displayName = btn3Custom;
  
  let subStatusText = displayName;
  
  // 🎯 SUB-STATUS TIJD DISPLAY (VERSIE B - CLEAN)
  let untilValue = null;
  
  // Check multiple possible field names
  if (emp.sub_status_until) {
    untilValue = emp.sub_status_until;
  } else if (emp.SubStatusUntil) {
    untilValue = emp.SubStatusUntil;
  } else if (emp.subStatusUntil) {
    untilValue = emp.subStatusUntil;
  }
  
  let subStatusHTML = `<div style="font-size:11px;padding:4px 8px;background:rgba(0,0,0,0.1);border-radius:4px;margin-top:4px">${subStatusText}</div>`;
  
  if (untilValue && untilValue !== 'NULL' && untilValue !== null) {
    try {
      const until = new Date(untilValue);
      const now = new Date();
      
      if (!isNaN(until.getTime()) && until > now) {
        // 🎨 Bepaal kleur op basis van button
        let color = '#999'; // Default grijs
        if (subOriginal === 'BUTTON1') {
          color = buttonConfig.button1.color || '#ff69b4'; // Roze
        } else if (subOriginal === 'BUTTON2') {
          color = buttonConfig.button2.color || '#9370db'; // Paars
        } else if (subOriginal === 'BUTTON3') {
          color = buttonConfig.button3.color || '#9acd32'; // Groen
        }
        
        // Smart date formatting
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const untilDay = new Date(until.getFullYear(), until.getMonth(), until.getDate());
        
        const daysDiff = Math.floor((untilDay - today) / (1000 * 60 * 60 * 24));
        
        let timeText;
        if (daysDiff === 0) {
          // Today - show only time
          timeText = `Tot ${until.getHours().toString().padStart(2, '0')}:${until.getMinutes().toString().padStart(2, '0')}`;
        } else if (daysDiff === 1) {
          // Tomorrow - show "morgen" + time
          timeText = `Tot morgen ${until.getHours().toString().padStart(2, '0')}:${until.getMinutes().toString().padStart(2, '0')}`;
        } else {
          // Later - show day + month
          const months = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
          timeText = `Tot ${until.getDate()} ${months[until.getMonth()]}`;
        }
        
        // Add time display (VERSIE B - Larger, centered, with colored border)
        subStatusHTML = `<div style="font-size:11px;padding:4px 8px;background:rgba(0,0,0,0.1);border-radius:4px;margin-top:4px">${subStatusText}</div>`;
        subStatusHTML += `<div style="background:rgba(255,255,255,0.95);color:#333;padding:8px 13px;border-radius:8px;font-size:11px;font-weight:600;margin:8px auto;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.15);border:2px solid ${color};max-width:200px">🕐 ${timeText}</div>`;
      }
    } catch (error) {
      // Silent fail - don't show time if error
    }
  }
  
  html += subStatusHTML;
}
        if(visibleFields.includes("Functie") && emp.Functie){
          html += `<div class="emp-meta">${emp.Functie}</div>`;
        }

        if(visibleFields.includes("Afdeling") || visibleFields.includes("Locatie")){
          const parts = [];
          if(visibleFields.includes("Afdeling") && emp.Afdeling) parts.push(emp.Afdeling);
          if(visibleFields.includes("Locatie") && (emp.Locatie||emp.Gebouw)) parts.push(emp.Locatie||emp.Gebouw);
          if(parts.length) html += `<div class="emp-meta">${parts.join(" – ")}</div>`;
        }

        html += '<div class="btn-wrap">';
        html += '<button class="status-btn in">IN</button>';
        html += '<button class="status-btn out">UIT</button>';
        html += '</div>';

        // 🔥 EXTRA KNOPPEN MET 8-KARAKTER NAMEN! 🔥
        if (window.__userFeatures && window.__userFeatures.extraButtons) {
          const extras = window.__userFeatures.extraButtons;
          html += '<div class="extra-btn-wrap">';
          
          // Button 1 (PAUZE) - 🔥 MAX 8 TEKENS!
          let btn1Name = buttonConfig.button1.name;
          // Check for user custom name FIRST
          if(window.__userFeatures.customButtonNames && window.__userFeatures.customButtonNames.button1) {
            btn1Name = window.__userFeatures.customButtonNames.button1;
          }
          // Check both custom name, global name, and legacy name
          if (extras[btn1Name] === true || extras[buttonConfig.button1.name] === true || extras.PAUZE === true) {
            const displayName = btn1Name.substring(0, Math.min(8, btn1Name.length)).toUpperCase();
            // Button 1 always gets class "pauze" (for pink color)
            html += `<button class="extra-btn pauze" data-status="${btn1Name}" data-button-number="1" title="${btn1Name}">${displayName}</button>`;
          }
          
          // Button 2 (THUISWERKEN)
          let btn2Name = buttonConfig.button2.name;
          if(window.__userFeatures.customButtonNames && window.__userFeatures.customButtonNames.button2) {
            btn2Name = window.__userFeatures.customButtonNames.button2;
          }
          if (extras[btn2Name] === true || extras[buttonConfig.button2.name] === true || extras.THUISWERKEN === true) {
            const displayName = btn2Name.substring(0, Math.min(8, btn2Name.length)).toUpperCase();
            // Button 2 always gets class "thuiswerken" (for purple color)
            html += `<button class="extra-btn thuiswerken" data-status="${btn2Name}" data-button-number="2" title="${btn2Name}">${displayName}</button>`;
          }
          
          // Button 3 (VAKANTIE)
          let btn3Name = buttonConfig.button3.name;
          if(window.__userFeatures.customButtonNames && window.__userFeatures.customButtonNames.button3) {
            btn3Name = window.__userFeatures.customButtonNames.button3;
          }
          if (extras[btn3Name] === true || extras[buttonConfig.button3.name] === true || extras.VAKANTIE === true) {
            const displayName = btn3Name.substring(0, Math.min(8, btn3Name.length)).toUpperCase();
            // Button 3 always gets class "vakantie" (for green color)
            html += `<button class="extra-btn vakantie" data-status="${btn3Name}" data-button-number="3" title="${btn3Name}">${displayName}</button>`;
          }
          
          html += '</div>';
        }
        html += '</div></div>';
        card.innerHTML = html;

        const inBtn = card.querySelector(".status-btn.in");
        const outBtn = card.querySelector(".status-btn.out");
        // 🔧 IN/OUT buttons: clear sub_status
        if(inBtn) inBtn.addEventListener("click",()=>{ updateStatus(emp.ID,"IN", null); if(typeof window.__labee_autoHideMenuReset === "function") window.__labee_autoHideMenuReset(); });
        if (outBtn) outBtn.addEventListener("click",()=>{ updateStatus(emp.ID,"OUT", null); });

        // 🔧 Extra buttons: Set sub-status (zet status op IN als nog OUT)
        const extraBtns = card.querySelectorAll(".extra-btn");
        extraBtns.forEach(btn => {
          btn.addEventListener("click", () => {
            const subStatusName = btn.getAttribute('data-status');
            const currentStatus = emp.Status.toUpperCase();
            
            // Als OUT → zet eerst op IN
            const newStatus = (currentStatus === "OUT") ? "IN" : currentStatus;
            
            // 🆕 Get button number from data attribute (POSITION-BASED!)
            const buttonNumber = parseInt(btn.getAttribute('data-button-number')) || 1;
            const config = buttonConfig[`button${buttonNumber}`];
            
            // ⭐ CRITICAL FIX: Generate LABEL from button number
            const labelMap = {
              1: 'BUTTON1',
              2: 'BUTTON2',
              3: 'BUTTON3'
            };
            const subStatusLabel = labelMap[buttonNumber] || 'BUTTON1';
            
            console.log('🔘 Button clicked:', {
              buttonNumber: buttonNumber,
              displayName: subStatusName,
              configName: config?.name,
              labelToSend: subStatusLabel  // ⭐ This goes to database!
            });
            
            if (config && config.ask_until) {
              // Show modal
              window.showUntilModal(emp.ID, subStatusName, subStatusName, buttonNumber);
            } else {
              // Direct update with LABEL (not custom name!)
              updateStatus(emp.ID, newStatus, subStatusLabel);  // ⭐ SEND LABEL!
            }
          });
        });

        list.appendChild(card);
      });
    }

// ============================================================================
// === DEEL 7: INITIALIZATION & USER CUSTOM NAMES
// ============================================================================

    // === DEEL 7 ===
    // Footer buttons (Admin + BHV)
    function updateFooterButtons(){
      const footer = document.querySelector("footer");
      if(!footer) return;
      let actions = footer.querySelector(".footer-actions");
      if(!actions){
        actions = document.createElement("div");
        actions.className = "footer-actions";
        footer.appendChild(actions);
      }
      actions.innerHTML = "";
      
      // ✅ BHV Button - ALTIJD ZICHTBAAR met duidelijk icoon
      const bhvBtn = document.createElement("button");
      bhvBtn.type = "button";
      bhvBtn.className = "footer-btn footer-btn-bhv";
      bhvBtn.innerHTML = "🚨 BHV Overzicht"; // ✅ Icoon toegevoegd
      bhvBtn.setAttribute("title", "Open BHV Overzicht");
      bhvBtn.addEventListener("click", function() {
        window.open(BASE_PATH + "/bhv-print/bhv-print.html", "bhv_overzicht", "width=1200,height=800,menubar=no,toolbar=no,location=no");
      });
      
      // ✅ Admin Button - CORRECTE URL naar dashboard
      const adminBtn = document.createElement("a");
      adminBtn.href = BASE_PATH + "/admin/dashboard.php"; // ✅ GEFIXED!
      adminBtn.className = "footer-btn footer-btn-admin";
      adminBtn.innerHTML = "⚙️ Admin Dashboard"; // ✅ Icoon toegevoegd
      adminBtn.setAttribute("target", "_blank");
      adminBtn.setAttribute("title", "Open Admin Dashboard");
      
      actions.appendChild(bhvBtn);
      actions.appendChild(adminBtn);
    }
    
    function shouldShowBHV(){
      return employees.some(e => normalize(e.BHV) === "ja" && normalize(e.Status) === "in");
    }
    
    function finalizeUI(){
      updateFooterButtons();
      
      // ✅ REMOVED: BHV button blijft nu altijd zichtbaar!
      // Oude code die BHV button verborg is verwijderd
    }
    
    // Init sequence
    removeLeftoverEmailDOM();
    headerUIInit();
    
    // Enable fullscreen na eerste user interactie
    let fullscreenEnabled = false;
    document.addEventListener('click', function enableFullscreen() {
      if (!fullscreenEnabled && !document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(e => {
          console.log('Fullscreen niet toegestaan');
        });
        fullscreenEnabled = true;
        document.removeEventListener('click', enableFullscreen);
      }
    }, { once: true });
    
    // Start auto-hide menu direct bij page load
    if (typeof window.__labee_autoHideMenuStart === 'function') {
      window.__labee_autoHideMenuStart();
      console.log('Auto-hide menu timer started (2 min)');
    }
    
    // 🆕 FILTER DEBOUNCING - Attach listeners
    document.addEventListener('DOMContentLoaded', () => {
      const searchInput = document.getElementById("search-input");
      const filterSelects = document.querySelectorAll("#filters select");
      
      if(searchInput) {
        searchInput.addEventListener("input", debounceFilter);
      }
      
      filterSelects.forEach(sel => {
        sel.addEventListener("change", debounceFilter);
      });
    });
    
    // ============================================================================
    // 🔍 FILTER PERSISTENCE MODULE
    // Bewaart filters in localStorage + visuele feedback
    // ============================================================================
    (function() {
        'use strict';
        
        const STORAGE_KEY = 'peopledisplay_filters';
        const FILTER_TIMEOUT = 5 * 60 * 1000; // 5 minuten
        
        function saveFilters() {
            const searchInput = document.getElementById("search-input");
            const statusSel = document.getElementById("filter-status");
            const bhvSel = document.getElementById("filter-bhv");
            const locSel = document.getElementById("filter-locatie");
            const afdSel = document.getElementById("filter-afdeling");
            
            if (!searchInput) return;
            
            const filters = {
                search: searchInput.value || '',
                status: statusSel?.value || '',
                bhv: bhvSel?.value || '',
                locatie: locSel?.value || '',
                afdeling: afdSel?.value || '',
                timestamp: Date.now()
            };
            
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(filters));
                updateFilterIndicator();
            } catch (e) {
                console.warn('Failed to save filters:', e);
            }
        }
        
        function loadFilters() {
            try {
                const stored = localStorage.getItem(STORAGE_KEY);
                if (!stored) return null;
                
                const filters = JSON.parse(stored);
                
                if (Date.now() - filters.timestamp > FILTER_TIMEOUT) {
                    clearFilters(false);
                    return null;
                }
                
                return filters;
            } catch (e) {
                return null;
            }
        }
        
        function restoreFilters() {
            const filters = loadFilters();
            if (!filters) return;
            
            const searchInput = document.getElementById("search-input");
            const statusSel = document.getElementById("filter-status");
            const bhvSel = document.getElementById("filter-bhv");
            const locSel = document.getElementById("filter-locatie");
            const afdSel = document.getElementById("filter-afdeling");
            
            if (!searchInput) return;
            
            searchInput.value = filters.search || '';
            if (statusSel) statusSel.value = filters.status || '';
            if (bhvSel) bhvSel.value = filters.bhv || '';
            if (locSel) locSel.value = filters.locatie || '';
            if (afdSel) afdSel.value = filters.afdeling || '';
            
            updateFilterIndicator();
        }
        
        function clearFilters(applyFilter = true) {
            const searchInput = document.getElementById("search-input");
            const statusSel = document.getElementById("filter-status");
            const bhvSel = document.getElementById("filter-bhv");
            const locSel = document.getElementById("filter-locatie");
            const afdSel = document.getElementById("filter-afdeling");
            
            if (searchInput) searchInput.value = '';
            if (statusSel) statusSel.value = '';
            if (bhvSel) bhvSel.value = '';
            if (locSel) locSel.value = '';
            if (afdSel) afdSel.value = '';
            
            try {
                localStorage.removeItem(STORAGE_KEY);
            } catch (e) {}
            
            updateFilterIndicator();
            
            if (applyFilter && typeof applyCurrentFilters === 'function') {
                applyCurrentFilters();
            }
        }
        
        function hasActiveFilters() {
            const searchInput = document.getElementById("search-input");
            const statusSel = document.getElementById("filter-status");
            const bhvSel = document.getElementById("filter-bhv");
            const locSel = document.getElementById("filter-locatie");
            const afdSel = document.getElementById("filter-afdeling");
            
            return (
                (searchInput?.value || '') !== '' ||
                (statusSel?.value || '') !== '' ||
                (bhvSel?.value || '') !== '' ||
                (locSel?.value || '') !== '' ||
                (afdSel?.value || '') !== ''
            );
        }
        
        function updateFilterIndicator() {
            const filtersSection = document.getElementById('filters');
            const resetBtn = document.getElementById('reset-filters-btn');
            const indicator = document.getElementById('filter-indicator');
            
            const active = hasActiveFilters();
            
            if (filtersSection) {
                if (active) {
                    filtersSection.classList.add('filters-active');
                } else {
                    filtersSection.classList.remove('filters-active');
                }
            }
            
            if (resetBtn) {
                resetBtn.style.display = active ? 'inline-block' : 'none';
            }
            
            if (indicator) {
                const count = [
                    document.getElementById("search-input")?.value,
                    document.getElementById("filter-status")?.value,
                    document.getElementById("filter-bhv")?.value,
                    document.getElementById("filter-locatie")?.value,
                    document.getElementById("filter-afdeling")?.value
                ].filter(v => v && v !== '').length;
                
                if (count > 0) {
                    indicator.textContent = count;
                    indicator.style.display = 'inline-block';
                } else {
                    indicator.style.display = 'none';
                }
            }
        }
        
        function createResetButton() {
            const filtersSection = document.getElementById('filters');
            if (!filtersSection || document.getElementById('reset-filters-btn')) return;
            
            const button = document.createElement('button');
            button.id = 'reset-filters-btn';
            button.className = 'reset-filters-btn';
            button.innerHTML = '🗑️ Reset Filters <span id="filter-indicator" class="filter-count">0</span>';
            button.style.display = 'none';
            button.onclick = clearFilters;
            
            filtersSection.appendChild(button);
        }
        
        function init() {
            restoreFilters();
            
            const searchInput = document.getElementById("search-input");
            const filterSelects = document.querySelectorAll("#filters select");
            
            if (searchInput) {
                searchInput.addEventListener("input", saveFilters);
            }
            
            filterSelects.forEach(sel => {
                sel.addEventListener("change", saveFilters);
            });
            
            createResetButton();
            
            // Wait for employees to load before applying filters
            if (hasActiveFilters()) {
                console.log('📍 Active filters detected, will apply after data loads');
                
                // Listen for employee data load
                const checkAndApply = setInterval(() => {
                    if (window.employees && window.employees.length > 0 && typeof applyCurrentFilters === 'function') {
                        console.log('✅ Employees loaded, applying saved filters');
                        applyCurrentFilters();
                        clearInterval(checkAndApply);
                    }
                }, 200); // Check every 200ms
                
                // Timeout after 5 seconds
                setTimeout(() => clearInterval(checkAndApply), 5000);
            }
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            setTimeout(init, 100);
        }
        
        window.FilterPersistence = {
            save: saveFilters,
            clear: clearFilters,
            hasActive: hasActiveFilters
        };
    })();
    // ============================================================================
    // EINDE FILTER PERSISTENCE MODULE
    // ============================================================================
    
    // Haal user features en config op
    fetch(BASE_PATH + '/admin/api/get-user-features.php')
      .then(r => r.ok ? r.json() : null)
      .then(userFeatures => {
        console.log("📂 USER FEATURES RECEIVED:", userFeatures);

// ═══════════════════════════════════════════════════════════════════
// EINDE DEEL 2 - GA VERDER MET DEEL 3!
// ═══════════════════════════════════════════════════════════════════
/**
 * ═══════════════════════════════════════════════════════════════════
 * APP.JS - DEEL 3 VAN 3
 * ═══════════════════════════════════════════════════════════════════
 * PLAK DIT DIRECT NA DEEL 2
 * DIT IS HET LAATSTE DEEL!
 * ═══════════════════════════════════════════════════════════════════
 */

        
        // 🆕 Sla user features op
        if(userFeatures && !userFeatures.error) {
          userVisibleFields = userFeatures.visibleFields || null;
          userLocations = userFeatures.locations || null;
          userAfdelingen = userFeatures.afdelingen || null; // 🆕 Afdeling filtering
          
          // 🔧 Fix extraButtons: converteer array naar object formaat
          if(Array.isArray(userFeatures.extraButtons)) {
            // Als het een array is met strings zoals ["PAUZE", "VAKANTIE"]
            userExtraButtons = {};
            userFeatures.extraButtons.forEach(btn => {
              userExtraButtons[btn] = true;
            });
            console.log("✅ Extra buttons converted from array:", userExtraButtons);
          } else if(typeof userFeatures.extraButtons === 'object') {
            // Als het al een object is zoals {"PAUZE": true}
            userExtraButtons = userFeatures.extraButtons;
            console.log("✅ Extra buttons loaded:", userExtraButtons);
          } else {
            userExtraButtons = {};
          }
          
          // 🆕 Auto-select user locations
          if(userLocations && Array.isArray(userLocations) && userLocations.length > 0) {
            selectedLocations = userLocations.slice();
            console.log("✅ User locations auto-selected:", selectedLocations);
            console.log("   Location IDs:", selectedLocations.map(getLocationID));
          } else {
            console.log("⚠️ No user locations found - showing all");
            selectedLocations = []; // Toon alles als geen locaties ingesteld
          }
          
          // 🆕 Auto-select user afdelingen
          if(userAfdelingen && Array.isArray(userAfdelingen) && userAfdelingen.length > 0) {
            selectedAfdelingen = userAfdelingen.slice();
            console.log("✅ User afdelingen auto-selected:", selectedAfdelingen);
          } else {
            console.log("⚠️ No user afdelingen found - showing all");
            selectedAfdelingen = []; // Toon alles als geen afdelingen ingesteld
          }
          
          console.log("📊 Final user settings:", {
            visibleFields: userVisibleFields,
            locations: selectedLocations,
            afdelingen: selectedAfdelingen, // 🆕
            extraButtons: userExtraButtons
          });
        } else {
          console.warn("⚠️ User features not available or error:", userFeatures);
        }
        
        // Haal daarna config op
        return fetch(BASE_PATH + "/admin/get-config.php")
          .then(r => {
            if(!r.ok) throw new Error("get-config.php returned " + r.status);
            return r.json();
          })
          .then(cfg => {
    console.log("CONFIG:", cfg);
    
    window.DATA_API_URL = BASE_PATH + "/admin/api/employees_api.php";  // ✅ NIEUWE REGEL
            
            // 🎬 Start presentatie controller
            if (window.PresentationController && userFeatures) {
              console.log('🎬 Initializing presentation controller');
              window.PresentationController.init({
                presentationID: cfg.presentationID || null,
                userPresentationID: userFeatures.presentationID || null,
                idle_timeout: userFeatures.presentationIdleSeconds || 120,  // ✅ In SECONDEN!
                allow_auto_fullscreen: cfg.allow_auto_fullscreen || false
              });
            } else if (!window.PresentationController) {
              console.log('ℹ️ PresentationController not loaded - presentation disabled');
            }
            
            // Sla user features op voor filtering

/**
 * ============================================================================
 * EINDE DEEL 2 VAN 3
 * ============================================================================
 * GA VERDER MET: app_part3_FINAL.js
 * ============================================================================
 */
/**
 * ============================================================================
 * APP.JS - DEEL 3 VAN 3 (LAATSTE DEEL!)
 * ============================================================================
 * INSTRUCTIE: Plak dit DIRECT na deel 2 (ZONDER deze header!)
 * ============================================================================
 * DEEL 3 (regel 1641-2463):
 * BEVAT: Manual Location Selector module (NIEUW! 📍)
 * ============================================================================
 */

            window.__userFeatures = {
              ...userFeatures,
              extraButtons: userExtraButtons // Gebruik de geconverteerde versie
            };
            
            // 🔗 Create alias for sort toggle module (uses window.userFeatures)
            window.userFeatures = window.__userFeatures;
            
            console.log("🌐 Global __userFeatures set:", window.__userFeatures);
            
            // 🆕 Load button configuration
            loadButtonConfig();
            
            // 🔄 Initialize sort toggle if available
            if (window.SortToggle && typeof window.SortToggle.init === 'function') {
                console.log('🔄 Calling SortToggle.init()...');
                window.SortToggle.init();
            }
            
            // 🆕 Render user profile in header
            renderUserProfile(window.__userFeatures);
            
            // Setup field options NADAT user features geladen zijn
            setupFieldOptions();
            
            fetchEmployees();
            
            // 🔄 AUTO-REFRESH employees every 30 seconds
            setInterval(() => {
              console.log('🔄 Auto-refreshing employees...');
              fetchEmployees();
            }, 30000);
          });
      })
      .catch(err => {
        console.error("Fout bij ophalen config/features:", err);
        setupFieldOptions(); // Fallback
        fetchEmployees();
      });
    
    
    // ============================================================================
    // === VISITORS FUNCTIONALITY ===
    // ============================================================================
    
    let visitors = [];
    let visitorsContainer = null;
    let visitorsSection = null;
    
    // Create visitors section in DOM
    function createVisitorsSection() {
      if (visitorsSection) return; // Already exists
      
      const employeeList = document.getElementById("employee-list");
      if (!employeeList) {
        console.warn("⚠️ employee-list not found, cannot create visitors section");
        return;
      }
      
      // Create FLEX CONTAINER for side-by-side layout
      let flexContainer = document.getElementById("visitors-flex-container");
      if (!flexContainer) {
        flexContainer = document.createElement("div");
        flexContainer.id = "visitors-flex-container";
        flexContainer.style.cssText = `
          display: flex;
          gap: 10px;
          margin: 15px auto;
          max-width: 1400px;
          flex-wrap: wrap;
        `;
        employeeList.parentNode.insertBefore(flexContainer, employeeList);
      }
      
      // Create section element
      visitorsSection = document.createElement("div");
      visitorsSection.className = "visitors-section";
      visitorsSection.id = "visitors-section-dynamic";
      
      // Add inline styles - ULTRA AGGRESSIVE COMPACT
      visitorsSection.style.cssText = `
        margin: 0;
        flex: 0 0 auto;
        width: fit-content;
        max-width: 450px;
        min-width: 280px;
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        border-radius: 8px;
        padding: 5px 8px 5px 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        display: none;
      `;
      
      visitorsSection.innerHTML = `
        <div class="visitors-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; color: white;">
          <h3 style="font-size: 13px; font-weight: 600; margin: 0; line-height: 1;">👥 Verwachte Bezoekers</h3>
          <div class="visitors-count" id="visitors-count-dynamic" style="background: rgba(255,255,255,0.3); padding: 2px 8px; border-radius: 10px; font-weight: 600; font-size: 10px;">0</div>
        </div>
        <div class="visitors-list" id="visitors-list-dynamic" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 200px)); gap: 4px; max-height: 250px; overflow-y: auto;"></div>
      `;
      
      // Insert INTO flex container
      flexContainer.appendChild(visitorsSection);
      visitorsContainer = document.getElementById("visitors-list-dynamic");
      
      console.log("✅ Visitors section created dynamically (side-by-side)");
    }
    
    // Fetch visitors from API
    async function fetchVisitors() {
      try {
        const response = await fetch(BASE_PATH + '/api/get_todays_visitors.php', {
          cache: 'no-store'
        });
        
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }
        
        const data = await response.json();
        
        if (data.success) {
          visitors = data.visitors || [];
          console.log('✅ Visitors loaded:', visitors.length);
          renderVisitors();
        } else {
          console.warn('⚠️ Visitors fetch failed:', data.error);
          visitors = [];
          renderVisitors();
        }
      } catch (error) {
        console.error('❌ Error fetching visitors:', error);
        visitors = [];
        renderVisitors();
      }
    }
    
    // Render visitors in UI
    function renderVisitors() {
      console.log('🎨 renderVisitors called');
      console.log('   Total visitors:', visitors.length);
      console.log('   Selected locations:', selectedLocations);
      console.log('   Selected locations length:', selectedLocations ? selectedLocations.length : 'null');
      
      // ✅ NIEUWE AANPAK: Verwijder section VOLLEDIG als geen match
      // (niet alleen display:none, maar echt uit DOM)
      
      if (!selectedLocations || selectedLocations.length === 0) {
        console.log('🚫 ALL MODE - REMOVING visitor section from DOM');
        if (visitorsSection && visitorsSection.parentNode) {
          visitorsSection.parentNode.removeChild(visitorsSection);
          visitorsSection = null;
          visitorsContainer = null;
        }
        return;
      }
      
      console.log('✅ SPECIFIC LOCATION MODE - continuing...');
      
      // Filter AANGEMELD bezoekers
      let filteredVisitors = visitors.filter(v => 
        v.status && v.status.toUpperCase() === 'AANGEMELD'
      );
      
      console.log('📋 AANGEMELD visitors:', filteredVisitors.length, '/', visitors.length);
      
      if (filteredVisitors.length > 0) {
        console.log('   Visitor locations:', filteredVisitors.map(v => v.locatie));
      }
      
      // Filter op GESELECTEERDE locatie (ALTIJD!)
      filteredVisitors = filteredVisitors.filter(v => {
        if (!v.locatie) {
          console.log('   Visitor has no location - skipping');
          return false;
        }
        const match = selectedLocations.some(selLoc => 
          getLocationID(selLoc) === getLocationID(v.locatie)
        );
        console.log('   Visitor', v.naam, 'at', v.locatie, '- match:', match);
        return match;
      });
      console.log('📍 After location filter:', filteredVisitors.length, 'visitors remain');
      
      // ✅ GEEN bezoekers voor deze locatie? VERWIJDER section
      if (filteredVisitors.length === 0) {
        console.log('👻 No visitors for this location - REMOVING pink section from DOM');
        if (visitorsSection && visitorsSection.parentNode) {
          visitorsSection.parentNode.removeChild(visitorsSection);
          visitorsSection = null;
          visitorsContainer = null;
        }
        return;
      }
      
      // WEL bezoekers? Zorg dat section bestaat
      if (!visitorsSection) {
        createVisitorsSection();
      }
      
      if (!visitorsContainer) {
        console.warn('⚠️ Visitors container not ready after create');
        return;
      }
      
      // Toon section
      console.log('👥 SHOWING', filteredVisitors.length, 'visitors for selected location(s)');
      visitorsSection.style.display = 'block';
      
      // Update count
      const countBadge = document.getElementById('visitors-count-dynamic');
      if (countBadge) {
        countBadge.textContent = filteredVisitors.length === 1 ? '1 bezoeker' :
                                  filteredVisitors.length + ' bezoekers';
      }
      
      // Clear container
      visitorsContainer.innerHTML = '';
      
      // Render visitor cards
      filteredVisitors.forEach(visitor => {
        const card = document.createElement('div');
        card.className = 'visitor-card';
        card.setAttribute('data-visitor-id', visitor.id);
        
        // Inline styles for ULTRA MINIMAL visitor card
        card.style.cssText = `
          background: white;
          border-radius: 6px;
          padding: 5px 7px;
          box-shadow: 0 2px 4px rgba(0,0,0,0.08);
          display: flex;
          flex-direction: column;
          transition: transform 0.2s, box-shadow 0.2s;
        `;
        
        card.addEventListener('mouseenter', () => {
          card.style.transform = 'translateY(-2px)';
          card.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        });
        card.addEventListener('mouseleave', () => {
          card.style.transform = '';
          card.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
        });
        
        const time = visitor.bezoek_tijd ? visitor.bezoek_tijd.substring(0, 5) : '--:--';
        const naam = (visitor.naam || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const bedrijf = (visitor.bedrijf || 'Geen bedrijf').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const locatie = (visitor.locatie || 'Onbekend').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        
        card.innerHTML = `
          <div style="display: flex; justify-content: space-between; align-items: baseline; gap: 4px; margin-bottom: 1px;">
            <div style="font-size: 13px; font-weight: 600; color: #2d3748; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; line-height: 1.2;">${naam}</div>
            <div style="font-size: 10px; color: #667eea; white-space: nowrap; font-weight: 700; line-height: 1.2;">🕐 ${time}</div>
          </div>
          <div style="font-size: 10px; color: #a0aec0; background: #f7fafc; padding: 1px 6px; border-radius: 8px; text-align: center; margin-bottom: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.2;">${locatie}</div>
          <div style="font-size: 10px; color: #718096; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 3px; line-height: 1.2;">${bedrijf}</div>
          <button onclick="window.checkInVisitor(${visitor.id})" style="
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            border: none;
            padding: 3px;
            border-radius: 5px;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            line-height: 1.2;
          " onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform=''">
            ✓ Check In
          </button>
        `;
        
        visitorsContainer.appendChild(card);
      });
    }
    
    // Check in visitor
    window.checkInVisitor = async function(visitorId) {
      if (!confirm('Bezoeker inchecken?')) return;
      
      try {
        const response = await fetch(BASE_PATH + '/api/visitor_checkin.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            visitor_id: visitorId,
            action: 'checkin'
          })
        });
        
        const data = await response.json();
        
        if (data.success) {
          console.log('✅ Visitor checked in:', visitorId);
          // Refresh visitors list
          await fetchVisitors();
        } else {
          alert('Fout bij inchecken: ' + (data.error || 'Onbekende fout'));
        }
      } catch (error) {
        console.error('❌ Check-in error:', error);
        alert('Fout bij inchecken. Probeer opnieuw.');
      }
    };
    
    // Initialize visitors after employees are loaded
    function initVisitors() {
      console.log('📊 Initializing visitors...');
      createVisitorsSection();
      fetchVisitors();
      
      // Refresh visitors every 30 seconds
      setInterval(fetchVisitors, 30000);
      
      // Initialize visitors inside (compact) after 2 seconds
      setTimeout(() => {
        initVisitorsInside();
      }, 2000);
    }
    
    // ============================================================================
    // === VISITORS INSIDE FUNCTIONALITY (COMPACT)
    // ============================================================================
    
    let visitorsInside = [];
    let visitorsInsideContainer = null;
    let visitorsInsideSection = null;
    
    // Create compact visitors inside section
    function createVisitorsInsideSection() {
      if (visitorsInsideSection) return;
      
      // Use same flex container as pink section
      let flexContainer = document.getElementById("visitors-flex-container");
      if (!flexContainer) {
        const employeeList = document.getElementById("employee-list");
        if (!employeeList) {
          console.warn("⚠️ employee-list not found");
          return;
        }
        flexContainer = document.createElement("div");
        flexContainer.id = "visitors-flex-container";
        flexContainer.style.cssText = `
          display: flex;
          gap: 10px;
          margin: 15px auto;
          max-width: 1400px;
          flex-wrap: wrap;
        `;
        employeeList.parentNode.insertBefore(flexContainer, employeeList);
      }
      
      // Create compact section
      visitorsInsideSection = document.createElement("div");
      visitorsInsideSection.className = "visitors-inside-section";
      visitorsInsideSection.id = "visitors-inside-section-dynamic";
      
      // ULTRA AGGRESSIVE COMPACT styling with green gradient
      visitorsInsideSection.style.cssText = `
        margin: 0;
        flex: 0 0 auto;
        width: fit-content;
        max-width: 450px;
        min-width: 280px;
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        border-radius: 8px;
        padding: 5px 8px 5px 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        display: none;
      `;
      
      visitorsInsideSection.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; color: white;">
          <h3 style="font-size: 13px; font-weight: 600; margin: 0; line-height: 1;">👤 Bezoekers Binnen</h3>
          <div id="visitors-inside-count-dynamic" style="background: rgba(255,255,255,0.3); padding: 2px 8px; border-radius: 10px; font-weight: 600; font-size: 10px;">0</div>
        </div>
        <div id="visitors-inside-list-dynamic" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 200px)); gap: 4px; max-height: 250px; overflow-y: auto;"></div>
      `;
      
      // Insert INTO flex container
      flexContainer.appendChild(visitorsInsideSection);
      visitorsInsideContainer = document.getElementById("visitors-inside-list-dynamic");
      
      console.log("✅ Visitors inside section created (side-by-side)");
    }
    
    // Fetch visitors inside from API
    async function fetchVisitorsInside() {
      try {
        const response = await fetch(BASE_PATH + '/api/get_visitors_inside.php', {
          cache: 'no-store'
        });
        
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }
        
        const data = await response.json();
        
        if (data.success) {
          visitorsInside = data.visitors || [];
          console.log('✅ Visitors inside loaded:', visitorsInside.length);
          renderVisitorsInside();
        } else {
          console.warn('⚠️ Visitors inside fetch failed:', data.error);
          visitorsInside = [];
          renderVisitorsInside();
        }
      } catch (error) {
        console.error('❌ Error fetching visitors inside:', error);
        visitorsInside = [];
        renderVisitorsInside();
      }
    }
    
    // Render visitors inside (COMPACT layout)
    function renderVisitorsInside() {
      console.log('🎨 renderVisitorsInside called');
      console.log('   Total inside:', visitorsInside.length);
      console.log('   Selected locations:', selectedLocations);
      console.log('   Selected locations length:', selectedLocations ? selectedLocations.length : 'null');
      
      // ✅ NIEUWE AANPAK: Verwijder section VOLLEDIG als geen match
      
      if (!selectedLocations || selectedLocations.length === 0) {
        console.log('🚫 ALL MODE - REMOVING visitors inside section from DOM');
        if (visitorsInsideSection && visitorsInsideSection.parentNode) {
          visitorsInsideSection.parentNode.removeChild(visitorsInsideSection);
          visitorsInsideSection = null;
          visitorsInsideContainer = null;
        }
        return;
      }
      
      console.log('✅ SPECIFIC LOCATION MODE - continuing...');
      
      // Filter op GESELECTEERDE locatie (ALTIJD!)
      let filteredVisitors = visitorsInside.filter(v => {
        if (!v.locatie) {
          console.log('   Visitor inside has no location - skipping');
          return false;
        }
        const match = selectedLocations.some(selLoc => 
          getLocationID(selLoc) === getLocationID(v.locatie)
        );
        console.log('   Visitor inside', v.naam, 'at', v.locatie, '- match:', match);
        return match;
      });
      console.log('📍 After location filter:', filteredVisitors.length, 'visitors inside remain');
      
      // ✅ GEEN bezoekers binnen voor deze locatie? VERWIJDER section
      if (filteredVisitors.length === 0) {
        console.log('👻 No visitors inside for this location - REMOVING green section from DOM');
        if (visitorsInsideSection && visitorsInsideSection.parentNode) {
          visitorsInsideSection.parentNode.removeChild(visitorsInsideSection);
          visitorsInsideSection = null;
          visitorsInsideContainer = null;
        }
        return;
      }
      
      // WEL bezoekers binnen? Zorg dat section bestaat
      if (!visitorsInsideSection) {
        createVisitorsInsideSection();
      }
      
      if (!visitorsInsideContainer) {
        console.warn('⚠️ Visitors inside container not ready after create');
        return;
      }
      
      // Toon section
      console.log('👥 SHOWING', filteredVisitors.length, 'visitors inside');
      visitorsInsideSection.style.display = 'block';
      
      // Update count
      const countBadge = document.getElementById('visitors-inside-count-dynamic');
      if (countBadge) {
        countBadge.textContent = filteredVisitors.length.toString();
      }
      
      // Clear container
      visitorsInsideContainer.innerHTML = '';
      
      // Render COMPACT tiles (not rows!)
      filteredVisitors.forEach(visitor => {
        const tile = document.createElement('div');
        tile.className = 'visitor-inside-tile';
        tile.setAttribute('data-visitor-id', visitor.id);
        
        // Ultra minimal TILE styling
        tile.style.cssText = `
          background: rgba(255,255,255,0.95);
          border-radius: 6px;
          padding: 5px 7px;
          box-shadow: 0 2px 4px rgba(0,0,0,0.08);
          display: flex;
          flex-direction: column;
          transition: transform 0.2s;
        `;
        
        tile.addEventListener('mouseenter', () => {
          tile.style.transform = 'scale(1.02)';
        });
        tile.addEventListener('mouseleave', () => {
          tile.style.transform = '';
        });
        
        const naam = (visitor.naam || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const locatie = (visitor.locatie || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        
        // Format time (HH:MM)
        let tijd = '--:--';
        if (visitor.checked_in_at) {
          try {
            const date = new Date(visitor.checked_in_at);
            tijd = date.toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });
          } catch (e) {
            tijd = visitor.checked_in_at.substring(11, 16);
          }
        }
        
        tile.innerHTML = `
          <div style="display: flex; justify-content: space-between; align-items: baseline; gap: 4px; margin-bottom: 1px;">
            <div style="font-size: 13px; font-weight: 600; color: #2d3748; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; line-height: 1.2;">${naam}</div>
            <div style="font-size: 10px; color: #718096; white-space: nowrap; line-height: 1.2;">🕐 ${tijd}</div>
          </div>
          <div style="font-size: 10px; color: #a0aec0; background: #f7fafc; padding: 1px 6px; border-radius: 8px; text-align: center; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.2;">${locatie}</div>
          <button onclick="window.checkOutVisitor(${visitor.id})" style="
            background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);
            color: white;
            border: none;
            padding: 3px;
            border-radius: 5px;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            line-height: 1.2;
          " onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform=''">
            ✓ Check Out
          </button>
        `;
        
        visitorsInsideContainer.appendChild(tile);
      });
    }
    
    // Check out visitor
    window.checkOutVisitor = async function(visitorId) {
      if (!confirm('Bezoeker uitchecken?')) return;
      
      try {
        const response = await fetch(BASE_PATH + '/api/visitor_checkout.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            visitor_id: visitorId
          })
        });
        
        const data = await response.json();
        
        if (data.success) {
          console.log('✅ Visitor checked out:', visitorId);
          // Refresh both lists
          await Promise.all([
            fetchVisitorsInside(),
            fetchVisitors() // Refresh verwachte bezoekers (might have new ones)
          ]);
        } else {
          alert('Fout bij uitchecken: ' + (data.error || 'Onbekende fout'));
        }
      } catch (error) {
        console.error('❌ Check-out error:', error);
        alert('Fout bij uitchecken. Probeer opnieuw.');
      }
    };
    
    // Initialize visitors inside
    function initVisitorsInside() {
      console.log('👤 Initializing visitors inside (compact)...');
      createVisitorsInsideSection();
      fetchVisitorsInside();
      
      // Refresh visitors inside every 30 seconds
      setInterval(fetchVisitorsInside, 30000);
    }
    
    // Hook into page load - wait for employee-list to exist
    const waitForEmployeeList = setInterval(() => {
      if (document.getElementById('employee-list')) {
        clearInterval(waitForEmployeeList);
        setTimeout(initVisitors, 1000); // Give employees time to render first
      }
    }, 100);
    
    // ✅ EXPORT internal functies naar window voor gebruik buiten IIFE
    // Dit MOET binnen de IIFE gebeuren (functies zijn hier beschikbaar)
    window.__labee_internal = {
      fetchEmployees: fetchEmployees,
      renderEmployees: renderEmployees,
      applyCurrentFilters: applyCurrentFilters,
      updateDashboard: updateDashboard,
      getEmployees: () => employees,
      getSelectedLocations: () => selectedLocations
    };
    
  })(); // einde IIFE
} // einde initialisatie guard

// ============================================================================
// === EXTERNAL API EXPORT (BUITEN GUARD!) - Voor auto-refresh
// ============================================================================
// Dit staat BUITEN de guard zodat het ALTIJD bereikbaar is,
// ook na auto-refresh wanneer de guard true is en code skipped wordt.

window.labeeApp = window.__labee_internal;
console.log("✅ window.labeeApp exported:", window.labeeApp ? Object.keys(window.labeeApp) : 'UNDEFINED');

// ============================================================================
// MANUAL LOCATION SELECTOR MODULE
// ============================================================================

// Sessie opslag voor tijdelijke locaties (in localStorage for persistence)
// localStorage survives page refreshes, window variables don't

/**
 * Sla tijdelijke locatie op voor employee
 */
function setTempLocation(employeeId, tempLocationName, originalLocationName) {
    const tempLocs = JSON.parse(localStorage.getItem('__tempEmployeeLocations') || '{}');
    const origLocs = JSON.parse(localStorage.getItem('__originalEmployeeLocations') || '{}');
    
    tempLocs[employeeId] = tempLocationName;
    origLocs[employeeId] = originalLocationName;
    
    localStorage.setItem('__tempEmployeeLocations', JSON.stringify(tempLocs));
    localStorage.setItem('__originalEmployeeLocations', JSON.stringify(origLocs));
    
    console.log(`📍 Temp location set for employee ${employeeId}: ${tempLocationName} (original: ${originalLocationName})`);
}

/**
 * Haal tijdelijke locatie op
 */
function getTempLocation(employeeId) {
    const tempLocs = JSON.parse(localStorage.getItem('__tempEmployeeLocations') || '{}');
    return tempLocs[employeeId] || null;
}

/**
 * Haal originele locatie op
 */
function getOriginalLocation(employeeId) {
    const origLocs = JSON.parse(localStorage.getItem('__originalEmployeeLocations') || '{}');
    return origLocs[employeeId] || null;
}

/**
 * Clear tijdelijke locatie (bij check-out)
 */
function clearTempLocation(employeeId) {
    const tempLocs = JSON.parse(localStorage.getItem('__tempEmployeeLocations') || '{}');
    const origLocs = JSON.parse(localStorage.getItem('__originalEmployeeLocations') || '{}');
    
    delete tempLocs[employeeId];
    delete origLocs[employeeId];
    
    localStorage.setItem('__tempEmployeeLocations', JSON.stringify(tempLocs));
    localStorage.setItem('__originalEmployeeLocations', JSON.stringify(origLocs));
    
    console.log(`📍 Temp location cleared for employee ${employeeId}`);
}

/**
 * Helper: Haal alle locaties op
 */
function getAllLocations() {
    // Probeer eerst window.__allLocations
    if (window.__allLocations && window.__allLocations.length > 0) {
        return window.__allLocations;
    }
    
    // Fallback: extract uit labeeApp employees
    const employees = window.labeeApp && typeof window.labeeApp.getEmployees === 'function' 
        ? window.labeeApp.getEmployees() 
        : [];
    
    if (!employees || employees.length === 0) {
        console.warn('getAllLocations: No employees found');
        return [];
    }
    
    const locations = new Set();
    employees.forEach(emp => {
        const loc = emp.Locatie || emp.locatie || emp.Gebouw;
        if (loc && loc.trim()) {
            locations.add(loc.trim());
        }
    });
    
    const result = Array.from(locations).sort();
    console.log('📍 getAllLocations found:', result.length, 'locations');
    return result;
}

/**
 * Toon locatie selector modal
 */
function showManualLocationSelector(employee) {
    console.log('📍 Showing manual location selector for:', employee);
    
    // Check of employee dit mag
    if (!employee.allow_manual_location_change || employee.allow_manual_location_change != 1) {
        console.log('❌ Employee not allowed to change location manually');
        return;
    }
    
    // Haal alle locaties op
    const allLocations = getAllLocations();
    
    if (!allLocations || allLocations.length === 0) {
        alert('Geen locaties beschikbaar');
        return;
    }
    
    // Maak modal HTML
    const modalHTML = `
        <div id="manual-location-modal" style="
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        ">
            <div style="
                background: white;
                border-radius: 12px;
                padding: 30px;
                max-width: 500px;
                width: 90%;
                box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            ">
                <h2 style="margin: 0 0 10px 0; color: #2d3748; font-size: 22px;">
                    📍 Check-in op andere locatie
                </h2>
                <p style="color: #718096; margin: 0 0 20px 0; font-size: 14px;">
                    ${employee.Naam || employee.naam || 'Medewerker'}<br>
                    <small>Standaard locatie: ${employee.Locatie || employee.locatie || 'Onbekend'}</small>
                </p>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748;">
                        Kies locatie voor deze check-in:
                    </label>
                    <select id="manual-location-select" style="
                        width: 100%;
                        padding: 12px;
                        border: 2px solid #e2e8f0;
                        border-radius: 8px;
                        font-size: 16px;
                        background: white;
                    ">
                        <option value="">-- Selecteer locatie --</option>
                        ${allLocations.map(loc => `
                            <option value="${loc}">${loc}</option>
                        `).join('')}
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button id="manual-location-confirm" style="
                        flex: 1;
                        padding: 12px 24px;
                        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
                        color: white;
                        border: none;
                        border-radius: 8px;
                        font-size: 16px;
                        font-weight: 600;
                        cursor: pointer;
                    ">
                        ✓ Inchecken op deze locatie
                    </button>
                    <button id="manual-location-cancel" style="
                        padding: 12px 24px;
                        background: #e2e8f0;
                        color: #2d3748;
                        border: none;
                        border-radius: 8px;
                        font-size: 16px;
                        font-weight: 600;
                        cursor: pointer;
                    ">
                        Annuleren
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Voeg modal toe aan body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Event listeners
    document.getElementById('manual-location-cancel').addEventListener('click', closeManualLocationModal);
    
    document.getElementById('manual-location-confirm').addEventListener('click', () => {
        const selectedLocation = document.getElementById('manual-location-select').value;
        
        if (!selectedLocation) {
            alert('Selecteer eerst een locatie');
            return;
        }
        
        // Sla tijdelijke locatie op (inclusief originele locatie voor reset)
        setTempLocation(employee.ID, selectedLocation, employee.Locatie || employee.locatie);
        
        console.log('📍 Calling check-in with location:', selectedLocation);
        
        // Check-in met tijdelijke locatie - via window.updateStatus
        if (typeof window.updateStatus === 'function') {
            window.updateStatus(employee.ID, 'IN', null, selectedLocation);
            console.log('✅ Check-in called successfully');
        } else {
            console.error('❌ updateStatus function not found on window');
        }
        
        // Sluit modal
        closeManualLocationModal();
    });
    
    // Close on background click
    document.getElementById('manual-location-modal').addEventListener('click', (e) => {
        if (e.target.id === 'manual-location-modal') {
            closeManualLocationModal();
        }
    });
}

/**
 * Sluit locatie selector modal
 */
function closeManualLocationModal() {
    const modal = document.getElementById('manual-location-modal');
    if (modal) {
        modal.remove();
    }
}

// Maak functies globally beschikbaar
window.showManualLocationSelector = showManualLocationSelector;
window.setTempLocation = setTempLocation;
window.getTempLocation = getTempLocation;
window.clearTempLocation = clearTempLocation;

console.log('✅ Manual Location Selector module loaded');
/**
 * ============================================================================
 * EINDE DEEL 3 VAN 3 - APP.JS COMPLEET!
 * ============================================================================
 * 
 * ✅ JE BENT KLAAR!
 * 
 * VOLGENDE STAPPEN:
 * 1. Voeg alle 3 delen samen (zonder headers!)
 * 2. Sla op als app.js
 * 3. Upload naar /app.js (OVERSCHRIJF)
 * 4. Hard refresh browser (Ctrl+Shift+R)
 * 5. Check console: "✅ Manual Location Selector module loaded"
 * 6. Test 📍 knop op employee kaarten
 * 
 * ============================================================================
 */

// ============================================================================
// 🔥 APP.JS LOADED - MANUAL LOCATION MODULE
// ============================================================================
console.log('✅ Manual Location Selector module loaded');

/**
 * ═══════════════════════════════════════════════════════════════
 * ✨ SORTEER TOGGLE FUNCTIONALITY (NIEUW)
 * ═══════════════════════════════════════════════════════════════
 */

(function() {
    'use strict';
    
    let currentSortMode = 'achternaam'; // Default
    let previousNameSortMode = 'achternaam'; // Track last name-based sort for status sorting
    
    /**
     * Initialize sort toggle
     */
    function initSortToggle() {
        console.log('🔄 Initializing sort toggle...');
        
        // Check if user has sorteerFunctie feature
        const canToggleSort = window.userFeatures?.sorteerFunctie || false;
        
        if (!canToggleSort) {
            console.log('ℹ️ User does not have sort toggle feature');
            return;
        }
        
        console.log('✅ User has sort toggle feature - showing button');
        
        // Show toggle container
        const container = document.getElementById('sort-toggle-container');
        if (container) {
            container.style.display = 'block';
        }
        
        // Load saved preference from localStorage
        const savedSort = localStorage.getItem('peopledisplay_sort_mode');
        if (savedSort && (savedSort === 'voornaam' || savedSort === 'achternaam' || savedSort === 'status')) {
            currentSortMode = savedSort;
            console.log('📝 Loaded saved sort preference:', currentSortMode);
        }
        
        // Update UI to reflect current mode
        updateSortUI();
        
        // Setup event listeners
        setupSortEventListeners();
    }
    
    /**
     * Setup event listeners
     */
    function setupSortEventListeners() {
        const toggleBtn = document.getElementById('sort-toggle-btn');
        const dropdown = document.getElementById('sort-dropdown');
        const sortOptions = document.querySelectorAll('.sort-option');
        
        if (!toggleBtn || !dropdown) {
            console.error('❌ Sort toggle elements not found');
            return;
        }
        
        // Toggle dropdown on button click
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
            toggleBtn.classList.toggle('active');
        });
        
        // Handle sort option clicks
        sortOptions.forEach(option => {
            option.addEventListener('click', function(e) {
                e.stopPropagation();
                const sortMode = this.dataset.sort;
                
                if (sortMode !== currentSortMode) {
                    console.log('🔄 Switching sort mode to:', sortMode);
                    currentSortMode = sortMode;
                    
                    // Save to localStorage
                    localStorage.setItem('peopledisplay_sort_mode', sortMode);
                    
                    // Update UI
                    updateSortUI();
                    
                    // Re-apply filters with new sort
                    if (window.labeeApp && typeof window.labeeApp.applyCurrentFilters === 'function') {
                        window.labeeApp.applyCurrentFilters();
                    } else if (window.labeeApp && typeof window.labeeApp.renderEmployees === 'function') {
                        window.labeeApp.renderEmployees();
                    }
                }
                
                // Close dropdown
                dropdown.classList.remove('show');
                toggleBtn.classList.remove('active');
            });
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!toggleBtn.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
                toggleBtn.classList.remove('active');
            }
        });
        
        console.log('✅ Sort toggle event listeners setup complete');
    }
    
    /**
     * Update sort UI to reflect current mode
     */
    function updateSortUI() {
        const letter = document.getElementById('sort-letter');
        const sortOptions = document.querySelectorAll('.sort-option');
        
        // Update compact button letter (A = Achternaam, V = Voornaam, S = Status)
        if (letter) {
            if (currentSortMode === 'voornaam') {
                letter.textContent = 'V';
            } else if (currentSortMode === 'status') {
                letter.textContent = 'S';
            } else {
                letter.textContent = 'A';
            }
        }
        
        // Update active option in dropdown
        sortOptions.forEach(option => {
            const sortMode = option.dataset.sort;
            const bullet = option.querySelector('.bullet');
            
            if (sortMode === currentSortMode) {
                option.classList.add('active');
                if (bullet) bullet.textContent = '●';
            } else {
                option.classList.remove('active');
                if (bullet) bullet.textContent = '○';
            }
        });
        
        console.log('🎨 Sort UI updated - current mode:', currentSortMode);
    }
    
    /**
     * Get current sort mode
     */
    function getCurrentSortMode() {
        return currentSortMode;
    }
    
    /**
     * Set previous name-based sort mode (for status sorting)
     */
    function setPreviousNameSort(mode) {
        if (mode === 'voornaam' || mode === 'achternaam') {
            previousNameSortMode = mode;
            console.log('📝 Previous name sort set to:', mode);
        }
    }
    
    /**
     * Get previous name-based sort mode
     */
    function getPreviousNameSort() {
        return previousNameSortMode;
    }
    
    // Expose to global scope
    window.SortToggle = {
        init: initSortToggle,
        getCurrentMode: getCurrentSortMode,
        setPreviousNameSort: setPreviousNameSort,
        getPreviousNameSort: getPreviousNameSort
    };
    
    console.log('✅ Sort toggle module loaded');
})();


