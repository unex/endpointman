<?php
/**
 * Endpoint Manager Object Module - Sec Templates
 *
 * @author Javier Pastor
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */

namespace FreePBX\modules;

require_once(__DIR__ . "/PageBase.class.php");

/**
 * The template pages: the template manager and the template editor.
 *
 * The ?subpage= value selects the manager or the editor. The ajax commands of
 * both share one namespace, as on SettingsPage and PackagesPage.
 */
class TemplatesPage extends \PageBase {
	// Declare these properties. PHP 8.2 gives a deprecation for a write to an
	// undeclared property, and this class does not have
	// #[\AllowDynamicProperties].
	public $eda;     //endpoint data abstraction layer
	public $system;  //FreePBX\modules\epm_system, from lib/
	public $tpl;     //Endpointman\View, owned by Endpointman
	public $error;   //error construct
	public $message; //message construct

	public function __construct($epm) {
		parent::__construct($epm);
		$this->eda     = $epm->eda;
		$this->system  = $epm->system;
		$this->tpl     = $epm->tpl;
		$this->error   = array();
		$this->message = array();
	}

	/**
	 * Show the manager or the editor. An absent or unknown ?subpage= shows the
	 * manager.
	 */
	public function showPage() {
		$subpage = $this->_subpage();
		$views = array(
			'manager' => __DIR__ . '/views/epm_templates_manager.page.php',
			'editor'  => __DIR__ . '/views/epm_templates_editor.page.php',
		);
		return load_view($views[$subpage], array('request' => $_REQUEST, 'subpage' => $subpage));
	}

	/**
	 * ?subpage= comes from $_REQUEST, so do not trust it. Match it against the
	 * two names, and use the manager for all other values.
	 *
	 * @return string "manager" or "editor"
	 */
	public function _subpage() {
		$subpage = $this->epm->getReq('subpage', '');
		return (is_string($subpage) && strtolower($subpage) === 'editor') ? 'editor' : 'manager';
	}

	public function ajaxRequest($req, &$setting) {
		$arrVal = array("model_clone", "list_current_template", "add_template", "del_template", "custom_config_get_gloabl", "custom_config_update_gloabl", "custom_config_reset_gloabl", "list_files_edit");
		if (in_array($req, $arrVal)) {
			$setting['authenticate'] = true;
			$setting['allowremote'] = false;
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * The manager and the editor share one command namespace, as on SettingsPage
	 * and PackagesPage. Each name is unique.
	 */
	public function ajaxHandler($command) {
		switch ($command) {
			case "list_current_template":
				return $this->epm_templates_list_current_templates();

			case "model_clone":
				return $this->epm_templates_model_clone();

			case "add_template":
				return $this->epm_templates_add_template();

			case "del_template":
				return $this->epm_templates_del_template();

			case "custom_config_get_gloabl":
				return $this->epm_template_custom_config_get_global();

			case "custom_config_update_gloabl":
				return $this->epm_template_custom_config_update_global();

			case "custom_config_reset_gloabl":
				return $this->epm_template_custom_config_reset_global();

			case "list_files_edit":
				foreach (array('idsel', 'custom', 'namefile') as $v) {
					if (!isset($_REQUEST[$v])) {
						return array("status" => false, "message" => _("No send value!") . " [" . $v . "]");
					}
				}
				return $this->edit_template_display_files($_REQUEST['idsel'], $_REQUEST['custom'], $_REQUEST['namefile']);

			default:
				return array("status" => false, "message" => _("Command not found!") . " [" . $command . "]");
		}
	}

	/**
	 * Runs before the page shows, on each request for this view.
	 *
	 * The Save button of the editor is the FreePBX action bar. It submits the
	 * `.fpbx-submit` form with command=save_template, the template id, the
	 * custom flag and all fields.
	 *
	 * On success, the browser goes back to what it edited: the same template, or
	 * the device list for a MAC-specific template. Thus a reload cannot post the
	 * form again.
	 *
	 * **POST only.** The referrer check in config.php is the CSRF protection of
	 * the framework. It runs only when the request has an `action` parameter, and
	 * this form does not have one. Without the POST check, a link could make a
	 * logged-in admin save an empty template. The real form already uses
	 * `method="post"`. This check does not replace a token for each form. A
	 * cross-site POST is still possible, as on the other write paths of the
	 * module, because FreePBX gives modules no token helper here.
	 */
	public function doConfigPageInit($command) {
		if ($command !== 'save_template') {
			return;
		}
		if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
			return;
		}
		$id     = $this->epm->getReq('id', '');
		$custom = $this->epm->getReq('custom', '0');
		if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) {
			return;
		}
		$custom = ($custom === '0' || $custom === 0 || $custom === '') ? '0' : '1';

		if ($this->epm->save_template($id, $custom, $_REQUEST) === false) {
			return;
		}
		if (isset($_REQUEST['silent_mode'])) {
			// save_template() already told the popup to close.
			exit;
		}
		if ($custom === '0') {
			redirect('config.php?display=endpointman&view=templates&subpage=editor&custom=0&idsel=' . (int) $id);
		}
		redirect('config.php?display=endpointman&view=devices');
	}

