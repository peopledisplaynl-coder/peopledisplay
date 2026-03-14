// ============================================================================
// BESTANDSNAAM:  bhv-print.js
// UPLOAD NAAR:   /bhv-print/bhv-print.js
// DATUM FIX:     2024-12-04
// VERSIE:        v2.0 MYSQL FIXED
// 
// FIX TOEGEPAST:
// - API URL veranderd van proxy.php naar employees_api.php
// - Verwijderd: ?action=getemployees parameter (v2.0 heeft geen action param)
// - Response mapping aangepast voor v2.0 MySQL structure
// ============================================================================

"use strict";

// Detecteer BASE_PATH — werkt voor root én submap installs
const BASE_PATH = (function() {
    if (typeof window.PD_BASE_PATH !== 'undefined') return window.PD_BASE_PATH;
    const path = window.location.pathname;
    return path.replace(/\/bhv-print\/.*$/, '').replace(/\/$/, '');
})();

// v2.0: Gebruik directe MySQL API (GEEN Google Sheets proxy meer!)
const DATA_API_URL = BASE_PATH + "/admin/api/employees_api.php";
let employees = [];

function normalize(str){ return (str||"").toString().trim().toLowerCase(); }

function fetchEmployees(){
  return fetch(DATA_API_URL, { cache: "no-store" })
    .then(r => {
      if (!r.ok) throw new Error('Fetch failed: ' + r.status);
      return r.json();
    })
    .then(data => {
      // v2.0 response format: {success: true, employees: [...]}
      const empList = data.employees || data || [];
      employees = (Array.isArray(empList) ? empList : []).map(e => ({
        Naam: (e.naam ?? e.Naam ?? "").trim(),
        Functie: (e.functie ?? e.Functie ?? "").trim(),
        Afdeling: (e.afdeling ?? e.Afdeling ?? "").trim(),
        Locatie: (e.locatie ?? e.Locatie ?? e.Gebouw ?? "").trim(),
        BHV: (e.bhv ?? e.BHV ?? "").trim(),
        Status: (e.status ?? e.Status ?? "").trim().toUpperCase(),
        Tijdstip: e.tijdstip ?? e.Tijdstip ?? ""
      }));
      console.log('✅ Loaded employees:', employees.length);
    })
    .catch(err => {
      console.error('fetchEmployees error:', err);
      alert('Fout bij ophalen medewerkers: ' + err.message);
    });
}

function renderBHVList(list){
  const container = document.getElementById("bhv-list");
  container.innerHTML = "";

  if(list.length === 0){
    container.innerHTML = "<p>Geen medewerkers met status IN gevonden.</p>";
    return;
  }

  list.forEach(emp => {
    const card = document.createElement("div");
    card.className = "card";

    const naam = document.createElement("div");
    naam.className = "name";
    naam.textContent = emp.Naam;

    const meta = document.createElement("div");
    meta.className = "meta";
    meta.textContent = `${emp.Functie} – ${emp.Afdeling} – ${emp.Locatie}`;

    const tijd = document.createElement("div");
    tijd.className = "meta";
    tijd.textContent = `Tijdstip: ${formatTime(emp.Tijdstip)}`;

    card.appendChild(naam);
    card.appendChild(meta);
    card.appendChild(tijd);

    if(normalize(emp.BHV) === "ja"){
      const badge = document.createElement("div");
      badge.className = "bhv-badge";
      badge.textContent = "BHV";
      card.appendChild(badge);
    }

    container.appendChild(card);
  });
}

function formatTime(ts){
  if(!ts) return "";
  try {
    const d = new Date(ts);
    return d.toLocaleTimeString("nl-NL",{hour:"2-digit",minute:"2-digit"});
  } catch(e){ return ts; }
}

function applyFilters(){
  const q = normalize(document.getElementById("search-input").value);
  const afd = normalize(document.getElementById("filter-afdeling").value);
  const loc = normalize(document.getElementById("filter-locatie").value);
  const bhv = normalize(document.getElementById("filter-bhv").value);

  const filtered = employees.filter(e => {
    if(e.Status !== "IN") return false;
    const matchQ = !q || normalize(e.Naam).includes(q) || normalize(e.Functie).includes(q);
    const matchA = !afd || normalize(e.Afdeling) === afd;
    const matchL = !loc || normalize(e.Locatie) === loc;
    const matchB = !bhv || (bhv === "ja" ? normalize(e.BHV) === "ja" : normalize(e.BHV) !== "ja");
    return matchQ && matchA && matchL && matchB;
  });

  renderBHVList(filtered);
}

