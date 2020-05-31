<?php
class DB {
  private $host = "localhost";
  private $user = "allan";
  private $pass = "password";
  private $base = "stautrh_api";

  public $conn;

  public function connect(){
    try{
      $this->conn = new PDO(
        "mysql:host=".$this->host.";".
        "dbname=".$this->base, $this->user, $this->pass
      );
      //var_dump($this->conn);
    }catch(PDOException $excep){
      print_r("Erro: ".$excep->getMessage());
    }

    return $this->conn;
  }
}