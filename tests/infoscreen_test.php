<?php
// tests/infoscreen_test.php
// Unit tests for the infoscreen parsers and helpers (includes/infoscreen.php):
// RSS 2.0 / Atom / RDF feeds with images in every common place, Buienradar and
// Open-Meteo weather, URL cleaning and the private-address guard. No database,
// no network. Run: php tests/infoscreen_test.php  (also part of npm test)

declare(strict_types=1);

require __DIR__ . '/../includes/infoscreen.php';

$failures = 0;
$count = 0;
function check(bool $ok, string $what): void
{
    global $failures, $count;
    $count++;
    if (!$ok) {
        $failures++;
        fwrite(STDERR, "FAIL: $what\n");
    }
}
function eq($actual, $expected, string $what): void
{
    check($actual === $expected, $what . ' — got ' . var_export($actual, true) . ', expected ' . var_export($expected, true));
}
$fx = fn(string $f) => file_get_contents(__DIR__ . '/fixtures/infoscreen/' . $f);

// ── RSS 2.0 ──────────────────────────────────────────────────────────────
$items = pd_infoscreen_parse_feed($fx('rss2.xml'));
eq(count($items), 4, 'rss2: empty title skipped');
eq($items[0]['title'], 'Nieuwe speeltuin geopend & feestelijk ingewijd', 'rss2: entities decoded');
eq($items[0]['summary'], 'De kinderen openden vanochtend de nieuwe speeltuin.', 'rss2: html and script stripped');
eq($items[0]['image'], 'https://voorbeeld.test/img/speeltuin.jpg', 'rss2: enclosure image');
eq($items[0]['link'], 'https://voorbeeld.test/nieuws/speeltuin', 'rss2: link');
eq($items[0]['published'], date('Y-m-d H:i:s', strtotime('Mon, 23 Nov 2026 09:15:00 +0100')), 'rss2: pubDate');
eq($items[0]['key'], sha1('https://voorbeeld.test/?p=101'), 'rss2: key from guid');
eq($items[1]['image'], 'https://voorbeeld.test/img/groot.jpg', 'rss2: widest media:content');
eq($items[2]['image'], 'https://voorbeeld.test/wp-content/uploads/foto.png', 'rss2: image from content:encoded');
eq($items[2]['published'], date('Y-m-d H:i:s', strtotime('2026-11-22T14:00:00+01:00')), 'rss2: dc:date');
eq($items[3]['image'], null, 'rss2: audio enclosure is no image');
eq($items[3]['link'], null, 'rss2: javascript: link dropped');

// ── Atom ─────────────────────────────────────────────────────────────────
$items = pd_infoscreen_parse_feed($fx('atom.xml'));
eq(count($items), 2, 'atom: entries');
eq($items[0]['image'], 'https://atom.test/1.webp', 'atom: enclosure link');
eq($items[0]['link'], 'https://atom.test/1', 'atom: alternate link');
eq($items[0]['summary'], 'Samenvatting van het Atom-bericht.', 'atom: summary');
eq($items[1]['title'], 'Tweede bericht', 'atom: html title stripped');
eq($items[1]['image'], 'https://cdn.atom.test/2.jpg', 'atom: protocol-relative img in content');

// ── RSS 1.0 / RDF ────────────────────────────────────────────────────────
$items = pd_infoscreen_parse_feed($fx('rdf.xml'));
eq(count($items), 1, 'rdf: item');
eq($items[0]['title'], 'RDF-bericht', 'rdf: title');

// ── Not a feed ───────────────────────────────────────────────────────────
foreach (['<html><body>Hallo</body></html>' => 'html page', '{"a":1}' => 'json', '<rss><channel><item>' => 'broken xml'] as $input => $what) {
    try {
        pd_infoscreen_parse_feed($input);
        check(false, "not a feed ($what) must throw");
    } catch (RuntimeException $e) {
        check(true, $what);
    }
}
// XXE: external entities are never loaded
$xxe = '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY x SYSTEM "file:///etc/passwd">]><rss><channel><item><title>&x;</title></item></channel></rss>';
try {
    $items = pd_infoscreen_parse_feed($xxe);
    check(!str_contains(json_encode($items), 'root:'), 'xxe: no file contents');
} catch (RuntimeException $e) {
    check(true, 'xxe rejected');
}

