<?php
// tests/infoscreen_test.php
// Unit tests for the infoscreen parsers and helpers (includes/infoscreen.php):
// RSS 2.0 / Atom / RDF feeds with images in every common place, Buienradar and
// Open-Meteo weather, URL cleaning and the private-address guard. No database,
// no network. Run: php tests/infoscreen_test.php  (also part of npm test)

declare(strict_types=1);

require __DIR__ . '/../includes/infoscreen.php';
require __DIR__ . '/../includes/birthday.php';

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

// ── iCal agenda ─────────────────────────────────────────────────────────
require __DIR__ . '/../includes/infoscreen_ical.php';
$tz = new DateTimeZone('Europe/Amsterdam');
$events = pd_ical_parse($fx('outlook.ics'));
$occ = pd_ical_occurrences($events, new DateTimeImmutable('2026-11-23 00:00', $tz), new DateTimeImmutable('2026-12-07 00:00', $tz));
$list = array_map(fn($o) => $o['start'] . ' ' . $o['title'], $occ);
eq($list, [
    '2026-11-23 15:00:00 Teamoverleg',
    '2026-11-23 19:00:00 Ouderavond groep 1 tot en met 8 met een hele lange titel die over de regelgrens heen loopt',
    '2026-11-24 19:00:00 Ouderavond groep 1 tot en met 8 met een hele lange titel die over de regelgrens heen loopt',
    '2026-11-25 19:00:00 Ouderavond groep 1 tot en met 8 met een hele lange titel die over de regelgrens heen loopt',
    '2026-11-27 00:00:00 Studiedag, alle groepen vrij',
    '2026-11-30 17:00:00 Teamoverleg (verplaatst)',
    '2026-12-05 00:00:00 Sinterklaas',
], 'ical: weekly (summer→winter time, EXDATE, moved and cancelled occurrence), daily UNTIL, all-day, yearly, private left out');
eq($occ[0]['place'], 'Personeelskamer', 'ical: location');
eq($occ[0]['end'], '2026-11-23 16:00:00', 'ical: end time');
eq($occ[1]['end'], '2026-11-23 21:00:00', 'ical: DURATION');
eq($occ[4]['all_day'], true, 'ical: all-day flag');
eq($occ[4]['end'], '2026-11-28 00:00:00', 'ical: all-day end');
$mr = array_values(array_filter(pd_ical_occurrences($events, new DateTimeImmutable('2026-10-01', $tz), new DateTimeImmutable('2027-12-31', $tz)), fn($o) => $o['title'] === 'MR-vergadering'));
eq(array_column($mr, 'start'), ['2026-10-13 20:00:00', '2026-11-10 19:00:00', '2026-12-08 19:00:00', '2027-01-12 19:00:00', '2027-02-09 19:00:00', '2027-03-09 19:00:00'],
   'ical: monthly 2nd Tuesday, COUNT=6, UTC times shown in Amsterdam time');
// Ongoing event that started before the window
$now = pd_ical_occurrences($events, new DateTimeImmutable('2026-11-23 20:00', $tz), new DateTimeImmutable('2026-11-23 23:00', $tz));
eq(array_column($now, 'start'), ['2026-11-23 19:00:00'], 'ical: event still going on is included');
// Monthly on the 31st skips short months; weekly with INTERVAL; last Friday of the month
$ics = "BEGIN:VCALENDAR\nBEGIN:VEVENT\nUID:a\nSUMMARY:Op de 31e\nDTSTART:20270131T090000\nRRULE:FREQ=MONTHLY;COUNT=4\nEND:VEVENT\n"
     . "BEGIN:VEVENT\nUID:b\nSUMMARY:Om de week\nDTSTART:20270104T090000\nRRULE:FREQ=WEEKLY;INTERVAL=2;UNTIL=20270201T000000Z\nEND:VEVENT\n"
     . "BEGIN:VEVENT\nUID:c\nSUMMARY:Laatste vrijdag\nDTSTART:20270129T090000\nRRULE:FREQ=MONTHLY;BYDAY=-1FR;COUNT=3\nEND:VEVENT\nEND:VCALENDAR\n";
