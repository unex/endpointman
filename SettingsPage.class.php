<?php
/**
 * Endpoint Manager Object Module - Sec Advanced
 *
 * @author Javier Pastor
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */

namespace FreePBX\modules;

require_once(__DIR__ . "/PageBase.class.php");

class SettingsPage extends \PageBase {
	public function showPage() {
		return load_view(__DIR__ . '/views/settings.php', array('epm' => $this));
	}

	public function ajaxRequest($req, &$setting) {
		$arrVal = array("saveconfig", "oui", "oui_add", "oui_del",
						"poce_list_brands", "poce_select", "poce_select_file",
						"poce_save_file", "poce_save_as_file",
						"poce_delete_config_custom", "list_files_brands_export");
		if (in_array($req, $arrVal)) {
			$setting['authenticate'] = true;
			$setting['allowremote'] = false;
			return true;
		}
		return false;
	}

    public function ajaxHandler($command) {
		// The page script reads these from data.txt (data.txt.error and
		// data.txt.save_changes_ok). Each handler that reports a result needs them
		// in the response. PackagesPage::ajaxHandler() uses the same form.
		$txt = array(
			'error' => _("Error!"),
			'save_changes' => _("Saving Changes..."),
			'save_changes_ok' => _("Saving Changes... Ok!"),
			'opt_invalid' => _("Invalid Option!")
		);

		switch ($command) {
			case "saveconfig":
				$retarr = $this->_settings_saveconfig();
				break;

			case "oui":
				//$sql = 'SELECT endpointman_oui_list.id, endpointman_oui_list.oui , endpointman_brand_list.name, endpointman_oui_list.custom FROM endpointman_oui_list , endpointman_brand_list WHERE endpointman_oui_list.brand = endpointman_brand_list.id ORDER BY endpointman_oui_list.oui ASC';
				$sql = 'SELECT T1.id, T1.oui, T2.name, T1.custom FROM endpointman_oui_list as T1 , endpointman_brand_list as T2 WHERE T1.brand = T2.id ORDER BY T1.oui ASC';
				$sql_p = array();
				$data = epm_sql($sql, 'getAll', $sql_p);
				$ret = array();
				foreach ($data as $item) {
					$ret[] = array('id' => $item['id'], 'oui' => $item['oui'], 'brand' => $item['name'], 'custom' => $item['custom']);
				}
				return $ret;
				break;

			case "oui_add":
				$retarr = $this->_oui_add();
				break;

			case "oui_del":
				$retarr = $this->_oui_remove();
				break;

			case "poce_list_brands":
				$retarr = $this->_poce_list_brands();
				break;

			case "poce_select":
				$retarr = $this->_poce_select();
				break;

			case "poce_select_file":
				$retarr = $this->_poce_select_file();
				break;

			case "poce_save_file":
			case "poce_save_as_file":
				$retarr = $this->_poce_save_file();
				break;

			case "poce_delete_config_custom":
				$retarr = $this->_poce_delete_config_custom();
				break;

			case "list_files_brands_export":
				$retarr = $this->_manual_upload_list_files_brans_export();
				break;

			default:
				$retarr = array("status" => false, "message" => _("Command not found!") . " [" .$command. "]");
				break;
		}

		$retarr['txt'] = $txt;
		return $retarr;
	}

	public function doConfigPageInit($command) {
		switch ($command) {
			case "export":
				$this->_iedl_export();
				break;

			case "import":
				$this->_iedl_import();
				exit;
				break;
			case "export_brands_availables":
				$this->_manual_upload_export_brans_available();
				exit;
				break;

			case "export_brands_availables_file":
				$this->_manual_upload_export_brans_available_file();
				exit;
				break;

			case "upload_brand":
				$this->_manual_upload_brand();
				exit;
				break;

		}
	}

	public function getActionBar($request) {
		return "";
	}


	/**** FUNCIONES SEC MODULO "epm_advanced\settings" ****/
	public function _config_loc_is_writable()
	{
		$config_loc = $this->configmod->get("config_loc");
		$tftp_writable = FALSE;
		if ((isset($config_loc)) AND ($config_loc != "")) {
			if ((file_exists($config_loc)) AND (is_dir($config_loc))) {
				if (is_writable($config_loc)) {
					$tftp_writable = TRUE;
				}
			}
		}
		return $tftp_writable;
	}

