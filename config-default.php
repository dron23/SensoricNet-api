<?php

$conf['version'] = "v0.1"; // verze

$conf['base_dir'] = dirname(__FILE__);

$conf['log_filename'] = $conf['base_dir']."/log/sensoricnet_api.log";

// databaze
$conf['dbhost'] = "127.0.0.1";
$conf['dbname'] = "sensoricnet";
$conf['dbuser'] = "sensoricnet";
$conf['dbpasswd'] = "********";

$conf['mqtt_host'] = "127.0.0.1";
$conf['mqtt_basic_topic'] = "SensoricNet";

$conf['log_severities'] = array (
		'emergency' => 0,
		'alert'     => 1,
		'critical'  => 2,
		'error'     => 3,
		'warning'   => 4,
		'notice'    => 5,
		'info'      => 6,
		'debug'     => 7
);
$conf['log_severity'] = 'debug';

?>