// ── Buienradar ───────────────────────────────────────────────────────────
$w = pd_infoscreen_parse_buienradar($fx('buienradar.json'), 51.5667, 4.9333); // Gilze
eq($w['station'], 'Gilze-Rijen', 'buienradar: nearest station with a temperature');
eq($w['current']['temp'], 7.4, 'buienradar: temperature');
eq($w['current']['cat'], 'partly', 'buienradar: "Licht bewolkt" → partly');
eq($w['current']['wind_bft'], 3, 'buienradar: wind');
eq($w['current']['humidity'], 81, 'buienradar: humidity');
eq($w['current']['is_day'], true, 'buienradar: day icon');
eq(count($w['days']), 5, 'buienradar: five days');
eq($w['days'][0]['date'], '2026-11-24', 'buienradar: day date');
eq($w['days'][0]['max'], 10, 'buienradar: max');
eq($w['days'][0]['min'], 5, 'buienradar: min');
eq($w['days'][0]['cat'], 'showers', 'buienradar: buien');
eq($w['days'][2]['cat'], 'sleet', 'buienradar: natte sneeuw');
eq($w['days'][3]['cat'], 'clear', 'buienradar: zonnig');
eq($w['days'][4]['cat'], 'thunder', 'buienradar: icon letter fallback (s = onweer)');
eq($w['report']['title'], 'Wisselvallig met af en toe regen', 'buienradar: report');
$w = pd_infoscreen_parse_buienradar($fx('buienradar.json'), 51.45, 4.33); // Woensdrecht has no temperature
eq($w['station'], 'Gilze-Rijen', 'buienradar: station without temperature skipped');
try {
    pd_infoscreen_parse_buienradar('{"foo":1}', 52, 5);
    check(false, 'buienradar: bad json must throw');
} catch (RuntimeException $e) {
    check(true, 'buienradar bad json');
}

// ── Open-Meteo ───────────────────────────────────────────────────────────
$w = pd_infoscreen_parse_openmeteo($fx('openmeteo.json'));
eq($w['current']['temp'], 7.8, 'open-meteo: temperature');
eq($w['current']['cat'], 'rain', 'open-meteo: code 61 → rain');
eq($w['current']['desc'], 'Lichte regen', 'open-meteo: Dutch text');
eq($w['current']['wind_bft'], 3, 'open-meteo: 18.4 km/h → 3 Bft');
eq($w['current']['wind_dir'], 'ZW', 'open-meteo: 225° → ZW');
eq(count($w['days']), 5, 'open-meteo: days');
eq($w['days'][3]['cat'], 'snow', 'open-meteo: code 71 → snow');
eq($w['days'][4]['min'], -1, 'open-meteo: negative min');
eq($w['days'][1]['rain_chance'], 70, 'open-meteo: rain chance');
try {
    pd_infoscreen_parse_openmeteo('{"error":true,"reason":"Latitude must be in range"}');
    check(false, 'open-meteo: error must throw');
} catch (RuntimeException $e) {
    check(str_contains($e->getMessage(), 'Latitude'), 'open-meteo: error reason shown');
}
check(str_contains(pd_infoscreen_weather_url('open-meteo', 51.5667, 4.9333), 'latitude=51.5667'), 'open-meteo url');

// ── Helpers ──────────────────────────────────────────────────────────────
eq(pd_infoscreen_clean_url('https://a.test/x?y=1&amp;z=2'), 'https://a.test/x?y=1&z=2', 'clean url: entity decode');
eq(pd_infoscreen_clean_url('//cdn.test/a.jpg'), 'https://cdn.test/a.jpg', 'clean url: protocol-relative');
eq(pd_infoscreen_clean_url('ftp://a.test/x'), null, 'clean url: ftp refused');
eq(pd_infoscreen_clean_url('data:image/png;base64,AAAA'), null, 'clean url: data refused');
eq(pd_infoscreen_public_host('127.0.0.1'), false, 'public host: loopback refused');
eq(pd_infoscreen_public_host('192.168.1.10'), false, 'public host: private refused');
eq(pd_infoscreen_public_host('169.254.169.254'), false, 'public host: link-local refused');
eq(pd_infoscreen_public_host('8.8.8.8'), true, 'public host: public ip allowed');
eq(pd_infoscreen_plain('<p>Een <b>lange</b> zin die te lang is om helemaal te tonen</p>', 20), 'Een lange zin die…', 'plain: shortened on a word');
eq(pd_infoscreen_beaufort(0.5), 0, 'beaufort 0');
eq(pd_infoscreen_beaufort(40.0), 6, 'beaufort 6');
eq(pd_infoscreen_compass(0.0), 'N', 'compass N');
eq(pd_infoscreen_compass(350.0), 'N', 'compass 350 → N');

// HTTP refuses private targets before connecting
$res = pd_infoscreen_http_get('http://127.0.0.1/feed');
eq($res['ok'], false, 'http get: loopback refused');

echo ($failures ? "FAILED: $failures of $count checks\n" : "OK: $count infoscreen checks passed\n");
exit($failures ? 1 : 0);
