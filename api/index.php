<?php

require_once __DIR__ . '/../vendor/autoload.php';	// composer autoloader

require_once __DIR__ . "/../config.php"; //setup, promenne
require_once __DIR__ . "/../functions.php";
require_once __DIR__ . "/../initpage_inc.php"; //inicializace, db...


//use Tracy\Debugger;
//Debugger::enable();

//use SensoricNet\Auth;


$mode = 'debug'; // 'debug' or 'production'
//$mode = 'production';

$server = new \Jacwright\RestServer\RestServer ($mode);
$server->addClass ( '\SensoricNet\SensoricNetRestApi' );

$server->handle ();

