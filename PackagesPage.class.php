<?php
/**
 * Endpoint Manager Object Module - Sec Config
 *
 * @author Javier Pastor
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */

namespace FreePBX\modules;

require_once(__DIR__ . "/PageBase.class.php");

class PackagesPage extends \PageBase {
	// public $UPDATE_PATH;
    // public $MODULES_PATH;
	// public $LOCAL_PATH;
	// public $PHONE_MODULES_PATH;
	// public $PROVISIONER_BASE;

	// public function __construct($freepbx = null, $cfgmod = null, $system = null)
	// {
	// 	$this->freepbx = $freepbx;
	// 	$this->db = $freepbx->Database;
	// 	$this->config = $freepbx->Config;
	// 	$this->configmod = $cfgmod;
	// 	$this->system = $system;


	// 	$this->UPDATE_PATH = $this->configmod->get('update_server');
    //     $this->MODULES_PATH = $this->config->get('AMPWEBROOT') . '/admin/modules/';
    //     if (file_exists($this->MODULES_PATH . "endpointman/")) {
    //         $this->LOCAL_PATH = $this->MODULES_PATH . "endpointman/";
    //     } else {
    //         die("Can't Load Local Endpoint Manager Directory!");
    //     }
    //     if (file_exists($this->MODULES_PATH . "_ep_phone_modules/")) {
    //         $this->PHONE_MODULES_PATH = $this->MODULES_PATH . "_ep_phone_modules/";
    //     } else {
    //         $this->PHONE_MODULES_PATH = $this->MODULES_PATH . "_ep_phone_modules/";
    //         if (!file_exists($this->PHONE_MODULES_PATH)) {
    //             mkdir($this->PHONE_MODULES_PATH, 0775);
    //         }
    //         if (file_exists($this->PHONE_MODULES_PATH . "setup.php")) {
    //             unlink($this->PHONE_MODULES_PATH . "setup.php");
    //         }
    //         if (!file_exists($this->MODULES_PATH . "_ep_phone_modules/")) {
    //             die('Endpoint Manager can not create the modules folder!');
    //         }
    //     }

	// }

	public function showPage() {
		return load_view(__DIR__ . '/views/packages.php');
	}

	public function ajaxRequest($req, &$setting) {
		$arrVal = array("saveconfig", "list_all_brand", "list_brand_model_hide");
		if (in_array($req, $arrVal)) {
			$setting['authenticate'] = true;
			$setting['allowremote'] = false;
			return true;
		}
		return false;
	}

    public function ajaxHandler($command) {
		$txt = array(
			'ayuda_model' => _("If we can activate the model set terminals of the models.<br /> If this model is disabled will not appear in the list of models that can be configured for PBX."),
			'ayuda_producto' => _('The button "Install Firmware" installs the necessary files to the server for the terminal alone are updated via TFTP or HTTP.<br /> The button "Remove frimware" delete files server products.<br /> The button "Update frimware" appears if a newer frimware detected on the server and asks if you want to update.<br /> The "Update" button appears when a new version of this model pack is detected.'),
			'ayuda_marca' => _('The "Install" button installs the configuration package brand models we selected.<br /> The "Uninstall" button removes the package configuration models of the brand selected.<br /> The "Update" button appears if a new version of the package that is already installed to upgrade to the latest version is detected.'),
			'new_pack_mod' => _("New Package Modified"),
			'pack_last_mod' => _("Package Last Modified"),
			'check_update' => _("Check for Update "),
			'check_online' => _("Check Online "),
			'install' => _("Install"),
			'uninstall' => _("Uninstall"),
			'update' => _("Update"),
			'fw_install' => _('FW Install'),
			'fw_uninstall' =>  _('FW Delete'),
			'fw_update' => _('FW Update'),
			'enable' => _('Enable'),
			'disable' => _('Disable'),
			'show' => _("Show"),
			'hide' => _("Hide"),
			'ready' => _("Ready!"),
			'error' => _("Error!"),
			'title_update' => _("Update!"),
			'save_changes' => _("Saving Changes..."),
			'save_changes_ok' => _("Saving Changes... Ok!"),
			'err_upload_content' => _("Upload Content!"),
			'check' => _("Check for Updates..."),
			'check_ok' => _("Check for Updates... Ok!"),
			'update_content' => _("Update Content..."),
			'opt_invalid' => _("Invalid Option!")
		);

		switch ($command) {
			case "saveconfig":
				$retarr = $this->epm_config_manager_saveconfig();
				break;

			case "list_all_brand":
				$retarr = array("status" => true, "message" => "OK", "datlist" => $this->epm_config_manager_hardware_get_list_all());
				break;

			case "list_brand_model_hide":
				$retarr = array("status" => true, "message" => "OK", "datlist" => $this->epm_config_manager_hardware_get_list_all_hide_show());
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
			case "check_for_updates":
				$this->epm_config_manager_check_for_updates();
				echo "<br /><hr><br />";
				exit;
				break;

			case "firmware":
				$this->epm_config_manager_firmware();
				echo "<br /><hr><br />";
				exit;
				break;

			case "brand":
				$this->epm_config_manager_brand();
				echo "<br /><hr><br />";
				exit;
				break;
		}
	}

