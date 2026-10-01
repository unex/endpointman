<?php

/**
 * Read and write the structured blob columns of the module.
 *
 * Eleven columns in four tables hold a structured value, not a scalar:
 * template data, overrides for each device and template, the config file
 * override list and the specific settings form:
 *
 *   endpointman_model_list     template_data
 *   endpointman_mac_list       global_custom_cfg_data, global_user_cfg_data,
 *                              global_settings_override, specific_settings,
 *                              config_files_override
 *   endpointman_template_list  global_custom_cfg_data, global_settings_override,
 *                              config_files_override
 *   endpointman_line_list      custom_cfg_data, user_cfg_data
 *
 * The module writes JSON. Older releases wrote PHP serialize() data, so the
 * reader reads both formats. An upgraded install works before install.php
 * converts the rows, and a row from an older release still reads. The two
 * formats cannot be confused. A serialize()d array always starts with `a:`.
 * Each value here is an array, so its JSON always starts with `{` or `[`.
 *
 * JSON has three advantages. unserialize() on data that an attacker can
 * influence is a risk, also with allowed_classes=false. Any tool that reads
 * JSON can read the columns. A person can debug `SELECT template_data`.
 *
 * Each of these columns is notnull="false" (see module.xml). A row that the
 * UI never saved thus gives NULL. `unserialize(null)` is deprecated in PHP
 * 8.1, and `unserialize('')` gives a warning. The Whoops handler of FreePBX
 * turns both into an ErrorException. Both readers accept NULL.
 *
 * @license MPL / GPLv2 / LGPL
 * @package Endpoint Manager
 */

if (!function_exists('epm_blob_decode')) {

	/**
	 * Read a blob column, in either format.
	 *
	 * @param mixed $blob    column value: JSON, a serialized string, NULL or ''
	 * @param mixed $default what to return when there is nothing to read
	 * @return mixed the stored value, or $default
	 */
	function epm_blob_decode($blob, $default = false) {
		// Some callers already have the decoded value. generate_gui_html gets both a
		// database row and an array that is already in memory.
		if (is_array($blob) || is_object($blob)) {
			return $blob;
		}
		if ($blob === null || $blob === false || $blob === '' || !is_string($blob)) {
			return $default;
		}

		$first = substr(ltrim($blob), 0, 1);
		if ($first === '{' || $first === '[') {
			$value = json_decode($blob, true);
			return json_last_error() === JSON_ERROR_NONE ? $value : $default;
		}

		// Refuse objects. These columns hold arrays of scalars, and no writer encodes
		// an object. If a blob contains an object, the refusal stops it from running
		// code through __destruct() or __wakeup() during the read.
		$value = @unserialize($blob, array('allowed_classes' => false));

		// unserialize() returns false for a damaged blob and also for a blob that
		// holds the boolean false. Thus compare with serialize(false), and do not
		// trust the return value alone.
		if ($value === false && $blob !== serialize(false)) {
			return $default;
		}
		return $value;
	}

	/**
	 * Encode a value for a blob column.
	 *
	 * json_encode() returns false for input that it cannot represent. Usually
	 * this is a string that is not valid UTF-8, for example from a phone template
	 * or a pasted config value. Do not store a damaged value or lose the save.
	 * Use the old format. epm_blob_decode() reads it, and a later write converts
	 * the row when the bad value is gone.
	 *
	 * @param mixed $value
	 * @return string
	 */
	function epm_blob_encode($value) {
		$json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if ($json === false) {
			trigger_error(
				'epm_blob_encode: falling back to serialize(), json_encode failed: '
				. json_last_error_msg(),
				E_USER_NOTICE
			);
			return serialize($value);
		}
		return $json;
	}
}
