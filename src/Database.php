<?php
class Database {
  private $pdo;

  public function __construct($cfg){
    $this->pdo = new PDO(
      $cfg['dsn'],
      $cfg['user'],
      $cfg['pass'],
      array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
      )
    );
  }

  public function pdo() {
    return $this->pdo;
  }
}
