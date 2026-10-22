<?php
/**
 * BESTANDSNAAM: download.php
 * UPLOAD NAAR:  peopledisplay.nl/download.php
 *
 * Download pagina voor PeopleDisplay Starter (gratis)
 * met optionele Ko-fi donatie.
 *
 * De download komt van de nieuwste GitHub-release. Die wordt automatisch gemaakt
 * zodra er een versietag (vX.Y.Z) naar GitHub wordt gepusht — zie
 * .github/workflows/release.yml in de repository. Hier hoeft dus niets meer
 * met de hand geüpload te worden.
 */

declare(strict_types=1);

const PD_GITHUB_REPO  = 'peopledisplaynl-coder/peopledisplay';
const PD_RELEASE_ZIP  = 'peopledisplay-starter.zip';
const PD_CACHE_SECONDS = 3600; // GitHub API maximaal 1x per uur bevragen
const PD_LOCAL_FALLBACK = '/downloads/peopledisplay_latest.zip'; // oude handmatige download, alleen als noodroute

function pd_http_get(string $url): ?string
{
    $headers = ['User-Agent: peopledisplay.nl-download', 'Accept: application/vnd.github+json'];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code >= 200 && $code < 300) ? (string)$body : null;
    }
    $ctx = stream_context_create(['http' => ['timeout' => 5, 'header' => implode("\r\n", $headers)]]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : $body;
}

/**
 * Nieuwste release: ['version', 'url', 'size'] of null.
 * Gecached in de tijdelijke map; bij een storing bij GitHub wordt de laatst bekende gebruikt.
 */
function pd_latest_release(): ?array
{
    $cacheFile = sys_get_temp_dir() . '/pd_github_release.json';
    $cached = is_file($cacheFile) ? json_decode((string)@file_get_contents($cacheFile), true) : null;
    if (is_array($cached) && (time() - (int)($cached['fetched'] ?? 0)) < PD_CACHE_SECONDS) {
        return $cached['release'];
    }

    $json = pd_http_get('https://api.github.com/repos/' . PD_GITHUB_REPO . '/releases/latest');
    $data = $json ? json_decode($json, true) : null;
    if (is_array($data) && !empty($data['tag_name'])) {
        $release = ['version' => ltrim((string)$data['tag_name'], 'v'), 'url' => null, 'size' => 0];
        foreach ($data['assets'] ?? [] as $asset) {
            if (($asset['name'] ?? '') === PD_RELEASE_ZIP) {
                $release['url']  = (string)$asset['browser_download_url'];
                $release['size'] = (int)$asset['size'];
            }
        }
        if ($release['url'] !== null) {
            @file_put_contents($cacheFile, json_encode(['fetched' => time(), 'release' => $release]), LOCK_EX);
            return $release;
        }
    }
    return is_array($cached) ? $cached['release'] : null; // GitHub niet bereikbaar: laatst bekende release
}

$release = pd_latest_release();

// Download gestart: door naar het ZIP-bestand van de nieuwste release
if (isset($_GET['start'])) {
    if ($release !== null) {
        header('Location: ' . $release['url'], true, 302);
    } elseif (is_file($_SERVER['DOCUMENT_ROOT'] . PD_LOCAL_FALLBACK)) {
        header('Location: ' . PD_LOCAL_FALLBACK, true, 302);
    } else {
        header('Location: https://github.com/' . PD_GITHUB_REPO . '/releases/latest/download/' . PD_RELEASE_ZIP, true, 302);
    }
    exit;
}

