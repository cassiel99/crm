<?php
require_once __DIR__ . '/../Sync.php';
// src/Repositories/ContactRepository.php
class ContactRepository {
  /** @var PDO */
  private $db;

  public function __construct($db){ $this->db = $db; }

  public function all(){
    return $this->db->query("SELECT id,first_name,last_name FROM contacts ORDER BY id DESC")->fetchAll();
  }

  public function get($id){
    $st = $this->db->prepare("SELECT id,first_name,last_name FROM contacts WHERE id=?");
    $st->execute(array((int)$id));
    $c = $st->fetch();
    if(!$c) return null;

    $st = $this->db->prepare("
      SELECT d.id, d.title
      FROM deals d
      JOIN deal_contacts dc ON dc.deal_id = d.id
      WHERE dc.contact_id = ?
      ORDER BY d.title
    ");
    $st->execute(array((int)$id));
    $c['deals'] = $st->fetchAll();
    return $c;
  }

  public function create($first, $last = null, $dealIds = array()){
    $st = $this->db->prepare("INSERT INTO contacts(first_name,last_name) VALUES(?,?)");
    $st->execute(array($first, $last));
    $id = (int)$this->db->lastInsertId();
    $this->syncDeals($id, $dealIds);
    return $id;
  }

  public function update($id, $first, $last, $dealIds){
    if($first !== null || $last !== null){
      $st = $this->db->prepare("UPDATE contacts SET first_name=COALESCE(?,first_name), last_name=COALESCE(?,last_name) WHERE id=?");
      $st->execute(array($first, $last, (int)$id));
    }
    if(is_array($dealIds)) $this->syncDeals((int)$id, $dealIds);
    return true;
  }

  public function delete($id){
    $st = $this->db->prepare("DELETE FROM contacts WHERE id=?");
    return $st->execute(array((int)$id));
  }

  private function syncDeals($contactId, $ids){
    $this->db->prepare("DELETE FROM deal_contacts WHERE contact_id=?")->execute(array((int)$contactId));
    if(!is_array($ids) || !$ids) return;

    $ids = array_values(array_unique(array_map('intval', $ids)));
    if(!$ids) return;

    $in  = implode(',', array_fill(0, count($ids), '?'));
    $sql = "INSERT INTO deal_contacts (deal_id, contact_id)
            SELECT d.id, ? FROM deals d WHERE d.id IN ($in)";
    $params = array_merge(array((int)$contactId), $ids);
    $st = $this->db->prepare($sql);
    $st->execute($params);
  }
}