	public function getRightNav($request) {
		return "";
	}

	public function getActionBar($request) {
		return "";
	}


	/**
     * Get info models by product id selected.
     * @param int $id_product product ID
	 * @param bool $show_all True return all, False return hidden = 0
     * @return array
     */
	public function epm_config_hardware_get_list_models($id_product=NULL, $show_all = true, $byorder = "model")
	{
		if(! is_numeric($id_product)) { throw new \Exception( _("ID Producto not is number")." (".$id_product.")"); }
		$byorder = $this->_order_column($byorder, array('id', 'brand', 'model', 'max_lines', 'product_id', 'enabled', 'hidden'), 'model');
		if($show_all == true) 	{ $sql = 'SELECT * FROM endpointman_model_list WHERE product_id = ? ORDER BY '.$byorder.' ASC'; }
		else 					{ $sql = 'SELECT * FROM endpointman_model_list WHERE hidden = 0 AND product_id = ? ORDER BY '.$byorder.' ASC'; }
		$result = epm_sql($sql, 'getAll', array($id_product));
		return $result;
	}

	/**
     * Get info product by brand id selected.
     * @param int $id_brand brand ID
	 * @param bool $show_all True return all, FAlse return hidde = 0
     * @return array
     */
	public function epm_config_hardware_get_list_product($id_brand=NULL, $show_all = true, $byorder = "long_name")
	{
		if(! is_numeric($id_brand)) { throw new \Exception(_("ID Brand not is numbre")." (".$id_brand.")"); }
		$byorder = $this->_order_column($byorder, array('id', 'brand', 'short_name', 'long_name', 'cfg_dir', 'cfg_ver', 'hidden'), 'long_name');
		if ($show_all == true) 	{ $sql = 'SELECT * FROM endpointman_product_list WHERE brand = ? ORDER BY '.$byorder.' ASC'; }
		else 					{ $sql = 'SELECT * FROM endpointman_product_list WHERE hidden = 0 AND brand = ? ORDER BY '.$byorder.' ASC'; }
		$result = epm_sql($sql, 'getAll', array($id_brand));
		return $result;
	}

	/**
     * Get info all brands.
	 * @param bool $show_all True return all, False return hidde = 0
     * @return array
     */
	public function epm_config_hardware_get_list_brand($show_all = true, $byorder = "id") {
		$byorder = $this->_order_column($byorder, array('id', 'name', 'directory', 'cfg_ver', 'installed', 'hidden'), 'id');
		if ($show_all == true) 	{ $sql = "SELECT * from endpointman_brand_list WHERE id > 0 ORDER BY " . $byorder . " ASC "; }
		else 					{ $sql = "SELECT * from endpointman_brand_list WHERE id > 0 AND hidden = 0 ORDER BY " . $byorder . " ASC "; }
		$result = epm_sql($sql, 'getAll', array());
		return $result;
	}

	/**
	 * Limit an ORDER BY column to a column that the table has.
	 *
	 * A column name is an identifier, so a placeholder cannot carry it. A fixed
	 * set is the only safe method. An unknown sort order is a display preference,
	 * not an error, so use $default and do not throw.
	 *
	 * @param string $byorder  requested column
	 * @param array  $allowed  the sortable columns of the table
	 * @param string $default  column to use when $byorder is not one of them
	 * @return string a column name safe to concatenate
	 */
	private function _order_column($byorder, $allowed, $default) {
		return (is_string($byorder) && in_array($byorder, $allowed, true)) ? $byorder : $default;
	}


