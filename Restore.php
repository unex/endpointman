<?php
/**
 * Endpoint Manager: FreePBX 17 restore hook
 *
 * This is the counterpart of Backup.php. See that file for the contents of a
 * backup.
 *
 * This class does not use the inherited importTables() or
 * restoreLegacyDatabase(). Both send each row through
 * RestoreBase::addDataToTableFromArray(), which changes each value with
 *
 *     str_replace(array('\n', '\"', "\'"), array("\n", '"', "'"), $data)
 *
 * This changes a literal backslash-n into a newline and a backslash-quote
 * into a quote. The eleven blob columns of this module hold JSON.
 * json_encode() writes these two sequences when a string has a newline or a
 * quote. The config editor makes such strings, for example from a multi-line
 * setting or a label like `Reception "front desk"`.
 *
 * After the change, the JSON is not valid. json_decode() returns null,
 * epm_blob_decode() uses its default, and the restore empties the column
 * without an error. The risk is in the storage format, and it grows with each
 * custom template.
 *
 * importRows() below inserts the rows of the backup without changes.
 *
 * @package Endpointman
 */

namespace FreePBX\modules\Endpointman;

use FreePBX\modules\Backup as Base;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Restore extends Base\RestoreBase {

	public function runRestore() {
		$configs = $this->getConfigs();

		$this->importRowsForTables(isset($configs['tables']) ? $configs['tables'] : array());
		$this->importKVStore(isset($configs['kvstore']) ? $configs['kvstore'] : array());
		$this->importAdvancedSettings(isset($configs['settings']) ? $configs['settings'] : array());

		$this->restorePhoneModules(isset($configs['phone_modules_path']) ? $configs['phone_modules_path'] : '');
		$this->warnMissingPackages();
	}

	/**
	 * Restore from a backup of FreePBX 13, 14 or 15.
	 *
	 * The legacy format gives a PDO to a temporary database with the old tables,
	 * not a config payload. The rows must stay unchanged, so they use the same
	 * path as a current restore. Legacy backups hold serialize() blobs.
	 * lib/blob.inc.php still reads them, so copy them too.
	 *
	 * @param PDO   $pdo           connection to the restored legacy database
	 * @param array $data          manifest/astdb/settings/features
	 * @param array $tables        tables the framework attributed to us
	 * @param array $unknownTables tables with no owner
	 * @return void
	 */
	public function processLegacy($pdo, $data, $tables, $unknownTables) {
		$rows = array();
		foreach ($this->moduleTables() as $table) {
			try {
				$sth = $pdo->query(sprintf('SELECT * FROM `%s`', $table));
				$rows[$table] = $sth->fetchAll(PDO::FETCH_ASSOC);
			} catch (\Exception $e) {
				$this->log(sprintf(_("The old backup has no table %s. Skipped."), $table), 'WARNING');
			}
		}

		$this->importRowsForTables($rows);
		$this->restoreLegacyKvstore($pdo);
		$this->restoreLegacySettings($pdo);
		$this->warnMissingPackages();
	}

	/**
	 * Get each table that this module owns on this install.
	 *
	 * Read both the live schema and module.xml, because they can differ in both
	 * directions. A table that module.xml no longer lists can still exist. A new
	 * table in module.xml exists only after install.php runs.
	 *
	 * @return array table names
	 */
	public function moduleTables() {
		$tables = $this->getModuleTable_names('endpointman');
		$tables = is_array($tables) ? $tables : array();

		$dir = $this->FreePBX->Config->get('AMPWEBROOT') . '/admin/modules/endpointman';
		if (file_exists($dir . '/module.xml')) {
			$xml = simplexml_load_file($dir . '/module.xml');
			if (!empty($xml->database->table)) {
				foreach ($xml->database->table as $table) {
					$name = (string) $table->attributes()->name;
					if (!in_array($name, $tables, true)) {
						$tables[] = $name;
					}
				}
			}
		}
		return $tables;
	}

	/**
	 * Replace the contents of each table in the payload with its rows.
	 *
	 * @param array $tables table name => list of rows
	 * @return void
	 */
	public function importRowsForTables($tables) {
		if (empty($tables) || !is_array($tables)) {
			$this->log(_("This backup has no Endpoint Manager tables. There is nothing to restore."), 'WARNING');
			return;
		}
		foreach ($tables as $table => $rows) {
			$this->importRows($table, is_array($rows) ? $rows : array());
		}
	}

	/**
	 * Empty one table and insert the rows of the backup without changes.
	 *
	 * If the backup has a column that this schema does not have, drop the column
	 * and show a warning. A column that the backup does not have gets its
	 * default. All other values stay exactly as they are.
	 *
	 * @param string $table
	 * @param array  $rows
	 * @return void
	 */
	public function importRows($table, $rows) {
		$columns = $this->columnsOf($table);
		if ($columns === null) {
			$this->log(sprintf(_("The table %s does not exist on this system. Skipped."), $table), 'WARNING');
			return;
		}

		$this->log(sprintf(_("Restoring %s rows into %s"), count($rows), $table));
		$this->FreePBX->Database->query(sprintf('TRUNCATE TABLE `%s`', $table));

		$statements = array();
		$warned = array();
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$values = array();
			foreach ($row as $column => $value) {
				if (!isset($columns[$column])) {
					if (!isset($warned[$column])) {
						$this->log(sprintf(_("The column %s does not exist in %s. Skipped."), $column, $table), 'WARNING');
						$warned[$column] = true;
					}
					continue;
				}
				$values[':' . $column] = $value;
			}
			if (empty($values)) {
				continue;
			}

			// Prepare one statement for each set of columns. The rows of one table
			// usually have the same set, so this prepares once.
			$names = array_keys($values);
			$key = implode(',', $names);
			if (!isset($statements[$key])) {
				$statements[$key] = $this->FreePBX->Database->prepare(sprintf(
					'INSERT INTO `%s` (%s) VALUES (%s)',
					$table,
					'`' . implode('`, `', array_map(function ($n) { return substr($n, 1); }, $names)) . '`',
					implode(', ', $names)
				));
			}

			try {
				$statements[$key]->execute($values);
			} catch (\Exception $e) {
				$this->log(sprintf(_("Cannot add a row to %s: %s"), $table, $e->getMessage()), 'ERROR');
			}
		}
	}

	/**
	 * Get the columns of a table on this system.
	 *
	 * @param string $table
	 * @return array|null column name => true, or null when there is no such table
	 */
	public function columnsOf($table) {
		try {
			$sth = $this->FreePBX->Database->query(sprintf('SHOW COLUMNS FROM `%s`', $table));
		} catch (\Exception $e) {
			return null;
		}
		$columns = array();
		foreach ($sth->fetchAll(PDO::FETCH_ASSOC) as $column) {
			$columns[$column['Field']] = true;
		}
		return $columns;
	}

	/**
	 * Restore the `_ep_phone_modules/` tree.
	 *
	 * The backup records absolute paths. If AMPWEBROOT on this machine is
	 * different, the tree goes to a directory that nothing reads. Thus change the
	 * stored prefix to the prefix of this install. certman does the same with its
	 * key directory.
	 *
	 * @param string $originalPath the path of the tree on the backup machine
	 * @return void
	 */
	public function restorePhoneModules($originalPath) {
		$files = $this->getFiles();
		if (empty($files)) {
			$this->log(_("This backup has no phone module files."), 'WARNING');
			return;
		}

		$target = rtrim($this->FreePBX->Endpointman->PHONE_MODULES_PATH, '/');
		$original = rtrim((string) $originalPath, '/');
		if ($original === '') {
			$original = $target;
		}
		if ($original !== $target) {
			$this->log(sprintf(_("The backup has the phone modules of %s. Restoring them to %s."), $original, $target));
		}

		$count = 0;
		foreach ($files as $file) {
			$source = $this->tmpdir . '/files' . $file->getPathTo() . '/' . $file->getFilename();
			if (!file_exists($source)) {
				continue;
			}
			$dir = $this->remap($file->getPathTo(), $original, $target);
			if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
				$this->log(sprintf(_("Cannot make %s."), $dir), 'ERROR');
				continue;
			}
			if (!@copy($source, $dir . '/' . $file->getFilename())) {
				$this->log(sprintf(_("Cannot restore %s."), $dir . '/' . $file->getFilename()), 'ERROR');
				continue;
			}
			$count++;
		}
		$this->log(sprintf(_("Restored %s phone module files to %s."), $count, $target));
	}

	/**
	 * Move one backup path into the phone modules directory of this install.
	 *
	 * A path outside the original tree stays as it is. getFiles() holds only the
	 * files of this module now. If a future backup adds a different tree, it must
	 * not go here.
	 *
	 * @param string $path
	 * @param string $original
	 * @param string $target
	 * @return string
	 */
	public function remap($path, $original, $target) {
		$path = rtrim($path, '/');
		if ($path === $original) {
			return $target;
		}
		if (strpos($path, $original . '/') === 0) {
			return $target . substr($path, strlen($original));
		}
		return $path;
	}

	/**
	 * Report brands that the restored catalog shows as installed but that have
	 * no package on disk.
	 *
	 * The module then offers models for which it cannot make a config. The fix
	 * is a new install from the Packages page. Thus name the brands now, before a
	 * missing driver shows the fault later.
	 *
	 * @return void
	 */
	public function warnMissingPackages() {
		try {
			$sth = $this->FreePBX->Database->query(
				'SELECT `directory` FROM `endpointman_brand_list` WHERE `installed` = 1 ORDER BY `directory`'
			);
		} catch (\Exception $e) {
			return;
		}

		$root = rtrim($this->FreePBX->Endpointman->PHONE_MODULES_PATH, '/') . '/endpoint/';
		$missing = array();
		foreach ($sth->fetchAll(PDO::FETCH_COLUMN) as $directory) {
			if ($directory !== '' && !is_dir($root . $directory)) {
				$missing[] = $directory;
			}
		}

		if (!empty($missing)) {
			$this->log(sprintf(
				_("The database shows these brands as installed, but their packages are not on the disk: %s. Install them again on the Brands page."),
				implode(', ', $missing)
			), 'WARNING');
		}
	}
}
