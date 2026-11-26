<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * infoscreen.php
 * Upload naar: /includes/infoscreen.php
 *
 * Infoscreen module (2.4.0): own messages, news feeds (RSS/Atom) and weather on a
 * display, as an alternative to (or next to) the Google Slides presentation.
 *
 * - Schema: pd_infoscreen_ensure_schema() — called from migrations.php and from the
 *   infoscreen pages themselves, so a screen works before an admin has visited.
 * - Fetching: pd_infoscreen_refresh() — from cron/cron_infoscreen.php, cron_endpoint.php
 *   or lazily after a screen request. One fetch at a time (MySQL named lock).
 * - Weather providers: Buienradar (JSON feed) and Open-Meteo. Both are free for
 *   non-commercial use; the admin page shows their terms and the screen always shows
 *   the source. News images are hot-linked, never copied to this server.
 */

if (defined('PD_INFOSCREEN_LOADED')) {
    return;
}
define('PD_INFOSCREEN_LOADED', true);

const PD_IS_MODES = ['slides', 'infoscreen', 'combined'];
const PD_IS_FETCH_MINUTES = 15;          // minimum minutes between fetches per source
const PD_IS_ITEMS_KEEP = 40;             // news items kept per source
const PD_IS_HTTP_TIMEOUT = 8;            // seconds per request
const PD_IS_MAX_BYTES = 3 * 1024 * 1024; // max download size per request
const PD_IS_UPLOAD_DIR = 'uploads/infoscreen';

const PD_IS_WEATHER_PROVIDERS = [
    'buienradar' => [
        'label'       => 'Buienradar',
        'attribution' => 'Weergegevens: Buienradar.nl',
        'terms'       => 'Gratis voor niet-commercieel gebruik, met bronvermelding (staat altijd op het scherm). Voor commercieel gebruik is toestemming van Buienradar nodig.',
        'terms_url'   => 'https://www.buienradar.nl/overbuienradar/gratis-weerdata',
    ],
    'open-meteo' => [
        'label'       => 'Open-Meteo',
        'attribution' => 'Weergegevens: Open-Meteo.com (CC BY 4.0)',
        'terms'       => 'Gratis voor niet-commercieel gebruik (tot 10.000 verzoeken per dag), met bronvermelding. Voor commercieel gebruik is een abonnement nodig.',
        'terms_url'   => 'https://open-meteo.com/en/terms',
    ],
];

const PD_IS_DEFAULT_SETTINGS = [
    'title'          => '',      // header text; empty = no title
    'slide_seconds'  => '12',    // default time per slide
    'news_slides'    => '1',     // show news items with an image as full slides
    'news_slide_max' => '4',     // max news slides per round
    'weather_slide'  => '1',     // a weather slide once per round
    'ticker'         => '1',     // headline ticker at the bottom
    'ticker_speed'   => '60',    // pixels per second
    'sidebar'        => '1',     // weather/clock sidebar
    'theme'          => 'donker',// donker | licht
    'show_clock'     => '1',
    'agenda_sidebar' => '1',     // upcoming appointments in the sidebar
    'agenda_slide'   => '1',     // an agenda slide once per round
    'agenda_days'    => '7',     // how many days ahead
    'bhv_card'       => '0',     // "BHV aanwezig" block with the checked-in BHV'ers
    'bhv_names'      => '1',     // show their names (first name + initial), not only the number
    'birthday_slide' => '0',     // "Vandaag jarig" slide + ticker item (opt-in, privacy)
];

// ─────────────────────────────────────────────────────────────────────────────
// Schema
// ─────────────────────────────────────────────────────────────────────────────