	/**** FUNCIONES SEC MODULO "epm_config\editor" ****/
	/**
     * Get info all brdans, prodics, models.
     * @return array
     */
	 /*
	 SE DESACTIVA A VERS SI NO SE USA EN NINGUN SITIO.
	public function epm_config_editor_hardware_get_list_all ()
	{
		$row_out = array();
		$i = 0;
		foreach ($this->epm_config_hardware_get_list_brand(true, "name") as $row)
		{
			$row_out[$i] = $row;
			$row_out[$i]['count'] = $i;
			if ($row['installed'])
			{
				$j = 0;
				foreach ($this->epm_config_hardware_get_list_product($row['id'], true) as $row2)
				{
					$row_out[$i]['products'][$j] = $row2;
					$k = 0;
					foreach ($this->epm_config_hardware_get_list_models($row2['id'], true) as $row3) {
						$row_out[$i]['products'][$j]['models'][$k] = $row3;
						$k++;
					}
					$j++;
				}
			}
			$i++;
		}
		return $row_out;
	}
	*/
	/*** END SEC FUNCTIONS ***/






	/**** FUNCIONES SEC MODULO "epm_config\manager" ****/
	private function epm_config_manager_check_for_updates ()
	{
		out("<h3>Update data...</h3>");
		$this->epm->brandmanager->update_check(true);
		out ("All Done!");
	}

	private function epm_config_manager_brand()
	{
		$arrVal['VAR_REQUEST'] = array("command_sub", "idfw");
		foreach ($arrVal['VAR_REQUEST'] as $valor) {
			if (! array_key_exists($valor, $_REQUEST)) {
				out (_("Error: No send value!")." [".$valor."]");
				return false;
			}
		}

		$arrVal['VAR_IS_NUM'] = array("idfw");
		foreach ($arrVal['VAR_IS_NUM'] as $valor) {
			if (! is_numeric($_REQUEST[$valor])) {
				out (_("Error: Value send is not number!")." [".$valor."]");
				return false;
			}
		}

		$dget['command'] =  strtolower($_REQUEST['command_sub']);
		$dget['id'] = $_REQUEST['idfw'];

		switch($dget['command']) {
			case "brand_install":
			case "brand_update":
				$this->epm->brandmanager->download_brand($dget['id']);
				break;

			case "brand_uninstall":
				$this->epm->brandmanager->remove_brand($dget['id']);
				break;

			default:
				out (_("Error: Command not found!")." [" . $dget['command'] . "]");
		}
		$this->epm->brandmanager->update_check();
		unset ($dget);

		return true;
	}

	private function epm_config_manager_firmware()
	{
		$arrVal['VAR_REQUEST'] = array("command_sub", "idfw");
		foreach ($arrVal['VAR_REQUEST'] as $valor) {
			if (! array_key_exists($valor, $_REQUEST)) {
				out (_("Error: No send value!")." [".$valor."]");
				return false;
			}
		}

		$arrVal['VAR_IS_NUM'] = array("idfw");
		foreach ($arrVal['VAR_IS_NUM'] as $valor) {
			if (! is_numeric($_REQUEST[$valor])) {
				out (_("Error: Value send is not number!")." [".$valor."]");
				return false;
			}
		}

		$dget['command'] =  strtolower($_REQUEST['command_sub']);
		$dget['id'] = $_REQUEST['idfw'];

		switch($dget['command']) {
			case "fw_install":
			case "fw_update":
				$this->epm->brandmanager->install_firmware($dget['id']);
				break;

			case "fw_uninstall":
				$this->epm->brandmanager->remove_firmware($dget['id']);
				break;

			default:
				out (_("Error: Command not found!")." [" . $dget['command'] . "]");
		}

		unset ($dget);
		return true;
	}

