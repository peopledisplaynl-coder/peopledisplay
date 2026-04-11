<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_start();
}
<?php
// delete-backup.php?file=config.20251010-0930.json
$filename = $_GET['file'] ?? '';
$path = __DIR__ . '/backups/' . basename($filename);

if (!file_exists($path)) {
  echo "❌ Bestand niet gevonden.";
  exit;
}

if (unlink($path)) {
  echo "🗑️ Backup verwijderd: $filename";
} else {
  echo "❌ Verwijderen mislukt.";
}
