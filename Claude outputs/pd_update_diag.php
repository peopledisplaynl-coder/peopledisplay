<?php
/**
 * TIJDELIJK diagnose-script voor het update-meldingssysteem.
 * Upload naast dashboard.php (in /admin/) en open in de browser terwijl je bent
 * ingelogd, of gewoon direct via de URL. Verwijder daarna weer van de server.
 *
 * Laat zien:
 *  - welke PD_CURRENT_VERSION dit install rapporteert
 *  - of de live version.json vanaf admin.peopledisplay.nl succesvol op te halen is
 *    vanaf DEZE server (netwerk/firewall/SSL-problemen worden hier zichtbaar)
 *  - of er een lokale cache staat, en hoe oud die is
 *  - wat checkForUpdates() daadwerkelijk teruggeeft, mét en zonder cache
 */
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../includes/version.php';
require_once __DIR__ . '/../includes/update_check.php';

echo "=== PeopleDisplay update-diagnose ===\n\n";

echo "1) Huidige versie op dit install:\n";
echo "   PD_CURRENT_VERSION = " . PD_CURRENT_VERSION . "\n\n";

echo "2) Cachebestand:\n";
$cacheFile = sys_get_temp_dir() . '/pd_update_cache.json';
echo "   sys_get_temp_dir() = " . sys_get_temp_dir() . "\n";
echo "   Cachepad          = $cacheFile\n";
if (file_exists($cacheFile)) {
    $age = time() - filemtime($cacheFile);
    echo "   Bestaat: JA — leeftijd " . round($age / 60) . " minuten (TTL is 720 minuten / 12 uur)\n";
    $cached = json_decode(file_get_contents($cacheFile), true);
    echo "   Gecachete versie in bestand: " . ($cached['version'] ?? '(onbekend)') . "\n";
} else {
    echo "   Bestaat: NEE\n";
}
echo "\n";

echo "3) Live ophalen van " . PD_VERSION_URL . " (cache genegeerd):\n";
$ctx = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true]]);
$t0 = microtime(true);
$raw = @file_get_contents(PD_VERSION_URL, false, $ctx);
$ms = round((microtime(true) - $t0) * 1000);
if ($raw === false) {
    echo "   MISLUKT — file_get_contents gaf false terug na {$ms}ms.\n";
    $err = error_get_last();
    echo "   Laatste PHP-fout: " . ($err['message'] ?? '(geen)') . "\n";
    echo "   -> Dit betekent meestal: uitgaande HTTPS-verbindingen zijn geblokkeerd op\n";
    echo "      deze hosting, of allow_url_fopen staat uit, of er is een SSL/CA-probleem.\n";
} else {
    echo "   GELUKT — " . strlen($raw) . " bytes ontvangen in {$ms}ms.\n";
    $data = json_decode($raw, true);
    if ($data) {
        echo "   Live versie volgens admin.peopledisplay.nl: " . ($data['version'] ?? '?') . "\n";
        echo "   delta_download_url aanwezig: " . (!empty($data['delta_download_url']) ? 'JA' : 'NEE') . "\n";
    } else {
        echo "   Kon de response niet als JSON parsen — ruwe eerste 300 tekens:\n";
        echo "   " . substr($raw, 0, 300) . "\n";
    }
}
echo "\n";

echo "4) allow_url_fopen ini-instelling: " . (ini_get('allow_url_fopen') ? 'ON' : 'OFF (dit zou de oorzaak zijn!)') . "\n\n";

echo "5) checkForUpdates() met de huidige (mogelijk gecachete) staat:\n";
$info = checkForUpdates();
echo "   " . json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

echo "6) Zelfde, maar met cache eerst verwijderd:\n";
if (file_exists($cacheFile)) { @unlink($cacheFile); }
$info2 = checkForUpdates();
echo "   " . json_encode($info2, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

echo "=== Einde diagnose — verwijder dit bestand weer van de server ===\n";
