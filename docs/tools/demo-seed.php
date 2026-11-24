<?php
/**
 * docs/tools/demo-seed.php
 *
 * Builds a throw-away demo installation for the manual screenshots:
 * a fresh database from install.sql, a superadmin, a few users, fictional
 * locations, departments, employees and visitors, and admin/db_config.php
 * in the given app folder. NEVER point this at a real database — it drops
 * and recreates the database it is given.
 *
 *   [PD_DEMO_DOMAIN=demo.peopledisplay.test] php docs/tools/demo-seed.php <app-folder> <host> <port> <db-user> <db-pass> [db-name]
 *
 * Used by docs/tools/screenshots.mjs (see docs/README.md).
 */

declare(strict_types=1);

[$_, $appDir, $host, $port, $user, $pass] = array_pad($argv, 6, '');
$dbName = $argv[6] ?? 'pd_demo';
if (!$appDir || !is_dir($appDir) || !$host) {
    fwrite(STDERR, "usage: php demo-seed.php <app-folder> <host> <port> <db-user> <db-pass> [db-name]\n");
    exit(1);
}
$root = dirname(__DIR__, 2);

$server = new PDO("mysql:host=$host;port=$port", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec("DROP DATABASE IF EXISTS `$dbName`");
$server->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$db = new PDO("mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Schema exactly as install.php imports it
$sql = file_get_contents($root . '/install.sql');
$sql = preg_replace('/--.*$/m', '', $sql);
$sql = preg_replace('#/\*.*?\*/#s', '', $sql);
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    if ($stmt !== '') $db->exec($stmt);
}

file_put_contents($appDir . '/admin/db_config.php',
    "<?php\n\$DB_HOST='" . addslashes($host) . "';\n\$DB_NAME='$dbName';\n\$DB_USER='" . addslashes($user) . "';\n\$DB_PASS='" . addslashes($pass) . "';\n");

// ── Fictional organisation ─────────────────────────────────────────────
$locations = [
    ['Hoofdgebouw', 'HG', 'Schoolstraat 1, Voorbeeldstad'],
    ['BSO De Vlinder', 'BSO', 'Parklaan 12, Voorbeeldstad'],
    ['KDV Het Speelhuis', 'KDV', 'Dorpsplein 3, Voorbeelddorp'],
];
$st = $db->prepare('INSERT INTO locations (location_name, location_code, address, active, sort_order) VALUES (?, ?, ?, 1, ?)');
foreach ($locations as $i => $l) $st->execute([$l[0], $l[1], $l[2], $i + 1]);

$departments = ['Directie', 'Onderwijs', 'Kinderopvang', 'Administratie', 'Facilitair'];
$st = $db->prepare('INSERT INTO afdelingen (afdeling_name, active, sort_order) VALUES (?, 1, ?)');
foreach ($departments as $i => $d) $st->execute([$d, $i + 1]);

// [voornaam, achternaam, functie, afdeling, locatie, status, bhv, sub_status]
$employees = [
    ['Anna', 'de Vries', 'Directeur', 'Directie', 'Hoofdgebouw', 'IN', 'Ja', null],
    ['Bram', 'Jansen', 'Leerkracht groep 5', 'Onderwijs', 'Hoofdgebouw', 'IN', 'Nee', null],
    ['Carlijn', 'Bakker', 'Leerkracht groep 3', 'Onderwijs', 'Hoofdgebouw', 'IN', 'Ja', 'VAKANTIE'],
    ['Daan', 'Visser', 'Conciërge', 'Facilitair', 'Hoofdgebouw', 'IN', 'Ja', null],
    ['Eva', 'Smit', 'Administratief medewerker', 'Administratie', 'Hoofdgebouw', 'IN', 'Nee', 'PAUZE'],
    ['Finn', 'Meijer', 'Intern begeleider', 'Onderwijs', 'Hoofdgebouw', 'IN', 'Nee', 'THUISWERKEN'],
    ['Gwen', 'de Boer', 'Pedagogisch medewerker', 'Kinderopvang', 'BSO De Vlinder', 'IN', 'Ja', null],
    ['Hugo', 'Mulder', 'Pedagogisch medewerker', 'Kinderopvang', 'BSO De Vlinder', 'OUT', 'Nee', null],
    ['Isa', 'de Groot', 'Locatiemanager', 'Kinderopvang', 'BSO De Vlinder', 'IN', 'Nee', null],
    ['Jesse', 'Bos', 'Pedagogisch medewerker', 'Kinderopvang', 'KDV Het Speelhuis', 'IN', 'Ja', null],
    ['Kim', 'Vos', 'Pedagogisch medewerker', 'Kinderopvang', 'KDV Het Speelhuis', 'OUT', 'Nee', null],
    ['Lotte', 'Peters', 'Locatiemanager', 'Kinderopvang', 'KDV Het Speelhuis', 'IN', 'Nee', null],
];
$photoDir = $appDir . '/images'; // employee photos live in /images (BASE_PHOTO_URL in app.js)
@mkdir($photoDir, 0755, true);
$colors = ['#667eea', '#48bb78', '#ed8936', '#e53e3e', '#38b2ac', '#9f7aea', '#d69e2e', '#3182ce', '#dd6b20', '#319795', '#805ad5', '#c53030'];
$st = $db->prepare('INSERT INTO employees
    (employee_id, naam, voornaam, achternaam, functie, afdeling, locatie, home_locatie, status, bhv, sub_status, sub_status_type, sub_status_until,
     foto_url, email, allow_manual_location_change, actief, tijdstip)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, ?)');
foreach ($employees as $i => $e) {
    [$first, $last, $role, $dept, $loc, $status, $bhv, $sub] = $e;
    $file = sprintf('demo_%02d.svg', $i + 1);
    $initials = mb_substr($first, 0, 1) . mb_strtoupper(mb_substr(preg_replace('/^(de|van|van de|van der)\s+/i', '', $last), 0, 1));
    file_put_contents("$photoDir/$file", sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160" viewBox="0 0 160 160"><rect width="160" height="160" fill="%s"/>' .
        '<circle cx="80" cy="62" r="30" fill="#ffffff" opacity=".9"/><rect x="30" y="102" width="100" height="70" rx="50" fill="#ffffff" opacity=".9"/>' .
        '<text x="80" y="72" font-family="Arial, sans-serif" font-size="26" font-weight="700" text-anchor="middle" fill="%s">%s</text></svg>',
        $colors[$i % count($colors)], $colors[$i % count($colors)], htmlspecialchars($initials)
    ));
    // Extra buttons are stored as BUTTON1/2/3 (PAUZE/THUISWERKEN/VAKANTIE) with status IN
    $subLabel = $sub ? ['PAUZE' => 'BUTTON1', 'THUISWERKEN' => 'BUTTON2', 'VAKANTIE' => 'BUTTON3'][$sub] : null;
    $until = $sub ? date('Y-m-d', strtotime('+' . ($sub === 'VAKANTIE' ? 7 : 0) . ' days')) . ' 17:00:00' : null;
    $st->execute([
        sprintf('E%03d', $i + 1), "$first $last", $first, $last, $role, $dept, $loc, $loc, $status, $bhv,
        $subLabel, $subLabel, $until,
        "/images/$file", strtolower($first) . '@voorbeeldschool.nl',
        date('Y-m-d H:i:s', strtotime('today 08:' . sprintf('%02d', 5 + $i * 4))),
    ]);
}

