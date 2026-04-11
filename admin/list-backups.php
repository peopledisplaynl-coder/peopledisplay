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
// list-backups.php
session_start();
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['is_admin'])){ http_response_code(401); echo json_encode([]); exit; }

$dir = __DIR__ . '/backups';
$list = [];
if(is_dir($dir)){
  foreach(scandir($dir, SCANDIR_SORT_DESCENDING) as $f){
    if($f === '.' || $f === '..') continue;
    $path = $dir . '/' . $f;
    if(is_file($path)){
      $list[] = ['id'=>pathinfo($f, PATHINFO_FILENAME),'filename'=>$f,'created_at'=>date('c', filemtime($path)),'filesize'=>filesize($path)];
    }
  }
}
echo json_encode($list);
