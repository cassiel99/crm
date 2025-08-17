<?php

ini_set('display_errors', '1');
error_reporting(E_ALL);

$docroot = __DIR__;             
$above   = dirname($docroot);    

$candidates = [
  $docroot,             
  $above,                         
];

$APP_ROOT = null;
foreach ($candidates as $base) {
  if (is_dir($base.'/src') && is_dir($base.'/config') && file_exists($base.'/config/config.php')) {
    $APP_ROOT = $base;
    break;
  }
}

if (!$APP_ROOT) {
  header('Content-Type: text/plain; charset=utf-8', true, 500);
  echo "Не найден config/ или src/\nИскал в путях:\n";
  foreach ($candidates as $base) {
    echo " - $base/config/config.php (".(file_exists($base.'/config/config.php')?'есть':'нет').")\n";
    echo " - $base/src (".(is_dir($base.'/src')?'есть':'нет').")\n";
  }
  exit;
}

require $APP_ROOT.'/config/config.php';
require $APP_ROOT.'/src/Database.php';

try {
  $cfg = require $APP_ROOT.'/config/config.php';
  $pdo = (new Database($cfg))->pdo();
  $ok  = $pdo->query('SELECT 1')->fetchColumn();

  header('Content-Type: text/plain; charset=utf-8');
  echo "DB OK (PHP ".PHP_VERSION.") — SELECT 1 = $ok\n";
  foreach (['deals','contacts','deal_contacts'] as $t) {
    $exists = $pdo->query("SHOW TABLES LIKE '$t'")->fetchColumn() ? 'exists' : 'missing';
    echo "$t: $exists\n";
  }
} catch (Throwable $e) {
  header('Content-Type: text/plain; charset=utf-8', true, 500);
  echo "DB ERROR: ".$e->getMessage();
}
