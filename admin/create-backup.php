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
// create-backup.php
session_start();
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['is_admin'])){ http_response_code(401); echo json_encode(['success'=>false]); exit; }

try {
  $cfgFile = __DIR__ . '/config.json';
  $backupsDir = __DIR__ . '/backups';
  if(!is_dir($backupsDir)) mkdir($backupsDir,0750,true);
  $current = file_exists($cfgFile) ? file_get_contents($cfgFile) : '{}';
  $id = time() . '_' . bin2hex(random_bytes(4));
  $fn = "config_backup_{$id}.json";
  file_put_contents($backupsDir . '/' . $fn, $current, LOCK_EX);
  $now = date('c');
  $auditFile = __DIR__ . '/audit.log';
  file_put_contents($auditFile, json_encode(['action'=>'create-backup','user'=>$_SESSION['user'] ?? 'admin','created_at'=>$now,'details'=>['file'=>$fn]]) . PHP_EOL, FILE_APPEND | LOCK_EX);
  echo json_encode(['success'=>true,'id'=>$id,'filename'=>$fn]);
} catch(Exception $e){
  http_response_code(500); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
