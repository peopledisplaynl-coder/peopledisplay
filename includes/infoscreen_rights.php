<?php
/**
 * infoscreen_rights.php
 * Upload naar: /includes/infoscreen_rights.php
 *
 * Infoscreen rights per user (2.10.0). Every user (any role) can get rights for the
 * infoscreen, each part separately, for all locations or only for chosen locations:
 *   users.features.infoscreenRights       = {messages, news, weather, agenda, branding, screens, display, connections}
 *   users.features.infoscreenAllLocations = true|false
 *   users.features.infoscreenLocations    = [location names]
 * "display" (Weergave) and "connections" (Teams/API) apply to the whole organisation and
 * therefore only count with all locations. A SuperAdmin may always do everything.
 *
 * Users saved before 2.10.0 have no infoscreenRights yet; their rights are derived from the
 * old settings (admin right "manage_infoscreen", infoscreenEditor, infoscreenBranding), so
 * nothing changes until the user is saved again with the new tab.
 */

const PD_IS_RIGHTS = [
    'messages'    => ['📝 Berichten', 'Berichten plaatsen, aanpassen, uitzetten en verwijderen', false],
    'news'        => ['📰 Nieuws', 'Nieuwsbronnen en websites koppelen', false],
    'weather'     => ['🌤️ Weer', 'Weerplaats kiezen', false],
    'agenda'      => ['📅 Agenda', 'Agenda\'s koppelen', false],
    'branding'    => ['🖌️ Huisstijl', 'Logo, titel, kleuren en lettertype', false],
    'screens'     => ['🖥️ Schermen', 'Wat de schermen van deze locaties tonen, met voorbeeld (de locaties van een scherm blijven bij de beheerder)', false],
    'display'     => ['🎨 Weergave', 'Instellingen voor alle schermen: tijd per dia, nieuwsbalk, BHV en jarigen (alleen bij alle locaties)', true],
    'connections' => ['🔗 Koppelingen', 'Teams en de Berichten-API (alleen bij alle locaties)', true],
];
const PD_IS_PRESETS = [
    'redacteur'      => ['Redacteur', 'Alleen berichten', ['messages']],
    'hoofdredacteur' => ['Hoofdredacteur', 'Alles voor de eigen locaties', ['messages', 'news', 'weather', 'agenda', 'branding', 'screens']],
    'beheerder'      => ['Beheerder infoscherm', 'Alles, voor alle locaties', ['messages', 'news', 'weather', 'agenda', 'branding', 'screens', 'display', 'connections']],
];
const PD_IS_ADMIN_ROLES = ['admin', 'superadmin', 'employee_manager', 'user_manager'];

/** Rights from users.features (and the role, for users from before 2.10.0). */
function pd_infoscreen_rights_from_features(?array $features, string $role): array
{
    $features = $features ?: [];
    $none = array_fill_keys(array_keys(PD_IS_RIGHTS), false);
    if ($role === 'superadmin') {
        return ['all' => true, 'locations' => [], 'rights' => array_fill_keys(array_keys(PD_IS_RIGHTS), true), 'legacy' => false];
    }
    if (is_array($features['infoscreenRights'] ?? null)) {
        $rights = $none;
        foreach ($features['infoscreenRights'] as $k => $v) {
            if (isset($rights[$k])) $rights[$k] = (bool)$v;
        }
        $all = !empty($features['infoscreenAllLocations']);
        $locs = is_array($features['infoscreenLocations'] ?? null) ? $features['infoscreenLocations'] : [];
        $legacy = false;
    } else {
        // Before 2.10.0: admin right "Infoscherm beheren" = everything; redacteur rights per location
        $adminFeatures = $features['admin_features'] ?? null;
        $manage = in_array($role, PD_IS_ADMIN_ROLES, true)
            && (!is_array($adminFeatures) || !empty($adminFeatures['manage_infoscreen']));
        if ($manage) {
            return ['all' => true, 'locations' => [], 'rights' => array_fill_keys(array_keys(PD_IS_RIGHTS), true), 'legacy' => true];
        }
        $rights = $none;
        $rights['messages'] = !empty($features['infoscreenEditor']);
        $rights['branding'] = !empty($features['infoscreenBranding']);
        $all = false;
        $locs = is_array($features['infoscreenLocations'] ?? null) ? $features['infoscreenLocations']
              : (is_array($features['locations'] ?? null) ? $features['locations'] : []);
        $legacy = true;
    }
    if (!$all) {
        foreach (PD_IS_RIGHTS as $k => [, , $orgWide]) {
            if ($orgWide) $rights[$k] = false;
        }
    }
    $locs = array_values(array_unique(array_filter(array_map('strval', $locs), fn($l) => $l !== '')));
    return ['all' => $all, 'locations' => $all ? [] : $locs, 'rights' => $rights, 'legacy' => $legacy];
}

