<?
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);
date_default_timezone_set('UTC');

$mysqli = db_connect(); 

include_once 'parking_zones.php';
parking_zones();


$mysqli -> close();

function getIni() {
	require_once ("/var/www/c/core/config.php"); return getConfig();
}

function db_connect() {
	$ini = getIni();
	$connection = new mysqli($ini['mysql']['host'], $ini['mysql']['user'], $ini['mysql']['password'], 'anytime', $ini['mysql']['port']);
	$connection -> set_charset("utf8");
	return $connection;
}

?>
