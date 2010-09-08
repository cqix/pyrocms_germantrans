<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');
/*
| -------------------------------------------------------------------
| DATABASE LANGUAGE INFO
| -------------------------------------------------------------------
| You may call this file via $this->config->load()
| -------------------------------------------------------------------
|
*/
require_once 'database.php';

//Default language
$config['database_default_language'] = 'en';

//All possible groups
$config['database_groups'] = array_keys($db);

//All possible languages
$config['database_languages'] = array();
foreach ($config['database_groups'] as $value) {
    $values = explode('_', $value);
    if (count($values)!==2) continue;
    if ($values[0]!=='live') continue;
    array_push($config['database_languages'], $values[1]);
}

if (count($config['database_languages'])>0) {
    array_unshift($config['database_languages'], $config['database_default_language']);
}