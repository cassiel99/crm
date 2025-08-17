<?php
// src/Controllers/ContactController.php
require_once __DIR__ . '/../Repositories/ContactRepository.php';

class ContactController {
  private $repo;

  public function __construct($repo){ $this->repo = $repo; }

  public function index(){
    return $this->repo->all();
  }

  public function show($id){
    $c = $this->repo->get((int)$id);
    if(!$c){ throw new RuntimeException('Not found'); }
    return $c;
  }

  public function create($data){
    $first = trim(isset($data['first_name']) ? (string)$data['first_name'] : '');
    if($first === ''){ throw new InvalidArgumentException('first_name required'); }
    $last  = isset($data['last_name']) ? trim((string)$data['last_name']) : null;
    $dealIds = $this->normalizeIds(isset($data['deal_ids']) ? $data['deal_ids'] : array());
    return $this->repo->create($first, $last, $dealIds);
  }

  public function update($id, $data){
    $first = array_key_exists('first_name', $data) ? trim((string)$data['first_name']) : null;
    if($first !== null && $first === ''){ throw new InvalidArgumentException('first_name cannot be empty'); }
    $last  = array_key_exists('last_name',  $data) ? trim((string)$data['last_name']) : null;
    $dealIds = array_key_exists('deal_ids', $data) ? $this->normalizeIds($data['deal_ids']) : null;
    return $this->repo->update((int)$id, $first, $last, $dealIds);
  }

  public function delete($id){
    return $this->repo->delete((int)$id);
  }

  private function normalizeIds($ids){
    if(!is_array($ids)) return array();
    $out = array();
    foreach($ids as $v){
      $n = (int)$v;
      if($n > 0) $out[$n] = true;
    }
    return array_map('intval', array_keys($out));
  }
}
