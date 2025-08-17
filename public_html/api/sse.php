<?php
// public_html/api/sse.php — Server-Sent Events
// Работает на шаред-хостинге. Браузер будет переподключаться сам.

ini_set('default_socket_timeout', -1);
set_time_limit(0);
ignore_user_abort(true);

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// autodetect APP_ROOT: внутри public_html или на уровень выше
$docroot = dirname(__DIR__);  // .../public_html
$above   = dirname($docroot);
$APP_ROOT = (file_exists($docroot.'/config/config.php') && is_dir($docroot.'/src')) ? $docroot
           : ((file_exists($above.'/config/config.php') && is_dir($above.'/src')) ? $above : $docroot);

require_once $APP_ROOT.'/src/Database.php';
$cfg = require $APP_ROOT.'/config/config.php';
$db  = (new Database($cfg))->pdo();

$lastId  = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$started = time();
$timeout = 25; // держим соединение 25 сек, затем клиент переподключится

while (!connection_aborted() && (time() - $started) < $timeout) {
  $st = $db->prepare("SELECT id, entity, action, entity_id, payload, UNIX_TIMESTAMP(created_at) AS ts
                      FROM sync_events WHERE id > ? ORDER BY id ASC");
  $st->execute(array($lastId));
  $rows = $st->fetchAll();

  foreach ($rows as $row) {
    $lastId = (int)$row['id'];
    $data = array(
      'entity'    => $row['entity'],
      'action'    => $row['action'],
      'entity_id' => (int)$row['entity_id'],
      'ts'        => (int)$row['ts']
    );
    if (!empty($row['payload'])) {
      $pl = json_decode($row['payload'], true);
      if ($pl !== null) $data['payload'] = $pl;
    }
    // Идентификатор события — для восстановления после reconnection
    echo "id: ".$lastId."\n";
    // Именованные события: deals.update, contacts.create, ...
    echo "event: ".$row['entity'].".".$row['action']."\n";
    echo "data: ".json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";
  }

  @ob_flush(); @flush();

  if ($rows) { // если были события — сразу проверим ещё раз
    continue;
  }

  // Пинг, чтобы соединение не простаивало
  echo ": ping\n\n";
  @ob_flush(); @flush();
  sleep(2);
}

// корректное завершение — клиент переподключится
echo ": bye\n\n";
@ob_flush(); @flush();