function setupFilters(){
  const afdSel = document.getElementById("filter-afdeling");
  const locSel = document.getElementById("filter-locatie");

  const afdelingen = [...new Set(employees.map(e => e.Afdeling).filter(Boolean))].sort();
  const locaties = [...new Set(employees.map(e => e.Locatie).filter(Boolean))].sort();

  afdelingen.forEach(a => {
    const opt = document.createElement("option");
    opt.value = a;
    opt.textContent = a;
    afdSel.appendChild(opt);
  });

  locaties.forEach(l => {
    const opt = document.createElement("option");
    opt.value = l;
    opt.textContent = l;
    locSel.appendChild(opt);
  });
}

function exportCSV(){
  const rows = [["Naam","Functie","Afdeling","Locatie","Tijdstip","BHV"]];
  const list = employees.filter(e => e.Status === "IN");
  
  if (list.length === 0) {
    alert('Geen medewerkers met status IN om te exporteren.');
    return;
  }
  
  list.forEach(e => {
    rows.push([e.Naam,e.Functie,e.Afdeling,e.Locatie,formatTime(e.Tijdstip),e.BHV]);
  });
  
  const csv = rows.map(r => r.join(";")).join("\n");
  const blob = new Blob([csv], {type:"text/csv;charset=utf-8;"});
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = "bhv-overzicht-" + new Date().toISOString().split('T')[0] + ".csv";
  a.click();
  URL.revokeObjectURL(url);
}

function generatePDF(){
  // Gebruik browser print functie voor nu
  // Voor echte PDF generatie zou je een library als jsPDF of html2pdf.js kunnen gebruiken
  const printBtn = document.getElementById('print-btn');
  if (printBtn) {
    alert('PDF generatie gebruikt de browser print functie.\n\nKies "Opslaan als PDF" in het print dialoog.');
    window.print();
  }
}

function sendEmail(){
  const emailSelect = document.getElementById("email-select");
  const emailInput = document.getElementById("email-input");
  const email = emailSelect.value || emailInput.value.trim();
  
  if(!email || !email.includes("@")) {
    alert("Voer een geldig e-mailadres in.");
    return;
  }

  // Genereer email body
  const list = employees.filter(e => e.Status === "IN");
  if (list.length === 0) {
    alert('Geen medewerkers met status IN om te versturen.');
    return;
  }

  let message = "BHV OVERZICHT - Aanwezige medewerkers\n";
  message += "Gegenereerd op: " + new Date().toLocaleString('nl-NL') + "\n\n";
  message += "Totaal IN: " + list.length + " medewerkers\n\n";
  message += "=" .repeat(60) + "\n\n";

  list.forEach(emp => {
    message += `Naam: ${emp.Naam}\n`;
    message += `Functie: ${emp.Functie}\n`;
    message += `Afdeling: ${emp.Afdeling}\n`;
    message += `Locatie: ${emp.Locatie}\n`;
    message += `Tijdstip: ${formatTime(emp.Tijdstip)}\n`;
    if (normalize(emp.BHV) === "ja") message += `⚠️ BHV-er\n`;
    message += "\n" + "-".repeat(60) + "\n\n";
  });

  const payload = {
    to: email,
    subject: "BHV Overzicht - " + new Date().toLocaleDateString('nl-NL'),
    message: message
  };

  // Verstuur via email-proxy
  const btn = document.getElementById('email-send-btn');
  btn.disabled = true;
  btn.textContent = '📨 Versturen...';

  fetch(BASE_PATH + '/email-proxy.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(data => {
    btn.disabled = false;
    btn.textContent = '📨 Verstuur per e-mail';
    
    if (data.success) {
      alert('✅ E-mail succesvol verstuurd naar: ' + email);
    } else {
      alert('❌ Fout bij versturen: ' + (data.error || 'Onbekende fout'));
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.textContent = '📨 Verstuur per e-mail';
    console.error('Email error:', err);
    alert('❌ Fout bij versturen e-mail: ' + err.message);
  });
}

// Event listeners
document.getElementById("print-btn").addEventListener("click", () => window.print());
document.getElementById("pdf-btn").addEventListener("click", generatePDF);
document.getElementById("csv-btn").addEventListener("click", exportCSV);
document.getElementById("email-send-btn").addEventListener("click", sendEmail);
document.getElementById("search-input").addEventListener("input", applyFilters);
document.getElementById("filter-afdeling").addEventListener("change", applyFilters);
document.getElementById("filter-locatie").addEventListener("change", applyFilters);
document.getElementById("filter-bhv").addEventListener("change", applyFilters);

// Initialize
fetchEmployees().then(() => {
  applyFilters();
  setupFilters();
}).catch(err => {
  console.error('Init error:', err);
  document.getElementById("bhv-list").innerHTML = "<p>❌ Fout bij laden van medewerkers</p>";
});