function pd_infoscreen_ensure_schema(PDO $db): bool
{
    static $done = null;
    if ($done !== null) {
        return $done;
    }
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `infoscreen_messages` (
            `id`               int(11)      NOT NULL AUTO_INCREMENT,
            `title`            varchar(200) NOT NULL,
            `body`             text         DEFAULT NULL,
            `image_path`       varchar(255) DEFAULT NULL,
            `location`         varchar(100) DEFAULT NULL COMMENT 'Verouderd sinds 2.6.0, zie locations',
            `locations`        text         DEFAULT NULL COMMENT 'JSON-lijst van locations.location_name, NULL = alle locaties',
            `starts_at`        datetime     DEFAULT NULL,
            `ends_at`          datetime     DEFAULT NULL,
            `duration_seconds` int(11)      DEFAULT NULL COMMENT 'NULL = standaard uit de instellingen',
            `priority`         tinyint(1)   NOT NULL DEFAULT 0 COMMENT '1 = belangrijk',
            `color`            varchar(20)  NOT NULL DEFAULT 'blauw',
            `active`           tinyint(1)   NOT NULL DEFAULT 1,
            `sort_order`       int(11)      NOT NULL DEFAULT 0,
            `source`           varchar(30)  NOT NULL DEFAULT 'handmatig',
            `api_key_id`       int(11)      DEFAULT NULL COMMENT 'Koppeling (Berichten-API of Teams) die het bericht plaatste',
            `external_id`      varchar(100) DEFAULT NULL COMMENT 'Eigen kenmerk van het andere systeem, om bij te werken of te verwijderen',
            `author`           varchar(150) DEFAULT NULL,
            `created_by`       int(11)      DEFAULT NULL,
            `created_at`       datetime     NOT NULL DEFAULT current_timestamp(),
            `updated_at`       datetime     DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_active_window` (`active`, `starts_at`, `ends_at`),
            KEY `idx_location` (`location`),
            KEY `idx_api_external` (`api_key_id`, `external_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `infoscreen_sources` (
            `id`            int(11)       NOT NULL AUTO_INCREMENT,
            `type`          enum('rss','weather','ical') NOT NULL,
            `name`          varchar(150)  NOT NULL,
            `url`           varchar(500)  DEFAULT NULL,
            `provider`      varchar(30)   DEFAULT NULL,
            `latitude`      decimal(9,6)  DEFAULT NULL,
            `longitude`     decimal(9,6)  DEFAULT NULL,
            `location`      varchar(100)  DEFAULT NULL COMMENT 'Verouderd sinds 2.6.0, zie locations',
            `locations`     text          DEFAULT NULL COMMENT 'JSON-lijst van locations.location_name, NULL = alle locaties',
            `max_items`     int(11)       NOT NULL DEFAULT 8,
            `show_images`   tinyint(1)    NOT NULL DEFAULT 1,
            `attribution`   varchar(200)  DEFAULT NULL,
            `active`        tinyint(1)    NOT NULL DEFAULT 1,
            `sort_order`    int(11)       NOT NULL DEFAULT 0,
            `data_json`     mediumtext    DEFAULT NULL COMMENT 'Weer: laatste weerdata. Website (provider web): instellingen',
            `last_fetch_at` datetime      DEFAULT NULL,
            `last_ok_at`    datetime      DEFAULT NULL,
            `last_status`   varchar(255)  DEFAULT NULL,
            `created_at`    datetime      NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_type_active` (`type`, `active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `infoscreen_items` (
            `id`           int(11)      NOT NULL AUTO_INCREMENT,
            `source_id`    int(11)      NOT NULL,
            `item_key`     char(40)     NOT NULL,
            `title`        varchar(300) NOT NULL,
            `summary`      text         DEFAULT NULL,
            `image_url`    varchar(800) DEFAULT NULL,
            `link`         varchar(800) DEFAULT NULL,
            `published_at` datetime     DEFAULT NULL,
            `fetched_at`   datetime     NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_source_item` (`source_id`, `item_key`),
            KEY `idx_source_published` (`source_id`, `published_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `infoscreen_settings` (
            `setting_key`   varchar(50)  NOT NULL,
            `setting_value` text         DEFAULT NULL,
            `updated_at`    datetime     DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Agenda (2.5.0): events from iCal sources, already expanded (repeating appointments)
        $db->exec("CREATE TABLE IF NOT EXISTS `infoscreen_events` (
            `id`         int(11)      NOT NULL AUTO_INCREMENT,
            `source_id`  int(11)      NOT NULL,
            `event_key`  char(40)     NOT NULL,
            `title`      varchar(300) NOT NULL,
            `place`      varchar(200) DEFAULT NULL,
            `starts_at`  datetime     NOT NULL,
            `ends_at`    datetime     DEFAULT NULL,
            `all_day`    tinyint(1)   NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_source_event` (`source_id`, `event_key`),
            KEY `idx_starts` (`starts_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Koppelingen (2.5.0): keys for the Berichten-API and Teams outgoing webhooks
        $db->exec("CREATE TABLE IF NOT EXISTS `infoscreen_api_keys` (
            `id`           int(11)      NOT NULL AUTO_INCREMENT,
            `type`         enum('api','teams') NOT NULL DEFAULT 'api',
            `name`         varchar(100) NOT NULL,
            `key_hash`     char(64)     DEFAULT NULL COMMENT 'SHA-256 van de API-sleutel (de sleutel zelf wordt niet bewaard)',
            `key_hint`     varchar(12)  DEFAULT NULL COMMENT 'Laatste tekens van de sleutel, om hem te herkennen',
            `secret_enc`   text         DEFAULT NULL COMMENT 'Teams-beveiligingstoken, versleuteld',
            `location`     varchar(100) DEFAULT NULL COMMENT 'Verouderd sinds 2.6.0, zie locations',
            `locations`    text         DEFAULT NULL COMMENT 'Standaardlocaties (JSON-lijst), NULL = alle locaties',
            `default_days` int(11)      NOT NULL DEFAULT 7,
            `color`        varchar(20)  NOT NULL DEFAULT 'blauw',
            `active`       tinyint(1)   NOT NULL DEFAULT 1,
            `window_start` datetime     DEFAULT NULL,
            `window_count` int(11)      NOT NULL DEFAULT 0,
            `last_used_at` datetime     DEFAULT NULL,
            `created_by`   int(11)      DEFAULT NULL,
            `created_at`   datetime     NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_key_hash` (`key_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 2.4.0 → 2.5.0: agenda sources and messages from other systems
        $type = $db->query("SHOW COLUMNS FROM `infoscreen_sources` LIKE 'type'")->fetch();
        if ($type && strpos($type['Type'], 'ical') === false) {
            $db->exec("ALTER TABLE `infoscreen_sources` MODIFY `type` ENUM('rss','weather','ical') NOT NULL");
        }
        foreach ([
            'api_key_id'  => "ALTER TABLE `infoscreen_messages` ADD COLUMN `api_key_id` int(11) DEFAULT NULL AFTER `source`",
            'external_id' => "ALTER TABLE `infoscreen_messages` ADD COLUMN `external_id` varchar(100) DEFAULT NULL AFTER `api_key_id`",
            'author'      => "ALTER TABLE `infoscreen_messages` ADD COLUMN `author` varchar(150) DEFAULT NULL AFTER `external_id`",
        ] as $column => $sql) {
            if (!$db->query("SHOW COLUMNS FROM `infoscreen_messages` LIKE '$column'")->fetch()) {
                $db->exec($sql);
            }
        }
        if (!$db->query("SHOW INDEX FROM `infoscreen_messages` WHERE Key_name = 'idx_api_external'")->fetch()) {
            $db->exec("ALTER TABLE `infoscreen_messages` ADD KEY `idx_api_external` (`api_key_id`, `external_id`)");
        }

        // 2.5 → 2.6.0: more than one location per message, source and connection (JSON list of names)
        foreach (['infoscreen_messages', 'infoscreen_sources', 'infoscreen_api_keys'] as $table) {
            if (!$db->query("SHOW COLUMNS FROM `$table` LIKE 'locations'")->fetch()) {
                $db->exec("ALTER TABLE `$table` ADD COLUMN `locations` text DEFAULT NULL AFTER `location`");
                $rows = $db->query("SELECT id, location FROM `$table` WHERE location IS NOT NULL AND location <> ''")->fetchAll(PDO::FETCH_ASSOC);
                $upd = $db->prepare("UPDATE `$table` SET locations = ? WHERE id = ?");
                foreach ($rows as $r) {
                    $upd->execute([json_encode([$r['location']], JSON_UNESCAPED_UNICODE), $r['id']]);
                }
            }
        }

        $col = $db->query("SHOW COLUMNS FROM `users` LIKE 'screen_mode'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `screen_mode` enum('slides','infoscreen','combined') NOT NULL DEFAULT 'slides' COMMENT 'Wat het scherm toont bij inactiviteit: Google Slides, Infoscherm of beide'");
        }
        $done = true;
    } catch (\Throwable $e) {
        error_log('infoscreen schema: ' . $e->getMessage());
        $done = false;
    }
    return $done;
}

// ─────────────────────────────────────────────────────────────────────────────
// Settings
// ─────────────────────────────────────────────────────────────────────────────

function pd_infoscreen_settings(PDO $db): array
{
    $settings = PD_IS_DEFAULT_SETTINGS;
    try {
        foreach ($db->query("SELECT setting_key, setting_value FROM infoscreen_settings") as $row) {
            if (array_key_exists($row['setting_key'], $settings)) {
                $settings[$row['setting_key']] = (string)$row['setting_value'];
            }
        }
    } catch (\Throwable $e) {
        // table missing: defaults
    }
    return $settings;
}

function pd_infoscreen_save_settings(PDO $db, array $input): array
{
    $clean = [];
    $clean['title']          = mb_substr(trim((string)($input['title'] ?? '')), 0, 80);
    $clean['slide_seconds']  = (string)max(5, min(120, (int)($input['slide_seconds'] ?? 12)));
    $clean['news_slides']    = !empty($input['news_slides']) ? '1' : '0';
    $clean['news_slide_max'] = (string)max(0, min(20, (int)($input['news_slide_max'] ?? 4)));
    $clean['weather_slide']  = !empty($input['weather_slide']) ? '1' : '0';
    $clean['ticker']         = !empty($input['ticker']) ? '1' : '0';
    $clean['ticker_speed']   = (string)max(20, min(200, (int)($input['ticker_speed'] ?? 60)));
    $clean['sidebar']        = !empty($input['sidebar']) ? '1' : '0';
    $clean['theme']          = in_array($input['theme'] ?? '', ['donker', 'licht'], true) ? $input['theme'] : 'donker';
    $clean['show_clock']     = !empty($input['show_clock']) ? '1' : '0';
    $clean['agenda_sidebar'] = !empty($input['agenda_sidebar']) ? '1' : '0';
    $clean['agenda_slide']   = !empty($input['agenda_slide']) ? '1' : '0';
    $clean['agenda_days']    = (string)max(1, min(31, (int)($input['agenda_days'] ?? 7)));
    $clean['bhv_card']       = !empty($input['bhv_card']) ? '1' : '0';
    $clean['bhv_names']      = !empty($input['bhv_names']) ? '1' : '0';
    $clean['birthday_slide'] = !empty($input['birthday_slide']) ? '1' : '0';

    $stmt = $db->prepare("INSERT INTO infoscreen_settings (setting_key, setting_value) VALUES (?, ?)
                          ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($clean as $k => $v) {
        $stmt->execute([$k, $v]);
    }
    return $clean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Small helpers
// ─────────────────────────────────────────────────────────────────────────────

/** Location names of an item (JSON list); [] = all locations. */
function pd_infoscreen_locs(?string $json): array
{
    if ($json === null || $json === '') return [];
    $list = json_decode($json, true);
    return is_array($list) ? array_values(array_filter(array_map('strval', $list), fn($l) => $l !== '')) : [];
}

/** JSON for the locations column; null = all locations. */
function pd_infoscreen_locs_json(array $names): ?string
{
    $names = array_values(array_unique(array_filter(array_map(fn($n) => trim((string)$n), $names), fn($n) => $n !== '')));
    return $names ? json_encode($names, JSON_UNESCAPED_UNICODE) : null;
}

/** Does an item for $itemLocs show on a screen for $screenLocs? (empty = all, on either side) */
function pd_infoscreen_locs_match(array $itemLocs, array $screenLocs): bool
{
    return !$itemLocs || !$screenLocs || array_intersect($itemLocs, $screenLocs);
}

function pd_infoscreen_locs_label(array $names): string
{
    return $names ? implode(', ', $names) : 'Alle locaties';
}

/** Keep infoscreen items pointing at a location after it is renamed (called from locations_manage.php). */
function pd_infoscreen_rename_location(PDO $db, string $old, string $new): void
{
    if ($old === '' || $old === $new || !pd_infoscreen_ensure_schema($db)) return;
    foreach (['infoscreen_messages', 'infoscreen_sources', 'infoscreen_api_keys'] as $table) {
        $rows = $db->query("SELECT id, locations FROM `$table` WHERE locations IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        $upd = $db->prepare("UPDATE `$table` SET locations = ? WHERE id = ?");
        foreach ($rows as $r) {
            $list = pd_infoscreen_locs($r['locations']);
            if (in_array($old, $list, true)) {
                $upd->execute([pd_infoscreen_locs_json(array_map(fn($l) => $l === $old ? $new : $l, $list)), $r['id']]);
            }
        }
    }
}

/**
 * Location picker (2.6.0): an "Alle locaties" checkbox plus one checkbox per location.
 * $allowAll = false (Berichtenredacteur) hides "Alle locaties".
 * Posts locations_all=1 and/or locations[]; read back with pd_infoscreen_locs_from_post().
 */
function pd_infoscreen_location_picker(array $locations, array $selected, bool $allowAll = true, string $help = ''): string
{
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $isAll = $allowAll && $selected === [];
    $out = '<div class="loc-picker" data-loc-picker>';
    if ($allowAll) {
        $out .= '<label class="check loc-all"><input type="checkbox" name="locations_all" value="1"' . ($isAll ? ' checked' : '')
              . '> <strong>🌐 Alle locaties</strong></label>';
    }
    $out .= '<div class="loc-list">';
    foreach ($locations as $loc) {
        $on = !$isAll && in_array($loc, $selected, true);
        $out .= '<label class="check"><input type="checkbox" name="locations[]" value="' . $h($loc) . '"' . ($on ? ' checked' : '')
              . ($isAll ? ' disabled' : '') . '> 📍 ' . $h($loc) . '</label>';
    }
    if (!$locations) $out .= '<div class="help">Er zijn geen locaties beschikbaar.</div>';
    $out .= '</div>';
    if ($help !== '') $out .= '<div class="help">' . $help . '</div>';
    $out .= '</div>';
    return $out;
}

/** Character counter under the message text (2.6.1): advice, the screen makes long text smaller itself. */
function pd_infoscreen_text_counter_js(): string
{
    return <<<'JS'
<script>
document.querySelectorAll('textarea[name="body"]').forEach(function (ta) {
    var out = document.createElement('div');
    out.className = 'help';
    ta.insertAdjacentElement('afterend', out);
    var form = ta.form;
    function hasImage() {
        var file = form.querySelector('input[name="image"]');
        var cur = form.querySelector('.current-img');
        var rm = form.querySelector('input[name="remove_image"]');
        return (file && file.files.length > 0) || (cur && !(rm && rm.checked));
    }
    function update() {
        var n = ta.value.length, advice = hasImage() ? 250 : 450;
        out.textContent = n + ' tekens. Advies: ' + (hasImage() ? 'met afbeelding' : 'zonder afbeelding') + ' tot ongeveer ' + advice +
            ' tekens. Past het niet, dan maakt het scherm de tekst vanzelf kleiner.';
        out.style.color = n > advice ? '#b45309' : '';
        out.style.fontWeight = n > advice ? '600' : '';
    }
    ta.addEventListener('input', update);
    form.addEventListener('change', update);
    update();
});
</script>
JS;
}

/** Script for the picker: "Alle locaties" switches the single locations off. Include once per page. */
function pd_infoscreen_location_picker_js(): string
{
    return <<<'JS'
<script>
document.querySelectorAll('[data-loc-picker]').forEach(function (p) {
    var all = p.querySelector('input[name="locations_all"]');
    if (!all) return;
    all.addEventListener('change', function () {
        p.querySelectorAll('input[name="locations[]"]').forEach(function (c) { c.disabled = all.checked; if (all.checked) c.checked = false; });
    });
});
</script>
JS;
}

/**
 * Read the picker from $_POST. Returns null for "Alle locaties" (or nothing ticked, when allowed),
 * otherwise the JSON list of the ticked locations that are in $allowed.
 * With $allowAll = false an empty selection returns false (= invalid).
 */
function pd_infoscreen_locs_from_post(array $allowed, bool $allowAll = true)
{
    if ($allowAll && !empty($_POST['locations_all'])) return null;
    $picked = array_values(array_intersect($allowed, array_map('strval', (array)($_POST['locations'] ?? []))));
    if (!$picked) return $allowAll ? null : false;
    return pd_infoscreen_locs_json($picked);
}

/**
 * Who may place messages for which locations (2.6.0).
 * - SuperAdmin, and admins with the right "Infoscherm beheren" ($canManage): all locations.
 * - A user with the feature "infoscreenEditor" (Berichtenredacteur): only the locations set for
 *   that user (features.locations); none set = nothing.
 * Returns ['all' => bool, 'locations' => [...]] or null (no access).
 */
function pd_infoscreen_editor_scope(PDO $db, int $userId, bool $canManage): ?array
{
    if ($canManage) {
        return ['all' => true, 'locations' => []];
    }
    $stmt = $db->prepare("SELECT features FROM users WHERE id = ? AND active = 1");
    $stmt->execute([$userId]);
    $features = json_decode((string)$stmt->fetchColumn(), true);
    if (!is_array($features) || empty($features['infoscreenEditor'])) {
        return null;
    }
    $locs = is_array($features['locations'] ?? null) ? $features['locations'] : [];
    $known = $db->query("SELECT location_name FROM locations")->fetchAll(PDO::FETCH_COLUMN);
    return ['all' => false, 'locations' => array_values(array_intersect(array_map('strval', $locs), $known))];
}

/** May this scope change a message for $msgLocs? A redacteur only for messages within the own locations. */
function pd_infoscreen_scope_allows(array $scope, array $msgLocs): bool
{
    if ($scope['all']) return true;
    return $msgLocs && $scope['locations'] && !array_diff($msgLocs, $scope['locations']);
}

function pd_infoscreen_csrf_token(): string
{
    if (empty($_SESSION['pd_is_csrf'])) {
        $_SESSION['pd_is_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['pd_is_csrf'];
}

function pd_infoscreen_csrf_ok(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['pd_is_csrf']) && hash_equals($_SESSION['pd_is_csrf'], $token);
}

/** Plain text from (possibly HTML) feed text, shortened to $max characters. */
function pd_infoscreen_plain(?string $html, int $max = 0): string
{
    $text = (string)$html;
    $text = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', ' ', $text);
    $text = preg_replace('~<br\s*/?>|</p>~i', ' ', $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if ($max > 0 && mb_strlen($text) > $max) {
        $cut = mb_substr($text, 0, $max);
        $space = mb_strrpos($cut, ' ');
        $text = rtrim(mb_substr($cut, 0, $space !== false && $space > $max * 0.6 ? $space : $max), " ,.;:-") . '…';
    }
    return $text;
}

/** Only absolute http(s) URLs, max 800 characters. */
function pd_infoscreen_clean_url(?string $url): ?string
{
    $url = trim(html_entity_decode((string)$url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '' || strlen($url) > 800) {
        return null;
    }
    if (str_starts_with($url, '//')) {
        $url = 'https:' . $url;
    }
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true) || !parse_url($url, PHP_URL_HOST)) {
        return null;
    }
    return $url;
}

/** The host's IPv4 addresses when they are all public (no loopback, private, link-local); else []. */
function pd_infoscreen_public_ips(string $host): array
{
    $host = trim($host, '[]');
    $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return [];
        }
    }
    return $ips;
}

function pd_infoscreen_public_host(string $host): bool
{
    return pd_infoscreen_public_ips($host) !== [];
}

/**
 * GET a URL with a short timeout and a size cap. Follows up to 3 redirects, each to a
 * public host. Returns ['ok' => bool, 'status' => int, 'body' => string, 'error' => string].
 */
function pd_infoscreen_http_get(string $url, int $timeout = PD_IS_HTTP_TIMEOUT, int $maxBytes = PD_IS_MAX_BYTES, ?string $sinkPath = null): array
{
    if (function_exists('pd_infoscreen_http_get_override')) {
        $res = pd_infoscreen_http_get_override($url); // tests
        if ($sinkPath !== null && $res['ok']) {
            file_put_contents($sinkPath, $res['body']);
            $res['body'] = '';
        }
        return $res;
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'cURL is niet beschikbaar op deze server'];
    }
    $version = defined('PD_CURRENT_VERSION') ? PD_CURRENT_VERSION : 'dev';
    for ($hop = 0; $hop < 4; $hop++) {
        $clean = pd_infoscreen_clean_url($url);
        if ($clean === null) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Ongeldige URL'];
        }
        $host = (string)parse_url($clean, PHP_URL_HOST);
        $ips = pd_infoscreen_public_ips($host);
        if (!$ips) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Adres niet bereikbaar of niet toegestaan (alleen openbare websites)'];
        }
        $port = (int)(parse_url($clean, PHP_URL_PORT) ?: (str_starts_with($clean, 'https:') ? 443 : 80));
        $body = '';
        $size = 0;
        $sink = $sinkPath !== null ? fopen($sinkPath, 'wb') : null;
        if ($sinkPath !== null && !$sink) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Tijdelijk bestand kon niet worden aangemaakt'];
        }
        $ch = curl_init($clean);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_USERAGENT      => 'PeopleDisplay-Infoscherm/' . $version . ' (+https://peopledisplay.nl)',
            CURLOPT_HTTPHEADER     => ['Accept: application/rss+xml, application/atom+xml, application/xml, text/xml, application/json;q=0.9, */*;q=0.5'],
            CURLOPT_ENCODING       => '',
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            // Connect to the address that was checked above (no second DNS lookup)
            CURLOPT_RESOLVE        => filter_var($host, FILTER_VALIDATE_IP) ? [] : [$host . ':' . $port . ':' . $ips[0]],
            // Large downloads (agendas) go to a file instead of memory
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$body, &$size, $sink, $maxBytes) {
                $size += strlen($chunk);
                if ($size > $maxBytes) return 0;
                if ($sink) return fwrite($sink, $chunk);
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $redirect = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $err = curl_error($ch);
        curl_close($ch);
        if ($sink) fclose($sink);

        if ($status >= 300 && $status < 400 && $redirect !== '') {
            $url = $redirect;
            continue;
        }
        if ($ok === false && $size > $maxBytes) {
            return ['ok' => false, 'status' => $status, 'body' => '', 'error' => 'Bestand is te groot (meer dan ' . round($maxBytes / 1048576) . ' MB)'];
        }
        if ($ok === false) {
            return ['ok' => false, 'status' => $status, 'body' => '', 'error' => 'Verbinding mislukt: ' . $err];
        }
        if ($status !== 200) {
            return ['ok' => false, 'status' => $status, 'body' => '', 'error' => 'Server antwoordde met HTTP ' . $status];
        }
        return ['ok' => true, 'status' => $status, 'body' => $body, 'error' => ''];
    }
    return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Te veel doorverwijzingen'];
}

// ─────────────────────────────────────────────────────────────────────────────
// News feeds (RSS 2.0, RSS 1.0/RDF, Atom)
// ─────────────────────────────────────────────────────────────────────────────

/** Attribute without namespace, also on elements reached through children($ns). */
function pd_infoscreen_attr(SimpleXMLElement $node, string $name): string
{
    $attrs = $node->attributes();
    return $attrs !== null && isset($attrs[$name]) ? (string)$attrs[$name] : '';
}

/**
 * Parse a feed into items: [['key','title','summary','image','link','published'], …].
 * Throws RuntimeException with a Dutch message when the text is not a usable feed.
 */
function pd_infoscreen_parse_feed(string $xml): array
{
    $xml = ltrim($xml, "\xEF\xBB\xBF \t\r\n");
    if ($xml === '' || $xml[0] !== '<') {
        throw new RuntimeException('Dit is geen RSS- of Atom-feed (geen XML ontvangen)');
    }
    $prev = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA | LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if ($doc === false) {
        throw new RuntimeException('De feed kon niet gelezen worden (ongeldige XML)');
    }

    $root = strtolower($doc->getName());
    $entries = [];
    if ($root === 'rss') {
        foreach ($doc->channel->item as $it) $entries[] = $it;
    } elseif ($root === 'feed') {
        foreach ($doc->children('http://www.w3.org/2005/Atom')->entry as $it) $entries[] = $it;
        if (!$entries) foreach ($doc->entry as $it) $entries[] = $it;
    } elseif ($root === 'rdf') {
        foreach ($doc->children('http://purl.org/rss/1.0/')->item as $it) $entries[] = $it;
        if (!$entries) foreach ($doc->item as $it) $entries[] = $it;
    } else {
        throw new RuntimeException('Dit is geen RSS- of Atom-feed');
    }

    $items = [];
    foreach ($entries as $e) {
        $isAtom = $root === 'feed';
        $atom = $isAtom ? $e->children('http://www.w3.org/2005/Atom') : null;
        $f = fn(string $name) => $isAtom && isset($atom->$name) ? $atom->$name : ($e->$name ?? null);

        $title = pd_infoscreen_plain((string)$f('title'), 300);
        if ($title === '') continue;

        // Link
        $link = null;
        if ($isAtom) {
            foreach (($atom->link ?? []) as $l) {
                $rel = pd_infoscreen_attr($l, 'rel');
                if ($rel === '' || $rel === 'alternate') { $link = pd_infoscreen_attr($l, 'href'); break; }
            }
        } else {
            $link = (string)$e->link;
        }

        // Text
        $content = $e->children('http://purl.org/rss/1.0/modules/content/');
        $rawHtml = (string)($isAtom ? ($atom->summary ?? $atom->content ?? '') : ($e->description ?? ''));
        if ($rawHtml === '' && isset($content->encoded)) $rawHtml = (string)$content->encoded;
        $fullHtml = $rawHtml . ' ' . (isset($content->encoded) ? (string)$content->encoded : '') . ($isAtom ? ' ' . (string)($atom->content ?? '') : '');
        $summary = pd_infoscreen_plain($rawHtml, 320);
        if ($summary === $title) $summary = '';

        // Date
        $dateRaw = (string)($isAtom ? ($atom->updated ?? $atom->published ?? '') : ($e->pubDate ?? ''));
        if ($dateRaw === '') {
            $dc = $e->children('http://purl.org/dc/elements/1.1/');
            $dateRaw = (string)($dc->date ?? '');
        }
        $ts = $dateRaw !== '' ? strtotime($dateRaw) : false;

        // Image
        $image = pd_infoscreen_find_image($e, $isAtom ? $atom : null, $fullHtml);

        $guid = (string)($isAtom ? ($atom->id ?? '') : ($e->guid ?? ''));
        $key = sha1($guid !== '' ? $guid : ($link . '|' . $title));

        $items[] = [
            'key'       => $key,
            'title'     => $title,
            'summary'   => $summary,
            'image'     => $image,
            'link'      => pd_infoscreen_clean_url($link),
            'published' => $ts ? date('Y-m-d H:i:s', $ts) : null,
        ];
    }
    return $items;
}

function pd_infoscreen_find_image(SimpleXMLElement $e, ?SimpleXMLElement $atom, string $html): ?string
{
    // <enclosure url="" type="image/jpeg"> (RSS)
    foreach (($e->enclosure ?? []) as $enc) {
        $type = strtolower(pd_infoscreen_attr($enc, 'type'));
        $url = pd_infoscreen_attr($enc, 'url');
        if ($url !== '' && ($type === '' ? preg_match('/\.(jpe?g|png|gif|webp)(\?|$)/i', $url) : str_starts_with($type, 'image/'))) {
            if ($u = pd_infoscreen_clean_url($url)) return $u;
        }
    }
    // <link rel="enclosure" type="image/..."> (Atom)
    if ($atom) {
        foreach (($atom->link ?? []) as $l) {
            if (pd_infoscreen_attr($l, 'rel') === 'enclosure' && str_starts_with(strtolower(pd_infoscreen_attr($l, 'type')), 'image/')) {
                if ($u = pd_infoscreen_clean_url(pd_infoscreen_attr($l, 'href'))) return $u;
            }
        }
    }
    // Media RSS: media:content / media:thumbnail (also inside media:group); pick the widest
    $media = $e->children('http://search.yahoo.com/mrss/');
    $candidates = [];
    $collect = function ($node) use (&$candidates) {
        foreach (($node->content ?? []) as $c) {
            $medium = strtolower(pd_infoscreen_attr($c, 'medium'));
            $type = strtolower(pd_infoscreen_attr($c, 'type'));
            $url = pd_infoscreen_attr($c, 'url');
            if ($url !== '' && ($medium === 'image' || str_starts_with($type, 'image/') || ($medium === '' && $type === '' && preg_match('/\.(jpe?g|png|gif|webp)(\?|$)/i', $url)))) {
                $candidates[] = [(int)pd_infoscreen_attr($c, 'width'), $url];
            }
        }
        foreach (($node->thumbnail ?? []) as $t) {
            if (pd_infoscreen_attr($t, 'url') !== '') $candidates[] = [(int)pd_infoscreen_attr($t, 'width'), pd_infoscreen_attr($t, 'url')];
        }
    };
    $collect($media);
    foreach (($media->group ?? []) as $g) $collect($g);
    usort($candidates, fn($a, $b) => $b[0] <=> $a[0]);
    foreach ($candidates as [, $url]) {
        if ($u = pd_infoscreen_clean_url($url)) return $u;
    }
    // First <img src> in the HTML text
    if (preg_match('~<img\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']~i', $html, $m)) {
        if ($u = pd_infoscreen_clean_url($m[1])) return $u;
    }
    return null;
}

function pd_infoscreen_fetch_rss(PDO $db, array $source): array
{
    $res = pd_infoscreen_http_get((string)$source['url']);
    if (!$res['ok']) {
        throw new RuntimeException($res['error']);
    }
    $items = pd_infoscreen_parse_feed($res['body']);
    if (!$items) {
        throw new RuntimeException('De feed bevat geen berichten');
    }
    return pd_infoscreen_store_items($db, $source, $items);
}

/** Store fetched news items (feed or website) and keep the newest PD_IS_ITEMS_KEEP. */
function pd_infoscreen_store_items(PDO $db, array $source, array $items): array
{
    $items = array_slice($items, 0, 50);
    // Items without a date keep the order of the page/feed: the first is the newest
    $t = time();
    foreach ($items as $i => $it) {
        if (empty($it['published'])) $items[$i]['published'] = date('Y-m-d H:i:s', $t - $i);
    }
    $ins = $db->prepare("INSERT INTO infoscreen_items (source_id, item_key, title, summary, image_url, link, published_at, fetched_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE title = VALUES(title), summary = VALUES(summary), image_url = VALUES(image_url),
                                                 link = VALUES(link), published_at = COALESCE(VALUES(published_at), published_at), fetched_at = NOW()");
    foreach ($items as $it) {
        $ins->execute([$source['id'], $it['key'], $it['title'], $it['summary'], $it['image'], $it['link'], $it['published']]);
    }
    // Keep the newest PD_IS_ITEMS_KEEP items
    $ids = $db->prepare("SELECT id FROM infoscreen_items WHERE source_id = ? ORDER BY published_at DESC, id DESC LIMIT " . PD_IS_ITEMS_KEEP);
    $ids->execute([$source['id']]);
    $keep = array_map('intval', $ids->fetchAll(PDO::FETCH_COLUMN));
    if ($keep) {
        $db->prepare("DELETE FROM infoscreen_items WHERE source_id = ? AND id NOT IN (" . implode(',', $keep) . ")")->execute([$source['id']]);
    }
    $withImage = count(array_filter($items, fn($i) => $i['image']));
    return ['message' => count($items) . ' berichten opgehaald, ' . $withImage . ' met afbeelding', 'count' => count($items)];
}

// ─────────────────────────────────────────────────────────────────────────────
// Weather
// ─────────────────────────────────────────────────────────────────────────────

/** Weather categories used for icons and backgrounds. */
const PD_IS_WEATHER_TEXT = [
    'clear'   => 'Zonnig',
    'partly'  => 'Half bewolkt',
    'cloudy'  => 'Bewolkt',
    'fog'     => 'Mist',
    'drizzle' => 'Motregen',
    'rain'    => 'Regen',
    'showers' => 'Buien',
    'thunder' => 'Onweer',
    'snow'    => 'Sneeuw',
    'sleet'   => 'Natte sneeuw',
];

/** WMO weather code (Open-Meteo) → [category, Dutch text]. */
function pd_infoscreen_wmo(int $code): array
{
    $map = [
        0 => ['clear', 'Onbewolkt'], 1 => ['clear', 'Overwegend zonnig'], 2 => ['partly', 'Half bewolkt'], 3 => ['cloudy', 'Bewolkt'],
        45 => ['fog', 'Mist'], 48 => ['fog', 'Mist met rijp'],
        51 => ['drizzle', 'Lichte motregen'], 53 => ['drizzle', 'Motregen'], 55 => ['drizzle', 'Dichte motregen'],
        56 => ['sleet', 'Ijzel'], 57 => ['sleet', 'Zware ijzel'],
        61 => ['rain', 'Lichte regen'], 63 => ['rain', 'Regen'], 65 => ['rain', 'Zware regen'],
        66 => ['sleet', 'Lichte ijzelregen'], 67 => ['sleet', 'IJzelregen'],
        71 => ['snow', 'Lichte sneeuw'], 73 => ['snow', 'Sneeuw'], 75 => ['snow', 'Zware sneeuw'], 77 => ['snow', 'Korrelsneeuw'],
        80 => ['showers', 'Lichte buien'], 81 => ['showers', 'Buien'], 82 => ['showers', 'Zware buien'],
        85 => ['snow', 'Sneeuwbuien'], 86 => ['snow', 'Zware sneeuwbuien'],
        95 => ['thunder', 'Onweer'], 96 => ['thunder', 'Onweer met hagel'], 99 => ['thunder', 'Zwaar onweer met hagel'],
    ];
    return $map[$code] ?? ['cloudy', 'Bewolkt'];
}

/** Buienradar: category from the Dutch description, falling back to the icon letter. */
function pd_infoscreen_buienradar_category(string $description, string $iconUrl): string
{
    $d = mb_strtolower($description);
    $rules = [
        'thunder' => ['onweer', 'bliksem'],
        'sleet'   => ['natte sneeuw', 'hagel', 'ijzel'],
        'snow'    => ['sneeuw'],
        'showers' => ['bui'],
        'drizzle' => ['motregen'],
        'rain'    => ['regen'],
        'fog'     => ['mist', 'nevel'],
        'clear'   => ['zonnig', 'onbewolkt', 'helder', 'zon '],
        'partly'  => ['opklaring', 'half bewolkt', 'licht bewolkt', 'afwisselend', 'wisselend'],
        'cloudy'  => ['bewolkt', 'betrokken'],
    ];
    foreach ($rules as $cat => $words) {
        foreach ($words as $w) {
            if ($d !== '' && str_contains($d . ' ', $w)) return $cat;
        }
    }
    // Icon letter: …/weather/30x30/b.png (night icons use a double letter, e.g. cc.png)
    if (preg_match('~/([a-z])\1?\.png~i', $iconUrl, $m)) {
        $letters = [
            'a' => 'clear', 'b' => 'partly', 'j' => 'partly', 'o' => 'partly', 'r' => 'partly',
            'c' => 'cloudy', 'p' => 'cloudy',
            'd' => 'fog', 'n' => 'fog',
            'f' => 'showers', 'h' => 'showers', 'k' => 'showers',
            'l' => 'rain', 'm' => 'drizzle', 'q' => 'rain',
            'g' => 'thunder', 's' => 'thunder',
            'i' => 'snow', 't' => 'snow', 'u' => 'snow', 'v' => 'snow', 'w' => 'sleet',
        ];
        return $letters[strtolower($m[1])] ?? 'cloudy';
    }
    return 'cloudy';
}

function pd_infoscreen_num($v): ?float
{
    if ($v === null || $v === '' || !is_numeric($v)) return null;
    return (float)$v;
}

/** km/h → Beaufort. */
function pd_infoscreen_beaufort(?float $kmh): ?int
{
    if ($kmh === null) return null;
    foreach ([1, 6, 12, 20, 29, 39, 50, 62, 75, 89, 103, 118] as $bft => $limit) {
        if ($kmh < $limit) return $bft;
    }
    return 12;
}

function pd_infoscreen_compass(?float $deg): ?string
{
    if ($deg === null) return null;
    $dirs = ['N', 'NNO', 'NO', 'ONO', 'O', 'OZO', 'ZO', 'ZZO', 'Z', 'ZZW', 'ZW', 'WZW', 'W', 'WNW', 'NW', 'NNW'];
    return $dirs[(int)round(fmod($deg + 360, 360) / 22.5) % 16];
}

function pd_infoscreen_distance(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/** Normalise the Buienradar JSON feed for a position. */
function pd_infoscreen_parse_buienradar(string $json, float $lat, float $lon): array
{
    $data = json_decode($json, true);
    if (!is_array($data) || empty($data['actual']['stationmeasurements'])) {
        throw new RuntimeException('Onverwacht antwoord van Buienradar');
    }
    $best = null;
    $bestDist = INF;
    foreach ($data['actual']['stationmeasurements'] as $st) {
        $sLat = pd_infoscreen_num($st['lat'] ?? null);
        $sLon = pd_infoscreen_num($st['lon'] ?? null);
        if ($sLat === null || $sLon === null || pd_infoscreen_num($st['temperature'] ?? null) === null) continue;
        $dist = pd_infoscreen_distance($lat, $lon, $sLat, $sLon);
        if ($dist < $bestDist) { $bestDist = $dist; $best = $st; }
    }
    if (!$best) {
        throw new RuntimeException('Geen weerstation met metingen gevonden');
    }
    $desc = (string)($best['weatherdescription'] ?? '');
    $icon = (string)($best['iconurl'] ?? '');
    $cat = pd_infoscreen_buienradar_category($desc, $icon);
    $isDay = !preg_match('~/([a-z])\1\.png~i', $icon);

    $days = [];
    foreach (array_slice($data['forecast']['fivedayforecast'] ?? [], 0, 5) as $d) {
        $dDesc = (string)($d['weatherdescription'] ?? '');
        $min = pd_infoscreen_num($d['mintemperatureMax'] ?? $d['mintemperature'] ?? null);
        $max = pd_infoscreen_num($d['maxtemperatureMax'] ?? $d['maxtemperature'] ?? null);
        $ts = strtotime((string)($d['day'] ?? ''));
        $days[] = [
            'date'        => $ts ? date('Y-m-d', $ts) : null,
            'min'         => $min !== null ? (int)round($min) : null,
            'max'         => $max !== null ? (int)round($max) : null,
            'rain_chance' => pd_infoscreen_num($d['rainChance'] ?? null) !== null ? (int)$d['rainChance'] : null,
            'cat'         => pd_infoscreen_buienradar_category($dDesc, (string)($d['iconurl'] ?? '')),
            'desc'        => $dDesc,
        ];
    }

    $report = $data['forecast']['weatherreport'] ?? [];
    $short = $data['forecast']['shortterm']['forecast'] ?? '';
    return [
        'provider' => 'buienradar',
        'station'  => preg_replace('/^Meetstation\s+/i', '', (string)($best['stationname'] ?? '')),
        'current'  => [
            'temp'     => round((float)$best['temperature'], 1),
            'feels'    => pd_infoscreen_num($best['feeltemperature'] ?? null),
            'desc'     => $desc !== '' ? $desc : PD_IS_WEATHER_TEXT[$cat],
            'cat'      => $cat,
            'is_day'   => $isDay,
            'wind_bft' => pd_infoscreen_num($best['windspeedBft'] ?? null) !== null ? (int)$best['windspeedBft'] : null,
            'wind_dir' => $best['winddirection'] ?? null,
            'humidity' => pd_infoscreen_num($best['humidity'] ?? null) !== null ? (int)$best['humidity'] : null,
        ],
        'days'     => $days,
        'report'   => [
            'title'   => pd_infoscreen_plain((string)($report['title'] ?? ''), 120),
            'summary' => pd_infoscreen_plain((string)($report['summary'] ?? $short), 400),
        ],
    ];
}

/** Normalise an Open-Meteo forecast response. */
function pd_infoscreen_parse_openmeteo(string $json): array
{
    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['current']['temperature_2m'])) {
        $reason = is_array($data) && !empty($data['reason']) ? ': ' . $data['reason'] : '';
        throw new RuntimeException('Onverwacht antwoord van Open-Meteo' . $reason);
    }
    $c = $data['current'];
    [$cat, $desc] = pd_infoscreen_wmo((int)($c['weather_code'] ?? 3));
    $days = [];
    $daily = $data['daily'] ?? [];
    foreach (($daily['time'] ?? []) as $i => $date) {
        [$dCat, $dDesc] = pd_infoscreen_wmo((int)($daily['weather_code'][$i] ?? 3));
        $days[] = [
            'date'        => $date,
            'min'         => isset($daily['temperature_2m_min'][$i]) ? (int)round($daily['temperature_2m_min'][$i]) : null,
            'max'         => isset($daily['temperature_2m_max'][$i]) ? (int)round($daily['temperature_2m_max'][$i]) : null,
            'rain_chance' => isset($daily['precipitation_probability_max'][$i]) ? (int)$daily['precipitation_probability_max'][$i] : null,
            'cat'         => $dCat,
            'desc'        => $dDesc,
        ];
    }
    $wind = pd_infoscreen_num($c['wind_speed_10m'] ?? null);
    return [
        'provider' => 'open-meteo',
        'station'  => null,
        'current'  => [
            'temp'     => round((float)$c['temperature_2m'], 1),
            'feels'    => pd_infoscreen_num($c['apparent_temperature'] ?? null),
            'desc'     => $desc,
            'cat'      => $cat,
            'is_day'   => !isset($c['is_day']) || (int)$c['is_day'] === 1,
            'wind_bft' => pd_infoscreen_beaufort($wind),
            'wind_dir' => pd_infoscreen_compass(pd_infoscreen_num($c['wind_direction_10m'] ?? null)),
            'humidity' => isset($c['relative_humidity_2m']) ? (int)$c['relative_humidity_2m'] : null,
        ],
        'days'     => array_slice($days, 0, 5),
        'report'   => ['title' => '', 'summary' => ''],
    ];
}

function pd_infoscreen_weather_url(string $provider, float $lat, float $lon): string
{
    if ($provider === 'open-meteo') {
        return 'https://api.open-meteo.com/v1/forecast?' . http_build_query([
            'latitude'  => round($lat, 4),
            'longitude' => round($lon, 4),
            'current'   => 'temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m,wind_direction_10m,is_day',
            'daily'     => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
            'timezone'  => 'Europe/Amsterdam',
            'forecast_days' => 5,
        ]);
    }
    return 'https://data.buienradar.nl/2.0/feed/json';
}

function pd_infoscreen_fetch_weather(PDO $db, array $source): array
{
    $provider = (string)$source['provider'];
    if (!isset(PD_IS_WEATHER_PROVIDERS[$provider])) {
        throw new RuntimeException('Onbekende weerbron');
    }
    $lat = (float)$source['latitude'];
    $lon = (float)$source['longitude'];
    $res = pd_infoscreen_http_get(pd_infoscreen_weather_url($provider, $lat, $lon));
    if (!$res['ok']) {
        throw new RuntimeException($res['error']);
    }
    $weather = $provider === 'open-meteo'
        ? pd_infoscreen_parse_openmeteo($res['body'])
        : pd_infoscreen_parse_buienradar($res['body'], $lat, $lon);
    $weather['fetched_at'] = date('c');
    $db->prepare("UPDATE infoscreen_sources SET data_json = ? WHERE id = ?")
       ->execute([json_encode($weather, JSON_UNESCAPED_UNICODE), $source['id']]);
    $msg = 'Weer opgehaald: ' . $weather['current']['temp'] . '°C, ' . $weather['current']['desc'];
    if (!empty($weather['station'])) $msg .= ' (meetstation ' . $weather['station'] . ')';
    return ['message' => $msg, 'count' => 1];
}

// ─────────────────────────────────────────────────────────────────────────────
// Refresh
// ─────────────────────────────────────────────────────────────────────────────

/** Fetch one source now and record the outcome. Returns ['ok' => bool, 'message' => string]. */
function pd_infoscreen_fetch_source(PDO $db, array $source): array
{
    try {
        if ($source['type'] === 'ical') {
            require_once __DIR__ . '/infoscreen_ical.php';
            $result = pd_infoscreen_fetch_ical($db, $source);
        } elseif ($source['type'] === 'weather') {
            $result = pd_infoscreen_fetch_weather($db, $source);
        } elseif (($source['provider'] ?? '') === 'web') {
            require_once __DIR__ . '/infoscreen_web.php';
            $result = pd_infoscreen_fetch_web($db, $source);
        } else {
            $result = pd_infoscreen_fetch_rss($db, $source);
        }
        $db->prepare("UPDATE infoscreen_sources SET last_fetch_at = NOW(), last_ok_at = NOW(), last_status = ? WHERE id = ?")
           ->execute([mb_substr('OK: ' . $result['message'], 0, 255), $source['id']]);
        return ['ok' => true, 'message' => $result['message']];
    } catch (\Throwable $e) {
        $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Onverwachte fout';
        if (!$e instanceof RuntimeException) error_log('infoscreen fetch: ' . $e->getMessage());
        $db->prepare("UPDATE infoscreen_sources SET last_fetch_at = NOW(), last_status = ? WHERE id = ?")
           ->execute([mb_substr('Fout: ' . $msg, 0, 255), $source['id']]);
        return ['ok' => false, 'message' => $msg];
    }
}

/**
 * Fetch every active source that is due. One run at a time (named lock); stops after
 * $maxSeconds so a screen request or a web cron never hangs.
 */
function pd_infoscreen_refresh(PDO $db, bool $force = false, int $maxSeconds = 25): array
{
    if (!pd_infoscreen_ensure_schema($db)) {
        return ['ran' => false, 'results' => []];
    }
    $lock = (int)$db->query("SELECT GET_LOCK('pd_infoscreen_refresh', 0)")->fetchColumn();
    if ($lock !== 1) {
        return ['ran' => false, 'results' => []];
    }
    $results = [];
    $start = microtime(true);
    try {
        $sql = "SELECT * FROM infoscreen_sources WHERE active = 1";
        if (!$force) {
            $sql .= " AND (last_fetch_at IS NULL OR last_fetch_at < NOW() - INTERVAL " . PD_IS_FETCH_MINUTES . " MINUTE)";
        }
        $sql .= " ORDER BY last_fetch_at IS NOT NULL, last_fetch_at";
        foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $source) {
            if (microtime(true) - $start > $maxSeconds) break;
            $results[$source['id']] = pd_infoscreen_fetch_source($db, $source) + ['name' => $source['name']];
        }
    } finally {
        $db->query("SELECT RELEASE_LOCK('pd_infoscreen_refresh')");
    }
    return ['ran' => true, 'results' => $results];
}

/** True when at least one active source is due for a fetch. */
function pd_infoscreen_refresh_due(PDO $db): bool
{
    try {
        return (bool)$db->query("SELECT 1 FROM infoscreen_sources WHERE active = 1
            AND (last_fetch_at IS NULL OR last_fetch_at < NOW() - INTERVAL " . PD_IS_FETCH_MINUTES . " MINUTE) LIMIT 1")->fetchColumn();
    } catch (\Throwable $e) {
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Screen data
// ─────────────────────────────────────────────────────────────────────────────

/** Location names a user's screen is set to (empty array = all locations). */
function pd_infoscreen_user_locations(PDO $db, int $userId): array
{
    $stmt = $db->prepare("SELECT features FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $features = json_decode((string)$stmt->fetchColumn(), true);
    $locs = is_array($features['locations'] ?? null) ? $features['locations'] : [];
    return array_values(array_filter(array_map('strval', $locs), fn($l) => $l !== ''));
}

/** Everything a screen needs, already filtered for its locations. */
function pd_infoscreen_screen_data(PDO $db, array $locations, string $baseUrl = ''): array
{
    $settings = pd_infoscreen_settings($db);

    // Messages within their time window, for this screen's locations
    $stmt = $db->query("SELECT id, title, body, image_path, locations, starts_at, ends_at, duration_seconds, priority, color
        FROM infoscreen_messages
        WHERE active = 1 AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW())
        ORDER BY priority DESC, sort_order, id DESC LIMIT 300");
    $messages = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
        if (!pd_infoscreen_locs_match(pd_infoscreen_locs($m['locations']), $locations)) continue;
        if (count($messages) >= 50) break;
        $messages[] = [
            'id'       => (int)$m['id'],
            'title'    => $m['title'],
            'body'     => (string)$m['body'],
            'image'    => $m['image_path'] ? $baseUrl . '/' . ltrim($m['image_path'], '/') : null,
            'duration' => $m['duration_seconds'] ? (int)$m['duration_seconds'] : null,
            'priority' => (int)$m['priority'] === 1,
            'color'    => $m['color'],
        ];
    }

    // Sources
    $stmt = $db->query("SELECT * FROM infoscreen_sources WHERE active = 1 ORDER BY sort_order, id");
    $news = [];
    $weather = null;
    $calendars = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $srcLocs = pd_infoscreen_locs($s['locations']);
        if (!pd_infoscreen_locs_match($srcLocs, $locations)) continue;
        if ($s['type'] === 'ical') {
            $calendars[(int)$s['id']] = $s['attribution'] !== null && $s['attribution'] !== '' ? $s['attribution'] : $s['name'];
            continue;
        }
        if ($s['type'] === 'weather') {
            // A screen set to certain locations prefers the weather place of its location; a screen
            // for all locations prefers the place set for "all locations".
            $data = json_decode((string)$s['data_json'], true);
            if (!is_array($data)) continue;
            $specific = $srcLocs !== [];
            $better = $weather !== null
                && ($locations ? ($specific && !$weather['_specific']) : (!$specific && $weather['_specific']));
            if ($weather === null || $better) {
                $weather = $data + [
                    'place'       => $s['name'],
                    'attribution' => PD_IS_WEATHER_PROVIDERS[$s['provider']]['attribution'] ?? '',
                    '_specific'   => $specific,
                ];
            }
            continue;
        }
        $items = $db->prepare("SELECT title, summary, image_url, published_at FROM infoscreen_items
            WHERE source_id = ? ORDER BY published_at DESC, id DESC LIMIT " . max(1, min(30, (int)$s['max_items'])));
        $items->execute([$s['id']]);
        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $it) {
            $news[] = [
                'title'     => $it['title'],
                'summary'   => (string)$it['summary'],
                'image'     => (int)$s['show_images'] === 1 ? $it['image_url'] : null,
                'source'    => $s['attribution'] !== null && $s['attribution'] !== '' ? $s['attribution'] : $s['name'],
                'published' => $it['published_at'],
            ];
        }
    }
    if ($weather) unset($weather['_specific']);

    // Agenda: what is still going on or starts within the set number of days (Amsterdam time)
    $agenda = [];
    if ($calendars) {
        $tz = new DateTimeZone('Europe/Amsterdam');
        $now = new DateTimeImmutable('now', $tz);
        $until = $now->setTime(0, 0)->modify('+' . max(1, (int)$settings['agenda_days']) . ' days');
        $ids = implode(',', array_keys($calendars));
        $ev = $db->prepare("SELECT source_id, title, place, starts_at, ends_at, all_day FROM infoscreen_events
            WHERE source_id IN ($ids) AND starts_at < ? AND (ends_at > ? OR (ends_at IS NULL AND starts_at >= ?))
            ORDER BY starts_at, title LIMIT 60");
        $nowStr = $now->format('Y-m-d H:i:s');
        $ev->execute([$until->format('Y-m-d H:i:s'), $nowStr, $now->setTime(0, 0)->format('Y-m-d H:i:s')]);
        foreach ($ev->fetchAll(PDO::FETCH_ASSOC) as $e) {
            $agenda[] = [
                'title'    => $e['title'],
                'place'    => $e['place'],
                'start'    => $e['starts_at'],
                'end'      => $e['ends_at'],
                'all_day'  => (int)$e['all_day'] === 1,
                'calendar' => count($calendars) > 1 ? $calendars[(int)$e['source_id']] : null,
            ];
        }
    }

    return [
        'settings' => [
            'title'          => $settings['title'],
            'slide_seconds'  => (int)$settings['slide_seconds'],
            'news_slides'    => $settings['news_slides'] === '1',
            'news_slide_max' => (int)$settings['news_slide_max'],
            'weather_slide'  => $settings['weather_slide'] === '1',
            'ticker'         => $settings['ticker'] === '1',
            'ticker_speed'   => (int)$settings['ticker_speed'],
            'sidebar'        => $settings['sidebar'] === '1',
            'theme'          => $settings['theme'],
            'show_clock'     => $settings['show_clock'] === '1',
            'agenda_sidebar' => $settings['agenda_sidebar'] === '1',
            'agenda_slide'   => $settings['agenda_slide'] === '1',
            'agenda_days'    => (int)$settings['agenda_days'],
            'bhv_card'       => $settings['bhv_card'] === '1',
            'bhv_names'      => $settings['bhv_names'] === '1',
            'birthday_slide' => $settings['birthday_slide'] === '1',
        ],
        'messages'  => $messages,
        'agenda'    => $agenda,
        'news'      => $news,
        'weather'   => $weather,
        'bhv'       => $settings['bhv_card'] === '1'
            ? pd_infoscreen_bhv_present($db, $locations, $settings['bhv_names'] === '1')
            : null,
        'birthdays' => $settings['birthday_slide'] === '1' ? pd_infoscreen_birthdays($db, $locations) : [],
    ];
}

/** Short display name: first name + initial of the surname ("Eva S."). */
function pd_infoscreen_short_name(array $emp): string
{
    $first = trim((string)($emp['voornaam'] ?? ''));
    $last  = trim((string)($emp['achternaam'] ?? ''));
    if ($first === '') {
        $parts = preg_split('/\s+/', trim((string)($emp['naam'] ?? '')));
        $first = (string)array_shift($parts);
        $last  = (string)end($parts);
    }
    if ($first === '') return '';
    // surname may start with a prefix ("van der Berg"): use the first capital
    $initial = preg_match('/\p{Lu}/u', $last, $m) ? $m[0] : mb_strtoupper(mb_substr($last, 0, 1));
    return $initial !== '' ? $first . ' ' . $initial . '.' : $first;
}

/**
 * BHV'ers who are checked in at the screen's locations: status IN without an extra status
 * (the extra buttons such as "Thuiswerken" or "Vakantie" also set IN, but the person is
 * not in the building — when in doubt the screen rather shows too few than too many). Returns ['count' => n, 'names' => [...]] (names empty when hidden).
 */
function pd_infoscreen_bhv_present(PDO $db, array $locations, bool $withNames): array
{
    $sql = "SELECT voornaam, achternaam, naam FROM employees
            WHERE actief = 1 AND bhv = 'Ja' AND status = 'IN' AND (sub_status IS NULL OR sub_status = '')";
    $params = [];
    if ($locations) {
        $sql .= " AND locatie IN (" . implode(',', array_fill(0, count($locations), '?')) . ")";
        $params = array_values($locations);
    }
    $sql .= " ORDER BY voornaam, achternaam";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return ['count' => 0, 'names' => []];
    }
    $names = $withNames ? array_values(array_filter(array_map('pd_infoscreen_short_name', $rows))) : [];
    return ['count' => count($rows), 'names' => array_slice($names, 0, 12)];
}

/**
 * Employees whose birthday (MM-DD, opt-in per employee) is today in Amsterdam, for the
 * screen's locations (home location, otherwise current location). 29 February is
 * celebrated on 28 February in other years.
 */
function pd_infoscreen_birthdays(PDO $db, array $locations, ?DateTimeImmutable $today = null): array
{
    $today = $today ?? new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam'));
    $days = [$today->format('m-d')];
    if ($today->format('m-d') === '02-28' && !$today->format('L')) {
        $days[] = '02-29';
    }
    $sql = "SELECT voornaam, achternaam, naam FROM employees
            WHERE actief = 1 AND verjaardag IN (" . implode(',', array_fill(0, count($days), '?')) . ")";
    $params = $days;
    if ($locations) {
        $sql .= " AND COALESCE(NULLIF(home_locatie, ''), locatie) IN ("
              . implode(',', array_fill(0, count($locations), '?')) . ")";
        $params = array_merge($params, array_values($locations));
    }
    $sql .= " ORDER BY voornaam, achternaam LIMIT 20";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return array_values(array_filter(array_map('pd_infoscreen_short_name', $stmt->fetchAll(PDO::FETCH_ASSOC))));
    } catch (\Throwable $e) {
        return []; // column not migrated yet
    }
}

/** Store an uploaded message image. Returns the relative path. Throws RuntimeException. */
function pd_infoscreen_store_upload(array $file, string $appRoot): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Uploaden mislukt');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Afbeelding is te groot (maximaal 5 MB)');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime] ?? null;
    if ($ext === null || @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Alleen afbeeldingen zijn toegestaan (JPG, PNG, GIF of WEBP)');
    }
    $dir = rtrim($appRoot, '/') . '/' . PD_IS_UPLOAD_DIR;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Map voor afbeeldingen kon niet worden aangemaakt');
    }
    $name = 'bericht_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name) && !rename($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Afbeelding kon niet worden opgeslagen');
    }
    return PD_IS_UPLOAD_DIR . '/' . $name;
}

/** Delete a message image when no other message uses it. */
function pd_infoscreen_delete_image(PDO $db, ?string $path, string $appRoot): void
{
    if (!$path || !str_starts_with($path, PD_IS_UPLOAD_DIR . '/') || str_contains($path, '..')) return;
    $stmt = $db->prepare("SELECT COUNT(*) FROM infoscreen_messages WHERE image_path = ?");
    $stmt->execute([$path]);
    if ((int)$stmt->fetchColumn() === 0) {
        @unlink(rtrim($appRoot, '/') . '/' . $path);
    }
}

