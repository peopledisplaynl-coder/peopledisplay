<?php
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
$backupDir = __DIR__ . '/backups';
$zipName = 'config-backups-' . date('Ymd-His') . '.zip';
$zipPath = __DIR__ . '/' . $zipName;

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
  die("❌ Kan ZIP niet aanmaken.");
}

$files = glob($backupDir . '/config.*.json');
foreach ($files as $file) {
  $zip->addFile($file, basename($file));
}
$zip->close();

echo "✅ ZIP aangemaakt: <a href='$zipName' download>$zipName</a>";