	private function _settings_saveconfig ()
	{
		$arrVal['VAR_REQUEST'] = array("name", "value");
		foreach ($arrVal['VAR_REQUEST'] as $valor) {
			if (! array_key_exists($valor, $_REQUEST)) {
				return array("status" => false, "message" => sprintf(_("The request has no %s value."), $valor));
			}
		}

		$dget['name'] = strtolower($_REQUEST['name']);
		$dget['value'] = $_REQUEST['value'];

		switch($dget['name']) {
			case "disable_endpoint_warning":
				$dget['value'] = strtolower($dget['value']);
				$save_key = 'disable_endpoint_warning';
				$save_val = ($dget['value'] == "yes" ? "1": "0");
				break;

			case "allow_dupext":
				$dget['value'] = strtolower($dget['value']);
				$save_key = 'show_all_registrations';
				$save_val = ($dget['value'] == "yes" ? "1": "0");
				break;

			case "allow_hdfiles":
				$dget['value'] = strtolower($dget['value']);
				$save_key = 'allow_hdfiles';
				$save_val = ($dget['value'] == "yes" ? "1": "0");
				break;

			case "tftp_check":
				$dget['value'] = strtolower($dget['value']);
				$save_key = 'tftp_check';
				$save_val = ($dget['value'] == "yes" ? "1": "0");
				break;

			case "backup_check":
				$dget['value'] = strtolower($dget['value']);
				$save_key = 'backup_check';
				$save_val = ($dget['value'] == "yes" ? "1": "0");
				break;

			case "config_loc":
				// Check for an empty value before you read the string. The value comes as
				// name=config_loc&value=..., so the message uses the value, not
				// $_POST['config_loc'].
				$dget['value'] = trim($dget['value']);
				if ($dget['value'] == "") {
					$retarr = array("status" => false, "message" => _("Type the config directory."));
					break;
				}
				//No trailing slash. Help the user out and add one :-)
				if (substr($dget['value'], -1) != "/") {
					$dget['value'] = $dget['value'] . "/";
				}
				if ((file_exists($dget['value'])) AND (is_dir($dget['value']))) {
					if (is_writable($dget['value'])) {
						$save_key = 'config_location';
						$save_val = $dget['value'];
					} else {
						$retarr = array("status" => false, "message" => _("The web server cannot write to this directory."));
					}
				} else {
					$retarr = array("status" => false, "message" => sprintf(_("The directory does not exist. Make it as root: mkdir %s"), epm_esc($dget['value'])));
				}
				break;

			case "srvip":
				$dget['value'] = trim($dget['value']);
				$save_key = 'srvip';
				$save_val = $dget['value'];
				break;
			case "intsrvip":
				$dget['value'] = trim($dget['value']);
				$save_key = 'intsrvip';
				$save_val = $dget['value'];
				break;
			case "tz":
				$save_key = 'tz';
				$save_val = $dget['value'];
				break;

			case "adminpass":
				$dget['value'] = trim($dget['value']);
				$save_key = 'adminpass';
				$save_val = $dget['value'];
				break;
			case "userpass":
				$dget['value'] = trim($dget['value']);
				$save_key = 'userpass';
				$save_val = $dget['value'];
				break;

			case "ntp_server":
				$dget['value'] = trim($dget['value']);
				$save_key = 'ntp';
				$save_val = $dget['value'];
				break;

			case "nmap_loc":
				$save_key = 'nmap_location';
				$save_val = $dget['value'];
				break;

			case "arp_loc":
				$save_key = 'arp_location';
				$save_val = $dget['value'];
				break;

			case "anacrypt_loc":
				// Empty is valid and turns the program off. A path must pass the same check
				// that the Aastra driver makes before each run.
				$dget['value'] = trim($dget['value']);
				if ($dget['value'] !== '') {
					$anacrypt_problem = \FreePBX\modules\epm_system::anacrypt_problem($dget['value'], $this->PHONE_MODULES_PATH);
					if ($anacrypt_problem !== '') {
						$retarr = array("status" => false, "message" => $anacrypt_problem);
						break;
					}
				}
				$save_key = 'anacrypt_location';
				$save_val = $dget['value'];
				break;

			case "anacrypt_key":
				$dget['value'] = trim($dget['value']);
				if (($dget['value'] === '') || preg_match('/[\x00-\x20\x7f]/', $dget['value'])) {
					$retarr = array("status" => false, "message" => _("The anacrypt key must not be empty. It must not contain spaces or control characters."));
					break;
				}
				$save_key = 'anacrypt_key';
				$save_val = $dget['value'];
				break;

			case "package_server":
				// Validate the value. Do not store it as typed. A value without a trailing
				// slash makes URLs like ".../provisionermaster.json", and an http:// value
				// downloads nothing. See Endpointman::normalizeUpdateServer().
				$normalize_error = null;
				$normalized = \FreePBX\modules\Endpointman::normalizeUpdateServer($dget['value'], $normalize_error);
				if ($normalized === false) {
					$retarr = array("status" => false, "message" => $normalize_error);
					break;
				}
				$dget['value'] = $normalized;
				$save_key = 'update_server';
				$save_val = $dget['value'];
				break;

			case "provision_acl":
				// Store the canonical form, not the typed value, so the field always shows
				// networks. 192.168.1.5/24 becomes 192.168.1.0/24, and a bare address gets
				// /32. An empty list is valid and denies all requests (see lib/acl.inc.php).
				$acl_error = null;
				$normalized = epm_acl_normalize($dget['value'], $acl_error);
				if ($normalized === false) {
					$retarr = array("status" => false, "message" => $acl_error);
					break;
				}
				$dget['value'] = $normalized;
				$save_key = 'provision_acl';
				$save_val = $normalized;
				break;

			case "trusted_proxies":
				// The syntax and the canonical form are the same as for the allowlist. Empty
				// is the default and means that p.php believes no proxy.
				$acl_error = null;
				$normalized = epm_acl_normalize($dget['value'], $acl_error);
				if ($normalized === false) {
					$retarr = array("status" => false, "message" => $acl_error);
					break;
				}
				$dget['value'] = $normalized;
				$save_key = 'trusted_proxies';
				$save_val = $normalized;
				break;

			case "cfg_type":
				if ($dget['value'] == 'http' or $dget['value'] == 'https' ) {
					$symlink = $this->config->get('AMPWEBROOT') . "/provisioning";
					$reallink = $this->LOCAL_PATH . "provisioning";
					if ((!is_link($symlink)) OR (!readlink($symlink) == $reallink)) {
						if (!symlink($reallink, $symlink)) {
							$retarr = array("status" => false, "message" => sprintf(_("Cannot make the provisioning link in %s. Check the permissions."), epm_esc($this->config->get('AMPWEBROOT'))));
							//$dget['value'] = 'file';
							break;
						} //else {
							//$dget['value'] = 'http';
						//}
					}// else {
						//$dget['value'] = 'http';
					//}
				} else {
					$dget['value'] = 'file';
				}
				$save_key = 'server_type';
				$save_val = $dget['value'];
				break;

			default:
				$retarr = array("status" => false, "message" => sprintf(_("Unknown setting %s."), epm_esc($dget['name'])) );
		}

		if (isset($save_key)) {
			$this->configmod->save($save_key, $save_val);
			$retarr = array("status" => true, "message" => "OK", "name" => $dget['name'], "value" => $dget['value']);
			unset($save_key, $save_val);
		}

		unset($dget);
		return $retarr;
	}


