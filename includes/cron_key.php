<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * cron_key.php
 * Upload naar: /includes/cron_key.php
 *
 * Per-installation key for web cron calls (2.6.0). Until 2.5.x every installation used the
 * same key, which is public on GitHub. The key now lives in data/cron_key.php (created
 * automatically, belongs to the server, never part of an update, folder closed by .htaccess).
 *
 * Transition: on an existing installation the old key (and, for cron_reset_expired_sub_status.php,
 * a call without a key) keeps working for PD_CRON_LEGACY_DAYS days after the new key was created, so existing
 * cron-job.org jobs do not break on update. Each such call is recorded and the admin dashboard
 * shows a warning. "Automatische taken" (admin/cron_manage.php) can switch it off at once.
 */

if (!defined('PD_CRON_LEGACY_KEY')) {
    define('PD_CRON_LEGACY_KEY', 'INZ8U56BCZ');
}
if (!defined('PD_CRON_LEGACY_DAYS')) {
    define('PD_CRON_LEGACY_DAYS', 30);
}

function pd_cron_dir(): string
{
    return defined('PD_CRON_KEY_DIR') ? PD_CRON_KEY_DIR : dirname(__DIR__) . '/data';
}

/** Close the data folder for the web (same as the 2FA key file). */
function pd_cron_protect_dir(string $dir): void
{
    $ht = $dir . '/.htaccess';
    if (is_dir($dir) && !is_file($ht)) {
        @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
}

function pd_cron_write_state(array $state): bool
{
    $dir = pd_cron_dir();
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (!is_dir($dir) || !is_writable($dir)) return false;
    pd_cron_protect_dir($dir);
    $content = "<?php\n// PeopleDisplay: sleutel voor de cronjobs (cron-job.org of hostingpaneel). Niet delen.\n"
        . "// Nieuwe sleutel maken: Beheer > Automatische taken.\nreturn " . var_export($state, true) . ";\n";
    $ok = @file_put_contents($dir . '/cron_key.php', $content, LOCK_EX) !== false;
    if ($ok) {
        @chmod($dir . '/cron_key.php', 0640);
        if (function_exists('opcache_invalidate')) @opcache_invalidate($dir . '/cron_key.php', true);
    }
    return $ok;
}

function pd_cron_new_key(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $key = '';
    for ($i = 0; $i < 32; $i++) $key .= $chars[random_int(0, strlen($chars) - 1)];
    return $key;
}

/**
 * ['key' => string|null, 'legacy_until' => int, 'created' => int].
 * key null = the key file could not be created (data folder not writable).
 */
function pd_cron_state(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $file = pd_cron_dir() . '/cron_key.php';
    if (is_file($file)) {
        $state = @include $file;
        if (is_array($state) && is_string($state['key'] ?? null) && strlen($state['key']) >= 20) {
            pd_cron_protect_dir(pd_cron_dir());
            return $cache = ['key' => $state['key'], 'legacy_until' => (int)($state['legacy_until'] ?? 0), 'created' => (int)($state['created'] ?? 0)];
        }
    }
    // Transition period only for installations that existed before this version (db_config.php older
    // than a day); a fresh installation never accepts the old public key.
    $cfg = dirname(__DIR__) . '/admin/db_config.php';
    $existing = is_file($cfg) && (int)@filemtime($cfg) < time() - 86400;
    $state = ['key' => pd_cron_new_key(), 'legacy_until' => $existing ? time() + PD_CRON_LEGACY_DAYS * 86400 : 0, 'created' => time()];
    if (!pd_cron_write_state($state)) {
        return ['key' => null, 'legacy_until' => PHP_INT_MAX, 'created' => 0];
    }
    return $cache = $state;
}

function pd_cron_key(): ?string
{
    return pd_cron_state()['key'];
}

/** Is the old public key still accepted? */
function pd_cron_legacy_allowed(): bool
{
    return pd_cron_state()['legacy_until'] > time();
}

/** New key; the transition period is kept as it was. Returns the new key or null. */
function pd_cron_regenerate(): ?string
{
    $state = pd_cron_state();
    $state['key'] = pd_cron_new_key();
    $state['created'] = time();
    if ($state['legacy_until'] === PHP_INT_MAX) $state['legacy_until'] = time() + PD_CRON_LEGACY_DAYS * 86400;
    return pd_cron_write_state($state) ? $state['key'] : null;
}

/** Stop accepting the old key right now. */
function pd_cron_legacy_disable(): bool
{
    $state = pd_cron_state();
    if ($state['key'] === null) return false;
    $state['legacy_until'] = 0;
    return pd_cron_write_state($state);
}

function pd_cron_record_legacy(string $task): void
{
    $dir = pd_cron_dir();
    if (!is_dir($dir) || !is_writable($dir)) return;
    $file = $dir . '/cron_legacy_used.json';
    $data = json_decode((string)@file_get_contents($file), true);
    if (!is_array($data)) $data = [];
    $data[$task] = time();
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

/** Tasks that still used the old key (or no key), with the last time: [task => timestamp]. */
function pd_cron_legacy_usage(int $sinceDays = 14): array
{
    $data = json_decode((string)@file_get_contents(pd_cron_dir() . '/cron_legacy_used.json'), true);
    if (!is_array($data)) return [];
    $since = time() - $sinceDays * 86400;
    return array_filter(array_map('intval', $data), fn($t) => $t >= $since);
}

/**
 * Check a web cron call. CLI calls are always fine.
 * $allowMissing: a call without a key counts as an old call (only for scripts that had no key check).
 */
function pd_cron_key_ok(?string $provided, string $task, bool $allowMissing = false): bool
{
    if (PHP_SAPI === 'cli') return true;
    $provided = (string)$provided;
    $key = pd_cron_key();
    if ($key !== null && $provided !== '' && hash_equals($key, $provided)) return true;
    $legacy = $provided === PD_CRON_LEGACY_KEY || ($allowMissing && $provided === '');
    if ($legacy && pd_cron_legacy_allowed()) {
        pd_cron_record_legacy($task);
        return true;
    }
    return false;
}

/** Stop a web cron call with 403 unless the key is right. */
function pd_cron_require_key(string $task, bool $allowMissing = false, bool $json = false): void
{
    if (pd_cron_key_ok($_GET['key'] ?? null, $task, $allowMissing)) return;
    http_response_code(403);
    if ($json) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Invalid or missing security key. Zie Beheer > Automatische taken voor de juiste link.',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    } else {
        echo 'Access denied. Zie Beheer > Automatische taken voor de juiste link.';
    }
    exit;
}
