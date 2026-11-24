<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * cron_manage.php
 * Upload naar: /admin/cron_manage.php
 *
 * "Automatische taken" (2.6.0): the cron jobs of this installation, the commands for the
 * hosting panel and the web links (with this installation's own key) for cron-job.org.
 * Make a new key, or stop accepting the old public key before the transition period ends.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/cron_key.php';
require_once __DIR__ . '/../includes/infoscreen.php';
require_once __DIR__ . '/auth_helper.php';

requireAdmin();
requireAdminFeature('manage_system_config');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = pd_infoscreen_csrf_ok($_POST['csrf'] ?? null);
    $action = $_POST['action'] ?? '';
    if (!$ok) {
        $_SESSION['pd_flash'] = '❌ De pagina was verlopen. Probeer het opnieuw.';
    } elseif ($action === 'regenerate') {
        $_SESSION['pd_flash'] = pd_cron_regenerate()
            ? '✅ Nieuwe sleutel gemaakt. Pas nu de links in uw cronjobs (cron-job.org) aan: de vorige sleutel werkt niet meer.'
            : '❌ De nieuwe sleutel kon niet worden opgeslagen (map data/ niet schrijfbaar).';
    } elseif ($action === 'legacy_off') {
        $_SESSION['pd_flash'] = pd_cron_legacy_disable()
            ? '✅ De oude, openbare sleutel werkt vanaf nu niet meer.'
            : '❌ Dat lukte niet (map data/ niet schrijfbaar).';
    }
    header('Location: cron_manage.php');
    exit;
}

$flash = $_SESSION['pd_flash'] ?? null;
unset($_SESSION['pd_flash']);
$csrf = pd_infoscreen_csrf_token();
$state = pd_cron_state();
$key = $state['key'];
$legacyAllowed = pd_cron_legacy_allowed();
$legacyUsage = pd_cron_legacy_usage(30);

$appRoot = dirname(__DIR__);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$appUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'jouwdomein.nl') . rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');

