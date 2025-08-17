<?php
// src/Controllers/DealController.php
require_once __DIR__ . '/../Repositories/DealRepository.php';

class DealController {
  private $repo;

  public function __construct($repo){ $this->repo = $repo; }

  public function index(){
    return $this->repo->all();
  }

  public function show($id){
    $deal = $this->repo->get((int)$id);
    if(!$deal){ throw new RuntimeException('Not found'); }
    return $deal;
  }

  public function create($data){
    $title = trim(isset($data['title']) ? (string)$data['title'] : '');
    if($title === ''){ throw new InvalidArgumentException('title required'); }
    $amount = isset($data['amount']) ? (float)$data['amount'] : 0.0;
    if($amount < 0){ throw new InvalidArgumentException('amount >= 0'); }
    $contactIds = $this->normalizeIds(isset($data['contact_ids']) ? $data['contact_ids'] : array());
    return $this->repo->create($title, $amount, $contactIds);
  }

  public function update($id, $data){
    $title  = array_key_exists('title',  $data) ? trim((string)$data['title']) : null;
    if($title !== null && $title === ''){ throw new InvalidArgumentException('title cannot be empty'); }
    $amount = array_key_exists('amount', $data) ? (float)$data['amount'] : null;
    if($amount !== null && $amount < 0){ throw new InvalidArgumentException('amount >= 0'); }
    $contactIds = array_key_exists('contact_ids', $data) ? $this->normalizeIds($data['contact_ids']) : null;
    return $this->repo->update((int)$id, $title, $amount, $contactIds);
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
