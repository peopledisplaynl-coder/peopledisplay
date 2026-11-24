<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * infoscreen_api.php
 * Upload naar: /includes/infoscreen_api.php
 *
 * Messages from other systems for the infoscreen (2.5.0):
 * - Berichten-API (api/infoscreen_push.php): a key per connection, sent as
 *   "Authorization: Bearer <key>" or "X-Api-Key: <key>". Only the SHA-256 of the key is stored.
 * - Teams (api/infoscreen_teams.php): a Teams "outgoing webhook" in a team. Someone writes
 *   "@Infoscherm <title>" in a channel; Teams signs the request with HMAC-SHA256 and the
 *   security token, which is stored encrypted (same key file as 2FA).
 * Both are limited to PD_IS_API_PER_HOUR requests per hour per connection, and a connection
 * can only change or remove its own messages.
 */

if (defined('PD_INFOSCREEN_API_LOADED')) {
    return;
}
define('PD_INFOSCREEN_API_LOADED', true);

require_once __DIR__ . '/infoscreen.php';

const PD_IS_API_PER_HOUR = 120;
const PD_IS_COLORS = ['blauw', 'groen', 'oranje', 'rood', 'paars', 'grijs'];

/** A new API key: [key, sha256, hint]. The key itself is shown once and never stored. */
function pd_is_api_new_key(): array
{
    $key = 'pdis_' . bin2hex(random_bytes(20));
    return [$key, hash('sha256', $key), substr($key, -6)];
}

/** The key sent with a request (Authorization: Bearer … or X-Api-Key), or ''. */
function pd_is_api_request_key(): string
{
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($auth === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) $auth = $v;
        }
    }
    if (preg_match('/^Bearer\s+(\S+)$/i', trim($auth), $m)) {
        return $m[1];
    }
    return trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
}