	/**
	 * Get the URL of the editor for one template.
	 *
	 * The devices page opens the editor in two ways. A MAC-specific template has
	 * custom=1 and uses the device id. A shared template has custom=0 and uses
	 * the template id. A $template_id of 0 means that the device has no shared
	 * template, which custom=1 encodes.
	 *
	 * @param  int|string $device_id   endpointman_mac_list.id
	 * @param  int|string $template_id endpointman_template_list.id, or 0
	 * @return string
	 */
	public function editorUrl($device_id, $template_id) {
		if ((int) $template_id === 0) {
			return 'config.php?display=endpointman&view=templates&subpage=editor&custom=1&idsel=' . (int) $device_id;
		}
		return 'config.php?display=endpointman&view=templates&subpage=editor&custom=0&idsel=' . (int) $template_id;
	}

	public function getRightNav($request) {
		if(isset($request['subpage']) && $request['subpage'] == "editor") {
			return load_view(__DIR__."/views/epm_templates/editor.views.rnav.php",array());
		} else {
			return '';
		}
	}

	/**
	 * The Save button of the editor.
	 *
	 * FreePBX calls this through Endpointman::getActionBar(), because it calls
	 * the hook on the module object and not on the page.
	 *
	 * The key sets the order in the bar. config.php sorts submit, duplicate,
	 * reset and delete. script.legacy.js reads `name` to find the action of the
	 * button. 'submit' for both makes the click post `.fpbx-submit`.
	 *
	 * **There is no Delete button.** The FreePBX handler for Delete reads
	 * `data-fpbx-delete` from the form and loads that URL. This form does not
	 * have the attribute, so the button goes to the text "undefined". A URL there
	 * needs a route that deletes on GET, and save_template() accepts only POST
	 * for this reason. To delete a template, use the trash icon on the manager
	 * grid (`del_template`), which asks for confirmation.
	 */
	public function getActionBar($request) {
		$buttons = array();
        switch(isset($request['subpage']) ? $request['subpage'] : '') {
            case 'editor':
                $buttons = array(
					'submit' => array(
                        'name' => 'submit',
                        'id' => 'save',
                        'value' => _('Save'),
                        'hidden' => ''
                    )
                );

				if(empty($request['idsel']) && empty($request['custom'])){
					$buttons = NULL;
				}
            	break;

			default:
        }
        return $buttons;
	}






	public function epm_template_custom_config_get_global()
	{
		if (! isset($_REQUEST['custom'])) {
			$retarr = array("status" => false, "message" => _("No send Custom Value!"));
		}
		elseif (! isset($_REQUEST['tid'])) {
			$retarr = array("status" => false, "message" => _("No send TID!"));
		}
		elseif (! is_numeric($_REQUEST['tid'])) {
			$retarr = array("status" => false, "message" => _("TID is not number!"));
		}
		else
		{
			$dget['custom'] = $_REQUEST['custom'];
			$dget['tid'] = $_REQUEST['tid'];

			if($dget['custom'] == 0) {
				//This is a group template
		        $sql = 'SELECT global_settings_override FROM endpointman_template_list WHERE id = ?';
		        $sql_p = array($dget['tid']);
			} else {
				//This is an individual template
		        $sql = 'SELECT global_settings_override FROM endpointman_mac_list WHERE id = ?';
		        $sql_p = array($dget['tid']);
			}
			$settings = epm_sql($sql, 'getOne', $sql_p);

			if (is_string($settings) && strlen($settings) > 0) {
				$settings = epm_blob_decode($settings);
				//$settings['tz'] = FreePBX::Endpointman()->listTZ(FreePBX::Endpointman()->configmod->get("tz"));
			}
			else {
				$settings = array();
				$settings['srvip'] = ""; //$this->configmod->get("srvip");
				$settings['ntp'] = ""; //$this->configmod->get("ntp");
				$settings['config_location'] = ""; //$this->configmod->get("config_location");
				$settings['tz'] = $this->configmod->get("tz");
				$settings['server_type'] = $this->configmod->get("server_type");
			}

			$retarr = array("status" => true, "settings" => $settings, "message" => _("Global Config Read OK!"));
			unset($dget);
		}
		return $retarr;
	}


