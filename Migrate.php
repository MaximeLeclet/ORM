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

    // Migration queries
    $migrationQueries = array();

    // Create the version control table it it not exists
    if(!$this->pdo->query('SELECT 1 FROM schema_version LIMIT 1')) {
      $versionControlQuery = 'CREATE TABLE schema_version(table_name VARCHAR(255) NOT NULL PRIMARY KEY, version INTEGER NOT NULL, columns VARCHAR(255));';
      $migrationQueries[] = $versionControlQuery;
    }

    foreach ($this->files as $file) {

      // Getting file's content
      $clearContent = Migrate::getConfigFileContent($file);
      $tableName = (array_key_exists('tableName', $clearContent)) ? $clearContent['tableName'] : pathinfo(self::DEFAULT_CONFIG_DIRECTORY . $file)['filename'];

      $fields = array();
      foreach ($clearContent['fields'] as $key => $value) {
        $properties = (array_key_exists('properties', $value)) ? ' ' . $value['properties'] : '';
        $fields[] = $key . ' ' . $value['type'] . $properties;
      }

      // Creation of the table if it no exists
      if(!$this->pdo->query('SELECT 1 FROM ' . $tableName . ' LIMIT 1')) {
        $createQuery = 'CREATE TABLE ' . $tableName . ' (';
        $createQuery = $createQuery . implode(', ', $fields) . ');';
        $migrationQueries[] = $createQuery;
      }

      // Version of the file
      $fileVersion = $clearContent['version'];
      // Version of the table
      $DBData = $this->pdo->query('SELECT version, columns FROM schema_version WHERE table_name LIKE "' . $tableName . '";');

      // If there is no version in base
      if(!$DBData) {
        // Set the DB version to the file version
        $versionQuery = 'INSERT INTO schema_version VALUES("' . $tableName . '", ' . $fileVersion . ', "' . implode(',', $fields) . '");';
        $migrationQueries[] = $versionQuery;
      } else { // If the DB had a version

        $DBData = $DBData->fetch(PDO::FETCH_ASSOC);
        $DBVersion = $DBData['version'];

        if($fileVersion != $DBVersion) { // If the current version is different of the DB version, update !
          $DBFields = explode(',', $DBData['columns']);
          $DBFieldsNames = array();
          // Getting DB fields names
          foreach ($DBFields as $DBField) {
            $DBFieldsExploded = explode(" ", $DBField);
            $DBFieldsNames[] = $DBFieldsExploded[0];
          }

          $fileFieldsNames = array();
          // Getting file fields names
          foreach ($clearContent['fields'] as $key => $value) {
            $fileFieldsNames[] = $key;
          }

          $newFields = array_diff($fileFieldsNames, $DBFieldsNames);
          $deletedFields = array_diff($DBFieldsNames, $fileFieldsNames);

          // Creating queries to update new fields
          foreach ($newFields as $key => $value) {
            $typeNewField = $clearContent['fields'][$value]['type'];
            $propertiesNewField = (array_key_exists('properties', $clearContent['fields'][$value])) ? $clearContent['fields'][$value]['properties'] : '';
            $newFieldsQuery = 'ALTER TABLE ' . $tableName . ' ADD ' . $value . ' ' . $typeNewField . ' ' . $propertiesNewField . ';';
            $schemaNewFieldsQuery = 'UPDATE schema_version SET columns = "' . implode(',', $fields) . '" WHERE table_name LIKE "' . $tableName . '";';
            $migrationQueries[] = $newFieldsQuery;
            $migrationQueries[] = $schemaNewFieldsQuery;
          }
          // Creating queries to update deleted fields
          foreach ($deletedFields as $key => $value) {
            // code...
          }

        }
      }

      // Create the migration directory if not exist
      if (!file_exists(self::DEFAULT_MIGRATION_DIRECTORY))
        mkdir(self::DEFAULT_MIGRATION_DIRECTORY, 0777, true);
      // Creating the migration file
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
        if($query != '')
          $allQueries[] = $query;
      }
    }

    // If there is no queries, print it, else execute
    if(empty($allQueries)) {
      echo "\nOK ! Nothing to update !\n\n";
    } else {
      // Print of the execution plan
      echo "\nThe following SQL statements will be executed : \n\n";
      foreach ($allQueries as $query) {
        echo $query . "\n";
      }
      echo "\n";

      echo "Continue ? [O/n] ";
      $handle = fopen ("php://stdin","r");
      $line = fgets($handle);
      if(trim($line) != 'O') {
          echo "\nAborting...\n\n";
          exit;
      }
      fclose($handle);

      // Queries exectuion
      foreach ($allQueries as $query) {
        if($query) {
          $result = $this->pdo->exec($query);
          if($result !== 0 && $result == false)
            throw new \Exception("Query execution return an error : " . $query . "\n");
        }
      }

      echo "\n";
      echo "Done !\n\n";

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
