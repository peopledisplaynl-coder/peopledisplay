<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * Tweestapsverificatie (2FA) met een authenticator-app (sinds 2.3.0).
 *
 *  - TOTP volgens RFC 6238 (6 cijfers, 30 seconden), eigen implementatie zonder externe dienst
 *  - Geheime sleutel versleuteld in de database (sleutelbestand data/twofa_key.php)
 *  - 10 herstelcodes per gebruiker (alleen een hash wordt bewaard, elke code werkt eenmalig)
 *  - "Dit apparaat 30 dagen vertrouwen" (tabel user_trusted_devices), los van "ingelogd blijven"
 *  - Verplicht stellen per gebruiker met 7 dagen uitstel (users.twofa_required / twofa_grace_until)
 *  - Noodoverride: bestaat het bestand twofa_uit.txt in de hoofdmap, dan wordt 2FA overal genegeerd
 *
 * Alles is zo geschreven dat een fout in dit bestand het inloggen met alleen een wachtwoord
 * niet blokkeert voor gebruikers die geen 2FA hebben.
 */

if (!defined('PD_2FA_GRACE_DAYS')) {
    define('PD_2FA_GRACE_DAYS', 7);
}
if (!defined('PD_2FA_TRUST_DAYS')) {
    define('PD_2FA_TRUST_DAYS', 30);
}
if (!defined('PD_2FA_PENDING_SECONDS')) {
    define('PD_2FA_PENDING_SECONDS', 600);
}
if (!defined('PD_2FA_COOKIE')) {
    define('PD_2FA_COOKIE', 'pd_trusted_device');
}
/** Mislukte codes per gebruiker (alle adressen samen) in 15 minuten voordat 2FA tijdelijk dicht gaat. */
if (!defined('PD_2FA_MAX_FAILS_PER_USER')) {
    define('PD_2FA_MAX_FAILS_PER_USER', 10);
}

/** Zijn de PHP-onderdelen voor 2FA aanwezig? */
function pd_2fa_available(): bool
{
    return function_exists('openssl_encrypt') && function_exists('hash_hmac') && function_exists('random_bytes');
}

/** Noodoverride: bestand twofa_uit.txt in de hoofdmap (via FTP aan te maken). */
function pd_2fa_override_active(): bool
{
    return is_file(dirname(__DIR__) . '/twofa_uit.txt');
}

// ─────────────────────────────────────────────────────────────────────
// Base32 en TOTP
// ─────────────────────────────────────────────────────────────────────

