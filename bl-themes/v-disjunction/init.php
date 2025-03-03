<?php defined('BLUDIT') or die('Bludit CMS.');

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require(THEME_DIR_PHP."classes/disjunction.class.php");

$lib = new Disjunction();