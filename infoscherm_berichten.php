<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * infoscherm_berichten.php
 * Upload naar: /infoscherm_berichten.php
 *
 * Infoscreen for editors (2.6.0), e.g. reception or caretaker. A user with the right
 * "infoscreenEditor" places and edits messages, a user with "infoscreenBranding" (2.9.0) sets
 * the huisstijl, both only for the infoscreen locations of that user. Messages for all
 * locations (or for other locations) are shown read-only. SuperAdmin and admins with
 * "Infoscherm beheren" may do everything here as well.
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

session_start();
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/includes/logout_checker.php';
require_once __DIR__ . '/includes/infoscreen.php';
require_once __DIR__ . '/admin/auth_helper.php';

pd_infoscreen_ensure_schema($db);
$userId = (int)$_SESSION['user_id'];
$canManage = hasAdminFeature('manage_infoscreen');
$scope = pd_infoscreen_editor_scope($db, $userId, $canManage);
if ($scope === null) {
    header('Location: frontpage.php');
    exit;
}

$appRoot = __DIR__;
$tab = ($_GET['tab'] ?? '') === 'huisstijl' && $scope['branding'] ? 'huisstijl' : ($scope['messages'] ? 'berichten' : 'huisstijl');
if ($scope['all']) {
    try {
        $pickable = $db->query("SELECT location_name FROM locations WHERE active = 1 ORDER BY sort_order, location_name")->fetchAll(PDO::FETCH_COLUMN);
    } catch (\Throwable $e) {
        $pickable = $db->query("SELECT location_name FROM locations ORDER BY location_name")->fetchAll(PDO::FETCH_COLUMN);
    }
} else {
    $pickable = $scope['locations'];
}

function pd_isb_redirect(string $flash, bool $ok = true, string $qs = ''): void
{
    $_SESSION['pd_flash'] = ($ok ? '✅ ' : '❌ ') . $flash;
    header('Location: infoscherm_berichten.php' . $qs);
    exit;
}

/** May this scope change the huisstijl of $loc ('' = organisation, only with all locations)? */
function pd_isb_brand_allowed(array $scope, string $loc, array $pickable): bool
{
    if (!$scope['branding']) return false;
    if ($loc === '') return $scope['all'];
    return in_array($loc, $pickable, true);
}

