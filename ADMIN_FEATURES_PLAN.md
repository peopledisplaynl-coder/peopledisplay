# PeopleDisplay — Admin Feature Rechten Plan van Aanpak
**Voor uitvoering met Claude Code in VSCode**

---

## Samenvatting

Admins krijgen instelbare rechten per feature. SuperAdmins kunnen per admin instellen welke functies die admin mag gebruiken. Technisch wordt het bestaande `users.features` JSON veld uitgebreid — geen database schema wijziging nodig.

---

## Overzicht features per rol

### Altijd alleen SuperAdmin (hardcoded, niet instelbaar):
- Licentiebeheer
- Andere admins/superadmins aanmaken of verwijderen
- Build update pagina

### Instelbaar per admin (standaard AAN bij nieuwe admin):
| Feature key | Beschrijving | Pagina |
|---|---|---|
| `manage_locations` | Locaties beheren | locations_manage.php |
| `manage_departments` | Afdelingen beheren | afdelingen_manage.php |
| `manage_locations_order` | Locatie volgorde sorteren | locations_order.php |
| `manage_departments_order` | Afdeling volgorde sorteren | afdelingen_order.php |
| `manage_kiosk_tokens` | Kiosk tokens aanmaken/beheren | kiosk_tokens_manage.php |
| `manage_visitors` | Bezoekersbeheer | visitors_manage.php + visitor_email_config.php |
| `manage_badges` | Badge generator | badges_generate.php |
| `manage_bulk_actions` | Bulk acties (alles op UIT) | bulk_actions.php |
| `view_audit_log` | Audit log inzien | audit_log.php |
| `manage_system_config` | Systeemconfiguratie + extra knoppen | config_manage.php |
| `manage_substatus_dates` | Sub-status datum instellingen | substatus_date_settings.php |
| `manage_users` | Gebruikersbeheer (alleen users/managers) | users_manage.php |

---

## Fase 1 — Helper functie toevoegen

### `admin/auth_helper.php`

Voeg toe na de bestaande functies:

```php
/**
 * Check of de ingelogde admin een specifieke feature mag gebruiken.
 * SuperAdmins mogen altijd alles.
 * Admins alleen als de feature in hun features JSON staat (of als het veld leeg is = alles toegestaan).
 */
if (!function_exists('hasAdminFeature')) {
    function hasAdminFeature(string $feature): bool {
        global $db;
        $role = $_SESSION['role'] ?? '';
        
        // SuperAdmin mag altijd alles
        if ($role === 'superadmin') return true;
        
        // Geen admin = geen toegang
        if (!in_array($role, ['admin', 'employee_manager', 'user_manager'])) return false;
        
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) return false;
        
        try {
            $stmt = $db->prepare("SELECT features FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row || empty($row['features'])) return true; // Leeg = alles toegestaan
            
            $features = json_decode($row['features'], true) ?? [];
            
            // Als admin_features niet bestaat = alles toegestaan (backward compatible)
            if (!isset($features['admin_features'])) return true;
            
            return !empty($features['admin_features'][$feature]);
        } catch (Exception $e) {
            return true; // Bij fout: toegang verlenen (fail open)
        }
    }
}

/**
 * Redirect naar dashboard als admin de feature niet heeft.
 */
if (!function_exists('requireAdminFeature')) {
    function requireAdminFeature(string $feature): void {
        if (!hasAdminFeature($feature)) {
            header('Location: dashboard.php?error=no_permission');
            exit;
        }
    }
}
```

---

## Fase 2 — Paginabeveiliging toevoegen

Voeg `requireAdminFeature()` toe bovenaan elke beveiligde pagina, direct na `requireAdmin()`:

### `admin/locations_manage.php`
```php
requireAdmin();
requireAdminFeature('manage_locations');
```

### `admin/afdelingen_manage.php`
```php
requireAdmin();
requireAdminFeature('manage_departments');
```

### `admin/locations_order.php`
```php
requireAdmin();
requireAdminFeature('manage_locations_order');
```

### `admin/afdelingen_order.php`
```php
requireAdmin();
requireAdminFeature('manage_departments_order');
```

### `admin/kiosk_tokens_manage.php`
```php
requireAdmin();
requireAdminFeature('manage_kiosk_tokens');
```

### `admin/visitors_manage.php`
```php
requireAdmin();
requireAdminFeature('manage_visitors');
```

### `admin/visitor_email_config.php`
```php
requireAdmin();
requireAdminFeature('manage_visitors');
```

### `admin/badges_generate.php`
```php
requireAdmin();
requireAdminFeature('manage_badges');
```

### `admin/bulk_actions.php`
```php
requireAdmin();
requireAdminFeature('manage_bulk_actions');
```

### `admin/audit_log.php`
```php
requireAdmin();
requireAdminFeature('view_audit_log');
```

### `admin/config_manage.php`
```php
requireAdmin();
requireAdminFeature('manage_system_config');
```

### `admin/substatus_date_settings.php`
```php
requireAdmin();
requireAdminFeature('manage_substatus_dates');
```

### `admin/users_manage.php`
```php
requireAdmin();
requireAdminFeature('manage_users');
```

---

## Fase 3 — Dashboard tegels verbergen

### `admin/dashboard.php`

Voeg PHP variabelen toe na de bestaande role check:

```php
// Admin feature checks voor dashboard
$canManageLocations      = hasAdminFeature('manage_locations');
$canManageDepartments    = hasAdminFeature('manage_departments');
$canManageKiosk          = hasAdminFeature('manage_kiosk_tokens');
$canManageVisitors       = hasAdminFeature('manage_visitors');
$canManageBadges         = hasAdminFeature('manage_badges');
$canBulkActions          = hasAdminFeature('manage_bulk_actions');
$canViewAuditLog         = hasAdminFeature('view_audit_log');
$canManageConfig         = hasAdminFeature('manage_system_config');
$canManageSubstatus      = hasAdminFeature('manage_substatus_dates');
$canManageUsers          = hasAdminFeature('manage_users');
```

