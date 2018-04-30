<?php


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