// Versie voor op de pagina: van de release, anders zoals vroeger van admin.peopledisplay.nl
$currentVersion = $release['version'] ?? null;
if ($currentVersion === null) {
    $versionData = json_decode((string)pd_http_get('https://admin.peopledisplay.nl/api/changelog.php'), true) ?? [];
    $currentVersion = $versionData['version'] ?? '2.1.9';
}
$sizeLabel = !empty($release['size']) ? ' (' . number_format($release['size'] / 1048576, 0, ',', '.') . ' MB)' : '';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PeopleDisplay Downloaden — Gratis Starter</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            max-width: 560px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        .logo { font-size: 56px; margin-bottom: 16px; }
        h1 { font-size: 28px; font-weight: 600; color: #2d3748; margin-bottom: 8px; }
        .version { display: inline-block; background: #f0f4ff; color: #667eea; padding: 4px 14px; border-radius: 20px; font-size: 14px; font-weight: 600; margin-bottom: 8px; }
        .free-badge { display: inline-block; background: #e8f5e9; color: #2e7d32; padding: 4px 14px; border-radius: 20px; font-size: 14px; font-weight: 600; margin-bottom: 24px; margin-left: 6px; }
        .info { color: #718096; font-size: 15px; line-height: 1.6; margin-bottom: 24px; }
        .limits { background: #f8f9ff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 20px; margin-bottom: 28px; text-align: left; }
        .limits h3 { font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.05em; }
        .limit-row { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #555; padding: 3px 0; }
        .limit-row::before { content: "✓"; color: #48bb78; font-weight: 700; }
        .btn-download {
            display: block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px 40px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: none;
            width: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 20px rgba(102,126,234,0.4);
            margin-bottom: 16px;
        }
        .btn-download:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(102,126,234,0.5); }
        .progress { display: none; margin-bottom: 16px; }
        .progress-bar { height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin-bottom: 8px; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); border-radius: 4px; animation: prog 2s ease-in-out forwards; }
        @keyframes prog { 0% { width: 0%; } 100% { width: 95%; } }
        .progress-text { color: #667eea; font-size: 14px; font-weight: 600; }
        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 24px 0; }
        .donation-box { background: #fff8f0; border: 1px solid #fed7a0; border-radius: 12px; padding: 20px; text-align: left; }
        .donation-box h3 { font-size: 15px; font-weight: 600; color: #c05621; margin-bottom: 8px; }
        .donation-box p { font-size: 13px; color: #744210; line-height: 1.6; margin-bottom: 14px; }
        .btn-kofi {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #FF5E5B;
            color: white;
            padding: 10px 22px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: transform 0.2s;
        }
        .btn-kofi:hover { transform: translateY(-1px); }
        .kofi-icon { font-size: 18px; }
        .upgrade-note { margin-top: 20px; font-size: 12px; color: #a0aec0; }
        .upgrade-note a { color: #667eea; text-decoration: none; }
        .upgrade-note a:hover { text-decoration: underline; }
        .requirements { margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: left; }
        .requirements h3 { font-size: 13px; color: #4a5568; font-weight: 600; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em; }
        .req-item { font-size: 13px; color: #718096; padding: 2px 0; }
        .req-item::before { content: "→ "; color: #a0aec0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">📦</div>
        <h1>PeopleDisplay</h1>
        <div>
            <span class="version">v<?= htmlspecialchars($currentVersion) ?></span>
            <span class="free-badge">✓ Gratis</span>
        </div>

        <p class="info">
            Download de gratis Starter versie en zet PeopleDisplay in minder dan 15 minuten op jouw eigen hosting.
        </p>

        <div class="limits">
            <h3>Inbegrepen in de Starter versie</h3>
            <div class="limit-row">Max 10 medewerkers</div>
            <div class="limit-row">1 locatie</div>
            <div class="limit-row">3 gebruikers (beheerders)</div>
            <div class="limit-row">Alle basisfuncties — check-in, check-out, overzicht</div>
            <div class="limit-row">BHV overzicht, badge generator</div>
            <div class="limit-row">PWA — installeerbaar als app</div>
        </div>

        <button class="btn-download" onclick="startDownload()">
            ⬇️ Download PeopleDisplay Starter<?= htmlspecialchars($sizeLabel) ?>
        </button>

        <div class="progress" id="progress">
            <div class="progress-bar"><div class="progress-fill"></div></div>
            <div class="progress-text" id="progress-text">⏳ Download wordt voorbereid...</div>
        </div>

        <hr class="divider">

        <div class="donation-box">
            <h3>☕ Vond je PeopleDisplay nuttig?</h3>
            <p>PeopleDisplay is gratis te gebruiken als Starter. Als je het waardeert, kun je de verdere ontwikkeling steunen met een kleine donatie via Ko-fi. Elk bedrag helpt!</p>
            <a href="https://ko-fi.com/peopledisplay" target="_blank" class="btn-kofi">
                <span class="kofi-icon">☕</span> Steun via Ko-fi
            </a>
        </div>

        <div class="upgrade-note">
            Meer dan 10 medewerkers of meerdere locaties nodig?
            <a href="https://peopledisplay.nl/prijzen">Bekijk de betaalde pakketten →</a>
        </div>

        <div class="requirements">
            <h3>Systeemvereisten</h3>
            <div class="req-item">PHP 8.0 of hoger</div>
            <div class="req-item">MySQL 5.7 / MariaDB 10.3 of hoger</div>
            <div class="req-item">Standaard webhosting (Strato, TransIP, Antagonist etc.)</div>
            <div class="req-item">Minimaal 50 MB schijfruimte</div>
        </div>
    </div>

    <script>
    function startDownload() {
        document.getElementById('progress').style.display = 'block';
        document.getElementById('progress-text').textContent = '⏳ Download wordt voorbereid...';
        setTimeout(function() {
            document.getElementById('progress-text').textContent = '✅ Download gestart! Sla het bestand op.';
            window.location.href = '?start=1';
        }, 1500);
    }
    </script>
</body>
</html>
