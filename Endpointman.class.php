<?php
/**
 * Endpoint Manager Object Module
 *
 * @author Andrew Nagy
 * @author Javier Pastor
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */

namespace FreePBX\modules;

use FreePBX_Helpers;

function format_txt($texto = "", $css_class = "", $remplace_txt = array()) {
	if (count($remplace_txt) > 0)
	{
		foreach ($remplace_txt as $clave => $valor) {
			$texto = str_replace($clave, $valor, $texto);
		}
	}
	return '<p ' . ($css_class != '' ? 'class="' . $css_class . '"' : '') . '>'.$texto.'</p>';
}

function generate_xml_from_array ($array, $node_name, &$tab = -1) {
	$tab++;
	$xml ="";
	if (is_array($array) || is_object($array)) {
		foreach ($array as $key=>$value) {
			if (is_numeric($key)) {
				$key = $node_name;
			}

			$xml .= str_repeat("	", $tab). '<' . $key . '>' . "\n";
			$xml .= generate_xml_from_array($value, $node_name, $tab);
			$xml .= str_repeat("	", $tab). '</' . $key . '>' . "\n";

		}
	} else {
		$xml = str_repeat("	", $tab) . htmlspecialchars($array, ENT_QUOTES) . "\n";
	}
	$tab--;
	return $xml;
}


class Endpointman extends FreePBX_Helpers implements \BMO {

	/**
	 * The default package server for brand packages.
	 *
	 * provisioner.net does not resolve, and provision.lol, the old default, is a
	 * parked domain. `unex/provisioner` publishes to GitHub Pages and uses the
	 * protocol that BrandManager expects. /v4/ holds the packages with data
	 * only. The root keeps the 2023 packages with PHP for FreePBX 16, and
	 * extract_package() refuses those.
	 *
	 * The URL must use HTTPS. GitHub Pages answers http:// with a 301.
	 * download_file_with_progress_bar() does not follow redirects, so an http://
	 * URL downloads nothing.
	 */
	const DEFAULT_UPDATE_SERVER = 'https://unex.github.io/provisioner/v4/';

	/**
	 * Normalize a package server URL, or reject it.
	 *
	 * Each caller in BrandManager adds a file name to the end of this value.
	 * download_file_with_progress_bar() reads the HTTP status line and does not
	 * follow redirects. Thus the value must end in a slash, and it must be the
	 * URL that serves the file, not a URL that redirects.
	 *
	 * This function tests an http:// URL before it stores it. If the server
	 * answers with a 301 to https://, the function stores the https:// URL and
	 * tells the caller. A LAN mirror that answers 200 on plain HTTP stays as it
	 * is. If the server does not answer, the function keeps the value, because
	 * the admin can set up a mirror before it runs.
	 *
	 * @param string $url   the value that the admin typed
	 * @param string $error set to the reason on failure, or to a note about a
	 *                      change that the function made
	 * @return string|false the URL to store, or false if it is not usable
	 */
	public static function normalizeUpdateServer($url, &$error = null) {
		$url = trim((string) $url);
		if ($url === '') {
			$error = _("No package server given.");
			return false;
		}

		// Admins often type a bare host. Add the https:// scheme.
		if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
			$url = 'https://' . $url;
		}

		$parts = parse_url($url);
		if ($parts === false || empty($parts['host'])) {
			$error = sprintf(_("'%s' is not a URL."), $url);
			return false;
		}
		$scheme = strtolower($parts['scheme'] ?? '');
		if ($scheme !== 'http' && $scheme !== 'https') {
			$error = sprintf(_("Package servers must be http or https, not '%s'."), $scheme);
			return false;
		}

		$path = $parts['path'] ?? '/';
		$out  = 'https://' . $parts['host']
		      . (isset($parts['port']) ? ':' . $parts['port'] : '')
		      . rtrim($path, '/') . '/';

		// parse_url() accepts almost any text as a host, also text with spaces. Thus
		// check the full URL.
		if (filter_var($out, FILTER_VALIDATE_URL) === false) {
			$error = sprintf(_("'%s' is not a URL."), $url);
			return false;
		}

