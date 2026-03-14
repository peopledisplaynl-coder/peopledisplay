<?php
/**
 * PeopleDisplay License Management System
 * CRITICAL: This file handles license validation and enforcement
 * File: /includes/license.php
 * Version: 2.0.0
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

define('PD_LICENSE_SALT', 'PEOPLEDISPLAY_SALT_2024');

// ============================================================
// 1. isLicenseValid()
// ============================================================

/**
 * Check if a currently installed license is valid and active.
 * @return bool
 */
function isLicenseValid(): bool {
    global $db;
    try {
        $stmt = $db->prepare(
            "SELECT license_key, license_status, license_domain, license_expires_at
             FROM config WHERE id = 1 LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['license_key'])) {
            return false;
        }
        if ($row['license_status'] !== 'active') {
            return false;
        }
        if ($row['license_domain'] !== getCurrentDomain()) {
            return false;
        }
        if (!empty($row['license_expires_at']) && strtotime($row['license_expires_at']) < time()) {
            return false;
        }
        return true;
    } catch (Exception $e) {
        error_log('[PD License] isLicenseValid: ' . $e->getMessage());
        return false;
    }
}

// ============================================================
// 2. getLicenseInfo()
// ============================================================

/**
 * Get complete license info joined with tier data.
 * @return array|null
 */
function getLicenseInfo(): ?array {
    global $db;
    try {
        $stmt = $db->prepare("
            SELECT
                c.license_key, c.license_tier, c.license_domain,
                c.license_activated_at, c.license_expires_at,
                c.license_status, c.license_notes,
                lt.tier_name, lt.tier_description,
                lt.max_users, lt.max_employees,
                lt.max_locations, lt.max_departments,
                lt.features, lt.price_eur
            FROM config c
            LEFT JOIN license_tiers lt ON c.license_tier = lt.tier_code
            WHERE c.id = 1
            LIMIT 1
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['license_key'])) {
            return null;
        }

        $row['features_array'] = [];
        if (!empty($row['features'])) {
            $decoded = json_decode($row['features'], true);
            if (is_array($decoded)) {
                $row['features_array'] = $decoded;
            }
        }

        return $row;
    } catch (Exception $e) {
        error_log('[PD License] getLicenseInfo: ' . $e->getMessage());
        return null;
    }
}

// ============================================================
// 3. getTierLimits()
// ============================================================

/**
 * Get usage limits from the active license tier.
 * @return array{max_users:int, max_employees:int, max_locations:int, max_departments:int}
 */
function getTierLimits(): array {
    $zero = [
        'max_users'       => 0,
        'max_employees'   => 0,
        'max_locations'   => 0,
        'max_departments' => 0,
    ];
    $info = getLicenseInfo();
    if (!$info) {
        return $zero;
    }
    return [
        'max_users'       => (int)($info['max_users']       ?? 0),
        'max_employees'   => (int)($info['max_employees']   ?? 0),
        'max_locations'   => (int)($info['max_locations']   ?? 0),
        'max_departments' => (int)($info['max_departments'] ?? 0),
    ];
}

// ============================================================
// 4. hasFeature()
// ============================================================

/**
 * Check whether the active license tier includes a given feature.
 * @param string $feature_key
 * @return bool
 */
function hasFeature(string $feature_key): bool {
    $info = getLicenseInfo();
    if (!$info) {
        return false;
    }
    return !empty($info['features_array'][$feature_key]);
}

// ============================================================
// 5-8. canAdd*() limit checks
// ============================================================

/**
 * @return bool  True if adding one more user is within the tier limit.
 */
function canAddUser(): bool {
    global $db;
    $limits = getTierLimits();
    if ($limits['max_users'] <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE active = 1 AND deleted_at IS NULL");
        $stmt->execute();
        return (int)$stmt->fetchColumn() < $limits['max_users'];
    } catch (Exception $e) {
        error_log('[PD License] canAddUser: ' . $e->getMessage());
        return false;
    }
}

/**
 * @return bool  True if adding one more employee is within the tier limit.
 */
function canAddEmployee(): bool {
    global $db;
    $limits = getTierLimits();
    if ($limits['max_employees'] <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM employees WHERE actief = 1");
        $stmt->execute();
        return (int)$stmt->fetchColumn() < $limits['max_employees'];
    } catch (Exception $e) {
        error_log('[PD License] canAddEmployee: ' . $e->getMessage());
        return false;
    }
}

/**
 * @return bool  True if adding one more location is within the tier limit.
 */
