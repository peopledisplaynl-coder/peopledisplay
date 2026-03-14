<?php
// includes/config.php
// Dit bestand wordt geladen door admin bestanden die __DIR__ . '/../includes/config.php' doen

// Laad db.php (die zit in dezelfde map als dit bestand)
require_once __DIR__ . '/db.php';

// Config array vullen vanuit database
$config = [];

if (!isset($db) || !($db instanceof PDO)) {
    die('⛔ Geen geldige databaseverbinding in includes/config.php');
}

try {
    $stmt = $db->query("SELECT * FROM config WHERE id = 1 LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        die('⛔ Geen configuratieregel gevonden in de database');
    }

    $config = $row;
    
    // Parse JSON velden
    if (isset($config['visibleFields']) && is_string($config['visibleFields'])) {
        $config['visibleFields'] = json_decode($config['visibleFields'], true) ?? [];
    }
    if (isset($config['locations']) && is_string($config['locations'])) {
        $config['locations'] = json_decode($config['locations'], true) ?? [];
    }
    if (isset($config['extraButtons']) && is_string($config['extraButtons'])) {
        $config['extraButtons'] = json_decode($config['extraButtons'], true) ?? [];
    }

} catch (Throwable $e) {
    error_log('includes/config.php error: ' . $e->getMessage());
    die('⛔ Fout bij laden van configuratie: ' . $e->getMessage());
}

// Helper functie
function cfg(string $key, $default = '') {
    global $config;
    return $config[$key] ?? $default;
}