function pd_isb_dt(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

/** The message, or null when it does not exist or this user may not change it. */
function pd_isb_editable(PDO $db, array $scope, int $id): ?array
{
    $stmt = $db->prepare("SELECT * FROM infoscreen_messages WHERE id = ?");
    $stmt->execute([$id]);
    $m = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($m && pd_infoscreen_scope_allows($scope, pd_infoscreen_locs($m['locations']))) ? $m : null;
}

// ─── POST ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!pd_infoscreen_csrf_ok($_POST['csrf'] ?? null)) {
        pd_isb_redirect('De pagina was verlopen. Probeer het opnieuw.', false);
    }
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($action === 'save_brand' || $action === 'reset_brand') {
            $loc = (string)($_POST['loc'] ?? '');
            $qs = '?tab=huisstijl&loc=' . rawurlencode($loc);
            if (!pd_isb_brand_allowed($scope, $loc, $pickable)) pd_isb_redirect('De huisstijl van deze locatie mag u niet aanpassen.', false, '?tab=huisstijl');
            if ($action === 'reset_brand') {
                pd_branding_reset($db, $loc, $appRoot);
                pd_isb_redirect('Eigen huisstijl verwijderd: de locatie volgt weer de organisatie.', true, $qs);
            }
            pd_branding_save($db, $loc, $_POST, $_FILES, $appRoot, $userId);
            pd_isb_redirect('Huisstijl opgeslagen. Open schermen nemen dit binnen 5 minuten over.', true, $qs);
        }
        if (!$scope['messages'] && in_array($action, ['save', 'toggle', 'delete'], true)) {
            pd_isb_redirect('U mag geen berichten plaatsen.', false, '?tab=huisstijl');
        }
        if ($action === 'save') {
            $back = $id ? '?edit=' . $id : '?new=1';
            $old = null;
            if ($id) {
                $old = pd_isb_editable($db, $scope, $id);
                if (!$old) pd_isb_redirect('Dit bericht mag u niet aanpassen.', false);
            }
            $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 200);
            if ($title === '') pd_isb_redirect('Vul een titel in.', false, $back);
            $locs = pd_infoscreen_locs_from_post($pickable, $scope['all']);
            if ($locs === false) pd_isb_redirect('Kies minimaal één locatie.', false, $back);
            $body = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 2000);
            $color = pd_infoscreen_color_from_post($_POST);
            [$imgLayout, $imgFit, $imgSize] = pd_infoscreen_image_options_from_post($_POST);
            $starts = pd_isb_dt($_POST['starts_at'] ?? '');
            $ends = pd_isb_dt($_POST['ends_at'] ?? '');
            if ($starts && $ends && $ends <= $starts) pd_isb_redirect('De einddatum moet na de begindatum liggen.', false, $back);
            $priority = !empty($_POST['priority']) ? 1 : 0;
            $active = !empty($_POST['active']) ? 1 : 0;

            $image = $old['image_path'] ?? null;
            if (!empty($_POST['remove_image'])) $image = null;
            if (!empty($_FILES['image']['name'])) {
                $image = pd_infoscreen_store_upload($_FILES['image'], $appRoot);
            }
            if ($id) {
                $db->prepare("UPDATE infoscreen_messages SET title = ?, body = ?, image_path = ?, image_layout = ?, image_fit = ?, image_size = ?,
                              locations = ?, starts_at = ?, ends_at = ?, priority = ?, color = ?, active = ? WHERE id = ?")
                   ->execute([$title, $body, $image, $imgLayout, $imgFit, $imgSize, $locs, $starts, $ends, $priority, $color, $active, $id]);
                if (($old['image_path'] ?? null) && $old['image_path'] !== $image) pd_infoscreen_delete_image($db, $old['image_path'], $appRoot);
                pd_isb_redirect('Bericht opgeslagen.');
            }
            $db->prepare("INSERT INTO infoscreen_messages (title, body, image_path, image_layout, image_fit, image_size, locations, starts_at, ends_at, priority, color, active, created_by)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
               ->execute([$title, $body, $image, $imgLayout, $imgFit, $imgSize, $locs, $starts, $ends, $priority, $color, $active, $userId]);
            pd_isb_redirect('Bericht toegevoegd.');
        }
        if ($action === 'toggle' || $action === 'delete') {
            $m = pd_isb_editable($db, $scope, $id);
            if (!$m) pd_isb_redirect('Dit bericht mag u niet aanpassen.', false);
            if ($action === 'toggle') {
                $db->prepare("UPDATE infoscreen_messages SET active = 1 - active WHERE id = ?")->execute([$id]);
                pd_isb_redirect((int)$m['active'] ? 'Bericht uitgezet.' : 'Bericht aangezet.');
            }
            $db->prepare("DELETE FROM infoscreen_messages WHERE id = ?")->execute([$id]);
            pd_infoscreen_delete_image($db, $m['image_path'] ?: null, $appRoot);
            pd_isb_redirect('Bericht verwijderd.');
        }
    } catch (\Throwable $e) {
        pd_isb_redirect($e instanceof RuntimeException ? $e->getMessage() : 'Opslaan is mislukt.', false);
    }
    pd_isb_redirect('Onbekende actie.', false);
}

// ─── Page data ───────────────────────────────────────────────────────────────
$flash = $_SESSION['pd_flash'] ?? null;
unset($_SESSION['pd_flash']);
$csrf = pd_infoscreen_csrf_token();

