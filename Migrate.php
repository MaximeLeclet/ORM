<?php

require_once "Mysql.php";

class Migrate {

  const DEFAULT_CONFIG_DIRECTORY = "config/";
  const DEFAULT_MIGRATION_DIRECTORY = "migration/";

  // DB connection
  private $pdo = NULL;
  // Files to validate
  private $files = NULL;
  private static $migrateInstance = NULL;

  private function __construct() {
    $this->pdo = Mysql::getInstance()->getConnection();
    $this->files = array_diff(scandir(self::DEFAULT_CONFIG_DIRECTORY), array('..', '.'));
  }

  public static function getInstance() {
    if(is_null(self::$migrateInstance)) {
      self::$migrateInstance = new Migrate();
    }
    return self::$migrateInstance;
  }

  // Validate files in the config directory
  public function validateConfig() {
    foreach ($this->files as $file) {
      // Getting file's content
      $clearContent = Migrate::getConfigFileContent($file);

      // Check if file had the 'version' key
      if(!array_key_exists('version', $clearContent))
        throw new Exception('Migrate - validateConfig : Config file need a version');

      // Check if file had the 'fields' key
      if(!array_key_exists('fields', $clearContent))
        throw new Exception('Migrate - validateConfig : Config file need fields');

      // Check if fields have the 'type' key
      foreach ($clearContent['fields'] as $fieldName => $field) {
        if(!array_key_exists('type', $field))
          throw new Exception('Migrate - validateConfig : fields need a type');
      }
    }
  }

  // Make migration files
  public function makeMigration() {
    $migrationQueries = array();
    foreach ($this->files as $file) {
      // Getting file's content
      $clearContent = Migrate::getConfigFileContent($file);
      $tableName = (array_key_exists('tableName', $clearContent)) ? $clearContent['tableName'] : pathinfo(self::DEFAULT_CONFIG_DIRECTORY . $file)['filename'];
      $createQuery = 'CREATE TABLE ' . $tableName . ' (';
      $fields = array();
      foreach ($clearContent['fields'] as $key => $value) {
        $properties = (array_key_exists('properties', $value)) ? ' ' . $value['properties'] : '';
        $fields[] = $key . ' ' . $value['type'] . $properties;
      }
      $createQuery = $createQuery . implode(', ', $fields) . ');';
      $migrationQueries[] = $createQuery;
      // Create the migration directory if not exist
      if (!file_exists(self::DEFAULT_MIGRATION_DIRECTORY))
        mkdir(self::DEFAULT_MIGRATION_DIRECTORY, 0777, true);
      file_put_contents(self::DEFAULT_MIGRATION_DIRECTORY . '1.sql', implode("\r\n", $migrationQueries));
    }
  }

  // Apply pending migrations
  public function migrate() {
    $migrations = array_diff(scandir(self::DEFAULT_MIGRATION_DIRECTORY), array('..', '.'));
    $allQueries = array();
    foreach ($migrations as $file) {
      $content = file_get_contents(self::DEFAULT_MIGRATION_DIRECTORY . $file);
      $queries = explode("\r\n", $content);
      foreach ($queries as $query) {
        $allQueries[] = $query;
      }
    }

    // Print of the execution plan
    echo "\nThe following queries will be executed : \n\n";
    foreach ($allQueries as $query) {
      echo $query . "\n";
    }
    echo "\n";

    // Queries exectuion
    foreach ($allQueries as $query) {
      $result = $this->pdo->exec($query);
      var_dump($result);
      if($result !== 0 && $result == false)
        throw new \Exception("Query execution return an error : " . $query . "\n");
    }

  }

  // Return the JSON decoded of a config file
  public static function getConfigFileContent($file) {
    $JSONContent = file_get_contents(self::DEFAULT_CONFIG_DIRECTORY . $file);
    return json_decode($JSONContent, true);
  }

}

$migrate = Migrate::getInstance();

try {
  $migrate->validateConfig();
  $migrate->makeMigration();
  $migrate->migrate();
} catch(Exception $e) {
  echo 'Error : ', $e->getMessage(), "\n";
}
