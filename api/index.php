<?php

require_once __DIR__ . '/../vendor/autoload.php';	// composer autoloader

//use Tracy\Debugger;
//Debugger::enable();

require_once __DIR__ . "/../config.php"; //setup, promenne
require_once __DIR__ . "/../functions.php";
require_once __DIR__ . "/../initpage_inc.php"; //inicializace, db...



//use SensoricNet\Auth;


$mode = 'debug'; // 'debug' or 'production'
//$mode = 'production';

$server = new \Jacwright\RestServer\RestServer ($mode);
$server->addClass ( '\SensoricNet\SensoricNetRestApi' );

$server->handle ();

$runtime = round(microtime(TRUE) - $_SERVER['REQUEST_TIME_FLOAT'], 6);
echo "Script running for $runtime seconds";
$logger->debug("Script running for $runtime seconds");
