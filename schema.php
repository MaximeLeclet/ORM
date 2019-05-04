<?php

require_once "Mysql.php";

// Repertoire ou se trouvent les fichiers de config
$configDirectory = 'config';

// Fichiers de config
$configFiles = array_diff(scandir($configDirectory), array('..', '.'));

// Connexion à la BDD
$pdo = Mysql::getInstance()->getConnection();

// Tableau des requetes qui seront effectuees
$migrationQueries = array();

// Creation d'une table qui contient un numero de version pour chaque fichier de config, ce qui permet de voir si il y a eu modification
$createSchemaVersion = 'CREATE TABLE IF NOT EXISTS schema_versions (table_name VARCHAR(255) NOT NULL PRIMARY KEY, version VARCHAR(255) NOT NULL);';

// Parcours de tous les fichiers json
foreach ($configFiles as $file) {

  $JSONContent = file_get_contents($configDirectory . '/' . $file);
  $clearContent = json_decode($JSONContent, true);

  $tableName = (array_key_exists('tableName', $clearContent)) ? $clearContent['tableName'] : pathinfo($configDirectory . '/' . $file)['filename'];

  $createQuery = 'CREATE TABLE ' . $tableName . ' (';

  $fields = array();

  // Parcours des champs
  foreach ($clearContent['fields'] as $key => $value) {
    $properties = (array_key_exists('properties', $value)) ? ' ' . $value['properties'] : '';
    $fields[] = $key . ' ' . $value['type'] . $properties;
  }

  $createQuery = $createQuery . implode(', ', $fields) . ');';

  $migrationQueries[] = $createQuery;
  $pdo->exec($createQuery);

  //file_put_contents('migration/1.txt', implode('\n', $migrationQueries));

}