	public function epm_template_custom_config_update_global ()
	{
		if (! isset($_REQUEST['custom'])) {
			$retarr = array("status" => false, "message" => _("No send Custom Value!"));
		}
		elseif (! isset($_REQUEST['tid'])) {
			$retarr = array("status" => false, "message" => _("No send TID!"));
		}
		elseif (! is_numeric($_REQUEST['tid'])) {
			$retarr = array("status" => false, "message" => _("TID is not number!"));
		}
		else
		{
			$dget['custom'] = $_REQUEST['custom'];
			$dget['tid'] = $_REQUEST['tid'];


			$_REQUEST['srvip'] = trim($_REQUEST['srvip']);  #trim whitespace from IP address
			$_REQUEST['config_loc'] = trim($_REQUEST['config_loc']);  #trim whitespace from Config Location

			$settings_warning = "";
			if (strlen($_REQUEST['config_loc']) > 0) {
				//No trailing slash. Help the user out and add one :-)
				if($_REQUEST['config_loc'][strlen($_REQUEST['config_loc'])-1] != "/") {
					$_REQUEST['config_loc'] = $_REQUEST['config_loc'] ."/";
				}

				if((isset($_REQUEST['config_loc'])) AND ($_REQUEST['config_loc'] != "")) {
					if((file_exists($_REQUEST['config_loc'])) AND (is_dir($_REQUEST['config_loc']))) {
						if(is_writable($_REQUEST['config_loc'])) {
							$_REQUEST['config_loc'] = $_REQUEST['config_loc'];
						} else {
							$settings_warning = _("Directory Not Writable!");
							$_REQUEST['config_loc'] = $this->configmod->get('config_location');
						}
					} else {
						$settings_warning = _("Not a Vaild Directory");
						$_REQUEST['config_loc'] = $this->configmod->get('config_location');
					}
				} else {
					$settings_warning = _("No Configuration Location Defined!");
					$_REQUEST['config_loc'] = $this->configmod->get('config_location');
				}
			}

			$settings['config_location'] = $_REQUEST['config_loc'];
			$settings['server_type'] = (isset($_REQUEST['server_type']) ? $_REQUEST['server_type'] : "");	//REVISAR NO ESTABA ANTES
			$settings['srvip'] = (isset($_REQUEST['srvip']) ? $_REQUEST['srvip'] : "");
			$settings['ntp'] = (isset($_REQUEST['ntp_server']) ? $_REQUEST['ntp_server'] : "");
			$settings['tz'] = (isset($_REQUEST['tz']) ? $_REQUEST['tz'] : "");
			$settings_ser = epm_blob_encode($settings);
			unset($settings);

			if($dget['custom'] == 0) {
				//This is a group template
				$sql = 'UPDATE endpointman_template_list SET global_settings_override = ? WHERE id = ?';
				$sql_p = array($settings_ser, $dget['tid']);
			} else {
				//This is an individual template
				$sql = 'UPDATE endpointman_mac_list SET global_settings_override = ? WHERE id = ?';
				$sql_p = array($settings_ser, $dget['tid']);
			}
			unset($settings_ser);
			epm_sql($sql, 'query', $sql_p);

			if (strlen($settings_warning) > 0) { $settings_warning = " ".$settings_warning; }
			$retarr = array("status" => true, "message" => _("Updated!").$settings_warning);
			unset($dget);
		}
		return $retarr;
	}


	public function epm_template_custom_config_reset_global()
	{
		if (! isset($_REQUEST['custom'])) {
			$retarr = array("status" => false, "message" => _("No send Custom Value!"));
		}
		elseif (! isset($_REQUEST['tid'])) {
			$retarr = array("status" => false, "message" => _("No send TID!"));
		}
		elseif (! is_numeric($_REQUEST['tid'])) {
			$retarr = array("status" => false, "message" => _("TID is not number!"));
		}
		else
		{
			$dget['custom'] = $_REQUEST['custom'];
			$dget['tid'] = $_REQUEST['tid'];

			if($dget['custom'] == 0) {
				//This is a group template
				$sql = 'UPDATE endpointman_template_list SET global_settings_override = NULL WHERE id = ?';
				$sql_p = array($dget['tid']);
			} else {
				//This is an individual template
				$sql = 'UPDATE endpointman_mac_list SET global_settings_override = NULL WHERE id = ?';
				$sql_p = array($dget['tid']);
			}
			epm_sql($sql, 'query', $sql_p);

			$retarr = array("status" => true, "message" => _("Globals Reset to Default!"));
			unset($dget);
		}
		return $retarr;
	}




