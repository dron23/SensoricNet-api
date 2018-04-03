<?php


/**
 * logovani podle nastavene severity na obrazovku a do souboru
 *
 * @param string $severity
 * @param string $text
 */
function logit($severity, $text) {
	global $conf, $log;

	if ($conf['log_severities'][$conf['log_severity']] >= $conf['log_severities'][$severity]) {
		$text_array = explode("\n", $text);
		foreach ($text_array as $line) {
			fputs($log, date("Ymd H:i:s ") . strtoupper($severity) . ": " . $line . "\n");
		}
	}
}


/**
 * na zaklade $field_name vrati odpovidajici hodnotu ve stringu, TODO
 * 
 * @param string $field_name
 * @param string $field_value
 */
function getValueString($field_name, $field_value) {
	return NULL;
}

/**
 * na zaklade $field_name vrati odpovidajici hodnotu ve floatu, TODO
 * 
 * @param string $field_name
 * @param string $field_value
 */
function getValueFloat($field_name, $field_value) {
	if (is_object($field_value)) {
		return 0;
	} else return $field_value;
}

//TODO