	private function epm_config_manager_saveconfig()
	{
		$arrVal['VAR_REQUEST'] = array("typesavecfg", "value", "idtype", "idbt");
		foreach ($arrVal['VAR_REQUEST'] as $valor) {
			if (! array_key_exists($valor, $_REQUEST)) {
				return array("status" => false, "message" => _("No send value!")." [".$valor."]");
			}
		}

		$arrVal['VAR_IS_NUM'] = array("value", "idbt");
		foreach ($arrVal['VAR_IS_NUM'] as $valor) {
			if (! is_numeric($_REQUEST[$valor])) {
				return array("status" => false, "message" => _("Value send is not number!")." [".$valor."]");
			}
		}

		$dget['typesavecfg'] = strtolower($_REQUEST['typesavecfg']);
		$dget['value'] = strtolower($_REQUEST['value']);
		$dget['idtype'] = strtolower($_REQUEST['idtype']);
		$dget['id'] = $_REQUEST['idbt'];

		if (! in_array($dget['typesavecfg'], array("hidden", "enabled"))) {
			return array("status" => false, "message" => _("Type Save Config is not valid!")." [".$dget['typesavecfg']."]");
		}

		if (($dget['value'] > 1 ) and ($dget['value'] < 0)) {
			return array("status" => false, "message" => _("Invalid Value!"));
		}


		if ($dget['typesavecfg'] == "enabled") {
			if (($dget['idtype']) == "modelo") {
				$sql = 'UPDATE endpointman_model_list SET enabled = ? WHERE id = ?';
				$sql_p = array($dget['value'], $dget['id']);
			}
			else {
				$retarr = array("status" => false, "message" => _("IdType not valid to typesavecfg!"));
			}
		}
		else {
			switch($dget['idtype']) {
				case "marca":
					$sql = 'UPDATE endpointman_brand_list SET hidden = ? WHERE id = ?';
					$sql_p = array($dget['value'], $dget['id']);
					break;

				case "producto":
					$sql = 'UPDATE endpointman_product_list SET hidden = ? WHERE id = ?';
					$sql_p = array($dget['value'], $dget['id']);
					break;

				case "modelo":
					$sql = 'UPDATE endpointman_model_list SET hidden = ? WHERE id = ?';
					$sql_p = array($dget['value'], $dget['id']);
					break;

				default:
					$retarr = array("status" => false, "message" => _("IDType invalid: ") . $dget['idtype'] );
			}
		}
		if (isset($sql)) {
			epm_sql($sql, 'query', $sql_p);
			$retarr = array("status" => true, "message" => "OK", "typesavecfg" => $dget['typesavecfg'], "value" => $dget['value'], "idtype" => $dget['idtype'], "id" => $dget['id']);
			unset($sql);
		}

		unset($dget);
		return $retarr;
	}

	public function epm_config_manager_hardware_get_list_all_hide_show()
	{
		$row_out = array();

		$i = 0;
		$brand_list = $this->epm_config_hardware_get_list_brand(true, "name");
		foreach ($brand_list as $row)
		{
			//$row_out[$i] = $row;
			$row_out[$i]['id'] = $row['id'];
			$row_out[$i]['name'] = $row['name'];
			$row_out[$i]['directory'] = $row['directory'];
			$row_out[$i]['installed'] = $row['installed'];
			$row_out[$i]['hidden'] = $row['hidden'];
			$row_out[$i]['count'] = $i;
			$row_out[$i]['products'] = array();
			if ($row['hidden'] == 1)
			{
				$i++;
				continue;
			}

			$j = 0;
			$product_list = $this->epm_config_hardware_get_list_product($row['id'], true);
			foreach($product_list as $row2) {
				//$row_out[$i]['products'][$j] = $row2;
				$row_out[$i]['products'][$j]['id'] = $row2['id'];
				$row_out[$i]['products'][$j]['brand'] = $row2['brand'];
				$row_out[$i]['products'][$j]['long_name'] = $row2['long_name'];
				$row_out[$i]['products'][$j]['short_name'] = $row2['short_name'];
				$row_out[$i]['products'][$j]['hidden'] = $row2['hidden'];
				$row_out[$i]['products'][$j]['count'] = $j;
				$row_out[$i]['products'][$j]['models'] = array();
				if ($row2['hidden'] == 1)
				{
					$j++;
					continue;
				}

				$k = 0;
				$model_list = $this->epm_config_hardware_get_list_models($row2['id'], true);
				foreach($model_list as $row3)
				{
					//$row_out[$i]['products'][$j]['models'][$k] = $row3;
					$row_out[$i]['products'][$j]['models'][$k]['id'] = $row3['id'];
					$row_out[$i]['products'][$j]['models'][$k]['brand'] = $row3['brand'];
					$row_out[$i]['products'][$j]['models'][$k]['model'] = $row3['model'];
					$row_out[$i]['products'][$j]['models'][$k]['product_id'] = $row3['product_id'];
					$row_out[$i]['products'][$j]['models'][$k]['enabled'] = $row3['enabled'];
					$row_out[$i]['products'][$j]['models'][$k]['hidden'] = $row3['hidden'];
					$row_out[$i]['products'][$j]['models'][$k]['count'] = $k;
					$k++;
				}
				$j++;
			}
			$i++;
		}
		//echo "<textarea>" . print_r($row_out, true)  . "</textarea>";
		return $row_out;
	}