$all = pd_ical_occurrences(pd_ical_parse($ics), new DateTimeImmutable('2027-01-01', $tz), new DateTimeImmutable('2027-12-31', $tz));
$by = fn($t) => array_column(array_values(array_filter($all, fn($o) => $o['title'] === $t)), 'start');
eq(array_map(fn($d) => substr($d, 0, 10), $by('Op de 31e')), ['2027-01-31', '2027-03-31', '2027-05-31', '2027-07-31'], 'ical: monthly on the 31st');
eq(array_map(fn($d) => substr($d, 0, 10), $by('Om de week')), ['2027-01-04', '2027-01-18'], 'ical: every other week until');
eq(array_map(fn($d) => substr($d, 0, 10), $by('Laatste vrijdag')), ['2027-01-29', '2027-02-26', '2027-03-26'], 'ical: last Friday');
try {
    pd_ical_parse('<html>login</html>');
    check(false, 'ical: html must throw');
} catch (RuntimeException $e) {
    check(true, 'ical not a calendar');
}
// A large calendar (years of history, ~25 MB) is read line by line with little memory, and
// long-running series (daily since 2015) still reach the window
$big = tempnam(sys_get_temp_dir(), 'pdtest');
$fh = fopen($big, 'wb');
fwrite($fh, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nX-WR-TIMEZONE:Europe/Amsterdam\r\n");
for ($i = 0; $i < 60000; $i++) {
    $d = date('Ymd', strtotime('2015-01-01 +' . intdiv($i, 20) . ' days'));
    fwrite($fh, "BEGIN:VEVENT\r\nUID:old-$i\r\nSUMMARY:Oude afspraak $i met een wat langere omschrijving zodat het bestand groot wordt\r\n"
        . "DESCRIPTION:" . str_repeat('Lorem ipsum dolor sit amet ', 12) . "\r\nDTSTART:{$d}T090000\r\nDTEND:{$d}T100000\r\nEND:VEVENT\r\n");
}
fwrite($fh, "BEGIN:VEVENT\r\nUID:daily\r\nSUMMARY:Dagstart\r\nDTSTART:20150105T083000\r\nDTEND:20150105T084500\r\nRRULE:FREQ=DAILY\r\nEND:VEVENT\r\n");
fwrite($fh, "BEGIN:VEVENT\r\nUID:weekly\r\nSUMMARY:Weekoverleg\r\nDTSTART:20160104T150000\r\nDTEND:20160104T160000\r\nRRULE:FREQ=WEEKLY;BYDAY=MO\r\nEND:VEVENT\r\n");
fwrite($fh, "BEGIN:VEVENT\r\nUID:monthly\r\nSUMMARY:Maandoverleg\r\nDTSTART:20150113T190000\r\nRRULE:FREQ=MONTHLY;BYDAY=2TU\r\nEND:VEVENT\r\n");
fwrite($fh, "BEGIN:VEVENT\r\nUID:ended\r\nSUMMARY:Afgelopen reeks\r\nDTSTART:20150105T120000\r\nRRULE:FREQ=DAILY;UNTIL=20200101T000000Z\r\nEND:VEVENT\r\n");
fwrite($fh, "END:VCALENDAR\r\n");
fclose($fh);
$sizeMb = round(filesize($big) / 1048576, 1);
$memBefore = memory_get_usage();
$from = new DateTimeImmutable('2026-11-23 00:00', $tz);
$fh = fopen($big, 'rb');
$t0 = microtime(true);
$evs = pd_ical_parse_stream($fh, $from);
fclose($fh);
$occ = pd_ical_occurrences($evs, $from, $from->modify('+14 days'));
$secs = round(microtime(true) - $t0, 1);
$memUsed = round((memory_get_peak_usage() - $memBefore) / 1048576, 1);
unlink($big);
check($sizeMb > 20, "ical: big test calendar is $sizeMb MB");
eq(count($evs), 4, 'ical: old single appointments dropped while reading');
check($memUsed < 16, "ical: $sizeMb MB calendar parsed with $memUsed MB extra memory");
check($secs < 20, "ical: $sizeMb MB calendar parsed in $secs s");
$titles = array_count_values(array_column($occ, 'title'));
eq($titles['Dagstart'] ?? 0, 14, 'ical: daily since 2015 → 14 occurrences in two weeks');
eq($titles['Weekoverleg'] ?? 0, 2, 'ical: weekly since 2016 → 2 Mondays');
$occ30 = pd_ical_occurrences($evs, $from, $from->modify('+30 days'));
eq(count(array_filter($occ30, fn($o) => $o['title'] === 'Maandoverleg')), 1, 'ical: monthly 2nd Tuesday since 2015 → 8 December');
eq(isset($titles['Afgelopen reeks']), false, 'ical: series that ended is skipped');
eq(array_values(array_filter($occ30, fn($o) => $o['title'] === 'Maandoverleg'))[0]['start'] ?? null, '2026-12-08 19:00:00', 'ical: monthly after fast-forward');
echo "  (big calendar: $sizeMb MB, $secs s, +$memUsed MB)\n";

eq(pd_ical_url('webcal://outlook.office365.com/owa/calendar/x/calendar.ics'), 'https://outlook.office365.com/owa/calendar/x/calendar.ics', 'ical: webcal → https');

// ── Berichten-API and Teams ─────────────────────────────────────────────
require __DIR__ . '/../includes/infoscreen_api.php';
[$key, $hash, $hint] = pd_is_api_new_key();
check((bool)preg_match('/^pdis_[0-9a-f]{40}$/', $key), 'api: key format');
eq($hash, hash('sha256', $key), 'api: stored hash');
eq($hint, substr($key, -6), 'api: hint');
eq(pd_is_api_text('<p>Regel <b>één</b></p><p>Regel&nbsp;twee<br>drie</p><script>x()</script>', 200), "Regel één\nRegel twee\ndrie", 'api: html to text with lines');
$token = base64_encode(random_bytes(32));
$body = '{"type":"message","text":"<at>Infoscherm</at> Test"}';
$sig = base64_encode(hash_hmac('sha256', $body, base64_decode($token), true));
eq(pd_is_teams_verify($token, $body, 'HMAC ' . $sig), true, 'teams: valid signature');
eq(pd_is_teams_verify($token, $body . ' ', 'HMAC ' . $sig), false, 'teams: changed body refused');
eq(pd_is_teams_verify($token, $body, 'Bearer ' . $sig), false, 'teams: wrong scheme refused');
eq(pd_is_teams_verify(base64_encode(random_bytes(32)), $body, 'HMAC ' . $sig), false, 'teams: other token refused');
eq(pd_is_teams_lines('<div><at>Infoscherm</at>&nbsp;Studiedag vrijdag</div><div>Alle groepen vrij.</div><div>tot vrijdag</div>'),
   ['Studiedag vrijdag', 'Alle groepen vrij.', 'tot vrijdag'], 'teams: mention removed, lines kept');
$mon = strtotime('2026-11-23 10:00'); // a Monday
eq(pd_is_teams_until('vrijdag', $mon), '2026-11-27 23:59:00', 'teams: tot vrijdag');
eq(pd_is_teams_until('maandag', $mon), '2026-11-23 23:59:00', 'teams: tot maandag (today)');
eq(pd_is_teams_until('morgen 12:00', $mon), '2026-11-24 12:00:00', 'teams: tot morgen 12:00');
eq(pd_is_teams_until('5-12', $mon), '2026-12-05 23:59:00', 'teams: tot 5-12');
eq(pd_is_teams_until('3-1', $mon), '2027-01-03 23:59:00', 'teams: tot 3-1 → next year');
eq(pd_is_teams_until('31-2', $mon), null, 'teams: invalid date');
eq(pd_is_teams_until('ooit', $mon), null, 'teams: unknown');

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

// ── Locations (2.6.0) ────────────────────────────────────────────────────
eq(pd_infoscreen_locs(null), [], 'locs: NULL = all');
eq(pd_infoscreen_locs('["A","B"]'), ['A', 'B'], 'locs: json list');
eq(pd_infoscreen_locs('kapot'), [], 'locs: broken json = all');
eq(pd_infoscreen_locs_json([]), null, 'locs json: empty = NULL');
eq(pd_infoscreen_locs_json(['B', 'A', 'B']), '["B","A"]', 'locs json: unique, order kept');
eq(pd_infoscreen_locs_match([], ['A']), true, 'match: all-locations item on a screen');
eq(pd_infoscreen_locs_match(['A'], []), true, 'match: screen without locations shows everything');
eq(pd_infoscreen_locs_match(['A', 'B'], ['B']), true, 'match: overlap');
eq(pd_infoscreen_locs_match(['A'], ['C']), false, 'match: other location');
eq(pd_infoscreen_locs_label([]), 'Alle locaties', 'label: all');
eq(pd_infoscreen_locs_label(['A', 'B']), 'A, B', 'label: list');
$admin = ['all' => true, 'locations' => []];
$editor = ['all' => false, 'locations' => ['A', 'B']];
eq(pd_infoscreen_scope_allows($admin, []), true, 'scope: admin may edit all-locations messages');
eq(pd_infoscreen_scope_allows($editor, []), false, 'scope: editor may not edit all-locations messages');
eq(pd_infoscreen_scope_allows($editor, ['A']), true, 'scope: editor own location');
eq(pd_infoscreen_scope_allows($editor, ['A', 'C']), false, 'scope: editor not when another location is in it');
eq(pd_infoscreen_scope_allows(['all' => false, 'locations' => []], ['A']), false, 'scope: editor without locations');
$_POST = ['locations_all' => '1', 'locations' => ['A']];
eq(pd_infoscreen_locs_from_post(['A', 'B']), null, 'post: Alle locaties wins');
$_POST = ['locations' => ['B', 'X']];
eq(pd_infoscreen_locs_from_post(['A', 'B']), '["B"]', 'post: unknown location dropped');
$_POST = ['locations_all' => '1', 'locations' => ['X']];
eq(pd_infoscreen_locs_from_post(['A', 'B'], false), false, 'post: editor cannot pick all, nothing valid = invalid');
$_POST = [];
eq(pd_infoscreen_locs_from_post(['A'], true), null, 'post: admin nothing ticked = all');
$html = pd_infoscreen_location_picker(['A', 'B<'], ['A'], false);
check(strpos($html, 'locations_all') === false, 'picker: editor has no Alle locaties');
check(strpos($html, 'B&lt;') !== false, 'picker: names escaped');

// ── Cron key (2.6.0) ─────────────────────────────────────────────────────
$cronDir = sys_get_temp_dir() . '/pd_cron_test_' . bin2hex(random_bytes(4));
define('PD_CRON_KEY_DIR', $cronDir);
require __DIR__ . '/../includes/cron_key.php';
$key = pd_cron_key();
check(is_string($key) && strlen($key) === 32, 'cron: key created');
check(is_file($cronDir . '/cron_key.php') && is_file($cronDir . '/.htaccess'), 'cron: key file and .htaccess written');
check(strpos((string)file_get_contents($cronDir . '/cron_key.php'), PD_CRON_LEGACY_KEY) === false, 'cron: new key is not the old one');
eq(pd_cron_legacy_allowed(), false, 'cron: fresh installation never accepts the old key');
check($key !== pd_cron_new_key(), 'cron: keys are random');
@unlink($cronDir . '/cron_key.php'); @unlink($cronDir . '/.htaccess'); @rmdir($cronDir);

// ── Website source (2.7.0) ───────────────────────────────────────────────
require __DIR__ . '/../includes/infoscreen_web.php';
$web = fn(string $f) => file_get_contents(__DIR__ . '/fixtures/infoscreen/web/' . $f);
$detect = function (string $f, string $url) use ($web) {
    $xp = pd_is_web_dom($web($f));
    return pd_is_web_detect($xp, pd_is_web_base($xp, $url));
};
$g = $detect('schoolsite.html', 'https://www.voorbeeldschool.test/actueel');
eq(count($g), 1, 'web school: one list (menu, footer, pages and social icons skipped)');
eq($g[0]['count'], 3, 'web school: three news items');
eq($g[0]['items'][0]['title'], 'Kunstpodium groot succes', 'web school: title from the heading');
eq($g[0]['items'][0]['link'], 'https://www.voorbeeldschool.test/actueel/nieuws/kunstpodium-groot-succes', 'web school: absolute link');
eq($g[0]['items'][0]['published'], '2026-11-12 08:00:00', 'web school: Dutch date');
eq($g[0]['items'][1]['image'], 'https://www.voorbeeldschool.test/cp/uploads/nieuws/lampion.jpg', 'web school: lazy-loaded image (data-src)');
eq($g[0]['items'][1]['published'], '2026-11-05 08:00:00', 'web school: abbreviated month');
eq($g[0]['items'][2]['link'], 'https://www.voorbeeldschool.test/nieuws/herfstvakantie', 'web school: relative link resolved');
eq($g[0]['items'][2]['published'], '2026-10-28 08:00:00', 'web school: dd-mm-yyyy');
$g = $detect('webflow.html', 'https://www.stichting.test/vacatures');
eq(count($g), 1, 'web webflow: one list (navbar, footer skipped)');
eq($g[0]['count'], 4, 'web webflow: four vacancies');
eq($g[0]['items'][0]['title'], 'Kom jij onze bovenbouw versterken? | 0,5 – 0,8 fte', 'web webflow: title');
$g = $detect('articles.html', 'https://gemeente.voorbeeld.test/nieuws');
eq($g[0]['count'], 3, 'web articles: three articles');
eq($g[0]['items'][0]['link'], 'https://gemeente.voorbeeld.test/nieuws/bericht-1', 'web articles: <base href>');
eq($g[0]['items'][0]['image'], 'https://gemeente.voorbeeld.test/img/a-1024.jpg', 'web articles: widest srcset image');
eq($g[0]['items'][1]['image'], 'https://gemeente.voorbeeld.test/img/b.jpg', 'web articles: background image');
eq($g[0]['items'][2]['title'], 'Café voor mantelzorgers', 'web articles: windows-1252 page as UTF-8');
check($g[0]['items'][0]['published'] !== null && str_starts_with($g[0]['items'][0]['published'], '2026-11-20'), 'web articles: <time datetime>');
eq($detect('jsonly.html', 'https://app.test/'), [], 'web: JavaScript-only page gives nothing');
$xp = pd_is_web_dom($web('wordpress.html'));
eq(array_keys(pd_is_web_feed_links($xp, 'https://kc.voorbeeld.test/')), ['https://kc.voorbeeld.test/feed/'], 'web: feed link found, comments feed skipped');
// Saved list and CSS mode
$xpath = $detect('schoolsite.html', 'https://www.voorbeeldschool.test/actueel')[0]['xpath'];
eq(count(pd_is_web_extract($web('schoolsite.html'), 'https://www.voorbeeldschool.test/actueel', ['mode' => 'list', 'xpath' => $xpath, 'css' => []])), 3, 'web: saved list');
$items = pd_is_web_extract($web('webflow.html'), 'https://www.stichting.test/vacatures',
    ['mode' => 'css', 'css' => ['item' => '.vacature-list > .vacature-item', 'title' => 'h3.vacature-title', 'date' => '']]);
eq(count($items), 4, 'web css: items');
eq($items[3]['title'], 'Leerkracht vervangingspool', 'web css: title selector');
eq(pd_is_web_css_to_xpath('ul.news > li a[href]'), "//ul[contains(concat(' ',normalize-space(@class),' '),' news ')]/li//a[@href]", 'css to xpath');
eq(pd_is_web_css_to_xpath('h3', true), './/h3', 'css to xpath: relative');
eq(pd_is_web_css_to_xpath('div:hover'), null, 'css to xpath: pseudo-classes refused');
eq(pd_is_web_abs('../x.html', 'https://a.test/b/c/d.html'), 'https://a.test/b/x.html', 'web: ../ resolved');
eq(pd_is_web_abs('javascript:void(0)', 'https://a.test/'), null, 'web: javascript: link refused');
eq(pd_is_web_date('31 februari 2026'), null, 'web: impossible date');

// ── BHV and birthdays (2.8.0) ────────────────────────────────────────────
eq(pd_birthday_normalize('14-03'), '03-14', 'birthday: dd-mm');
eq(pd_birthday_normalize('4/3'), '03-04', 'birthday: d/m');
eq(pd_birthday_normalize('14-03-1985'), '03-14', 'birthday: year dropped');
eq(pd_birthday_normalize('1985-03-14'), '03-14', 'birthday: ISO date');
eq(pd_birthday_normalize('29-02'), '02-29', 'birthday: 29 February allowed');
eq(pd_birthday_normalize('31-04'), null, 'birthday: impossible date');
eq(pd_birthday_normalize('morgen'), null, 'birthday: text refused');
eq(pd_birthday_normalize('  '), '', 'birthday: empty clears');
eq(pd_birthday_display('03-14'), '14-03', 'birthday: display');
eq(pd_birthday_display(null), '', 'birthday: display empty');
eq(pd_infoscreen_short_name(['voornaam' => 'Eva', 'achternaam' => 'van der Berg']), 'Eva B.', 'short name: prefix skipped');
eq(pd_infoscreen_short_name(['voornaam' => 'Jan', 'achternaam' => 'Smit']), 'Jan S.', 'short name');
eq(pd_infoscreen_short_name(['voornaam' => '', 'achternaam' => '', 'naam' => 'Piet de Vries']), 'Piet V.', 'short name: from naam');
eq(pd_infoscreen_short_name(['voornaam' => 'Noor', 'achternaam' => '']), 'Noor', 'short name: no surname');
foreach (['bhv_card' => '0', 'bhv_names' => '1', 'birthday_slide' => '0'] as $k => $v) {
    eq(PD_IS_DEFAULT_SETTINGS[$k] ?? null, $v, "default setting $k");
}

eq(pd_infoscreen_screen_overrides('{"infoscreenParts":{"birthdays":"off","bhv":"on"}}'), ['birthday_slide' => '0', 'bhv_card' => '1'], 'screen parts: on/off');
eq(pd_infoscreen_screen_overrides('{"infoscreenParts":{"birthdays":"maybe"}}'), [], 'screen parts: unknown value = default');
eq(pd_infoscreen_screen_overrides(null), [], 'screen parts: no features');
eq(pd_infoscreen_screen_parts_clean(['birthdays' => 'on', 'bhv' => 'default', 'x' => 'on']), ['birthdays' => 'on'], 'screen parts: clean form input');

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec("CREATE TABLE employees (id INTEGER PRIMARY KEY, voornaam TEXT, achternaam TEXT, naam TEXT, status TEXT, sub_status TEXT,
               locatie TEXT, home_locatie TEXT, bhv TEXT, actief INT, verjaardag TEXT)");
    $ins = $db->prepare("INSERT INTO employees (voornaam, achternaam, naam, status, locatie, home_locatie, bhv, actief, verjaardag) VALUES (?,?,?,?,?,?,?,?,?)");
    foreach ([
        ['Eva', 'Smit', 'Eva Smit', 'IN', 'Hoofdgebouw', 'Hoofdgebouw', 'Ja', 1, '03-14'],
        ['Jan', 'Bakker', 'Jan Bakker', 'IN', 'Dependance', 'Hoofdgebouw', 'Ja', 1, '02-29'],
        ['Piet', 'Jansen', 'Piet Jansen', 'OUT', 'Hoofdgebouw', 'Hoofdgebouw', 'Ja', 1, '03-14'],
        ['Noor', 'Visser', 'Noor Visser', 'IN', 'Hoofdgebouw', '', 'Nee', 1, null],
        ['Oud', 'Lid', 'Oud Lid', 'IN', 'Hoofdgebouw', 'Hoofdgebouw', 'Ja', 0, '03-14'],
        ['Sam', 'de Wit', 'Sam de Wit', 'OVERLEG', 'Hoofdgebouw', 'Hoofdgebouw', 'Ja', 1, null],
        ['Tim', 'Kok', 'Tim Kok', 'IN', 'Hoofdgebouw', 'Hoofdgebouw', 'Ja', 1, null],
    ] as $r) $ins->execute($r);
    $db->exec("UPDATE employees SET sub_status = 'THUISWERKEN' WHERE voornaam = 'Tim'");
    eq(pd_infoscreen_bhv_present($db, [], true), ['count' => 2, 'names' => ['Eva S.', 'Jan B.']], 'bhv: all locations, status IN without extra status');
    eq(pd_infoscreen_bhv_present($db, ['Hoofdgebouw'], true), ['count' => 1, 'names' => ['Eva S.']], 'bhv: current location counts');
    eq(pd_infoscreen_bhv_present($db, ['Gymzaal'], false), ['count' => 0, 'names' => []], 'bhv: nobody');
    eq(pd_infoscreen_bhv_present($db, [], false)['names'], [], 'bhv: names hidden');
    $tz = new DateTimeZone('Europe/Amsterdam');
    eq(pd_infoscreen_birthdays($db, [], new DateTimeImmutable('2026-03-14', $tz)), ['Eva S.', 'Piet J.'], 'birthdays: today, inactive skipped');
    eq(pd_infoscreen_birthdays($db, ['Hoofdgebouw'], new DateTimeImmutable('2027-02-28', $tz)), ['Jan B.'], 'birthdays: 29-02 on 28-02, home location');
    eq(pd_infoscreen_birthdays($db, [], new DateTimeImmutable('2028-02-28', $tz)), [], 'birthdays: leap year waits for 29-02');
    eq(pd_infoscreen_birthdays($db, ['Dependance'], new DateTimeImmutable('2026-03-14', $tz)), [], 'birthdays: other location');
    pd_birthday_save($db, 4, '1-1');
    eq($db->query("SELECT verjaardag FROM employees WHERE id = 4")->fetchColumn(), '01-01', 'birthday: saved');
    check(pd_birthday_save($db, 4, '32-1') === false, 'birthday: invalid not saved');
    pd_birthday_save($db, 4, '');
    eq($db->query("SELECT verjaardag FROM employees WHERE id = 4")->fetchColumn(), null, 'birthday: cleared');
} else {
    echo "(sqlite not available: BHV/birthday queries tested in db_smoke)\n";
}