function pd_is_api_find_key(PDO $db, string $key): ?array
{
    if (!preg_match('/^pdis_[0-9a-f]{40}$/', $key)) {
        return null;
    }
    $stmt = $db->prepare("SELECT * FROM infoscreen_api_keys WHERE type = 'api' AND key_hash = ? AND active = 1");
    $stmt->execute([hash('sha256', $key)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row && hash_equals($row['key_hash'], hash('sha256', $key)) ? $row : null;
}

/** Counts the request; false when the connection used up its requests for this hour. */
function pd_is_api_rate_ok(PDO $db, array $keyRow): bool
{
    $db->prepare("UPDATE infoscreen_api_keys
                  SET window_count = IF(window_start IS NULL OR window_start < NOW() - INTERVAL 1 HOUR, 1, window_count + 1),
                      window_start = IF(window_start IS NULL OR window_start < NOW() - INTERVAL 1 HOUR, NOW(), window_start),
                      last_used_at = NOW()
                  WHERE id = ?")->execute([$keyRow['id']]);
    $stmt = $db->prepare("SELECT window_count FROM infoscreen_api_keys WHERE id = ?");
    $stmt->execute([$keyRow['id']]);
    return (int)$stmt->fetchColumn() <= PD_IS_API_PER_HOUR;
}

/** Plain text with line breaks kept (for message bodies from other systems). */
function pd_is_api_text(?string $html, int $max): string
{
    $text = (string)$html;
    $text = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $text);
    $text = preg_replace('~<br\s*/?>|</p>|</div>|</li>~i', "\n", $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\xC2\xA0", ' ', $text);
    $lines = array_map(fn($l) => trim(preg_replace('/[ \t]+/u', ' ', $l)), explode("\n", str_replace("\r", '', $text)));
    $text = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    return mb_substr($text, 0, $max);
}

/** A date from the API: ISO 8601 or "2026-12-01 08:00"; null when empty; false when invalid. */
function pd_is_api_date($value)
{
    if ($value === null || $value === '') return null;
    try {
        $d = new DateTimeImmutable((string)$value);
        return $d->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    } catch (\Throwable $e) {
        return false;
    }
}

/** Store image bytes in uploads/infoscreen. Throws RuntimeException. */
function pd_is_api_store_image_bytes(string $bytes, string $appRoot): string
{
    if (strlen($bytes) > 5 * 1024 * 1024) {
        throw new RuntimeException('Afbeelding is te groot (maximaal 5 MB)');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime] ?? null;
    if ($ext === null || @getimagesizefromstring($bytes) === false) {
        throw new RuntimeException('De afbeelding is geen JPG, PNG, GIF of WEBP');
    }
    $dir = rtrim($appRoot, '/') . '/' . PD_IS_UPLOAD_DIR;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Map voor afbeeldingen kon niet worden aangemaakt');
    }
    $name = 'api_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (file_put_contents($dir . '/' . $name, $bytes) === false) {
        throw new RuntimeException('Afbeelding kon niet worden opgeslagen');
    }
    return PD_IS_UPLOAD_DIR . '/' . $name;
}

/** Existing location names. */
function pd_is_api_locations(PDO $db): array
{
    return $db->query("SELECT location_name FROM locations")->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Create or update (same external_id) a message for a connection.
 * Returns ['id', 'ends_at', 'updated']. Throws RuntimeException with a Dutch message.
 */
function pd_is_api_save_message(PDO $db, array $keyRow, array $in, string $appRoot, string $source = 'api'): array
{
    $title = pd_is_api_text($in['title'] ?? '', 200);
    if ($title === '') throw new RuntimeException('title (titel) ontbreekt');
    $body = pd_is_api_text($in['body'] ?? ($in['text'] ?? ''), 2000);

    $location = trim((string)($in['location'] ?? ''));
    if ($location === '') {
        $location = $keyRow['location'] ?: null;
    } elseif (!in_array($location, pd_is_api_locations($db), true)) {
        throw new RuntimeException('Onbekende locatie: ' . $location);
    }

    $starts = pd_is_api_date($in['starts_at'] ?? null);
    $ends = pd_is_api_date($in['ends_at'] ?? null);
    if ($starts === false || $ends === false) throw new RuntimeException('Ongeldige datum in starts_at of ends_at (gebruik bijv. 2026-12-01T08:00:00+01:00)');
    if ($ends === null) {
        $ends = date('Y-m-d H:i:s', strtotime(($starts ?? 'now') . ' +' . max(1, (int)$keyRow['default_days']) . ' days'));
    }
    if ($starts && $ends <= $starts) throw new RuntimeException('ends_at moet na starts_at liggen');

    $color = in_array($in['color'] ?? '', PD_IS_COLORS, true) ? $in['color'] : ($keyRow['color'] ?: 'blauw');
    $duration = isset($in['duration_seconds']) && $in['duration_seconds'] !== '' ? max(5, min(300, (int)$in['duration_seconds'])) : null;
    $priority = filter_var($in['priority'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    $externalId = mb_substr(trim((string)($in['external_id'] ?? '')), 0, 100) ?: null;
    $author = mb_substr(trim((string)($in['author'] ?? '')), 0, 150) ?: null;

    // Image: a public web address (downloaded once) or base64 data
    $image = null;
    if (!empty($in['image_base64'])) {
        $b64 = preg_replace('~^data:image/[a-z+]+;base64,~i', '', (string)$in['image_base64']);
        $bytes = base64_decode($b64, true);
        if ($bytes === false) throw new RuntimeException('image_base64 is geen geldige base64');
        $image = pd_is_api_store_image_bytes($bytes, $appRoot);
    } elseif (!empty($in['image_url'])) {
        $res = pd_infoscreen_http_get((string)$in['image_url']);
        if (!$res['ok']) throw new RuntimeException('Afbeelding ophalen mislukt: ' . $res['error']);
        $image = pd_is_api_store_image_bytes($res['body'], $appRoot);
    }

    $existing = null;
    if ($externalId !== null) {
        $stmt = $db->prepare("SELECT id, image_path FROM infoscreen_messages WHERE api_key_id = ? AND external_id = ?");
        $stmt->execute([$keyRow['id'], $externalId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if ($existing) {
        $keepImage = $image ?? (array_key_exists('image_url', $in) || array_key_exists('image_base64', $in) ? null : $existing['image_path']);
        $db->prepare("UPDATE infoscreen_messages SET title = ?, body = ?, image_path = ?, location = ?, starts_at = ?, ends_at = ?,
                      duration_seconds = ?, priority = ?, color = ?, author = ?, active = 1 WHERE id = ?")
           ->execute([$title, $body, $keepImage, $location, $starts, $ends, $duration, $priority, $color, $author, $existing['id']]);
        if ($existing['image_path'] && $existing['image_path'] !== $keepImage) pd_infoscreen_delete_image($db, $existing['image_path'], $appRoot);
        return ['id' => (int)$existing['id'], 'ends_at' => $ends, 'updated' => true];
    }
    $db->prepare("INSERT INTO infoscreen_messages (title, body, image_path, location, starts_at, ends_at, duration_seconds, priority, color, active, source, api_key_id, external_id, author)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)")
       ->execute([$title, $body, $image, $location, $starts, $ends, $duration, $priority, $color, $source, $keyRow['id'], $externalId, $author]);
    return ['id' => (int)$db->lastInsertId(), 'ends_at' => $ends, 'updated' => false];
}

/** Remove a message of this connection (by id or external_id). True when one was removed. */
function pd_is_api_delete_message(PDO $db, array $keyRow, ?int $id, ?string $externalId, string $appRoot): bool
{
    $stmt = $id
        ? $db->prepare("SELECT id, image_path FROM infoscreen_messages WHERE api_key_id = ? AND id = ?")
        : $db->prepare("SELECT id, image_path FROM infoscreen_messages WHERE api_key_id = ? AND external_id = ?");
    $stmt->execute([$keyRow['id'], $id ?: (string)$externalId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;
    $db->prepare("DELETE FROM infoscreen_messages WHERE id = ?")->execute([$row['id']]);
    pd_infoscreen_delete_image($db, $row['image_path'], $appRoot);
    return true;
}

/** Messages of this connection that are not expired yet. */
function pd_is_api_list_messages(PDO $db, array $keyRow): array
{
    $stmt = $db->prepare("SELECT id, external_id, title, location, starts_at, ends_at, priority, active FROM infoscreen_messages
                          WHERE api_key_id = ? AND (ends_at IS NULL OR ends_at >= NOW()) ORDER BY id DESC LIMIT 100");
    $stmt->execute([$keyRow['id']]);
    return array_map(fn($r) => ['id' => (int)$r['id'], 'external_id' => $r['external_id'], 'title' => $r['title'], 'location' => $r['location'],
        'starts_at' => $r['starts_at'], 'ends_at' => $r['ends_at'], 'priority' => (bool)$r['priority'], 'active' => (bool)$r['active']], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// ─────────────────────────────────────────────────────────────────────────────
// Teams outgoing webhook
// ─────────────────────────────────────────────────────────────────────────────

/** Teams signs the raw body: "Authorization: HMAC base64(HMAC-SHA256(body, base64decode(token)))". */
function pd_is_teams_verify(string $securityToken, string $rawBody, string $authHeader): bool
{
    if (!preg_match('/^HMAC\s+(\S+)$/i', trim($authHeader), $m)) return false;
    $key = base64_decode($securityToken, true);
    if ($key === false || $key === '') return false;
    $expected = base64_encode(hash_hmac('sha256', $rawBody, $key, true));
    return hash_equals($expected, $m[1]);
}

/** Teams message text → plain lines, without the @mention of the webhook. */
function pd_is_teams_lines(string $text): array
{
    $text = preg_replace('~<at>.*?</at>~is', '', $text);
    $plain = pd_is_api_text($text, 4000);
    return array_values(array_filter(array_map('trim', explode("\n", $plain)), fn($l) => $l !== ''));
}

/** "tot 12-12", "tot 12-12-2026 16:00", "tot morgen", "tot vrijdag" → 'Y-m-d H:i:s' or null. */
function pd_is_teams_until(string $text, ?int $now = null): ?string
{
    $now = $now ?? time();
    $text = mb_strtolower(trim($text));
    $time = '23:59';
    if (preg_match('/\b(\d{1,2})[:.](\d{2})\s*(uur)?$/', $text, $tm)) {
        $time = sprintf('%02d:%02d', $tm[1], $tm[2]);
        $text = trim(substr($text, 0, -strlen($tm[0])));
    }
    $day = null;
    if ($text === 'vandaag') {
        $day = date('Y-m-d', $now);
    } elseif ($text === 'morgen') {
        $day = date('Y-m-d', strtotime('+1 day', $now));
    } elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})(?:[-\/.](\d{2,4}))?$/', $text, $m)) {
        $y = isset($m[3]) ? (int)$m[3] : (int)date('Y', $now);
        if ($y < 100) $y += 2000;
        if (!checkdate((int)$m[2], (int)$m[1], $y)) return null;
        $day = sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
        if (!isset($m[3]) && $day . ' ' . $time < date('Y-m-d H:i', $now)) $day = sprintf('%04d-%02d-%02d', $y + 1, $m[2], $m[1]);
    } else {
        $days = ['maandag' => 1, 'dinsdag' => 2, 'woensdag' => 3, 'donderdag' => 4, 'vrijdag' => 5, 'zaterdag' => 6, 'zondag' => 7];
        if (isset($days[$text])) {
            $diff = ($days[$text] - (int)date('N', $now) + 7) % 7;
            $day = date('Y-m-d', strtotime("+$diff days", $now));
        }
    }
    return $day ? $day . ' ' . $time . ':00' : null;
}

/** The reply text for one Teams message. Never throws. */
function pd_is_teams_handle(PDO $db, array $keyRow, array $activity, string $appRoot): string
{
    $name = $keyRow['name'];
    $lines = pd_is_teams_lines((string)($activity['text'] ?? ''));
    $first = mb_strtolower($lines[0] ?? '');
    $help = "Zo zet je een bericht op het infoscherm:\n\n@$name Titel van het bericht\nTekst eronder (mag meerdere regels)\n"
          . "tot vrijdag 16:00 (optioneel; zonder datum " . (int)$keyRow['default_days'] . " dagen)\nbelangrijk (optioneel)\n\n"
          . "Andere opdrachten: @$name lijst · @$name stop 12";

    if ($first === '' || in_array($first, ['help', 'hulp', '?'], true)) {
        return $help;
    }
    if ($first === 'lijst') {
        $msgs = array_filter(pd_is_api_list_messages($db, $keyRow), fn($m) => $m['active']);
        if (!$msgs) return 'Er staan nu geen berichten van dit team op het infoscherm.';
        return "Op het infoscherm:\n\n" . implode("\n", array_map(fn($m) => '• ' . $m['id'] . ': ' . $m['title'] . ' (tot ' . date('j-n H:i', strtotime($m['ends_at'])) . ')', $msgs));
    }
    if (preg_match('/^(stop|verwijder|weg)\s+#?(\d+)$/', $first, $m)) {
        return pd_is_api_delete_message($db, $keyRow, (int)$m[2], null, $appRoot)
            ? "Bericht {$m[2]} is van het infoscherm gehaald."
            : "Bericht {$m[2]} niet gevonden (alleen berichten die via dit team zijn geplaatst).";
    }

    // A new message: option lines are taken out, the first remaining line is the title
    $in = ['priority' => false];
    $rest = [];
    foreach ($lines as $line) {
        $l = mb_strtolower($line);
        if (in_array($l, ['belangrijk', '!belangrijk', '#belangrijk'], true)) { $in['priority'] = true; continue; }
        if (preg_match('/^tot\s+(.+)$/u', $l, $m) && ($until = pd_is_teams_until($m[1])) !== null) { $in['ends_at'] = $until; continue; }
        if (preg_match('/^kleur\s+(\w+)$/u', $l, $m) && in_array($m[1], PD_IS_COLORS, true)) { $in['color'] = $m[1]; continue; }
        if (preg_match('/^locatie\s+(.+)$/iu', $line, $m)) { $in['location'] = trim($m[1]); continue; }
        if (str_contains($l, '#belangrijk')) { $in['priority'] = true; $line = trim(str_ireplace('#belangrijk', '', $line)); }
        $rest[] = $line;
    }
    if (!$rest) return $help;
    $in['title'] = array_shift($rest);
    $in['body'] = implode("\n", $rest);
    $in['author'] = (string)($activity['from']['name'] ?? '');
    if (!empty($activity['id'])) $in['external_id'] = 'teams:' . $activity['id'];
    if (isset($in['ends_at']) && $in['ends_at'] < date('Y-m-d H:i:s')) {
        return 'Die einddatum ligt in het verleden. Het bericht is niet geplaatst.';
    }
    try {
        $r = pd_is_api_save_message($db, $keyRow, $in, $appRoot, 'teams');
    } catch (RuntimeException $e) {
        return 'Niet geplaatst: ' . $e->getMessage();
    }
    $days = ['', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag'];
    $months = ['', 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
    $t = strtotime($r['ends_at']);
    $until = $days[(int)date('N', $t)] . ' ' . date('j', $t) . ' ' . $months[(int)date('n', $t)] . ' ' . date('H:i', $t);
    return "✅ Staat op het infoscherm tot $until" . ($in['priority'] ? ' (belangrijk)' : '') . ".\nNummer {$r['id']}. Weghalen: @$name stop {$r['id']}";
}
