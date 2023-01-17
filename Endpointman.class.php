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

function format_txt($texto = "", $css_class = "", $remplace_txt = array())
{
	if (count($remplace_txt) > 0)
	{
		foreach ($remplace_txt as $clave => $valor) {
			$texto = str_replace($clave, $valor, $texto);
		}
	}
	return '<p ' . ($css_class != '' ? 'class="' . $css_class . '"' : '') . '>'.$texto.'</p>';
}

function generate_xml_from_array ($array, $node_name, &$tab = -1)
{
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


class Endpointman implements \BMO {

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
		require_once('lib/json.class.php');
		require_once('lib/Config.class.php');
		require_once('lib/epm_system.class.php');
		require_once('lib/datetimezone.class.php');
		require_once('lib/epm_data_abstraction.class.php');
		//require_once("lib/RainTPL.class.php");

		$this->freepbx = $freepbx;
		$this->db = $freepbx->Database;
		$this->config = $freepbx->Config;
		$this->configmod = new Endpointman\Config();
		$this->system = new epm_system();
		$this->eda = new epm_data_abstraction($this->config, $this->configmod);


		$this->configmod->set('disable_epm', FALSE);
		$this->eda->global_cfg = $this->configmod->getall();

        //Generate empty array
        $this->error = array();
        $this->message = array();


		$this->configmod->set('tz', $this->config->get('PHPTIMEZONE'));
		date_default_timezone_set($this->configmod->get('tz'));

		$this->UPDATE_PATH = $this->configmod->get('update_server');
        $this->MODULES_PATH = $this->config->get('AMPWEBROOT') . '/admin/modules/';

define("UPDATE_PATH", $this->UPDATE_PATH);
define("MODULES_PATH", $this->MODULES_PATH);


        //Determine if local path is correct!
        if (file_exists($this->MODULES_PATH . "endpointman/")) {
            $this->LOCAL_PATH = $this->MODULES_PATH . "endpointman/";
define("LOCAL_PATH", $this->LOCAL_PATH);
        } else {
            die("Can't Load Local Endpoint Manager Directory!");
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
                die('Endpoint Manager can not create the modules folder!');
            }
        }
define("PHONE_MODULES_PATH", $this->PHONE_MODULES_PATH);

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
                            _("Please change the location:") . "<a href='config.php?display=epm_advanced'>" . _("Here") . "</a><br />" .
                            _("Or run this command on SSH:") . "<br />" .
							"'chown -hR root: " . $group . " " . $config_location . "'<br />" .
							"'chmod g+w " . $config_location . "'";
					$this->configmod->set('disable_epm', TRUE);
                }
            } else {
                $this->error['config_location'] = _("Configuration Directory is not a directory or does not exist! Please change the location here:") . "<a href='config.php?display=epm_advanced'>" . _("Here") . "</a>";
				$this->configmod->set('disable_epm', TRUE);
            }
        }

        //$this->tpl = new RainTPL(LOCAL_PATH . '_old/templates/freepbx', LOCAL_PATH . '_old/templates/freepbx/compiled', '/admin/assets/endpointman/images');
		//$this->tpl = new RainTPL('/admin/assets/endpointman/images');


		require_once('Endpointman_Config.class.php');
		$this->epm_config = new Endpointman_Config($freepbx, $this->configmod, $this->system);

		require_once('Endpointman_Advanced.class.php');
		$this->epm_advanced = new Endpointman_Advanced($freepbx, $this->configmod, $this->epm_config);

		require_once('Endpointman_Templates.class.php');
		$this->epm_templates = new Endpointman_Templates($freepbx, $this->configmod, $this->epm_config, $this->eda);

		require_once('Endpointman_Devices.class.php');
		$this->epm_devices = new Endpointman_Devices($freepbx, $this->configmod);

		require_once('Endpointman_Devices.class.php');
		$this->epm_oss = new Endpointman_Devices($freepbx, $this->configmod);

		//require_once('Endpointman_Devices.class.php');
		$this->epm_placeholders = new Endpointman_Devices($freepbx, $this->configmod);

	}

	public function chownFreepbx() {
		$webroot = $this->config->get('AMPWEBROOT');
		$modulesdir = $webroot . '/admin/modules/';
		$files = array();
		$files[] = array('type' => 'dir',
						'path' => $modulesdir . '/_ep_phone_modules/',
						'perms' => 0755);
		$files[] = array('type' => 'file',
						'path' => $modulesdir . '/_ep_phone_modules/setup.php',
						'perms' => 0755);
		$files[] = array('type' => 'dir',
						'path' => '/tftpboot',
						'perms' => 0755);
		return $files;
	}

	public function ajaxRequest($req, &$setting) {
		//AVISO!!!!!!!!!!!!!!!!!!!!!!!!!!
		//PERMITE TODO!!!!!!!!!!!!!!!!!!!
		$setting['authenticate'] = true;
		$setting['allowremote'] = true;
		return true;

		$module_sec = isset($_REQUEST['module_sec'])? trim($_REQUEST['module_sec']) : '';
		if ($module_sec == "") { return false; }

		switch($module_sec)
		{
			case "epm_devices":
				return $this->epm_devices->ajaxRequest(trim($req), $setting);
				break;

			case "epm_oss":
				return $this->epm_oss->ajaxRequest(trim($req), $setting);
				break;

			case "epm_placeholders":
				return $this->epm_placeholders->ajaxRequest(trim($req), $setting);
				break;

			case "epm_config":
				return $this->epm_config->ajaxRequest(trim($req), $setting);
				break;

			case "epm_advanced":
				return $this->epm_advanced->ajaxRequest(trim($req), $setting);
				break;

			case "epm_templates":
				return $this->epm_templates->ajaxRequest(trim($req), $setting);
				break;
		}
        return false;
    }

    public function ajaxHandler() {

		$module_sec = isset($_REQUEST['module_sec'])? trim($_REQUEST['module_sec']) : '';
		$module_tab = isset($_REQUEST['module_tab'])? trim($_REQUEST['module_tab']) : '';
		$command = isset($_REQUEST['command'])? trim($_REQUEST['command']) : '';

		if ($command == "") {
			return array("status" => false, "message" => _("No command was sent!"));
		}

		$arrVal['mod_sec'] = array("epm_devices", "epm_oss", "epm_placeholders", "epm_templates", "epm_config", "epm_advanced");
		if (! in_array($module_sec, $arrVal['mod_sec'])) {
			return array("status" => false, "message" => _("Invalid section module!"));
		}

		switch ($module_sec)
		{
			case "epm_devices":
				return $this->epm_devices->ajaxHandler($module_tab, $command);
				break;

			case "epm_oss":
				return $this->epm_oss->ajaxHandler($module_tab, $command);
				break;
			case "epm_placeholders":
				return $this->epm_placeholders->ajaxHandler($module_tab, $command);
				break;

			case "epm_templates":
				return $this->epm_templates->ajaxHandler($module_tab, $command);
				break;

			case "epm_config":
				return $this->epm_config->ajaxHandler($module_tab, $command);
				break;

			case "epm_advanced":
				return $this->epm_advanced->ajaxHandler($module_tab, $command);
				break;
		}
		return false;
    }

	public static function myDialplanHooks() {
		return true;
	}

	public function doConfigPageInit($page) {
		//TODO: Pendiente revisar y eliminar moule_tab.
		$module_tab = isset($_REQUEST['module_tab'])? trim($_REQUEST['module_tab']) : '';
		if ($module_tab == "") {
			$module_tab = isset($_REQUEST['subpage'])? trim($_REQUEST['subpage']) : '';
		}
		$command = isset($_REQUEST['command'])? trim($_REQUEST['command']) : '';


		$arrVal['mod_sec'] = array("epm_devices","epm_oss", "epm_placeholders", "epm_templates", "epm_config", "epm_advanced");
		if (! in_array($page, $arrVal['mod_sec'])) {
			die(_("Invalid section module!"));
		}

		switch ($page)
		{
			case "epm_devices":
				$this->epm_devices->doConfigPageInit($module_tab, $command);
				break;
			case "epm_oss":
				$this->epm_oss->doConfigPageInit($module_tab, $command);
				break;
			case "epm_placeholders":
				$this->epm_placeholders->doConfigPageInit($module_tab, $command);
				break;
			case "epm_templates":
				$this->epm_templates->doConfigPageInit($module_tab, $command);
				break;

			case "epm_config":
				$this->epm_config->doConfigPageInit($module_tab, $command);
				break;

			case "epm_advanced":
				$this->epm_advanced->doConfigPageInit($module_tab, $command);
				break;
		}
	}

	public function doGeneralPost() {
		if (!isset($_REQUEST['Submit'])) 	{ return; }
		if (!isset($_REQUEST['display'])) 	{ return; }

		needreload();
	}

	public function myShowPage() {
		if (! isset($_REQUEST['display']))
			return $this->pagedata;

		switch ($_REQUEST['display'])
		{
			case "epm_devices":
				$this->epm_devices->myShowPage($this->pagedata);
				break;
			case "epm_oss":
				return $this->epm_oss->myShowPage($this->pagedata);
				break;
			case "epm_placeholders":
				return $this->epm_placeholders->myShowPage($this->pagedata);
				break;
			case "epm_templates":
				$this->epm_templates->myShowPage($this->pagedata);
				return $this->pagedata;
				break;

			case "epm_config":
				$this->epm_config->myShowPage($this->pagedata);
				break;

			case "epm_advanced":
				$this->epm_advanced->myShowPage($this->pagedata);
				break;
		}

		if(! empty($this->pagedata)) {
			foreach($this->pagedata as &$page) {
				ob_start();
				include($page['page']);
				$page['content'] = ob_get_contents();
				ob_end_clean();
			}
			return $this->pagedata;
		}
	}

	public function getActiveModules() {

	}

	//http://wiki.freepbx.org/display/FOP/Adding+Floating+Right+Nav+to+Your+Module
	public function getRightNav($request) {
		if (! isset($_REQUEST['display']))
			return '';
		else {
			//return load_view(dirname(__FILE__).'/views/rnav.php',array());
			return load_view(dirname(__FILE__) . '/views/rnav.php', $var);
		}
		switch($_REQUEST['display'])
		{
			case "epm_devices":
				return load_view(dirname(__FILE__) . '/views/rnav.php', $var);
				break;
			case "epm_oss":
				return load_view(dirname(__FILE__) . '/views/rnav.php', $var);
				break;
			case "epm_placeholders":
				return load_view(dirname(__FILE__) . '/views/rnav.php', $var);
				break;

			case "epm_config":
				return load_view(dirname(__FILE__) . '/views/rnav.php', $var);
				break;

			case "epm_advanced":
				return load_view(dirname(__FILE__) . '/views/rnav.php', $var);
				break;

			case "epm_templates":
				return load_view(dirname(__FILE__) . '/views/rnav.php', $var);
				break;

			default:
		        return '';

		}
	}

	//http://wiki.freepbx.org/pages/viewpage.action?pageId=29753755
	public function getActionBar($request) {
			if (! isset($_REQUEST['display']))
			return '';

		switch($_REQUEST['display'])
		{
			case "epm_devices":
				return $this->epm_devices->getActionBar($request);
				break;

			case "epm_oss":
				return $this->epm_oss->getActionBar($request);
				break;
			case "epm_placeholders":
				return $this->epm_placeholders->getActionBar($request);
				break;
			case "epm_config":
				return $this->epm_config->getActionBar($request);
				break;

			case "epm_advanced":
				return $this->epm_advanced->getActionBar($request);
				break;

			case "epm_templates":
				return $this->epm_templates->getActionBar($request);
				break;

			default:
		        return '';

		}
	}

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

	public function backup() {
	}

    public function restore($backup) {
	}


	private function epm_config_manual_install($install_type = "", $package ="")
	{
		if ($install_type == "") {
			throw new \Exception("Not send install_type!");
		}

		switch($install_type) {
			case "export_brand":

				break;

			case "upload_master_xml":
				if (file_exists($this->PHONE_MODULES_PATH."temp/master.xml")) {
					$handle = fopen($this->PHONE_MODULES_PATH."temp/master.xml", "rb");
					$contents = stream_get_contents($handle);
					fclose($handle);
					@$a = simplexml_load_string($contents);
					if($a===FALSE) {
						echo "Not a valid xml file";
						break;
					} else {
						rename($this->PHONE_MODULES_PATH."temp/master.xml", $this->PHONE_MODULES_PATH."master.xml");
						echo "Move Successful<br />";
						$this->update_check();
						echo "Updating Brands<br />";
					}
				} else {
				}
				break;

			case "upload_provisioner":

				break;

			case "upload_brand":

				break;
		}
	}


     /**
     * Returns list of Brands that are installed and not hidden and that have at least one model enabled under them
     * @param integer $selected ID Number of the brand that is supposed to be selected in a drop-down list box
     * @return array Number array used to generate a select box
     */
    function brands_available($selected = NULL, $show_blank=TRUE) {
        $data = $this->eda->all_active_brands();
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
        require_once('lib/datetimezone.class.php');
        $data = \DateTimeZone::listIdentifiers();
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

    function has_git() {
        exec('which git', $output);

        $git = file_exists($line = trim(current($output))) ? $line : 'git';

        unset($output);

        exec($git . ' --version', $output);

        preg_match('#^(git version)#', current($output), $matches);

        return!empty($matches[0]) ? $git : false;
        echo!empty($matches[0]) ? 'installed' : 'nope';
    }

    function tftp_check() {
        //create a simple block here incase people have strange issues going on as we will kill http
        //by running this if the server isn't really running!
        $sql = 'SELECT value FROM endpointman_global_vars WHERE var_name = \'tftp_check\'';
        if (sql($sql, 'getOne') != 1) {
            $sql = 'UPDATE endpointman_global_vars SET value = \'1\' WHERE var_name = \'tftp_check\'';
            sql($sql);
            $subject = shell_exec("netstat -luan --numeric-ports");
            if (preg_match('/:69\s/i', $subject)) {
                $rand = md5(rand(10, 2000));
                if (file_put_contents($this->configmod->get('config_location') . 'TEST', $rand)) {
                    if ($this->system->tftp_fetch('127.0.0.1', 'TEST') != $rand) {
                        $this->error['tftp_check'] = 'Local TFTP Server is not correctly configured';
                    }
                    unlink($this->configmod->get('config_location') . 'TEST');
                } else {
                    $this->error['tftp_check'] = 'Unable to write to ' . $this->configmod->get('config_location');
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
                    $this->error['tftp_check'] = 'TFTP Server is not running. <br />See here for instructions on how to install one: <a href="http://wiki.provisioner.net/index.php/Tftp" target="_blank">http://wiki.provisioner.net/index.php/Tftp</a>';
                }
            }
            $sql = 'UPDATE endpointman_global_vars SET value = \'0\' WHERE var_name = \'tftp_check\'';
            sql($sql);
        } else {
            $this->error['tftp_check'] = 'TFTP Server check failed on last past. Skipping';
        }
    }


    /**
     * Used to send sample configurations to provisioner.net
     * NOTE: The user has to explicitly click a link that states they are sending the configuration to the project
     * We don't take configs on our own accord!!
     * @param <type> $brand Brand Directory
     * @param <type> $product Product Directory
     * @param <type> $orig_name The file's original name we are sending
     * @param <type> $data The config file's data
    */
    function submit_config($brand, $product, $orig_name, $data) {
    	$posturl = 'http://www.provisioner.net/submit_config.php';

    	$fp = fopen($this->LOCAL_PATH . 'data.txt', 'w');
    	fwrite($fp, $data);
    	fclose($fp);
    	$file_name_with_full_path = $this->LOCAL_PATH . "data.txt";

    	$postvars = array('brand' => $brand, 'product' => $product, 'origname' => htmlentities(addslashes($orig_name)), 'file_contents' => '@' . $file_name_with_full_path);

    	$ch = curl_init($posturl);
    	curl_setopt($ch, CURLOPT_POST, 1);
    	curl_setopt($ch, CURLOPT_POSTFIELDS, $postvars);
    	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    	curl_setopt($ch, CURLOPT_HEADER, 0);  // DO NOT RETURN HTTP HEADERS
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  // RETURN THE CONTENTS OF THE CALL, probably not needed
    	$Rec_Data = curl_exec($ch);

    	ob_start();
    	header("Content-Type: text/html");
    	$Final_Out = ob_get_clean();
    	curl_close($ch);
    	unlink($file_name_with_full_path);

    	return($Final_Out);
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
            $sql = "SELECT endpointman_model_list.max_lines, endpointman_product_list.config_files, endpointman_mac_list.*, endpointman_product_list.id as product_id, endpointman_product_list.long_name, endpointman_model_list.template_data, endpointman_product_list.cfg_dir, endpointman_brand_list.directory FROM endpointman_brand_list, endpointman_mac_list, endpointman_model_list, endpointman_product_list WHERE endpointman_mac_list.id=" . $id . " AND endpointman_mac_list.model = endpointman_model_list.id AND endpointman_model_list.brand = endpointman_brand_list.id AND endpointman_model_list.product_id = endpointman_product_list.id";
        } else {
            $sql = "SELECT endpointman_model_list.max_lines, endpointman_brand_list.directory, endpointman_product_list.cfg_dir, endpointman_product_list.config_files, endpointman_product_list.long_name, endpointman_model_list.template_data, endpointman_model_list.id as model_id, endpointman_template_list.* FROM endpointman_brand_list, endpointman_product_list, endpointman_model_list, endpointman_template_list WHERE endpointman_product_list.id = endpointman_template_list.product_id AND endpointman_brand_list.id = endpointman_product_list.brand AND endpointman_template_list.model_id = endpointman_model_list.id AND endpointman_template_list.id = " . $id;
        }

        //Load template data
        $row = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

        $cfg_data = unserialize($row['template_data']);
        $count = count($cfg_data);

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

        $config_files = explode(",", $row['config_files']);

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
            $config_files_selected = serialize($config_files_selected);
        }
        $custom_cfg_data_temp['data'] = $custom_cfg_data;
        $custom_cfg_data_temp['ari'] = $custom_cfg_data_ari;

        $save = serialize($custom_cfg_data_temp);
        if ($custom == "0") {
            $sql = 'UPDATE endpointman_template_list SET config_files_override = \'' . addslashes($config_files_selected) . '\', global_custom_cfg_data = \'' . addslashes($save) . '\' WHERE id =' . $id;
            $location = "template_manager";
			//print_r($sql);
        } else {
            $sql = 'UPDATE endpointman_mac_list SET config_files_override = \'' . addslashes($config_files_selected) . '\', template_id = 0, global_custom_cfg_data = \'' . addslashes($save) . '\' WHERE id =' . $id;
            $location = "devices_manager";
        }
        sql($sql);

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