function canAddLocation(): bool {
    global $db;
    $limits = getTierLimits();
    if ($limits['max_locations'] <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM locations WHERE active = 1");
        $stmt->execute();
        return (int)$stmt->fetchColumn() < $limits['max_locations'];
    } catch (Exception $e) {
        error_log('[PD License] canAddLocation: ' . $e->getMessage());
        return false;
    }
}

/**
 * @return bool  True if adding one more department is within the tier limit.
 */
function canAddDepartment(): bool {
    global $db;
    $limits = getTierLimits();
    if ($limits['max_departments'] <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM afdelingen WHERE active = 1");
        $stmt->execute();
        return (int)$stmt->fetchColumn() < $limits['max_departments'];
    } catch (Exception $e) {
        error_log('[PD License] canAddDepartment: ' . $e->getMessage());
        return false;
    }
}

// ============================================================
// 9. getCurrentDomain()
// ============================================================

/**
 * Return the canonical domain: lowercase, no www., no port.
 * @return string
 */
function getCurrentDomain(): string {
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    // Strip port number
    if (strpos($host, ':') !== false) {
        $host = explode(':', $host)[0];
    }
    // Strip leading www.
    if (strncasecmp($host, 'www.', 4) === 0) {
        $host = substr($host, 4);
    }
    return strtolower(trim($host));
}

// ============================================================
// 10. validateLicenseKey()
// ============================================================

/**
 * Validate a license key and extract its tier code.
 *
 * Format: PDIS-T001-RAND-HASH
 *   PDIS  = fixed prefix
 *   T     = tier letter (S/P/B/E/C/U)
 *   001   = 3-digit sequence (001-999)
 *   RAND  = 4 alphanumeric chars (A-Z0-9)
 *   HASH  = 4-char checksum (generateLicenseChecksum of "PDIS-T001-RAND")
 *
 * @param  string $key
 * @return array{valid:bool, tier:string, error:string}
 */
function validateLicenseKey(string $key): array {
    $key   = strtoupper(trim($key));
    $parts = explode('-', $key);

    if (count($parts) !== 4) {
        return ['valid' => false, 'tier' => '', 'error' => 'Ongeldig formaat — verwacht PDIS-T001-RAND-HASH'];
    }

    [$prefix, $tseq, $rand, $hash] = $parts;

    if ($prefix !== 'PDIS') {
        return ['valid' => false, 'tier' => '', 'error' => 'Ongeldig prefix — sleutel moet beginnen met PDIS'];
    }

    // T+SEQ: one letter + exactly 3 digits
    if (!preg_match('/^([SPBECU])([0-9]{3})$/', $tseq, $m)) {
        return ['valid' => false, 'tier' => '', 'error' => 'Ongeldig tier/reeks segment (bijv. S001)'];
    }
    $tierLetter = $m[1];
    $sequence   = (int)$m[2];

    if ($sequence < 1 || $sequence > 999) {
        return ['valid' => false, 'tier' => '', 'error' => 'Reeksnummer buiten bereik (001-999)'];
    }

    // RAND: exactly 4 chars A-Z0-9
    if (!preg_match('/^[A-Z0-9]{4}$/', $rand)) {
        return ['valid' => false, 'tier' => '', 'error' => 'Ongeldig willekeurig segment — 4 tekens A-Z0-9 verwacht'];
    }

    // Verify checksum
    $prefix_str    = "PDIS-{$tseq}-{$rand}";
    $expectedHash  = generateLicenseChecksum($prefix_str);
    if ($hash !== $expectedHash) {
        return ['valid' => false, 'tier' => '', 'error' => 'Ongeldige licentiesleutel — checksum onjuist'];
    }

    // Map tier letter → tier_code
    $tierMap = [
        'S' => 'starter',
        'P' => 'professional',
        'B' => 'business',
        'E' => 'enterprise',
        'C' => 'custom',
        'U' => 'unlimited',
    ];
    $tier = $tierMap[$tierLetter] ?? '';
    if ($tier === '') {
        return ['valid' => false, 'tier' => '', 'error' => 'Onbekende tier letter: ' . $tierLetter];
    }

    return ['valid' => true, 'tier' => $tier, 'error' => ''];
}

// ============================================================
// 11. generateLicenseChecksum()
// ============================================================

/**
 * Generate a 4-character uppercase checksum from a key prefix.
 * @param  string $data  The key prefix, e.g. "PDIS-S001-TEST"
 * @return string        4 uppercase hex chars
 */
function generateLicenseChecksum(string $data): string {
    return strtoupper(substr(md5($data . PD_LICENSE_SALT), 0, 4));
}

// ============================================================
// 12. activateLicense()
// ============================================================

/**
 * Activate a license key on this installation.
 * @param  string $key
 * @return array{success:bool, error:string, tier:string}
 */