	/**** FUNCIONES SEC MODULO "epm_template\manager" ****/
	public function epm_templates_del_template()
	{
		if (! isset($_REQUEST['idsel'])) {
			$retarr = array("status" => false, "message" => _("No send ID!"));
		}
		elseif (! is_numeric($_REQUEST['idsel'])) {
			$retarr = array("status" => false, "message" => _("ID is not number!"));
		}
		elseif ($_REQUEST['idsel'] <= 0) {
			$retarr = array("status" => false, "message" => _("ID send is negative!"));
		}
		else {
			$dget['idsel'] = $_REQUEST['idsel'];

			$sql = 'DELETE FROM endpointman_template_list WHERE id = ?';
			$sql_p = array($dget['idsel']);
			epm_sql($sql, 'query', $sql_p);
			$sql = 'UPDATE endpointman_mac_list SET template_id = 0 WHERE template_id = ?';
			$sql_p = array($dget['idsel']);
			epm_sql($sql, 'query', $sql_p);

			$retarr = array("status" => true, "message" => _("Delete Template OK!"));
			unset($dget);
		}
		return $retarr;
	}

	public function epm_templates_add_template ()
	{
		$arrVal['VAR_REQUEST'] = array("newnametemplate", "newproductselec", "newclonemodel");
		foreach ($arrVal['VAR_REQUEST'] as $valor) {
			if (! array_key_exists($valor, $_REQUEST)) {
				return array("status" => false, "message" => _("No send value!")." [".$valor."]");
			}
		}

		$arrVal['VAR_IS_NUM'] = array("newproductselec", "newclonemodel");
		foreach ($arrVal['VAR_IS_NUM'] as $valor) {
			if (! is_numeric($_REQUEST[$valor])) {
				return array("status" => false, "message" => _("Value send is not number!")." [".$valor."]");
			}
		}

		if (empty($_REQUEST['newnametemplate'])) {
			$retarr = array("status" => false, "message" => _("Name is null!"));
		}
		elseif ($_REQUEST['newproductselec'] <= 0) {
			$retarr = array("status" => false, "message" => _("Product send is negative!"));
		}
		elseif ($_REQUEST['newclonemodel'] <= 0) {
			$retarr = array("status" => false, "message" => _("Clone Model send is negative!"));
		}
		else {
			$dget['newnametemplate'] = $_REQUEST['newnametemplate'];
			$dget['newproductselec'] = $_REQUEST['newproductselec'];
			$dget['newclonemodel'] = $_REQUEST['newclonemodel'];

			$db = $this->db;
			$sql = "INSERT INTO endpointman_template_list (product_id, name, model_id) VALUES (?,?,?)";
			$q = $db->prepare($sql);
			$ob = $q->execute(array($dget['newproductselec'], $dget['newnametemplate'], $dget['newclonemodel']));
			$newid = $db->lastInsertId();
			//$this->edit_template_display($newid,0);

			$retarr = array("status" => true, "message" => _("Add New Template OK!"), "newid" => $newid);
			unset($dget);
		}
		return $retarr;
	}

	public function epm_templates_model_clone ()
	{
		if (! isset($_REQUEST['id'])) {
			$retarr = array("status" => false, "message" => _("No send ID!"));
		}
		elseif (! is_numeric($_REQUEST['id'])) {
			$retarr = array("status" => false, "message" => _("ID send is not number!"));
		}
		elseif ($_REQUEST['id'] <= 0) {
			$retarr = array("status" => false, "message" => _("ID send is number not valid!"));
		}
		else
		{
			$dget['id'] = $_REQUEST['id'];

			$i=0;
			$out = array();
			$sql = 'SELECT endpointman_model_list.id, endpointman_model_list.model as model FROM endpointman_model_list, endpointman_product_list WHERE endpointman_product_list.id = endpointman_model_list.product_id AND endpointman_model_list.enabled = 1 AND endpointman_model_list.hidden = 0 AND product_id = ?';
			$sql_p = array($dget['id']);
			$result = epm_sql($sql, 'getAll', $sql_p);
			foreach($result as $row) {
				$out[$i]['optionValue'] = $row['id'];
				$out[$i]['optionDisplay'] = $row['model'];
				$i++;
			}
			$retarr = array("status" => true, "message" => _("Generate list Ok!"), "listopt" => $out);

			unset($dget);
		}
		return $retarr;
	}