	//TODO: PENDIENTE ACTUALIZAR Y ELIMINAR DATOS NO NECESARIOS (TEMPLATES)
	//http://pbx.cerebelum.lan/admin/ajax.php?module=endpointman&module_sec=epm_config&module_tab=manager&command=list_all_brand
	public function epm_config_manager_hardware_get_list_all()
	{
		$row_out = array();
		$i = 0;
		$brand_list = $this->epm_config_hardware_get_list_brand(true, "name");
		//FIX: https://github.com/FreePBX-ContributedModules/endpointman/commit/2ad929d0b38f05c9da1b847426a4094c3314be3b

		foreach ($brand_list as $row)
		{
			$row_out[$i] = $row;
			$row_out[$i]['count'] = $i;
			$row_out[$i]['cfg_ver_datetime'] = $row['cfg_ver'];
			$row_out[$i]['cfg_ver_datetime_txt'] = date("c",$row['cfg_ver']);

			$row_mod = $this->epm->brandmanager->brand_update_check($row['directory']);
			$row_out[$i]['update'] = $row_mod['update'];
			if(isset($row_mod['update_vers'])) {
				$row_out[$i]['update_vers'] = $row_mod['update_vers'];
				$row_out[$i]['update_vers_txt'] = date("c",$row_mod['update_vers']);
			}

			if (! isset($row_out[$i]['update'])) 			{ $row_out[$i]['update'] = ""; }
			if (! isset($row_out[$i]['update_vers'])) 		{ $row_out[$i]['update_vers'] = $row_out[$i]['cfg_ver_datetime']; }
			if (! isset($row_out[$i]['update_vers_txt'])) 	{ $row_out[$i]['update_vers_txt'] = $row_out[$i]['cfg_ver_datetime_txt']; }

			if ($row['hidden'] == 1)
			{
				$i++;
				continue;
			}


			$j = 0;
			$product_list = $this->epm_config_hardware_get_list_product($row['id'], true);
			foreach($product_list as $row2) {
				$row_out[$i]['products'][$j] = $row2;
				if((array_key_exists('firmware_vers', $row2)) AND ($row2['firmware_vers'] > 0)) {
					$temp = $this->epm->brandmanager->firmware_update_check($row2['id']);
					$row_out[$i]['products'][$j]['update_fw'] = 1;
					// $row_out[$i]['products'][$j]['update_vers_fw'] = $temp['data']['firmware_ver'];
				} else {
					$row_out[$i]['products'][$j]['update_fw'] = 0;
					$row_out[$i]['products'][$j]['update_vers_fw'] = "";
				}
				if (! isset($row_out[$i]['products'][$j]['update_fw'])) 		{ $row_out[$i]['products'][$j]['update_fw'] = 0; }
				if (! isset($row_out[$i]['products'][$j]['update_vers_fw'])) 	{ $row_out[$i]['products'][$j]['update_vers_fw'] = ""; }


				$row_out[$i]['products'][$j]['fw_type'] = $this->epm->brandmanager->firmware_local_check($row2['id']);
				$row_out[$i]['products'][$j]['count'] = $j;
				if ($row2['hidden'] == 1)
				{
					$j++;
					continue;
				}

				$k = 0;
				$model_list = $this->epm_config_hardware_get_list_models($row2['id'], true);
				foreach($model_list as $row3)
				{
					$row_out[$i]['products'][$j]['models'][$k] = $row3;

					unset ($row_out[$i]['products'][$j]['models'][$k]['template_list']);
					unset ($row_out[$i]['products'][$j]['models'][$k]['template_data']);

					if($row_out[$i]['products'][$j]['models'][$k]['enabled']){
						$row_out[$i]['products'][$j]['models'][$k]['enabled_checked'] = 'checked';
					}
					$row_out[$i]['products'][$j]['models'][$k]['count'] = $k;
					$k++;
				}
				$j++;
			}


			$i++;
		}
		//echo "<textarea>".print_r($row_out,true)."</textarea>";
		return $row_out;
	}
}
