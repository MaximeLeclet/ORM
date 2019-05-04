<?php

class Mysql {

  const DEFAULT_USER = "newuser";
  const DEFAULT_HOST = "localhost";
  const DEFAULT_PASS = "password";
  const DEFAULT_DBNAME = "ORM";

  private $PDOInstance = NULL;

  private static $mysqlInstance = NULL;

  private function __construct() {
    $this->PDOInstance = new PDO("mysql:dbname=" . self::DEFAULT_DBNAME . ";host=" . self::DEFAULT_HOST, self::DEFAULT_USER, self::DEFAULT_PASS);
  }

  public static function getInstance() {

    if(is_null(self::$mysqlInstance)) {
      self::$mysqlInstance = new Mysql();
    }
    return self::$mysqlInstance;

  }

  public function getConnection() {
    return $this->PDOInstance;
  }

}