	public function epm_templates_list_current_templates ()
	{

		$sql = 'SELECT endpointman_template_list.*, endpointman_product_list.short_name as model_class, endpointman_model_list.model as model_clone, endpointman_model_list.enabled FROM endpointman_template_list, endpointman_model_list, endpointman_product_list WHERE endpointman_model_list.hidden = 0 AND endpointman_template_list.model_id = endpointman_model_list.id AND endpointman_template_list.product_id = endpointman_product_list.id';
		$sql_p = array();
		$template_list = epm_sql($sql, 'getAll', $sql_p);
		$i = 0;
		$row_out = array();
		foreach($template_list as $row) {
			$row_out[$i] = $row;
			$row_out[$i]['custom'] = 0;
			if(!$row['enabled']) {
				$row_out[$i]['model_clone'] = $row_out[$i]['model_clone'];
			}
			$i++;
		}

		$sql = 'SELECT endpointman_mac_list.mac, endpointman_mac_list.id, endpointman_mac_list.model, endpointman_model_list.model as model_clone, endpointman_product_list.short_name as model_class FROM endpointman_mac_list, endpointman_model_list, endpointman_product_list WHERE  endpointman_product_list.id = endpointman_model_list.product_id AND endpointman_mac_list.global_custom_cfg_data IS NOT NULL AND endpointman_model_list.id = endpointman_mac_list.model AND endpointman_mac_list.template_id = 0';
		$sql_p = array();
		$template_list = epm_sql($sql, 'getAll', $sql_p);
		foreach($template_list as $row) {
			$sql = 'SELECT  description , line FROM  endpointman_line_list WHERE  mac_id =? ORDER BY line ASC';
			$sql_p = array($row['id']);
			$line_list = epm_sql($sql, 'getAll', $sql_p);
			$description = "";
			$c = 0;
			foreach($line_list as $line_row) {
				if($c > 0) {
					$description .= ", ";
				}
				$description .= $line_row['description'];
				$c++;
			}
			$row_out[$i] = $row;
			$row_out[$i]['custom'] = 1;
			$row_out[$i]['name'] = $row['mac'];
			$row_out[$i]['description'] = $description;
			$i++;
		}

	/*
		//$sql = 'SELECT endpointman_oui_list.id, endpointman_oui_list.oui , endpointman_brand_list.name, endpointman_oui_list.custom FROM endpointman_oui_list , endpointman_brand_list WHERE endpointman_oui_list.brand = endpointman_brand_list.id ORDER BY endpointman_oui_list.oui ASC';
		$sql = 'SELECT T1.id, T1.oui, T2.name, T1.custom FROM endpointman_oui_list as T1 , endpointman_brand_list as T2 WHERE T1.brand = T2.id ORDER BY T1.oui ASC';
		$data = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
		$ret = array();
		foreach ($data as $item) {
			$ret[] = array('id' => $item['id'], 'oui' => $item['oui'], 'brand' => $item['name'], 'custom' => $item['custom']);
		}
		*/
		return $row_out;
	}









