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
// backup-download.php
session_start();
if(empty($_SESSION['is_admin'])){ http_response_code(401); echo 'Not authorized'; exit; }
$id = $_GET['backupId'] ?? '';
$backupsDir = __DIR__ . '/backups';
$pattern = $backupsDir . "/config_backup_{$id}.json";
$matched = glob($pattern);
if(empty($matched)){ http_response_code(404); echo 'Not found'; exit; }
$path = $matched[0];
if(!is_file($path)){ http_response_code(404); echo 'Not file'; exit; }

header('Content-Description: File Transfer');
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="'.basename($path).'"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
