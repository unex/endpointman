<?php
/**
 * Endpoint Manager: FreePBX 17 backup hook
 *
 * The Backup module finds the modules that it can back up by a `Backup.php`
 * file in the module directory (Backup.class.php::getModules()).
 *
 * The backup contains:
 *
 *   tables    each `endpointman_*` table: devices, lines, templates, custom
 *             configs, and the brand, model, product and OUI catalog
 *   kvstore   the settings of the module (lib/Config.class.php)
 *   settings  freepbx_settings rows owned by `endpointman`. A stock install
 *             has none, but a later setting is then not missed.
 *   files     the full `_ep_phone_modules/` tree
 *
 * The backup includes `_ep_phone_modules/` and does not download it again.
 * The package server can serve most of it again, but not firmware. The
 * package server publishes no firmware packages, so a firmware tarball on
 * disk came from a manual upload. The tree is about 16 MB for three brands,
 * which is similar to the backup size of `music` and `certman`.
 *
 * The backup does NOT include `/tftpboot/`. The module makes its contents
 * from the tables above, and `fwconsole endpointman --rebuild` writes them
 * again after a restore.
 *
 * @package Endpointman
 */

namespace FreePBX\modules\Endpointman;

use FreePBX\modules\Backup as Base;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Backup extends Base\BackupBase {

	public function runBackup($id, $transaction) {
		$this->addConfigs(array(
			'tables'   => $this->dumpTables(true),
			'kvstore'  => $this->dumpKVStore(),
			'settings' => $this->dumpAdvancedSettings(),
			// The location of the tree on this machine. Restore.php moves the stored
			// paths to its own AMPWEBROOT, as certman does with its key directory.
			'phone_modules_path' => $this->phoneModulesPath(),
		));

		$this->addPhoneModules();
	}

	/**
	 * Get the data directory of the module from the module itself. Thus this
	 * file is not a second place that knows the path.
	 *
	 * @return string with a trailing slash, as the module stores it
	 */
	public function phoneModulesPath() {
		return $this->FreePBX->Endpointman->PHONE_MODULES_PATH;
	}

	/**
	 * Add each file under `_ep_phone_modules/` to the backup.
	 *
	 * @return void
	 */
	public function addPhoneModules() {
		$root = rtrim($this->phoneModulesPath(), '/');
		if (!is_dir($root)) {
			$this->log(sprintf(_("%s does not exist. There are no phone modules to back up."), $root), 'WARNING');
			return;
		}

		$dirs = array();
		$count = 0;
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
		);
		foreach ($iterator as $file) {
			if (!$file->isFile()) {
				continue;
			}
			$dirs[] = $file->getPath();
			$this->addFile($file->getBasename(), $file->getPath(), '', 'epm');
			$count++;
		}

		$this->addDirectories(array_unique($dirs));
		$this->log(sprintf(_("Saving %s phone module files from %s"), $count, $root));
	}
}