	function edit_template_display_files($id, $custom, $namefile = "")
	{
    	if ($custom == 0) {
    		$sql = 'SELECT model_id FROM endpointman_template_list WHERE id=?';
    		$sql_p = array($id);
    	} else {
    		$sql = 'SELECT model FROM endpointman_mac_list WHERE id=?';
    		$sql_p = array($id);
    	}
    	$model_id = epm_sql($sql, 'getOne', $sql_p);
    	if (!$this->epm->brandmanager->sync_model($model_id)) {
    		die("unable to sync local template files - TYPE:" . $custom);
    	}

    	$dReturn = array();
		if ($custom == 0) {
			$sql = 'SELECT endpointman_model_list.max_lines, endpointman_model_list.model as model_name, endpointman_template_list.global_custom_cfg_data,  endpointman_product_list.config_files, endpointman_product_list.short_name, endpointman_product_list.id as product_id, endpointman_model_list.template_data, endpointman_model_list.id as model_id, endpointman_template_list.* FROM endpointman_product_list, endpointman_model_list, endpointman_template_list WHERE endpointman_product_list.id = endpointman_template_list.product_id AND endpointman_template_list.model_id = endpointman_model_list.id AND endpointman_template_list.id = ?';
			$sql_p = array($id);
		} else {
			$sql = 'SELECT endpointman_model_list.max_lines, endpointman_model_list.model as model_name, endpointman_mac_list.global_custom_cfg_data, endpointman_product_list.config_files, endpointman_mac_list.*, endpointman_line_list.*, endpointman_model_list.id as model_id, endpointman_model_list.template_data, endpointman_product_list.id as product_id, endpointman_product_list.short_name, endpointman_product_list.cfg_dir, endpointman_brand_list.directory FROM endpointman_brand_list, endpointman_mac_list, endpointman_model_list, endpointman_product_list, endpointman_line_list WHERE endpointman_mac_list.id=? AND endpointman_mac_list.id = endpointman_line_list.mac_id AND endpointman_mac_list.model = endpointman_model_list.id AND endpointman_model_list.brand = endpointman_brand_list.id AND endpointman_model_list.product_id = endpointman_product_list.id';
			$sql_p = array($id);
		}
		$row = epm_sql($sql, 'getRow', $sql_p);


		if ($row['config_files_override'] == "") {
			$config_files_saved = "";
		} else {
			$config_files_saved = epm_blob_decode($row['config_files_override']);
		}
		$config_files_list = explode(",", $row['config_files']);
		asort($config_files_list);

		$i = 0;
		$b = 0;
		$alt_configs = array();
		$only_configs = array();
		foreach ($config_files_list as $files)
		{
			if ($namefile != $files)  { continue; }

			$only_configs[$b]['id'] = $b;
			$only_configs[$b]['id_d'] = $id;
			$only_configs[$b]['id_p'] = $row['product_id'];
			$only_configs[$b]['name'] = $files;
			$only_configs[$b]['select'] = "ON";

			$sql = 'SELECT * FROM  endpointman_custom_configs WHERE product_id = ? AND original_name = ?';
			$sql_p = array($row['product_id'], $files);
			$alt_configs_list = epm_sql($sql, 'getAll', $sql_p);

			if ( count($alt_configs_list) > 0)
			{
				$files = str_replace(".", "_", $files);
				foreach ($alt_configs_list as $ccf)
				{
					$cf_key = $files;
					if ((isset($config_files_saved[$cf_key])) AND (is_array($config_files_saved)) AND ($config_files_saved[$cf_key] == $ccf['id'])) {
						$alt_configs[$i]['select'] = 'ON';
						$only_configs[$b]['select'] = "OFF";
					}
					else {
						$alt_configs[$i]['select'] = 'OFF';
					}
					$alt_configs[$i]['id'] = $ccf['id'];
					$alt_configs[$i]['id_p'] = $row['product_id'];
					$alt_configs[$i]['name'] = $ccf['name'];
					$alt_configs[$i]['name_original'] = $files;

					$i++;
				}
			}
		}

		$dReturn['only_configs'] = $only_configs;
		$dReturn['alt_configs'] = $alt_configs;

    	return $dReturn;
	}