$tasks = [
    ['cron/cron_reset_expired_sub_status.php', null, 'Elke 5 minuten', 'Zet extra statussen terug waarvan de einddatum voorbij is'],
    ['cron/daily_reset_cron.php', 'daily_reset', 'Elke dag 23:00', 'Zet alle medewerkers op UIT'],
    ['cron/cron_auto_checkout.php', null, 'Elke dag 23:55', 'Checkt bezoekers uit (behalve meerdaagse bezoeken)'],
    ['cron/cron_auto_delete.php', null, 'Elke dag 02:00', 'Verwijdert bezoekersgegevens na 7 dagen (AVG)'],
    ['cron/cleanup_tokens_cron.php', 'cleanup_tokens', 'Elke dag 02:00', 'Ruimt verlopen “blijf ingelogd”-tokens op'],
    ['cron/cron_infoscreen.php', 'infoscreen_refresh', 'Elke 15 minuten', 'Haalt nieuws, weer en agenda op voor het infoscherm'],
];
$legacyNames = [
    'cron_reset_expired_sub_status' => 'cron_reset_expired_sub_status.php', 'daily_reset_cron' => 'daily_reset_cron.php',
    'cron_auto_checkout' => 'cron_auto_checkout.php', 'cron_auto_delete' => 'cron_auto_delete.php',
    'cleanup_tokens_cron' => 'cleanup_tokens_cron.php',
];
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$webUrl = function (array $t) use ($appUrl, $key): string {
    $k = rawurlencode((string)$key);
    if ($t[0] === 'cron/cron_infoscreen.php') return $appUrl . '/cron_endpoint.php?action=infoscreen_refresh&key=' . $k;
    return $appUrl . '/' . $t[0] . '?key=' . $k;
};
// A link on screen with the key blurred until "Tonen" is clicked
$showUrl = function (string $url) use ($h, $key): string {
    $k = rawurlencode((string)$key);
    $pos = strrpos($url, $k);
    return $pos === false ? $h($url) : $h(substr($url, 0, $pos)) . '<span class="secret">' . $h($k) . '</span>';
};
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Automatische taken — PeopleDisplay</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; color: #1f2937; }
    .back-link { display: inline-block; margin-bottom: 16px; color: #fff; text-decoration: none; font-weight: 600; }
    .container { max-width: 1100px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px 30px 34px; box-shadow: 0 10px 40px rgba(0,0,0,.2); }
    h1 { font-size: 26px; }
    .subtitle { color: #6b7280; font-size: 14px; margin: 4px 0 20px; }
    h2 { font-size: 18px; margin-bottom: 12px; }
    .panel { border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px 22px; margin-bottom: 22px; background: #fcfcfd; }
    .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-weight: 500; font-size: 14px; }
    .alert.ok { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert.err { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .info { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 12px 16px; border-radius: 6px; font-size: 13.5px; color: #1e3a5f; line-height: 1.55; margin-bottom: 16px; }
    .info.warn { background: #fffbeb; border-left-color: #f59e0b; color: #5b4204; }
    .keybox { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .keybox code { font-size: 15px; background: #f3f4f6; padding: 8px 12px; border-radius: 7px; letter-spacing: .03em; }
    code { background: rgba(0,0,0,.06); padding: 1px 5px; border-radius: 4px; font-size: 12.5px; word-break: break-all; }
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border: none; border-radius: 7px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; white-space: nowrap; }
    .btn-light { background: #f3f4f6; color: #374151; } .btn-light:hover { background: #e5e7eb; }
    .btn-danger { background: #fee2e2; color: #b91c1c; } .btn-danger:hover { background: #fecaca; }
    .btn-sm { padding: 5px 9px; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; padding: 8px 10px; border-bottom: 2px solid #e5e7eb; }
    td { padding: 10px; border-bottom: 1px solid #f0f1f3; vertical-align: top; }
    .muted { color: #6b7280; font-size: 12.5px; }
    .url { display: flex; gap: 6px; align-items: flex-start; }
    .url code { flex: 1; }
    .masked .secret { filter: blur(5px); }
    @media (max-width: 760px) { .container { padding: 20px 16px; } .hide-sm { display: none; } }
</style>
</head>
<body>
<a href="dashboard.php" class="back-link">← Terug naar Dashboard</a>
<div class="container">
    <h1>⏱️ Automatische taken</h1>
    <div class="subtitle">Cronjobs die PeopleDisplay op vaste tijden laten werken, en de geheime sleutel voor cron-job.org</div>

    <?php if ($flash): ?><div class="alert <?= str_starts_with($flash, '❌') ? 'err' : 'ok' ?>"><?= $h($flash) ?></div><?php endif; ?>

    <?php if ($key === null): ?>
        <div class="info warn"><strong>Er kon geen eigen sleutel worden gemaakt.</strong> De map <code>data/</code> bestaat niet of is niet schrijfbaar.
            Maak de map via FTP aan (of geef schrijfrechten) en laad deze pagina opnieuw. Tot die tijd werkt de oude sleutel nog.</div>
    <?php else: ?>
        <?php if ($legacyAllowed && $legacyUsage): ?>
            <div class="info warn"><strong>Een cronjob gebruikt nog de oude, openbare sleutel.</strong>
                Die werkt nog tot <?= $h(date('j-n-Y', $state['legacy_until'])) ?>. Vervang in cron-job.org de links hieronder.
                Laatst gebruikt door:
                <?php foreach ($legacyUsage as $task => $ts): ?>
                    <br>· <code><?= $h($legacyNames[$task] ?? str_replace('endpoint:', 'cron_endpoint.php?action=', $task)) ?></code> — <?= $h(date('j-n-Y H:i', $ts)) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="panel">
            <h2>🔑 Sleutel van deze installatie</h2>
            <div class="keybox masked" id="keybox">
                <code class="secret" id="cronkey"><?= $h($key) ?></code>
                <button class="btn btn-light btn-sm" type="button" onclick="['keybox', 'tasks'].forEach(function (id) { var e = document.getElementById(id); if (e) e.classList.toggle('masked'); })">👁️ Tonen</button>
                <button class="btn btn-light btn-sm" type="button" onclick="pdCopy(document.getElementById('cronkey').textContent, this)">📋 Kopiëren</button>
            </div>
            <p class="muted" style="margin-top:10px">Deze sleutel is alleen van deze installatie en staat niet in de code of op GitHub. Deel hem niet.
                Iemand met de sleutel kan de taken hieronder starten (bijvoorbeeld iedereen op UIT zetten).</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">
                <form method="post" onsubmit="return confirm('Nieuwe sleutel maken? De huidige links in cron-job.org werken daarna niet meer.')">
                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="action" value="regenerate">
                    <button class="btn btn-light" type="submit">🔄 Nieuwe sleutel maken</button>
                </form>
                <?php if ($legacyAllowed): ?>
                <form method="post" onsubmit="return confirm('De oude sleutel nu uitzetten? Cronjobs die hem nog gebruiken krijgen dan een foutmelding (403).')">
                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="action" value="legacy_off">
                    <button class="btn btn-danger" type="submit">🚫 Oude sleutel nu uitzetten</button>
                </form>
                <?php endif; ?>
            </div>
            <p class="muted" style="margin-top:10px">
                <?php if ($legacyAllowed): ?>
                    Overgangsperiode: de oude sleutel uit eerdere versies werkt nog tot <?= $h(date('j-n-Y', $state['legacy_until'])) ?>, daarna niet meer.
                <?php else: ?>
                    De oude sleutel uit eerdere versies werkt niet meer.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="panel masked" id="tasks">
        <h2>📋 Taken</h2>
        <div class="info"><strong>Kies één manier per taak.</strong> Heeft uw hosting cronjobs (bijv. Plesk “Geplande taken”)? Gebruik dan de opdracht.
            Anders maakt u bij <a href="https://cron-job.org" target="_blank" rel="noopener">cron-job.org</a> per taak een cronjob met de weblink.</div>
        <table>
            <thead><tr><th>Taak</th><th class="hide-sm">Wanneer</th><th>Opdracht (hostingpaneel) of weblink (cron-job.org)</th></tr></thead>
            <tbody>
            <?php foreach ($tasks as $t): ?>
                <tr>
                    <td><strong><?= $h(basename($t[0])) ?></strong><div class="muted"><?= $h($t[3]) ?></div></td>
                    <td class="hide-sm"><?= $h($t[2]) ?></td>
                    <td>
                        <div class="url"><code>php <?= $h($appRoot . '/' . $t[0]) ?></code></div>
                        <?php if ($key !== null): ?>
                        <div class="url" style="margin-top:6px"><code><?= $showUrl($webUrl($t)) ?></code>
                            <button class="btn btn-light btn-sm" type="button" onclick="pdCopy(<?= $h(json_encode($webUrl($t))) ?>, this)">📋</button></div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($key !== null): ?>
        <p class="muted" style="margin-top:12px">Testen: <code><?= $showUrl($appUrl . '/cron_endpoint.php?action=test&key=' . rawurlencode($key)) ?></code> geeft <code>"success": true</code> als de sleutel klopt.</p>
        <?php endif; ?>
    </div>
</div>
<script>
function pdCopy(text, btn) {
    var done = function () { var t = btn.textContent; btn.textContent = '✅'; setTimeout(function () { btn.textContent = t; }, 1500); };
    if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done); return; }
    var ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); done(); } catch (e) {} document.body.removeChild(ta);
}
</script>
</body>
</html>