Wrap elke tegel met de juiste check, bijv:
```php
<?php if ($canManageLocations): ?>
<a href="locations_manage.php" class="menu-card locations">...</a>
<?php endif; ?>
```

---

## Fase 4 — Feature beheer in users_manage.php

### Sectie toevoegen in het gebruiker bewerken formulier

Voeg een nieuwe sectie toe voor admins in het edit formulier:

```javascript
// In de edit modal JavaScript, na de bestaande features sectie:
${user.role === 'admin' ? `
<div class="form-group" style="margin-top: 16px;">
  <label style="font-weight: 600; margin-bottom: 8px; display: block;">
    Admin rechten
    <small style="font-weight: 400; color: #718096;">
      — welke beheerfuncties mag deze admin gebruiken?
    </small>
  </label>
  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
    ${adminFeatures.map(f => `
      <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
        <input type="checkbox" name="admin_feature_${f.key}" 
               ${getAdminFeature(user, f.key) ? 'checked' : ''}>
        ${f.label}
      </label>
    `).join('')}
  </div>
</div>
` : ''}
```

Voeg toe aan de JavaScript constanten:
```javascript
const adminFeatures = [
  { key: 'manage_locations',         label: 'Locaties beheren' },
  { key: 'manage_departments',       label: 'Afdelingen beheren' },
  { key: 'manage_locations_order',   label: 'Locatie volgorde sorteren' },
  { key: 'manage_departments_order', label: 'Afdeling volgorde sorteren' },
  { key: 'manage_kiosk_tokens',      label: 'Kiosk tokens beheren' },
  { key: 'manage_visitors',          label: 'Bezoekersbeheer' },
  { key: 'manage_badges',            label: 'Badge generator' },
  { key: 'manage_bulk_actions',      label: 'Bulk acties' },
  { key: 'view_audit_log',           label: 'Audit log inzien' },
  { key: 'manage_system_config',     label: 'Systeemconfiguratie' },
  { key: 'manage_substatus_dates',   label: 'Sub-status datum instellingen' },
  { key: 'manage_users',             label: 'Gebruikersbeheer' },
];

function getAdminFeature(user, key) {
  try {
    const f = typeof user.features === 'string' 
      ? JSON.parse(user.features) : (user.features || {});
    if (!f.admin_features) return true; // Standaard alles aan
    return !!f.admin_features[key];
  } catch(e) { return true; }
}
```

### Opslaan van admin features

In de PHP update handler, naast de bestaande features opslag:

```php
// Admin features opslaan
if ($role === 'admin') {
    $currentFeatures = json_decode($features_json, true) ?? [];
    $adminFeatureKeys = [
        'manage_locations', 'manage_departments', 'manage_locations_order',
        'manage_departments_order', 'manage_kiosk_tokens', 'manage_visitors',
        'manage_badges', 'manage_bulk_actions', 'view_audit_log',
        'manage_system_config', 'manage_substatus_dates', 'manage_users'
    ];
    $adminFeatures = [];
    foreach ($adminFeatureKeys as $key) {
        $adminFeatures[$key] = isset($_POST['admin_feature_' . $key]);
    }
    $currentFeatures['admin_features'] = $adminFeatures;
    $features_json = json_encode($currentFeatures);
}
```

---

## Fase 5 — Foutmelding op dashboard

### `admin/dashboard.php`

Voeg toe na de bestaande alert secties:

```php
<?php if (isset($_GET['error']) && $_GET['error'] === 'no_permission'): ?>
<div class="alert alert-error" style="margin-bottom: 20px;">
    ⛔ Je hebt geen toegang tot deze functie. 
    Neem contact op met de SuperAdmin als je denkt dat dit een fout is.
</div>
<?php endif; ?>
```

---

## Volgorde van uitvoering in Claude Code

```
1. Pas admin/auth_helper.php aan — hasAdminFeature() en requireAdminFeature()
2. Voeg requireAdminFeature() toe aan alle beveiligde pagina's (Fase 2)
3. Pas admin/dashboard.php aan — variabelen en tegel checks (Fase 3)
4. Pas admin/users_manage.php aan — admin features sectie in edit formulier (Fase 4)
5. Voeg foutmelding toe aan dashboard (Fase 5)
6. Test met een admin account zonder rechten
7. Bump versie naar 2.1.1
8. Commit en push
```

---

## Startinstructie voor Claude Code

```
Lees eerst /mnt/project/ADMIN_FEATURES_PLAN.md volledig door.

We voegen instelbare admin-rechten toe aan PeopleDisplay.
SuperAdmins kunnen per admin instellen welke beheerfuncties
die admin mag gebruiken. Het bestaande users.features JSON
veld wordt uitgebreid met een admin_features object.

Geen database wijzigingen nodig.

Begin met Fase 1: voeg hasAdminFeature() en requireAdminFeature()
toe aan admin/auth_helper.php.

Daarna Fase 2: voeg requireAdminFeature() toe aan alle beveiligde
pagina's zoals beschreven in het plan.

Volg het plan stap voor stap en commit na elke fase.
```

---

## Notities

- **Backward compatible**: bestaande admins zonder `admin_features` in hun features JSON krijgen standaard overal toegang — niets breekt
- **Versie**: dit wordt versie 2.1.1
- **Testen**: maak een testadmin aan zonder rechten en controleer alle pagina's
- **SuperAdmin**: ziet altijd alles, kan nooit worden geblokkeerd