		if ($scheme !== 'http') {
			return $out;
		}

		// Ask the server what it does with http:// before you store the URL. Limit
		// the time of the probe. This runs inside the save of the admin, and
		// get_headers() otherwise waits for default_socket_timeout (60 seconds) when
		// the address does not answer.
		$plain = 'http://' . substr($out, strlen('https://'));
		$context = stream_context_create(array(
			'http' => array('method' => 'HEAD', 'timeout' => 5, 'follow_location' => 0),
		));
		$headers = @get_headers($plain . 'master.json', 1, $context);
		if ($headers === false) {
			$error = sprintf(_("Saved, but %s did not answer. Check the address if downloads fail."), $plain);
			return $plain;
		}
		$status = is_array($headers[0]) ? end($headers[0]) : $headers[0];
		if (preg_match('/30[1278]/', $status)) {
			$error = sprintf(_("%s redirects, and a redirect stops downloads. The module uses %s."), $plain, $out);
			return $out;
		}
		return $plain;
	}

	//public $epm_config;


	public $db; //Database from FreePBX
	public $eda; //endpoint data abstraction layer
	public $tpl; //Template System Object (RAIN TPL)
	//public $system;

    public $error; //error construct
    public $message; //message construct

	public $UPDATE_PATH;
    public $MODULES_PATH;
	public $LOCAL_PATH;
	public $PHONE_MODULES_PATH;
	public $PROVISIONER_BASE;


	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception("Not given a FreePBX Object");
		}
		require_once(__DIR__ . '/lib/sql.inc.php');
		require_once(__DIR__ . '/lib/blob.inc.php');
		require_once(__DIR__ . '/lib/acl.inc.php');
		require_once(__DIR__ . '/lib/esc.inc.php');
		require_once('lib/Config.class.php');
		require_once('lib/epm_system.class.php');
		require_once('lib/epm_data_abstraction.class.php');

		$this->freepbx = $freepbx;
		$this->db = $freepbx->Database;
		$this->config = $freepbx->Config;
		$this->configmod = new Endpointman\Config($this);
		$this->system = new epm_system();
		$this->eda = new epm_data_abstraction($this);

		$this->configmod->set('disable_epm', FALSE);
		$this->eda->global_cfg = $this->configmod->getall();

        //Generate empty array
        $this->error = array();
        $this->message = array();


		// The time zone of the phones. The admin sets it on the settings page.
		// PHPTIMEZONE fills it only while it is empty. FreePBX sets the default time
		// zone of PHP (View::setTimezone), not this module.
		if ($this->configmod->get('tz') === '') {
			$this->configmod->set('tz', $this->config->get('PHPTIMEZONE'));
		}

		$this->UPDATE_PATH = $this->configmod->get('update_server');
        $this->MODULES_PATH = $this->config->get('AMPWEBROOT') . '/admin/modules/';

		// define("UPDATE_PATH", $this->UPDATE_PATH);
		// define("MODULES_PATH", $this->MODULES_PATH);

        //Determine if local path is correct!
        if (file_exists($this->MODULES_PATH . "endpointman/")) {
            $this->LOCAL_PATH = $this->MODULES_PATH . "endpointman/";

		// define("LOCAL_PATH", $this->LOCAL_PATH);

        } else {
            throw new \Exception("Can't Load Local Endpoint Manager Directory: " . $this->MODULES_PATH . "endpointman/");
        }

        //Define the location of phone modules, keeping it outside of the module directory so that when the user updates endpointmanager they don't lose all of their phones
        if (file_exists($this->MODULES_PATH . "_ep_phone_modules/")) {
            $this->PHONE_MODULES_PATH = $this->MODULES_PATH . "_ep_phone_modules/";
        } else {
            $this->PHONE_MODULES_PATH = $this->MODULES_PATH . "_ep_phone_modules/";
            if (!file_exists($this->PHONE_MODULES_PATH)) {
                mkdir($this->PHONE_MODULES_PATH, 0775);
            }
            if (file_exists($this->PHONE_MODULES_PATH . "setup.php")) {
                unlink($this->PHONE_MODULES_PATH . "setup.php");
            }
            if (!file_exists($this->MODULES_PATH . "_ep_phone_modules/")) {
                throw new \Exception("Endpoint Manager can not create the modules folder: " . $this->PHONE_MODULES_PATH);
            }
        }

		// define("PHONE_MODULES_PATH", $this->PHONE_MODULES_PATH);

        //Define error reporting
        if (($this->configmod->get('debug')) AND (!isset($_REQUEST['quietmode']))) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        } else {
            ini_set('display_errors', 0);
        }

        //Check if config location is writable and/or exists!
        if ($this->configmod->isExiste('config_location')) {
			$config_location = $this->configmod->get('config_location');
            if (is_dir($config_location)) {
                if (!is_writeable($config_location)) {
                    $user = exec('whoami');
                    $group = exec("groups");
                    $this->error['config_location'] = _("Configuration Directory is not writable!") . "<br />" .
                            _("Please change the location:") . "<a href='config.php?display=endpointman&view=settings'>" . _("Here") . "</a><br />" .
                            _("Or run this command on SSH:") . "<br />" .
							"'chown -hR root: " . $group . " " . $config_location . "'<br />" .
							"'chmod g+w " . $config_location . "'";
					$this->configmod->set('disable_epm', TRUE);
                }
            } else {
                $this->error['config_location'] = _("Configuration Directory is not a directory or does not exist! Please change the location here:") . "<a href='config.php?display=endpointman&view=settings'>" . _("Here") . "</a>";
				$this->configmod->set('disable_epm', TRUE);
            }
        }

        // Start the view layer. The views are plain PHP files in views/tpl/, and
        // nothing is compiled or cached. The pages call assign() in one place and
        // draw() in another, so the class keeps that API.
        require_once($this->LOCAL_PATH . 'lib/View.class.php');
        $this->tpl = new \Endpointman\View($this->LOCAL_PATH . 'views/tpl');
        if (!empty($this->configmod->get('disable_help'))) {
            $this->tpl->assign("disable_help", 1);
        }



		// require_once('Endpointman_Config.class.php');
		// $this->epm_config = new Endpointman_Config($freepbx, $this->configmod, $this->system);

		require_once('BrandManager.class.php');

		$this -> brandmanager = new \BrandManager($this);

		require_once('AboutPage.class.php');
		require_once('SettingsPage.class.php');
		require_once('DevicesPage.class.php');
		require_once('PackagesPage.class.php');
		require_once('TemplatesPage.class.php');
		require_once('PlaceholdersPage.class.php');

		// The keys are the ?view= values. views/bootnav.php shows the menu.
		// "devices" and "packages" are the old names of "phones" and "brands".
		// The page scripts send their ajax commands and popup commands to the
		// old names, and old links use them too, so both names stay.
		$devices = new DevicesPage($this);
		$packages = new PackagesPage($this);
		$this -> pages = array(
			"about" => new AboutPage($this),
			"settings" => new SettingsPage($this),
			"phones" => $devices,
			"devices" => $devices,
			"brands" => $packages,
			"packages" => $packages,
			"templates" => new TemplatesPage($this),
			"placeholders" => new PlaceholdersPage($this),
		);

	}

	/**
	 * Get the menu item of a ?view= value.
	 *
	 * An empty view is the landing page, which is the phone list. An old name
	 * gives the menu item of its new name. An unknown view gives "about", the
	 * same as _get_page().
	 *
	 * @param mixed $name value of ?view=
	 * @return string one of phones, brands, templates, settings, about
	 */
	public function menuItem($name) {
		$alias = array('' => 'phones', 'devices' => 'phones', 'packages' => 'brands', 'placeholders' => 'templates');
		if (is_string($name) && isset($alias[$name])) {
			return $alias[$name];
		}
		if (is_string($name) && in_array($name, array('phones', 'brands', 'templates', 'settings'), true)) {
			return $name;
		}
		return 'about';
	}

	/**
	 * Find the page object for a ?view= value.
	 *
	 * $name comes from $_REQUEST, so do not trust it. An empty view gets the
	 * phone list. An unknown view gets the "about" page, not null.
	 * doConfigPageInit() runs before showPage(), so a null here causes a 500
	 * before the page shows.
	 *
	 * @param string $name value of ?view=, possibly empty or unknown
	 * @return object the matching page object, or the "about" page
	 */
	function _get_page($name) {
		if ($name === '') {
			return $this -> pages["phones"];
		}
		if (!is_string($name) || !isset($this -> pages[$name])) {
			return $this -> pages["about"];
		}
		return $this -> pages[$name];
	}

	// Use 'rdir', not 'dir'. FreePBX applies 'dir' only to the directory itself.
	// install.php runs as root under `fwconsole ma install` and creates
	// endpoint/ and temp/ in _ep_phone_modules. With 'dir', these directories
	// stay owned by root, and the web server cannot download a brand.
	public function chownFreepbx() {
		$webroot = $this->config->get('AMPWEBROOT');
		$modulesdir = $webroot . '/admin/modules/';
		$files = array();
		$files[] = array('type' => 'rdir',
						'path' => $modulesdir . '/_ep_phone_modules/',
						'perms' => 0755);
		$files[] = array('type' => 'rdir',
						'path' => '/tftpboot',
						'perms' => 0755);
		return $files;
	}

	/**
	 * This returns html to the main page
	 *
	 * @return string html
	 */
	public function showPage() {
		$view = $this -> getReq("view", "");
		return $this -> _get_page($view) -> showPage();
	}

	/**
	 * Authorize one ajax command.
	 *
	 * FreePBX's Ajax::doRequest() asks the module object, not the page object.
	 * This method sends the question to the page that runs the handler. Each
	 * page has its own allowlist. If the page does not know the command, the
	 * method returns false and doRequest() sends 403 before ajaxHandler() runs.
	 *
	 * $req is the $command of ajax.php, so the view comes from the request. It
	 * must be the same view that ajaxHandler() uses. Otherwise the check
	 * protects a different page from the page that runs.
	 *
	 * @param string $req     value of ?command=
	 * @param array  $setting authenticate/allowremote/changesession, by reference
	 * @return bool true to allow the command
	 */
	public function ajaxRequest($req, &$setting) {
		$view = $this -> getReq("view", "");
		return $this -> _get_page($view) -> ajaxRequest($req, $setting);
    }

    public function ajaxHandler() {
		$view = $this -> getReq("view", "");
		$command = $this -> getReq("command", "");

		return $this -> _get_page($view) -> ajaxHandler($command);
    }

	/**
	 * Get the action bar buttons for the current page.
	 *
	 * config.php:854 calls getActionBar() on the module object, not on the page
	 * object. This method sends the call to the page. Without it, no page shows
	 * an action bar, and the template editor has no Save button.
	 *
	 * FreePBX expects an array. It reads any other value as "no buttons", so a
	 * page can return "".
	 *
	 * @param array $request $_REQUEST, as config.php passes it
	 * @return array|string buttons keyed by submit/duplicate/reset/delete
	 */
	public function getActionBar($request) {
		$view = $this -> getReq("view", "");
		return $this -> _get_page($view) -> getActionBar($request);
	}

	/**
	 * Get the right navigation for the current page.
	 *
	 * config.php:888 also calls this on the module object. Only three pages
	 * define it, so check for the method first. An undefined method here stops
	 * the footer after the page shows.
	 *
	 * @param array $request $_REQUEST, as config.php passes it
	 * @return string html, or '' for a page with no side navigation
	 */
	public function getRightNav($request) {
		$view = $this -> getReq("view", "");
		$page = $this -> _get_page($view);
		if (!method_exists($page, 'getRightNav')) {
			return '';
		}
		return $page -> getRightNav($request);
	}

	public static function myDialplanHooks() {
		return true;
	}

	public function doConfigPageInit($page) {
		$view = $this -> getReq("view", "");
		$command = $this -> getReq("command", "");

		return $this -> _get_page($view) -> doConfigPageInit($command);
	}

	public function doGeneralPost() {
		if (!isset($_REQUEST['Submit'])) 	{ return; }
		if (!isset($_REQUEST['display'])) 	{ return; }

		needreload();
	}

	public function getActiveModules() {}


	public function install() {

	}

	public function uninstall() {
		if(file_exists($this->PHONE_MODULES_PATH)) {
			out(_("Removing Phone Modules Directory"));
			$this->system->rmrf($this->PHONE_MODULES_PATH);
			@exec("rm -R ". $this->PHONE_MODULES_PATH);
		}

		$provisioning_path = $this->config->get('AMPWEBROOT')."/provisioning";
		if(file_exists($provisioning_path) && is_link($provisioning_path)) {
			out(_('Removing symlink to web provisioner'));
			unlink($provisioning_path);
		}

		if(!is_link($this->config->get('AMPWEBROOT').'/admin/assets/endpointman')) {
			$this->system->rmrf($this->config->get('AMPWEBROOT').'/admin/assets/endpointman');
		}
		return true;
	}

	// The backup and restore support is in Backup.php and Restore.php in this
	// directory. Backup.class.php::getModules() looks for these files. The old
	// backup() and restore() hooks of FreePBX 13 are not used.


	// Manual upload is in SettingsPage (Advanced Settings, Manual Upload).

     /**
     * Returns list of Brands that are installed and not hidden and that have at least one model enabled under them
     * @param integer $selected ID Number of the brand that is supposed to be selected in a drop-down list box
     * @return array Number array used to generate a select box
     */
    function brands_available($selected = NULL, $show_blank=TRUE) {
        $data = $this->eda->all_active_brands();
        $temp = array();
        if ($show_blank) {
            $temp[0]['value'] = "";
            $temp[0]['text'] = "";
            $i = 1;
        } else {
            $i = 0;
        }
        foreach ($data as $row) {
            $temp[$i]['value'] = $row['id'];
            $temp[$i]['text'] = $row['name'];
            if ($row['id'] == $selected) {
                $temp[$i]['selected'] = TRUE;
            } else {
                $temp[$i]['selected'] = NULL;
            }
            $i++;
        }
        return($temp);
    }

	function listTZ($selected) {
        $data = \DateTimeZone::listIdentifiers();
        $temp = array();
        $i = 0;
        foreach ($data as $key => $row) {
            $temp[$i]['value'] = $row;
            $temp[$i]['text'] = $row;
            if (strtoupper ($temp[$i]['value']) == strtoupper($selected)) {
                $temp[$i]['selected'] = 1;
            } else {
                $temp[$i]['selected'] = 0;
            }
            $i++;
        }

        return($temp);
    }

    function tftp_check() {
        //create a simple block here incase people have strange issues going on as we will kill http
        //by running this if the server isn't really running!
        if ($this->configmod->get('tftp_check') != 1) {
            $this->configmod->save('tftp_check', '1');
            // udp_port_listening() uses `ss`, or `netstat` when `ss` is not available.
            // Debian 12 has no net-tools. NULL means that neither tool is installed. This
            // is not the same as "nothing is listening", so report it. In both cases,
            // skip the fetch below, because the check prevents a fetch when no server
            // runs.
            $listening = $this->system->udp_port_listening(69);
            if ($listening === NULL) {
                $this->error['tftp_check'] = _('Cannot check for a TFTP server. Neither ss (iproute2) nor netstat (net-tools) is installed.');
            } elseif ($listening) {
                $rand = md5(rand(10, 2000));
                if (file_put_contents($this->configmod->get('config_location') . 'TEST', $rand)) {
                    if ($this->system->tftp_fetch('127.0.0.1', 'TEST') != $rand) {
                        $this->error['tftp_check'] = 'Local TFTP Server is not correctly configured';
                    }
                    unlink($this->configmod->get('config_location') . 'TEST');
                } else {
                    $this->error['tftp_check'] = 'Unable to write to ' . epm_esc($this->configmod->get('config_location'));
                }
            } else {
                $dis = FALSE;
                if (file_exists('/etc/xinetd.d/tftp')) {
                    $contents = file_get_contents('/etc/xinetd.d/tftp');
                    if (preg_match('/disable.*=.*yes/i', $contents)) {
                        $this->error['tftp_check'] = 'Disabled is set to "yes" in /etc/xinetd.d/tftp. Please fix <br />Then restart your TFTP service';
                        $dis = TRUE;
                    }
                }
                if (!$dis) {
                    // wiki.provisioner.net does not resolve. Point to Debian's tftpd-hpa, which
                    // FreePBX 17 uses.
                    $this->error['tftp_check'] = _('The TFTP server is not running. Install and enable tftpd-hpa, and set its root to the configuration directory below.');
                }
            }
            $this->configmod->save('tftp_check', '0');
        } else {
            $this->error['tftp_check'] = 'TFTP Server check failed on last past. Skipping';
        }
    }


    /**
     * Send sample configurations upstream. This always refuses.
     *
     * There is no place to send them. The old endpoint was on provisioner.net,
     * which does not resolve. provision.lol replaced it in the old defaults, but
     * it is a parked domain. A POST of a phone config would go to the owner of
     * that domain.
     *
     * Two callers in includes/popup.inc.php read the return value, so the method
     * stays. Put a new submission endpoint here if the project gets one.
     *
     * @param string $brand     Brand Directory
     * @param string $product   Product Directory
     * @param string $orig_name The original name of the file to send
     * @param string $data      The data of the config file
     * @return false
    */
    function submit_config($brand, $product, $orig_name, $data) {
    	$this->error['submit_config'] = _("The module cannot send configurations upstream, because the project has no submission endpoint. Open a pull request at https://github.com/unex/provisioner.");
    	return false;
    }

    /**
     * Save template from the template view pain
     * @param int $id Either the MAC ID or Template ID
     * @param int $custom Either 0 or 1, it determines if $id is MAC ID or Template ID
     * @param array $variables The variables sent from the form. usually everything in $_REQUEST[]
     * @return string Location of area to return to in Endpoint Manager
     */
    function save_template($id, $custom, $variables) {
        //Custom Means specific to that MAC
        //This function is reversed. Not sure why

        if ($custom != "0") {
            $sql = 'SELECT endpointman_model_list.max_lines, endpointman_product_list.config_files, endpointman_mac_list.*, endpointman_product_list.id as product_id, endpointman_product_list.long_name, endpointman_model_list.template_data, endpointman_product_list.cfg_dir, endpointman_brand_list.directory FROM endpointman_brand_list, endpointman_mac_list, endpointman_model_list, endpointman_product_list WHERE endpointman_mac_list.id=? AND endpointman_mac_list.model = endpointman_model_list.id AND endpointman_model_list.brand = endpointman_brand_list.id AND endpointman_model_list.product_id = endpointman_product_list.id';
            $sql_p = array($id);
        } else {
            $sql = 'SELECT endpointman_model_list.max_lines, endpointman_brand_list.directory, endpointman_product_list.cfg_dir, endpointman_product_list.config_files, endpointman_product_list.long_name, endpointman_model_list.template_data, endpointman_model_list.id as model_id, endpointman_template_list.* FROM endpointman_brand_list, endpointman_product_list, endpointman_model_list, endpointman_template_list WHERE endpointman_product_list.id = endpointman_template_list.product_id AND endpointman_brand_list.id = endpointman_product_list.brand AND endpointman_template_list.model_id = endpointman_model_list.id AND endpointman_template_list.id = ?';
            $sql_p = array($id);
        }

        //Load template data
        $row = epm_sql($sql, 'getRow', $sql_p);
        if (empty($row)) {
            $this->error['save_template'] = _("No such template.");
            return false;
        }

        $cfg_data = epm_blob_decode($row['template_data'], array());
        if (!isset($cfg_data['data']) || !is_array($cfg_data['data'])) {
            $cfg_data['data'] = array();
        }

        $custom_cfg_data = array();
        $custom_cfg_data_ari = array();

        foreach ($cfg_data['data'] as $cats) {
            foreach ($cats as $items) {
                foreach ($items as $key_name => $config_options) {
                    if (preg_match('/(.*)\|(.*)/i', $key_name, $matches)) {
                        $type = $matches[1];
                        $key = $matches[2];
                    } else {
                        die('invalid');
                    }
                    switch ($type) {
                        case "loop":
                            $stuffing = explode("_", $key);
                            $key2 = $stuffing[0];
                            foreach ($config_options as $item_key => $item_data) {
                                $lc = isset($item_data['loop_count']) ? $item_data['loop_count'] : '';
                                $key = 'loop|' . $key2 . '_' . $item_key . '_' . $lc;
                                if ((isset($item_data['loop_count'])) AND (isset($variables[$key]))) {
                                    $custom_cfg_data[$key] = $variables[$key];
                                    $ari_key = "ari_" . $key;
                                    if (isset($variables[$ari_key])) {
                                        if ($variables[$ari_key] == "on") {
                                            $custom_cfg_data_ari[$key] = 1;
                                        }
                                    }
                                }
                            }
                            break;
                        case "lineloop":
                            foreach ($config_options as $item_key => $item_data) {
                                $lc = isset($item_data['line_count']) ? $item_data['line_count'] : '';
                                $key = 'line|' . $lc . '|' . $item_key;
                                if ((isset($item_data['line_count'])) AND (isset($variables[$key]))) {
                                    $custom_cfg_data[$key] = $variables[$key];
                                    $ari_key = "ari_" . $key;
                                    if (isset($variables[$ari_key])) {
                                        if ($variables[$ari_key] == "on") {
                                            $custom_cfg_data_ari[$key] = 1;
                                        }
                                    }
                                }
                            }
                            break;
                        case "option":
                            if (isset($variables[$key])) {
                                $custom_cfg_data[$key] = $variables[$key];
                                $ari_key = "ari_" . $key;
                                if (isset($variables[$ari_key])) {
                                    if ($variables[$ari_key] == "on") {
                                        $custom_cfg_data_ari[$key] = 1;
                                    }
                                }
                            }
                            break;
                        default:
                            break;
                    }
                }
            }
        }

        // The editor numbers its config_files[] selects by the position in the
        // sorted file list. edit_template_display_files_list() sorts it with asort().
        // Use the same order here. Otherwise a product with unsorted files stores the
        // override under the wrong file.
        $config_files = explode(",", $row['config_files']);
        asort($config_files);
        $config_files = array_values($config_files);

        $i = 0;
        while ($i < count($config_files)) {
            $config_files[$i] = str_replace(".", "_", $config_files[$i]);

            if (isset($variables['config_files'][$i])) {

                $variables[$config_files[$i]] = explode("_", $variables['config_files'][$i], 2);

                $variables[$config_files[$i]] = $variables[$config_files[$i]][0];
                if ($variables[$config_files[$i]] > 0) {
                    $config_files_selected[$config_files[$i]] = $variables[$config_files[$i]];


                }
            }
            $i++;
        }
        if (!isset($config_files_selected)) {
            $config_files_selected = "";
        } else {
            $config_files_selected = epm_blob_encode($config_files_selected);
        }
        $custom_cfg_data_temp['data'] = $custom_cfg_data;
        $custom_cfg_data_temp['ari'] = $custom_cfg_data_ari;

        $save = epm_blob_encode($custom_cfg_data_temp);
        if ($custom == "0") {
            $sql = 'UPDATE endpointman_template_list SET config_files_override = ?, global_custom_cfg_data = ? WHERE id =?';
            $sql_p = array($config_files_selected, $save, $id);
            $location = "template_manager";
			//print_r($sql);
        } else {
            $sql = 'UPDATE endpointman_mac_list SET config_files_override = ?, template_id = 0, global_custom_cfg_data = ? WHERE id =?';
            $sql_p = array($config_files_selected, $save, $id);
            $location = "devices_manager";
        }
        epm_sql($sql, 'query', $sql_p);

        $phone_info = array();

        if (isset($variables['silent_mode'])) {
            echo '<script language="javascript" type="text/javascript">window.close();</script>';
        } else {
            return($location);
        }
    }

    function display_configs() {
    }
}
