<?php
/**
 * The autoloader of the provisioner library.
 *
 * It loads the phone drivers: endpoint_base, endpoint_<brand>_base and
 * endpoint_<brand>_<family>_phone. The drivers are next to this file and ship
 * with the module. The module does not download them.
 *
 * Two rules apply:
 *
 *  - Code comes from this directory, and data comes from _ep_phone_modules/.
 *    The drivers read each template through $this->root_dir .
 *    $this->modules_path, which DevicesPage sets to the downloaded tree. Thus
 *    brand packages can change without a module release, and the module never
 *    runs code from them.
 *  - A class name cannot point outside this directory. Class names come from
 *    brand and family directories in downloaded JSON. Thus the loader checks
 *    the resolved path against this directory before it includes the file.
 *
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */

if (!class_exists('ProvisionerConfig')) {

class ProvisionerConfig {

	/** @var string absolute path of the shipped library, with trailing slash */
	private static $root = '';

	/**
	 * Register the autoloader.
	 */
	public static function setup() {
		self::$root = rtrim(realpath(__DIR__), '/') . '/';
		spl_autoload_register(array('ProvisionerConfig', 'endpointsAutoload'));
	}

	/**
	 * Load one provisioner class.
	 *
	 * @param string $class e.g. endpoint_yealink_t2x_phone
	 * @return bool
	 */
	public static function endpointsAutoload($class) {
		if (class_exists($class, false)) {
			return true;
		}

		// The upstream mapping: underscores are directory separators.
		// endpoint_yealink_t2x_phone -> endpoint/yealink/t2x/phone.php
		if (!preg_match('/^[A-Za-z0-9_]+$/', $class)) {
			return false;
		}

		if (self::$root === '') {
			self::$root = rtrim(realpath(__DIR__), '/') . '/';
		}

		$file = self::$root . str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
		$real = realpath($file);

		// A brand directory from a downloaded package cannot point the loader
		// outside the library that shipped with the module.
		if ($real === false || strpos($real, self::$root) !== 0) {
			return false;
		}
		if (!is_file($real)) {
			return false;
		}

		require $real;
		return true;
	}

	/**
	 * Where the shipped library lives, with a trailing slash.
	 *
	 * @return string
	 */
	public static function libraryPath() {
		if (self::$root === '') {
			self::$root = rtrim(realpath(__DIR__), '/') . '/';
		}
		return self::$root;
	}
}

}

ProvisionerConfig::setup();
