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