// ── Users ──────────────────────────────────────────────────────────────
$allLocations = array_column($locations, 0);
$features = [
    'visibleFields'    => ['Foto', 'Voornaam', 'Achternaam', 'Functie', 'Afdeling', 'Locatie', 'BHV'],
    'extraButtons'     => ['PAUZE' => true, 'THUISWERKEN' => true, 'VAKANTIE' => true],
    'locations'        => $allLocations,
    'sorteerFunctie'   => true,
    'sorteerStandaard' => 'voornaam_status',
    'zoomFunctie'      => true,
    'zoomStandaard'    => 100,
    'inuitBord'        => false,
    'admin_features'   => [],
];
$users = [
    ['admin', 'Demo2026!', 'Beheerder', 'superadmin', $features],
    ['receptie', 'Demo2026!', 'Receptie Hoofdgebouw', 'user', ['locations' => ['Hoofdgebouw']] + $features],
    ['bord', 'Demo2026!', 'Infoscherm hal', 'user', ['inuitBord' => true] + $features],
];
$st = $db->prepare('INSERT INTO users (username, password_hash, display_name, email, role, features, active) VALUES (?, ?, ?, ?, ?, ?, 1)');
foreach ($users as $u) {
    $st->execute([$u[0], password_hash($u[1], PASSWORD_DEFAULT), $u[2], $u[0] . '@voorbeeldschool.nl', $u[3], json_encode($u[4])]);
}

