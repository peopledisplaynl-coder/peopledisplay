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
// restore-backup.php
session_start();
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['is_admin'])){ http_response_code(401); echo json_encode(['success'=>false,'error'=>'not_authorized']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if(empty($input['backupId'])){ http_response_code(400); echo json_encode(['success'=>false,'error'=>'missing_backupId']); exit; }

$backupsDir = __DIR__ . '/backups';
$id = basename($input['backupId']);
$pattern = $backupsDir . "/config_backup_{$id}.json";
$matched = glob($pattern);
if(empty($matched)){ http_response_code(404); echo json_encode(['success'=>false,'error'=>'not_found']); exit; }
$src = $matched[0];
$cfgFile = __DIR__ . '/config.json';

try {
  // make emergency backup of current
  $emerg = $backupsDir . '/config_emergency_' . time() . '.json';
  if(file_exists($cfgFile)) copy($cfgFile, $emerg);
  // restore
  copy($src, $cfgFile);
  file_put_contents(__DIR__ . '/audit.log', json_encode(['action'=>'restore-backup','user'=>$_SESSION['user'] ?? 'admin','created_at'=>date('c'),'details'=>['restored'=>$src]]) . PHP_EOL, FILE_APPEND | LOCK_EX);
  echo json_encode(['success'=>true,'restored'=>basename($src)]);
} catch(Exception $e){
  http_response_code(500); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
