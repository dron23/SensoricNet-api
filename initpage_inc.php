<?php

// nejake konstanty at se nam lip pracuje s chybama...
define("DEBUG", 0);
define("OK", 1);
define("WARNING", 2);
define("ERROR", 3);
define("FATAL", 255);

//log severities
define('EMERGENCY', 0);
define('ALERT', 1);
define('CRITICAL', 2);
define('ERROR', 3);
define('WARNING', 4);
define('NOTICE', 5);
define('INFO', 6);
define('DEBUG', 7);

//---[Logovani]-------------------------------------------------------------------

// logovaci soubor
$log = fopen($conf['log_filename'], "a");
if (!$log) {
	$errors[] = array ("typ" => FATAL, "text"  => _("Nepodařilo se otevřít logovací soubor."));
	$fatal_error = true;
}

//---[Session]-------------------------------------------------------------------

if (!isset($_SESSION)) session_start();		// zahájení relace session


//---[DB]-----------------------------------------------------------------------

// PDO
try {
	$db = new PDO("mysql:host=".$conf['dbhost'].";dbname=".$conf['dbname'].";charset=utf8", $conf['dbuser'], $conf['dbpasswd']);
	$db->exec("set names utf8");
	logit ("info", "pripojeni k db je ok");

} catch (PDOException $e) {
	logit ("error", "Chyba pri pripojeni k databazi. ".$e->getMessage());
	die();
}

?>