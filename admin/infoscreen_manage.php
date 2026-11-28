<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * infoscreen_manage.php
 * Upload naar: /admin/infoscreen_manage.php
 *
 * Infoscreen management (2.4.0): own messages, news feeds, weather places, display
 * settings and which screens show the infoscreen. Server-rendered forms (POST → redirect
 * → flash), protected with a session CSRF token.
 *
 * Since 2.10.0 for every user with an infoscreen right (any role): tabs, lists and actions
 * follow the rights and locations of that user (includes/infoscreen_rights.php).
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/version.php';
require_once __DIR__ . '/../includes/infoscreen.php';
require_once __DIR__ . '/../includes/infoscreen_ical.php';
require_once __DIR__ . '/../includes/infoscreen_api.php';
require_once __DIR__ . '/../includes/twofa.php';
require_once __DIR__ . '/../includes/cron_key.php';
require_once __DIR__ . '/auth_helper.php';

requireLogin();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
pd_infoscreen_ensure_schema($db);
$scope = pd_infoscreen_scope($db, (int)$_SESSION['user_id']);
$isAdminRole = in_array($_SESSION['role'] ?? '', PD_IS_ADMIN_ROLES, true);
if ($scope === null) {
    header('Location: ' . ($isAdminRole ? 'dashboard.php' : '../frontpage.php'));
    exit;
}

$appRoot = dirname(__DIR__);
$tabs = [
    'berichten' => '📝 Berichten',
    'nieuws'    => '📰 Nieuws',
    'weer'      => '🌤️ Weer',
    'agenda'    => '📅 Agenda',
    'koppelingen' => '🔗 Koppelingen',
    'weergave'  => '🎨 Weergave',
    'huisstijl' => '🖌️ Huisstijl',
    'schermen'  => '🖥️ Schermen',
];
// Each tab needs its own right (2.10.0)
const PD_IS_TAB_RIGHTS = ['berichten' => 'messages', 'nieuws' => 'news', 'weer' => 'weather', 'agenda' => 'agenda',
    'koppelingen' => 'connections', 'weergave' => 'display', 'huisstijl' => 'branding', 'schermen' => 'screens'];
$tabs = array_filter($tabs, fn($k) => pd_infoscreen_scope_can($scope, PD_IS_TAB_RIGHTS[$k]), ARRAY_FILTER_USE_KEY);
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : array_key_first($tabs);
$colors = array_map(fn($c) => $c[1] ?? PD_BRAND_DEFAULT_PRIMARY, PD_IS_MESSAGE_COLORS); // swatches for lists

function pd_is_redirect(string $tab, string $flash, bool $ok = true, string $extra = ''): void
{
    $_SESSION['pd_flash'] = ($ok ? '✅ ' : '❌ ') . $flash;
    header('Location: infoscreen_manage.php?tab=' . urlencode($tab) . $extra);
    exit;
}

function pd_is_dt(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

// Locations (names, as used everywhere in PeopleDisplay)
try {
    $locations = $db->query("SELECT location_name FROM locations WHERE active = 1 ORDER BY sort_order, location_name")->fetchAll(PDO::FETCH_COLUMN);
} catch (\Throwable $e) {
    $locations = $db->query("SELECT location_name FROM locations ORDER BY location_name")->fetchAll(PDO::FETCH_COLUMN);
}

// Locations this user may choose; items for other (or all) locations are read-only
$pickable = $scope['all'] ? $locations : array_values(array_intersect($locations, $scope['locations']));
$canItem = fn(array $itemLocs): bool => pd_infoscreen_scope_allows($scope, $itemLocs);
$sourceRight = ['rss' => 'news', 'weather' => 'weather', 'ical' => 'agenda'];
$sourceTab = ['rss' => 'nieuws', 'weather' => 'weer', 'ical' => 'agenda'];
$need = function (string $right, string $tabKey) use ($scope): void {
    if (!pd_infoscreen_scope_can($scope, $right)) pd_is_redirect($tabKey, 'Hiervoor heeft u geen rechten.', false);
};
$loadSource = function (int $id) use ($db, $scope, $canItem, $sourceRight, $sourceTab): array {
    $stmt = $db->prepare("SELECT * FROM infoscreen_sources WHERE id = ?");
    $stmt->execute([$id]);
    $src = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$src) pd_is_redirect('berichten', 'Bron niet gevonden.', false);
    if (!pd_infoscreen_scope_can($scope, $sourceRight[$src['type']] ?? 'news') || !$canItem(pd_infoscreen_locs($src['locations']))) {
        pd_is_redirect($sourceTab[$src['type']] ?? 'nieuws', 'Deze bron is ook voor andere locaties; die past de beheerder aan.', false);
    }
    return $src;
};
$loadMessage = function (int $id) use ($db, $canItem): array {
    $stmt = $db->prepare("SELECT * FROM infoscreen_messages WHERE id = ?");
    $stmt->execute([$id]);
    $m = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$m) pd_is_redirect('berichten', 'Bericht niet gevonden.', false);
    if (!$canItem(pd_infoscreen_locs($m['locations']))) pd_is_redirect('berichten', 'Dit bericht is ook voor andere locaties; dat past de beheerder aan.', false);
    return $m;
};