	/**** FUNCIONES SEC MODULO "epm_advanced\poce" ****/
	public function _poce_list_brands()
	{
		//$sql = 'SELECT * FROM endpointman_product_list WHERE hidden = 0 AND id > 0 ORDER BY long_name ASC';
		//$sql = 'SELECT * FROM endpointman_product_list WHERE hidden = 0 AND id > 0 AND brand IN (SELECT id FROM asterisk.endpointman_brand_list where hidden = 0) ORDER BY long_name ASC';
		$sql = 'SELECT * FROM endpointman_product_list WHERE hidden = 0 AND id IN (SELECT DISTINCT product_id FROM asterisk.endpointman_model_list where enabled = 1) AND brand IN (SELECT id FROM asterisk.endpointman_brand_list where hidden = 0) ORDER BY long_name ASC';
		$sql_p = array();
		$product_list = epm_sql($sql, 'getAll', $sql_p);
		$i = 0;
		$temp = array();
		foreach ($product_list as $srow)
		{
			$temp[$i]['id'] = $srow['id'];
			$temp[$i]['name'] = $srow['long_name'];
			$temp[$i]['name_mini'] = substr($srow['long_name'], 0, 40).(strlen($srow['long_name']) > 40 ? "..." : "");
			$i++;
		}
		return array("status" => true, "message" => "OK", "ldatos" => $temp);
	}

	public function _poce_select()
	{
			if (! isset($_REQUEST['product_select'])) {
			$retarr = array("status" => false, "message" => _("Select a family."));
		}
		elseif (! is_numeric($_REQUEST['product_select'])) {
			$retarr = array("status" => false, "message" => _("The family ID is not a number."));
		}
		elseif ($_REQUEST['product_select'] < 0) {
			$retarr = array("status" => false, "message" => _("The family ID is not valid."));
		}
		else
		{
			$dget['product_select'] = $_REQUEST['product_select'];

			$sql = 'SELECT * FROM `endpointman_product_list` WHERE `hidden` = 0 AND `id` = ?';
			$sql_p = array($dget['product_select']);
			$product_select_info = epm_sql($sql, 'getRow', $sql_p);

			$sql = 'SELECT cfg_dir,directory,config_files FROM endpointman_product_list,endpointman_brand_list WHERE endpointman_product_list.brand = endpointman_brand_list.id AND endpointman_product_list.id =?';
			$sql_p = array($dget['product_select']);
			$row =  epm_sql($sql, 'getRow', $sql_p);
			$config_files = explode(",", $row['config_files']);
			$i = 0;
			if (count($config_files)) {
				foreach ($config_files as $config_files_data) {
					//$file_list[$i]['value'] = $i;
					$file_list[$i]['value'] = $dget['product_select'];
					$file_list[$i]['text'] = $config_files_data;
					$i++;
				}
			} else { $file_list = NULL; }

			$sql = 'SELECT * FROM endpointman_custom_configs WHERE product_id = ?';
			$sql_p = array($dget['product_select']);
			$data = epm_sql($sql, 'getAll', $sql_p);
			$i = 0;
			if (count($data)) {
				//$data = epm_sql($sql, 'getAll', $sql_p);
				foreach ($data as $row2) {
					$sql_file_list[$i]['value'] = $row2['id'];
					$sql_file_list[$i]['text'] = $row2['name'];
					$sql_file_list[$i]['ref'] = $row2['original_name'];
					$i++;
				}
			} else { $sql_file_list = NULL; }


			require_once($this->LOCAL_PATH . 'lib/provisioner/autoload.php');
			$class = "endpoint_" . $row['directory'] . "_" . $row['cfg_dir'] . '_phone';
			$base_class = "endpoint_" . $row['directory'] . '_base';
			$master_class = "endpoint_base";

			/*********************************************************************************
			 *** Quick Fix for FreePBX Distro
			 *** I seriously want to figure out why ONLY the FreePBX Distro can't do autoloads.
			 **********************************************************************************/
			if (!class_exists($master_class)) {
				ProvisionerConfig::endpointsAutoload($master_class);
			}
			if (!class_exists($base_class)) {
				ProvisionerConfig::endpointsAutoload($base_class);
			}
			if (!class_exists($class)) {
				ProvisionerConfig::endpointsAutoload($class);
			}
			//end quick fix
			$phone_config = new $class();

			//TODO: remove
			$template_file_list[0]['value'] = "template_data_custom.xml";
			$template_file_list[0]['text'] = "template_data_custom.xml";

			$sql = 'SELECT id, model FROM endpointman_model_list WHERE product_id = ? AND enabled = 1 AND hidden = 0';
			$sql_p = array($dget['product_select']);
			$data = epm_sql($sql, 'getAll', $sql_p);
			$i = 1;
			foreach ($data as $list) {
				//$template_file_list[$i]['value'] = "template_data_" . $list['model'] . "_custom.xml";
				$template_file_list[$i]['value'] = $list['id'];
				$template_file_list[$i]['text'] = "template_data_" . $list['model'] . "_custom.xml";
			}

			$retarr = array("status" => true,
							"message" => "OK",
							"product_select" => $dget['product_select'],
							"product_select_info" => $product_select_info,
							"file_list" => $file_list,
							"template_file_list" => $template_file_list,
							"sql_file_list" => $sql_file_list);
			unset($dget);
		}
		return $retarr;
	}

