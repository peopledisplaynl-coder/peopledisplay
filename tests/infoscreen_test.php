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

echo ($failures ? "FAILED: $failures of $count checks\n" : "OK: $count infoscreen checks passed\n");
exit($failures ? 1 : 0);