const PD_B32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function pd_base32_encode(string $bin): string
{
    $bits = '';
    $len = strlen($bin);
    for ($i = 0; $i < $len; $i++) {
        $bits .= str_pad(decbin(ord($bin[$i])), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= PD_B32_ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
    }
    return $out;
}

function pd_base32_decode(string $b32): string
{
    $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32));
    $bits = '';
    $len = strlen($b32);
    for ($i = 0; $i < $len; $i++) {
        $bits .= str_pad(decbin(strpos(PD_B32_ALPHABET, $b32[$i])), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

/** Nieuwe geheime sleutel: 20 willekeurige bytes (160 bit) als base32. */
function pd_2fa_generate_secret(): string
{
    return pd_base32_encode(random_bytes(20));
}

/** TOTP-code (6 cijfers) voor een teller (tijdstap van 30 seconden). */
function pd_totp_code(string $secretB32, int $counter, int $digits = 6): string
{
    $key = pd_base32_decode($secretB32);
    $bin = pack('N2', ($counter >> 32) & 0xFFFFFFFF, $counter & 0xFFFFFFFF);
    $hash = hash_hmac('sha1', $bin, $key, true);
    $offset = ord($hash[19]) & 0x0F;
    $value = ((ord($hash[$offset]) & 0x7F) << 24)
           | ((ord($hash[$offset + 1]) & 0xFF) << 16)
           | ((ord($hash[$offset + 2]) & 0xFF) << 8)
           |  (ord($hash[$offset + 3]) & 0xFF);
    return str_pad((string)($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

/**
 * Controleert een ingevoerde code, met één tijdstap speling naar beide kanten (klokverschil).
 * Een tijdstap die al eens gebruikt is ($lastCounter) wordt geweigerd, zodat een afgekeken
 * code niet nog een keer werkt.
 *
 * @return int|null de gebruikte tijdstap bij succes, anders null
 */
function pd_totp_verify(string $secretB32, string $input, int $lastCounter = 0, ?int $now = null): ?int
{
    $input = preg_replace('/\s+/', '', $input);
    if (!preg_match('/^\d{6}$/', $input)) {
        return null;
    }
    $now = $now ?? time();
    $current = intdiv($now, 30);
    $found = null;
    for ($i = -1; $i <= 1; $i++) {
        $counter = $current + $i;
        if (hash_equals(pd_totp_code($secretB32, $counter), $input) && $counter > $lastCounter) {
            $found = $counter;
        }
    }
    return $found;
}

/**
 * Controleert of een code bij deze sleutel hoort maar bij een andere tijd (telefoonklok loopt af).
 *
 * @return int|null aantal tijdstappen van 30 seconden verschil (positief = telefoon loopt voor), of null
 */
function pd_totp_clock_offset(string $secretB32, string $input, ?int $now = null): ?int
{
    $input = preg_replace('/\s+/', '', $input);
    if (!preg_match('/^\d{6}$/', $input)) {
        return null;
    }
    $current = intdiv($now ?? time(), 30);
    for ($i = 2; $i <= 120; $i++) {
        foreach ([$i, -$i] as $d) {
            if (hash_equals(pd_totp_code($secretB32, $current + $d), $input)) {
                return $d;
            }
        }
    }
    return null;
}

/** otpauth://-adres dat de authenticator-app via de QR-code of de handmatige sleutel inleest. */
function pd_2fa_otpauth_uri(string $username, string $secretB32, string $issuer = 'PeopleDisplay'): string
{
    $label = rawurlencode($issuer) . ':' . rawurlencode($username);
    return 'otpauth://totp/' . $label
        . '?secret=' . $secretB32
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}

/** QR-code als inline SVG, lokaal gemaakt (de sleutel gaat nooit naar een externe dienst). */
function pd_2fa_qr_svg(string $text, int $pixels = 220): string
{
    $tcpdf = dirname(__DIR__) . '/tcpdf/tcpdf_barcodes_2d.php';
    if (!is_file($tcpdf)) {
        return '';
    }
    try {
        require_once $tcpdf;
        $barcode = new TCPDF2DBarcode($text, 'QRCODE,M');
        $svg = $barcode->getBarcodeSVGcode(4, 4, 'black');
    } catch (Throwable $e) {
        error_log('pd_2fa_qr_svg: ' . $e->getMessage());
        return '';
    }
    $svg = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    $svg = preg_replace('/<!DOCTYPE[^>]*>\s*/', '', $svg);
    if (preg_match('/<svg width="(\d+(?:\.\d+)?)" height="(\d+(?:\.\d+)?)"/', $svg, $m)) {
        $svg = str_replace(
            $m[0],
            '<svg viewBox="0 0 ' . $m[1] . ' ' . $m[2] . '" width="' . $pixels . '" height="' . $pixels . '" role="img" aria-label="QR-code voor je authenticator-app"',
            $svg
        );
    }
    return '<div style="background:#fff;padding:8px;display:inline-block;border-radius:8px;">' . $svg . '</div>';
}

// ─────────────────────────────────────────────────────────────────────
// Versleuteling van de geheime sleutel
// ─────────────────────────────────────────────────────────────────────

/**
 * Sleutel voor het versleutelen van de 2FA-geheimen. Staat in data/twofa_key.php
 * (wordt automatisch aangemaakt, hoort bij de server en zit niet in updates). Kan dat
 * bestand niet worden aangemaakt, dan wordt een sleutel afgeleid van de databasegegevens
 * (herkenbaar aan de voorvoegsel "v1:").
 */
function pd_2fa_key(string $version = 'v2'): ?string
{
    if ($version === 'v2') {
        $dir = defined('PD_2FA_KEY_DIR') ? PD_2FA_KEY_DIR : dirname(__DIR__) . '/data';
        $file = $dir . '/twofa_key.php';
        if (is_file($file)) {
            pd_2fa_protect_dir($dir);
            $key = @include $file;
            if (is_string($key) && strlen($key) >= 32) {
                return hash('sha256', 'pd-2fa-file|' . $key, true);
            }
            return null;
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            $key = bin2hex(random_bytes(32));
            $content = "<?php\n// PeopleDisplay: sleutel voor tweestapsverificatie. NIET verwijderen of delen;\n"
                . "// zonder dit bestand werken de ingestelde 2FA-apps niet meer.\nreturn '" . $key . "';\n";
            if (@file_put_contents($file, $content, LOCK_EX) !== false) {
                @chmod($file, 0640);
                pd_2fa_protect_dir($dir);
                return hash('sha256', 'pd-2fa-file|' . $key, true);
            }
        }
        return null;
    }
    // v1: afgeleid van de databasegegevens
    if (defined('DB_PASS') && defined('DB_NAME') && defined('DB_USER')) {
        return hash('sha256', 'pd-2fa-db|' . DB_NAME . '|' . DB_USER . '|' . DB_PASS, true);
    }
    return null;
}

/**
 * Zorgt dat de map met het sleutelbestand niet via de browser te lezen is en dat de webserver het
 * bijbehorende .htaccess kan lezen: de map moet doorzoekbaar zijn (0755) en .htaccess leesbaar (0644).
 * Bij een te strenge map (0750) meldt Apache "unable to read htaccess" en weigert alles; dat is veilig,
 * maar dit herstelt het. Het sleutelbestand zelf blijft 0640.
 */
function pd_2fa_protect_dir(string $dir): void
{
    static $done = [];
    if (isset($done[$dir])) {
        return;
    }
    $done[$dir] = true;
    $perm = @fileperms($dir);
    if ($perm !== false && ($perm & 0005) !== 0005) {
        @chmod($dir, 0755);
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
    if (is_file($ht)) {
        $p = @fileperms($ht);
        if ($p !== false && ($p & 0004) !== 0004) {
            @chmod($ht, 0644);
        }
    }
}

/** Versleutelt een geheim. Resultaat: "v2:" of "v1:" + base64(iv + tag + data). */
function pd_2fa_encrypt(string $plain): string
{
    $version = 'v2';
    $key = pd_2fa_key('v2');
    if ($key === null) {
        $version = 'v1';
        $key = pd_2fa_key('v1');
    }
    if ($key === null) {
        throw new RuntimeException('Geen sleutel beschikbaar voor 2FA');
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('Versleutelen mislukt');
    }
    return $version . ':' . base64_encode($iv . $tag . $cipher);
}

function pd_2fa_decrypt(string $stored): ?string
{
    if (!preg_match('/^(v1|v2):(.+)$/', $stored, $m)) {
        return null;
    }
    $key = pd_2fa_key($m[1]);
    $raw = base64_decode($m[2], true);
    if ($key === null || $raw === false || strlen($raw) < 29) {
        return null;
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? null : $plain;
}

/** Hash van een herstelcode (met de serversleutel, zodat een databaselek niet genoeg is). */
function pd_2fa_recovery_hash(string $code): string
{
    $key = pd_2fa_key('v2') ?? pd_2fa_key('v1') ?? 'pd';
    return hash_hmac('sha256', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code)), $key);
}

// ─────────────────────────────────────────────────────────────────────
// Database
// ─────────────────────────────────────────────────────────────────────

/** Tabellen en kolommen voor 2FA. Zelfde aanpak als pd_security_ensure_schema(). */
function pd_2fa_ensure_schema(PDO $db): bool
{
    static $done = null;
    if ($done !== null) {
        return $done;
    }
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `user_2fa` (
            `user_id`      int(11)      NOT NULL,
            `secret_enc`   text         NOT NULL,
            `enabled_at`   datetime     NOT NULL DEFAULT current_timestamp(),
            `last_counter` bigint(20)   NOT NULL DEFAULT 0,
            PRIMARY KEY (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `user_2fa_recovery` (
            `id`        int(11)     NOT NULL AUTO_INCREMENT,
            `user_id`   int(11)     NOT NULL,
            `code_hash` char(64)    NOT NULL,
            `used_at`   datetime    DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `user_trusted_devices` (
            `id`         int(11)      NOT NULL AUTO_INCREMENT,
            `user_id`    int(11)      NOT NULL,
            `selector`   char(32)     NOT NULL,
            `token_hash` char(64)     NOT NULL,
            `user_agent` varchar(255) DEFAULT NULL,
            `ip_address` varchar(45)  DEFAULT NULL,
            `created_at` datetime     NOT NULL DEFAULT current_timestamp(),
            `expires_at` datetime     NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_selector` (`selector`),
            KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $col = $db->query("SHOW COLUMNS FROM `users` LIKE 'twofa_required'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `twofa_required` tinyint(1) NOT NULL DEFAULT 0");
        }
        $col = $db->query("SHOW COLUMNS FROM `users` LIKE 'twofa_grace_until'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `twofa_grace_until` datetime DEFAULT NULL");
        }
        $done = true;
    } catch (Throwable $e) {
        error_log('pd_2fa_ensure_schema: ' . $e->getMessage());
        $done = false;
    }
    return $done;
}

/** Heeft deze gebruiker 2FA aan staan? Bij een databasefout: nee (nooit iemand buitensluiten door een fout). */
function pd_2fa_enabled(PDO $db, int $userId): bool
{
    if (!pd_2fa_available() || !pd_2fa_ensure_schema($db)) {
        return false;
    }
    try {
        $stmt = $db->prepare("SELECT 1 FROM user_2fa WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('pd_2fa_enabled: ' . $e->getMessage());
        return false;
    }
}

/** Is 2FA voor deze gebruiker verplicht gesteld? Kiosk-/tabletaccounts nooit. */
function pd_2fa_required(PDO $db, int $userId): bool
{
    if (!pd_2fa_available() || !pd_2fa_ensure_schema($db)) {
        return false;
    }
    try {
        $stmt = $db->prepare("SELECT twofa_required FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        if ((int)$stmt->fetchColumn() !== 1) {
            return false;
        }
        $stmt = $db->prepare("SELECT 1 FROM kiosk_tokens WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return !$stmt->fetchColumn();
    } catch (Throwable $e) {
        // kiosk_tokens kan op oude installaties ontbreken; dan telt de gebruikersinstelling
        try {
            $stmt = $db->prepare("SELECT twofa_required FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn() === 1;
        } catch (Throwable $e2) {
            return false;
        }
    }
}

/**
 * Einde van het uitstel voor een gebruiker bij wie 2FA verplicht is maar nog niet is ingesteld.
 * Staat er nog geen datum, dan begint het uitstel nu. Geeft een unix-tijdstip terug, of null.
 */
function pd_2fa_grace_deadline(PDO $db, int $userId): ?int
{
    try {
        $stmt = $db->prepare("SELECT UNIX_TIMESTAMP(twofa_grace_until) FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $ts = $stmt->fetchColumn();
        if ($ts === false) {
            return null;
        }
        if ($ts === null || $ts === '') {
            $db->prepare("UPDATE users SET twofa_grace_until = NOW() + INTERVAL " . (int)PD_2FA_GRACE_DAYS . " DAY WHERE id = ? AND twofa_grace_until IS NULL")
               ->execute([$userId]);
            $stmt->execute([$userId]);
            $ts = $stmt->fetchColumn();
        }
        return $ts !== false && $ts !== null ? (int)$ts : null;
    } catch (Throwable $e) {
        error_log('pd_2fa_grace_deadline: ' . $e->getMessage());
        return null;
    }
}

/** Slaat een nieuw geheim op en zet 2FA aan. Geeft de 10 herstelcodes terug (alleen nu zichtbaar). */
function pd_2fa_enable(PDO $db, int $userId, string $secretB32, int $counter): array
{
    pd_2fa_ensure_schema($db);
    $enc = pd_2fa_encrypt($secretB32);
    $db->prepare("REPLACE INTO user_2fa (user_id, secret_enc, enabled_at, last_counter) VALUES (?, ?, NOW(), ?)")
       ->execute([$userId, $enc, $counter]);
    $codes = pd_2fa_new_recovery_codes($db, $userId);
    // Oude "ingelogd blijven"-tokens en vertrouwde apparaten horen bij de situatie zonder 2FA
    pd_2fa_revoke_devices($db, $userId, true);
    return $codes;
}

/** 10 nieuwe herstelcodes (vervangt de oude). Formaat XXXXX-XXXXX zonder verwarrende tekens. */
function pd_2fa_new_recovery_codes(PDO $db, int $userId): array
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $codes = [];
    $db->prepare("DELETE FROM user_2fa_recovery WHERE user_id = ?")->execute([$userId]);
    $ins = $db->prepare("INSERT INTO user_2fa_recovery (user_id, code_hash) VALUES (?, ?)");
    for ($i = 0; $i < 10; $i++) {
        $raw = '';
        for ($j = 0; $j < 10; $j++) {
            $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $ins->execute([$userId, pd_2fa_recovery_hash($raw)]);
        $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5);
    }
    return $codes;
}

function pd_2fa_recovery_left(PDO $db, int $userId): int
{
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_2fa_recovery WHERE user_id = ? AND used_at IS NULL");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** Zet 2FA uit en ruimt alles op (geheim, herstelcodes, vertrouwde apparaten, ingelogd-blijven). */
function pd_2fa_disable(PDO $db, int $userId): void
{
    pd_2fa_ensure_schema($db);
    $db->prepare("DELETE FROM user_2fa WHERE user_id = ?")->execute([$userId]);
    $db->prepare("DELETE FROM user_2fa_recovery WHERE user_id = ?")->execute([$userId]);
    pd_2fa_revoke_devices($db, $userId, true);
}

/** Vertrouwde apparaten (en eventueel "ingelogd blijven"-tokens) van een gebruiker intrekken. */
function pd_2fa_revoke_devices(PDO $db, int $userId, bool $alsoRememberTokens = false): void
{
    try {
        $db->prepare("DELETE FROM user_trusted_devices WHERE user_id = ?")->execute([$userId]);
    } catch (Throwable $e) {
        // tabel nog niet aangemaakt
    }
    if ($alsoRememberTokens) {
        try {
            $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$userId]);
        } catch (Throwable $e) {
            // tabel bestaat niet op elke installatie
        }
    }
}

/**
 * Controleert een ingevoerde code: eerst als TOTP-code, anders als herstelcode.
 * Herstelcodes werken eenmalig. Een TOTP-tijdstap werkt ook maar eenmaal.
 *
 * @return string|null 'totp' of 'recovery' bij succes, anders null
 */
function pd_2fa_verify_user_code(PDO $db, int $userId, string $input): ?string
{
    if (!pd_2fa_available() || !pd_2fa_ensure_schema($db)) {
        return null;
    }
    $input = trim($input);
    if ($input === '') {
        return null;
    }
    try {
        $stmt = $db->prepare("SELECT secret_enc, last_counter FROM user_2fa WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        if (preg_match('/^\s*\d{3}\s?\d{3}\s*$/', $input)) {
            $secret = pd_2fa_decrypt((string)$row['secret_enc']);
            if ($secret === null) {
                error_log('pd_2fa_verify_user_code: geheim van gebruiker ' . $userId . ' niet te ontsleutelen (sleutelbestand data/twofa_key.php kwijt?)');
                return null;
            }
            $counter = pd_totp_verify($secret, $input, (int)$row['last_counter']);
            if ($counter === null) {
                return null;
            }
            // Atomair: alleen bijwerken als de teller nog niet hoger was (twee gelijktijdige pogingen)
            $upd = $db->prepare("UPDATE user_2fa SET last_counter = ? WHERE user_id = ? AND last_counter < ?");
            $upd->execute([$counter, $userId, $counter]);
            return $upd->rowCount() === 1 ? 'totp' : null;
        }

        $hash = pd_2fa_recovery_hash($input);
        $upd = $db->prepare("UPDATE user_2fa_recovery SET used_at = NOW() WHERE user_id = ? AND code_hash = ? AND used_at IS NULL LIMIT 1");
        $upd->execute([$userId, $hash]);
        return $upd->rowCount() === 1 ? 'recovery' : null;
    } catch (Throwable $e) {
        error_log('pd_2fa_verify_user_code: ' . $e->getMessage());
        return null;
    }
}

/** Te veel mislukte codes voor deze gebruiker (vanaf welk adres dan ook)? */
function pd_2fa_user_locked(PDO $db, string $username): bool
{
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts
            WHERE username = ? AND success = 0 AND reason = 'bad_2fa' AND created_at > (NOW() - INTERVAL 15 MINUTE)");
        $stmt->execute([mb_substr($username, 0, 100)]);
        return (int)$stmt->fetchColumn() >= PD_2FA_MAX_FAILS_PER_USER;
    } catch (Throwable $e) {
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────
// Vertrouwd apparaat
// ─────────────────────────────────────────────────────────────────────

function pd_2fa_set_cookie(string $value, int $expires): void
{
    setcookie(PD_2FA_COOKIE, $value, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => function_exists('pd_is_https') ? pd_is_https() : false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** Dit apparaat 30 dagen vertrouwen: token in de database en een cookie. */
function pd_2fa_trust_device(PDO $db, int $userId): void
{
    try {
        pd_2fa_ensure_schema($db);
        $selector = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        $expires = time() + PD_2FA_TRUST_DAYS * 86400;
        $db->prepare("INSERT INTO user_trusted_devices (user_id, selector, token_hash, user_agent, ip_address, expires_at)
                      VALUES (?, ?, ?, ?, ?, ?)")
           ->execute([
               $userId, $selector, hash('sha256', $token),
               mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
               function_exists('pd_client_ip') ? pd_client_ip() : '',
               date('Y-m-d H:i:s', $expires),
           ]);
        pd_2fa_set_cookie($selector . ':' . $token, $expires);
        // Opruimen van verlopen apparaten (zelden)
        if (random_int(1, 20) === 1) {
            $db->exec("DELETE FROM user_trusted_devices WHERE expires_at < NOW()");
        }
    } catch (Throwable $e) {
        error_log('pd_2fa_trust_device: ' . $e->getMessage());
    }
}

/** Is dit apparaat (cookie) een vertrouwd apparaat van deze gebruiker? */
function pd_2fa_device_trusted(PDO $db, int $userId): bool
{
    $cookie = (string)($_COOKIE[PD_2FA_COOKIE] ?? '');
    if (!preg_match('/^([a-f0-9]{32}):([a-f0-9]{64})$/', $cookie, $m)) {
        return false;
    }
    try {
        pd_2fa_ensure_schema($db);
        $stmt = $db->prepare("SELECT token_hash FROM user_trusted_devices WHERE selector = ? AND user_id = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$m[1], $userId]);
        $hash = $stmt->fetchColumn();
        return is_string($hash) && hash_equals($hash, hash('sha256', $m[2]));
    } catch (Throwable $e) {
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────
// Sessie: wachten op de code, en afdwingen van het instellen
// ─────────────────────────────────────────────────────────────────────

/**
 * Moet deze gebruiker nu een code invoeren om in te loggen? Ja als 2FA aan staat, het apparaat
 * niet vertrouwd is en de noodoverride niet actief is.
 */
function pd_2fa_challenge_needed(PDO $db, int $userId): bool
{
    if (pd_2fa_override_active() || !pd_2fa_enabled($db, $userId)) {
        return false;
    }
    return !pd_2fa_device_trusted($db, $userId);
}

/** Wachtende inlog (wachtwoord is goed, code nog niet). */
function pd_2fa_set_pending(array $user, bool $remember): void
{
    $_SESSION['pd_2fa_pending'] = [
        'user_id'  => (int)$user['id'],
        'username' => (string)$user['username'],
        'remember' => $remember,
        'time'     => time(),
    ];
}

function pd_2fa_get_pending(): ?array
{
    $p = $_SESSION['pd_2fa_pending'] ?? null;
    if (!is_array($p) || empty($p['user_id']) || (time() - (int)($p['time'] ?? 0)) > PD_2FA_PENDING_SECONDS) {
        unset($_SESSION['pd_2fa_pending']);
        return null;
    }
    return $p;
}

function pd_2fa_allowed_script(): bool
{
    $base = basename(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    return in_array($base, [
        'twofa_setup.php', 'force_password_change.php', 'logout.php',
        'heartbeat.php', 'check_session.php', 'check_session_status.php',
    ], true);
}

/**
 * Staat een aanvraag toe voor iemand bij wie 2FA verplicht is maar niet is ingesteld?
 * Tijdens het uitstel is alles toegestaan; daarna alleen de instelpagina. Wordt aangeroepen
 * aan het einde van includes/db.php, na de controle op de verplichte wachtwoordwijziging.
 * De status wordt hooguit elke 5 minuten opnieuw uit de database gelezen.
 */
function pd_enforce_2fa_setup(PDO $db): void
{
    if (empty($_SESSION['user_id']) || !empty($_SESSION['kiosk_login']) || PHP_SAPI === 'cli') {
        return;
    }
    if (pd_2fa_override_active() || !pd_2fa_available()) {
        return;
    }

    $userId = (int)$_SESSION['user_id'];
    $state = $_SESSION['pd_2fa_state'] ?? null;
    if (!is_array($state) || (time() - (int)($state['checked'] ?? 0)) > 300) {
        $state = ['checked' => time(), 'must_setup' => false, 'deadline' => null];
        try {
            if (pd_2fa_ensure_schema($db) && pd_2fa_required($db, $userId) && !pd_2fa_enabled($db, $userId)) {
                $state['must_setup'] = true;
                $state['deadline'] = pd_2fa_grace_deadline($db, $userId);
            }
        } catch (Throwable $e) {
            return; // bij een fout nooit iemand buitensluiten
        }
        $_SESSION['pd_2fa_state'] = $state;
    }

    if (empty($state['must_setup'])) {
        return;
    }
    if (pd_2fa_allowed_script()) {
        return;
    }

    $base = defined('BASE_PATH') ? BASE_PATH : '';
    $target = $base . '/twofa_setup.php';
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $isApi = strpos($script, '/api/') !== false
        || (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && strpos($accept, 'text/html') === false)
        || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || (strpos($accept, 'application/json') !== false && strpos($accept, 'text/html') === false);

    // Nog binnen het uitstel: gewoon doorwerken. Wel een herinnering: bij het openen van een pagina
    // (niet bij API-aanroepen) wordt 2FA voorgesteld als dat in deze sessie de laatste 24 uur nog niet
    // is gebeurd. Zo komt ook iemand die "ingelogd blijven" heeft gekozen, of net een nieuw wachtwoord
    // heeft gekozen, de uitnodiging tegen. "Later instellen" brengt je direct terug.
    if (!empty($state['deadline']) && time() < (int)$state['deadline']) {
        if (!$isApi
            && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
            && strpos($accept, 'text/html') !== false
            && (time() - (int)($_SESSION['pd_2fa_invited_at'] ?? 0)) >= 86400) {
            $_SESSION['pd_2fa_invited_at'] = time();
            header('Location: ' . $target);
            exit;
        }
        return;
    }

    if ($isApi) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'  => false,
            'error'    => 'Je moet eerst tweestapsverificatie instellen.',
            'code'     => 'twofa_setup_required',
            'redirect' => $target,
        ]);
        exit;
    }
    header('Location: ' . $target);
    exit;
}

/** Gedeelde stijl voor de 2FA-pagina's (zelfde uiterlijk als het inlogscherm). */
function pd_2fa_page_css(): string
{
    return <<<'CSS'
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
.box { background: #fff; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,.2); padding: 36px; width: 100%; max-width: 520px; }
h1 { font-size: 24px; color: #2d3748; margin-bottom: 8px; text-align: center; }
h2 { font-size: 16px; color: #2d3748; margin: 22px 0 8px; }
.intro { color: #4a5568; font-size: 14px; line-height: 1.5; margin-bottom: 20px; text-align: center; }
p.text, li.text { color: #4a5568; font-size: 14px; line-height: 1.55; margin-bottom: 10px; }
.form-group { margin-bottom: 16px; }
label { display: block; font-weight: 600; color: #2d3748; margin-bottom: 6px; font-size: 14px; }
input[type="text"], input[type="password"] { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px; }
input[type="text"]:focus, input[type="password"]:focus { outline: none; border-color: #667eea; }
input.code { text-align: center; letter-spacing: 4px; font-size: 22px; font-family: Consolas, monospace; }
.check { display: flex; gap: 10px; align-items: flex-start; padding: 12px; background: #f7fafc; border: 2px solid #e2e8f0; border-radius: 8px; margin-bottom: 18px; font-size: 14px; color: #2d3748; }
.check input { width: 18px; height: 18px; margin-top: 2px; flex: none; }
.check small { display: block; color: #718096; font-size: 12px; margin-top: 2px; }
.hint { font-size: 12px; color: #718096; margin-top: 4px; }
.error { background: #fed7d7; color: #c53030; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; border-left: 4px solid #c53030; }
.ok { background: #c6f6d5; color: #22543d; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; border-left: 4px solid #48bb78; }
.warn { background: #feebc8; color: #7b341e; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; border-left: 4px solid #ed8936; }
button, .btn { display: block; width: 100%; padding: 14px; border: none; border-radius: 8px; cursor: pointer; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; font-size: 16px; font-weight: 600; text-align: center; text-decoration: none; }
button.secondary, .btn.secondary { background: #edf2f7; color: #2d3748; margin-top: 10px; }
button.danger { background: #e53e3e; }
.links { text-align: center; margin-top: 18px; font-size: 13px; }
.links a { color: #718096; }
.center { text-align: center; }
.secret { font-family: Consolas, monospace; font-size: 15px; background: #f7fafc; border: 1px dashed #a0aec0; border-radius: 6px; padding: 8px 12px; display: inline-block; letter-spacing: 2px; word-break: break-all; }
.codes { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin: 12px 0 16px; }
.codes div { font-family: Consolas, monospace; font-size: 16px; text-align: center; background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; }
.steps { padding-left: 20px; }
@media print { body { background: #fff; } .box { box-shadow: none; } .noprint { display: none; } }
CSS;
}

/** Eenvoudige CSRF-token voor de 2FA-instelpagina (per sessie). */
function pd_2fa_csrf_token(): string
{
    if (empty($_SESSION['pd_2fa_csrf'])) {
        $_SESSION['pd_2fa_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['pd_2fa_csrf'];
}

function pd_2fa_csrf_ok(): bool
{
    $sent = (string)($_POST['csrf'] ?? '');
    return $sent !== '' && !empty($_SESSION['pd_2fa_csrf']) && hash_equals($_SESSION['pd_2fa_csrf'], $sent);
}

/**
 * Versleutelde sleutel voor in het instelformulier: zo controleert de server precies de sleutel
 * waarmee de QR-code op het scherm is gemaakt, ook als de sessie intussen iets anders bevat.
 */
function pd_2fa_setup_token_make(string $secretB32, int $userId): string
{
    return pd_2fa_encrypt(json_encode(['u' => $userId, 's' => $secretB32, 't' => time()]));
}

function pd_2fa_setup_token_read(string $token, int $userId): string
{
    if ($token === '') {
        return '';
    }
    $plain = pd_2fa_decrypt($token);
    $data = $plain !== null ? json_decode($plain, true) : null;
    if (!is_array($data) || (int)($data['u'] ?? 0) !== $userId || time() - (int)($data['t'] ?? 0) > 3600) {
        return '';
    }
    $secret = (string)($data['s'] ?? '');
    return preg_match('/^[A-Z2-7]{16,}$/', $secret) ? $secret : '';
}