	function edit_template_display_files_list($id, $custom)
	{
    	if ($custom == 0) {
    		$sql = 'SELECT model_id FROM endpointman_template_list WHERE id=?';
    		$sql_p = array($id);
    	} else {
    		$sql = 'SELECT model FROM endpointman_mac_list WHERE id=?';
    		$sql_p = array($id);
    	}
    	$model_id = epm_sql($sql, 'getOne', $sql_p);
    	if (!$this->epm->brandmanager->sync_model($model_id)) {
    		die("unable to sync local template files - TYPE:" . $custom);
    	}


		if ($custom == 0) {
			$sql = 'SELECT endpointman_model_list.max_lines, endpointman_model_list.model as model_name, endpointman_template_list.global_custom_cfg_data,  endpointman_product_list.config_files, endpointman_product_list.short_name, endpointman_product_list.id as product_id, endpointman_model_list.template_data, endpointman_model_list.id as model_id, endpointman_template_list.* FROM endpointman_product_list, endpointman_model_list, endpointman_template_list WHERE endpointman_product_list.id = endpointman_template_list.product_id AND endpointman_template_list.model_id = endpointman_model_list.id AND endpointman_template_list.id = ?';
			$sql_p = array($id);
		} else {
			$sql = 'SELECT endpointman_model_list.max_lines, endpointman_model_list.model as model_name, endpointman_mac_list.global_custom_cfg_data, endpointman_product_list.config_files, endpointman_mac_list.*, endpointman_line_list.*, endpointman_model_list.id as model_id, endpointman_model_list.template_data, endpointman_product_list.id as product_id, endpointman_product_list.short_name, endpointman_product_list.cfg_dir, endpointman_brand_list.directory FROM endpointman_brand_list, endpointman_mac_list, endpointman_model_list, endpointman_product_list, endpointman_line_list WHERE endpointman_mac_list.id=? AND endpointman_mac_list.id = endpointman_line_list.mac_id AND endpointman_mac_list.model = endpointman_model_list.id AND endpointman_model_list.brand = endpointman_brand_list.id AND endpointman_model_list.product_id = endpointman_product_list.id';
			$sql_p = array($id);
		}
		$row = epm_sql($sql, 'getRow', $sql_p);
		$config_files_list = explode(",", $row['config_files']);
		asort($config_files_list);

		$i = 0;
		$b = 0;
		$dReturn = array();
		foreach ($config_files_list as $files)
		{
			$dReturn[$b]['id'] = $b;
			$dReturn[$b]['id_d'] = $id;
			$dReturn[$b]['id_p'] = $row['product_id'];
			$dReturn[$b]['name'] = $files;
			$b++;
		}
		unset($config_files_list);

    	return $dReturn;
	}





