<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * Gedeelde beveiligingsfuncties voor inloggen en wachtwoorden (sinds 2.2.0).
 *
 *  - Wachtwoordbeleid (minimale lengte)
 *  - Logboek van inlogpogingen (tabel login_attempts) en automatische blokkade
 *  - Verplichte wachtwoordwijziging (users.must_change_password)
 *
 * Alles is zo geschreven dat een fout in dit bestand nooit het inloggen onmogelijk
 * maakt: bij een databasefout wordt er niet geblokkeerd en wordt er niets gedwongen.
 */

if (!defined('PD_MIN_PASSWORD_LENGTH')) {
    define('PD_MIN_PASSWORD_LENGTH', 10);
}

/** Aantal mislukte pogingen (per IP + gebruikersnaam) voordat er geblokkeerd wordt. */
if (!defined('PD_LOGIN_MAX_FAILS')) {
    define('PD_LOGIN_MAX_FAILS', 5);
}
/** Aantal mislukte pogingen per IP (alle gebruikersnamen samen) in 15 minuten. */
if (!defined('PD_LOGIN_MAX_FAILS_PER_IP')) {
    define('PD_LOGIN_MAX_FAILS_PER_IP', 20);
}
/** Basisduur van een blokkade in minuten; verdubbelt bij herhaling, maximaal 24 uur. */
if (!defined('PD_LOGIN_LOCK_MINUTES')) {
    define('PD_LOGIN_LOCK_MINUTES', 15);
}
/** Hoe lang (dagen) mislukte en gelukte pogingen worden bewaard. */
if (!defined('PD_LOGIN_LOG_RETENTION_DAYS')) {
    define('PD_LOGIN_LOG_RETENTION_DAYS', 90);
}

/**
 * Controleert een nieuw wachtwoord tegen het beleid.
 *
 * @return string|null Nederlandse foutmelding, of null als het wachtwoord goed is.
 */
function pd_password_policy_error(string $password): ?string
{
    if (mb_strlen($password) < PD_MIN_PASSWORD_LENGTH) {
        return 'Het wachtwoord moet minimaal ' . PD_MIN_PASSWORD_LENGTH . ' tekens bevatten.';
    }
    if (mb_strlen($password) > 200) {
        return 'Het wachtwoord is te lang (maximaal 200 tekens).';
    }
    if (trim($password) === '') {
        return 'Het wachtwoord mag niet alleen uit spaties bestaan.';
    }
    return null;
}

/** IP-adres van de bezoeker (alleen REMOTE_ADDR; X-Forwarded-For is te vervalsen). */
function pd_client_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return substr($ip, 0, 45);
}

/** Is de verbinding versleuteld (HTTPS)? Ook achter een proxy die dat doorgeeft. */
function pd_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/**
 * Zorgt dat de tabellen en kolommen voor de beveiliging bestaan. De gewone migraties
 * (includes/migrations.php) draaien alleen voor beheerders; het inlogscherm moet ook
 * werken als er na een update nog geen beheerder is geweest.
 */
function pd_security_ensure_schema(PDO $db): bool
{
    static $done = null;
    if ($done !== null) {
        return $done;
    }
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id`         bigint(20)   NOT NULL AUTO_INCREMENT,
            `username`   varchar(100) NOT NULL DEFAULT '',
            `ip_address` varchar(45)  NOT NULL DEFAULT '',
            `user_agent` varchar(255) DEFAULT NULL,
            `success`    tinyint(1)   NOT NULL DEFAULT 0,
            `reason`     varchar(40)  DEFAULT NULL,
            `created_at` datetime     NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_ip_time`   (`ip_address`, `created_at`),
            KEY `idx_user_time` (`username`, `created_at`),
            KEY `idx_created`   (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $col = $db->query("SHOW COLUMNS FROM `users` LIKE 'must_change_password'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `must_change_password` tinyint(1) NOT NULL DEFAULT 0");
        }
        $col = $db->query("SHOW COLUMNS FROM `users` LIKE 'password_changed_at'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `password_changed_at` datetime DEFAULT NULL");
        }
        $col = $db->query("SHOW COLUMNS FROM `users` LIKE 'last_login'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `last_login` datetime DEFAULT NULL");
        }
        $done = true;
    } catch (Throwable $e) {
        error_log('pd_security_ensure_schema: ' . $e->getMessage());
        $done = false;
    }
    return $done;
}

