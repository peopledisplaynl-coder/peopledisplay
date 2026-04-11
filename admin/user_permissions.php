<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true)) {
    header('Location: /frontpage.php');
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <title>Gebruikerspermissies beheren</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/assets/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding: 2rem; }
    .section-title { margin-top: 2rem; font-weight: bold; border-bottom: 1px solid #ccc; padding-bottom: .5rem; }
    .form-check { margin-bottom: .5rem; }
    .status { margin-top: 1rem; }
    .nav-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
  </style>
</head>
<body>
<div class="container">
  <div class="nav-top">
    <h1>Beheer permissies</h1>
    <a href="/admin/index.php" class="btn btn-secondary">← Terug naar admin</a>
  </div>

  <form id="userSelectForm" class="mb-4">
    <label for="userSelect" class="form-label">Selecteer gebruiker:</label>
    <select id="userSelect" class="form-select" name="id" required>
      <option value="">-- Kies een gebruiker --</option>
    </select>
  </form>

  <form id="permForm" style="display:none;">
    <div class="section-title">📍 Locatie-menu</div>
    <div id="locatieContainer"></div>

    <div class="section-title">🗺️ Kaart-checkboxen</div>
    <div id="kaartContainer"></div>

    <div class="section-title">🛎️ Actieknoppen</div>
    <div id="actieContainer"></div>

    <button type="submit" class="btn btn-primary mt-3">Opslaan</button>
    <div id="status" class="status"></div>
  </form>
</div>

<script>
const apiUsers = '/admin/api/users_list.php'; // ✅ juiste endpoint
const apiGet = id => `/admin/api/features.php?id=${id}`;
const apiPost = '/admin/api/features_update.php';

const containers = {
  locatie: document.getElementById('locatieContainer'),
  kaart: document.getElementById('kaartContainer'),
  actie: document.getElementById('actieContainer')
};

const categorieMap = {
  locatie: ['locatie_', 'menu_'],
  kaart: ['kaart_', 'layer_', 'checkbox_'],
  actie: ['vakantie', 'pauze', 'thuiswerken', 'actieknop']
};

function detectCategorie(key) {
  key = key.toLowerCase();
  for (const [cat, patterns] of Object.entries(categorieMap)) {
    if (patterns.some(p => key.includes(p))) return cat;
  }
  return 'kaart';
}

function loadUsers() {
  fetch(apiUsers)
    .then(r => r.json())
    .then(data => {
      const sel = document.getElementById('userSelect');
      data.users.forEach(u => {
        const opt = document.createElement('option');
        opt.value = u.id;
        opt.textContent = `${u.display_name || u.username} (${u.role})`;
        if (u.id == <?= $id ?>) opt.selected = true;
        sel.appendChild(opt);
      });
      if (sel.value) loadFeatures(sel.value);
    });
}

function loadFeatures(id) {
  fetch(apiGet(id))
    .then(r => r.json())
    .then(data => {
      if (!data.success) throw new Error(data.message || 'Fout bij laden');
      Object.values(containers).forEach(c => c.innerHTML = '');
      data.features.forEach(f => {
        const cat = detectCategorie(f.key_name);
        const div = document.createElement('div');
        div.className = 'form-check';
        div.innerHTML = `
          <input class="form-check-input" type="checkbox" id="f_${f.id}" name="${f.key_name}" ${f.visible ? 'checked' : ''}>
          <label class="form-check-label" for="f_${f.id}">${f.key_name}</label>
        `;
        containers[cat].appendChild(div);
      });
      document.getElementById('permForm').style.display = 'block';
    });
}

document.getElementById('userSelectForm').addEventListener('change', e => {
  const id = document.getElementById('userSelect').value;
  if (id) window.location.href = `user_permissions.php?id=${id}`;
});

document.getElementById('permForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const id = document.getElementById('userSelect').value;
  const checkboxes = document.querySelectorAll('input[type=checkbox]');
  const features = Array.from(checkboxes).map(cb => ({
    key_name: cb.name,
    visible: cb.checked ? 1 : 0
  }));
  fetch(apiPost, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ userid: id, features })
  })
  .then(r => r.json())
  .then(data => {
    const status = document.getElementById('status');
    if (data.success) {
      status.innerHTML = '<span class="text-success">Permissies opgeslagen.</span>';
    } else {
      status.innerHTML = '<span class="text-danger">Fout bij opslaan: ' + (data.message || data.error) + '</span>';
    }
  });
});

loadUsers();
</script>
</body>
</html>
