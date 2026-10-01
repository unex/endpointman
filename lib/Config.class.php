<?php
/**
 * Endpoint Manager Config
 *
 * The settings of the module, stored in the BMO KVStore.
 *
 * The store is `kvstore_FreePBX_modules_Endpointman`. DB_Helper creates it on
 * first use, and other FreePBX 17 modules use the same kind of store. Only
 * this class knows where the values are.
 *
 * Reads come from a copy in memory that loads once for each request. Thus a
 * page that reads twenty settings makes one query. set() writes only that
 * copy, as the callers expect. Endpointman::__construct fills an empty `tz`
 * from PHPTIMEZONE and sets the runtime value `disable_epm`. Neither must
 * change the database. save() writes to the database.
 *
 * @author Javier Pastor
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */

namespace FreePBX\modules\Endpointman;

class Config {

	/**
	 * Each setting that the module reads, with the value of a new install.
	 *
	 * This list is also the list of all keys. A get() for a different key
	 * returns the default of its caller.
	 *
	 * install.php sets `nmap_location`, `arp_location` and `asterisk_location`
	 * from epm_find_exec(), because they depend on the host. `arp_location` holds
	 * the neighbor table tool that install.php found: `ip` (iproute2) before
	 * `arp` (net-tools). Debian 12 has `ip` but not `arp`, and
	 * epm_system::neighbour_table() reads both. Empty means that install.php found
	 * no tool, and the module searches PATH at run time.
	 */
	private static $defaults = array(
		'srvip'                    => '',
		'intsrvip'                 => '',
		'tz'                       => '',
		'gmtoff'                   => '',
		'gmthr'                    => '',
		'config_location'          => '/tftpboot/',
		'update_server'            => '',
		'version'                  => '',
		'debug'                    => '0',
		'arp_location'             => '',
		'nmap_location'            => '',
		'asterisk_location'        => '',
		// The Aastra program that encrypts config files, and its key (-p). The
		// admin installs the program. Empty means that the module never runs it.
		// See epm_system::anacrypt_problem().
		'anacrypt_location'        => '',
		'anacrypt_key'             => '1234abcd',
		'language'                 => '',
		'check_updates'            => '0',
		'disable_help'             => '0',
		'disable_endpoint_warning' => '0',
		'show_all_registrations'   => '0',
		'ntp'                      => '',
		'server_type'              => 'file',
		// The addresses that can get a device config over HTTP. Empty denies all
		// addresses (see lib/acl.inc.php). The default is the private ranges. Thus an
		// upgrade from a release without an ACL continues to serve the LAN phones but
		// stops serving the internet.
		'provision_acl'            => '127.0.0.1/32, ::1/128, 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16',
		// Reverse proxies whose X-Forwarded-For and X-Forwarded-Proto headers p.php
		// can believe. Empty means none, and p.php ignores the headers.
		'trusted_proxies'          => '',
		'allow_hdfiles'            => '0',
		'tftp_check'               => '0',
		'nmap_search'              => '',
		'backup_check'             => '0',
		'adminpass'                => '123456',
		'userpass'                 => '111111',
	);

	/** @var \FreePBX\modules\Endpointman the BMO object that owns the KVStore */
	private $epm;

	/** @var array the request-lifetime copy of every setting */
	private $module_conf = array();

	public function __construct($epm) {
		$this->epm = $epm;
		$this->load();
	}

	/**
	 * The defaults of a new install. install.php writes these into the KVStore,
	 * and load() uses them for a key that has no stored value.
	 *
	 * @return array
	 */
	public static function defaults() {
		return self::$defaults;
	}

	/**
	 * Read each stored setting into memory.
	 *
	 * @param bool $clear discard the copy in memory first. False keeps the
	 *                    runtime values that set() wrote. The page
	 *                    views/settings.inc/config.tab.php needs this after a
	 *                    save, so the page shows the values from the database.
	 * @return void
	 */
	public function load($clear = true) {
		if ($clear) { $this->module_conf = array(); }

		$stored = $this->epm->getAll();
		if (!is_array($stored)) { $stored = array(); }

		$this->module_conf = array_merge(self::$defaults, $stored, $this->module_conf);
	}

	/**
	 * Old name for load(). The settings view calls this name, so it stays.
	 *
	 * @param bool $clear
	 * @return void
	 */
	public function getConfigModuleSQL($clear = true) {
		$this->load($clear);
	}

	public function getall() {
		return $this->module_conf;
	}

	public function isExiste($var) {
		return isset($this->module_conf[$var]);
	}

	public function get($var, $defvar = "") {
		$varreturn = "";
		if ($this->isExiste($var)) {
			$varreturn = $this->module_conf[$var];
		}
		elseif ($defvar != "") {
			$this->module_conf[$var] = $defvar;
		}
		if ($varreturn == "") {
			$varreturn = $defvar;
		}
		return $varreturn;
	}

	/**
	 * Set a value for the rest of this request only.
	 *
	 * @param string $var
	 * @param mixed  $val
	 * @return void
	 */
	public function set($var, $val) {
		$this->module_conf[$var] = $val;
	}

	/**
	 * Set a value and write it to the KVStore.
	 *
	 * The function stores values as strings. setConfig() reads boolean false as
	 * "delete this key". Without the string, a caller that sends false removes
	 * the setting and does not store "0".
	 *
	 * @param string $var
	 * @param mixed  $val
	 * @return void
	 */
	public function save($var, $val) {
		if (is_bool($val)) { $val = $val ? '1' : '0'; }
		$val = (string) $val;

		$this->module_conf[$var] = $val;
		$this->epm->setConfig($var, $val);
	}

	/**
	 * Remove a setting from memory and from the KVStore.
	 *
	 * @param string $var
	 * @return void
	 */
	public function del($var) {
		unset($this->module_conf[$var]);
		$this->epm->delConfig($var);
	}
}
