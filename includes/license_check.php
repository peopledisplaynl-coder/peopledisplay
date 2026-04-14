<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * PeopleDisplay License Check Enforcement
 *
 * CRITICAL: Include this file at the top of EVERY protected page.
 * Place it AFTER session_start() and db.php includes.
 *
 * Usage:
 *   require_once __DIR__ . '/../includes/license_check.php';  // from admin/
 *   require_once __DIR__ . '/includes/license_check.php';     // from root
 *
 * File: /includes/license_check.php
 * Version: 2.0.0
 */

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'license_check.php') {
    die('Direct access not permitted');
}

// Load license functions (safe to double-include)
require_once __DIR__ . '/license.php';

// ============================================================
// Pages that may be accessed without a valid license
// ============================================================
$_pd_exempt = [
    'activate_license.php',
    'install.php',
    'login.php',
    'logout.php',
    'offline.php',
];

$_pd_current = basename($_SERVER['PHP_SELF']);

if (!in_array($_pd_current, $_pd_exempt, true)) {

    if (!isLicenseValid()) {

        $licenseInfo = getLicenseInfo();

        // Determine the redirect reason
        if (!$licenseInfo || empty($licenseInfo['license_key'])) {
            // No key: Starter limits exceeded → prompt upgrade
            $reason = 'starter_limit_exceeded';
        } elseif ($licenseInfo['license_status'] === 'expired') {
            $reason = 'expired';
        } elseif ($licenseInfo['license_status'] === 'revoked') {
            $reason = 'revoked';
        } elseif (($licenseInfo['license_domain'] ?? '') !== getCurrentDomain()) {
            $reason = 'domain_mismatch&registered=' . urlencode($licenseInfo['license_domain'] ?? '');
        } else {
            $reason = 'invalid';
        }

        // Detect root path for redirect (works in subdirectory installs)
        $scriptDir  = dirname($_SERVER['SCRIPT_NAME']);
        $rootPath   = rtrim(str_replace('/admin', '', $scriptDir), '/');
        $activateUrl = $rootPath . '/activate_license.php?reason=' . $reason;

        header('Location: ' . $activateUrl);
        exit;
    }

    // License is valid — expose info to calling page via globals
    $GLOBALS['current_license'] = getLicenseInfo();
    $GLOBALS['tier_limits']     = getTierLimits();
}

// ============================================================
// Helper: block access if a feature is not in the active tier
// ============================================================

/**
 * Redirect to license management if the feature is not available.
 *
 * @param  string $feature_key   e.g. 'visitor_management', 'kiosk_mode'
 * @param  bool   $redirect      If false, returns bool instead of redirecting.
 * @return bool                  Always true if feature is available.
 */
function requireFeature(string $feature_key, bool $redirect = true): bool {
    if (!hasFeature($feature_key)) {
        if ($redirect) {
            $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
            $rootPath  = rtrim(str_replace('/admin', '', $scriptDir), '/');
            header('Location: ' . $rootPath . '/admin/license_management.php?error=feature_not_available&feature=' . urlencode($feature_key));
            exit;
        }
        return false;
    }
    return true;
}