	/**
     * Custom Means specific to that MAC
     * id is either the mac ID (not address) or the template ID
     * @param integer $id
     * @param integer $custom
     */
    function edit_template_display($id, $custom) {
    	//endpointman_flush_buffers();

    	$alt_configs = NULL;

    	if ($custom == 0) {
    		$sql = 'SELECT model_id FROM endpointman_template_list WHERE id=?';
    		$sql_p = array($id);
    	} else {
    		$sql = 'SELECT model FROM endpointman_mac_list WHERE id=?';
    		$sql_p = array($id);
    	}

    	$model_id = epm_sql($sql, 'getOne', $sql_p);

    	//Make sure the model data from the local confg files are stored in the database and vice-versa. Serious errors will occur if the database is not in sync with the local file
    	if (!$this->epm->brandmanager->sync_model($model_id)) {
    		die("unable to sync local template files - TYPE:" . $custom);
    	}

    	$dReturn = array();


		//Determine if we are dealing with a general template or a specific [for that phone only] template (custom =0 means general)
		if ($custom == 0) {
			$sql = 'SELECT endpointman_model_list.max_lines, endpointman_model_list.model as model_name, endpointman_template_list.global_custom_cfg_data,  endpointman_product_list.config_files, endpointman_product_list.short_name, endpointman_product_list.id as product_id, endpointman_model_list.template_data, endpointman_model_list.id as model_id, endpointman_template_list.* FROM endpointman_product_list, endpointman_model_list, endpointman_template_list WHERE endpointman_product_list.id = endpointman_template_list.product_id AND endpointman_template_list.model_id = endpointman_model_list.id AND endpointman_template_list.id = ?';
			$sql_p = array($id);
		} else {
			$sql = 'SELECT endpointman_model_list.max_lines, endpointman_model_list.model as model_name, endpointman_mac_list.global_custom_cfg_data, endpointman_product_list.config_files, endpointman_mac_list.*, endpointman_line_list.*, endpointman_model_list.id as model_id, endpointman_model_list.template_data, endpointman_product_list.id as product_id, endpointman_product_list.short_name, endpointman_product_list.cfg_dir, endpointman_brand_list.directory FROM endpointman_brand_list, endpointman_mac_list, endpointman_model_list, endpointman_product_list, endpointman_line_list WHERE endpointman_mac_list.id=? AND endpointman_mac_list.id = endpointman_line_list.mac_id AND endpointman_mac_list.model = endpointman_model_list.id AND endpointman_model_list.brand = endpointman_brand_list.id AND endpointman_model_list.product_id = endpointman_product_list.id';
			$sql_p = array($id);
		}
		$row = epm_sql($sql, 'getRow', $sql_p);


		$dReturn['template_editor_display'] = 1;

		//Let the template system know if we are working with a general template or a specific [for that phone only] template
		$dReturn['custom'] = $custom;
    	 if ($custom)
    	 {
			$dReturn['ext'] = $row['ext'];
    	 }
    	 else
    	 {
    	 	$dReturn['template_name'] = $row['name'];
    	 }
		$dReturn['product'] = $row['short_name'];
		$dReturn['model'] = $row['model_name'];

		if ($ma = $this->models_available($row['model_id'], NULL, $row['product_id'])) {
			$dReturn['models_ava'] = $ma;
		}

		if (isset($_REQUEST['maxlines'])) {
			$areas = $this->areaAvailable($row['model_id'], $_REQUEST['maxlines']);
		} else {
			$areas = $this->areaAvailable($row['model_id'], 1);
		}
		$dReturn['area_ava'] = $areas;

		//Start the display of the html file in the product folder
		if ($row['config_files_override'] == "") {
			$config_files_saved = "";
		} else {
			$config_files_saved = epm_blob_decode($row['config_files_override']);
		}
		$config_files_list = explode(",", $row['config_files']);
		asort($config_files_list);

		$alt = 0;
		$i = 0;
		$b = 0;
		$only_configs = array();
		foreach ($config_files_list as $files) {
			$sql = 'SELECT * FROM  endpointman_custom_configs WHERE product_id = ? AND original_name = ?';
			$sql_p = array($row['product_id'], $files);
			$alt_configs_list_count = epm_sql($sql, 'getAll', $sql_p);
			if (! empty($alt_configs_list_count)) {
				$alt_configs_list = epm_sql($sql, 'getAll', $sql_p);
				$alt_configs[$i]['name'] = $files;
				$alt_configs[$i]['id_p'] = $row['product_id'];
				$files = str_replace(".", "_", $files);
				$h = 0;
				foreach ($alt_configs_list as $ccf) {
					$alt_configs[$i]['list'][$h]['id'] = $ccf['id'];
					$cf_key = $files;
					if ((isset($config_files_saved[$cf_key])) AND (is_array($config_files_saved)) AND ($config_files_saved[$cf_key] == $ccf['id'])) {
						$alt_configs[$i]['list'][$h]['selected'] = 'selected';
					}
					$alt_configs[$i]['list'][$h]['id'] = $h;
					$alt_configs[$i]['list'][$h]['name'] = $ccf['name'];
					$h++;
				}
				$alt = 1;
			}
			else {
				$only_configs[$b]['id'] = $b;
				$only_configs[$b]['id_d'] = $id;
				$only_configs[$b]['id_p'] = $row['product_id'];
				$only_configs[$b]['name'] = $files;
				$b++;
			}
			$i++;
		}

		$dReturn['only_configs'] = $only_configs;
		$dReturn['alt_configs'] = $alt_configs;
		$dReturn['alt'] = $alt;

		if (!isset($_REQUEST['maxlines'])) {
			$maxlines = 1;
		} else {
			$maxlines = $_REQUEST['maxlines'];
		}
		if ($row['template_data'] != "") {
			$out = $this->generate_gui_html($row['template_data'], $row['global_custom_cfg_data'], TRUE, NULL, $maxlines);
		} else {
			$out = "No Template Data has been defined for this Product<br />";
		}

		$dReturn['template_editor'] = $out;
		$dReturn['hidden_id'] = $row['id'];
		$dReturn['hidden_custom'] = $custom;

    	return $dReturn;
    }

	/*
	 * The devices page and this class share the four helpers below. They build
	 * arrays that do not depend on the format. The working copies are in
	 * DevicesPage, so these methods call them. includes/popup.inc.php reaches
	 * generate_gui_html in the same way.
	 */

	function models_available($model = NULL, $brand = NULL, $product = NULL) {
		return $this->epm->_get_page('devices')->models_available($model, $brand, $product);
	}

	function areaAvailable($model, $area = NULL) {
		return $this->epm->_get_page('devices')->areaAvailable($model, $area);
	}

	function generate_gui_html($cfg_data, $custom_cfg_data = NULL, $admin = FALSE, $user_cfg_data = NULL, $max_lines = 3, $ext = NULL) {
		return $this->epm->_get_page('devices')->generate_gui_html($cfg_data, $custom_cfg_data, $admin, $user_cfg_data, $max_lines, $ext);
	}

	function generate_form_data($i, $cfg_data, $key = NULL, $custom_cfg_data = NULL, $admin = FALSE, $user_cfg_data = NULL, $extra_data = NULL, $template_type = 'GENERAL') {
		return $this->epm->_get_page('devices')->generate_form_data($i, $cfg_data, $key, $custom_cfg_data, $admin, $user_cfg_data, $extra_data, $template_type);
	}
}