	public function _poce_select_file()
	{
		$arrVal['VAR_REQUEST'] = array("product_select", "file_id", "file_name", "type_file");
		foreach ($arrVal['VAR_REQUEST'] as $valor) {
			if (! array_key_exists($valor, $_REQUEST)) {
				return array("status" => false, "message" => sprintf(_("The request has no %s value."), $valor));
			}
		}
		if (! is_numeric($_REQUEST['product_select'])) {
			return array("status" => false, "message" => _("The family ID is not a number."));
		}
		elseif ($_REQUEST['product_select'] < 0) {
			return array("status" => false, "message" => _("The family ID is not valid."));
		}

		$dget['product_select'] = $_REQUEST['product_select'];
		$dget['file_name'] = $_REQUEST['file_name'];
		$dget['file_id'] = $_REQUEST['file_id'];
		$dget['type_file'] = $_REQUEST['type_file'];

		if ($dget['type_file'] == "sql") {
			$sql = 'SELECT * FROM endpointman_custom_configs WHERE id =?';
			$sql_p = array($dget['file_id']);
			$row = epm_sql($sql, 'getrow', $sql_p);

			$type = $dget['type_file'];
			$sendidt = $row['id'];
			$product_select = $row['product_id'];
			$save_as_name_value = $row['name'];
			$original_name = $row['original_name'];
			$filename =  $row['name'];
			$location = "SQL: ". $row['name'];
			$config_data = $this->display_htmlspecialchars($row['data']);

		}
		elseif ($dget['type_file'] == "file") {
			$sql = 'SELECT cfg_dir,directory,config_files FROM endpointman_product_list,endpointman_brand_list WHERE endpointman_product_list.brand = endpointman_brand_list.id AND endpointman_product_list.id = ?';
			$sql_p = array($dget['product_select']);
			$row = epm_sql($sql, 'getRow', $sql_p);

			$config_files = explode(",", $row['config_files']);
			//TODO: Añadir validacion para ver si $dget['file_name'] esta en el array $config_files

			$filename = $dget['file_name'];
			$pathfile = $this->PHONE_MODULES_PATH . 'endpoint/' . $row['directory'] . "/" . $row['cfg_dir'] . "/" . $filename;


			if (is_readable($pathfile)) {
				if(filesize($pathfile)>0) {
					$handle = fopen($pathfile, "rb");
					$contents = fread($handle, filesize($pathfile));
					fclose($handle);
					$contents = $this->display_htmlspecialchars($contents);
				}
				else {
					$contents = "";
				}

				$type = $dget['type_file'];
				$sendidt = $dget['file_id'];
				$product_select = $dget['product_select'];
				$save_as_name_value = $filename;
				$original_name = $filename;
				$filename =  $filename;
				$location = $pathfile;
				$config_data = $contents;
			}
			else {
				$retarr = array("status" => false, "message" => sprintf(_("Cannot read the file %s. Check the permissions."), $filename));
			}
		}
		elseif ($dget['type_file'] == "tfile")
		{
			if ($dget['file_id'] == "template_data_custom.xml")
			{
				$sendidt = "";
				$original_name = $dget['file_name'];
				$config_data = "";
			}
			else {

				$sql = 'SELECT * FROM endpointman_model_list WHERE id = ?';
				$sql_p = array($dget['file_id']);
				$data = epm_sql($sql, 'getRow', $sql_p);

				$sendidt = $data['id'];
				$original_name = $dget['file_name'];
				$config_data = epm_blob_decode($data['template_data']);
				$config_data = generate_xml_from_array ($config_data, 'node');
			}

			$type = $dget['type_file'];
			$product_select = $dget['product_select'];
			$save_as_name_value = $dget['file_name'];
			$filename = $dget['file_name'];
			$location = $dget['file_name'];
		}

		$retarr = array("status" => true,
						"message" => "OK",
						"type" => $type,
						"sendidt" => $sendidt,
						"product_select" => $product_select,
						"save_as_name_value" => $save_as_name_value,
						"original_name" => $original_name,
						"filename" => $filename,
						"location" => $location,
						"config_data" => $config_data);

		unset($dget);
		return $retarr;
	}







