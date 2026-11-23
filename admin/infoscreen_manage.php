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
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/version.php';
require_once __DIR__ . '/../includes/infoscreen.php';
require_once __DIR__ . '/auth_helper.php';

requireAdmin();
requireAdminFeature('manage_infoscreen');
pd_infoscreen_ensure_schema($db);

$appRoot = dirname(__DIR__);
$tabs = [
    'berichten' => '📝 Berichten',
    'nieuws'    => '📰 Nieuws',
    'weer'      => '🌤️ Weer',
    'weergave'  => '🎨 Weergave',
    'schermen'  => '🖥️ Schermen',
];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'berichten';
$colors = ['blauw' => '#1d4ed8', 'groen' => '#15803d', 'oranje' => '#ea7a12', 'rood' => '#dc2626', 'paars' => '#7c3aed', 'grijs' => '#475569'];

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

function pd_is_location(?string $value, array $locations): ?string
{
    $value = trim((string)$value);
    return $value !== '' && in_array($value, $locations, true) ? $value : null;
}

// Locations (names, as used everywhere in PeopleDisplay)
try {
    $locations = $db->query("SELECT location_name FROM locations WHERE active = 1 ORDER BY sort_order, location_name")->fetchAll(PDO::FETCH_COLUMN);
} catch (\Throwable $e) {
    $locations = $db->query("SELECT location_name FROM locations ORDER BY location_name")->fetchAll(PDO::FETCH_COLUMN);
}

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
                $id = (int)($_POST['id'] ?? 0);
                $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 200);
                if ($title === '') pd_is_redirect('berichten', 'Vul een titel in.', false, $id ? '&edit=' . $id : '&new=1');
                $body = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 2000);
                $color = isset($colors[$_POST['color'] ?? '']) ? $_POST['color'] : 'blauw';
                $starts = pd_is_dt($_POST['starts_at'] ?? '');
                $ends = pd_is_dt($_POST['ends_at'] ?? '');
                if ($starts && $ends && $ends <= $starts) pd_is_redirect('berichten', 'De einddatum moet na de begindatum liggen.', false, $id ? '&edit=' . $id : '&new=1');
                $duration = trim((string)($_POST['duration_seconds'] ?? '')) === '' ? null : max(5, min(300, (int)$_POST['duration_seconds']));
                $location = pd_is_location($_POST['location'] ?? '', $locations);
                $priority = !empty($_POST['priority']) ? 1 : 0;
                $active = !empty($_POST['active']) ? 1 : 0;

                $old = null;
                if ($id) {
                    $stmt = $db->prepare("SELECT image_path FROM infoscreen_messages WHERE id = ?");
                    $stmt->execute([$id]);
                    $old = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$old) pd_is_redirect('berichten', 'Bericht niet gevonden.', false);
                }
                $image = $old['image_path'] ?? null;
                if (!empty($_POST['remove_image'])) $image = null;
                if (!empty($_FILES['image']['name'])) {
                    $image = pd_infoscreen_store_upload($_FILES['image'], $appRoot);
                }

                if ($id) {
                    $db->prepare("UPDATE infoscreen_messages SET title = ?, body = ?, image_path = ?, location = ?, starts_at = ?, ends_at = ?,
                                  duration_seconds = ?, priority = ?, color = ?, active = ? WHERE id = ?")
                       ->execute([$title, $body, $image, $location, $starts, $ends, $duration, $priority, $color, $active, $id]);
                    if (($old['image_path'] ?? null) && $old['image_path'] !== $image) pd_infoscreen_delete_image($db, $old['image_path'], $appRoot);
                    pd_is_redirect('berichten', 'Bericht opgeslagen.');
                }
                $db->prepare("INSERT INTO infoscreen_messages (title, body, image_path, location, starts_at, ends_at, duration_seconds, priority, color, active, created_by)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$title, $body, $image, $location, $starts, $ends, $duration, $priority, $color, $active, (int)$_SESSION['user_id']]);
                pd_is_redirect('berichten', 'Bericht toegevoegd.');

            case 'toggle_message':
                $db->prepare("UPDATE infoscreen_messages SET active = 1 - active WHERE id = ?")->execute([(int)$_POST['id']]);
                pd_is_redirect('berichten', 'Bericht bijgewerkt.');

            case 'delete_message':
                $stmt = $db->prepare("SELECT image_path FROM infoscreen_messages WHERE id = ?");
                $stmt->execute([(int)$_POST['id']]);
                $path = $stmt->fetchColumn();
                $db->prepare("DELETE FROM infoscreen_messages WHERE id = ?")->execute([(int)$_POST['id']]);
                pd_infoscreen_delete_image($db, $path ?: null, $appRoot);
                pd_is_redirect('berichten', 'Bericht verwijderd.');

            case 'save_source':
                $id = (int)($_POST['id'] ?? 0);
                $type = ($_POST['type'] ?? '') === 'weather' ? 'weather' : 'rss';
                $tabFor = $type === 'weather' ? 'weer' : 'nieuws';
                $editQs = $id ? '&edit=' . $id : '&new=1';
                $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 150);
                if ($name === '') pd_is_redirect($tabFor, 'Vul een naam in.', false, $editQs);
                $location = pd_is_location($_POST['location'] ?? '', $locations);
                $url = $provider = $lat = $lon = $attribution = null;
                $maxItems = 8;
                $showImages = 1;
                if ($type === 'rss') {
                    $url = pd_infoscreen_clean_url($_POST['url'] ?? '');
                    if (!$url) pd_is_redirect($tabFor, 'Vul een geldig webadres van de feed in (begint met https://).', false, $editQs);
                    $maxItems = max(1, min(30, (int)($_POST['max_items'] ?? 8)));
                    $showImages = !empty($_POST['show_images']) ? 1 : 0;
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
                    $db->prepare("UPDATE infoscreen_sources SET name = ?, url = ?, provider = ?, latitude = ?, longitude = ?, location = ?, max_items = ?,
                                  show_images = ?, attribution = ?, active = ?, last_fetch_at = NULL WHERE id = ? AND type = ?")
                       ->execute([$name, $url, $provider, $lat, $lon, $location, $maxItems, $showImages, $attribution, $active, $id, $type]);
                } else {
                    $db->prepare("INSERT INTO infoscreen_sources (type, name, url, provider, latitude, longitude, location, max_items, show_images, attribution, active)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                       ->execute([$type, $name, $url, $provider, $lat, $lon, $location, $maxItems, $showImages, $attribution, $active]);
                    $id = (int)$db->lastInsertId();
                }
                // Fetch right away so the admin sees whether it works
                $stmt = $db->prepare("SELECT * FROM infoscreen_sources WHERE id = ?");
                $stmt->execute([$id]);
                $result = pd_infoscreen_fetch_source($db, $stmt->fetch(PDO::FETCH_ASSOC));
                pd_is_redirect($tabFor, 'Opgeslagen. ' . ($result['ok'] ? $result['message'] : 'Ophalen lukte nog niet: ' . $result['message']), $result['ok']);

            case 'fetch_source':
                $stmt = $db->prepare("SELECT * FROM infoscreen_sources WHERE id = ?");
                $stmt->execute([(int)$_POST['id']]);
                $source = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$source) pd_is_redirect($back, 'Bron niet gevonden.', false);
                $result = pd_infoscreen_fetch_source($db, $source);
                pd_is_redirect($back, $source['name'] . ': ' . $result['message'], $result['ok']);

            case 'toggle_source':
                $db->prepare("UPDATE infoscreen_sources SET active = 1 - active WHERE id = ?")->execute([(int)$_POST['id']]);
                pd_is_redirect($back, 'Bron bijgewerkt.');

            case 'delete_source':
                $db->prepare("DELETE FROM infoscreen_items WHERE source_id = ?")->execute([(int)$_POST['id']]);
                $db->prepare("DELETE FROM infoscreen_sources WHERE id = ?")->execute([(int)$_POST['id']]);
                pd_is_redirect($back, 'Bron verwijderd.');

            case 'save_settings':
                pd_infoscreen_save_settings($db, $_POST);
                pd_is_redirect('weergave', 'Weergave opgeslagen. Open schermen nemen dit binnen 5 minuten over.');

            case 'save_screens':
                $modes = $_POST['screen_mode'] ?? [];
                $enabled = $_POST['can_show'] ?? [];
                $stmt = $db->prepare("UPDATE users SET screen_mode = ?, can_show_presentation = ? WHERE id = ? AND deleted_at IS NULL");
                foreach ($modes as $userId => $mode) {
                    if (!in_array($mode, PD_IS_MODES, true)) continue;
                    $stmt->execute([$mode, !empty($enabled[$userId]) ? 1 : 0, (int)$userId]);
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

$messages = $db->query("SELECT * FROM infoscreen_messages ORDER BY active DESC, priority DESC, COALESCE(starts_at, created_at) DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
$sources = $db->query("SELECT s.*, (SELECT COUNT(*) FROM infoscreen_items i WHERE i.source_id = s.id) AS item_count,
                              (SELECT COUNT(*) FROM infoscreen_items i WHERE i.source_id = s.id AND i.image_url IS NOT NULL) AS image_count
                       FROM infoscreen_sources s ORDER BY s.type, s.sort_order, s.id")->fetchAll(PDO::FETCH_ASSOC);
$rssSources = array_values(array_filter($sources, fn($s) => $s['type'] === 'rss'));
$weatherSources = array_values(array_filter($sources, fn($s) => $s['type'] === 'weather'));

$editMessage = null;
$editSource = null;
if ($editId && $tab === 'berichten') {
    foreach ($messages as $m) if ((int)$m['id'] === $editId) $editMessage = $m;
}
if ($editId && in_array($tab, ['nieuws', 'weer'], true)) {
    foreach ($sources as $s) if ((int)$s['id'] === $editId) $editSource = $s;
}

$screens = [];
if ($tab === 'schermen') {
    $screens = $db->query("SELECT id, username, display_name, role, can_show_presentation, presentation_id, screen_mode, presentation_idle_seconds, features
                           FROM users WHERE deleted_at IS NULL AND active = 1 ORDER BY role = 'user' DESC, display_name, username")->fetchAll(PDO::FETCH_ASSOC);
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
    .pill.prio { background: #fee2e2; color: #b91c1c; } .pill.ok { background: #d1fae5; color: #065f46; } .pill.err { background: #fee2e2; color: #991b1b; }
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
    @media (max-width: 760px) { .grid { grid-template-columns: 1fr; } .container { padding: 20px 16px; } td, th { padding: 8px 6px; } .hide-sm { display: none; } }
</style>
</head>
<body>
<a href="dashboard.php" class="back-link">← Terug naar Dashboard</a>
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

    <nav class="tabs">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="?tab=<?= $key ?>" class="<?= $key === $tab ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($flash): ?>
        <div class="alert <?= str_starts_with($flash, '❌') ? 'err' : 'ok' ?>"><?= $h($flash) ?></div>
    <?php endif; ?>

<?php if ($tab === 'berichten'): ?>
    <?php if ($showForm): $m = $editMessage ?? ['id' => 0, 'title' => '', 'body' => '', 'image_path' => null, 'location' => null, 'starts_at' => null, 'ends_at' => null, 'duration_seconds' => null, 'priority' => 0, 'color' => 'blauw', 'active' => 1]; ?>
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
                    <div class="help">JPG, PNG, GIF of WEBP, maximaal 5 MB. Liggend formaat (16:9) werkt het best.</div>
                </div>
                <div class="full">
                    <label class="f">Achtergrondkleur</label>
                    <div class="colors">
                        <?php foreach ($colors as $key => $hex): ?>
                            <label><input type="radio" name="color" value="<?= $key ?>" <?= $m['color'] === $key ? 'checked' : '' ?>><span><i style="background:<?= $hex ?>"></i><?= ucfirst($key) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>
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
                    <label class="f" for="location">Locatie</label>
                    <select id="location" name="location">
                        <option value="">Alle locaties</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= $h($loc) ?>" <?= $m['location'] === $loc ? 'selected' : '' ?>><?= $h($loc) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="help">Een scherm toont de berichten van de locaties die bij het schermaccount zijn ingesteld.</div>
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

    <div class="toolbar">
        <div class="muted"><?= count($messages) ?> bericht(en)</div>
        <?php if (!$showForm): ?><a class="btn btn-primary" href="?tab=berichten&amp;new=1">➕ Nieuw bericht</a><?php endif; ?>
    </div>
    <?php if (!$messages): ?>
        <div class="empty"><div class="big">📝</div>Nog geen berichten. Maak het eerste bericht aan met “Nieuw bericht”.</div>
    <?php else: ?>
    <table>
        <thead><tr><th></th><th>Bericht</th><th class="hide-sm">Locatie</th><th class="hide-sm">Periode</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($messages as $m): [$statusText, $statusCls] = pd_is_status($m); ?>
            <tr>
                <td><?php if ($m['image_path']): ?><img class="thumb" src="../<?= $h($m['image_path']) ?>" alt=""><?php else: ?><span class="thumb" style="background:<?= $colors[$m['color']] ?? '#1d4ed8' ?>"></span><?php endif; ?></td>
                <td><strong><?= $h($m['title']) ?></strong><?php if ((int)$m['priority']): ?> <span class="pill prio">Belangrijk</span><?php endif; ?>
                    <?php if ($m['body']): ?><div class="muted"><?= $h(mb_strimwidth($m['body'], 0, 90, '…')) ?></div><?php endif; ?></td>
                <td class="hide-sm"><?= $h($m['location'] ?: 'Alle') ?></td>
                <td class="hide-sm muted"><?= $h(pd_is_period($m)) ?></td>
                <td><span class="pill <?= $statusCls ?>"><?= $statusText ?></span></td>
                <td>
                    <div class="actions">
                        <a class="btn btn-light btn-sm" href="?tab=berichten&amp;edit=<?= (int)$m['id'] ?>">Bewerken</a>
                        <form method="post"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="berichten"><input type="hidden" name="action" value="toggle_message"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-light btn-sm"><?= (int)$m['active'] ? 'Uitzetten' : 'Aanzetten' ?></button></form>
                        <form method="post" onsubmit="return confirm('Bericht verwijderen?')"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="berichten"><input type="hidden" name="action" value="delete_message"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-danger btn-sm">Verwijderen</button></form>
                    </div>
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
        <strong>Welke feeds?</strong> Elke RSS- of Atom-feed werkt. Handig zijn feeds van uw eigen organisatie: een WordPress-website heeft er standaard één op
        <code>https://uwwebsite.nl/feed/</code>, en veel gemeenten, scholen en verenigingen publiceren hun nieuws ook zo.
        Nieuws met een afbeelding verschijnt als grote dia, alle koppen lopen door de nieuwsbalk onderaan.
    </div>

    <?php if ($showForm): $s = $editSource ?? ['id' => 0, 'name' => '', 'url' => '', 'location' => null, 'max_items' => 8, 'show_images' => 1, 'attribution' => '', 'active' => 1]; ?>
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
                    <label class="f" for="location">Locatie</label>
                    <select id="location" name="location">
                        <option value="">Alle locaties</option>
                        <?php foreach ($locations as $loc): ?><option value="<?= $h($loc) ?>" <?= $s['location'] === $loc ? 'selected' : '' ?>><?= $h($loc) ?></option><?php endforeach; ?>
                    </select>
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
        <?php if (!$showForm): ?><a class="btn btn-primary" href="?tab=nieuws&amp;new=1">➕ Nieuwsbron toevoegen</a><?php endif; ?>
    </div>
    <?php if (!$rssSources): ?>
        <div class="empty"><div class="big">📰</div>Nog geen nieuwsbronnen.</div>
    <?php else: ?>
    <table>
        <thead><tr><th>Bron</th><th class="hide-sm">Locatie</th><th>Berichten</th><th>Laatst opgehaald</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rssSources as $s): $ok = str_starts_with((string)$s['last_status'], 'OK'); ?>
            <tr>
                <td><strong><?= $h($s['name']) ?></strong> <?php if (!(int)$s['active']): ?><span class="pill off">Uit</span><?php endif; ?>
                    <div class="muted"><code><?= $h(mb_strimwidth((string)$s['url'], 0, 70, '…')) ?></code></div></td>
                <td class="hide-sm"><?= $h($s['location'] ?: 'Alle') ?></td>
                <td><?= (int)$s['item_count'] ?> <span class="muted">(<?= (int)$s['image_count'] ?> met afbeelding<?= (int)$s['show_images'] ? '' : ', uitgezet' ?>)</span></td>
                <td><?php if ($s['last_status']): ?><span class="pill <?= $ok ? 'ok' : 'err' ?>"><?= $ok ? 'OK' : 'Fout' ?></span><?php endif; ?>
                    <span class="muted"><?= $h(pd_is_ago($s['last_fetch_at'])) ?></span>
                    <?php if (!$ok && $s['last_status']): ?><div class="muted" style="color:#991b1b"><?= $h(preg_replace('/^Fout: /', '', $s['last_status'])) ?></div><?php endif; ?></td>
                <td><div class="actions">
                    <form method="post"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="nieuws"><input type="hidden" name="action" value="fetch_source"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-light btn-sm">🔄 Nu ophalen</button></form>
                    <a class="btn btn-light btn-sm" href="?tab=nieuws&amp;edit=<?= (int)$s['id'] ?>">Bewerken</a>
                    <form method="post"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="nieuws"><input type="hidden" name="action" value="toggle_source"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-light btn-sm"><?= (int)$s['active'] ? 'Uitzetten' : 'Aanzetten' ?></button></form>
                    <form method="post" onsubmit="return confirm('Nieuwsbron verwijderen?')"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="nieuws"><input type="hidden" name="action" value="delete_source"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-danger btn-sm">Verwijderen</button></form>
                </div></td>
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

    <?php if ($showForm): $s = $editSource ?? ['id' => 0, 'name' => '', 'provider' => 'buienradar', 'latitude' => '', 'longitude' => '', 'location' => null, 'active' => 1]; ?>
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
                    <label class="f" for="location">Locatie</label>
                    <select id="location" name="location">
                        <option value="">Alle locaties</option>
                        <?php foreach ($locations as $loc): ?><option value="<?= $h($loc) ?>" <?= $s['location'] === $loc ? 'selected' : '' ?>><?= $h($loc) ?></option><?php endforeach; ?>
                    </select>
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
        <?php if (!$showForm): ?><a class="btn btn-primary" href="?tab=weer&amp;new=1">➕ Weerplaats toevoegen</a><?php endif; ?>
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
                <td class="hide-sm"><?= $h($s['location'] ?: 'Alle') ?></td>
                <td class="wx-now"><?php if (is_array($wx) && isset($wx['current'])): ?><?= $h(round($wx['current']['temp'])) ?>°C, <?= $h($wx['current']['desc']) ?><?php if (!empty($wx['station'])): ?><div class="muted">Meetstation <?= $h($wx['station']) ?></div><?php endif; ?><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                <td><?php if ($s['last_status']): ?><span class="pill <?= $ok ? 'ok' : 'err' ?>"><?= $ok ? 'OK' : 'Fout' ?></span><?php endif; ?>
                    <span class="muted"><?= $h(pd_is_ago($s['last_fetch_at'])) ?></span>
                    <?php if (!$ok && $s['last_status']): ?><div class="muted" style="color:#991b1b"><?= $h(preg_replace('/^Fout: /', '', $s['last_status'])) ?></div><?php endif; ?></td>
                <td><div class="actions">
                    <form method="post"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="weer"><input type="hidden" name="action" value="fetch_source"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-light btn-sm">🔄 Nu ophalen</button></form>
                    <a class="btn btn-light btn-sm" href="?tab=weer&amp;edit=<?= (int)$s['id'] ?>">Bewerken</a>
                    <form method="post"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="weer"><input type="hidden" name="action" value="toggle_source"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-light btn-sm"><?= (int)$s['active'] ? 'Uitzetten' : 'Aanzetten' ?></button></form>
                    <form method="post" onsubmit="return confirm('Weerplaats verwijderen?')"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="tab" value="weer"><input type="hidden" name="action" value="delete_source"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-danger btn-sm">Verwijderen</button></form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

<?php elseif ($tab === 'weergave'): ?>
    <form method="post" class="panel">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="action" value="save_settings">
        <input type="hidden" name="tab" value="weergave">
        <h2>🎨 Weergave van het infoscherm</h2>
        <div class="grid">
            <div>
                <label class="f" for="title">Titel bovenaan</label>
                <input type="text" id="title" name="title" maxlength="80" value="<?= $h($settings['title']) ?>" placeholder="Bijv. Welkom bij OBS De Linde">
                <div class="help">Leeg = geen titel, alleen datum en tijd.</div>
            </div>
            <div>
                <label class="f" for="theme">Kleurstelling</label>
                <select id="theme" name="theme">
                    <option value="donker" <?= $settings['theme'] === 'donker' ? 'selected' : '' ?>>Donker (aanbevolen voor schermen)</option>
                    <option value="licht" <?= $settings['theme'] === 'licht' ? 'selected' : '' ?>>Licht</option>
                </select>
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
            <div></div>
            <div class="full" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <label class="check"><input type="checkbox" name="sidebar" value="1" <?= $settings['sidebar'] === '1' ? 'checked' : '' ?>> Zijbalk met weer (of klok)</label>
                <label class="check"><input type="checkbox" name="ticker" value="1" <?= $settings['ticker'] === '1' ? 'checked' : '' ?>> Nieuwsbalk onderaan</label>
                <label class="check"><input type="checkbox" name="news_slides" value="1" <?= $settings['news_slides'] === '1' ? 'checked' : '' ?>> Nieuws met afbeelding als dia</label>
                <label class="check"><input type="checkbox" name="weather_slide" value="1" <?= $settings['weather_slide'] === '1' ? 'checked' : '' ?>> Weerdia (één keer per ronde)</label>
                <label class="check"><input type="checkbox" name="show_clock" value="1" <?= $settings['show_clock'] === '1' ? 'checked' : '' ?>> Datum en tijd bovenaan</label>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">💾 Opslaan</button>
            <a class="btn btn-light" href="../infoscherm.php?preview=1&amp;mode=infoscreen" target="_blank">👁️ Voorbeeld infoscherm</a>
            <a class="btn btn-light" href="../infoscherm.php?preview=1&amp;mode=combined" target="_blank">👁️ Voorbeeld met Google Slides</a>
        </div>
    </form>

<?php elseif ($tab === 'schermen'): ?>
    <div class="info">
        <strong>Wat toont een scherm?</strong> Per gebruikersaccount (meestal het account van een kiosk of tv-scherm) kiest u wat er verschijnt:
        <br>• <strong>Google Slides</strong> — de presentatie zoals nu (presentatie-ID bij de gebruiker of in de systeemconfiguratie).
        <br>• <strong>Infoscherm</strong> — eigen berichten, nieuws en weer.
        <br>• <strong>Slides + infoscherm</strong> — de Google Slides presentatie groot in beeld, met weer, mededelingen en nieuwsbalk eromheen.
        <br>Op een aanraakscherm met medewerkerskaarten verschijnt dit na de ingestelde inactiviteit (“Automatisch tonen” moet aan staan); aanraken sluit het weer.
    </div>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="action" value="save_screens">
        <input type="hidden" name="tab" value="schermen">
        <table>
            <thead><tr><th>Gebruiker</th><th class="hide-sm">Rol</th><th>Automatisch tonen</th><th>Weergave</th><th class="hide-sm">Na inactiviteit</th></tr></thead>
            <tbody>
            <?php foreach ($screens as $u): $hasId = trim((string)$u['presentation_id']) !== '' || $globalPresentation !== ''; ?>
                <tr>
                    <td><strong><?= $h($u['display_name'] ?: $u['username']) ?></strong><div class="muted"><?= $h($u['username']) ?></div></td>
                    <td class="hide-sm muted"><?= $h($u['role']) ?></td>
                    <td><label class="check"><input type="checkbox" name="can_show[<?= (int)$u['id'] ?>]" value="1" <?= (int)$u['can_show_presentation'] ? 'checked' : '' ?>> Aan</label></td>
                    <td>
                        <select name="screen_mode[<?= (int)$u['id'] ?>]" style="max-width:230px">
                            <option value="slides" <?= $u['screen_mode'] === 'slides' ? 'selected' : '' ?>>Google Slides<?= $hasId ? '' : ' (geen ID ingesteld)' ?></option>
                            <option value="infoscreen" <?= $u['screen_mode'] === 'infoscreen' ? 'selected' : '' ?>>Infoscherm</option>
                            <option value="combined" <?= $u['screen_mode'] === 'combined' ? 'selected' : '' ?>>Slides + infoscherm</option>
                        </select>
                    </td>
                    <td class="hide-sm muted"><?= (int)$u['presentation_idle_seconds'] ?> sec</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="form-actions"><button class="btn btn-primary" type="submit">💾 Opslaan</button></div>
    </form>

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
        <p style="margin:10px 0"><strong>Of via een webcron (bijv. cron-job.org):</strong><br><code><?= $h($appUrl) ?>/cron_endpoint.php?action=infoscreen_refresh&amp;key=<em>UW-CRON-SLEUTEL</em></code></p>
    </div>
<?php endif; ?>
</div>
</body>
</html>
