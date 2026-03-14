<?php
/**
 * BESTANDSNAAM:  update_check.php
 * UPLOAD NAAR:   /includes/update_check.php
 */
declare(strict_types=1);

define('PD_VERSION_URL', 'https://admin.peopledisplay.nl/updates/version.json');

// Huidige versie — wordt automatisch bijgewerkt bij elke update
if (!defined('PD_CURRENT_VERSION')) {
    $versionFile = __DIR__ . '/version.php';
    if (file_exists($versionFile)) {
        require_once $versionFile;
    } else {
        define('PD_CURRENT_VERSION', '2.0.0');
    }
}

/**
 * Controleer op updates
 * Geeft array terug in formaat dat dashboard.php verwacht:
 *   ['available', 'version', 'critical', 'message', 'changelog_url', 'download_url']
 */
function checkForUpdates(): array
{
    $cacheFile = sys_get_temp_dir() . '/pd_update_cache.json';
    $cacheTTL  = 43200; // 12 uur

    // Gebruik cache als recent genoeg
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached) {
            return buildUpdateInfo($cached);
        }
    }

    // Haal versie op van admin server
    $ctx = stream_context_create([
        'http' => [
            'timeout'       => 4,
            'ignore_errors' => true,
        ]
    ]);

    $response = @file_get_contents(PD_VERSION_URL, false, $ctx);
    if (!$response) {
        return ['available' => false]; // Stil falen
    }

    $data = json_decode($response, true);
    if (!$data || empty($data['version'])) {
        return ['available' => false];
    }

    // Cache opslaan
    file_put_contents($cacheFile, $response);

    return buildUpdateInfo($data);
}

/**
 * Bouw het $updateInfo array zoals dashboard.php het verwacht
 */
function buildUpdateInfo(array $remote): array
{
    $currentVersion = PD_CURRENT_VERSION;

    if (!version_compare($remote['version'], $currentVersion, '>')) {
        return ['available' => false];
    }

    // Changelog van alleen de nieuwe versies samenvatten
    $changelog = $remote['changelog'] ?? [];
    $newChanges = [];
    foreach ($changelog as $release) {
        if (version_compare($release['version'], $currentVersion, '>')) {
            foreach ($release['changes'] as $change) {
                $newChanges[] = $change;
            }
        }
    }

    $message = !empty($newChanges) ? implode(' · ', array_slice($newChanges, 0, 2)) : '';

    return [
        'available'     => true,
        'version'       => $remote['version'],
        'critical'      => $remote['critical'] ?? false,
        'message'       => $message,
        'changelog_url' => 'https://peopledisplay.nl',
        'download_url'  => $remote['download_url'] ?? '',
        'checksum'      => $remote['checksum']      ?? '',
    ];
}
