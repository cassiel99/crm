<?php
class Sync {
  public static function emit($db, $entity, $action, $entityId, $payload = null){
    try {
      $st = $db->prepare("INSERT INTO sync_events(entity,action,entity_id,payload) VALUES(?,?,?,?)");
      $st->execute(array(
        (string)$entity,
        (string)$action,
        (int)$entityId,
        $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null
      ));
    } catch (Throwable $e) {
    }
  }
}