/** Legt een inlogpoging vast. Faalt stil. */
function pd_login_record(PDO $db, string $username, bool $success, string $reason = ''): void
{
    if (!pd_security_ensure_schema($db)) {
        return;
    }
    try {
        $stmt = $db->prepare("INSERT INTO login_attempts (username, ip_address, user_agent, success, reason)
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            mb_substr($username, 0, 100),
            pd_client_ip(),
            mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            $success ? 1 : 0,
            $reason !== '' ? mb_substr($reason, 0, 40) : null,
        ]);
        // Oude regels opruimen (zelden, ongeveer 1 op de 50 pogingen).
        if (random_int(1, 50) === 1) {
            $db->prepare("DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL " . (int)PD_LOGIN_LOG_RETENTION_DAYS . " DAY)")
               ->execute();
        }
    } catch (Throwable $e) {
        error_log('pd_login_record: ' . $e->getMessage());
    }
}

/**
 * Hoeveel seconden moet deze bezoeker nog wachten voordat er weer ingelogd mag worden?
 * 0 = niet geblokkeerd. Blokkeert per IP + gebruikersnaam (5 mislukte pogingen sinds de
 * laatste geslaagde inlog, duur 15 min, verdubbelt per 5 extra pogingen, maximaal 24 uur)
 * en per IP in totaal (20 mislukte pogingen in 15 minuten).
 *
 * De gebruikersnaam hoeft niet te bestaan: een onbekende naam wordt precies zo geteld,
 * zodat de melding niets verraadt over bestaande accounts.
 */
