<?php

/**
 * Prepared statements for the module, next to the global sql() of FreePBX.
 *
 * The sql($sql, $type, $fetchmode) of FreePBX cannot bind values.
 * libraries/sql.functions.php always sends an empty parameter array to
 * DB::getAll(), getRow() and getOne(), although all three support prepared
 * statements.
 *
 * epm_sql() has the same call form with a parameter array in the middle. To
 * convert a call, replace each value in the SQL with `?` and add one array
 * argument. The return values are the same as from the global sql(), also
 * its special cases (see epm_sql_one()).
 *
 * @license MPL / GPLv2 / LGPL
 * @package Endpoint Manager
 */

if (!function_exists('epm_sql')) {

	/**
	 * Run a statement with parameters.
	 *
	 * @param string $sql       statement with `?` placeholders
	 * @param string $type      query | getAll | getRow | getOne
	 * @param array  $params    values to bind, in placeholder order
	 * @param int    $fetchmode a DB_FETCHMODE_* constant, not a PDO::FETCH_*
	 *                          constant (the two number sets collide)
	 * @return mixed PDOStatement for "query", array for "getAll",
	 *               array|false for "getRow", string|false for "getOne"
	 */
	function epm_sql($sql, $type = "query", $params = array(), $fetchmode = DB_FETCHMODE_ASSOC) {
		if (!is_array($params)) {
			$params = array($params);
		}

		$stmt = \FreePBX::create()->Database->prepare($sql);
		$stmt->execute(array_values($params));

		// The global sql() calls these through $db->$type(), and PHP method names
		// ignore case. Thus callers use different spellings, for example "getrow"
		// and "getall".
		switch (strtolower($type)) {
			case "query":
				return $stmt;

			case "getall":
				return $stmt->fetchAll(epm_sql_fetchmode($fetchmode));

			case "getrow":
				return $stmt->fetch(epm_sql_fetchmode($fetchmode));

			case "getone":
				// Use !empty(), not isset(), as Database::sql_getOne() does. A column that
				// holds "0", "" or NULL thus returns false. The callers expect this, and at
				// least one of them needs it.
				$line = $stmt->fetchColumn();
				return !empty($line) ? $line : false;

			default:
				throw new \Exception("Unknown SQL query type of " . $type);
		}
	}

	/**
	 * Change a PEAR DB_FETCHMODE_* constant to the PDO constant, as
	 * DB::correctFetchMode() does.
	 *
	 * @param int $fetchmode
	 * @return int a PDO::FETCH_* constant
	 */
	function epm_sql_fetchmode($fetchmode = DB_FETCHMODE_ASSOC) {
		switch ($fetchmode) {
			case DB_FETCHMODE_OBJECT:
				return \PDO::FETCH_OBJ;
			case DB_FETCHMODE_ORDERED:
				return \PDO::FETCH_NUM;
			case DB_FETCHMODE_DEFAULT:
			case DB_FETCHMODE_ASSOC:
			default:
				return \PDO::FETCH_ASSOC;
		}
	}
}