// Location pin ("Pion"): two employees may check in at other locations
$db->exec("UPDATE employees SET allow_manual_location_change = 1, visible_locations = '[\"ALL\"]' WHERE voornaam IN ('Daan', 'Gwen')");

// WiFi/IP auto check-in configured for the main building (documentation IP range)
$db->exec("UPDATE locations SET primary_ip = '203.0.113.10', ip_range = '203.0.113.0/28', auto_checkin_enabled = 1
           WHERE location_name = 'Hoofdgebouw'");

// Kiosk token for the hall screen
$db->prepare("INSERT INTO kiosk_tokens (user_id, token, description, created_by, last_used)
              SELECT id, ?, 'Tablet ontvangstbalie Hoofdgebouw', 1, NOW() - INTERVAL 5 MINUTE FROM users WHERE username = 'receptie'")
   ->execute([bin2hex(random_bytes(16))]);

// Some audit history and active sessions
$audit = $db->prepare("INSERT INTO employee_audit (employee_id, action, field_changed, old_value, new_value, changed_by, ip_address, user_agent, created_at)
                       VALUES (?, ?, 'status', ?, ?, 1, '192.168.1.24', 'Mozilla/5.0 (Windows NT 10.0) Chrome/130', NOW() - INTERVAL ? MINUTE)");
foreach ([[1, 'OUT', 'IN', 95], [2, 'OUT', 'IN', 80], [4, 'OUT', 'IN', 75], [8, 'IN', 'OUT', 40], [5, 'IN', 'PAUZE', 12], [3, 'IN', 'VAKANTIE', 5]] as $a) {
    $audit->execute([$a[0], 'STATUS_CHANGE', $a[1], $a[2], $a[3]]);
}
$sess = $db->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, browser, device, login_time, last_activity, is_active, page_url)
                      SELECT id, ?, ?, ?, ?, ?, NOW() - INTERVAL ? MINUTE, NOW() - INTERVAL ? SECOND, 1, ? FROM users WHERE username = ?");
$sess->execute(['demo-sess-receptie', '192.168.1.40', 'Mozilla/5.0 (iPad; CPU OS 17_0) Safari', 'Safari', 'iPad', 180, 20, '/index.php', 'receptie']);
$sess->execute(['demo-sess-bord', '192.168.1.41', 'Mozilla/5.0 (X11; Linux) Chrome/130', 'Chrome', 'Linux', 300, 45, '/inuitbord.php', 'bord']);

// ── Visitors (today) ───────────────────────────────────────────────────
$st = $db->prepare('INSERT INTO visitors (voornaam, achternaam, email, bedrijf, contactpersoon, locatie, bezoek_datum, tijd, status, checked_in_at, privacy_accepted)
    VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, 1)');
$st->execute(['Mark', 'van Dam', 'mark@leverancier.example', 'Schoolmeubel BV', 'Daan Visser', 'Hoofdgebouw', '09:30:00', 'BINNEN', date('Y-m-d') . ' 09:31:00']);
$st->execute(['Sophie', 'Willems', 'sophie@inspectie.example', 'Onderwijsinspectie', 'Anna de Vries', 'Hoofdgebouw', '13:00:00', 'AANGEMELD', null]);

// ── Infoscherm: messages, a news feed and the weather (no network needed) ──
require_once $root . '/includes/infoscreen.php';
pd_infoscreen_ensure_schema($db);
$imgDir = $appDir . '/' . PD_IS_UPLOAD_DIR;
@mkdir($imgDir, 0755, true);
// Simple illustrations drawn with GD, so the screenshots need no photos from the internet
$demoImage = function (string $file, array $top, array $bottom, string $motif) use ($imgDir): void {
    $w = 1280; $h = 720;
    $im = imagecreatetruecolor($w, $h);
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        $c = imagecolorallocate($im, (int)($top[0] + ($bottom[0] - $top[0]) * $t), (int)($top[1] + ($bottom[1] - $top[1]) * $t), (int)($top[2] + ($bottom[2] - $top[2]) * $t));
        imageline($im, 0, $y, $w, $y, $c);
    }
    $white = imagecolorallocatealpha($im, 255, 255, 255, 20);
    $sun = imagecolorallocate($im, 253, 200, 60);
    $green = imagecolorallocate($im, 76, 160, 90);
    $dark = imagecolorallocate($im, 46, 110, 64);
    if ($motif === 'park') {
        imagefilledellipse($im, 1020, 170, 170, 170, $sun);
        imagefilledellipse($im, 300, 760, 1100, 420, $green);
        imagefilledellipse($im, 1050, 800, 1200, 460, $dark);
        foreach ([[220, 470], [380, 500], [900, 520]] as [$x, $y]) {
            imagefilledrectangle($im, $x - 8, $y, $x + 8, $y + 90, imagecolorallocate($im, 110, 70, 40));
            imagefilledellipse($im, $x, $y, 120, 120, $dark);
        }
    } elseif ($motif === 'books') {
        $cols = [[220, 70, 60], [60, 120, 200], [240, 170, 50], [90, 160, 110], [150, 90, 180]];
        $x = 300;
        foreach ($cols as $i => $c) {
            $bw = 90 + ($i % 2) * 20; $bh = 330 + ($i * 37) % 90;
            imagefilledrectangle($im, $x, 600 - $bh, $x + $bw, 600, imagecolorallocate($im, ...$c));
            imagefilledrectangle($im, $x + 15, 600 - $bh + 40, $x + $bw - 15, 600 - $bh + 60, $white);
            $x += $bw + 14;
        }
        imagefilledrectangle($im, 240, 600, 1040, 625, imagecolorallocate($im, 120, 80, 50));
    } else { // bike / sport
        imagefilledrectangle($im, 0, 560, $w, $h, imagecolorallocate($im, 90, 90, 100));
        imagesetthickness($im, 14);
        foreach ([470, 810] as $cx) imageellipse($im, $cx, 470, 230, 230, $white);
        imageline($im, 470, 470, 640, 470, $white); imageline($im, 640, 470, 810, 470, $white);
        imageline($im, 560, 330, 640, 470, $white); imageline($im, 560, 330, 760, 340, $white); imageline($im, 760, 340, 810, 470, $white);
        imagefilledellipse($im, 1050, 160, 150, 150, $sun);
    }
    imagejpeg($im, $imgDir . '/' . $file, 86);
    imagedestroy($im);
};
$demoImage('demo-bericht-1.jpg', [255, 196, 120], [235, 120, 80], 'books');
$demoImage('demo-nieuws-1.jpg', [120, 190, 240], [200, 230, 250], 'park');
$demoImage('demo-nieuws-2.jpg', [90, 150, 220], [170, 210, 240], 'bike');

$msg = $db->prepare("INSERT INTO infoscreen_messages (title, body, image_path, location, starts_at, ends_at, priority, color, active, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
$msg->execute(['Boekenmarkt in de aula', "Woensdag van 14:00 tot 16:00 uur. Alle boeken voor € 1.\nDe opbrengst gaat naar de nieuwe schoolbibliotheek.", PD_IS_UPLOAD_DIR . '/demo-bericht-1.jpg', null, date('Y-m-d 00:00:00', strtotime('-2 days')), date('Y-m-d 23:59:00', strtotime('+5 days')), 0, 'oranje']);
$msg->execute(['Studiedag vrijdag', 'Vrijdag zijn alle groepen vrij. De BSO is de hele dag open.', null, null, null, date('Y-m-d 18:00:00', strtotime('+4 days')), 1, 'rood']);
$msg->execute(['Welkom aan onze nieuwe collega’s', 'Gwen en Hidde zijn deze maand gestart. Zeg gerust even hallo!', null, 'Hoofdgebouw', null, null, 0, 'blauw']);
$msg->execute(['Kerstviering', 'Donderdag 17 december om 17:30 uur in het speellokaal.', null, null, date('Y-m-d 08:00:00', strtotime('+10 days')), null, 0, 'groen']);

$demoHost = 'http://' . (getenv('PD_DEMO_DOMAIN') ?: 'localhost');
$db->prepare("INSERT INTO infoscreen_sources (type, name, url, location, max_items, show_images, attribution, active, last_fetch_at, last_ok_at, last_status)
              VALUES ('rss', 'Nieuws Voorbeeldstad', 'https://www.voorbeeldstad.example/feed/', NULL, 8, 1, 'Gemeente Voorbeeldstad', 1, NOW(), NOW(), 'OK: 5 berichten opgehaald, 2 met afbeelding')")->execute();
$rssId = (int)$db->lastInsertId();
$item = $db->prepare("INSERT INTO infoscreen_items (source_id, item_key, title, summary, image_url, link, published_at) VALUES (?, ?, ?, ?, ?, ?, NOW() - INTERVAL ? MINUTE)");
foreach ([
    ['Nieuw speelpark aan de Parklaan feestelijk geopend', 'Kinderen uit de buurt mochten als eersten de klimtoren en de waterspeeltuin uitproberen.', $demoHost . '/' . PD_IS_UPLOAD_DIR . '/demo-nieuws-1.jpg', 50],
    ['Fietsstraat langs de school is klaar', 'Vanaf maandag hebben fietsers voorrang op de Schoolstraat.', $demoHost . '/' . PD_IS_UPLOAD_DIR . '/demo-nieuws-2.jpg', 140],
    ['Bibliotheek verlengt openingstijden op zaterdag', '', null, 230],
    ['Inzameling oud papier verschuift naar dinsdag', '', null, 400],
    ['Voorleeswedstrijd: inschrijving geopend', '', null, 600],
] as $i => $n) {
    $item->execute([$rssId, sha1('demo' . $i), $n[0], $n[1], $n[2], 'https://www.voorbeeldstad.example/nieuws/' . $i, $n[3]]);
}
$weather = pd_infoscreen_parse_buienradar(file_get_contents($root . '/tests/fixtures/infoscreen/buienradar.json'), 51.5667, 4.9333);
$weather['fetched_at'] = date('c');
$db->prepare("INSERT INTO infoscreen_sources (type, name, provider, latitude, longitude, active, data_json, last_fetch_at, last_ok_at, last_status)
              VALUES ('weather', 'Voorbeeldstad', 'buienradar', 51.5667, 4.9333, 1, ?, NOW(), NOW(), ?)")
   ->execute([json_encode($weather, JSON_UNESCAPED_UNICODE), 'OK: Weer opgehaald: 7.4°C, Licht bewolkt (meetstation Gilze-Rijen)']);
// Agenda (2.5.0): a published school calendar, with appointments relative to today
$db->prepare("INSERT INTO infoscreen_sources (type, name, url, active, last_fetch_at, last_ok_at, last_status)
              VALUES ('ical', 'Schoolagenda', 'https://outlook.office365.com/owa/calendar/voorbeeld/calendar.ics', 1, NOW(), NOW(), 'OK: 5 afspraken in de komende 60 dagen, waarvan 5 deze week')")->execute();
$icalId = (int)$db->lastInsertId();
$ev = $db->prepare("INSERT INTO infoscreen_events (source_id, event_key, title, place, starts_at, ends_at, all_day) VALUES (?, ?, ?, ?, ?, ?, ?)");
$day = fn(int $plus, string $time) => date('Y-m-d', strtotime("+$plus days")) . ' ' . $time;
foreach ([
    ['Teamoverleg', 'Personeelskamer', $day(0, '15:15:00'), $day(0, '16:30:00'), 0],
    ['Schoolfotograaf', 'Aula', $day(1, '08:30:00'), $day(1, '12:00:00'), 0],
    ['Sinterklaasviering', null, $day(2, '00:00:00'), $day(3, '00:00:00'), 1],
    ['Ouderavond groep 7 en 8', 'Speellokaal', $day(2, '19:30:00'), $day(2, '21:00:00'), 0],
    ['MR-vergadering', 'Personeelskamer', $day(3, '19:00:00'), $day(3, '20:30:00'), 0],
] as $i => $e) {
    $ev->execute([$icalId, sha1('demo-ev' . $i), $e[0], $e[1], $e[2], $e[3], $e[4]]);
}
// A Teams connection and a message posted through it
$db->exec("INSERT INTO infoscreen_api_keys (type, name, location, default_days, color, last_used_at) VALUES ('teams', 'Infoscherm', NULL, 7, 'paars', NOW() - INTERVAL 20 MINUTE)");
$teamsId = (int)$db->lastInsertId();
$db->prepare("INSERT INTO infoscreen_messages (title, body, location, ends_at, color, active, source, api_key_id, external_id, author)
              VALUES ('Gevonden: blauwe muts', 'Ligt bij de conciërge.', NULL, ?, 'paars', 1, 'teams', ?, 'teams:demo-1', 'Daan Visser')")
   ->execute([date('Y-m-d 23:59:00', strtotime('+3 days')), $teamsId]);

pd_infoscreen_save_settings($db, ['title' => 'Welkom op OBS De Voorbeeldschool', 'slide_seconds' => 12, 'news_slides' => 1, 'news_slide_max' => 4,
    'weather_slide' => 1, 'ticker' => 1, 'ticker_speed' => 60, 'sidebar' => 1, 'theme' => 'donker', 'show_clock' => 1,
    'agenda_sidebar' => 1, 'agenda_slide' => 1, 'agenda_days' => 7]);
$db->exec("UPDATE users SET screen_mode = 'infoscreen', can_show_presentation = 1 WHERE username = 'receptie'");

// ── Organisation settings and demo licence (local only) ───────────────
$db->prepare("UPDATE config SET
        visibleFields = ?, locations = ?, button1_name = 'PAUZE', button2_name = 'THUISWERKEN', button3_name = 'VAKANTIE',
        button3_ask_until = 1, button2_ask_until = 1,
        license_key = 'PDIS-TUNL-DEMO-0000', license_tier = 'unlimited', license_domain = ?,
        license_activated_at = NOW(), license_status = 'active',
        eula_accepted = 1, eula_accepted_at = NOW(), current_version = ?
    WHERE id = 1")
   ->execute([json_encode($features['visibleFields']), json_encode($allLocations), getenv('PD_DEMO_DOMAIN') ?: 'localhost', trim(json_decode(file_get_contents($root . '/version.json'), true)['version'])]);

echo "✓ demo database $dbName ready — login admin / Demo2026! (also: receptie, bord)\n";