	//TODO: PENDIENTE REVISAR
	function _poce_save_file()
	{
		$arrVal['VAR_REQUEST'] = array("product_select", "sendid", "type_file", "config_text", "save_as_name", "file_name", "original_name");
		foreach ($arrVal['VAR_REQUEST'] as $valor) {
			if (! array_key_exists($valor, $_REQUEST)) {
				return array("status" => false, "message" => sprintf(_("The request has no %s value."), $valor));
			}
		}

		$dget['command'] = $_REQUEST['command'];
		$dget['type_file'] = $_REQUEST['type_file'];
		$dget['sendid'] = $_REQUEST['sendid'];
		$dget['product_select'] = $_REQUEST['product_select'];
		$dget['save_as_name'] = $_REQUEST['save_as_name'];
		$dget['original_name'] = $_REQUEST['original_name'];
		$dget['file_name'] = $_REQUEST['file_name'];
		$dget['config_text'] = $_REQUEST['config_text'];

		if ($dget['type_file'] == "file") {
			if ($dget['command'] == "poce_save_file")
			{
				$sql = 'SELECT cfg_dir,directory,config_files FROM endpointman_product_list,endpointman_brand_list WHERE endpointman_product_list.brand = endpointman_brand_list.id AND endpointman_product_list.id = ?';
				$sql_p = array($dget['product_select']);
				$row = epm_sql($sql, 'getRow', $sql_p);
				$config_files = explode(",", $row['config_files']);

				if ((is_array($config_files)) AND (in_array($dget['file_name'], $config_files)))
				{
					$pathdir = $this->PHONE_MODULES_PATH . 'endpoint/' . $row['directory'] . "/" . $row['cfg_dir'] . "/";
					$pathfile = $pathdir . $dget['file_name'];
					if ((! file_exists($pathfile)) AND (! is_writable($pathdir))) {
						$retarr = array("status" => false, "message" => sprintf(_("The web server cannot write to the directory %s."), $pathdir));
					}
					elseif (! is_writable($pathfile)) {
						$retarr = array("status" => false, "message" => sprintf(_("The web server cannot write to the file %s."), $pathfile));
					}
					else
					{
						$wfh = fopen($pathfile, 'w');
						fwrite($wfh, $dget['config_text']);
						fclose($wfh);
						$retarr = array("status" => true, "message" => _("The file is saved. A brand update can overwrite it."));
					}
				}
				else {
					$retarr = array("status" => false, "message" => _("The file is not in the database."));
				}
			}
			elseif ($dget['command'] == "poce_save_as_file")
			{
				$db = $this->db;
				$sql = 'INSERT INTO endpointman_custom_configs (name, original_name, product_id, data) VALUES (?,?,?,?)';
				$sql_p = array();
				$q = $db->prepare($sql);
				$ob = $q->execute(array(addslashes($dget['save_as_name']), addslashes($dget['original_name']), $dget['product_select'], $dget['config_text']));
				$newidinsert = $db->lastInsertId();
				$retarr = array("status" => true, "message" => _("The file is saved as your version."));

				$retarr['type_file'] = "sql";
				$retarr['location'] = "SQL: ". $dget['save_as_name'];
				$retarr['sendidt'] = $newidinsert;
			}
			else {
				$retarr = array("status" => false, "message" => _("Unknown command."));
			}
		}
		elseif ($dget['type_file'] == "sql")
		{
			if ($dget['command'] == "poce_save_file")
			{
				$sql = 'UPDATE endpointman_custom_configs SET data = ? WHERE id = ?';
				$sql_p = array($dget['config_text'], $dget['sendid']);
				epm_sql($sql, 'query', $sql_p);
				$retarr = array("status" => true, "message" => _("The file is saved as your version."));
			}
			elseif ($dget['command'] == "poce_save_as_file")
			{
				$db = $this->db;
				$sql = 'INSERT INTO endpointman_custom_configs (name, original_name, product_id, data) VALUES (?,?,?,?)';
				$sql_p = array();
				$q = $db->prepare($sql);
				$ob = $q->execute(array(addslashes($dget['save_as_name']), addslashes($dget['original_name']), $dget['product_select'], $dget['config_text']));
				$newidinsert = $db->lastInsertId();
				$retarr = array("status" => true, "message" => _("The file is saved as your version."));

				$retarr['type_file'] = "sql";
				$retarr['location'] = "SQL: ". $dget['save_as_name'];
				$retarr['sendidt'] = $newidinsert;
			}
			else {
				$retarr = array("status" => false, "message" => _("Unknown command."));
			}
		}
		elseif ($dget['type_file'] == "tfile")
		{
			/*

			$db = $this->db;
			$sql = 'INSERT INTO endpointman_custom_configs (name, original_name, product_id, data) VALUES (?,?,?,?)';
			$q = $db->prepare($sql);
@ -790,7 +790,7 @@ class Endpointman_Advanced
			$retarr['type_file'] = "sql";
			$retarr['location'] = "SQL: ". $dget['save_as_name'];
			$retarr['sendidt'] = $newidinsert;
			*/
		}
		else {
			$retarr = array("status" => false, "message" => _("Unknown file type."));
		}

		$retarr['original_name'] = $dget['original_name'];
		$retarr['file_name'] = $dget['file_name'];
		$retarr['save_as_name'] = $dget['save_as_name'];

		unset($dget);
		return $retarr;
	}

	function _poce_delete_config_custom()
	{
		$arrVal['VAR_REQUEST'] = array("product_select", "type_file", "sql_select");
		foreach ($arrVal['VAR_REQUEST'] as $valor) {
			if (! array_key_exists($valor, $_REQUEST)) {
				return array("status" => false, "message" => sprintf(_("The request has no %s value."), $valor));
			}
		}

		$dget['type_file'] = $_REQUEST['type_file'];
		$dget['product_select'] = $_REQUEST['product_select'];
		$dget['sql_select'] = $_REQUEST['sql_select'];

		if ($dget['type_file'] == "sql") {
			$sql = 'DELETE FROM endpointman_custom_configs WHERE id =?';
			$sql_p = array($dget['sql_select']);
			epm_sql($sql, 'query', $sql_p);
			unset ($sql);
			$retarr = array("status" => true, "message" => _("Your version is deleted."));
		}
		else { $retarr = array("status" => false, "message" => _("Unknown file type.")); }

		unset($dget);
		return $retarr;
	}


	/**** FUNCIONES SEC MODULO "epm_advanced\manual_upload" ****/
	/**
	 * List the packages that "Export a brand" made.
	 *
	 * The files are <directory>-<unix time>.tgz in temp/export/.
	 */
	public function _manual_upload_list_files_brans_export()
	{
		$path_tmp_dir = $this->PHONE_MODULES_PATH . "temp/export/";
		$files = array();
		if (is_dir($path_tmp_dir)) {
			foreach (scandir($path_tmp_dir, SCANDIR_SORT_DESCENDING) as $archivo) {
				if (!preg_match('/^(.+)-(\d{10})\.tgz$/', $archivo, $m) || !is_file($path_tmp_dir . $archivo)) {
					continue;
				}
				$files[] = array(
					"brand"     => $m[1],
					"file"      => $archivo,
					"timer"     => $m[2],
					"timestamp" => date('Y-m-d H:i:s', (int) $m[2]),
				);
			}
		}
		return array("status" => true, "message" => "OK", "countlist" => count($files), "list_files" => $files);
	}