function activateLicense(string $key): array {
    global $db;

    $validation = validateLicenseKey($key);
    if (!$validation['valid']) {
        logLicenseAction($key, 'failed', getCurrentDomain(), 'Validatiefout: ' . $validation['error']);
        return ['success' => false, 'error' => $validation['error'], 'tier' => ''];
    }

    $tier   = $validation['tier'];
    $domain = getCurrentDomain();

    // Confirm tier exists and is active in DB
    try {
        $stmt = $db->prepare("SELECT id FROM license_tiers WHERE tier_code = ? AND active = 1 LIMIT 1");
        $stmt->execute([$tier]);
        if (!$stmt->fetch()) {
            $err = "Tier '{$tier}' bestaat niet of is inactief";
            logLicenseAction($key, 'failed', $domain, $err);
            return ['success' => false, 'error' => $err, 'tier' => $tier];
        }
    } catch (Exception $e) {
        error_log('[PD License] activateLicense tier check: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Databasefout bij activering', 'tier' => ''];
    }

    // Write license data to config
    try {
        $stmt = $db->prepare("
            UPDATE config SET
                license_key          = ?,
                license_tier         = ?,
                license_domain       = ?,
                license_activated_at = NOW(),
                license_expires_at   = NULL,
                license_status       = 'active',
                license_notes        = NULL
            WHERE id = 1
        ");
        $stmt->execute([$key, $tier, $domain]);
    } catch (Exception $e) {
        error_log('[PD License] activateLicense update: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Databasefout bij opslaan licentie', 'tier' => ''];
    }

    logLicenseAction($key, 'activated', $domain, "Tier: {$tier}");
    return ['success' => true, 'error' => '', 'tier' => $tier];
}

// ============================================================
// 13. deactivateLicense()
// ============================================================

/**
 * Deactivate the current license (clear all license fields).
 * @return bool
 */
function deactivateLicense(): bool {
    global $db;

    $info = getLicenseInfo();
    $key  = $info['license_key'] ?? '';

    try {
        $stmt = $db->prepare("
            UPDATE config SET
                license_key          = NULL,
                license_tier         = NULL,
                license_domain       = NULL,
                license_activated_at = NULL,
                license_expires_at   = NULL,
                license_status       = 'pending',
                license_notes        = NULL
            WHERE id = 1
        ");
        $stmt->execute();
        logLicenseAction($key, 'deactivated', getCurrentDomain(), 'Handmatige deactivering');
        return true;
    } catch (Exception $e) {
        error_log('[PD License] deactivateLicense: ' . $e->getMessage());
        return false;
    }
}

// ============================================================
// 14. logLicenseAction()
// ============================================================

/**
 * Write a license event to the license_log table.
 * @param string $key
 * @param string $action  Must match license_log.action ENUM.
 * @param string $domain
 * @param string $details
 */
function logLicenseAction(string $key, string $action, string $domain, string $details = ''): void {
    global $db;

    $allowed = ['activated', 'deactivated', 'validated', 'failed', 'upgraded', 'expired'];
    if (!in_array($action, $allowed, true)) {
        $action = 'failed';
    }

    $ip        = $_SERVER['REMOTE_ADDR']     ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    try {
        $stmt = $db->prepare("
            INSERT INTO license_log (license_key, action, domain, ip_address, user_agent, details)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$key ?: null, $action, $domain, $ip, $userAgent, $details]);
    } catch (Exception $e) {
        error_log('[PD License] logLicenseAction: ' . $e->getMessage());
    }
}

// ============================================================
// 15. getLicenseUsageStats()
// ============================================================

/**
 * Return current usage counts vs. tier limits for all four resource types.
 *
 * @return array{
 *   users:       array{current:int, limit:int, available:int, percentage:float},
 *   employees:   array{current:int, limit:int, available:int, percentage:float},
 *   locations:   array{current:int, limit:int, available:int, percentage:float},
 *   departments: array{current:int, limit:int, available:int, percentage:float}
 * }
 */
function getLicenseUsageStats(): array {
    global $db;

    $limits = getTierLimits();
    $counts = ['users' => 0, 'employees' => 0, 'locations' => 0, 'departments' => 0];

    try {
        $counts['users']       = (int)$db->query("SELECT COUNT(*) FROM users      WHERE active = 1 AND deleted_at IS NULL")->fetchColumn();
        $counts['employees']   = (int)$db->query("SELECT COUNT(*) FROM employees  WHERE actief  = 1")->fetchColumn();
        $counts['locations']   = (int)$db->query("SELECT COUNT(*) FROM locations  WHERE active  = 1")->fetchColumn();
        $counts['departments'] = (int)$db->query("SELECT COUNT(*) FROM afdelingen WHERE active  = 1")->fetchColumn();
    } catch (Exception $e) {
        error_log('[PD License] getLicenseUsageStats: ' . $e->getMessage());
    }

    $stat = static function (int $current, int $limit): array {
        $available  = max(0, $limit - $current);
        $percentage = $limit > 0 ? round(($current / $limit) * 100, 1) : 0.0;
        return compact('current', 'limit', 'available', 'percentage');
    };

    return [
        'users'       => $stat($counts['users'],       $limits['max_users']),
        'employees'   => $stat($counts['employees'],   $limits['max_employees']),
        'locations'   => $stat($counts['locations'],   $limits['max_locations']),
        'departments' => $stat($counts['departments'], $limits['max_departments']),
    ];
}

// ============================================================
// 16. getAllLicenseTiers()
// ============================================================

/**
 * Return all active license tiers ordered by sort_order.
 * @return array[]
 */
function getAllLicenseTiers(): array {
    global $db;
    try {
        $stmt = $db->query("SELECT * FROM license_tiers WHERE active = 1 ORDER BY sort_order ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['features_array'] = [];
            if (!empty($row['features'])) {
                $decoded = json_decode($row['features'], true);
                if (is_array($decoded)) {
                    $row['features_array'] = $decoded;
                }
            }
        }
        unset($row);
        return $rows;
    } catch (Exception $e) {
        error_log('[PD License] getAllLicenseTiers: ' . $e->getMessage());
        return [];
    }
}

// ============================================================
// 17. getLimitExceededMessage()
// ============================================================

/**
 * Build a structured alert data array for a license limit exceeded event.
 *
 * @param  string $type           One of: users, employees, locations, departments
 * @param  int    $current_limit  The tier's limit that was hit (e.g. 3 for max_locations=3)
 * @return array{icon:string, title:string, message:string, upgradeMessage:string}
 */
function getLimitExceededMessage(string $type, int $current_limit): array {
    $info      = getLicenseInfo();
    $tierName  = $info['tier_name'] ?? 'huidig';

    $icons  = ['users' => '👤', 'employees' => '👥', 'locations' => '🏢', 'departments' => '📁'];
    $labels = ['users' => 'gebruikers', 'employees' => 'medewerkers', 'locations' => 'locaties', 'departments' => 'afdelingen'];

    $icon  = $icons[$type]  ?? '🔒';
    $label = $labels[$type] ?? $type;

    $nextTier       = getNextTierFor($type, $current_limit);
    $upgradeMessage = $nextTier
        ? "Upgrade naar <strong>{$nextTier['name']}</strong> voor {$nextTier['limit']} {$label}."
        : 'Neem contact op voor een hoger pakket.';

    return [
        'icon'           => $icon,
        'title'          => ucfirst($label) . ' limiet bereikt',
        'message'        => "U heeft het maximum aantal {$label} ({$current_limit}) voor het <strong>{$tierName}</strong> pakket bereikt.",
        'upgradeMessage' => $upgradeMessage,
    ];
}

// ============================================================
// 18. getNextTierFor()
// ============================================================

/**
 * Return the cheapest tier that has a higher limit for $type than $current_limit.
 *
 * @param  string $type           One of: users, employees, locations, departments
 * @param  int    $current_limit
 * @return array{name:string, limit:int, price_eur:float}|null
 */
function getNextTierFor(string $type, int $current_limit): ?array {
    global $db;

    $allowed = ['users', 'employees', 'locations', 'departments'];
    if (!in_array($type, $allowed, true)) {
        return null;
    }

    $column = 'max_' . $type;   // safe: only whitelisted values

    try {
        $stmt = $db->prepare("
            SELECT tier_name AS name, {$column} AS `limit`, price_eur
            FROM   license_tiers
            WHERE  {$column} > ? AND active = 1
            ORDER  BY {$column} ASC
            LIMIT  1
        ");
        $stmt->execute([$current_limit]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Exception $e) {
        error_log('[PD License] getNextTierFor: ' . $e->getMessage());
        return null;
    }
}

// ============================================================
// Legacy compatibility shims (from stub license.php)
// ============================================================

function pd_check_license(): array {
    $valid = isLicenseValid();
    $info  = getLicenseInfo();
    return [
        'valid'   => $valid,
        'type'    => $info['license_tier'] ?? 'none',
        'expires' => $info['license_expires_at'] ?? null,
    ];
}

function pd_can_add_user():       bool { return canAddUser();       }
function pd_can_add_employee():   bool { return canAddEmployee();   }
function pd_can_add_location():   bool { return canAddLocation();   }
function pd_can_add_department(): bool { return canAddDepartment(); }
