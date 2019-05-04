<?php

require_once "Mysql.php";
require_once "EntityInterface.php";

abstract Class Entity implements EntityInterface {

  private $reflection;
  protected static $tableName = NULL;

  public function __construct() {
    $this->reflection = new \ReflectionClass($this);
  }

  public function save() {
    $propertyQuery = array();
    foreach ($this->reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
      if($property->getName() != 'id') {
        $propertyQuery[] = $property->getName() . ' = "' . $property->getValue($this) . '"';
      }
    }
    if($this->id) {
      $startQuery = 'UPDATE ';
      $endQuery = ' WHERE id=' . $this->id;
    } else {
      $startQuery = 'INSERT INTO ';
      $endQuery = '';
    }
    $query = $startQuery . static::getTableName() . ' SET ' . implode(', ', $propertyQuery) . $endQuery . ';';
    Mysql::getInstance()->getConnection()->exec($query);
  }

  public function load($id) {
    $query = 'SELECT * FROM ' . static::getTableName() . ' WHERE id=' . $id . ';';
    $result = Mysql::getInstance()->getConnection()->query($query)->fetch(PDO::FETCH_ASSOC);
    if(!$result) throw new Exception('Objet non trouvé');
    foreach ($result as $key => $value)
      $this->$key = $value;
  }

  public static function find($clauseWhere = NULL) {
    $clauseWhere = (isset($clauseWhere) && $clauseWhere != '') ? $clauseWhere: '1';
    $query = 'SELECT * FROM ' . static::getTableName() . ' WHERE ' . $clauseWhere . ';';
    return Mysql::getInstance()->getConnection()->query($query)->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function getTableName() {
    $reflection = new \ReflectionClass(get_called_class());
    return NULL !== static::$tableName ? static::$tableName : strtolower($reflection->getName());
  }

}
