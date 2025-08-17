<?php
require_once __DIR__ . '/../Sync.php';
// src/Repositories/DealRepository.php
class DealRepository {
  /** @var PDO */
  private $db;

  public function __construct($db){ $this->db = $db; }

  public function all(){
    return $this->db->query("SELECT id,title,amount FROM deals ORDER BY id DESC")->fetchAll();
  }

  public function get($id){
    $st = $this->db->prepare("SELECT id,title,amount,notes FROM deals WHERE id=?");
    $st->execute(array((int)$id));
    $deal = $st->fetch();
    if(!$deal) return null;

    $st = $this->db->prepare("
      SELECT c.id, c.first_name, c.last_name
      FROM contacts c
      JOIN deal_contacts dc ON dc.contact_id = c.id
      WHERE dc.deal_id = ?
      ORDER BY c.first_name, c.last_name
    ");
    $st->execute(array((int)$id));
    $deal['contacts'] = $st->fetchAll();
    return $deal;
  }

  public function create($title, $amount, $contactIds){
    $st = $this->db->prepare("INSERT INTO deals(title,amount) VALUES(?,?)");
    $st->execute(array($title, (float)$amount));
    $id = (int)$this->db->lastInsertId();
    $this->syncContacts($id, $contactIds);
    return $id;
  }

  public function update($id, $title, $amount, $contactIds){
    if($title !== null || $amount !== null){
      $st = $this->db->prepare("UPDATE deals SET title=COALESCE(?,title), amount=COALESCE(?,amount) WHERE id=?");
      $st->execute(array($title, $amount, (int)$id));
    }
    if(is_array($contactIds)) $this->syncContacts((int)$id, $contactIds);
    return true;
  }

  public function delete($id){
    $st = $this->db->prepare("DELETE FROM deals WHERE id=?");
    return $st->execute(array((int)$id));
  }

  private function syncContacts($dealId, $ids){
    $this->db->prepare("DELETE FROM deal_contacts WHERE deal_id=?")->execute(array((int)$dealId));
    if(!is_array($ids) || !$ids) return;

    $ids = array_values(array_unique(array_map('intval', $ids)));
    if(!$ids) return;

    $in  = implode(',', array_fill(0, count($ids), '?'));
    $sql = "INSERT INTO deal_contacts (deal_id, contact_id)
            SELECT ?, c.id FROM contacts c WHERE c.id IN ($in)";
    $params = array_merge(array((int)$dealId), $ids);
    $st = $this->db->prepare($sql);
    $st->execute($params);
  }
}