// ── Huisstijl (2.9.0) ────────────────────────────────────────────────────
eq(pd_color_valid('#ABC'), '#aabbcc', 'color: short hex');
eq(pd_color_valid('1d4ed8'), '#1d4ed8', 'color: without #');
eq(pd_color_valid('red'), null, 'color: names refused');
eq(pd_color_valid('#12345g'), null, 'color: invalid hex');
eq(pd_color_contrast('#ffffff', '#000000'), 21.0, 'contrast: black on white');
eq(pd_color_contrast('#777777', '#777777'), 1.0, 'contrast: same colour');
eq(pd_color_text_on('#1d4ed8'), '#ffffff', 'text on dark blue: white');
eq(pd_color_text_on('#fde047'), PD_BRAND_DARK_TEXT, 'text on yellow: dark');
eq(pd_color_shade('#ffffff', -0.5), '#808080', 'shade: half darker');
eq(array_column(pd_brand_contrast_report('#1d4ed8', '#fde047', '#0b1f3a', '#143a6b'), 2), ['ok', 'ok', 'ok', 'ok'], 'contrast report: good set');
eq(pd_brand_contrast_report('#1d4ed8', '#dc2626', '#0b1f3a', '#808080')[1][2], 'warn', 'contrast report: grey background is a warning');
$rows = [
    '' => ['location_name' => '', 'title' => 'Stichting', 'logo_path' => 'uploads/infoscreen/logo_s.png', 'logo_mode' => 'logo_title', 'logo_plate' => 0,
           'use_colors' => 1, 'color_primary' => '#00a3e0', 'color_accent' => '#e4007c', 'color_bg1' => '#ffffff', 'color_bg2' => '#eef6fb', 'font' => 'verdana'],
    'OBS De Linde' => ['location_name' => 'OBS De Linde', 'title' => 'Welkom op De Linde', 'logo_path' => null, 'logo_mode' => 'logo', 'logo_plate' => 1,
           'use_colors' => 0, 'color_primary' => null, 'color_accent' => null, 'color_bg1' => null, 'color_bg2' => null, 'font' => null],
    'KC Kiem' => ['location_name' => 'KC Kiem', 'title' => null, 'logo_path' => 'uploads/infoscreen/logo_k.png', 'logo_mode' => 'logo', 'logo_plate' => 1,
           'use_colors' => 1, 'color_primary' => '#2e7d32', 'color_accent' => '#f9a825', 'color_bg1' => '#0d2b12', 'color_bg2' => '#0d2b12', 'font' => 'georgia'],
];
$b = pd_branding_resolve($rows, [], 'donker', '/pd');
eq([$b['title'], $b['logo'], $b['colors']['light'], $b['primary']['bg']], ['Stichting', '/pd/uploads/infoscreen/logo_s.png', true, '#00a3e0'], 'brand: organisation (light background)');
check(str_starts_with($b['font'], 'Verdana'), 'brand: organisation font');
$b = pd_branding_resolve($rows, ['OBS De Linde'], 'donker', '');
eq([$b['title'], $b['logo'], $b['colors']['bg1'], $b['location']], ['Welkom op De Linde', '/uploads/infoscreen/logo_s.png', '#ffffff', 'OBS De Linde'], 'brand: location title, organisation logo and colours');
eq($b['logo_mode'], 'logo_title', 'brand: logo settings come with the logo');
$b = pd_branding_resolve($rows, ['Gymzaal', 'KC Kiem'], 'donker', '');
eq([$b['title'], $b['location'], $b['colors']['bg1']], ['Stichting', null, '#ffffff'], 'brand: screen with more locations = organisation');
$b = pd_branding_resolve($rows, ['OBS De Linde'], 'donker', '', '', '');
eq($b['location'], null, 'brand: organisation chosen for a one-location screen');
$b = pd_branding_resolve($rows, ['Gymzaal', 'KC Kiem'], 'donker', '', '', 'KC Kiem');
eq([$b['title'], $b['colors']['light'], $b['accent']['bg'], $b['logo_mode'], $b['logo_plate']], ['Stichting', false, '#f9a825', 'logo', true], 'brand: chosen location, own colours');
eq($b['accent']['text'], PD_BRAND_DARK_TEXT, 'brand: dark text on a yellow accent');
eq($b['colors']['link'], '#ffffff', 'brand: dark green headings on dark green are unreadable, so white');
check(str_starts_with($b['font'], 'Georgia'), 'brand: location font');
$b = pd_branding_resolve([], ['X'], 'licht', '', 'Oude titel');
eq([$b['title'], $b['logo'], $b['colors'], $b['primary']['bg']], ['Oude titel', null, null, PD_BRAND_DEFAULT_PRIMARY], 'brand: nothing set = theme and old title');
eq(pd_infoscreen_image_options_from_post(['image_layout' => 'top', 'image_fit' => 'contain', 'image_size' => '95']), ['top', 'contain', 70], 'image options: clamped size');
eq(pd_infoscreen_image_options_from_post(['image_layout' => 'diagonal']), ['left', 'cover', 50], 'image options: defaults');
eq(pd_infoscreen_color_from_post(['color' => 'accent']), 'accent', 'message colour: accent');
eq(pd_infoscreen_color_from_post(['color' => 'zwart']), 'huisstijl', 'message colour: unknown = huisstijl');
eq(pd_infoscreen_screen_brand('{"infoscreenBrand":""}'), '', 'screen brand: organisation');
eq(pd_infoscreen_screen_brand('{"infoscreenBrand":"KC Kiem"}'), 'KC Kiem', 'screen brand: location');
eq(pd_infoscreen_screen_brand('{}'), null, 'screen brand: automatic');

echo ($failures ? "FAILED: $failures of $count checks\n" : "OK: $count infoscreen checks passed\n");
exit($failures ? 1 : 0);