	/**
	 * Install the brand packages of an upload.
	 *
	 * The output shows in a dialog on the Brands page as HTML, so each value is
	 * escaped.
	 */
	public function _manual_upload_brand()
	{
		if (empty($_FILES["files"]["error"]) || !is_array($_FILES["files"]["error"])) {
			out(_("Error: No file was uploaded."));
			return;
		}
		foreach ($_FILES["files"]["error"] as $key => $error) {
			$name = basename($_FILES["files"]["name"][$key]);
			out(sprintf(_("Installing the package %s..."), epm_esc($name)));

			if ($error != UPLOAD_ERR_OK) {
				out(sprintf(_("Error: %s"), epm_esc($this->file_upload_error_message($error))));
				continue;
			}
			if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== "tgz") {
				out(_("Error: The package must be a .tgz file."));
				continue;
			}

			$uploads_dir_file = $this->PHONE_MODULES_PATH . "temp/" . $name;
			if (!move_uploaded_file($_FILES["files"]["tmp_name"][$key], $uploads_dir_file)) {
				out(_("Error: The upload is not valid."));
				continue;
			}

			$temp_directory = sys_get_temp_dir() . "/epm_temp/";
			if (!file_exists($temp_directory) && !mkdir($temp_directory)) {
				out(sprintf(_("Error: Cannot make the directory %s."), epm_esc($temp_directory)));
				@unlink($uploads_dir_file);
				continue;
			}

			outn(_("Extracting the package... "));
			// extract_package() applies the same rules as for a download. It refuses
			// the package when a file in it is not data.
			$extract_error = array();
			if (! $this->system->extract_package($uploads_dir_file, $temp_directory, $extract_error)) {
				out(_("Failed."));
				out(epm_esc($extract_error['extract_package']));
				@unlink($uploads_dir_file);
				continue;
			}
			out(_("Done."));

			// polycom.tgz and polycom-1790882875.tgz both name the brand polycom.
			$package = explode("-", basename($name, ".tgz"));
			$package = $package[0];
			if (file_exists($temp_directory . $package)) {
				// update_brand() removes the temporary files.
				$this->epm->brandmanager->update_brand($package, FALSE);
			} else {
				out(sprintf(_("Error: The package has no %s directory. Give the package the name of the brand directory, for example polycom.tgz."), epm_esc($package)));
			}
		}
	}

	/**
	 * Send an exported package.
	 *
	 * file_package comes from the request. basename() keeps the read in
	 * temp/export/, and the name must have the form of an export.
	 */
	public function _manual_upload_export_brans_available_file()
	{
		$file = isset($_REQUEST['file_package']) ? basename((string) $_REQUEST['file_package']) : '';
		$path_tmp_file = $this->PHONE_MODULES_PATH . "temp/export/" . $file;
		if (!preg_match('/^.+-\d{10}\.tgz$/', $file) || !is_file($path_tmp_file)) {
			header('HTTP/1.0 404 Not Found', true, 404);
			echo _("The file does not exist.");
			exit;
		}
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="' . $file . '"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($path_tmp_file));
		readfile($path_tmp_file);
		exit;
	}

	/**
	 * Make a package of an installed brand in temp/export/.
	 *
	 * The package holds the endpoint/<directory>/ tree, without firmware. The
	 * output shows in a dialog on the Brands page as HTML.
	 */
	public function _manual_upload_export_brans_available()
	{
		$id = isset($_REQUEST['package']) ? $_REQUEST['package'] : '';
		if (!is_numeric($id) || $id < 0) {
			out(_("Error: Select a brand."));
			return;
		}
		$row = epm_sql('SELECT `name`, `directory` FROM `endpointman_brand_list` WHERE `id` = ?', 'getRow', array($id));
		if (empty($row)) {
			out(_("Error: There is no brand with this ID."));
			return;
		}
		$source = $this->PHONE_MODULES_PATH . "endpoint/" . $row['directory'];
		if (!is_dir($source)) {
			out(sprintf(_("Error: %s is not installed."), epm_esc($row['name'])));
			return;
		}

		outn(sprintf(_("Exporting %s... "), epm_esc($row['name'])));
		$export_dir = $this->PHONE_MODULES_PATH . "temp/export/";
		if (!is_dir($export_dir) && !mkdir($export_dir, 0775, true)) {
			out(_("Failed."));
			out(sprintf(_("Error: Cannot make the directory %s."), epm_esc($export_dir)));
			return;
		}
		$tar = $export_dir . $row['directory'] . "-" . time() . ".tar";
		try {
			$phar = new \PharData($tar);
			$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
			foreach ($files as $path => $info) {
				$relative = substr($path, strlen($this->PHONE_MODULES_PATH . "endpoint/"));
				if (!$info->isFile() || preg_match('#(^|/)(firmware|\.svn)(/|$)#', $relative)) {
					continue;
				}
				$phar->addFile($path, $relative);
			}
			$phar->compress(\Phar::GZ, '.tgz');
			unset($phar);
			@unlink($tar);
		} catch (\Exception $e) {
			@unlink($tar);
			out(_("Failed."));
			out(epm_esc($e->getMessage()));
			return;
		}
		out(_("Done."));

		$url = 'config.php?display=endpointman&view=settings&command=export_brands_availables_file&file_package=' . rawurlencode(basename($tar, '.tar') . '.tgz');
		out('<a href="' . epm_esc($url) . '" class="btn btn-primary mt-2">' . _("Download the package") . '</a>');
	}


	/**** FUNCIONES SEC MODULO "epm_advanced\iedl" ****/
	/**
	 * Send the phone list as a CSV file.
	 *
	 * The columns are MAC, brand, model, extension, line and IPEI. One row is one
	 * line of a phone. _iedl_import() reads the same columns.
	 */
	public function _iedl_export($sFileName = "devices_list.csv")
	{
		header("Content-type: text/csv");
		header('Content-Disposition: attachment; filename="'.$sFileName.'"');
		$outstream = fopen("php://output",'w');
		$sql = 'SELECT endpointman_mac_list.mac, endpointman_brand_list.name, endpointman_model_list.model, endpointman_line_list.ext, endpointman_line_list.line, endpointman_line_list.ipei FROM endpointman_mac_list, endpointman_model_list, endpointman_brand_list, endpointman_line_list WHERE endpointman_line_list.mac_id = endpointman_mac_list.id AND endpointman_model_list.id = endpointman_mac_list.model AND endpointman_model_list.brand = endpointman_brand_list.id ORDER BY endpointman_mac_list.mac, endpointman_line_list.line';
		$result = epm_sql($sql, 'getAll', array());
		foreach($result as $row) {
			fputcsv($outstream, array_values($row));
		}
		fclose($outstream);
		exit;
	}

	/**
	 * Add the phones of an uploaded CSV file.
	 *
	 * The columns are MAC, brand, model, extension, line and IPEI. Only the MAC
	 * is necessary. The brand and the model match by name, first exactly and
	 * then as part of a name. The extension must match exactly.
	 *
	 * The Phones page shows the output as HTML, so each value from the file is
	 * escaped.
	 */
	public function _iedl_import()
	{
		$devices = $this->epm->_get_page('devices');
		if (empty($_FILES["files"]["error"]) || !is_array($_FILES["files"]["error"])) {
			echo '<p class="text-danger">' . _("No file was uploaded.") . '</p>';
			return;
		}
		$allowedExtensions = array("csv", "txt");
		foreach ($_FILES["files"]["error"] as $key => $error) {
			$file_name = epm_esc($_FILES["files"]["name"][$key]);
			echo '<p><strong>' . sprintf(_("Import of %s"), $file_name) . '</strong></p><ul>';

			if ($error != UPLOAD_ERR_OK) {
				echo '<li class="text-danger">' . epm_esc($this->file_upload_error_message($error)) . '</li></ul>';
				continue;
			}
			if (!in_array(strtolower(substr(strrchr($_FILES["files"]["name"][$key], "."), 1)), $allowedExtensions)) {
				echo '<li class="text-danger">' . _("Use a .csv or a .txt file.") . '</li></ul>';
				continue;
			}
			if ($_FILES["files"]["size"][$key] == 0) {
				echo '<li class="text-danger">' . _("The file is empty.") . '</li></ul>';
				continue;
			}
			if (!is_uploaded_file($_FILES["files"]["tmp_name"][$key])) {
				echo '<li class="text-danger">' . _("The upload is not valid.") . '</li></ul>';
				continue;
			}

			$handle = fopen($_FILES["files"]["tmp_name"][$key], "r");
			$i = 0;
			$added = 0;
			while (($device = fgetcsv($handle)) !== FALSE) {
				$i++;
				if (!isset($device[0]) || trim($device[0]) === "") {
					continue;
				}
				// Skip a header row, for example "MAC,Brand,Model,...".
				if ($i == 1 && !$this->mac_check_clean($device[0]) && stripos($device[0], 'mac') !== FALSE) {
					continue;
				}
				$mac = $this->mac_check_clean($device[0]);
				if (!$mac) {
					echo '<li class="text-danger">' . sprintf(_("Row %d: the MAC address is not valid."), $i) . '</li>';
					continue;
				}
				$brand_name = isset($device[1]) ? trim($device[1]) : '';
				$model_name = isset($device[2]) ? trim($device[2]) : '';
				$ext_name   = isset($device[3]) ? trim($device[3]) : '';
				$line_id    = (isset($device[4]) && trim($device[4]) !== '') ? trim($device[4]) : 1;
				$ipei       = isset($device[5]) ? trim($device[5]) : '';

				$brand_id = epm_sql('SELECT id FROM endpointman_brand_list WHERE name = ? LIMIT 1', 'getOne', array($brand_name));
				if (!$brand_id && $brand_name !== '') {
					$brand_id = epm_sql('SELECT id FROM endpointman_brand_list WHERE name LIKE ? LIMIT 1', 'getOne', array('%' . $brand_name . '%'));
				}
				if (!$brand_id) {
					echo '<li class="text-danger">' . sprintf(_("Row %d: the brand %s is not known."), $i, epm_esc($brand_name)) . '</li>';
					continue;
				}

				$model_id = epm_sql('SELECT id FROM endpointman_model_list WHERE brand = ? AND model = ? LIMIT 1', 'getOne', array($brand_id, $model_name));
				if (!$model_id && $model_name !== '') {
					$model_id = epm_sql('SELECT id FROM endpointman_model_list WHERE brand = ? AND model LIKE ? LIMIT 1', 'getOne', array($brand_id, '%' . $model_name . '%'));
				}
				if (!$model_id) {
					echo '<li class="text-danger">' . sprintf(_("Row %d: the model %s is not known."), $i, epm_esc($model_name)) . '</li>';
					continue;
				}

				$ext = epm_sql('SELECT extension, name FROM users WHERE extension = ? LIMIT 1', 'getRow', array($ext_name));
				if (empty($ext)) {
					echo '<li class="text-danger">' . sprintf(_("Row %d: the extension %s does not exist."), $i, epm_esc($ext_name)) . '</li>';
					continue;
				}

				$devices->error = array();
				if ($devices->add_device($mac, $model_id, $ext['extension'], 0, $line_id, $ext['name'], $ipei)) {
					$added++;
				} else {
					echo '<li class="text-danger">' . sprintf(_("Row %d: %s"), $i, implode(' ', $devices->error)) . '</li>';
				}
			}
			fclose($handle);
			echo '</ul><p>' . sprintf(ngettext("%d phone is added.", "%d phones are added.", $added), $added) . '</p>';
			if ($added > 0) {
				echo '<p>' . _("Write the configs of the new phones, and restart them: select the phones, and then use Selected phones > Write configs and restart.") . '</p>';
			}
		}
	}


	/**** FUNCIONES SEC MODULO "epm_advanced\oui_manager" ****/
	/**
	 * Delete a custom MAC prefix. The prefixes from the brand packages stay.
	 */
	private function _oui_remove()
	{
		if (!isset($_REQUEST['id_del']) || !is_numeric($_REQUEST['id_del']) || $_REQUEST['id_del'] < 0) {
			return array("status" => false, "message" => _("Select a MAC prefix."));
		}
		$id = $_REQUEST['id_del'];
		$custom = epm_sql('SELECT custom FROM endpointman_oui_list WHERE id = ?', 'getOne', array($id));
		if ($custom != 1) {
			return array("status" => false, "message" => _("Only a custom MAC prefix can be deleted. A brand update adds the built-in prefixes again."));
		}
		epm_sql('DELETE FROM endpointman_oui_list WHERE id = ? AND custom = 1', 'query', array($id));
		return array("status" => true, "message" => "OK", "id" => $id);
	}

	/**
	 * Add a custom MAC prefix for a brand.
	 */
	private function _oui_add()
	{
		$oui = isset($_REQUEST['number_new_oui']) ? strtoupper(preg_replace('/[\s:\-]/', '', (string) $_REQUEST['number_new_oui'])) : '';
		if (!preg_match('/^[0-9A-F]{6}$/', $oui)) {
			return array("status" => false, "message" => _("A MAC prefix is 6 hexadecimal characters, for example 0004F2."));
		}
		$brand = isset($_REQUEST['brand_new_oui']) ? $_REQUEST['brand_new_oui'] : '';
		if (!is_numeric($brand) || !epm_sql('SELECT id FROM endpointman_brand_list WHERE id = ?', 'getOne', array($brand))) {
			return array("status" => false, "message" => _("Select a brand."));
		}
		if (epm_sql('SELECT id FROM endpointman_oui_list WHERE oui = ?', 'getOne', array($oui))) {
			return array("status" => false, "message" => _("This MAC prefix is already in the list."));
		}
		epm_sql('INSERT INTO endpointman_oui_list (oui, brand, custom) VALUES (?, ?, \'1\')', 'query', array($oui, $brand));
		return array("status" => true, "message" => "OK", "oui" => $oui, "brand" => $brand);
	}


    /**
     * Fixes the display are special strings so we can visible see them instead of them being transformed
     * @param string $contents a string of course
     * @return string fixed string
     */
    function display_htmlspecialchars($contents) {
    	$contents = str_replace("&amp;", "&amp;amp;", $contents);
    	$contents = str_replace("&lt;", "&amp;lt;", $contents);
    	$contents = str_replace("&gt;", "&amp;gt;", $contents);
    	$contents = str_replace("&quot;", "&amp;quot;", $contents);
    	$contents = str_replace("&#039;", "&amp;#039;", $contents);
    	return($contents);
    }

    /**
     * Taken from PHP.net. A list of errors returned when uploading files.
     * @param <type> $error_code
     * @return string
     */
    function file_upload_error_message($error_code) {
    	switch ($error_code) {
    		case UPLOAD_ERR_INI_SIZE:
    			return _('The uploaded file exceeds the upload_max_filesize directive in php.ini');
    		case UPLOAD_ERR_FORM_SIZE:
    			return _('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form');
    		case UPLOAD_ERR_PARTIAL:
    			return _('The uploaded file was only partially uploaded');
    		case UPLOAD_ERR_NO_FILE:
    			return _('No file was uploaded');
    		case UPLOAD_ERR_NO_TMP_DIR:
    			return _('Missing a temporary folder');
    		case UPLOAD_ERR_CANT_WRITE:
    			return _('Failed to write file to disk');
    		case UPLOAD_ERR_EXTENSION:
    			return _('File upload stopped by extension');
    		default:
    			return _('Unknown upload error');
    	}
    }

	/**
     * This function takes a string and tries to determine if it's a valid mac addess, return FALSE if invalid
     * @param string $mac The full mac address
     * @return mixed The cleaned up MAC is it was a MAC or False if not a mac
     */
    function mac_check_clean($mac) {
    	if ((strlen($mac) == "17") OR (strlen($mac) == "12")) {
    		//It might be better to use switch here instead of these IF statements...
    		//Is the mac separated by colons(:) or dashes(-)?
    		if (preg_match("/[0-9a-f][0-9a-f][:-]" .
    				"[0-9a-f][0-9a-f][:-]" .
    				"[0-9a-f][0-9a-f][:-]" .
    				"[0-9a-f][0-9a-f][:-]" .
    				"[0-9a-f][0-9a-f][:-]" .
    				"[0-9a-f][0-9a-f]/i", $mac)) {
    				return(strtoupper(str_replace(":", "", str_replace("-", "", $mac))));
    				//Is the string exactly 12 characters?
    		} elseif (strlen($mac) == "12") {
    			//Now is the string a valid HEX mac address?
    			if (preg_match("/[0-9a-f][0-9a-f]" .
    					"[0-9a-f][0-9a-f]" .
    					"[0-9a-f][0-9a-f]" .
    					"[0-9a-f][0-9a-f]" .
    					"[0-9a-f][0-9a-f]" .
    					"[0-9a-f][0-9a-f]/i", $mac)) {
    					return(strtoupper($mac));
    			} else {
    				return(FALSE);
    			}
    			//Is the mac separated by whitespaces?
    		} elseif (preg_match("/[0-9a-f][0-9a-f][\s]" .
    				"[0-9a-f][0-9a-f][\s]" .
    				"[0-9a-f][0-9a-f][\s]" .
    				"[0-9a-f][0-9a-f][\s]" .
    				"[0-9a-f][0-9a-f][\s]" .
    				"[0-9a-f][0-9a-f]/i", $mac)) {
    				return(strtoupper(str_replace(" ", "", $mac)));
    		} else {
    			return(FALSE);
    		}
    	} else {
    		return(FALSE);
    	}
    }



}
