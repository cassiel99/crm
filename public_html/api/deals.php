<?php
// public_html/api/deals.php
declare(strict_types=1);

/* ===== DEBUG: включено для диагностики. Уберите в проде. ===== */
ini_set('display_errors', '1');
error_reporting(E_ALL);
register_shutdown_function(function () {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
    header('Content-Type: application/json; charset=utf-8', true, 500);
    echo json_encode(['error' => "FATAL: {$e['message']} @ {$e['file']}:{$e['line']}"], JSON_UNESCAPED_UNICODE);
  }
});
/* ============================================================ */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Самоопределение APP_ROOT — либо public_html, либо уровень выше
$docroot = dirname(__DIR__);
$above   = dirname($docroot);
$APP_ROOT = (file_exists($docroot.'/config/config.php') && is_dir($docroot.'/src'))
          ? $docroot
          : ((file_exists($above.'/config/config.php') && is_dir($above.'/src')) ? $above : $docroot);
define('APP_ROOT', $APP_ROOT);

require_once APP_ROOT . '/src/Database.php';
require_once APP_ROOT . '/src/Repositories/DealRepository.php';
if (is_file(APP_ROOT . '/src/Controllers/DealController.php')) {
  require_once APP_ROOT . '/src/Controllers/DealController.php';
}

$cfg  = require APP_ROOT . '/config/config.php';
$db   = (new Database($cfg))->pdo();
$repo = new DealRepository($db);
$ctl  = class_exists('DealController') ? new DealController($repo) : null;

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

function readJson(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}
function respond($data, int $code = 200): void {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  if ($method === 'GET') {
    if ($id) {
      $data = $ctl ? $ctl->show($id) : $repo->get($id);
      if (!$data) throw new RuntimeException('Not found', 404);
      respond($data);
    }
    respond($ctl ? $ctl->index() : $repo->all());
  }

  if ($method === 'POST') {
    $b = readJson();
    if ($ctl) {
      $newId = $ctl->create($b);
    } else {
      $title = trim((string)($b['title'] ?? ''));
      if ($title === '') throw new InvalidArgumentException('title required', 400);
      $amount = isset($b['amount']) ? (float)$b['amount'] : 0.0;
      if ($amount < 0) throw new InvalidArgumentException('amount >= 0', 400);
      $contactIds = isset($b['contact_ids']) && is_array($b['contact_ids'])
        ? array_values(array_unique(array_map('intval', $b['contact_ids'])))
        : [];
      $newId = $repo->create($title, $amount, $contactIds);
    }
    respond(['id' => $newId], 201);
  }

  if ($method === 'PUT' && $id) {
    $b = readJson();
    if ($ctl) {
      $ctl->update($id, $b);
    } else {
      $title  = array_key_exists('title',  $b) ? trim((string)$b['title']) : null;
      if ($title !== null && $title === '') throw new InvalidArgumentException('title cannot be empty', 400);
      $amount = array_key_exists('amount', $b) ? (float)$b['amount'] : null;
      if ($amount !== null && $amount < 0) throw new InvalidArgumentException('amount >= 0', 400);
      $contactIds = array_key_exists('contact_ids', $b)
        ? array_values(array_unique(array_map('intval', (array)$b['contact_ids'])))
        : null;
      $repo->update($id, $title, $amount, $contactIds);
    }
    respond(['ok' => true]);
  }

  if ($method === 'DELETE' && $id) {
    if ($ctl) $ctl->delete($id); else $repo->delete($id);
    respond(['ok' => true]);
  }

  respond(['error' => 'method not allowed'], 405);

} catch (InvalidArgumentException $e) {
  respond(['error' => $e->getMessage()], 400);
} catch (RuntimeException $e) {
  $code = $e->getCode() >= 400 ? $e->getCode() : 404;
  respond(['error' => $e->getMessage()], $code);
} catch (Throwable $e) {
  respond(['error' => $e->getMessage()], 500);
}