// ─────────────────────────────────────────────────────────────────────────────
// POST actions
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $back = $_POST['tab'] ?? $tab;
    if (!isset($tabs[$back])) $back = 'berichten';

    if (!pd_infoscreen_csrf_ok($_POST['csrf'] ?? null)) {
        pd_is_redirect($back, 'De pagina was verlopen. Probeer het opnieuw.', false);
    }

    try {
        switch ($action) {
            case 'save_message':
                $need('messages', 'berichten');
                $id = (int)($_POST['id'] ?? 0);
                $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 200);
                if ($title === '') pd_is_redirect('berichten', 'Vul een titel in.', false, $id ? '&edit=' . $id : '&new=1');
                $body = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 2000);
                $color = pd_infoscreen_color_from_post($_POST);
                [$imgLayout, $imgFit, $imgSize] = pd_infoscreen_image_options_from_post($_POST);
                $starts = pd_is_dt($_POST['starts_at'] ?? '');
                $ends = pd_is_dt($_POST['ends_at'] ?? '');
                if ($starts && $ends && $ends <= $starts) pd_is_redirect('berichten', 'De einddatum moet na de begindatum liggen.', false, $id ? '&edit=' . $id : '&new=1');
                $duration = trim((string)($_POST['duration_seconds'] ?? '')) === '' ? null : max(5, min(300, (int)$_POST['duration_seconds']));
                $locs = pd_infoscreen_locs_from_post($pickable, $scope['all']);
                if ($locs === false) pd_is_redirect('berichten', 'Kies minimaal één locatie.', false, $id ? '&edit=' . $id : '&new=1');
                $priority = !empty($_POST['priority']) ? 1 : 0;
                $active = !empty($_POST['active']) ? 1 : 0;

                $old = $id ? $loadMessage($id) : null;
                $image = $old['image_path'] ?? null;
                if (!empty($_POST['remove_image'])) $image = null;
                if (!empty($_FILES['image']['name'])) {
                    $image = pd_infoscreen_store_upload($_FILES['image'], $appRoot);
                }

                if ($id) {
                    $db->prepare("UPDATE infoscreen_messages SET title = ?, body = ?, image_path = ?, image_layout = ?, image_fit = ?, image_size = ?,
                                  locations = ?, starts_at = ?, ends_at = ?, duration_seconds = ?, priority = ?, color = ?, active = ? WHERE id = ?")
                       ->execute([$title, $body, $image, $imgLayout, $imgFit, $imgSize, $locs, $starts, $ends, $duration, $priority, $color, $active, $id]);
                    if (($old['image_path'] ?? null) && $old['image_path'] !== $image) pd_infoscreen_delete_image($db, $old['image_path'], $appRoot);
                    pd_is_redirect('berichten', 'Bericht opgeslagen.');
                }
                $db->prepare("INSERT INTO infoscreen_messages (title, body, image_path, image_layout, image_fit, image_size, locations, starts_at, ends_at, duration_seconds, priority, color, active, created_by)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$title, $body, $image, $imgLayout, $imgFit, $imgSize, $locs, $starts, $ends, $duration, $priority, $color, $active, (int)$_SESSION['user_id']]);
                pd_is_redirect('berichten', 'Bericht toegevoegd.');

            case 'toggle_message':
                $need('messages', 'berichten');
                $loadMessage((int)$_POST['id']);
                $db->prepare("UPDATE infoscreen_messages SET active = 1 - active WHERE id = ?")->execute([(int)$_POST['id']]);
                pd_is_redirect('berichten', 'Bericht bijgewerkt.');

            case 'delete_message':
                $need('messages', 'berichten');
                $path = $loadMessage((int)$_POST['id'])['image_path'];
                $db->prepare("DELETE FROM infoscreen_messages WHERE id = ?")->execute([(int)$_POST['id']]);
                pd_infoscreen_delete_image($db, $path ?: null, $appRoot);
                pd_is_redirect('berichten', 'Bericht verwijderd.');

            case 'save_source':
                $id = (int)($_POST['id'] ?? 0);
                $type = in_array($_POST['type'] ?? '', ['rss', 'weather', 'ical'], true) ? $_POST['type'] : 'rss';
                $tabFor = ['rss' => 'nieuws', 'weather' => 'weer', 'ical' => 'agenda'][$type];
                $need($sourceRight[$type], $tabFor);
                if ($id) {
                    $oldSrc = $loadSource($id);
                    if ($oldSrc['type'] !== $type) pd_is_redirect($tabFor, 'Bron niet gevonden.', false);
                }
                $editQs = $id ? '&edit=' . $id : '&new=1';
                $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 150);
                if ($name === '') pd_is_redirect($tabFor, 'Vul een naam in.', false, $editQs);
                $locs = pd_infoscreen_locs_from_post($pickable, $scope['all']);
                if ($locs === false) pd_is_redirect($tabFor, 'Kies minimaal één locatie.', false, $editQs);
                $url = $provider = $lat = $lon = $attribution = null;
                $maxItems = 8;
                $showImages = 1;
                $webCfg = null;
                if ($type === 'rss' && ($_POST['source_kind'] ?? '') === 'web') {
                    // Website without a feed (2.7.0)
                    $editQs = $id ? '&edit=' . $id : '&new=web';
                    $url = pd_infoscreen_clean_url($_POST['url'] ?? '');
                    if (!$url) pd_is_redirect($tabFor, 'Vul een geldig webadres van de nieuwspagina in (begint met https://).', false, $editQs);
                    $provider = 'web';
                    $mode = in_array($_POST['web_mode'] ?? '', ['auto', 'feed', 'list', 'css'], true) ? $_POST['web_mode'] : 'auto';
                    $webCfg = ['mode' => $mode, 'css' => []];
                    foreach (['item', 'title', 'link', 'image', 'date'] as $f) {
                        $v = mb_substr(trim((string)($_POST['css'][$f] ?? '')), 0, 200);
                        if ($v !== '') $webCfg['css'][$f] = $v;
                    }
                    if ($mode === 'feed') {
                        $webCfg['feed'] = pd_infoscreen_clean_url($_POST['web_feed'] ?? '');
                        if (!$webCfg['feed']) pd_is_redirect($tabFor, 'Kies eerst via "Zoek berichten" welke berichten u wilt tonen.', false, $editQs);
                    } elseif ($mode === 'list') {
                        $webCfg['xpath'] = mb_substr(trim((string)($_POST['web_xpath'] ?? '')), 0, 1000);
                        if ($webCfg['xpath'] === '' || !str_starts_with($webCfg['xpath'], '//')) pd_is_redirect($tabFor, 'Kies eerst via "Zoek berichten" welke berichten u wilt tonen.', false, $editQs);
                    } elseif ($mode === 'css') {
                        require_once __DIR__ . '/../includes/infoscreen_web.php';
                        if (empty($webCfg['css']['item']) || !pd_is_web_css_to_xpath($webCfg['css']['item'])) {
                            pd_is_redirect($tabFor, 'Vul een geldige CSS-selector in voor één bericht.', false, $editQs);
                        }
                        try { pd_is_web_css_sel($webCfg['css']); } catch (RuntimeException $e) { pd_is_redirect($tabFor, $e->getMessage(), false, $editQs); }
                    }
                    $maxItems = max(1, min(30, (int)($_POST['max_items'] ?? 8)));
                    $showImages = !empty($_POST['show_images']) ? 1 : 0;
                    $attribution = mb_substr(trim((string)($_POST['attribution'] ?? '')), 0, 200) ?: null;
                } elseif ($type === 'rss') {
                    $url = pd_infoscreen_clean_url($_POST['url'] ?? '');
                    if (!$url) pd_is_redirect($tabFor, 'Vul een geldig webadres van de feed in (begint met https://).', false, $editQs);
                    $maxItems = max(1, min(30, (int)($_POST['max_items'] ?? 8)));
                    $showImages = !empty($_POST['show_images']) ? 1 : 0;
                    $attribution = mb_substr(trim((string)($_POST['attribution'] ?? '')), 0, 200) ?: null;
                } elseif ($type === 'ical') {
                    $url = pd_infoscreen_clean_url(pd_ical_url((string)($_POST['url'] ?? '')));
                    if (!$url) pd_is_redirect($tabFor, 'Vul het webadres van de agenda in (begint met https:// of webcal://).', false, $editQs);
                    $showImages = 0;
                    $attribution = mb_substr(trim((string)($_POST['attribution'] ?? '')), 0, 200) ?: null;
                } else {
                    $provider = isset(PD_IS_WEATHER_PROVIDERS[$_POST['provider'] ?? '']) ? $_POST['provider'] : 'buienradar';
                    $lat = str_replace(',', '.', trim((string)($_POST['latitude'] ?? '')));
                    $lon = str_replace(',', '.', trim((string)($_POST['longitude'] ?? '')));
                    if (!is_numeric($lat) || !is_numeric($lon) || abs((float)$lat) > 90 || abs((float)$lon) > 180) {
                        pd_is_redirect($tabFor, 'Vul een geldige breedte- en lengtegraad in (gebruik "Zoek plaats").', false, $editQs);
                    }
                    if ($provider === 'buienradar' && ((float)$lat < 50 || (float)$lat > 54 || (float)$lon < 2.5 || (float)$lon > 7.5)) {
                        pd_is_redirect($tabFor, 'Buienradar heeft alleen meetstations in Nederland. Kies Open-Meteo voor een plaats daarbuiten.', false, $editQs);
                    }
                }
                $active = !empty($_POST['active']) ? 1 : 0;
                if ($id) {
                    $db->prepare("UPDATE infoscreen_sources SET name = ?, url = ?, provider = ?, latitude = ?, longitude = ?, locations = ?, max_items = ?,
                                  show_images = ?, attribution = ?, active = ?, last_fetch_at = NULL WHERE id = ? AND type = ?")
                       ->execute([$name, $url, $provider, $lat, $lon, $locs, $maxItems, $showImages, $attribution, $active, $id, $type]);
                } else {
                    $db->prepare("INSERT INTO infoscreen_sources (type, name, url, provider, latitude, longitude, locations, max_items, show_images, attribution, active)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                       ->execute([$type, $name, $url, $provider, $lat, $lon, $locs, $maxItems, $showImages, $attribution, $active]);
                    $id = (int)$db->lastInsertId();
                }
                if ($type === 'rss') {
                    // Website settings live in data_json; a plain feed has none
                    $db->prepare("UPDATE infoscreen_sources SET provider = ?, data_json = ? WHERE id = ?")
                       ->execute([$provider, $webCfg ? json_encode($webCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, $id]);
                }
                // Fetch right away so the admin sees whether it works
                $stmt = $db->prepare("SELECT * FROM infoscreen_sources WHERE id = ?");
                $stmt->execute([$id]);
                $result = pd_infoscreen_fetch_source($db, $stmt->fetch(PDO::FETCH_ASSOC));
                pd_is_redirect($tabFor, 'Opgeslagen. ' . ($result['ok'] ? $result['message'] : 'Ophalen lukte nog niet: ' . $result['message']), $result['ok']);

            case 'fetch_source':
                $source = $loadSource((int)$_POST['id']);
                $result = pd_infoscreen_fetch_source($db, $source);
                pd_is_redirect($back, $source['name'] . ': ' . $result['message'], $result['ok']);

            case 'toggle_source':
                $loadSource((int)$_POST['id']);
                $db->prepare("UPDATE infoscreen_sources SET active = 1 - active WHERE id = ?")->execute([(int)$_POST['id']]);
                pd_is_redirect($back, 'Bron bijgewerkt.');

            case 'delete_source':
                $loadSource((int)$_POST['id']);
                $db->prepare("DELETE FROM infoscreen_items WHERE source_id = ?")->execute([(int)$_POST['id']]);
                $db->prepare("DELETE FROM infoscreen_events WHERE source_id = ?")->execute([(int)$_POST['id']]);
                $db->prepare("DELETE FROM infoscreen_sources WHERE id = ?")->execute([(int)$_POST['id']]);
                pd_is_redirect($back, 'Bron verwijderd.');

            case 'create_key':
                $need('connections', 'koppelingen');
                $type = ($_POST['type'] ?? '') === 'teams' ? 'teams' : 'api';
                $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100);
                if ($name === '') pd_is_redirect('koppelingen', 'Vul een naam in.', false, '&new=' . $type);
                if ($type === 'teams' && !preg_match('/^[\p{L}\p{N} _-]{1,40}$/u', $name)) {
                    pd_is_redirect('koppelingen', 'Gebruik voor een Teams-koppeling een korte naam zonder leestekens, bijv. Infoscherm.', false, '&new=teams');
                }
                $locs = pd_infoscreen_locs_from_post($locations);
                $days = max(1, min(90, (int)($_POST['default_days'] ?? 7)));
                $color = in_array($_POST['color'] ?? '', PD_IS_COLORS, true) ? $_POST['color'] : 'blauw';
                $hash = $hint = null;
                if ($type === 'api') {
                    [$key, $hash, $hint] = pd_is_api_new_key();
                }
                $db->prepare("INSERT INTO infoscreen_api_keys (type, name, key_hash, key_hint, locations, default_days, color, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$type, $name, $hash, $hint, $locs, $days, $color, (int)$_SESSION['user_id']]);
                $newId = (int)$db->lastInsertId();
                if ($type === 'api') {
                    $_SESSION['pd_is_new_key'] = ['id' => $newId, 'key' => $key]; // shown once
                    pd_is_redirect('koppelingen', 'Koppeling aangemaakt. Kopieer de sleutel nu: hij wordt maar één keer getoond.');
                }
                pd_is_redirect('koppelingen', 'Teams-koppeling aangemaakt. Maak nu de uitgaande webhook in Teams en vul het beveiligingstoken in.', true, '&setup=' . $newId);

            case 'save_teams_token':
                $need('connections', 'koppelingen');
                $id = (int)($_POST['id'] ?? 0);
                $token = preg_replace('/\s+/', '', (string)($_POST['token'] ?? ''));
                $raw = base64_decode($token, true);
                if ($raw === false || strlen($raw) < 16) {
                    pd_is_redirect('koppelingen', 'Dit lijkt geen beveiligingstoken van Teams. Kopieer het token uit het venster dat Teams toonde na "Maken".', false, '&setup=' . $id);
                }
                $db->prepare("UPDATE infoscreen_api_keys SET secret_enc = ? WHERE id = ? AND type = 'teams'")->execute([pd_2fa_encrypt($token), $id]);
                pd_is_redirect('koppelingen', 'Beveiligingstoken opgeslagen. Typ nu in het Teams-kanaal: @naam help');

            case 'new_api_key':
                $need('connections', 'koppelingen');
                [$key, $hash, $hint] = pd_is_api_new_key();
                $db->prepare("UPDATE infoscreen_api_keys SET key_hash = ?, key_hint = ? WHERE id = ? AND type = 'api'")->execute([$hash, $hint, (int)$_POST['id']]);
                $_SESSION['pd_is_new_key'] = ['id' => (int)$_POST['id'], 'key' => $key];
                pd_is_redirect('koppelingen', 'Nieuwe sleutel gemaakt. De oude sleutel werkt niet meer.');

            case 'toggle_key':
                $need('connections', 'koppelingen');
                $db->prepare("UPDATE infoscreen_api_keys SET active = 1 - active WHERE id = ?")->execute([(int)$_POST['id']]);
                pd_is_redirect('koppelingen', 'Koppeling bijgewerkt.');

            case 'delete_key':
                $need('connections', 'koppelingen');
                $db->prepare("UPDATE infoscreen_messages SET api_key_id = NULL WHERE api_key_id = ?")->execute([(int)$_POST['id']]);
                $db->prepare("DELETE FROM infoscreen_api_keys WHERE id = ?")->execute([(int)$_POST['id']]);
                pd_is_redirect('koppelingen', 'Koppeling verwijderd. Berichten die al geplaatst waren, blijven staan tot hun einddatum.');

            case 'save_brand':
            case 'reset_brand':
                $need('branding', 'huisstijl');
                $loc = (string)($_POST['loc'] ?? '');
                if (!in_array($loc, $scope['all'] ? array_merge([''], $locations) : $pickable, true)) {
                    pd_is_redirect('huisstijl', 'De huisstijl van deze locatie mag u niet aanpassen.', false);
                }
                $qs = '&loc=' . rawurlencode($loc);
                if ($action === 'reset_brand') {
                    pd_branding_reset($db, $loc, $appRoot);
                    pd_is_redirect('huisstijl', $loc === '' ? 'Huisstijl van de organisatie verwijderd.' : 'Eigen huisstijl verwijderd: de locatie volgt weer de organisatie.', true, $qs);
                }
                pd_branding_save($db, $loc, $_POST, $_FILES, $appRoot, (int)$_SESSION['user_id']);
                pd_is_redirect('huisstijl', 'Huisstijl opgeslagen. Open schermen nemen dit binnen 5 minuten over.', true, $qs);

            case 'save_settings':
                $need('display', 'weergave');
                pd_infoscreen_save_settings($db, $_POST);
                pd_is_redirect('weergave', 'Weergave opgeslagen. Open schermen nemen dit binnen 5 minuten over.');

            case 'save_screens':
                $need('screens', 'schermen');
                $modes = $_POST['screen_mode'] ?? [];
                // Only screen accounts within this user's locations
                $allowedScreens = [];
                foreach ($db->query("SELECT id, features FROM users WHERE deleted_at IS NULL")->fetchAll(PDO::FETCH_ASSOC) as $u) {
                    $uf = json_decode((string)$u['features'], true) ?: [];
                    $ul = is_array($uf['locations'] ?? null) ? array_values(array_filter(array_map('strval', $uf['locations']))) : [];
                    if (pd_infoscreen_scope_sees_screen($scope, $ul)) $allowedScreens[(int)$u['id']] = true;
                }
                $enabled = $_POST['can_show'] ?? [];
                $stmt = $db->prepare("UPDATE users SET screen_mode = ?, can_show_presentation = ? WHERE id = ? AND deleted_at IS NULL");
                $partsInput = $_POST['parts'] ?? [];
                $featStmt = $db->prepare("SELECT features FROM users WHERE id = ?");
                $featSave = $db->prepare("UPDATE users SET features = ? WHERE id = ?");
                foreach ($modes as $userId => $mode) {
                    if (!in_array($mode, PD_IS_MODES, true) || !isset($allowedScreens[(int)$userId])) continue;
                    $stmt->execute([$mode, !empty($enabled[$userId]) ? 1 : 0, (int)$userId]);
                    // Per-screen choice for birthdays and BHV (2.8.1), stored in users.features
                    $featStmt->execute([(int)$userId]);
                    $feat = json_decode((string)$featStmt->fetchColumn(), true) ?: [];
                    $parts = pd_infoscreen_screen_parts_clean(is_array($partsInput[$userId] ?? null) ? $partsInput[$userId] : []);
                    // Huisstijl of this screen (2.9.0): automatic, organisation or one location
                    $brandIn = $_POST['brand'][$userId] ?? '*auto*';
                    $brand = $brandIn === '*org*' ? '' : (in_array($brandIn, $pickable, true) ? $brandIn : null);
                    if ($parts === ($feat['infoscreenParts'] ?? []) && $brand === ($feat['infoscreenBrand'] ?? null)) continue;
                    if ($parts) $feat['infoscreenParts'] = $parts; else unset($feat['infoscreenParts']);
                    if ($brand !== null) $feat['infoscreenBrand'] = $brand; else unset($feat['infoscreenBrand']);
                    $featSave->execute([json_encode($feat, JSON_UNESCAPED_UNICODE), (int)$userId]);
                }
                pd_is_redirect('schermen', 'Schermen opgeslagen. Een scherm neemt de keuze over na het opnieuw laden van de pagina.');
        }
    } catch (RuntimeException $e) {
        pd_is_redirect($back, $e->getMessage(), false);
    } catch (\Throwable $e) {
        error_log('infoscreen_manage: ' . $e->getMessage());
        pd_is_redirect($back, 'Er ging iets mis bij het opslaan.', false);
    }
    pd_is_redirect($back, 'Onbekende actie.', false);
}

// ─────────────────────────────────────────────────────────────────────────────
// Data for the page
// ─────────────────────────────────────────────────────────────────────────────
$flash = $_SESSION['pd_flash'] ?? '';
unset($_SESSION['pd_flash']);
$csrf = pd_infoscreen_csrf_token();
$settings = pd_infoscreen_settings($db);
$editId = (int)($_GET['edit'] ?? 0);
$showForm = $editId > 0 || !empty($_GET['new']);

// Visible: what can appear on this user's screens (own locations and "all locations"); editable: within own locations
$visibleItem = fn(array $itemLocs): bool => $scope['all'] || ($scope['locations'] && pd_infoscreen_locs_match($itemLocs, $scope['locations']));
$withFlags = function (array $rows) use ($visibleItem, $canItem): array {
    $out = [];
    foreach ($rows as $r) {
        $l = pd_infoscreen_locs($r['locations']);
        if (!$visibleItem($l)) continue;
        $r['_locs'] = $l;
        $r['_editable'] = $canItem($l);
        $out[] = $r;
    }
    return $out;
};
$messages = $withFlags($db->query("SELECT * FROM infoscreen_messages ORDER BY active DESC, priority DESC, COALESCE(starts_at, created_at) DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC));
$sources = $withFlags($db->query("SELECT s.*, (SELECT COUNT(*) FROM infoscreen_items i WHERE i.source_id = s.id) AS item_count,
                              (SELECT COUNT(*) FROM infoscreen_items i WHERE i.source_id = s.id AND i.image_url IS NOT NULL) AS image_count
                       FROM infoscreen_sources s ORDER BY s.type, s.sort_order, s.id")->fetchAll(PDO::FETCH_ASSOC));
// Where is it shown (2.10.0): infoscreen accounts and how many show each location
$screenAccounts = pd_infoscreen_screen_accounts($db);
$screenCounts = pd_infoscreen_screen_counts($pickable, $screenAccounts);
$shownOn = function (array $itemLocs) use ($screenAccounts): string {
    $names = pd_infoscreen_screens_showing($itemLocs, $screenAccounts);
    return '<span class="pill scr" title="' . htmlspecialchars($names ? 'Te zien op: ' . implode(', ', $names) : 'Geen infoscherm toont dit (zie Schermen)', ENT_QUOTES, 'UTF-8') . '">📺 '
         . count($names) . '</span>';
};
$readOnly = fn(array $itemLocs): string => '<span class="pill lock" title="' . ($itemLocs ? 'Ook voor locaties die niet bij u horen' : 'Voor alle locaties: dat past de beheerder aan') . '">🔒 Alleen lezen</span>';
$rssSources = array_values(array_filter($sources, fn($s) => $s['type'] === 'rss'));
$weatherSources = array_values(array_filter($sources, fn($s) => $s['type'] === 'weather'));
$icalSources = array_values(array_filter($sources, fn($s) => $s['type'] === 'ical'));
$eventCounts = [];
foreach ($db->query("SELECT source_id, COUNT(*) AS n, SUM(starts_at < NOW() + INTERVAL 7 DAY) AS week FROM infoscreen_events GROUP BY source_id") as $r) {
    $eventCounts[(int)$r['source_id']] = $r;
}
$keys = $db->query("SELECT k.*, (SELECT COUNT(*) FROM infoscreen_messages m WHERE m.api_key_id = k.id AND (m.ends_at IS NULL OR m.ends_at >= NOW())) AS live_count
                    FROM infoscreen_api_keys k ORDER BY k.type, k.name")->fetchAll(PDO::FETCH_ASSOC);
$keyNames = array_column($keys, 'name', 'id');
$newKey = $_SESSION['pd_is_new_key'] ?? null;
unset($_SESSION['pd_is_new_key']);

$editMessage = null;
$editSource = null;
if ($editId && $tab === 'berichten') {
    foreach ($messages as $m) if ((int)$m['id'] === $editId && $m['_editable']) $editMessage = $m;
    if (!$editMessage) $showForm = false;
}
if ($editId && in_array($tab, ['nieuws', 'weer', 'agenda'], true)) {
    foreach ($sources as $s) if ((int)$s['id'] === $editId && $s['_editable']) $editSource = $s;
    if (!$editSource) $showForm = false;
}
if ($showForm && !$editId && !$scope['all'] && !$pickable) $showForm = false;

// Huisstijl (2.9.0)
$brandRows = pd_branding_rows($db);
$orgBrand = pd_branding_resolve($brandRows, [], $settings['theme'], '..', $settings['title']);
$brandLocs = $scope['all'] ? array_merge([''], $locations) : $pickable;
$brandLoc = (string)($_GET['loc'] ?? ($brandLocs[0] ?? ''));
if (!in_array($brandLoc, $brandLocs, true)) $brandLoc = $brandLocs[0] ?? '';

$screens = [];
if ($tab === 'schermen') {
    $screens = [];
    foreach ($db->query("SELECT id, username, display_name, role, can_show_presentation, presentation_id, screen_mode, presentation_idle_seconds, features
                         FROM users WHERE deleted_at IS NULL AND active = 1 ORDER BY role = 'user' DESC, display_name, username")->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $uf = json_decode((string)$u['features'], true) ?: [];
        $u['_locs'] = is_array($uf['locations'] ?? null) ? array_values(array_filter(array_map('strval', $uf['locations']))) : [];
        if (pd_infoscreen_scope_sees_screen($scope, $u['_locs'])) $screens[] = $u;
    }
    $globalPresentation = (string)($db->query("SELECT presentationID FROM config WHERE id = 1")->fetchColumn() ?: '');
}

function pd_is_status(array $m): array
{
    $now = date('Y-m-d H:i:s');
    if (!(int)$m['active']) return ['Uit', 'off'];
    if ($m['starts_at'] && $m['starts_at'] > $now) return ['Gepland', 'planned'];
    if ($m['ends_at'] && $m['ends_at'] < $now) return ['Verlopen', 'expired'];
    return ['Op het scherm', 'live'];
}

function pd_is_period(array $m): string
{
    $f = fn($d) => date('j-n-Y H:i', strtotime($d));
    if (!$m['starts_at'] && !$m['ends_at']) return 'Altijd';
    if ($m['starts_at'] && $m['ends_at']) return $f($m['starts_at']) . ' t/m ' . $f($m['ends_at']);
    return $m['starts_at'] ? 'Vanaf ' . $f($m['starts_at']) : 'Tot en met ' . $f($m['ends_at']);
}

function pd_is_ago(?string $d): string
{
    if (!$d) return 'nog nooit';
    $min = (int)round((time() - strtotime($d)) / 60);
    if ($min < 1) return 'zojuist';
    if ($min < 60) return $min . ' min geleden';
    if ($min < 1440) return round($min / 60) . ' uur geleden';
    return date('j-n-Y H:i', strtotime($d));
}

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$dtValue = fn($v) => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';
$cronPath = $appRoot . '/cron/cron_infoscreen.php';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$appUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'jouwdomein.nl') . rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Infoscherm — PeopleDisplay</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; color: #1f2937; }
    .back-link { display: inline-block; margin-bottom: 16px; color: #fff; text-decoration: none; font-weight: 600; }
    .back-link:hover { text-decoration: underline; }
    .container { max-width: 1150px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px 30px 34px; box-shadow: 0 10px 40px rgba(0,0,0,.2); }
    .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
    h1 { font-size: 26px; color: #1f2937; }
    .subtitle { color: #6b7280; font-size: 14px; margin-top: 4px; }
    .tabs { display: flex; gap: 4px; border-bottom: 2px solid #e5e7eb; margin-bottom: 22px; flex-wrap: wrap; }
    .tabs a { padding: 10px 16px; text-decoration: none; color: #4b5563; font-weight: 600; font-size: 14px; border-radius: 8px 8px 0 0; margin-bottom: -2px; border-bottom: 2px solid transparent; }
    .tabs a:hover { background: #f3f4f6; }
    .tabs a.active { color: #5b46d6; border-bottom-color: #667eea; background: #f5f3ff; }
    .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-weight: 500; font-size: 14px; }
    .alert.ok { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert.err { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .screen-parts { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 4px 8px; align-items: center; min-width: 250px; }
    .screen-parts label { font-size: 12px; color: #6b7280; white-space: nowrap; }
    .screen-parts select { padding: 4px 6px; font-size: 13px; }
    .scope-box { background: #ecfdf5; border: 1.5px solid #6ee7b7; color: #064e3b; border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; font-size: 14px; }
    .loc-badge { display: inline-block; padding: 2px 9px; border-radius: 99px; background: #fff; border: 1px solid #d1d5db; font-size: 12.5px; font-weight: 600; color: #374151; margin: 2px 2px 2px 0; white-space: nowrap; }
    .loc-badge.all { background: #312e81; border-color: #312e81; color: #fff; }
    .pill.scr { background: #eef2ff; color: #3730a3; cursor: help; }
    .pill.lock { background: #f3f4f6; color: #4b5563; cursor: help; }
    .scr-count { font-size: 11.5px; color: #4338ca; background: #eef2ff; border-radius: 99px; padding: 0 6px; margin-left: 2px; }
    details.rules { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 14px; margin-bottom: 18px; }
    details.rules summary { cursor: pointer; font-weight: 700; color: #312e81; }
    .rules-table { margin: 10px 0 6px; font-size: 13px; }
    .rules-table th, .rules-table td { padding: 7px 9px; vertical-align: top; text-align: left; }
    .rules-table tbody th { white-space: nowrap; color: #374151; }
    .loc-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; }
    .loc-tabs a { padding: 6px 12px; border-radius: 99px; border: 1.5px solid #d1d5db; text-decoration: none; color: #374151; font-size: 13.5px; font-weight: 600; background: #fff; }
    .loc-tabs a.active { background: #312e81; border-color: #312e81; color: #fff; }
    .info { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 12px 16px; border-radius: 6px; font-size: 13.5px; color: #1e3a5f; line-height: 1.55; margin-bottom: 18px; }
    .info.warn { background: #fffbeb; border-left-color: #f59e0b; color: #5b4204; }
    .info strong { color: inherit; }
    .info code, td code { background: rgba(0,0,0,.06); padding: 1px 5px; border-radius: 4px; font-size: 12.5px; word-break: break-all; }
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border: none; border-radius: 7px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; transition: all .2s; white-space: nowrap; }
    .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; }
    .btn-primary:hover { box-shadow: 0 4px 14px rgba(102,126,234,.45); transform: translateY(-1px); }
    .btn-light { background: #f3f4f6; color: #374151; }
    .btn-light:hover { background: #e5e7eb; }
    .btn-sm { padding: 6px 10px; font-size: 12.5px; }
    .btn-danger { background: #fee2e2; color: #b91c1c; }
    .btn-danger:hover { background: #fecaca; }
    .toolbar { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 14px; flex-wrap: wrap; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; padding: 8px 10px; border-bottom: 2px solid #e5e7eb; }
    td { padding: 10px; border-bottom: 1px solid #f0f1f3; vertical-align: middle; }
    tr:hover td { background: #fafafa; }
    .thumb { width: 64px; height: 40px; border-radius: 6px; object-fit: cover; display: block; background: #e5e7eb; }
    .swatch { display: inline-block; width: 12px; height: 12px; border-radius: 3px; vertical-align: -1px; margin-right: 6px; }
    .pill { display: inline-block; padding: 3px 9px; border-radius: 99px; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .pill.live { background: #d1fae5; color: #065f46; } .pill.planned { background: #dbeafe; color: #1e40af; }
    .pill.expired { background: #f3f4f6; color: #6b7280; } .pill.off { background: #f3f4f6; color: #9ca3af; }
    .pill.prio { background: #fee2e2; color: #b91c1c; } .pill.src { background: #ede9fe; color: #5b21b6; } .pill.ok { background: #d1fae5; color: #065f46; } .pill.err { background: #fee2e2; color: #991b1b; }
    .muted { color: #6b7280; font-size: 12.5px; }
    .actions { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: nowrap; }
    .actions form { display: inline; }
    .empty { text-align: center; padding: 36px 10px; color: #6b7280; }
    .empty .big { font-size: 40px; margin-bottom: 8px; }
    .panel { border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px 22px; margin-bottom: 22px; background: #fcfcfd; }
    .panel h2 { font-size: 18px; margin-bottom: 14px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 20px; }
    .grid .full { grid-column: 1 / -1; }
    label.f { display: block; font-weight: 600; font-size: 13.5px; margin-bottom: 6px; color: #374151; }
    input[type=text], input[type=url], input[type=number], input[type=datetime-local], select, textarea {
        width: 100%; padding: 9px 11px; border: 1.5px solid #d1d5db; border-radius: 7px; font-size: 14px; font-family: inherit; background: #fff; }
    input:focus, select:focus, textarea:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,.15); }
    textarea { min-height: 110px; resize: vertical; line-height: 1.5; }
    .help { font-size: 12.5px; color: #6b7280; margin-top: 5px; line-height: 1.45; }
    .check { display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer; }
    .check input { width: 17px; height: 17px; }
    .colors { display: flex; gap: 8px; flex-wrap: wrap; }
    .colors label { cursor: pointer; }
    .colors input { position: absolute; opacity: 0; }
    .colors span { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px 6px 8px; border-radius: 99px; border: 2px solid #e5e7eb; font-size: 13px; font-weight: 600; }
    .colors span i { width: 16px; height: 16px; border-radius: 50%; display: inline-block; }
    .colors input:checked + span { border-color: #374151; background: #f9fafb; }
    .form-actions { display: flex; gap: 10px; margin-top: 18px; }
    .provider { border: 1.5px solid #e5e7eb; border-radius: 9px; padding: 12px 14px; display: flex; gap: 10px; cursor: pointer; margin-bottom: 8px; }
    .provider:has(input:checked) { border-color: #667eea; background: #f5f3ff; }
    .provider strong { display: block; margin-bottom: 2px; }
    .geo-results { margin-top: 8px; display: flex; flex-direction: column; gap: 4px; }
    .geo-results button { text-align: left; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 7px 10px; cursor: pointer; font-size: 13px; }
    .geo-results button:hover { background: #eef2ff; }
    .preview-links { display: flex; gap: 8px; flex-wrap: wrap; }
    .current-img { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
    .current-img img { height: 70px; border-radius: 6px; }
    .wx-now { font-size: 13px; }
    .web-preview { border: 1.5px dashed #d1d5db; border-radius: 8px; padding: 12px; background: #fff; }
    .web-opt { display: flex; gap: 10px; border: 1.5px solid #e5e7eb; border-radius: 9px; padding: 10px 12px; margin-bottom: 8px; cursor: pointer; }
    .web-opt.on { border-color: #667eea; background: #f5f3ff; }
    .web-opt input { margin-top: 3px; }
    .web-opt-body { flex: 1; min-width: 0; }
    .web-items { list-style: none; margin: 8px 0 0; display: flex; flex-direction: column; gap: 6px; }
    .web-items li { display: flex; gap: 10px; align-items: flex-start; font-size: 13px; }
    .web-items img, .web-items .noimg { width: 64px; height: 40px; object-fit: cover; border-radius: 5px; background: #e5e7eb; flex: 0 0 auto; }
    .web-adv { margin-top: 10px; }
    .web-adv summary { cursor: pointer; font-weight: 600; font-size: 13.5px; color: #4b5563; }
    .web-adv .grid { margin-top: 10px; }
    .loc-picker { border: 1.5px solid #d1d5db; border-radius: 7px; padding: 10px 12px; background: #fff; }
    .loc-picker .loc-all { padding-bottom: 8px; margin-bottom: 8px; border-bottom: 1px solid #eef0f3; }
    .loc-picker .loc-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 6px 14px; }
    .loc-picker input:disabled + * , .loc-picker label:has(input:disabled) { color: #9ca3af; }
    @media (max-width: 760px) { .grid { grid-template-columns: 1fr; } .container { padding: 20px 16px; } td, th { padding: 8px 6px; } .hide-sm { display: none; } }
</style>
<?= pd_infoscreen_message_style_css() ?>
<?= pd_branding_form_css() ?>
</head>
<body>
<?php if ($isAdminRole): ?><a href="dashboard.php" class="back-link">← Terug naar Dashboard</a><?php else: ?><a href="../frontpage.php" class="back-link">← Terug</a><?php endif; ?>
<div class="container">
    <div class="head">
        <div>
            <h1>📺 Infoscherm</h1>
            <div class="subtitle">Eigen berichten, nieuws en weer op uw schermen — als vervanging van of naast de Google Slides presentatie</div>
        </div>
        <div class="preview-links">
            <a class="btn btn-light" href="../infoscherm.php?preview=1&amp;mode=infoscreen" target="_blank">👁️ Voorbeeld</a>
        </div>
    </div>

    <?php if (!$scope['all']): ?>
    <div class="scope-box"><strong>U werkt voor:</strong>
        <?= $pickable ? implode(' ', array_map(fn($l) => '<span class="loc-badge">📍 ' . $h($l) . '</span>', $pickable)) : '<em>nog geen locaties — vraag de beheerder om ze bij uw gebruiker aan te vinken</em>' ?>
        <div class="muted" style="margin-top:6px">Wat ook voor andere locaties of voor alle locaties geldt, ziet u met 🔒 Alleen lezen.</div></div>
    <?php endif; ?>
    <nav class="tabs">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="?tab=<?= $key ?>" class="<?= $key === $tab ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($flash): ?>
        <div class="alert <?= str_starts_with($flash, '❌') ? 'err' : 'ok' ?>"><?= $h($flash) ?></div>
    <?php endif; ?>

<?php if ($tab === 'berichten'): ?>
    <?php if ($showForm): $m = $editMessage ?? ['id' => 0, 'title' => '', 'body' => '', 'image_path' => null, 'image_layout' => 'left', 'image_fit' => 'cover', 'image_size' => 50, 'locations' => null, 'starts_at' => null, 'ends_at' => null, 'duration_seconds' => null, 'priority' => 0, 'color' => 'huisstijl', 'active' => 1]; ?>
    <div class="panel">
        <h2><?= $m['id'] ? '✏️ Bericht bewerken' : '➕ Nieuw bericht' ?></h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="save_message">
            <input type="hidden" name="tab" value="berichten">
            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <div class="grid">
                <div class="full">
                    <label class="f" for="title">Titel *</label>
                    <input type="text" id="title" name="title" maxlength="200" required value="<?= $h($m['title']) ?>" placeholder="Bijv. Studiedag vrijdag 12 december">
                </div>
                <div class="full">
                    <label class="f" for="body">Tekst</label>
                    <textarea id="body" name="body" maxlength="2000" placeholder="Korte tekst. Houd het kort: mensen lopen voorbij."><?= $h($m['body']) ?></textarea>
                    <div class="help">Tip: maximaal 2 à 3 zinnen leest het prettigst. Een bericht met alleen een afbeelding (zonder tekst) toont de afbeelding groot, bijvoorbeeld een poster.</div>
                </div>
                <div class="full">
                    <label class="f" for="image">Afbeelding</label>
                    <?php if ($m['image_path']): ?>
                        <div class="current-img"><img src="../<?= $h($m['image_path']) ?>" alt=""><label class="check"><input type="checkbox" name="remove_image" value="1"> Afbeelding verwijderen</label></div>
                    <?php endif; ?>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                    <div class="help">JPG, PNG, GIF of WEBP, maximaal 5 MB. Kies hieronder waar de afbeelding staat en of hij helemaal zichtbaar moet zijn.</div>
                </div>
                <?= pd_infoscreen_message_style_fields($m, $orgBrand) ?>
                <div>
                    <label class="f" for="starts_at">Tonen vanaf</label>
                    <input type="datetime-local" id="starts_at" name="starts_at" value="<?= $dtValue($m['starts_at']) ?>">
                    <div class="help">Leeg = direct.</div>
                </div>
                <div>
                    <label class="f" for="ends_at">Tonen tot en met</label>
                    <input type="datetime-local" id="ends_at" name="ends_at" value="<?= $dtValue($m['ends_at']) ?>">
                    <div class="help">Leeg = blijft staan tot u het uitzet.</div>
                </div>
                <div>
                    <label class="f">Locaties</label>
                    <?= pd_infoscreen_location_picker($pickable, pd_infoscreen_locs($m['locations']), $scope['all'], ($scope['all'] ? 'Kies één of meer locaties, of “Alle locaties” voor een bericht dat overal moet staan. ' : '') . 'Een scherm toont de berichten van zijn locaties; 📺 = aantal infoschermen per locatie.', $screenCounts) ?>
                </div>
                <div>
                    <label class="f" for="duration_seconds">Tijd in beeld (seconden)</label>
                    <input type="number" id="duration_seconds" name="duration_seconds" min="5" max="300" value="<?= $h($m['duration_seconds']) ?>" placeholder="Standaard: <?= (int)$settings['slide_seconds'] ?>">
                </div>
                <div class="full" style="display:flex;gap:24px;flex-wrap:wrap">
                    <label class="check"><input type="checkbox" name="priority" value="1" <?= (int)$m['priority'] ? 'checked' : '' ?>> ⚠️ Belangrijk — vaker in beeld en ook in de nieuwsbalk</label>
                    <label class="check"><input type="checkbox" name="active" value="1" <?= (int)$m['active'] ? 'checked' : '' ?>> Actief</label>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">💾 Opslaan</button>
                <a class="btn btn-light" href="?tab=berichten">Annuleren</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if (!$showForm && $scope['all'] && $isAdminRole): ?>
    <div class="info"><strong>Iemand anders berichten of de huisstijl voor een locatie laten beheren?</strong> Geef die gebruiker bij
        <a href="users_manage.php">Gebruikers</a> › 📺 Infoscherm de rechten en locaties. Die gebruiker ziet hier dan alleen zijn eigen locaties.</div>
    <?php endif; ?>
    <div class="toolbar">
        <div class="muted"><?= count($messages) ?> bericht(en)</div>
        <?php if (!$showForm && ($scope['all'] || $pickable)): ?><a class="btn btn-primary" href="?tab=berichten&amp;new=1">➕ Nieuw bericht</a><?php endif; ?>
    </div>
    <?php if (!$messages): ?>
        <div class="empty"><div class="big">📝</div>Nog geen berichten. Maak het eerste bericht aan met “Nieuw bericht”.</div>
    <?php else: ?>
    <table>
        <thead><tr><th></th><th>Bericht</th><th class="hide-sm">Locatie en schermen</th><th class="hide-sm">Periode</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($messages as $m): [$statusText, $statusCls] = pd_is_status($m); ?>
            <tr>
                <td><?php if ($m['image_path']): ?><img class="thumb" src="../<?= $h($m['image_path']) ?>" alt=""><?php else: ?><span class="thumb" style="background:<?= $h($m['color'] === 'accent' ? $orgBrand['accent']['bg'] : ($m['color'] === 'huisstijl' ? $orgBrand['primary']['bg'] : ($colors[$m['color']] ?? '#1d4ed8'))) ?>"></span><?php endif; ?></td>
                <td><strong><?= $h($m['title']) ?></strong><?php if ((int)$m['priority']): ?> <span class="pill prio">Belangrijk</span><?php endif; ?>
                    <?php if (($m['source'] ?? 'handmatig') !== 'handmatig'): ?><span class="pill src" title="Geplaatst via <?= $h($m['source'] === 'teams' ? 'Teams' : 'de Berichten-API') ?>"><?= $m['source'] === 'teams' ? 'Teams' : 'API' ?><?= isset($keyNames[$m['api_key_id']]) ? ' · ' . $h($keyNames[$m['api_key_id']]) : '' ?><?= $m['author'] ? ' · ' . $h($m['author']) : '' ?></span><?php endif; ?>
                    <?php if ($m['body']): ?><div class="muted"><?= $h(mb_strimwidth($m['body'], 0, 90, '…')) ?></div><?php endif; ?></td>
                <td class="hide-sm"><?= $h(pd_infoscreen_locs_label($m['_locs'])) ?> <?= $shownOn($m['_locs']) ?></td>
                <td class="hide-sm muted"><?= $h(pd_is_period($m)) ?></td>
                <td><span class="pill <?= $statusCls ?>"><?= $statusText ?></span></td>
                <td>
                    <?php if (!$m['_editable']): ?><?= $readOnly($m['_locs']) ?><?php else: ?>
                    <div class="actions">
                        <a class="btn btn-light btn-sm" href="?tab=berichten&amp;edit=<?= (int)$m['id'] ?>">Bewerken</a>
                        <form method="post"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="berichten"><input type="hidden" name="action" value="toggle_message"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-light btn-sm"><?= (int)$m['active'] ? 'Uitzetten' : 'Aanzetten' ?></button></form>
                        <form method="post" onsubmit="return confirm('Bericht verwijderen?')"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="berichten"><input type="hidden" name="action" value="delete_message"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-danger btn-sm">Verwijderen</button></form>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

<?php elseif ($tab === 'nieuws'): ?>
    <div class="info warn">
        <strong>Let op de voorwaarden van een nieuwsbron.</strong> Veel nieuwssites staan hun RSS-feed alleen toe voor persoonlijk, niet-commercieel gebruik,
        soms alleen de koppen (zonder afbeeldingen) en altijd met bronvermelding. Het infoscherm toont bij elk bericht de naam van de bron.
        Controleer de voorwaarden van de bron voordat u hem toevoegt; zet zo nodig “Afbeeldingen tonen” uit.
        Afbeeldingen worden nooit naar deze server gekopieerd, het scherm laadt ze bij de bron.
    </div>
    <div class="info">
        <strong>Welke bronnen?</strong> Elke RSS- of Atom-feed werkt. Heeft een website geen feed, kies dan <em>Website zonder feed</em>: PeopleDisplay haalt de berichten dan van de nieuwspagina zelf. Handig zijn feeds van uw eigen organisatie: een WordPress-website heeft er standaard één op
        <code>https://uwwebsite.nl/feed/</code>, en veel gemeenten, scholen en verenigingen publiceren hun nieuws ook zo.
        Nieuws met een afbeelding verschijnt als grote dia, alle koppen lopen door de nieuwsbalk onderaan.
    </div>

    <?php $isWeb = $showForm && ($editSource ? ($editSource['provider'] ?? '') === 'web' : ($_GET['new'] ?? '') === 'web'); ?>
    <?php if ($isWeb): $s = $editSource ?? ['id' => 0, 'name' => '', 'url' => '', 'locations' => null, 'max_items' => 8, 'show_images' => 1, 'attribution' => '', 'active' => 1, 'data_json' => null];
        $wc = json_decode((string)($s['data_json'] ?? ''), true) ?: [];
        $wcMode = $wc['mode'] ?? 'auto'; $wcCss = is_array($wc['css'] ?? null) ? $wc['css'] : []; ?>
    <div class="panel">
        <h2><?= $s['id'] ? '✏️ Website bewerken' : '➕ Website zonder RSS-feed toevoegen' ?></h2>
        <p class="help" style="font-size:14px;color:#374151;margin:-6px 0 14px">PeopleDisplay haalt de nieuwspagina elke <?= PD_IS_FETCH_MINUTES ?> minuten op en neemt van elk bericht de titel,
            een korte tekst, de afbeelding en de link over. Werkt niet voor pagina's achter een inlog, sociale media en sites die hun inhoud met JavaScript opbouwen.</p>
        <form method="post" id="web-form">
            <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="save_source">
            <input type="hidden" name="type" value="rss">
            <input type="hidden" name="source_kind" value="web">
            <input type="hidden" name="tab" value="nieuws">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <input type="hidden" name="web_mode" id="web_mode" value="<?= $h($wcMode) ?>">
            <input type="hidden" name="web_feed" id="web_feed" value="<?= $h($wc['feed'] ?? '') ?>">
            <input type="hidden" name="web_xpath" id="web_xpath" value="<?= $h($wc['xpath'] ?? '') ?>">
            <div class="grid">
                <div>
                    <label class="f" for="name">Naam *</label>
                    <input type="text" id="name" name="name" maxlength="150" required value="<?= $h($s['name']) ?>" placeholder="Bijv. Nieuws van de gemeente">
                </div>
                <div>
                    <label class="f" for="attribution">Bronvermelding op het scherm</label>
                    <input type="text" id="attribution" name="attribution" maxlength="200" value="<?= $h($s['attribution']) ?>" placeholder="Leeg = de naam">
                </div>
                <div class="full">
                    <label class="f" for="url">Webadres van de nieuwspagina *</label>
                    <div style="display:flex;gap:8px">
                        <input type="url" id="url" name="url" maxlength="500" required value="<?= $h($s['url']) ?>" placeholder="https://www.voorbeeld.nl/nieuws">
                        <button type="button" class="btn btn-light" id="web-search">🔍 Zoek berichten</button>
                    </div>
                    <div class="help">Gebruik de overzichtspagina met alle berichten (bijv. <em>Nieuws</em> of <em>Actueel</em>), niet één los bericht.</div>
                </div>
                <div class="full">
                    <label class="f">Welke berichten?</label>
                    <div id="web-preview" class="web-preview">
                        <?php if ($s['id']): ?>
                            <div class="muted">Huidige keuze: <strong><?= $h(['auto' => 'automatisch de beste lijst', 'feed' => 'de gevonden feed', 'list' => 'een gekozen lijst op de pagina', 'css' => 'eigen CSS-selectors'][$wcMode] ?? $wcMode) ?></strong>.
                                Klik op <em>Zoek berichten</em> om opnieuw te kiezen.</div>
                        <?php else: ?>
                            <div class="muted">Vul het webadres in en klik op <em>Zoek berichten</em>. U ziet dan wat PeopleDisplay op de pagina vindt en kiest zelf.</div>
                        <?php endif; ?>
                    </div>
                    <details class="web-adv" <?= $wcMode === 'css' ? 'open' : '' ?>>
                        <summary>Geavanceerd: zelf aanwijzen met CSS-selectors</summary>
                        <p class="help">Alleen nodig als de automatische herkenning niet de goede berichten vindt. Gebruik eenvoudige selectors, zoals <code>.nieuws-item</code>,
                            <code>ul.news &gt; li</code> of <code>article h2</code>. Titel, link, afbeelding en datum zoeken binnen één bericht; leeg = automatisch.</p>
                        <div class="grid">
                            <?php foreach (['item' => 'Eén bericht *', 'title' => 'Titel', 'link' => 'Link', 'image' => 'Afbeelding', 'date' => 'Datum'] as $f => $label): ?>
                                <div><label class="f" for="css_<?= $f ?>"><?= $label ?></label>
                                    <input type="text" id="css_<?= $f ?>" name="css[<?= $f ?>]" maxlength="200" value="<?= $h($wcCss[$f] ?? '') ?>" placeholder="<?= $f === 'item' ? 'bijv. .nieuws-item' : '' ?>"></div>
                            <?php endforeach; ?>
                            <div style="display:flex;align-items:flex-end"><button type="button" class="btn btn-light" id="web-css-test">🧪 Testen met deze selectors</button></div>
                        </div>
                    </details>
                </div>
                <div>
                    <label class="f">Locaties</label>
                    <?= pd_infoscreen_location_picker($pickable, pd_infoscreen_locs($s['locations']), $scope['all'], '📺 = aantal infoschermen per locatie.', $screenCounts) ?>
                </div>
                <div>
                    <label class="f" for="max_items">Aantal berichten</label>
                    <input type="number" id="max_items" name="max_items" min="1" max="30" value="<?= (int)$s['max_items'] ?>">
                    <div class="help">De nieuwste of bovenste berichten van de pagina (1–30).</div>
                </div>
                <div class="full" style="display:flex;gap:24px;flex-wrap:wrap">
                    <label class="check"><input type="checkbox" name="show_images" value="1" <?= (int)$s['show_images'] ? 'checked' : '' ?>> Afbeeldingen tonen (alleen als de bron dat toestaat)</label>
                    <label class="check"><input type="checkbox" name="active" value="1" <?= (int)$s['active'] ? 'checked' : '' ?>> Actief</label>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">💾 Opslaan en ophalen</button>
                <a class="btn btn-light" href="?tab=nieuws">Annuleren</a>
            </div>
        </form>
    </div>
    <script>
    (function () {
        var form = document.getElementById('web-form'), box = document.getElementById('web-preview');
        var esc = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; };
        var current = { mode: form.web_mode.value, feed: form.web_feed.value, xpath: form.web_xpath.value };
        function pick(mode, feed, xpath) {
            form.web_mode.value = mode; form.web_feed.value = feed || ''; form.web_xpath.value = xpath || '';
            box.querySelectorAll('.web-opt').forEach(function (o) { o.classList.toggle('on', o.querySelector('input').checked); });
        }
        function itemsHtml(items) {
            return '<ul class="web-items">' + items.map(function (i) {
                return '<li>' + (i.image ? '<img src="' + esc(i.image) + '" alt="" loading="lazy" referrerpolicy="no-referrer">' : '<span class="noimg"></span>') +
                    '<span><strong>' + esc(i.title) + '</strong>' + (i.date ? ' <span class="muted">· ' + esc(i.date) + '</span>' : '') +
                    (i.summary ? '<br><span class="muted">' + esc(i.summary) + '</span>' : '') + '</span></li>';
            }).join('') + '</ul>';
        }
        function option(mode, value, title, sub, items, checked) {
            return '<label class="web-opt' + (checked ? ' on' : '') + '"><input type="radio" name="web_pick" data-mode="' + mode + '" value="' + esc(value) + '"' + (checked ? ' checked' : '') + '>' +
                '<span class="web-opt-body"><strong>' + title + '</strong> <span class="muted">' + sub + '</span>' + (items ? itemsHtml(items) : '') + '</span></label>';
        }
        function run(withCss) {
            var url = form.url.value.trim();
            if (!url) { form.url.focus(); return; }
            box.innerHTML = '<div class="muted">⏳ Pagina ophalen en berichten zoeken…</div>';
            var fd = new FormData();
            fd.append('csrf', form.csrf.value); fd.append('url', url);
            if (withCss) ['item', 'title', 'link', 'image', 'date'].forEach(function (f) { fd.append('css[' + f + ']', form['css[' + f + ']'].value); });
            fetch('api/infoscreen_web_preview.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.ok) { box.innerHTML = '<div class="info warn" style="margin:0">' + esc(d.error) + '</div>'; return; }
                    if (withCss) {
                        var n = (d.css_items || []).length;
                        box.innerHTML = n ? option('css', '', '🧪 Eigen selectors', n + ' bericht(en) gevonden', d.css_items, true)
                                          : '<div class="info warn" style="margin:0">Met deze selectors zijn geen berichten gevonden.</div>';
                        if (n) pick('css'); return;
                    }
                    var html = '', first = true, any = false;
                    var keep = function (mode, v) { return current.mode === mode && (mode === 'feed' ? current.feed === v : current.xpath === v); };
                    var hasKeep = d.feeds.some(function (f) { return keep('feed', f.url); }) || d.groups.some(function (g) { return keep('list', g.xpath); });
                    d.feeds.forEach(function (f) {
                        var on = hasKeep ? keep('feed', f.url) : first; first = false; any = true;
                        html += option('feed', f.url, '📡 Feed gevonden (aanbevolen)', esc(f.title), f.items, on);
                    });
                    d.groups.forEach(function (g, i) {
                        var on = hasKeep ? keep('list', g.xpath) : first; first = false; any = true;
                        html += option('list', g.xpath, '📋 Lijst ' + (i + 1), g.count + ' bericht(en) op de pagina', g.items, on);
                    });
                    if (!any) {
                        html = '<div class="info warn" style="margin:0">' + (d.js_only
                            ? 'Deze pagina bouwt zijn inhoud op met JavaScript. PeopleDisplay kan daar geen berichten uit halen.'
                            : 'Geen lijst met berichten gevonden. Controleer of dit de overzichtspagina is, of wijs de berichten zelf aan onder Geavanceerd.') + '</div>';
                    } else {
                        html = '<div class="help" style="margin:0 0 8px">Kies wat op het infoscherm moet komen:</div>' + html;
                    }
                    box.innerHTML = html;
                    var sel = box.querySelector('input[name=web_pick]:checked');
                    if (sel) pick(sel.dataset.mode, sel.dataset.mode === 'feed' ? sel.value : '', sel.dataset.mode === 'list' ? sel.value : '');
                })
                .catch(function () { box.innerHTML = '<div class="info warn" style="margin:0">Ophalen is mislukt. Probeer het opnieuw.</div>'; });
        }
        // An image the source does not let us show: a grey block instead of a broken icon
        box.addEventListener('error', function (e) {
            if (e.target.tagName === 'IMG') { var s = document.createElement('span'); s.className = 'noimg'; e.target.replaceWith(s); }
        }, true);
        box.addEventListener('change', function (e) {
            var r = e.target; if (r.name !== 'web_pick') return;
            pick(r.dataset.mode, r.dataset.mode === 'feed' ? r.value : '', r.dataset.mode === 'list' ? r.value : '');
        });
        document.getElementById('web-search').addEventListener('click', function () { run(false); });
        document.getElementById('web-css-test').addEventListener('click', function () { run(true); });
        form.url.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); run(false); } });
        form.addEventListener('submit', function () {
            // Selectors filled in without picking anything else: use them
            if (form['css[item]'].value.trim() !== '' && (form.web_mode.value === 'auto' || form.web_mode.value === 'css')) form.web_mode.value = 'css';
            if (form['css[item]'].value.trim() === '' && form.web_mode.value === 'css') form.web_mode.value = 'auto';
        });
    })();
    </script>
    <?php elseif ($showForm): $s = $editSource ?? ['id' => 0, 'name' => '', 'url' => '', 'locations' => null, 'max_items' => 8, 'show_images' => 1, 'attribution' => '', 'active' => 1]; ?>
    <div class="panel">
        <h2><?= $s['id'] ? '✏️ Nieuwsbron bewerken' : '➕ Nieuwsbron toevoegen' ?></h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="save_source">
            <input type="hidden" name="type" value="rss">
            <input type="hidden" name="tab" value="nieuws">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <div class="grid">
                <div>
                    <label class="f" for="name">Naam *</label>
                    <input type="text" id="name" name="name" maxlength="150" required value="<?= $h($s['name']) ?>" placeholder="Bijv. Nieuws van onze school">
                </div>
                <div>
                    <label class="f" for="attribution">Bronvermelding op het scherm</label>
                    <input type="text" id="attribution" name="attribution" maxlength="200" value="<?= $h($s['attribution']) ?>" placeholder="Leeg = de naam">
                </div>
                <div class="full">
                    <label class="f" for="url">Webadres van de feed *</label>
                    <input type="url" id="url" name="url" maxlength="500" required value="<?= $h($s['url']) ?>" placeholder="https://www.voorbeeld.nl/feed/">
                    <div class="help">Na het opslaan wordt de feed meteen opgehaald, zodat u direct ziet of hij werkt.</div>
                </div>
                <div>
                    <label class="f">Locaties</label>
                    <?= pd_infoscreen_location_picker($pickable, pd_infoscreen_locs($s['locations']), $scope['all'], '📺 = aantal infoschermen per locatie.', $screenCounts) ?>
                </div>
                <div>
                    <label class="f" for="max_items">Aantal berichten</label>
                    <input type="number" id="max_items" name="max_items" min="1" max="30" value="<?= (int)$s['max_items'] ?>">
                    <div class="help">De nieuwste berichten van deze bron (1–30).</div>
                </div>
                <div class="full" style="display:flex;gap:24px;flex-wrap:wrap">
                    <label class="check"><input type="checkbox" name="show_images" value="1" <?= (int)$s['show_images'] ? 'checked' : '' ?>> Afbeeldingen tonen (alleen als de bron dat toestaat)</label>
                    <label class="check"><input type="checkbox" name="active" value="1" <?= (int)$s['active'] ? 'checked' : '' ?>> Actief</label>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">💾 Opslaan en ophalen</button>
                <a class="btn btn-light" href="?tab=nieuws">Annuleren</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="toolbar">
        <div class="muted"><?= count($rssSources) ?> nieuwsbron(nen) · wordt elke <?= PD_IS_FETCH_MINUTES ?> minuten bijgewerkt</div>
        <?php if (!$showForm && ($scope['all'] || $pickable)): ?><div class="actions"><a class="btn btn-primary" href="?tab=nieuws&amp;new=1">➕ RSS-feed</a><a class="btn btn-primary" href="?tab=nieuws&amp;new=web">➕ Website zonder feed</a></div><?php endif; ?>
    </div>
    <?php if (!$rssSources): ?>
        <div class="empty"><div class="big">📰</div>Nog geen nieuwsbronnen.</div>
    <?php else: ?>
    <table>
        <thead><tr><th>Bron</th><th class="hide-sm">Locatie</th><th>Berichten</th><th>Laatst opgehaald</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rssSources as $s): $ok = str_starts_with((string)$s['last_status'], 'OK'); ?>
            <tr>
                <td><strong><?= $h($s['name']) ?></strong> <?php if (($s['provider'] ?? '') === 'web'): ?><span class="pill src" title="Website zonder RSS-feed">Website</span><?php endif; ?> <?php if (!(int)$s['active']): ?><span class="pill off">Uit</span><?php endif; ?>
                    <div class="muted"><code><?= $h(mb_strimwidth((string)$s['url'], 0, 70, '…')) ?></code></div></td>
                <td class="hide-sm"><?= $h(pd_infoscreen_locs_label($s['_locs'])) ?> <?= $shownOn($s['_locs']) ?></td>
                <td><?= (int)$s['item_count'] ?> <span class="muted">(<?= (int)$s['image_count'] ?> met afbeelding<?= (int)$s['show_images'] ? '' : ', uitgezet' ?>)</span></td>
                <td><?php if ($s['last_status']): ?><span class="pill <?= $ok ? 'ok' : 'err' ?>"><?= $ok ? 'OK' : 'Fout' ?></span><?php endif; ?>
                    <span class="muted"><?= $h(pd_is_ago($s['last_fetch_at'])) ?></span>
                    <?php if (!$ok && $s['last_status']): ?><div class="muted" style="color:#991b1b"><?= $h(preg_replace('/^Fout: /', '', $s['last_status'])) ?></div><?php endif; ?></td>
                <td><?php if (!$s['_editable']): ?><?= $readOnly($s['_locs']) ?><?php else: ?><div class="actions">
                    <form method="post"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="nieuws"><input type="hidden" name="action" value="fetch_source"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-light btn-sm">🔄 Nu ophalen</button></form>
                    <a class="btn btn-light btn-sm" href="?tab=nieuws&amp;edit=<?= (int)$s['id'] ?>">Bewerken</a>
                    <form method="post"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="nieuws"><input type="hidden" name="action" value="toggle_source"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-light btn-sm"><?= (int)$s['active'] ? 'Uitzetten' : 'Aanzetten' ?></button></form>
                    <form method="post" onsubmit="return confirm('Nieuwsbron verwijderen?')"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="nieuws"><input type="hidden" name="action" value="delete_source"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-danger btn-sm">Verwijderen</button></form>
                </div><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

<?php elseif ($tab === 'weer'): ?>
    <div class="info">
        Voeg één plaats toe voor alle schermen, of per locatie een eigen plaats. Een scherm toont het weer van de plaats die bij zijn locatie hoort,
        en anders die voor “Alle locaties”. Het weer wordt elke <?= PD_IS_FETCH_MINUTES ?> minuten bijgewerkt; de bron staat altijd op het scherm.
    </div>

    <?php if ($showForm): $s = $editSource ?? ['id' => 0, 'name' => '', 'provider' => 'buienradar', 'latitude' => '', 'longitude' => '', 'locations' => null, 'active' => 1]; ?>
    <div class="panel">
        <h2><?= $s['id'] ? '✏️ Weerplaats bewerken' : '➕ Weerplaats toevoegen' ?></h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="save_source">
            <input type="hidden" name="type" value="weather">
            <input type="hidden" name="tab" value="weer">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <div class="grid">
                <div class="full">
                    <label class="f" for="geo-q">Plaats *</label>
                    <div style="display:flex;gap:8px">
                        <input type="text" id="geo-q" name="name" maxlength="150" required value="<?= $h($s['name']) ?>" placeholder="Bijv. Gilze">
                        <button type="button" class="btn btn-light" id="geo-btn">🔍 Zoek plaats</button>
                    </div>
                    <div class="geo-results" id="geo-results"></div>
                    <div class="help">“Zoek plaats” vult de coördinaten in (via de gratis plaatsenzoeker van Open-Meteo, vanuit uw browser).</div>
                </div>
                <div>
                    <label class="f" for="latitude">Breedtegraad</label>
                    <input type="text" id="latitude" name="latitude" required value="<?= $h($s['latitude']) ?>" placeholder="51.5667">
                </div>
                <div>
                    <label class="f" for="longitude">Lengtegraad</label>
                    <input type="text" id="longitude" name="longitude" required value="<?= $h($s['longitude']) ?>" placeholder="4.9333">
                </div>
                <div class="full">
                    <label class="f">Weerbron</label>
                    <?php foreach (PD_IS_WEATHER_PROVIDERS as $key => $p): ?>
                        <label class="provider">
                            <input type="radio" name="provider" value="<?= $key ?>" <?= $s['provider'] === $key ? 'checked' : '' ?>>
                            <span><strong><?= $h($p['label']) ?></strong><span class="muted"><?= $h($p['terms']) ?> <a href="<?= $h($p['terms_url']) ?>" target="_blank" rel="noopener">Voorwaarden</a></span></span>
                        </label>
                    <?php endforeach; ?>
                    <div class="help">Buienradar: actuele metingen van het dichtstbijzijnde KNMI-meetstation, 5-daagse verwachting en het weerbericht (alleen Nederland). Open-Meteo: werkt voor elke plaats, zonder weerbericht.</div>
                </div>
                <div>
                    <label class="f">Locaties</label>
                    <?= pd_infoscreen_location_picker($pickable, pd_infoscreen_locs($s['locations']), $scope['all'], '📺 = aantal infoschermen per locatie.', $screenCounts) ?>
                </div>
                <div style="display:flex;align-items:flex-end">
                    <label class="check"><input type="checkbox" name="active" value="1" <?= (int)$s['active'] ? 'checked' : '' ?>> Actief</label>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">💾 Opslaan en ophalen</button>
                <a class="btn btn-light" href="?tab=weer">Annuleren</a>
            </div>
        </form>
    </div>
    <script>
    (function () {
        const q = document.getElementById('geo-q'), out = document.getElementById('geo-results');
        async function search() {
            const name = q.value.trim();
            if (name.length < 2) return;
            out.innerHTML = '<span class="muted">Zoeken…</span>';
            try {
                const r = await fetch('https://geocoding-api.open-meteo.com/v1/search?count=6&language=nl&format=json&name=' + encodeURIComponent(name));
                const d = await r.json();
                out.innerHTML = '';
                (d.results || []).forEach((p) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.textContent = [p.name, p.admin2, p.admin1, p.country].filter(Boolean).join(', ');
                    b.onclick = () => {
                        q.value = p.name;
                        document.getElementById('latitude').value = p.latitude.toFixed(4);
                        document.getElementById('longitude').value = p.longitude.toFixed(4);
                        out.innerHTML = '';
                    };
                    out.appendChild(b);
                });
                if (!out.children.length) out.innerHTML = '<span class="muted">Geen plaats gevonden.</span>';
            } catch (e) {
                out.innerHTML = '<span class="muted">Zoeken lukte niet. Vul de coördinaten zelf in (bijv. via Google Maps: rechtsklik op de kaart).</span>';
            }
        }
        document.getElementById('geo-btn').addEventListener('click', search);
        q.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); search(); } });
    })();
    </script>
    <?php endif; ?>

    <div class="toolbar">
        <div class="muted"><?= count($weatherSources) ?> weerplaats(en)</div>
        <?php if (!$showForm && ($scope['all'] || $pickable)): ?><a class="btn btn-primary" href="?tab=weer&amp;new=1">➕ Weerplaats toevoegen</a><?php endif; ?>
    </div>
    <?php if (!$weatherSources): ?>
        <div class="empty"><div class="big">🌤️</div>Nog geen weerplaats. Zonder weerplaats toont de zijbalk een grote klok.</div>
    <?php else: ?>
    <table>
        <thead><tr><th>Plaats</th><th>Bron</th><th class="hide-sm">Locatie</th><th>Nu</th><th>Laatst opgehaald</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($weatherSources as $s): $ok = str_starts_with((string)$s['last_status'], 'OK'); $wx = json_decode((string)$s['data_json'], true); ?>
            <tr>
                <td><strong><?= $h($s['name']) ?></strong> <?php if (!(int)$s['active']): ?><span class="pill off">Uit</span><?php endif; ?>
                    <div class="muted"><?= $h($s['latitude']) ?>, <?= $h($s['longitude']) ?></div></td>
                <td><?= $h(PD_IS_WEATHER_PROVIDERS[$s['provider']]['label'] ?? $s['provider']) ?></td>
                <td class="hide-sm"><?= $h(pd_infoscreen_locs_label($s['_locs'])) ?> <?= $shownOn($s['_locs']) ?></td>
                <td class="wx-now"><?php if (is_array($wx) && isset($wx['current'])): ?><?= $h(round($wx['current']['temp'])) ?>°C, <?= $h($wx['current']['desc']) ?><?php if (!empty($wx['station'])): ?><div class="muted">Meetstation <?= $h($wx['station']) ?></div><?php endif; ?><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                <td><?php if ($s['last_status']): ?><span class="pill <?= $ok ? 'ok' : 'err' ?>"><?= $ok ? 'OK' : 'Fout' ?></span><?php endif; ?>
                    <span class="muted"><?= $h(pd_is_ago($s['last_fetch_at'])) ?></span>
                    <?php if (!$ok && $s['last_status']): ?><div class="muted" style="color:#991b1b"><?= $h(preg_replace('/^Fout: /', '', $s['last_status'])) ?></div><?php endif; ?></td>
                <td><?php if (!$s['_editable']): ?><?= $readOnly($s['_locs']) ?><?php else: ?><div class="actions">
                    <form method="post"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="weer"><input type="hidden" name="action" value="fetch_source"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-light btn-sm">🔄 Nu ophalen</button></form>
                    <a class="btn btn-light btn-sm" href="?tab=weer&amp;edit=<?= (int)$s['id'] ?>">Bewerken</a>
                    <form method="post"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="weer"><input type="hidden" name="action" value="toggle_source"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-light btn-sm"><?= (int)$s['active'] ? 'Uitzetten' : 'Aanzetten' ?></button></form>
                    <form method="post" onsubmit="return confirm('Weerplaats verwijderen?')"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="weer"><input type="hidden" name="action" value="delete_source"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-danger btn-sm">Verwijderen</button></form>
                </div><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

<?php elseif ($tab === 'agenda'): ?>
    <div class="info">
        <strong>Een agenda koppelen.</strong> Het infoscherm leest een agenda via een iCal-link (.ics). Afspraken verschijnen in de zijbalk en
        één keer per ronde als agendadia, met terugkerende afspraken, verplaatste en geannuleerde afspraken. Privé-afspraken worden niet getoond.
        <br>• <strong>Outlook / Microsoft 365:</strong> Outlook op het web → Instellingen → Agenda → Gedeelde agenda's → <em>Een agenda publiceren</em>:
        kies de agenda en "Kan titels en locaties bekijken" → Publiceren → kopieer de <strong>ICS</strong>-link.
        <br>• <strong>Google Agenda:</strong> Instellingen → kies de agenda → <em>Geheim adres in iCal-indeling</em> → kopieer.
        <br>Iedereen met de link kan de agenda lezen; deel hem dus niet verder. Het infoscherm haalt de agenda elke <?= PD_IS_FETCH_MINUTES ?> minuten op.
    </div>

    <?php if ($showForm): $s = $editSource ?? ['id' => 0, 'name' => '', 'url' => '', 'locations' => null, 'attribution' => '', 'active' => 1]; ?>
    <div class="panel">
        <h2><?= $s['id'] ? '✏️ Agenda bewerken' : '➕ Agenda koppelen' ?></h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="save_source">
            <input type="hidden" name="type" value="ical">
            <input type="hidden" name="tab" value="agenda">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <div class="grid">
                <div>
                    <label class="f" for="name">Naam *</label>
                    <input type="text" id="name" name="name" maxlength="150" required value="<?= $h($s['name']) ?>" placeholder="Bijv. Schoolagenda">
                </div>
                <div>
                    <label class="f" for="attribution">Naam op het scherm</label>
                    <input type="text" id="attribution" name="attribution" maxlength="200" value="<?= $h($s['attribution']) ?>" placeholder="Leeg = de naam (alleen getoond bij meer agenda's)">
                </div>
                <div class="full">
                    <label class="f" for="url">iCal-link (.ics) *</label>
                    <input type="text" id="url" name="url" maxlength="500" required value="<?= $h($s['url']) ?>" placeholder="https://outlook.office365.com/owa/calendar/…/calendar.ics">
                    <div class="help">Na het opslaan wordt de agenda meteen opgehaald, zodat u direct ziet hoeveel afspraken er gevonden zijn.</div>
                </div>
                <div>
                    <label class="f">Locaties</label>
                    <?= pd_infoscreen_location_picker($pickable, pd_infoscreen_locs($s['locations']), $scope['all'], '📺 = aantal infoschermen per locatie.', $screenCounts) ?>
                </div>
                <div style="display:flex;align-items:flex-end">
                    <label class="check"><input type="checkbox" name="active" value="1" <?= (int)$s['active'] ? 'checked' : '' ?>> Actief</label>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">💾 Opslaan en ophalen</button>
                <a class="btn btn-light" href="?tab=agenda">Annuleren</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="toolbar">
        <div class="muted"><?= count($icalSources) ?> agenda('s) · getoond: <?= (int)$settings['agenda_days'] ?> dagen vooruit (aan te passen bij Weergave)</div>
        <?php if (!$showForm && ($scope['all'] || $pickable)): ?><a class="btn btn-primary" href="?tab=agenda&amp;new=1">➕ Agenda koppelen</a><?php endif; ?>
    </div>
    <?php if (!$icalSources): ?>
        <div class="empty"><div class="big">📅</div>Nog geen agenda gekoppeld.</div>
    <?php else: ?>
    <table>
        <thead><tr><th>Agenda</th><th class="hide-sm">Locatie</th><th>Afspraken</th><th>Laatst opgehaald</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($icalSources as $s): $ok = str_starts_with((string)$s['last_status'], 'OK'); $cnt = $eventCounts[(int)$s['id']] ?? null; ?>
            <tr>
                <td><strong><?= $h($s['name']) ?></strong> <?php if (!(int)$s['active']): ?><span class="pill off">Uit</span><?php endif; ?>
                    <div class="muted"><code><?= $h(mb_strimwidth((string)$s['url'], 0, 60, '…')) ?></code></div></td>
                <td class="hide-sm"><?= $h(pd_infoscreen_locs_label($s['_locs'])) ?> <?= $shownOn($s['_locs']) ?></td>
                <td><?= (int)($cnt['n'] ?? 0) ?> <span class="muted">(<?= (int)($cnt['week'] ?? 0) ?> komende week)</span></td>
                <td><?php if ($s['last_status']): ?><span class="pill <?= $ok ? 'ok' : 'err' ?>"><?= $ok ? 'OK' : 'Fout' ?></span><?php endif; ?>
                    <span class="muted"><?= $h(pd_is_ago($s['last_fetch_at'])) ?></span>
                    <?php if (!$ok && $s['last_status']): ?><div class="muted" style="color:#991b1b"><?= $h(preg_replace('/^Fout: /', '', $s['last_status'])) ?></div><?php endif; ?></td>
                <td><?php if (!$s['_editable']): ?><?= $readOnly($s['_locs']) ?><?php else: ?><div class="actions">
                    <form method="post"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="agenda"><input type="hidden" name="action" value="fetch_source"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-light btn-sm">🔄 Nu ophalen</button></form>
                    <a class="btn btn-light btn-sm" href="?tab=agenda&amp;edit=<?= (int)$s['id'] ?>">Bewerken</a>
                    <form method="post"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="agenda"><input type="hidden" name="action" value="toggle_source"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-light btn-sm"><?= (int)$s['active'] ? 'Uitzetten' : 'Aanzetten' ?></button></form>
                    <form method="post" onsubmit="return confirm('Agenda ontkoppelen?')"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="agenda"><input type="hidden" name="action" value="delete_source"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-danger btn-sm">Verwijderen</button></form>
                </div><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

<?php elseif ($tab === 'koppelingen'):
    $pushUrl = $appUrl . '/api/infoscreen_push.php';
    $newType = in_array($_GET['new'] ?? '', ['api', 'teams'], true) ? $_GET['new'] : '';
    $setupId = (int)($_GET['setup'] ?? 0);
?>
    <div class="info">
        <strong>Berichten uit andere systemen.</strong> Met een koppeling zetten andere systemen zelf een bericht op het infoscherm.
        <br>• <strong>Teams</strong> — gratis, zonder extra licentie: in een team typt iemand <code>@Infoscherm Studiedag vrijdag</code> in een kanaal en het bericht staat op de schermen.
        <br>• <strong>Berichten-API</strong> — voor eigen systemen, scripts, Zapier/Make of Power Automate (de HTTP-actie van Power Automate vraagt een Premium-licentie).
        <br>Berichten van een koppeling krijgen de locatie, kleur en looptijd van de koppeling, tenzij het bericht zelf iets anders opgeeft. U ziet ze gewoon bij Berichten en kunt ze daar aanpassen of uitzetten.
    </div>

    <?php if ($newKey): ?>
    <div class="panel" style="border-color:#f59e0b;background:#fffbeb">
        <h2>🔑 De sleutel van "<?= $h($keyNames[$newKey['id']] ?? '') ?>"</h2>
        <p class="help" style="font-size:14px;color:#5b4204">Kopieer deze sleutel nu en bewaar hem veilig, bijvoorbeeld in het systeem dat de berichten stuurt. Hij wordt <strong>niet nog een keer getoond</strong>; kwijt = een nieuwe maken.</p>
        <div style="display:flex;gap:8px;margin-top:10px"><input type="text" id="newkey" readonly value="<?= $h($newKey['key']) ?>" style="font-family:monospace"><button type="button" class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('newkey').value);this.textContent='✓ Gekopieerd'">📋 Kopiëren</button></div>
    </div>
    <?php endif; ?>

    <?php if ($newType): ?>
    <div class="panel">
        <h2><?= $newType === 'teams' ? '➕ Teams-koppeling maken' : '➕ API-koppeling maken' ?></h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="create_key">
            <input type="hidden" name="type" value="<?= $newType ?>">
            <input type="hidden" name="tab" value="koppelingen">
            <div class="grid">
                <div>
                    <label class="f" for="name">Naam *</label>
                    <input type="text" id="name" name="name" maxlength="<?= $newType === 'teams' ? 40 : 100 ?>" required placeholder="<?= $newType === 'teams' ? 'Infoscherm' : 'Bijv. Roostersysteem' ?>" value="<?= $newType === 'teams' ? 'Infoscherm' : '' ?>">
                    <div class="help"><?= $newType === 'teams' ? 'Gebruik dezelfde naam voor de webhook in Teams. Collega\'s typen @naam om een bericht te plaatsen.' : 'Waar komen de berichten vandaan? Alleen voor uzelf.' ?></div>
                </div>
                <div>
                    <label class="f">Standaardlocaties</label>
                    <?= pd_infoscreen_location_picker($locations, [], true, 'Voor berichten waarin geen locatie staat.') ?>
                </div>
                <div>
                    <label class="f" for="default_days">Standaard looptijd (dagen)</label>
                    <input type="number" id="default_days" name="default_days" min="1" max="90" value="7">
                    <div class="help">Hoe lang een bericht blijft staan als er geen einddatum bij staat.</div>
                </div>
                <div>
                    <label class="f" for="color">Standaardkleur</label>
                    <select id="color" name="color"><?php foreach (PD_IS_MESSAGE_COLORS as $c => [$cl]): ?><option value="<?= $c ?>"><?= $h($cl) ?></option><?php endforeach; ?></select>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">💾 Aanmaken</button>
                <a class="btn btn-light" href="?tab=koppelingen">Annuleren</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php foreach ($keys as $k): if ((int)$k['id'] !== $setupId || $k['type'] !== 'teams') continue; $teamsUrl = $appUrl . '/api/infoscreen_teams.php?k=' . (int)$k['id']; ?>
    <div class="panel" style="border-color:#667eea">
        <h2>🟣 Teams-koppeling "<?= $h($k['name']) ?>" instellen</h2>
        <ol style="margin:0 0 14px 20px;line-height:1.8;font-size:14px">
            <li>Open in Teams het team → <strong>⋯</strong> → <strong>Team beheren</strong> → tabblad <strong>Apps</strong> → <strong>Een uitgaande webhook maken</strong>
                (staat de knop er niet, dan moet de Teams-beheerder "aangepaste apps" toestaan).</li>
            <li>Naam: <code><?= $h($k['name']) ?></code> — Callback-URL: <code><?= $h($teamsUrl) ?></code> — Beschrijving: bijv. <em>Zet berichten op het infoscherm</em>.</li>
            <li>Klik op <strong>Maken</strong>. Teams toont een <strong>beveiligingstoken</strong>: kopieer het en plak het hieronder.</li>
        </ol>
        <form method="post" style="display:flex;gap:8px;flex-wrap:wrap">
            <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="save_teams_token">
            <input type="hidden" name="tab" value="koppelingen">
            <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
            <input type="text" name="token" required placeholder="Beveiligingstoken uit Teams" style="flex:1;min-width:260px;font-family:monospace" autocomplete="off">
            <button class="btn btn-primary" type="submit">💾 Token opslaan</button>
        </form>
        <p class="help">Werkt alleen in openbare kanalen van dat team (niet in privéchats of privékanalen). Afbeeldingen uit Teams worden niet meegenomen.</p>
    </div>
    <?php endforeach; ?>

    <div class="toolbar">
        <div class="muted"><?= count($keys) ?> koppeling(en) · maximaal <?= PD_IS_API_PER_HOUR ?> berichten per uur per koppeling</div>
        <?php if (!$newType): ?><div style="display:flex;gap:8px"><a class="btn btn-primary" href="?tab=koppelingen&amp;new=teams">➕ Teams-koppeling</a><a class="btn btn-light" href="?tab=koppelingen&amp;new=api">➕ API-koppeling</a></div><?php endif; ?>
    </div>
    <?php if (!$keys): ?>
        <div class="empty"><div class="big">🔗</div>Nog geen koppelingen.</div>
    <?php else: ?>
    <table>
        <thead><tr><th>Koppeling</th><th>Soort</th><th class="hide-sm">Standaard</th><th>Nu op het scherm</th><th>Laatst gebruikt</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($keys as $k): ?>
            <tr>
                <td><strong><?= $h($k['name']) ?></strong> <?php if (!(int)$k['active']): ?><span class="pill off">Uit</span><?php endif; ?>
                    <?php if ($k['type'] === 'api'): ?><div class="muted">Sleutel eindigt op <code>…<?= $h($k['key_hint']) ?></code></div>
                    <?php elseif (!$k['secret_enc']): ?><div class="muted" style="color:#b45309">Beveiligingstoken nog niet ingevuld</div><?php endif; ?></td>
                <td><?= $k['type'] === 'teams' ? 'Teams' : 'Berichten-API' ?></td>
                <td class="hide-sm muted"><?= $h(pd_infoscreen_locs_label(pd_infoscreen_locs($k['locations']))) ?> · <?= (int)$k['default_days'] ?> dagen · <?= $h($k['color']) ?></td>
                <td><?= (int)$k['live_count'] ?></td>
                <td class="muted"><?= $h(pd_is_ago($k['last_used_at'])) ?></td>
                <td><div class="actions">
                    <?php if ($k['type'] === 'teams'): ?><a class="btn btn-light btn-sm" href="?tab=koppelingen&amp;setup=<?= (int)$k['id'] ?>">Instellen</a>
                    <?php else: ?><form method="post" onsubmit="return confirm('Een nieuwe sleutel maken? De huidige sleutel werkt daarna niet meer.')"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="koppelingen"><input type="hidden" name="action" value="new_api_key"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>"><button class="btn btn-light btn-sm">Nieuwe sleutel</button></form><?php endif; ?>
                    <form method="post"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="koppelingen"><input type="hidden" name="action" value="toggle_key"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>"><button class="btn btn-light btn-sm"><?= (int)$k['active'] ? 'Uitzetten' : 'Aanzetten' ?></button></form>
                    <form method="post" onsubmit="return confirm('Koppeling verwijderen?')"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="koppelingen"><input type="hidden" name="action" value="delete_key"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>"><button class="btn btn-danger btn-sm">Verwijderen</button></form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div class="panel" style="margin-top:26px">
        <h2>📘 Berichten-API in het kort</h2>
        <p class="help" style="font-size:14px;color:#374151">Stuur een POST naar <code><?= $h($pushUrl) ?></code> met de kop <code>Authorization: Bearer &lt;sleutel&gt;</code> en JSON:</p>
<pre style="background:#0f172a;color:#e2e8f0;padding:14px;border-radius:8px;overflow:auto;font-size:12.5px;line-height:1.5;margin:10px 0">{
  "title": "Studiedag vrijdag",             // verplicht
  "body": "Alle groepen vrij.",             // tekst (HTML wordt platte tekst)
  "image_url": "https://…/foto.jpg",        // of "image_base64"
  "locations": ["Hoofdgebouw"],             // leeg = standaard van de koppeling
  "starts_at": "2026-12-01T08:00:00+01:00", // leeg = direct
  "ends_at": "2026-12-05T17:00:00+01:00",   // leeg = standaard looptijd
  "priority": true, "color": "rood",
  "external_id": "rooster-123"              // zelfde id = bijwerken
}</pre>
        <p class="help" style="font-size:14px;color:#374151">Verwijderen: <code>{"action": "delete", "external_id": "rooster-123"}</code>. Overzicht: <code>GET</code> met dezelfde sleutel. Voorbeeld in PowerShell:</p>
<pre style="background:#0f172a;color:#e2e8f0;padding:14px;border-radius:8px;overflow:auto;font-size:12.5px;line-height:1.5;margin:10px 0">Invoke-RestMethod -Method Post -Uri "<?= $h($pushUrl) ?>" `
  -Headers @{ Authorization = "Bearer UW-SLEUTEL" } -ContentType "application/json; charset=utf-8" `
  -Body '{"title":"Testbericht","body":"Verstuurd met PowerShell"}'</pre>
    </div>

<?php elseif ($tab === 'weergave'): ?>
    <form method="post" class="panel">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="action" value="save_settings">
        <input type="hidden" name="tab" value="weergave">
        <h2>🎨 Weergave van het infoscherm</h2>
        <div class="grid">
            <div>
                <label class="f" for="theme">Kleurstelling</label>
                <select id="theme" name="theme">
                    <option value="donker" <?= $settings['theme'] === 'donker' ? 'selected' : '' ?>>Donker (aanbevolen voor schermen)</option>
                    <option value="licht" <?= $settings['theme'] === 'licht' ? 'selected' : '' ?>>Licht</option>
                </select>
                <div class="help">Geldt als er geen eigen kleuren zijn ingesteld bij Huisstijl.</div>
            </div>
            <div>
                <label class="f">Titel, logo, kleuren en lettertype</label>
                <a class="btn btn-light" href="?tab=huisstijl">🖌️ Naar Huisstijl</a>
                <div class="help">Voor de hele organisatie en per locatie.</div>
            </div>
            <div>
                <label class="f" for="slide_seconds">Tijd per dia (seconden)</label>
                <input type="number" id="slide_seconds" name="slide_seconds" min="5" max="120" value="<?= (int)$settings['slide_seconds'] ?>">
                <div class="help">Per bericht aan te passen. Ook de dia-snelheid van Google Slides in de combinatie-weergave.</div>
            </div>
            <div>
                <label class="f" for="news_slide_max">Nieuwsdia's per ronde</label>
                <input type="number" id="news_slide_max" name="news_slide_max" min="0" max="20" value="<?= (int)$settings['news_slide_max'] ?>">
                <div class="help">Nieuws met een afbeelding als grote dia, tussen de eigen berichten door.</div>
            </div>
            <div>
                <label class="f" for="ticker_speed">Snelheid nieuwsbalk</label>
                <input type="number" id="ticker_speed" name="ticker_speed" min="20" max="200" value="<?= (int)$settings['ticker_speed'] ?>">
                <div class="help">20 = rustig, 60 = normaal, 120 = snel.</div>
            </div>
            <div>
                <label class="f" for="agenda_days">Agenda: dagen vooruit</label>
                <input type="number" id="agenda_days" name="agenda_days" min="1" max="31" value="<?= (int)$settings['agenda_days'] ?>">
                <div class="help">Alleen als er een agenda is gekoppeld (tabblad Agenda).</div>
            </div>
            <div class="full" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <label class="check"><input type="checkbox" name="sidebar" value="1" <?= $settings['sidebar'] === '1' ? 'checked' : '' ?>> Zijbalk met weer (of klok)</label>
                <label class="check"><input type="checkbox" name="ticker" value="1" <?= $settings['ticker'] === '1' ? 'checked' : '' ?>> Nieuwsbalk onderaan</label>
                <label class="check"><input type="checkbox" name="news_slides" value="1" <?= $settings['news_slides'] === '1' ? 'checked' : '' ?>> Nieuws met afbeelding als dia</label>
                <label class="check"><input type="checkbox" name="weather_slide" value="1" <?= $settings['weather_slide'] === '1' ? 'checked' : '' ?>> Weerdia (één keer per ronde)</label>
                <label class="check"><input type="checkbox" name="show_clock" value="1" <?= $settings['show_clock'] === '1' ? 'checked' : '' ?>> Datum en tijd bovenaan</label>
                <label class="check"><input type="checkbox" name="agenda_sidebar" value="1" <?= $settings['agenda_sidebar'] === '1' ? 'checked' : '' ?>> Agenda in de zijbalk</label>
                <label class="check"><input type="checkbox" name="agenda_slide" value="1" <?= $settings['agenda_slide'] === '1' ? 'checked' : '' ?>> Agendadia (één keer per ronde)</label>
            </div>
            <div class="full">
                <h3 style="margin:8px 0 6px;font-size:16px">🚑 BHV en 🎂 verjaardagen</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <label class="check"><input type="checkbox" name="bhv_card" value="1" <?= $settings['bhv_card'] === '1' ? 'checked' : '' ?>> Blok “BHV aanwezig” in de zijbalk</label>
                    <label class="check"><input type="checkbox" name="bhv_names" value="1" <?= $settings['bhv_names'] === '1' ? 'checked' : '' ?>> Met namen (voornaam + voorletter achternaam)</label>
                    <label class="check"><input type="checkbox" name="birthday_slide" value="1" <?= $settings['birthday_slide'] === '1' ? 'checked' : '' ?>> Dia “Vandaag jarig” en melding in de nieuwsbalk</label>
                </div>
                <div class="help" style="margin-top:6px">BHV: telt de medewerkers met BHV = Ja die op de locatie(s) van het scherm zijn ingecheckt (IN). Wie een extra status heeft (bijv. Pauze, Thuiswerken of Vakantie) telt niet mee: het scherm toont liever te weinig dan te veel. Is er niemand, dan kleurt het blok rood. Het scherm ververst dan elke 2 minuten. Zonder zijbalk komt het in de nieuwsbalk.<br>
                    Verjaardagen: alleen medewerkers bij wie een verjaardag (dag-maand) is ingevuld bij Medewerkers. Vul dit alleen in met toestemming van de medewerker; het jaar wordt niet bewaard.<br>
                    Dit is de standaard voor alle schermen. Per scherm zet u het apart aan of uit bij het tabblad Schermen.</div>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">💾 Opslaan</button>
            <a class="btn btn-light" href="../infoscherm.php?preview=1&amp;mode=infoscreen" target="_blank">👁️ Voorbeeld infoscherm</a>
            <a class="btn btn-light" href="../infoscherm.php?preview=1&amp;mode=combined" target="_blank">👁️ Voorbeeld met Google Slides</a>
        </div>
    </form>

<?php elseif ($tab === 'huisstijl'): ?>
    <div class="info">
        <strong>Huisstijl:</strong> logo, titel, kleuren en lettertype. Stel eerst de huisstijl van de <strong>hele organisatie</strong> in; een locatie
        (bijvoorbeeld een school binnen de stichting) kan een eigen huisstijl krijgen. Lege velden van een locatie volgen de organisatie.
        Een scherm met één locatie krijgt de huisstijl van die locatie, een scherm met meer locaties die van de organisatie; bij Schermen kiest u per scherm een andere. Gebruikers zonder beheerrechten kunnen de huisstijl
        van hun eigen locaties aanpassen met het recht <em>Huisstijl aanpassen</em> (Gebruikers › 📺 Infoscherm).
    </div>
    <div class="loc-tabs">
        <?php if ($scope['all']): ?><a href="?tab=huisstijl&amp;loc=" class="<?= $brandLoc === '' ? 'active' : '' ?>">🌐 Hele organisatie<?= isset($brandRows['']) ? ' ✓' : '' ?></a><?php endif; ?>
        <?php foreach ($pickable as $l): ?>
            <a href="?tab=huisstijl&amp;loc=<?= $h(rawurlencode($l)) ?>" class="<?= $l === $brandLoc ? 'active' : '' ?>">📍 <?= $h($l) ?><?= isset($brandRows[$l]) ? ' ✓' : '' ?></a>
        <?php endforeach; ?>
    </div>
    <div class="panel">
        <h2><?= $brandLoc === '' ? '🌐 Huisstijl van de hele organisatie' : '📍 Huisstijl van ' . $h($brandLoc) ?></h2>
        <?php if ($brandLoc !== '' && !isset($brandRows[$brandLoc])): ?><p class="help" style="margin:-6px 0 14px">Deze locatie volgt nu de organisatie. Sla op om een eigen huisstijl te maken.</p><?php endif; ?>
        <?php
        $hidden = '<input type="hidden" name="csrf" value="' . $h($csrf) . '"><input type="hidden" name="action" value="save_brand"><input type="hidden" name="tab" value="huisstijl"><input type="hidden" name="loc" value="' . $h($brandLoc) . '">';
        $reset = isset($brandRows[$brandLoc])
            ? '<button class="btn btn-light" type="submit" name="action" value="reset_brand" formnovalidate onclick="return confirm(\'' . ($brandLoc === '' ? 'Huisstijl van de organisatie verwijderen?' : 'Eigen huisstijl van deze locatie verwijderen?') . '\')">↺ ' . ($brandLoc === '' ? 'Standaard herstellen' : 'Volg de organisatie') . '</button>' : '';
        echo pd_branding_form($brandRows[$brandLoc] ?? pd_branding_blank($brandLoc), $hidden, '../',
            $brandLoc === '' ? pd_branding_resolve([], [], $settings['theme'], '..', '') : $orgBrand,
            '../infoscherm.php?preview=1&mode=infoscreen' . ($brandLoc !== '' ? '&loc=' . rawurlencode($brandLoc) : ''), $reset);
        ?>
    </div>

<?php elseif ($tab === 'schermen'): ?>
    <div class="info">
        <strong>Wat toont een scherm?</strong> Elk scherm (tv, kiosk of tablet) is een gebruikersaccount. Per account kiest u:
        <br>• <strong>Google Slides</strong> — de presentatie (presentatie-ID bij de gebruiker of in de systeemconfiguratie).
        <br>• <strong>Infoscherm</strong> — eigen berichten, nieuws, weer en agenda.
        <br>• <strong>Slides + infoscherm</strong> — de presentatie groot in beeld, met weer, mededelingen en nieuwsbalk eromheen.
        <br>Op een kiosk met medewerkerskaarten verschijnt dit na de ingestelde inactiviteit (“Automatisch tonen” aan); aanraken sluit het weer.
        <br><strong>Welke inhoud?</strong> Dat bepalen de <strong>locaties van het account</strong> (Gebruikers › Zichtbare locaties, in te stellen door de beheerder). Zie de tabel hieronder.
    </div>
    <details class="rules" <?= $scope['all'] ? '' : 'open' ?>>
        <summary>📋 Wat toont een scherm met één, meer of alle locaties?</summary>
        <table class="rules-table">
            <thead><tr><th></th><th>Scherm met 1 locatie</th><th>Meer locaties (bijv. een MFA of een kiosk voor meerdere locaties)</th><th>🌐 Alle locaties (geen locatie aangevinkt)</th></tr></thead>
            <tbody>
                <tr><th>Berichten, nieuws, agenda</th><td>van die locatie + wat voor alle locaties is</td><td>van al zijn locaties samen + wat voor alle locaties is</td><td>alles</td></tr>
                <tr><th>BHV aanwezig, Vandaag jarig</th><td>die locatie</td><td>al zijn locaties samen</td><td>iedereen</td></tr>
                <tr><th>Weer</th><td>de weerplaats van die locatie, anders de algemene</td><td>de eerste van zijn locaties met een eigen weerplaats, anders de algemene</td><td>de algemene weerplaats</td></tr>
                <tr><th>Huisstijl</th><td>die van de locatie (als die er een heeft), anders de organisatie</td><td>de organisatie, of hieronder zelf een kiezen</td><td>de organisatie, of zelf kiezen</td></tr>
            </tbody>
        </table>
        <div class="muted">Een kiosk toont op het infoscherm altijd de inhoud van al zijn locaties, ook als op de kiosk één locatie is gekozen.</div>
    </details>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="action" value="save_screens">
        <input type="hidden" name="tab" value="schermen">
        <table>
            <thead><tr><th>Scherm (account)</th><th>Locaties</th><th>Automatisch tonen</th><th>Weergave</th><th>Infoscherm</th><th>Voorbeeld</th></tr></thead>
            <tbody>
            <?php foreach ($screens as $u): $hasId = trim((string)$u['presentation_id']) !== '' || $globalPresentation !== ''; ?>
                <tr>
                    <td><strong><?= $h($u['display_name'] ?: $u['username']) ?></strong><div class="muted"><?= $h($u['username']) ?> · <?= $h($u['role']) ?></div></td>
                    <td><?= $u['_locs'] ? implode(' ', array_map(fn($l) => '<span class="loc-badge">📍 ' . $h($l) . '</span>', $u['_locs'])) : '<span class="loc-badge all" title="Geen locatie aangevinkt: dit scherm toont alle locaties">🌐 Alle locaties</span>' ?></td>
                    <td><label class="check"><input type="checkbox" name="can_show[<?= (int)$u['id'] ?>]" value="1" <?= (int)$u['can_show_presentation'] ? 'checked' : '' ?>> Aan</label>
                        <div class="muted">na <?= (int)$u['presentation_idle_seconds'] ?> sec</div></td>
                    <td>
                        <select name="screen_mode[<?= (int)$u['id'] ?>]" style="max-width:200px">
                            <option value="slides" <?= $u['screen_mode'] === 'slides' ? 'selected' : '' ?>>Google Slides<?= $hasId ? '' : ' (geen ID ingesteld)' ?></option>
                            <option value="infoscreen" <?= $u['screen_mode'] === 'infoscreen' ? 'selected' : '' ?>>Infoscherm</option>
                            <option value="combined" <?= $u['screen_mode'] === 'combined' ? 'selected' : '' ?>>Slides + infoscherm</option>
                        </select>
                    </td>
                    <?php $uf = json_decode((string)$u['features'], true) ?: [];
                          $parts = $uf['infoscreenParts'] ?? [];
                          $ub = is_string($uf['infoscreenBrand'] ?? null) ? $uf['infoscreenBrand'] : null;
                          $uLocs = is_array($uf['locations'] ?? null) ? array_values(array_intersect($uf['locations'], $locations)) : [];
                          $auto = count($uLocs) === 1 && isset($brandRows[$uLocs[0]]) ? $uLocs[0] : 'organisatie'; ?>
                    <td>
                        <div class="screen-parts">
                        <label>Huisstijl</label>
                        <select name="brand[<?= (int)$u['id'] ?>]" title="Automatisch: een scherm met één locatie krijgt de huisstijl van die locatie, anders die van de organisatie">
                            <option value="*auto*" <?= $ub === null ? 'selected' : '' ?>>Automatisch (<?= $h($auto) ?>)</option>
                            <option value="*org*" <?= $ub === '' ? 'selected' : '' ?>>Organisatie</option>
                            <?php foreach ($pickable as $l): if (!isset($brandRows[$l]) && $ub !== $l) continue; ?>
                                <option value="<?= $h($l) ?>" <?= $ub === $l ? 'selected' : '' ?>><?= $h($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php foreach (['birthdays' => ['birthday_slide', 'Vandaag jarig'], 'bhv' => ['bhv_card', 'BHV aanwezig']] as $pk => [$sk, $pl]):
                            $cur = $parts[$pk] ?? 'default'; ?>
                        <label><?= $pl ?></label>
                        <select name="parts[<?= (int)$u['id'] ?>][<?= $pk ?>]" title="Standaard = de instelling bij Weergave">
                            <option value="default" <?= $cur === 'default' ? 'selected' : '' ?>>Standaard (<?= $settings[$sk] === '1' ? 'aan' : 'uit' ?>)</option>
                            <option value="on" <?= $cur === 'on' ? 'selected' : '' ?>>Aan</option>
                            <option value="off" <?= $cur === 'off' ? 'selected' : '' ?>>Uit</option>
                        </select>
                        <?php endforeach; ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($u['screen_mode'] === 'slides'):
                            $pid = trim((string)$u['presentation_id']) !== '' ? trim((string)$u['presentation_id']) : $globalPresentation; ?>
                            <?php if ($pid !== ''): ?>
                                <a class="btn btn-light btn-sm" target="_blank" rel="noopener" title="Toont de Google Slides presentatie van dit scherm"
                                   href="https://docs.google.com/presentation/d/<?= $h(rawurlencode($pid)) ?>/embed?start=true&amp;loop=true&amp;delayms=<?= max(5, (int)$settings['slide_seconds']) * 1000 ?>">👁️ Slides</a>
                            <?php else: ?><span class="muted">geen presentatie</span><?php endif; ?>
                        <?php else: ?>
                            <a class="btn btn-light btn-sm" target="_blank" title="Toont wat dit scherm laat zien: berichten, nieuws en weer van de locaties van dit account"
                               href="../infoscherm.php?preview=1&amp;as=<?= (int)$u['id'] ?>">👁️ Voorbeeld</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="form-actions"><button class="btn btn-primary" type="submit">💾 Opslaan</button>
            <span class="help" style="margin-left:8px">Het voorbeeld toont de opgeslagen keuze; sla eerst op na een wijziging.</span></div>
    </form>
    <?php if (!$screens): ?><div class="empty"><div class="big">🖥️</div>Er zijn nog geen schermen voor uw locaties. De beheerder koppelt een schermaccount aan locaties bij Gebruikers.</div><?php endif; ?>

    <?php if ($isAdminRole): ?>
    <div class="panel" style="margin-top:26px">
        <h2>🖥️ Een scherm dat alleen het infoscherm toont</h2>
        <p class="help" style="font-size:14px;color:#374151">Voor een tv of scherm zonder aanraking: log op dat scherm in met het schermaccount (vink “Blijf ingelogd” aan) en open:</p>
        <p style="margin:10px 0"><code><?= $h($appUrl) ?>/infoscherm.php</code></p>
        <p class="help" style="font-size:14px;color:#374151">Met een kiosk-token (<a href="kiosk_tokens_manage.php">Kiosk tokens</a>) logt het scherm zelf in, ook na een herstart. Open dan in de kioskbrowser (bijvoorbeeld Edge in kioskmodus):</p>
        <p style="margin:10px 0"><code><?= $h($appUrl) ?>/kiosk_login.php?token=<em>UW-TOKEN</em>&amp;page=infoscherm</code></p>
        <p class="help">Het infoscherm haalt elke 5 minuten nieuwe gegevens op, werkt door bij een korte internetstoring en laadt zichzelf opnieuw na een update van PeopleDisplay.
            De locaties van het schermaccount bepalen welke berichten, nieuwsbronnen en welk weer het toont.</p>
    </div>

    <div class="panel">
        <h2>⏱️ Automatisch bijwerken (aanbevolen)</h2>
        <p class="help" style="font-size:14px;color:#374151">Nieuws en weer worden ook bijgewerkt zodra een scherm gegevens opvraagt. Met een cronjob (elke 15 minuten) hoeft een scherm daar nooit op te wachten:</p>
        <p style="margin:10px 0"><strong>Cronjob in het hostingpaneel:</strong><br><code>php <?= $h($cronPath) ?></code></p>
        <?php if (hasAdminFeature('manage_system_config') && pd_cron_key() !== null): ?>
        <p style="margin:10px 0"><strong>Of via een webcron (bijv. cron-job.org):</strong><br><code><?= $h($appUrl) ?>/cron_endpoint.php?action=infoscreen_refresh&amp;key=<?= $h(rawurlencode(pd_cron_key())) ?></code></p>
        <p class="help">De sleutel is van deze installatie. Alle cronjobs en de sleutel staan bij <a href="cron_manage.php">Automatische taken</a>.</p>
        <?php else: ?>
        <p style="margin:10px 0"><strong>Of via een webcron (bijv. cron-job.org):</strong><br><code><?= $h($appUrl) ?>/cron_endpoint.php?action=infoscreen_refresh&amp;key=<em>SLEUTEL</em></code></p>
        <p class="help">De sleutel staat bij Beheer &gt; Automatische taken (alleen voor beheerders met het recht Systeemconfiguratie).</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>
</div>
<?= pd_infoscreen_location_picker_js() ?>
<?= pd_infoscreen_text_counter_js() ?>
<?= pd_infoscreen_message_style_js() ?>
<?= pd_branding_form_js() ?>
</body>
</html>
