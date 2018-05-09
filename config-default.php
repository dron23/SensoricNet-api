<?php

$conf['version'] = "v0.1"; // verze

$conf['base_dir'] = dirname(__FILE__);

$conf['log_dir'] = $conf['base_dir']."/log/";
$conf['log_filename'] = "sensoricnet_api.log";

// databaze
$conf['dbhost'] = "127.0.0.1";
$conf['dbname'] = "sensoricnet";
$conf['dbuser'] = "sensoricnet";
$conf['dbpasswd'] = "********";

$conf['app_id'] = "SensoricNet";

// mqtt
$conf['mqtt_host'] = "127.0.0.1";
$conf['mqtt_basic_topic'] = "SensoricNet";
$conf['mqtt_port'] = 1883;
$conf['mqtt_qos'] = 5;

// grafana
$conf['grafana_base_url'] = "http://grafana.example.com:3000";
$conf['grafana_auth_token'] = "********************************************";

$conf['log_severity'] = 'debug';