function pd_login_wait_seconds(PDO $db, string $username): int
{
    if (!pd_security_ensure_schema($db)) {
        return 0;
    }
    $ip = pd_client_ip();
    $username = mb_substr($username, 0, 100);
    try {
        $wait = 0;

        // 1) IP + gebruikersnaam: mislukte pogingen na de laatste geslaagde inlog (max. 24 uur terug)
        $stmt = $db->prepare("SELECT COUNT(*) AS fails, UNIX_TIMESTAMP(MAX(created_at)) AS last_fail
            FROM login_attempts
            WHERE ip_address = ? AND username = ? AND success = 0 AND COALESCE(reason, '') <> 'blocked'
              AND created_at > (NOW() - INTERVAL 24 HOUR)
              AND created_at > COALESCE(
                    (SELECT MAX(created_at) FROM login_attempts WHERE ip_address = ? AND username = ? AND success = 1),
                    '1970-01-01')");
        $stmt->execute([$ip, $username, $ip, $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $fails = (int)($row['fails'] ?? 0);
        if ($fails >= PD_LOGIN_MAX_FAILS) {
            $steps = intdiv($fails - PD_LOGIN_MAX_FAILS, PD_LOGIN_MAX_FAILS);
            $lockMinutes = min(PD_LOGIN_LOCK_MINUTES * (2 ** min($steps, 7)), 1440);
            $until = (int)$row['last_fail'] + $lockMinutes * 60;
            $wait = max($wait, $until - time());
        }

        // 2) Alleen IP: te veel mislukte pogingen in 15 minuten, met welke naam dan ook
        $stmt = $db->prepare("SELECT COUNT(*) AS fails, UNIX_TIMESTAMP(MAX(created_at)) AS last_fail
            FROM login_attempts
            WHERE ip_address = ? AND success = 0 AND COALESCE(reason, '') <> 'blocked'
              AND created_at > (NOW() - INTERVAL 15 MINUTE)");
        $stmt->execute([$ip]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ((int)($row['fails'] ?? 0) >= PD_LOGIN_MAX_FAILS_PER_IP) {
            $until = (int)$row['last_fail'] + PD_LOGIN_LOCK_MINUTES * 60;
            $wait = max($wait, $until - time());
        }

        return max(0, $wait);
    } catch (Throwable $e) {
        error_log('pd_login_wait_seconds: ' . $e->getMessage());
        return 0; // bij een fout nooit iemand buitensluiten
    }
}

/** Nederlandse melding voor een geblokkeerde bezoeker. */
function pd_login_blocked_message(int $waitSeconds): string
{
    $minutes = max(1, (int)ceil($waitSeconds / 60));
    if ($minutes >= 120) {
        $when = (int)ceil($minutes / 60) . ' uur';
    } else {
        $when = $minutes . ($minutes === 1 ? ' minuut' : ' minuten');
    }
    return 'Te veel mislukte pogingen. Probeer het over ' . $when . ' opnieuw, of neem contact op met de beheerder.';
}

/**
 * Rondt een geslaagde wachtwoordcontrole af: nieuw sessie-ID (tegen sessie-overname),
 * sessiegegevens, laatste inlog, sessie-tracking en de vlag voor verplichte wachtwoordwijziging.
 *
 * @param array $user rij uit users met id, username, display_name, role en (optioneel) must_change_password
 */
function pd_login_start_session(PDO $db, array $user): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id']      = $user['id'];
    $_SESSION['username']     = $user['username'];
    $_SESSION['display_name'] = $user['display_name'];
    $_SESSION['role']         = $user['role'];
    unset($_SESSION['kiosk_login']);

    if (!empty($user['must_change_password'])) {
        $_SESSION['must_change_password'] = true;
    } else {
        unset($_SESSION['must_change_password']);
    }
    $_SESSION['pd_mcp_checked'] = time();
}

/** Cookie voor "ingelogd blijven" zetten of wissen, met Secure/HttpOnly/SameSite. */
function pd_set_remember_cookie(string $name, string $value, int $expires): void
{
    setcookie($name, $value, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => pd_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Staat deze aanvraag toe terwijl een wachtwoordwijziging verplicht is?
 * Alleen de wijzigpagina zelf, uitloggen en de sessiecontrole van de browser.
 */
function pd_password_change_allowed_script(): bool
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = basename($script);
    if (in_array($base, ['force_password_change.php', 'logout.php'], true)) {
        return true;
    }
    return in_array($base, ['heartbeat.php', 'check_session.php', 'check_session_status.php'], true);
}

/**
 * Dwingt de verplichte wachtwoordwijziging af. Wordt aangeroepen aan het einde van
 * includes/db.php, dus vóór elke pagina en API. Kiosk-sessies zijn uitgezonderd.
 *
 * De vlag in de database wordt hooguit elke 5 minuten opnieuw gelezen, zodat een beheerder
 * die de vlag zet ook voor mensen werkt die al ingelogd zijn, zonder extra query per verzoek.
 */
function pd_enforce_password_change(PDO $db): void
{
    if (empty($_SESSION['user_id']) || !empty($_SESSION['kiosk_login'])) {
        return;
    }
    if (PHP_SAPI === 'cli') {
        return;
    }

    $checked = (int)($_SESSION['pd_mcp_checked'] ?? 0);
    if (time() - $checked > 300) {
        $_SESSION['pd_mcp_checked'] = time();
        try {
            $stmt = $db->prepare("SELECT must_change_password FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$_SESSION['user_id']]);
            $flag = $stmt->fetchColumn();
            if ($flag === false) {
                return;
            }
            if ((int)$flag === 1) {
                $_SESSION['must_change_password'] = true;
            } else {
                unset($_SESSION['must_change_password']);
            }
        } catch (Throwable $e) {
            // Kolom bestaat nog niet (migratie draait pas bij het eerste beheerbezoek): niets afdwingen.
            return;
        }
    }

    if (empty($_SESSION['must_change_password']) || pd_password_change_allowed_script()) {
        return;
    }

    $base = defined('BASE_PATH') ? BASE_PATH : '';
    $target = $base . '/force_password_change.php';
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $isApi = strpos($script, '/api/') !== false
        || (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && strpos($accept, 'text/html') === false)
        || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || (strpos($accept, 'application/json') !== false && strpos($accept, 'text/html') === false);

    if ($isApi) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'  => false,
            'error'    => 'Je moet eerst je wachtwoord wijzigen.',
            'code'     => 'password_change_required',
            'redirect' => $target,
        ]);
        exit;
    }
    header('Location: ' . $target);
    exit;
}

/**
 * Zet na een geslaagde wachtwoordwijziging alles goed: vlag uit, tijdstip vastleggen,
 * "ingelogd blijven"-tokens van deze gebruiker ongeldig maken (andere apparaten moeten
 * opnieuw inloggen) en de sessievlag opruimen.
 */
function pd_password_changed(PDO $db, int $userId, bool $clearRememberTokens = true): void
{
    pd_security_ensure_schema($db);
    try {
        $db->prepare("UPDATE users SET must_change_password = 0, password_changed_at = NOW() WHERE id = ?")
           ->execute([$userId]);
    } catch (Throwable $e) {
        error_log('pd_password_changed: ' . $e->getMessage());
    }
    if ($clearRememberTokens) {
        try {
            $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$userId]);
        } catch (Throwable $e) {
            // tabel bestaat niet op elke installatie
        }
    }
    if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $userId) {
        unset($_SESSION['must_change_password']);
        $_SESSION['pd_mcp_checked'] = time();
    }
}
