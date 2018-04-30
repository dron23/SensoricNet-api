<?php


//---[Logovani]-------------------------------------------------------------------

use Katzgrau\KLogger\Logger;

$logger = new Katzgrau\KLogger\Logger($conf['log_dir'], Psr\Log\LogLevel::DEBUG, array (
		'filename' => $conf['log_filename']
		)
);

//---[Session]-------------------------------------------------------------------

if (!isset($_SESSION)) session_start();		// zahájení relace session


//---[DB]-----------------------------------------------------------------------

// PDO
try {
	$db = new PDO("mysql:host=".$conf['dbhost'].";dbname=".$conf['dbname'].";charset=utf8", $conf['dbuser'], $conf['dbpasswd']);
	$db->exec("set names utf8");
	$logger->info("pripojeni k db je ok");

} catch (PDOException $e) {
	$logger->error("Chyba pri pripojeni k databazi. ".$e->getMessage());
	die();
}