/** Clean form input of the 📺 Infoscherm tab into the three feature keys. */
function pd_infoscreen_rights_from_post(array $post): array
{
    $picked = array_map('strval', (array)($post['infoscreen_rights'] ?? []));
    $all = ($post['infoscreen_scope'] ?? '') === 'all';
    $rights = [];
    foreach (PD_IS_RIGHTS as $k => [, , $orgWide]) {
        $rights[$k] = in_array($k, $picked, true) && ($all || !$orgWide);
    }
    return [
        'infoscreenRights'       => $rights,
        'infoscreenAllLocations' => $all,
        'infoscreenLocations'    => $all ? [] : array_values(array_unique(array_filter(array_map('strval', (array)($post['infoscreen_locations'] ?? [])), fn($l) => $l !== ''))),
    ];
}

/**
 * What this user may do on the infoscreen: ['all' => bool, 'locations' => [...known names],
 * 'rights' => [...], 'role' => string], or null without any right.
 */
function pd_infoscreen_scope(PDO $db, int $userId): ?array
{
    $stmt = $db->prepare("SELECT role, features FROM users WHERE id = ? AND active = 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $r = pd_infoscreen_rights_from_features(json_decode((string)$row['features'], true), (string)$row['role']);
    if (!in_array(true, $r['rights'], true)) return null;
    if (!$r['all']) {
        $known = $db->query("SELECT location_name FROM locations")->fetchAll(PDO::FETCH_COLUMN);
        $r['locations'] = array_values(array_intersect($r['locations'], array_map('strval', $known)));
    }
    $r['role'] = (string)$row['role'];
    return $r;
}

function pd_infoscreen_scope_can(?array $scope, string $right): bool
{
    return $scope !== null && !empty($scope['rights'][$right]);
}

/**
 * May this scope see (and choose the display of) a screen account with these locations?
 * A screen without locations shows all locations: only for a scope with all locations.
 */
function pd_infoscreen_scope_sees_screen(array $scope, array $screenLocs): bool
{
    if ($scope['all']) return true;
    return $screenLocs && $scope['locations'] && !array_diff($screenLocs, $scope['locations']);
}

/** Infoscreen accounts (screen mode Infoscherm or Slides + infoscherm) with their locations. */
function pd_infoscreen_screen_accounts(PDO $db): array
{
    $out = [];
    try {
        $rows = $db->query("SELECT id, username, display_name, screen_mode, features FROM users
                            WHERE active = 1 AND deleted_at IS NULL AND screen_mode IN ('infoscreen', 'combined')")->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return [];
    }
    foreach ($rows as $u) {
        $f = json_decode((string)$u['features'], true) ?: [];
        $locs = is_array($f['locations'] ?? null) ? array_values(array_filter(array_map('strval', $f['locations']), fn($l) => $l !== '')) : [];
        $out[] = ['id' => (int)$u['id'], 'name' => $u['display_name'] ?: $u['username'], 'locations' => $locs];
    }
    return $out;
}

/** Names of the infoscreen accounts that show an item for these locations ([] = all locations). */
function pd_infoscreen_screens_showing(array $itemLocs, array $screens): array
{
    $names = [];
    foreach ($screens as $s) {
        if (pd_infoscreen_locs_match($itemLocs, $s['locations'])) $names[] = $s['name'];
    }
    return $names;
}

/** Per location: how many infoscreen accounts show it (screens without locations count everywhere). */
function pd_infoscreen_screen_counts(array $locations, array $screens): array
{
    $counts = [];
    foreach ($locations as $l) {
        $counts[$l] = count(array_filter($screens, fn($s) => !$s['locations'] || in_array($l, $s['locations'], true)));
    }
    return $counts;
}