$rows = $db->query("SELECT m.*, COALESCE(NULLIF(u.display_name, ''), u.username) AS created_by_name
                    FROM infoscreen_messages m LEFT JOIN users u ON u.id = m.created_by
                    ORDER BY m.active DESC, m.priority DESC, COALESCE(m.starts_at, m.created_at) DESC, m.id DESC")->fetchAll(PDO::FETCH_ASSOC);
$messages = [];
foreach ($rows as $m) {
    $locs = pd_infoscreen_locs($m['locations']);
    // A redacteur sees what can appear on the own screens: own locations and "Alle locaties"
    if (!$scope['all'] && !pd_infoscreen_locs_match($locs, $scope['locations'])) continue;
    if (!$scope['all'] && !$scope['locations']) continue;
    $m['_locs'] = $locs;
    $m['_editable'] = pd_infoscreen_scope_allows($scope, $locs);
    $messages[] = $m;
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId ? pd_isb_editable($db, $scope, $editId) : null;
$showForm = $tab === 'berichten' && ($edit || isset($_GET['new']));
if ($showForm && !$scope['all'] && !$pickable) $showForm = false;

// Huisstijl (2.9.0)
$brandRows = pd_branding_rows($db);
$orgBrand = pd_branding_resolve($brandRows, [], 'donker', '.');
$brandLocs = $scope['all'] ? array_merge([''], $pickable) : $pickable;
$brandLoc = (string)($_GET['loc'] ?? ($brandLocs[0] ?? ''));
if (!in_array($brandLoc, $brandLocs, true)) $brandLoc = $brandLocs[0] ?? '';
// Message colours shown as they look on the first own location
$msgBrand = pd_branding_resolve($brandRows, $scope['all'] ? [] : $pickable, 'donker', '.');

$stmt = $db->prepare("SELECT COALESCE(NULLIF(display_name, ''), username) FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userName = (string)$stmt->fetchColumn();

function pd_isb_status(array $m): array
{
    $now = date('Y-m-d H:i:s');
    if (!(int)$m['active']) return ['Uit', 'off'];
    if ($m['starts_at'] && $m['starts_at'] > $now) return ['Gepland', 'planned'];
    if ($m['ends_at'] && $m['ends_at'] < $now) return ['Verlopen', 'expired'];
    return ['Op het scherm', 'live'];
}

function pd_isb_period(array $m): string
{
    $f = fn($d) => date('j-n-Y H:i', strtotime($d));
    if (!$m['starts_at'] && !$m['ends_at']) return 'Altijd';
    if ($m['starts_at'] && $m['ends_at']) return $f($m['starts_at']) . ' t/m ' . $f($m['ends_at']);
    return $m['starts_at'] ? 'Vanaf ' . $f($m['starts_at']) : 'Tot en met ' . $f($m['ends_at']);
}

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$dtValue = fn($v) => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';
$locBadges = function (array $locs) use ($h): string {
    if (!$locs) return '<span class="loc-badge all">🌐 Alle locaties</span>';
    return implode(' ', array_map(fn($l) => '<span class="loc-badge">📍 ' . $h($l) . '</span>', $locs));
};
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Infoscherm-berichten — PeopleDisplay</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; color: #1f2937; }
    .back-link { display: inline-block; margin-bottom: 16px; color: #fff; text-decoration: none; font-weight: 600; }
    .back-link:hover { text-decoration: underline; }
    .container { max-width: 1000px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px 30px 34px; box-shadow: 0 10px 40px rgba(0,0,0,.2); }
    .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
    h1 { font-size: 26px; }
    .subtitle { color: #6b7280; font-size: 14px; margin-top: 4px; }
    .scope { border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; font-size: 14.5px; line-height: 1.6; }
    .scope.own { background: #ecfdf5; border: 1.5px solid #6ee7b7; color: #064e3b; }
    .scope.all { background: #eef2ff; border: 1.5px solid #a5b4fc; color: #312e81; }
    .scope.none { background: #fffbeb; border: 1.5px solid #fcd34d; color: #5b4204; }
    .scope .label { font-weight: 700; display: block; margin-bottom: 6px; }
    .loc-badge { display: inline-block; padding: 3px 10px; border-radius: 99px; background: #fff; border: 1px solid #d1d5db; font-size: 13px; font-weight: 600; color: #374151; margin: 2px 2px 2px 0; white-space: nowrap; }
    .loc-badge.all { background: #312e81; border-color: #312e81; color: #fff; }
    .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-weight: 500; font-size: 14px; }
    .alert.ok { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert.err { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border: none; border-radius: 7px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; white-space: nowrap; }
    .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; }
    .btn-light { background: #f3f4f6; color: #374151; }
    .btn-light:hover { background: #e5e7eb; }
    .btn-sm { padding: 6px 10px; font-size: 12.5px; }
    .btn-danger { background: #fee2e2; color: #b91c1c; }
    .toolbar { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 14px; flex-wrap: wrap; }
    .cards { display: flex; flex-direction: column; gap: 10px; }
    .card { display: grid; grid-template-columns: 84px 1fr auto; gap: 14px; align-items: center; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 14px; }
    .card.ro { background: #f9fafb; }
    .thumb { width: 84px; height: 52px; border-radius: 6px; object-fit: cover; display: block; background: #e5e7eb; }
    .card .t { font-weight: 700; font-size: 15px; }
    .card .meta { margin-top: 5px; display: flex; flex-wrap: wrap; gap: 4px 10px; align-items: center; font-size: 12.5px; color: #6b7280; }
    .pill { display: inline-block; padding: 3px 9px; border-radius: 99px; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .pill.live { background: #d1fae5; color: #065f46; } .pill.planned { background: #dbeafe; color: #1e40af; }
    .pill.expired { background: #f3f4f6; color: #6b7280; } .pill.off { background: #f3f4f6; color: #9ca3af; }
    .pill.prio { background: #fee2e2; color: #b91c1c; } .pill.lock { background: #f3f4f6; color: #4b5563; }
    .actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
    .actions form { display: inline; }
    .empty { text-align: center; padding: 36px 10px; color: #6b7280; }
    .empty .big { font-size: 40px; margin-bottom: 8px; }
    .panel { border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px 22px; margin-bottom: 22px; background: #fcfcfd; }
    .panel h2 { font-size: 18px; margin-bottom: 14px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 20px; }
    .grid .full { grid-column: 1 / -1; }
    label.f { display: block; font-weight: 600; font-size: 13.5px; margin-bottom: 6px; color: #374151; }
    input[type=text], input[type=datetime-local], textarea { width: 100%; padding: 9px 11px; border: 1.5px solid #d1d5db; border-radius: 7px; font-size: 14px; font-family: inherit; background: #fff; }
    input:focus, textarea:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,.15); }
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
    .current-img { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
    .current-img img { height: 70px; border-radius: 6px; }
    .loc-picker { border: 1.5px solid #d1d5db; border-radius: 7px; padding: 10px 12px; background: #fff; }
    .loc-picker .loc-all { padding-bottom: 8px; margin-bottom: 8px; border-bottom: 1px solid #eef0f3; }
    .loc-picker .loc-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 6px 14px; }
    .loc-picker label:has(input:disabled) { color: #9ca3af; }
    .tabs { display: flex; gap: 4px; border-bottom: 2px solid #e5e7eb; margin-bottom: 18px; }
    .tabs a { padding: 10px 16px; text-decoration: none; color: #4b5563; font-weight: 600; border-bottom: 3px solid transparent; margin-bottom: -2px; }
    .tabs a.active { color: #4338ca; border-color: #667eea; }
    .loc-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; }
    .loc-tabs a { padding: 6px 12px; border-radius: 99px; border: 1.5px solid #d1d5db; text-decoration: none; color: #374151; font-size: 13.5px; font-weight: 600; background: #fff; }
    .loc-tabs a.active { background: #312e81; border-color: #312e81; color: #fff; }
    select, input[type=file] { width: 100%; padding: 8px 10px; border: 1.5px solid #d1d5db; border-radius: 7px; font-size: 14px; font-family: inherit; background: #fff; }
    @media (max-width: 700px) { .grid { grid-template-columns: 1fr; } .container { padding: 20px 16px; } .card { grid-template-columns: 1fr; } .thumb { width: 100%; height: 120px; } .actions { justify-content: flex-start; } }
</style>
<?= pd_infoscreen_message_style_css() ?>
<?= pd_branding_form_css() ?>
</head>
<body>
<a href="frontpage.php" class="back-link">← Terug</a>
<div class="container">
    <div class="head">
        <div>
            <h1>📝 Infoscherm-berichten</h1>
            <div class="subtitle">Berichten op het infoscherm plaatsen en aanpassen<?= $userName !== '' ? ' — ' . $h($userName) : '' ?></div>
        </div>
        <div class="actions">
            <a class="btn btn-light" href="infoscherm.php?preview=1&amp;mode=infoscreen<?= !$scope['all'] && $pickable ? '&amp;loc=' . rawurlencode($pickable[0]) : '' ?>" target="_blank">👁️ Voorbeeld</a>
            <?php if ($canManage): ?><a class="btn btn-light" href="admin/infoscreen_manage.php">⚙️ Volledig beheer</a><?php endif; ?>
        </div>
    </div>

    <?php if ($scope['all']): ?>
        <div class="scope all"><span class="label">🌐 U werkt voor alle locaties</span>
            Als beheerder kiest u per bericht één of meer locaties, of “Alle locaties” voor belangrijke berichten die overal moeten staan.</div>
    <?php elseif ($scope['locations']): ?>
        <div class="scope own"><span class="label"><?= $scope['messages'] ? 'U plaatst berichten' : 'U past de huisstijl aan' ?> voor:</span>
            <?= $locBadges($scope['locations']) ?>
            <div class="help" style="color:inherit;margin-top:8px">Berichten van de beheerder voor alle locaties ziet u hier ook, maar die kunt u niet aanpassen.</div></div>
    <?php else: ?>
        <div class="scope none"><span class="label">⚠️ Er zijn nog geen locaties voor u ingesteld</span>
            Vraag de beheerder om bij uw gebruiker de locaties aan te vinken waarvoor u berichten mag plaatsen.</div>
    <?php endif; ?>

    <?php if ($scope['messages'] && $scope['branding']): ?>
    <div class="tabs">
        <a href="infoscherm_berichten.php" class="<?= $tab === 'berichten' ? 'active' : '' ?>">📝 Berichten</a>
        <a href="?tab=huisstijl" class="<?= $tab === 'huisstijl' ? 'active' : '' ?>">🖌️ Huisstijl</a>
    </div>
    <?php endif; ?>

    <?php if ($flash): ?>
        <div class="alert <?= str_starts_with($flash, '❌') ? 'err' : 'ok' ?>"><?= $h($flash) ?></div>
    <?php endif; ?>

    <?php if ($tab === 'huisstijl'): ?>
    <div class="panel">
        <h2>🖌️ Huisstijl van het infoscherm</h2>
        <p class="help" style="margin:-6px 0 14px">Logo, titel, kleuren en lettertype voor de schermen van een locatie. Lege velden volgen de huisstijl van de organisatie.</p>
        <?php if (count($brandLocs) > 1): ?>
        <div class="loc-tabs">
            <?php foreach ($brandLocs as $l): ?>
                <a href="?tab=huisstijl&amp;loc=<?= $h(rawurlencode($l)) ?>" class="<?= $l === $brandLoc ? 'active' : '' ?>"><?= $l === '' ? '🌐 Hele organisatie' : '📍 ' . $h($l) ?><?= isset($brandRows[$l]) ? ' ✓' : '' ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!$brandLocs): ?>
            <div class="empty">Er zijn nog geen locaties voor u ingesteld.</div>
        <?php else:
            $hidden = '<input type="hidden" name="csrf" value="' . $h($csrf) . '"><input type="hidden" name="action" value="save_brand"><input type="hidden" name="loc" value="' . $h($brandLoc) . '">';
            $reset = $brandLoc !== '' && isset($brandRows[$brandLoc])
                ? '<button class="btn btn-light" type="submit" name="action" value="reset_brand" formnovalidate onclick="return confirm(\'Eigen huisstijl van deze locatie verwijderen?\')">↺ Volg de organisatie</button>' : '';
            echo pd_branding_form($brandRows[$brandLoc] ?? pd_branding_blank($brandLoc), $hidden, '', $orgBrand,
                'infoscherm.php?preview=1&mode=infoscreen' . ($brandLoc !== '' ? '&loc=' . rawurlencode($brandLoc) : ''), $reset);
        endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($showForm): $m = $edit ?? ['id' => 0, 'title' => '', 'body' => '', 'image_path' => null, 'image_layout' => 'left', 'image_fit' => 'cover', 'image_size' => 50, 'locations' => null, 'starts_at' => null, 'ends_at' => null, 'priority' => 0, 'color' => 'huisstijl', 'active' => 1];
        $selected = pd_infoscreen_locs($m['locations']);
        if (!$m['id'] && !$scope['all'] && count($pickable) === 1) $selected = $pickable; ?>
    <div class="panel">
        <h2><?= $m['id'] ? '✏️ Bericht bewerken' : '➕ Nieuw bericht' ?></h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <div class="grid">
                <div class="full">
                    <label class="f" for="title">Titel *</label>
                    <input type="text" id="title" name="title" maxlength="200" required value="<?= $h($m['title']) ?>" placeholder="Bijv. Lift buiten gebruik">
                </div>
                <div class="full">
                    <label class="f" for="body">Tekst</label>
                    <textarea id="body" name="body" maxlength="2000" placeholder="Korte tekst. Houd het kort: mensen lopen voorbij."><?= $h($m['body']) ?></textarea>
                </div>
                <div class="full">
                    <label class="f">Op welke locaties? *</label>
                    <?= pd_infoscreen_location_picker($pickable, $selected, $scope['all'],
                        $scope['all'] ? 'Kies één of meer locaties, of “Alle locaties”.' : 'Het bericht verschijnt alleen op de schermen van de aangevinkte locaties.') ?>
                </div>
                <div class="full">
                    <label class="f" for="image">Afbeelding</label>
                    <?php if ($m['image_path']): ?>
                        <div class="current-img"><img src="<?= $h($m['image_path']) ?>" alt=""><label class="check"><input type="checkbox" name="remove_image" value="1"> Afbeelding verwijderen</label></div>
                    <?php endif; ?>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                    <div class="help">JPG, PNG, GIF of WEBP, maximaal 5 MB. Kies hieronder waar de afbeelding staat en of hij helemaal zichtbaar moet zijn.</div>
                </div>
                <?= pd_infoscreen_message_style_fields($m, $msgBrand) ?>
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
                <div class="full" style="display:flex;gap:24px;flex-wrap:wrap">
                    <label class="check"><input type="checkbox" name="priority" value="1" <?= (int)$m['priority'] ? 'checked' : '' ?>> ⚠️ Belangrijk — vaker in beeld en ook in de nieuwsbalk</label>
                    <label class="check"><input type="checkbox" name="active" value="1" <?= (int)$m['active'] ? 'checked' : '' ?>> Actief</label>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">💾 Opslaan</button>
                <a class="btn btn-light" href="infoscherm_berichten.php">Annuleren</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'berichten'): ?>
    <div class="toolbar">
        <div class="help" style="margin:0"><?= count($messages) ?> bericht(en)</div>
        <?php if (!$showForm && ($scope['all'] || $pickable)): ?><a class="btn btn-primary" href="?new=1">➕ Nieuw bericht</a><?php endif; ?>
    </div>

    <?php if (!$messages): ?>
        <div class="empty"><div class="big">📝</div>Nog geen berichten voor uw locaties.</div>
    <?php else: ?>
    <div class="cards">
    <?php foreach ($messages as $m): [$statusText, $statusCls] = pd_isb_status($m); ?>
        <div class="card<?= $m['_editable'] ? '' : ' ro' ?>">
            <div><?php if ($m['image_path']): ?><img class="thumb" src="<?= $h($m['image_path']) ?>" alt=""><?php else: ?><span class="thumb" style="background:<?= $h(PD_IS_MESSAGE_COLORS[$m['color']][1] ?? ($m['color'] === 'accent' ? $msgBrand['accent']['bg'] : $msgBrand['primary']['bg'])) ?>"></span><?php endif; ?></div>
            <div>
                <div class="t"><?= $h($m['title']) ?> <?php if ((int)$m['priority']): ?><span class="pill prio">Belangrijk</span><?php endif; ?></div>
                <div class="meta">
                    <?= $locBadges($m['_locs']) ?>
                    <span class="pill <?= $statusCls ?>"><?= $statusText ?></span>
                    <span><?= $h(pd_isb_period($m)) ?></span>
                    <?php $by = $m['source'] !== 'handmatig' ? ($m['author'] ?: ($m['source'] === 'teams' ? 'Teams' : 'API')) : $m['created_by_name']; ?>
                    <?php if ($by): ?><span>· door <?= $h($by) ?></span><?php endif; ?>
                </div>
            </div>
            <div class="actions">
                <?php if ($m['_editable']): ?>
                    <a class="btn btn-light btn-sm" href="?edit=<?= (int)$m['id'] ?>">Bewerken</a>
                    <form method="post"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-light btn-sm"><?= (int)$m['active'] ? 'Uitzetten' : 'Aanzetten' ?></button></form>
                    <form method="post" onsubmit="return confirm('Bericht verwijderen?')"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-danger btn-sm">Verwijderen</button></form>
                <?php else: ?>
                    <span class="pill lock" title="<?= $m['_locs'] ? 'Dit bericht staat ook op locaties die niet bij u horen.' : 'Berichten voor alle locaties past de beheerder aan.' ?>">🔒 Alleen lezen</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; /* tab berichten */ ?>
</div>
<?= pd_infoscreen_location_picker_js() ?>
<?= pd_infoscreen_text_counter_js() ?>
<?= pd_infoscreen_message_style_js() ?>
<?= pd_branding_form_js() ?>
</body>
</html>
