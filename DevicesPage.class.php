<?php
/**
 * Endpoint Manager Object Module - Sec Devices
 *
 * @author Javier Pastor
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */

namespace FreePBX\modules;

// Import the global classes. Without these imports, PHP looks for the names
// in FreePBX\modules. The iterators then fail for each family that copies
// files, and the catch blocks catch nothing.
use Exception;
use RecursiveArrayIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

require_once(__DIR__ . "/PageBase.class.php");

class DevicesPage extends \PageBase {
    // This class also holds the device methods of the old EndpointManager class.
    // The other methods of that class are in Endpointman, BrandManager,
    // SettingsPage and epm_system.
    public $eda;         //endpoint data abstraction layer
    public $system;      //FreePBX\modules\epm_system, from lib/
    public $tpl;         //Endpointman\View, owned by Endpointman
    public $global_cfg;  //global configuration options for Endpoint Manager
    public $error;       //error construct
    public $message;     //message construct

    public function __construct($epm) {
        parent::__construct($epm);
        $this->eda        = $epm->eda;
        $this->system     = $epm->system;
        $this->tpl        = $epm->tpl;
        $this->global_cfg = $this->eda->get_stored_globals();
        $this->error      = array();
        $this->message    = array();
    }
	public function showPage() {
		return load_view(__DIR__ . '/views/devices.php');
	}

	/**
	 * Allow only the commands that ajaxHandler() handles. Any other command
	 * has no query.
	 */
	public function ajaxRequest($req, &$setting) {
		$arrVal = array("model", "template", "mtemplate", "template2", "model_clone", "lines");
		if (in_array($req, $arrVal)) {
			$setting['authenticate'] = true;
			$setting['allowremote'] = false;
			return true;
		}
		return false;
	}

    public function ajaxHandler($command) {
		// switch ($command) {
		// }

		$endpoint = $this;

		$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : "";
		if(($id == "") OR ($id == "0")) {
			$out[0]['optionValue'] = "";
			$out[0]['optionDisplay'] = "";
			echo json_encode($out);
			die();
		}

		// $sql holds `?` placeholders and $sql_p holds the values for them.
		// epm_sql() binds them at the end of this method. Never put request data
		// into $sql.
		$sql_p = array($id);

		if($command == "model") {
			$sql = "SELECT * FROM endpointman_model_list WHERE enabled = 1 AND brand = ?";
		} elseif ($command == "template") {
			$sql = "SELECT id, name as model FROM  endpointman_template_list WHERE  product_id = ?";
		} elseif ($command == "mtemplate") {
			$sql = "SELECT id, name as model FROM  endpointman_template_list WHERE  model_id = ?";
		} elseif ($command == "template2") {
			$sql = "SELECT DISTINCT endpointman_template_list.id, endpointman_template_list.name as model FROM endpointman_template_list, endpointman_model_list, endpointman_product_list WHERE endpointman_template_list.product_id = endpointman_model_list.product_id AND endpointman_model_list.product_id = endpointman_product_list.id AND endpointman_model_list.id = ?";
		} elseif ($command == "model_clone") {
				$sql = "SELECT endpointman_model_list.id, endpointman_model_list.model as model FROM endpointman_model_list, endpointman_product_list WHERE endpointman_product_list.id = endpointman_model_list.product_id AND endpointman_model_list.enabled = 1 AND endpointman_model_list.hidden = 0 AND product_id = ?";
		} elseif ($command == "lines") {
			if(isset($_REQUEST['macid'])) {
				$sql = "SELECT endpointman_model_list.max_lines FROM endpointman_model_list,endpointman_line_list,endpointman_mac_list WHERE endpointman_mac_list.id = endpointman_line_list.mac_id AND endpointman_model_list.id = endpointman_mac_list.model AND endpointman_line_list.luid = ?";
				$sql_p = array($_REQUEST['macid']);
			} elseif(isset($_REQUEST['mac'])) {
				$macid = epm_sql("SELECT id FROM endpointman_mac_list WHERE mac = ?", 'getOne',
								 array($this->epm->_get_page('settings')->mac_check_clean($_REQUEST['mac'])));
				if($macid) {
					$_REQUEST['mac'] = $macid;
					$sql = "SELECT endpointman_model_list.max_lines FROM endpointman_model_list,endpointman_line_list,endpointman_mac_list WHERE endpointman_mac_list.id = endpointman_line_list.mac_id AND endpointman_model_list.id = endpointman_mac_list.model AND endpointman_mac_list.id = ?";
					$sql_p = array($macid);
				} else {
					unset($_REQUEST['mac']);
					$sql = "SELECT max_lines FROM endpointman_model_list WHERE id = ?";
				}
			} else {
				$sql = "SELECT max_lines FROM endpointman_model_list WHERE id = ?";
			}
		}

		if (($command == "template") OR ($command == "template2") OR ($command == "mtemplate")) {
			$out[0]['optionValue'] = 0;
			$out[0]['optionDisplay'] = _("Custom");
			$i=1;
		} elseif ($command == "model") {
			// An empty value, so that a required <select> refuses the blank row.
			$out[0]['optionValue'] = "";
			$out[0]['optionDisplay'] = "";
			$i=1;
		} else {
			$i=0;
		}

		if(($command == "lines") && (!isset($_REQUEST['mac'])) && (!isset($_REQUEST['macid']))) {
			$count = epm_sql($sql,'getOne',$sql_p);
			for($z=0;$z<$count;$z++) {
				$result[$z]['id'] = $z + 1;
				$result[$z]['model'] = $z + 1;
			}
		} elseif(isset($_REQUEST['macid'])) {
			$result = $endpoint->linesAvailable($_REQUEST['macid']);
		} elseif(isset($_REQUEST['mac'])) {
			$result = $endpoint->linesAvailable(NULL,$_REQUEST['mac']);
		} else {
			$result = epm_sql($sql,'getAll', $sql_p);
		}

		foreach($result as $row) {
				if((isset($_REQUEST['macid'])) OR (isset($_REQUEST['mac']))) {
					$out[$i]['optionValue'] = $row['value'];
					$out[$i]['optionDisplay'] = $row['text'];
				} else {
					$out[$i]['optionValue'] = $row['id'];
					$out[$i]['optionDisplay'] = $row['model'];
				}
			$i++;
		}

		return $out;
	}

	public function doConfigPageInit($module_tab = "", $command = "") {}

	public function getRightNav($request) {
		return "";
	}

	/**
	 * The form that views/devices.php shows: "add", "edit", or "" for the list.
	 *
	 * config.php draws the page before it asks for the action bar. The view
	 * sets this value, so the action bar follows the page that the view
	 * shows, and not the request. After Submit, the request still says
	 * "edit", but the view shows the list.
	 */
	public $form_mode = '';

	public function getActionBar($request) {
		if ($this->form_mode === '') {
			return "";
		}
		$buttons = array(
			'reset'  => array('name' => 'reset',  'id' => 'reset',  'value' => _('Reset')),
			'submit' => array('name' => 'submit', 'id' => 'submit', 'value' => _('Submit')),
		);
		if ($this->form_mode === 'edit') {
			$buttons['delete'] = array('name' => 'delete', 'id' => 'delete', 'value' => _('Delete'));
		}
		return $buttons;
	}

    function prepare_message_box() {
        $error_message = NULL;
        foreach ($this->error as $key => $error) {
            $error_message .= $error;
            if ($this->global_cfg['debug']) {
                $error_message .= " Function: [" . $key . "]";
            }
            $error_message .= "<br />";
        }
        $message = NULL;
        foreach ($this->message as $key => $error) {
            if (is_array($error)) {
                foreach ($error as $sub_error) {
                    $message .= $sub_error;
                    if ($this->global_cfg['debug']) {
                        $message .= " Function: [" . $key . "]";
                    }
                    $message .= "<br />";
                }
            } else {
                $message .= $error;
                if ($this->global_cfg['debug']) {
                    $message .= " Function: [" . $key . "]";
                }
                $message .= "<br />";
            }
        }

        if (isset($message)) {
            $this->display_message_box($message, 0);
        }

        if (isset($error_message)) {
            $this->display_message_box($error_message, 1);
        }
    }

    /**
     * Displays the message box on any page if triggered.
     * @param string $message The text of the message
     * @param string $tpl Not used at this time
     * @param int $fatal_error Either 0 or 1, 1 would be red, 0 would be black
     */

    function display_message_box($message, $fatal_error=0) {
        $this->tpl->assign("show_error_box", 1);
        $this->tpl->assign("advanced_debug", $this->global_cfg['debug']);
        $this->tpl->assign("fatal_error", $fatal_error);
        $this->tpl->assign("error_message", $message);
    }

    /**
     * Taken from PHP.net. A list of errors returned when uploading files.
     * @param <type> $error_code
     * @return string
     */

    function get_brand_from_mac($mac) {
        //Check for valid mac address first
        if (!$this->epm->_get_page('settings')->mac_check_clean($mac)) {
            return(FALSE);
        }

        //Get the OUI only
        $oui = substr($this->epm->_get_page('settings')->mac_check_clean($mac), 0, 6);
        //Find the matching brand model to the oui
        $oui_sql = 'SELECT endpointman_brand_list.name, endpointman_brand_list.id FROM endpointman_oui_list, endpointman_brand_list WHERE oui LIKE ? AND endpointman_brand_list.id = endpointman_oui_list.brand AND endpointman_brand_list.installed = 1 LIMIT 1';
        $oui_sql_p = array('%' . $oui . '%');
        $brand = epm_sql($oui_sql, 'getRow', $oui_sql_p);

        $res = epm_sql($oui_sql, 'query', $oui_sql_p);
        $brand_count = 1; //TODO review - was: "$brand_count = count($res);" - should be? "$brand_count = $res->numRows();"

        if (!$brand_count) {
            //oui doesn't have a matching mysql reference, probably a PC/router/wap/printer of some sort.
            $phone_info['id'] = 0;
            $phone_info['name'] = _("Unknown");
        } else {
            $phone_info['id'] = $brand['id'];
            $phone_info['name'] = $brand['name'];
        }

        return($phone_info);
    }

    function add_device($mac, $model, $ext, $template=NULL, $line=NULL, $displayname=NULL, $ipei=NULL) {
		// The CSV import gives $ipei. Of the forms, only the add form sends an
		// ipei field. "Add selected phones" does not send it.
		if ($ipei === NULL) {
			$ipei = (isset($_REQUEST['ipei']) && is_string($_REQUEST['ipei'])) ? $_REQUEST['ipei'] : '';
		}
        $mac = $this->epm->_get_page('settings')->mac_check_clean($mac);
        if ($mac) {
            if (empty($model)) {
                $this->error['add_device'] = _("Select a model.");
                return(FALSE);
            } elseif (empty($ext)) {
                $this->error['add_device'] = _("Select an extension.");
                return(FALSE);
            } else {
                if ($this->epm->brandmanager->sync_model($model)) {
                    $sql = 'SELECT id,template_id FROM endpointman_mac_list WHERE mac = ?';
                    $sql_p = array($mac);
                    $dup = epm_sql($sql, 'getRow', $sql_p);

                    if ($dup) {
                        if (!isset($template)) {
                            $template = $dup['template_id'];
                        }

                        $sql = 'UPDATE endpointman_mac_list SET model = ?, template_id =  ? WHERE id = ?';
                        $sql_p = array($model, $template, $dup['id']);
                        epm_sql($sql, 'query', $sql_p);
                        $return = $this->add_line($dup['id'], $line, $ext);
                        if ($return) {
                            return($return);
                        } else {
                            return(FALSE);
                        }
                    } else {
                        if (!isset($template)) {
                            $template = 0;
                        }

                        $sql = 'SELECT mac_id FROM endpointman_line_list WHERE ext = ?';
                        $sql_p = array($ext);
                        $used = epm_sql($sql, 'getOne', $sql_p);

                        if (($used) AND (!$this->global_cfg['show_all_registrations'])) {
                            $this->error['add_device'] = _("This extension is already on a different phone. To allow this, set Allow duplicate extensions on the Settings page.");
                            return(FALSE);
                        }

                        if (!isset($displayname)) {
                            $sql = 'SELECT description FROM devices WHERE id = ?';
                            $sql_p = array($ext);
                            $name = epm_sql($sql, 'getOne', $sql_p);
                        } else {
                            $name = $displayname;
                        }

                        $sql = 'SELECT endpointman_product_list. * , endpointman_model_list.template_data, endpointman_brand_list.directory FROM endpointman_model_list, endpointman_brand_list, endpointman_product_list WHERE endpointman_model_list.id =  ? AND endpointman_model_list.brand = endpointman_brand_list.id AND endpointman_model_list.product_id = endpointman_product_list.id';
                        $sql_p = array($model);

                        $row = epm_sql($sql, 'getRow', $sql_p);

                        $sql = 'INSERT INTO `endpointman_mac_list` (`mac`, `model`, `template_id`) VALUES (?, ?, ?)';
                        $sql_p = array($mac, $model, $template);
                        epm_sql($sql, 'query', $sql_p);

                        $sql = 'SELECT last_insert_id()';
                        $sql_p = array();
                        $ext_id = epm_sql($sql, 'getOne', $sql_p);

                        if (empty($line)) {
                            $line = 1;
                        }

                        $sql = 'INSERT INTO `endpointman_line_list` (`mac_id`, `ipei`, `ext`, `line`, `description`) VALUES (?, ?, ?, ?, ?)';
                        $sql_p = array($ext_id, $ipei, $ext, $line, $name);
                        epm_sql($sql, 'query', $sql_p);

                        $this->message['add_device'][] = sprintf(_("Added %s to line %s."), epm_esc($name), epm_esc($line));
                        return($ext_id);
                    }
                } else {
                    $this->error['Sync_Model'] = _("The model is not valid.");
                    return(FALSE);
                }
            }
        } else {
            $this->error['add_device'] = _("The MAC address is not valid.");
            return(FALSE);
        }
    }

    function add_line($mac_id, $line=NULL, $ext=NULL, $displayname=NULL) {
        if ((!isset($line)) AND (!isset($ext))) {
            if ($this->linesAvailable(NULL, $mac_id)) {
                if ($this->eda->all_unused_registrations()) {
                    $sql = 'SELECT * FROM endpointman_line_list WHERE mac_id = ?';
                    $sql_p = array($mac_id);
                    $lines_list = epm_sql($sql, 'getAll', $sql_p);

                    foreach ($lines_list as $row) {
                        $sql = 'SELECT description FROM devices WHERE id = ?';
                        $sql_p = array($row['ext']);
                        $name = epm_sql($sql, 'getOne', $sql_p);

                        $sql = 'UPDATE endpointman_line_list SET line = ?, ext = ?, description = ? WHERE luid =  ?';
                        $sql_p = array($row['line'], $row['ext'], $name, $row['luid']);
                        epm_sql($sql, 'query', $sql_p);
                    }

                    $reg = array_values($this->display_registration_list());
                    $lines = array_values($this->linesAvailable(NULL, $mac_id));

                    $sql = 'SELECT description FROM devices WHERE id = ?';
                    $sql_p = array($reg[0]['value']);
                    $name = epm_sql($sql, 'getOne', $sql_p);

                    $sql = 'INSERT INTO `endpointman_line_list` (`mac_id`, `ext`, `line`, `description`) VALUES (?, ?, ?, ?)';
                    $sql_p = array($mac_id, $reg[0]['value'], $lines[0]['value'], $name);
                    epm_sql($sql, 'query', $sql_p);

                    $this->message['add_line'] = sprintf(_("Added %s to line %s of the phone. Click Submit to write the config files."), "<i>" . epm_esc($name) . "</i>", "<i>" . epm_esc($lines[0]['value']) . "</i>");
                    return($mac_id);
                } else {
                    $this->error['add_line'] = _("No Devices/Extensions Left to Add") . "!";
                    return(FALSE);
                }
            } else {
                $this->error['add_line'] = _("No Lines Left to Add") . "!";
                return(FALSE);
            }
        } elseif ((!isset($line)) AND (isset($ext))) {
            if ($this->linesAvailable(NULL, $mac_id)) {
                if ($this->eda->all_unused_registrations()) {
                    $lines = array_values($this->linesAvailable(NULL, $mac_id));

                    $sql = 'INSERT INTO `endpointman_line_list` (`mac_id`, `ext`, `line`, `description`) VALUES (?, ?, ?, ?)';
                    $sql_p = array($mac_id, $ext, $lines[0]['value'], $displayname);
                    epm_sql($sql, 'query', $sql_p);

                    $this->message['add_line'] = sprintf(_("Added %s to line %s of the phone. Click Submit to write the config files."), "<i>" . epm_esc($name) . "</i>", "<i>" . epm_esc($lines[0]['value']) . "</i>");
                    return($mac_id);
                } else {
                    $this->error['add_line'] = _("No Devices/Extensions Left to Add") . "!";
                    return(FALSE);
                }
            } else {
                $this->error['add_line'] = _("No Lines Left to Add") . "!";
                return(FALSE);
            }
        } elseif ((isset($line)) AND (isset($ext))) {
            $sql = 'SELECT luid FROM endpointman_line_list WHERE line = ? AND mac_id = ?';
            $sql_p = array($line, $mac_id);
            $luid = epm_sql($sql, 'getOne', $sql_p);
            if ($luid) {
                $this->error['add_line'] = _("This line is already in use.");
                return(FALSE);
            } else {
                if (!isset($displayname)) {
                    $sql = 'SELECT description FROM devices WHERE id = ?';
                    $sql_p = array($ext);
                    $name = epm_sql($sql, 'getOne', $sql_p);
                } else {
                    $name = $displayname;
                }

                $sql = 'INSERT INTO `endpointman_line_list` (`mac_id`, `ext`, `line`, `description`) VALUES (?, ?, ?, ?)';
                $sql_p = array($mac_id, $ext, $line, $name);
                epm_sql($sql, 'query', $sql_p);
                $this->message['add_line'] .= sprintf(_("Added %s to line %s."), epm_esc($name), epm_esc($line)) . "<br/>";
                return($mac_id);
            }
        }
    }

    function update_device($macid, $model, $template, $luid=NULL, $name=NULL, $line=NULL, $update_lines=TRUE) {
        $sql = 'UPDATE endpointman_mac_list SET model = ?, template_id =  ? WHERE id = ?';
        $sql_p = array($model, $template, $macid);
        epm_sql($sql, 'query', $sql_p);

        if ($update_lines) {
            if (isset($luid)) {
                $this->update_line($luid, NULL, $name, $line);
                return(TRUE);
            } else {
                $this->update_line(NULL, $macid);
                return(TRUE);
            }
        }
    }

    function update_line($luid=NULL, $macid=NULL, $name=NULL, $line=NULL) {
        if (isset($luid)) {
            $sql = 'SELECT * FROM endpointman_line_list WHERE luid = ?';
            $sql_p = array($luid);
            $row = epm_sql($sql, 'getRow', $sql_p);

            if (!isset($name)) {
                $sql = 'SELECT description FROM devices WHERE id = ?';
                $sql_p = array($row['ext']);
                $name = epm_sql($sql, 'getOne', $sql_p);
            }

            if (!isset($line)) {
                $line = $row['line'];
            }
            $sql = 'UPDATE endpointman_line_list SET line = ?, ext = ?, description = ? WHERE luid =  ?';
            $sql_p = array($line, $row['ext'], $name, $row['luid']);
            epm_sql($sql, 'query', $sql_p);
            return(TRUE);
        } else {
            $sql = 'SELECT * FROM endpointman_line_list WHERE mac_id = ?';
            $sql_p = array($macid);
            $lines_info = epm_sql($sql, 'getAll', $sql_p);
            foreach ($lines_info as $row) {
                $sql = 'SELECT description FROM devices WHERE id = ?';
                $sql_p = array($row['ext']);
                $name = epm_sql($sql, 'getOne', $sql_p);

                $sql = 'UPDATE endpointman_line_list SET line = ?, ext = ?, description = ? WHERE luid =  ?';
                $sql_p = array($row['line'], $row['ext'], $name, $row['luid']);
                epm_sql($sql, 'query', $sql_p);
            }
            return(TRUE);
        }
    }

    /**
     * This will either a. delete said line or b. delete said device from line
     * @param <type> $line
     * @return <type>
     */

    function delete_line($lineid, $allow_device_remove=FALSE) {
        $sql = 'SELECT mac_id FROM endpointman_line_list WHERE luid = ?';
        $sql_p = array($lineid);
        $mac_id = epm_sql($sql, 'getOne', $sql_p);
        $row = $this->get_phone_info($mac_id);

        $sql = 'SELECT COUNT(*) FROM endpointman_line_list WHERE mac_id = ?';
        $sql_p = array($mac_id);
        $num_lines = epm_sql($sql, 'getOne', $sql_p);
        if ($num_lines > 1) {
            $sql = 'DELETE FROM endpointman_line_list WHERE luid=?';
            $sql_p = array($lineid);
            epm_sql($sql, 'query', $sql_p);
            $this->message['delete_line'] = _("The line is deleted.");
            return(TRUE);
        } else {
            if ($allow_device_remove) {
                $sql = 'DELETE FROM endpointman_line_list WHERE luid=?';
                $sql_p = array($lineid);
                epm_sql($sql, 'query', $sql_p);

                $sql = 'DELETE FROM endpointman_mac_list WHERE id=?';
                $sql_p = array($mac_id);
                epm_sql($sql, 'query', $sql_p);
                $this->message['delete_line'] = _("The line is deleted.");
                return(TRUE);
            } else {
                $this->error['delete_line'] = _("A phone must have one line. Delete the phone instead.");
                return(FALSE);
            }
        }
    }

    /**
     * Delete a phone and all of its lines.
     *
     * @param int $mac_id endpointman_mac_list.id
     * @return bool TRUE
     */
    function delete_device($mac_id) {
        $sql = 'DELETE FROM endpointman_line_list WHERE mac_id = ?';
        epm_sql($sql, 'query', array($mac_id));

        $sql = 'DELETE FROM endpointman_mac_list WHERE id = ?';
        epm_sql($sql, 'query', array($mac_id));
        return(TRUE);
    }

    function delete_device_by_mac($mac) {
        $sql = 'SELECT id FROM endpointman_mac_list WHERE mac = ?';
        $sql_p = array(str_replace(':', '', $mac));
        $mac_id = epm_sql($sql, 'getOne', $sql_p);

        if ($mac_id) {
          $this->delete_device($mac_id);
        }
        return(TRUE);
    }

    function retrieve_device_by_mac($mac) {
        $sql = 'SELECT id FROM endpointman_mac_list WHERE mac = ?';
        $sql_p = array(str_replace(':', '', $mac));
        $mac_id = epm_sql($sql, 'getOne', $sql_p);

        return($mac_id);
    }

    function retrieve_device_by_ext($ext) {
        $sql = 'SELECT DISTINCT mac_id FROM endpointman_line_list WHERE ext = ?';
        $sql_p = array($ext);
        $mac_id = epm_sql($sql, 'getOne', $sql_p);

        return($mac_id);
    }

    function get_message($function_name) {
        if (isset($this->message[$function_name])) {
            return($this->message[$function_name]);
        } else {
            return(_("Unknown message"));
        }
    }

    /**
     * Send this function an ID from the mac devices list table and you'll get all the information we have on that particular phone
     * @param integer $mac_id ID number reference from the MySQL database referencing the table endpointman_mac_list
     * @return array
     * @example
     * Final Output will look something similar to this
     *  Array
     *       (
     *            [config_files_override] =>
     *            [global_user_cfg_data] => N;
     *            [model_id] => 213
     *            [brand_id] => 2
     *            [name] => Grandstream
     *            [directory] => grandstream
     *            [model] => GXP2000
     *            [mac] => 000B820D0050
     *            [template_id] => 0
     *            [global_custom_cfg_data] => Serialized Data (Changed Template Values)
     *            [long_name] => GXP Enterprise IP series [280,1200,2000,2010,2020]
     *            [product_id] => 21
     *            [cfg_dir] => gxp
     *            [cfg_ver] => 1.5
     *            [template_data] => Serialized Data (The default Template Values)
     *            [enabled] => 1
     *            [line] => Array
     *                (
     *                    [1] => Array
     *                        (
     *                            [luid] => 2
     *                            [mac_id] => 2
     *                            [line] => 1
     *                            [ext] => 1000
     *                            [description] => Description
     *                            [custom_cfg_data] =>
     *                            [user_cfg_data] =>
     *                            [secret] => secret
     *                            [id] => 1000
     *                            [tech] => sip
     *                            [dial] => SIP/1000
     *                            [devicetype] => fixed
     *                            [user] => 1000
     *                            [emergency_cid] =>
     *                        )
     *                )
     *         )
     */




	 	//SIP EXTENSION SETTINGS get all Extensions and Settings

	public function getSipextensions(){

		$ret = array();

		$sql = "SELECT * FROM sip";
		$sql_p = array();
		$res = epm_sql($sql, 'getAll', $sql_p);

		if ($res) {


            if (!$res) {
                $this->error['getSipextensions'] = _("The database query failed.");
            }

		foreach ($res as $row) {
			$id=$row['id'];
			$keyword=$row['keyword'];
			$ret[$id . '-' . $keyword] = array(
			'keyword' => $row['keyword'],
			'data' => $row['data'],
			'flags' => $row['flags']

			);

                }
	}
		return $ret;

	}



	 //SIP  SETTINGS 2 get all SIP Settings Part 2

	public function getSipSettings2(){

		$ret = array();

		$sql = "SELECT * FROM kvstore_Sipsettings";
		$sql_p = array();
		$res = epm_sql($sql, 'getAll', $sql_p);

            if (!$res) {
                $this->error['getSipSettings2'] = _("The database query failed.");
            }
		if ($res) {

		foreach ($res as $row) {
			$key=$row['key'];
			$ret[$key] = array(
			'val' => $row['val'],
			'type' => $row['type']

			);


	}
	}
		return $ret;
	}






	//GET ALL FEATURE CODES

	public function getFeatureCodes(){

		$ret = array();

		$sql = "SELECT * FROM featurecodes";
		$sql_p = array();
		$res = epm_sql($sql, 'getAll', $sql_p);


		if ($res) {


            $featurecodes = $res;

            if (!$featurecodes) {
                $this->error['featurecodes'] = _("The database query failed.");
            }

		foreach ($featurecodes as $row) {
			$featurename=$row['featurename'];
			$ret[$featurename] = array(
			'modulename' => $row['modulename'],
			'featurename' => $row['featurename'],
			'description' => $row['description'],
			'helptext' => $row['helptext'],
			'defaultcode' => $row['defaultcode'],
			'customcode' => $row['customcode'],
			'enabled' => $row['enabled'],
			'providedest' => $row['providedest']

			);

		}


}

		return $ret;
	}






	 //SYSADMIN Settings

	public function getSysadminSettings(){
	//SYSADMIN IS A COMERCIAL MODULE - CHECK IF IT IS INSTALLED, ELSE THIS FEATURE WILL BE SKIPPED.
	// Declare $ret before the if. Sysadmin is a commercial module and is usually
	// absent. prepare_configs() reads four keys of $ret in eight places, so the
	// keys must exist when sysadmin is absent. A missing key or variable is a
	// PHP 8 warning, and FreePBX turns each warning into an exception.
	$ret = array(
		'provisuser' => array('value' => ''),
		'provispass' => array('value' => ''),
		'sslhpro'    => array('value' => ''),
		'hpro'       => array('value' => ''),
	);
	global $active_modules;
	if (!empty($active_modules['sysadmin']['rawname'])) {

		$sql = "SELECT * FROM sysadmin_options";
		$sql_p = array();
		$res = epm_sql($sql, 'getAll', $sql_p);

		if ($res) {


            if (!$res) {
                $this->error['sysadmin_options'] = _("The database query failed.");
            }

		foreach ($res as $row) {
			$key=$row['key'];
			$ret[$key] = array(
			'value' => $row['value']
		);
	}
}

}

		return $ret;
	}

    function get_phone_info($mac_id=NULL) {


			$sipextensions=$this->getSipextensions();
			$SipSettings2=$this->getSipSettings2();
			$featurecodes=$this->getFeatureCodes();
			$getSysadminSettings=$this->getSysadminSettings();









        //You could screw up a phone if the mac_id is blank
        if (!isset($mac_id)) {
            $this->error['get_phone_info'] = _("No phone is selected.");
            return(FALSE);
        }
        $sql = 'SELECT id FROM endpointman_mac_list WHERE model > 0 AND id =?';
        $sql_p = array($mac_id);

        $res = epm_sql($sql, 'query', $sql_p);

        if ($res) {
            //Returns Brand Name, Brand Directory, Model Name, Mac Address, Extension (FreePBX), Custom Configuration Template, Custom Configuration Data, Product Name, Product ID, Product Configuration Directory, Product Configuration Version, Product XML name,
            $sql = 'SELECT endpointman_mac_list.specific_settings, endpointman_mac_list.config_files_override, endpointman_mac_list.global_user_cfg_data, endpointman_model_list.id as model_id, endpointman_brand_list.id as brand_id, endpointman_brand_list.name, endpointman_brand_list.directory, endpointman_model_list.model, endpointman_mac_list.mac, endpointman_mac_list.template_id, endpointman_mac_list.global_custom_cfg_data, endpointman_product_list.long_name, endpointman_product_list.id as product_id, endpointman_product_list.cfg_dir, endpointman_product_list.cfg_ver, endpointman_model_list.template_data, endpointman_model_list.enabled, endpointman_mac_list.global_settings_override FROM endpointman_line_list, endpointman_mac_list, endpointman_model_list, endpointman_brand_list, endpointman_product_list WHERE endpointman_mac_list.model = endpointman_model_list.id AND endpointman_brand_list.id = endpointman_model_list.brand AND endpointman_product_list.id = endpointman_model_list.product_id AND endpointman_mac_list.id = endpointman_line_list.mac_id AND endpointman_mac_list.id = ?';
            $sql_p = array($mac_id);

            $phone_info = epm_sql($sql, 'getRow', $sql_p);

            if (!$phone_info) {
                $this->error['get_phone_info'] = _("The database query failed.");
            }

            //If there is a template associated with this phone then pull that information and put it into the array
            if ($phone_info['template_id'] > 0) {
                $sql = 'SELECT name, global_custom_cfg_data, config_files_override, global_settings_override FROM endpointman_template_list WHERE id = ?';
                $sql_p = array($phone_info['template_id']);

                $phone_info['template_data_info'] = epm_sql($sql, 'getRow', $sql_p);
            }

            $sql = 'SELECT endpointman_line_list.*, sip.data as secret, devices.*, endpointman_line_list.description AS epm_description FROM endpointman_line_list, sip, devices WHERE endpointman_line_list.ext = devices.id AND endpointman_line_list.ext = sip.id AND sip.keyword = \'secret\' AND mac_id = ? ORDER BY endpointman_line_list.line ASC';
            $sql_p = array($mac_id);
            $lines_info = epm_sql($sql, 'getAll', $sql_p);
            foreach ($lines_info as $line) {













                $phone_info['line'][$line['line']] = $line;
                $phone_info['line'][$line['line']]['description'] = $line['epm_description'];
				$phone_info['line'][$line['line']]['extension'] = $line['ext'];
				$phone_info['line'][$line['line']]['user_extension'] = $line['user'];
				// The `sip` table holds only the keywords that the extension has. A missing
				// keyword is normal, so read it with a default.
				$ext_kw = $phone_info['line'][$line['line']]['extension'] . '-';
				$phone_info['line'][$line['line']]['allowedcodec'] = $sipextensions[$ext_kw . 'allow']['data'] ?? '';
				$phone_info['line'][$line['line']]['forcerport'] = $sipextensions[$ext_kw . 'force_rport']['data'] ?? '';
				$phone_info['line'][$line['line']]['media_encryption'] = $sipextensions[$ext_kw . 'media_encryption']['data'] ?? '';
				$phone_info['line'][$line['line']]['sipdriver'] = $sipextensions[$ext_kw . 'sipdriver']['data'] ?? '';
				$phone_info['line'][$line['line']]['transport'] = $sipextensions[$ext_kw . 'transport']['data'] ?? '';
				$phone_info['line'][$line['line']]['trustrpid'] = $sipextensions[$ext_kw . 'trustrpid']['data'] ?? '';
				$phone_info['line'][$line['line']]['callerid'] = $sipextensions[$ext_kw . 'callerid']['data'] ?? '';
				$phone_info['line'][$line['line']]['encryption'] = $sipextensions[$ext_kw . 'encryption']['data'] ?? '';
				$phone_info['line'][$line['line']]['timestamp']=time();


// Find the SIP port. Asterisk 21 has no chan_sip, so each extension uses
// chan_pjsip. The port comes from the Sipsettings KVStore. Its keys have the
// form "<proto>port-<bind address>".
//
// FreePBX stores the transport of an extension as "<bind address>-<proto>".
// The value is empty for the UDP default. The key comes from this value, so
// each bind address that the admin sets works.
	$transport = (string) $phone_info['line'][$line['line']]['transport'];
	$sep   = strrpos($transport, '-');
	$bind  = ($transport === '' || $sep === false) ? '0.0.0.0' : substr($transport, 0, $sep);
	$proto = ($transport === '' || $sep === false) ? 'udp'     : substr($transport, $sep + 1);
	if (! in_array($proto, array('udp', 'tcp', 'tls'), true)) {
		$proto = 'udp';
		$bind  = '0.0.0.0';
	}
	// If Sipsettings has no port for the bind address of the extension, use the
	// wildcard bind. Then server_port always has a value.
	$port_key = $proto . 'port-' . $bind;
	if (! isset($SipSettings2[$port_key]['val'])) {
		$port_key = $proto . 'port-0.0.0.0';
	}
	$phone_info['line'][$line['line']]['proto'] = $proto;
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings2[$port_key]['val'] ?? '';




            }
			//print_r ($phone_info);
        } else {
            $sql = 'SELECT id, mac FROM endpointman_mac_list WHERE id =?';
            $sql_p = array($mac_id);
            //Phone is unknown, we need to display this to the end user so that they can make corrections
            $row = epm_sql($sql, 'getRow', $sql_p);

            $brand = $this->get_brand_from_mac($row['mac']);
            if ($brand) {
                $phone_info['brand_id'] = $brand['id'];
                $phone_info['name'] = $brand['name'];
            } else {
                $phone_info['brand_id'] = 0;
                $phone_info['name'] = 'Unknown';
            }

            $phone_info['id'] = $mac_id;
            $phone_info['model_id'] = 0;
            $phone_info['product_id'] = 0;
            $phone_info['custom_cfg_template'] = 0;
            $phone_info['mac'] = $row['mac'];
            $sql = 'SELECT endpointman_line_list.*, sip.data as secret, devices.* FROM endpointman_line_list, sip, devices WHERE endpointman_line_list.ext = devices.id AND endpointman_line_list.ext = sip.id AND sip.keyword = \'secret\' AND mac_id = ?';
            $sql_p = array($mac_id);
            $lines_info = epm_sql($sql, 'getAll', $sql_p);

            foreach ($lines_info as $line) {
                $phone_info['line'][$line['line']] = $line;

            }
        }

        return $phone_info;
    }

    /**
     * Custom Means specific to that MAC
     * id is either the mac ID (not address) or the template ID
     * @param integer $id
     * @param integer $custom
     */

    /**
     * Get the extra MAC addresses that the template of a device links.
     *
     * Multicell base stations, for example the Snom M700, provision each unit
     * from one file. Each MAC gets a symlink to that file. The list is an
     * optional template field with comma-separated values. Most devices do not
     * have it.
     *
     * @param mixed $cfg_data an unserialized global_custom_cfg_data blob
     * @return array MAC addresses, empty when the field is not set
     */
    private function additional_macs($cfg_data) {
        if (!is_array($cfg_data) || empty($cfg_data['data']['additionalmacs'])) {
            return array();
        }
        return array_filter(array_map('trim', explode(',', $cfg_data['data']['additionalmacs'])));
    }

    function write_configs($provisioner_lib, $reboot, $write_path, $phone_info, $returned_data) {

        //Create Directory Structure (If needed)
        if (isset($provisioner_lib->directory_structure)) {
            foreach ($provisioner_lib->directory_structure as $data) {
                if (file_exists($this->PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/" . $data)) {
                    $dir_iterator = new RecursiveDirectoryIterator($this->PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/" . $data . "/");
                    $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);
                    // could use CHILD_FIRST if you so wish
                    foreach ($iterator as $file) {
                        $dir = $write_path . str_replace($this->PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/", "", dirname($file));
                        if (!file_exists($dir)) {
                            if (!@mkdir($dir, 0775, TRUE)) {
                                $this->error['parse_configs'] = sprintf(_("Cannot make the directory %s."), epm_esc($data));
                                return(FALSE);
                            }
                        }
                    }
                } else {
                    $dir = $write_path . $data;
                    if (!file_exists($dir)) {
                        if (!@mkdir($dir, 0775)) {
                            $this->error['parse_configs'] = sprintf(_("Cannot make the directory %s."), epm_esc($data));
                            return(FALSE);
                        }
                    }
                }
            }
        }

        //Copy Files (If needed)
        if (isset($provisioner_lib->copy_files)) {
            foreach ($provisioner_lib->copy_files as $data) {
                if (file_exists($this->PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/" . $data)) {
                    $file = $write_path . $data;
                    $orig = $this->PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/" . $data;
                    if (!file_exists($file)) {
                        if (!@copy($orig, $file)) {
                            $this->error['parse_configs'] = sprintf(_("Cannot make the file %s."), epm_esc($data));
                            return(FALSE);
                        }
                    } else {
                        if (file_exists($this->PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/" . $data)) {
                            if (!file_exists(dirname($write_path . $data))) {
                                !@mkdir(dirname($write_path . $data), 0775);
                            }
                            copy($this->PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/" . $data, $write_path . $data);
                            chmod($write_path . $data, 0775);
                        }
                    }
                }
            }
        }

        foreach ($returned_data as $file => $data) {
            if (((file_exists($write_path . $file)) AND (is_writable($write_path . $file)) AND (!in_array($file, $provisioner_lib->protected_files))) OR (!file_exists($write_path . $file))) {
                //Move old file to backup
                if (!$this->global_cfg['backup_check']) {
                    if (!file_exists($write_path . 'config_bkup')) {
                        if (!@mkdir($write_path . 'config_bkup', 0775)) {
                            $this->error['parse_configs'] = _("Cannot make the backup directory.");
                            return(FALSE);
                        }
                    }
                    if (file_exists($write_path . $file)) {
                        if (!file_exists(dirname($write_path . 'config_bkup/' . $file))) {
                            mkdir(dirname($write_path . 'config_bkup/' . $file));
                        }

                        copy($write_path . $file, $write_path . 'config_bkup/' . $file . '.' . time());
                    }
                }





				//print_r ($additionalmac);
				//print_r ($file2);
				file_put_contents($write_path . $file, $data);

                chmod($write_path . $file, 0775);



	//Add Additional MAC File Symlinks to enable autoprovisioning for Multicell Devices like Snom and much more devices
				//$additionalmacs should be added as Input field in the Template JSON File and MAC Adresses should be inserted comma "," separated.
				// additionalmacs is an optional template field. Usually both blobs and the
				// key in them are absent, so read them with defaults. FreePBX turns each
				// warning into an exception.
				$globalcfgdata = epm_blob_decode($phone_info['global_custom_cfg_data'], array());
				$globalcfgdata_cs = epm_blob_decode($phone_info['template_data_info']['global_custom_cfg_data'] ?? null, array());

				$additionalmacs = $this->additional_macs($globalcfgdata);
				$additionalmacs_cs = $this->additional_macs($globalcfgdata_cs);

				$filelist = scandir($write_path);

			//Now we remove symlinks for your device and rebuild all symlinks you have entered in global Settings input area $additionalmacs in your template settings.
				foreach($filelist as $filesymlinkcheck) {
					if (is_link($write_path . $filesymlinkcheck)) {
						if (readlink($write_path . $filesymlinkcheck) == $write_path . $file){
							unlink($write_path . $filesymlinkcheck);
						}
			}
			}

				foreach($additionalmacs as $additionalmac) {
				if ($additionalmac !== ""){
						if (strpos($file, '_additional') !== false) {
					$filesymlink = str_replace($phone_info['mac'],$additionalmac,$file);
						$filesymlink = str_replace("_additional", "", "$filesymlink");
						if (!file_exists($write_path . $filesymlink)) {


				symlink($write_path . $file, $write_path . $filesymlink);
						}
					}
				}
				}
				foreach($additionalmacs_cs as $additionalmac) {
				if ($additionalmac !== ""){
						if (strpos($file, '_additional') !== false) {
					$filesymlink = str_replace($phone_info['mac'],$additionalmac,$file);
						$filesymlink = str_replace("_additional", "", "$filesymlink");
						if (!file_exists($write_path . $filesymlink)) {


				symlink($write_path . $file, $write_path . $filesymlink);
						}
					}
				}
				}

	//END OF SYMLINK SECTION


                if (!file_exists($write_path . $file)) {
                    $this->error['parse_configs'] = sprintf(_("Cannot write the file %s."), epm_esc($file));
                    return(FALSE);
                }
            } elseif (!in_array($file, $provisioner_lib->protected_files)) {
                $this->error['parse_configs'] = _("Cannot write the config file.");
                return(FALSE);
            }
        }

        if ($reboot) {
            $this->attach_reboot_context($provisioner_lib);
            $provisioner_lib->reboot();
        }
    }

    function prepare_configs($phone_info, $reboot=TRUE, $write=TRUE) {

        // Define the constant only once. prepare_configs() runs once for each device
        // during a rebuild, and a second define() is a PHP 8 warning.
        if (! defined('PROVISIONER_BASE')) {
            define('PROVISIONER_BASE', $this->PHONE_MODULES_PATH);
        }

        // The drivers ship with the module in lib/provisioner/. Only their data comes
        // from the package server, into _ep_phone_modules/. See
        // $provisioner_lib->root_dir below and lib/provisioner/README.md.
        $autoloader = $this->LOCAL_PATH . 'lib/provisioner/autoload.php';
        if (file_exists($autoloader)) {
            if (!class_exists('ProvisionerConfig')) {
                require($autoloader);
            }

            //Load Provisioner
            $class = "endpoint_" . $phone_info['directory'] . "_" . $phone_info['cfg_dir'] . '_phone';
            $base_class = "endpoint_" . $phone_info['directory'] . '_base';
            $master_class = "endpoint_base";
            if (!class_exists($master_class)) {
                ProvisionerConfig::endpointsAutoload($master_class);
            }
            if (!class_exists($base_class)) {
                ProvisionerConfig::endpointsAutoload($base_class);
            }
            if (!class_exists($class)) {
                ProvisionerConfig::endpointsAutoload($class);
            }

            if (class_exists($class)) {
                $provisioner_lib = new $class();

                // Find out if the device overrides the global settings.
                //
                // The override columns can be NULL, and they are empty on most devices. An
                // empty blob decodes to false, so isset() is not a sufficient test. Use a
                // fallback for each key. Without it, config_location is null and
                // write_configs() calls scandir(''), which fails on PHP 8.
                $override = $phone_info['template_id'] > 0
                    ? epm_blob_decode($phone_info['template_data_info']['global_settings_override'] ?? null, array())
                    : epm_blob_decode($phone_info['global_settings_override'] ?? null, array());
                if (!is_array($override)) {
                    $override = array();
                }
                $settings = array();
                foreach (array('srvip', 'ntp', 'config_location', 'tz') as $setting) {
                    $settings[$setting] = (isset($override[$setting]) && $override[$setting] !== '')
                        ? $override[$setting]
                        : $this->global_cfg[$setting];
                }
                // $settings is on the left. On a key collision, `+` keeps the left value.
                // The four resolved values win, and the other override keys stay.
                $settings = $settings + $override;



                //Tell the system who we are and were to find the data.
                $provisioner_lib->root_dir = $this->PHONE_MODULES_PATH;
                $provisioner_lib->engine = 'asterisk';
                $provisioner_lib->engine_location = !empty($this->global_cfg['asterisk_location']) ? $this->global_cfg['asterisk_location'] : 'asterisk';
                $provisioner_lib->system = 'unix';

                // Only the Aastra driver has these properties. The driver checks the path
                // again before it runs the program.
                if (property_exists($provisioner_lib, 'anacrypt_location')) {
                    $provisioner_lib->anacrypt_location = (string) $this->global_cfg['anacrypt_location'];
                    $provisioner_lib->anacrypt_key = (string) $this->global_cfg['anacrypt_key'];
                }

                //have to because of versions less than php5.3
                $provisioner_lib->brand_name = $phone_info['directory'];
                $provisioner_lib->family_line = $phone_info['cfg_dir'];



                //Phone Model (Please reference family_data.xml in the family directory for a list of recognized models)
                //This has to match word for word. I really need to fix this....
                $provisioner_lib->model = $phone_info['model'];

                //Timezone
                try {
                    $provisioner_lib->DateTimeZone = new \DateTimeZone($settings['tz']);
                } catch (Exception $e) {
                    $this->error['parse_configs'] = sprintf(_("The time zone is not valid: %s"), epm_esc($e->getMessage()));
                    return(FALSE);
                }

                $temp = "";
                $template_data = epm_blob_decode($phone_info['template_data']);
                $global_user_cfg_data = epm_blob_decode($phone_info['global_user_cfg_data']);
                if ($phone_info['template_id'] > 0) {
                    $global_custom_cfg_data = epm_blob_decode($phone_info['template_data_info']['global_custom_cfg_data']);
                    //Provide alternate Configuration file instead of the one from the hard drive
                    if (!empty($phone_info['template_data_info']['config_files_override'])) {
                        $temp = epm_blob_decode($phone_info['template_data_info']['config_files_override']);
                        foreach ($temp as $list) {
                            $sql = 'SELECT original_name,data FROM endpointman_custom_configs WHERE id = ?';
                            $sql_p = array($list);
                            $res = epm_sql($sql, 'query', $sql_p);
                            if ($res) {
                                $data = epm_sql($sql, 'getRow', $sql_p);
                                $provisioner_lib->config_files_override[$data['original_name']] = $data['data'];
                            }
                        }
                    }
                } else {
                    $global_custom_cfg_data = epm_blob_decode($phone_info['global_custom_cfg_data']);
                    //Provide alternate Configuration file instead of the one from the hard drive
                    if (!empty($phone_info['config_files_override'])) {
                        $temp = epm_blob_decode($phone_info['config_files_override']);
                        foreach ($temp as $list) {
                            $sql = 'SELECT original_name,data FROM endpointman_custom_configs WHERE id = ?';
                            $sql_p = array($list);
                            $res = epm_sql($sql, 'query', $sql_p);
                            if ($res) {
                                $data = epm_sql($sql, 'getRow', $sql_p);
                                $provisioner_lib->config_files_override[$data['original_name']] = $data['data'];
                            }
                        }
                    }
                }

                // Read only the 'data' half of this blob. The 'ari' half was for the old ARI
                // user portal, which the module does not have.
                if (!empty($global_custom_cfg_data)) {
                    if (array_key_exists('data', $global_custom_cfg_data)) {
                        $global_custom_cfg_data = $global_custom_cfg_data['data'];
                    } else {
                        $global_custom_cfg_data = array();
                    }
                }

                $new_template_data = array();
                $line_ops = array();
                if (is_array($global_custom_cfg_data)) {
                    foreach ($global_custom_cfg_data as $key => $data) {
                        //TODO: clean up with reg-exp
                        $full_key = $key;
                        $key = explode('|', $key);
                        $count = count($key);
                        switch ($count) {
                            case 1:
                                $new_template_data[$full_key] = $global_custom_cfg_data[$full_key];
                                break;
                            case 2:
                                $breaks = explode('_', $key[1]);
                                $new_template_data['loops'][$breaks[0]][$breaks[2]][$breaks[1]] = $global_custom_cfg_data[$full_key];
                                break;
                            case 3:
                                $line_ops[$key[1]][$key[2]] = $global_custom_cfg_data[$full_key];
                                break;
                        }
                    }
                }

                if (!$write) {
                    $new_template_data['provision']['type'] = 'dynamic';
                    $new_template_data['provision']['protocol'] = 'http';
                    // fwconsole has no REQUEST_URI, and dirname(null) is deprecated in PHP 8.1.
                    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
                    $new_template_data['provision']['path'] = rtrim($settings['srvip'] . dirname($request_uri) . '/', '/');
                    $new_template_data['provision']['encryption'] = FALSE;
                } else {
                    $new_template_data['provision']['type'] = 'file';
                    $new_template_data['provision']['protocol'] = 'tftp';
                    $new_template_data['provision']['path'] = $settings['srvip'];
                    $new_template_data['provision']['encryption'] = FALSE;
                }

                $new_template_data['ntp'] = $settings['ntp'];

                //Overwrite all specific settings variables now
                if (!empty($phone_info['specific_settings'])) {
                    $specific_settings = epm_blob_decode($phone_info['specific_settings']);
                    $specific_settings = is_array($specific_settings) ? $specific_settings : array();
                } else {
                    $specific_settings = array();
                }

                //Set Variables according to the template_data files included. We can include different template.xml files within family_data.xml also one can create
                //template_data_custom.xml which will get included or template_data_<model_name>_custom.xml which will also get included
                //line 'global' will set variables that aren't line dependant


                $provisioner_lib->settings = $new_template_data;



//LOAD FEATURECODES
	$featurecodes=$this->getFeatureCodes();
	$getSysadminSettings=$this->getSysadminSettings();




//Calculate Voicemail Number
	if (!$featurecodes['myvoicemail']['customcode']) {
		$provisioner_lib->settings['myvoicemail'] = $featurecodes['myvoicemail']['defaultcode'];
	}
	else  {
		$provisioner_lib->settings['myvoicemail'] = $featurecodes['myvoicemail']['customcode'];
	}
//Calculate DND_ON_NUMBER
	if (!$featurecodes['dnd_on']['customcode']) {
		$provisioner_lib->settings['dnd_on'] = $featurecodes['dnd_on']['defaultcode'];
	}
	else  {
		$provisioner_lib->settings['dnd_on'] = $featurecodes['dnd_on']['customcode'];
	}
//Calculate DND_OFF_NUMBER
	if (!$featurecodes['dnd_off']['customcode']) {
		$provisioner_lib->settings['dnd_off'] = $featurecodes['dnd_off']['defaultcode'];
	}
	else  {
		$provisioner_lib->settings['dnd_off'] = $featurecodes['dnd_off']['customcode'];
	}
//Calculate DND_TOGGLE_NUMBER
	if (!$featurecodes['dnd_toggle']['customcode']) {
		$provisioner_lib->settings['dnd_toggle'] = $featurecodes['dnd_toggle']['defaultcode'];
	}
	else  {
		$provisioner_lib->settings['dnd_toggle'] = $featurecodes['dnd_toggle']['customcode'];
	}





                //Loop through Lines!
                $li = 0;
                foreach ($phone_info['line'] as $line) {
                    // $line_ops has an entry only for lines with per-line overrides. Most devices
                    // have none.
                    $line_options = isset($line_ops[$line['line']]) && is_array($line_ops[$line['line']]) ? $line_ops[$line['line']] : array();
                    $line_statics = array(
					'line' => $line['line'],
					'username' => $line['ext'],
					'authname' => $line['ext'],
					'secret' => $line['secret'],
					'displayname' => $line['description'],
					'server_host' => $settings['srvip'],
					'server_port' => $line['server_port'],
					'proto' => $line['proto'],
					'user_extension' => $line['user_extension'],
					'extension' => $line['ext'],
					'allowedcodec' => $line['allowedcodec'],
					'forcerport' => $line['forcerport'],
					'media_encryption' => $line['media_encryption'],
					'sipdriver' => $line['sipdriver'],
					'transport' => $line['transport'],
					'trustrpid' => $line['trustrpid'],
					'callerid' => $line['callerid'],
					'encryption' => $line['encryption'],
					'tech' => $line['tech'],
					'ipei' => $line['ipei'],
					'myvoicemail' => $provisioner_lib->settings['myvoicemail'],
					'dnd_on' => $provisioner_lib->settings['dnd_on'],
					'dnd_off' => $provisioner_lib->settings['dnd_off'],
					'dnd_toggle' => $provisioner_lib->settings['dnd_toggle'],
					'primtimeserver' => $settings['ntp'],
					'globaladminpassword' => $this->global_cfg['adminpass'],
					'globaluserpassword' => $this->global_cfg['userpass'],
					'provisuser' => $getSysadminSettings['provisuser']['value'],
					'provispass' => $getSysadminSettings['provispass']['value'],
					'sslhpro' => $getSysadminSettings['sslhpro']['value'],
					'hpro' => $getSysadminSettings['hpro']['value']
					);




					//STATIC LINE SETTINGS WITHOUT LOOP (accXSetting)

					$provisioner_lib->settings['acc' . $line['line'] . 'secret'] = $line['secret'];
					$provisioner_lib->settings['acc' . $line['line'] . 'displayname'] = $line['description'];
					$provisioner_lib->settings['acc' . $line['line'] . 'user_extension'] = $line['user_extension'];
					$provisioner_lib->settings['acc' . $line['line'] . 'username'] = $line['ext'];
					$provisioner_lib->settings['acc' . $line['line'] . 'authname'] = $line['ext'];
					$provisioner_lib->settings['acc' . $line['line'] . 'extension'] = $line['extension'];
					$provisioner_lib->settings['acc' . $line['line'] . 'allowedcodec'] = $line['allowedcodec'];
					$provisioner_lib->settings['acc' . $line['line'] . 'forcerport'] = $line['forcerport'];
					$provisioner_lib->settings['acc' . $line['line'] . 'media_encryption'] = $line['media_encryption'];
					$provisioner_lib->settings['acc' . $line['line'] . 'sipdriver'] = $line['sipdriver'];
					$provisioner_lib->settings['acc' . $line['line'] . 'transport'] = $line['transport'];
					$provisioner_lib->settings['acc' . $line['line'] . 'trustrpid'] = $line['trustrpid'];
					$provisioner_lib->settings['acc' . $line['line'] . 'callerid'] = $line['callerid'];
					$provisioner_lib->settings['acc' . $line['line'] . 'encryption'] = $line['encryption'];
					$provisioner_lib->settings['acc' . $line['line'] . 'server_port'] = $line['server_port'];
					$provisioner_lib->settings['acc' . $line['line'] . 'ipei'] = $line['ipei'];
					$provisioner_lib->settings['server_host'] = $settings['srvip'];
					$provisioner_lib->settings['primtimeserver'] = $settings['ntp'];
					$provisioner_lib->settings['globaladminpassword'] = $this->global_cfg['adminpass'];
					$provisioner_lib->settings['globaluserpassword'] = $this->global_cfg['userpass'];
					$provisioner_lib->settings['provisuser'] = $getSysadminSettings['provisuser']['value'];
					$provisioner_lib->settings['provispass'] = $getSysadminSettings['provispass']['value'];
					$provisioner_lib->settings['sslhpro'] = $getSysadminSettings['sslhpro']['value'];
					$provisioner_lib->settings['hpro'] = $getSysadminSettings['hpro']['value'];

					$provisioner_lib->settings['timestamp'] = time();



				//THIS ARE EXTRA VALUES FOR GRANDSTREAM

					//Add loop Values for Grandstream (Tested with Analog HT8XX Adapter)

						//PROTO UPD/TCP/TLS
						if ($line['proto'] == "tcp") {
							$line_statics['gsproto'] = '1';
						}
						if ($line['proto'] == "udp") {
							$line_statics['gsproto'] = '0';
						}
						if ($line['proto'] == "tls") {
							$line_statics['gsproto'] = '2';
						}


						//GRANDSTREAM SRTP YES/NO

						if ($line['encryption'] == "yes" || $line['media_encryption'] == "sdes" ) {
							$line_statics['gsSRTP'] = '1';
						}
						if ($line['encryption'] == "no" || $line['media_encryption'] == "no" ) {
							$line_statics['gsSRTP'] = '0';
						}


					//Add static Values for Grandstream (Tested with Analog HT8XX Adapter)

						$provisioner_lib->settings['acc' . $line['line'] . 'gsproto'] = $line_statics['gsproto'];
						$provisioner_lib->settings['acc' . $line['line'] . 'gsSRTP'] = $line_statics['gsSRTP'];

				//THIS ARE EXTRA VALUES FOR YEALINK

					//Add loop Values for Yealink

						//PROTO UPD/TCP/TLS
						if ($line['proto'] == "tcp") {
							$line_statics['yealinktransport'] = '1';
						}
						if ($line['proto'] == "udp") {
							$line_statics['yealinktransport'] = '0';
						}
						if ($line['proto'] == "tls") {
							$line_statics['yealinktransport'] = '2';
						}


						//Yealink SRTP YES/NO

						if ($line['encryption'] == "yes" || $line['media_encryption'] == "sdes" ) {
							$line_statics['yealinksrtp'] = '2';
						}
						if ($line['encryption'] == "no" || $line['media_encryption'] == "no" ) {
							$line_statics['yealinksrtp'] = '0';
						}


					//Add static Values for Grandstream (Tested with Analog HT8XX Adapter)

						$provisioner_lib->settings['acc' . $line['line'] . 'yealinktransport'] = $line_statics['gsproto'];
						$provisioner_lib->settings['acc' . $line['line'] . 'yealinksrtp'] = $line_statics['gsSRTP'];



			//MERGE ARRAYS



                    $provisioner_lib->settings['line'][$li] = array_merge($line_options, $line_statics);
                    $li++;
                }

                if (array_key_exists('data', $specific_settings)) {
                   foreach ($specific_settings['data'] as $key => $data) {
                       $default_exp = preg_split("/\|/i", $key);
                        if (isset($default_exp[2])) {
                            //lineloop
                            $var = $default_exp[2];
                            $line = $default_exp[1];
                            $loc = $this->system->arraysearchrecursive($line, $provisioner_lib->settings['line'], 'line');
                            if ($loc !== FALSE) {
                                $k = $loc[0];
                                $provisioner_lib->settings['line'][$k][$var] = $data;
                            } else {
                                //Adding a new line-ish type options
                                if (isset($specific_settings['data']['line|' . $line . '|line_enabled'])) {
                                    $lastkey = array_key_last($provisioner_lib->settings['line']);
                                    $lastkey++;
                                    $provisioner_lib->settings['line'][$lastkey]['line'] = $line;
                                    $provisioner_lib->settings['line'][$lastkey][$var] = $data;
                                }
                            }
                        } else {
                            switch ($key) {
                                case "connection_type":
                                    $provisioner_lib->settings['network'][$key] = $data;
                                    break;
                                case "ip4_address":
                                    $provisioner_lib->settings['network']['ipv4'] = $data;
                                    break;
                                case "ip6_address":
                                    $provisioner_lib->settings['network']['ipv6'] = $data;
                                    break;
                                case "subnet_mask":
                                    $provisioner_lib->settings['network']['subnet'] = $data;
                                    break;
                                case "gateway_address":
                                    $provisioner_lib->settings['network']['gateway'] = $data;
                                    break;
                                case "primary_dns":
                                    $provisioner_lib->settings['network'][$key] = $data;
                                    break;
                                default:
                                    $provisioner_lib->settings[$key] = $data;
                                    break;
                            }
                        }
                    }
                }

                $provisioner_lib->settings['mac'] = $phone_info['mac'];
                $provisioner_lib->mac = $phone_info['mac'];

                //Setting a line variable here...these aren't defined in the template_data.xml file yet. however they will still be parsed
                //and if they have defaults assigned in a future template_data.xml or in the config file using pipes (|) those will be used, pipes take precedence
                $provisioner_lib->processor_info = "EndPoint Manager Version " . $this->global_cfg['version'];


                // Because every brand is an extension (eventually) of endpoint, you know this function will exist regardless of who it is
                //Start timer
                $time_start = microtime(true);

                $provisioner_lib->debug = TRUE;

                try {
                    $returned_data = $provisioner_lib->generate_all_files();
                } catch (Exception $e) {
                    $this->error['prepare_configs'] = sprintf(_("The phone driver stopped with an error: %s (%s, line %s)"), epm_esc($e->getMessage()), epm_esc($e->getFile()), epm_esc($e->getLine()));
                    return(FALSE);
                }
                //print_r($provisioner_lib->debug_return);
                //End timer
                $time_end = microtime(true);
                $time = $time_end - $time_start;
                if ($time > 360) {
                    $this->error['generate_time'] = sprintf(_("The configs took %s seconds to make."), round($time, 2));
                }
                if ($write) {
                    $this->write_configs($provisioner_lib, $reboot, $settings['config_location'], $phone_info, $returned_data);
                } else {
                    return ($returned_data);
                }
                return(TRUE);
            } else {
                // A missing driver means that the bundled library does not support the model.
                $this->error['parse_configs'] = sprintf(_("No driver for this model: %s is not in the bundled provisioner library."), $class);
                return(FALSE);
            }
        } else {
            $this->error['parse_configs'] = sprintf(_("The bundled provisioner library is missing: %s"), $autoloader);
            return(FALSE);
        }
    }

    /**
     * Check for new firmware on the servers
     * @param int $id Product ID
     * @return bool True on yes False on no
     */

    function download_json($location, $directory=NULL) {
        $temp_directory = $this->system->sys_get_temp_dir() . "/epm_temp/";
        if (!isset($directory)) {
            $destination_file = $this->PHONE_MODULES_PATH . 'endpoint/master.json';
            $directory = "master";
        } else {
            if (!file_exists($this->PHONE_MODULES_PATH . '/' . $directory)) {
                mkdir($this->PHONE_MODULES_PATH . '/' . $directory, 0775, TRUE);
            }
            $destination_file = $this->PHONE_MODULES_PATH . '/' . $directory . '/brand_data.json';
        }
        $temp_file = $temp_directory . $directory . '.json';
        file_exists(dirname($temp_file)) ? '' : mkdir(dirname($temp_file));

        if ($this->system->download_file($location, $temp_file)) {
            $handle = fopen($temp_file, "rb");
            $contents = fread($handle, filesize($temp_file));
            fclose($handle);

            $a = $this->validate_json($contents);
            if ($a === FALSE) {
                //Error with the internet....ABORRRTTTT THEEEEE DOWNLOAAAAADDDDDDDD! SCOTTYYYY!;
                unlink($temp_file);
                return(FALSE);
            } else {
                rename($temp_file, $destination_file);
                chmod($destination_file, 0775);
                return(TRUE);
            }
        } else {
            return(FALSE);
        }
    }

    /**
     * Check for new packges for brands. These packages will include phone models and such which the user can remove if they want
     * This function will alos auto-update the provisioner.net library incase anything has changed
     * @return array An array of all the brands/products/models and information about what's  enabled, installed or otherwise
     */

    function table_exists($table) {
        global $amp_conf;
        // A database name is an identifier, not a value, so it cannot be bound.
        // AMPDBNAME comes from amportal.conf, never from a request.
        $sql = "SHOW TABLES FROM " . $amp_conf['AMPDBNAME'];
        $sql_p = array();
        $result = epm_sql($sql, 'getAll', $sql_p);
        foreach ($result as $row) {
            if ($row[0] == $table) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * Give a driver what it needs to reboot a phone: the AMI connection and
     * the registered address of its first line. The drivers send NOTIFY over
     * AMI and never start a shell.
     *
     * @param object $provisioner_lib the driver object
     * @return void
     */
    public function attach_reboot_context($provisioner_lib) {
        $provisioner_lib->ami = $this->freepbx->astman;
        $ext = isset($provisioner_lib->settings['line'][0]['username']) ? (string) $provisioner_lib->settings['line'][0]['username'] : '';
        if ($this->registration_cache === null) {
            $this->registration_cache = $this->registration_status();
        }
        $provisioner_lib->device_ip = isset($this->registration_cache[$ext]['ip']) ? $this->registration_cache[$ext]['ip'] : '';
    }

    /** @var array|null registration_status() result, read once for each request */
    private $registration_cache = null;

    /**
     * Get the registration state of each registered extension, keyed by
     * extension.
     *
     * This reads AMI, not the output of `asterisk -rx 'pjsip show contacts'`.
     * The CLI method needed asterisk_location, started a process for each page
     * load and parsed columns. It also found only all-digit extensions and IPv4
     * addresses.
     *
     * FreePBX's PJSIPShowRegistrationInboundContactStatuses() sends one
     * ContactStatusDetail event for each contact and returns all of them.
     *
     * The list has contacts from inbound registrations only. A permanent contact
     * in an AOR shows in the CLI but not here. This is correct, because nothing
     * registered.
     *
     * @return array ext => array('status' => bool, 'ip' => string, 'port' => string)
     */
    public function registration_status() {
        $astman = $this->freepbx->astman;
        if (empty($astman) || !$astman->connected()) {
            return array();
        }

        // The helper reads $astman->response_catch but does not set or clear it. Set
        // it here. Without this, a read with no registrations is an undefined
        // property. Also, a second call in one request returns the events of the
        // first call.
        $astman->response_catch = array();
        $contacts = $astman->PJSIPShowRegistrationInboundContactStatuses();
        if (!is_array($contacts)) {
            return array();
        }

        $status = array();
        foreach ($contacts as $contact) {
            if (empty($contact['AOR'])) {
                continue;
            }

            // The other states of Asterisk are Unknown, Created, Removed and NonQualified.
            // They all mean that the contact does not answer qualify requests. The CLI
            // `Avail` test meant the same.
            $up = isset($contact['Status']) && strcasecmp($contact['Status'], 'Reachable') === 0;

            // ViaAddress is the address that Asterisk uses to reach the phone. The
            // contact URI is only what the phone sent. Use ViaAddress when it is present.
            $addr = !empty($contact['ViaAddress']) ? $contact['ViaAddress'] : '';
            if (($addr === '') && (!empty($contact['URI'])) && (preg_match('/@([^;>]+)/', $contact['URI'], $m))) {
                $addr = $m[1];
            }
            $port = '';
            $colon = strrpos($addr, ':');
            // Split only on a trailing `:port`. A bare IPv6 address has many colons, and
            // `[::1]:5060` splits after the bracket.
            if (($colon !== FALSE) && (ctype_digit(substr($addr, $colon + 1)))) {
                $port = substr($addr, $colon + 1);
                $addr = rtrim(substr($addr, 0, $colon), ']');
                $addr = ltrim($addr, '[');
            }

            // An AOR can have more than one contact. One reachable contact is sufficient.
            $ext = $contact['AOR'];
            if ((isset($status[$ext])) && ($status[$ext]['status']) && (!$up)) {
                continue;
            }
            $status[$ext] = array('status' => $up, 'ip' => $addr, 'port' => $port);
        }
        return $status;
    }

    /**
     * Reads a file. Json decodes it and will report any errors back
     * @param string $file location of file
     * @return mixed false on error, array on success
     * @version 2.11
     */

    function validate_netmask($mask) {
        return preg_match("/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\/(\d{1,2})$/", $mask) ? TRUE : FALSE;
    }

    /**
     * Discover New Device/Hardware
     * nmap will actually discover 'unseen' devices that the VoIP server hasn't heard from
     * If the user just wishes to use the kernel's neighbour table they can tell the function to not use nmap
     * This results in a speed increase from 60 seconds to less than one second.
     *
     * epm_system::neighbour_table() reads the table. It uses `ip neigh show`
     * before `arp -an`.
     *
     * This is the original function that started it all
     * http://www.pbxinaflash.com/community/index.php?threads/end-point-configuration-manager-module-for-freepbx-part-1.4514/page-4#post-37671
     *
     * @version 2.11
     * @param mixed $netmask The netmask, eg [1.1.1.1/24]
     * @param boolean $use_nmap True use nmap, false don't use it
     * @return array List of devices found on the network
     */

    function discover_new($netmask, $use_nmap=TRUE) {
        if (!$this->validate_netmask($netmask)) {
            $this->error['discover_new'] = _("The network is not valid. Use the form 192.168.1.0/24.");
            return(FALSE);
        }

        // nmap is optional and usually absent. Show a warning and continue. The ping
        // sweep only adds phones to the neighbor table before the read.
        if ($use_nmap) {
            $nmap = $this->global_cfg['nmap_location'];
            if (($nmap === '') || !file_exists($nmap) || !is_executable($nmap)) {
                $nmap = $this->system->find_exec('nmap');
            }
            if ($nmap === '') {
                $this->error['discover_new'] = _("nmap is not installed. The search uses the neighbor table only.");
            } else {
                shell_exec(escapeshellcmd($nmap) . ' -v -sP ' . escapeshellarg($netmask));
            }
        }

        // neighbour_table() uses `ip neigh show` from iproute2. It uses `arp -an`
        // only when iproute2 is not available, because Debian 12 has no net-tools.
        // NULL means that neither tool is installed. Report this, because it is not
        // the same as an empty network.
        $neighbours = $this->system->neighbour_table($this->global_cfg['arp_location']);
        if ($neighbours === NULL) {
            $this->error['discover_new'] = _("Cannot read the neighbor table. Install iproute2 (ip) or net-tools (arp).");
            return(FALSE);
        }

        $final = array();
        $z = 0;
        foreach ($neighbours as $ip => $mac) {

            //Get rid of the delimiter
            $mac_strip = strtoupper(str_replace(":", "", $mac));

            //arp prints a MAC of 000000000000 when no hardware was found, so we need to ignore it
            if ($mac_strip != "000000000000") {
                //only use the first 6 characters for the oui: http://en.wikipedia.org/wiki/Organizationally_Unique_Identifier
                $oui = substr($mac_strip, 0, 6);

                //Find the matching brand model to the oui
                $oui_sql = 'SELECT endpointman_brand_list.name, endpointman_brand_list.id FROM endpointman_oui_list, endpointman_brand_list WHERE oui LIKE ? AND endpointman_brand_list.id = endpointman_oui_list.brand AND endpointman_brand_list.installed = 1 LIMIT 1';
                $oui_sql_p = array('%' . $oui . '%');

                $brand = epm_sql($oui_sql, 'getRow', $oui_sql_p);

                // Most addresses are not phones, for example a PC, a router or the bridge of
                // the PBX. getRow() returns false when it does not know the OUI. Skip these
                // addresses, because an array read on false is a PHP 8 error.
                if (!is_array($brand)) {
                    //oui doesn't have a matching mysql reference, probably a PC/router/wap/printer of some sort.
                    $brand = array('name' => FALSE, 'id' => NULL);
                }

                //Find out if endpoint has already been configured for this mac address
                $count_sql = 'SELECT count(*) FROM endpointman_mac_list WHERE mac LIKE  ?';
                $count_sql_p = array('%' . $mac_strip . '%');
                $epm =  epm_sql($count_sql, 'getOne', $count_sql_p);

                //Add into a final array
                $final[$z] = array("ip" => $ip, "mac" => $mac, "mac_strip" => $mac_strip, "oui" => $oui, "brand" => $brand['name'], "brand_id" => $brand['id'], "endpoint_managed" => $epm);
                $z++;
            }
        }
        return empty($final) ? FALSE : $final;
    }

    function in_array_recursive($needle, $haystack) {

        $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($haystack));

        foreach ($it AS $element) {
            if ($element == $needle) {
                return TRUE;
            }
        }
        return FALSE;
    }

    function linesAvailable($lineid=NULL, $macid=NULL) {
        if (isset($lineid)) {
            $sql = 'SELECT max_lines FROM endpointman_model_list WHERE id = (SELECT endpointman_mac_list.model FROM endpointman_mac_list, endpointman_line_list WHERE endpointman_line_list.luid = ? AND endpointman_line_list.mac_id = endpointman_mac_list.id)';
            $sql_p = array($lineid);

            $sql_l = 'SELECT line, mac_id FROM `endpointman_line_list` WHERE luid = ?';
            $sql_l_p = array($lineid);
            $line = epm_sql($sql_l, 'getRow', $sql_l_p);

            $sql_lu = 'SELECT line FROM endpointman_line_list WHERE mac_id = ?';
            $sql_lu_p = array($line['mac_id']);
        } elseif (isset($macid)) {
            $sql = 'SELECT max_lines FROM endpointman_model_list WHERE id = (SELECT model FROM endpointman_mac_list WHERE id =?)';
            $sql_p = array($macid);
            $sql_lu = 'SELECT line FROM endpointman_line_list WHERE mac_id = ?';
            $sql_lu_p = array($macid);

            $line['line'] = 0;
        }

        $max_lines = epm_sql($sql, 'getOne', $sql_p);
        $lines_used = epm_sql($sql_lu, 'getAll', $sql_lu_p);

        for ($i = 1; $i <= $max_lines; $i++) {
            if ($i == $line['line']) {
                $temp[$i]['value'] = $i;
                $temp[$i]['text'] = $i;
                $temp[$i]['selected'] = "selected";
            } else {
                if (!$this->in_array_recursive($i, $lines_used)) {
                    $temp[$i]['value'] = $i;
                    $temp[$i]['text'] = $i;
                }
            }
        }
        if (isset($temp)) {
            return($temp);
        } else {
            return FALSE;
        }
    }

    /**
     * Display all unused registrations from whatever manager we are using!
     * @return <type>
     */

    function display_registration_list($line_id=NULL) {

        if (isset($line_id)) {
            $result = $this->eda->all_unused_registrations();
            $line_data = $this->eda->get_line_information($line_id);
        } else {
            $result = $this->eda->all_unused_registrations();
            $line_data = NULL;
        }

        $i = 1;
        $temp = array();
        foreach ($result as $row) {
            $temp[$i]['value'] = $row['id'];
            $temp[$i]['text'] = $row['id'] . " (" . $row['description'] . ")";
            $i++;
        }

        if (isset($line_data)) {
            $temp[$i]['value'] = $line_data['ext'];
            $temp[$i]['text'] = $line_data['ext'] . " (" . $line_data['description'] . ")";
            $temp[$i]['selected'] = "selected";
        }

        return($temp);
    }

    /**
     * Returns list of Brands that are installed and not hidden and that have at least one model enabled under them
     * @param integer $selected ID Number of the brand that is supposed to be selected in a drop-down list box
     * @return array Number array used to generate a select box
     */

    function display_templates($product_id, $temp_select = NULL) {
        $i = 0;
        $sql = 'SELECT id FROM  endpointman_product_list WHERE endpointman_product_list.id =?';
        $sql_p = array($product_id);

        $id = epm_sql($sql, 'getOne', $sql_p);

        $sql = 'SELECT * FROM  endpointman_template_list WHERE  product_id = ?';
        $sql_p = array($id);

        $data = epm_sql($sql, 'getAll', $sql_p);
        foreach ($data as $row) {
            $temp[$i]['value'] = $row['id'];
            $temp[$i]['text'] = $row['name'];
            if ($row['id'] == $temp_select) {
                $temp[$i]['selected'] = "selected";
            }
            $i++;
        }
        $temp[$i]['value'] = 0;
        if ($temp_select == 0) {
            $temp[$i]['text'] = _("Custom (this phone only)");
            $temp[$i]['selected'] = "selected";
        } else {
            $temp[$i]['text'] = _("Custom (this phone only)");
        }

        return($temp);
    }

    function validate_json($json) {
        return(TRUE);
    }

    // TemplatesPage::edit_template_display() is the only template editor.
    // Callers redirect to it through TemplatesPage::editorUrl().

    /**
     * Generates the Visual Display for the end user
     * @param <type> $cfg_data
     * @param <type> $custom_cfg_data
     * @param <type> $admin
     * @param <type> $user_cfg_data
     * @return <type>
     */

    function generate_gui_html($cfg_data, $custom_cfg_data=NULL, $admin=FALSE, $user_cfg_data=NULL, $max_lines=3, $ext=NULL) {
        // Decode the stored blobs into arrays. Callers send a database column or an
        // array that is already in memory. epm_blob_decode() returns an array
        // unchanged.
        $cfg_data = epm_blob_decode($cfg_data);
        $template_type = 'GENERAL';
        //Check to see if there is a custom template for this phone already listed in the endpointman_mac_list database
        if (!empty($custom_cfg_data)) {
            $custom_cfg_data = epm_blob_decode($custom_cfg_data);
            if (array_key_exists('data', $custom_cfg_data)) {
                if (array_key_exists('ari', $custom_cfg_data)) {
                    $extra_data = $custom_cfg_data['ari'];
                } else {
                    $template_type = 'GLOBAL';
                    // A blob can lack the 'freepbx' key. Old blobs do not have it, and the
                    // specifics popup writes it only when a "use defaults" box is checked. Empty
                    // means no overrides, as in the other two branches.
                    $extra_data = isset($custom_cfg_data['freepbx']) ? $custom_cfg_data['freepbx'] : array();
                }
                $custom_cfg_data = $custom_cfg_data['data'];
            } else {
                $extra_data = array();
            }
        } else {
            $custom_cfg_data = array();
            $extra_data = array();
        }
        if (isset($user_cfg_data)) {
            $user_cfg_data = epm_blob_decode($user_cfg_data);
        }

        $template_variables_array = array();
        $group_count = 0;
        $variables_count = 0;

        foreach ($cfg_data['data'] as $cats_name => $cats) {
            if ($admin) {
                $group_count++;
                $template_variables_array[$group_count]['title'] = $cats_name;
            } else {
                //Group all ARI stuff into one tab
                $template_variables_array[$group_count]['title'] = _("Phone settings");
            }
            foreach ($cats as $subcat_name => $subcats) {
                foreach ($subcats as $item_var => $config_options) {
                    if (preg_match('/(.*)\|(.*)/i', $item_var, $matches)) {
                        $type = $matches[1];
                        $variable = $matches[2];
                    } else {
                        die('no matches!');
                    }
                    if ($admin) {
                        //Administration View Only
                        switch ($type) {
                            case "lineloop":
                                //line|1|display_name
                                foreach ($config_options as $var_name => $var_items) {
                                    $lcount = isset($var_items['line_count']) ? $var_items['line_count'] : $lcount;
                                    $key = "line|" . $lcount . "|" . $var_name;
                                    $template_variables_array[$group_count]['data'][$variables_count] = $this->generate_form_data($variables_count, $var_items, $key, $custom_cfg_data, $admin, $user_cfg_data, $extra_data, $template_type);
                                    $template_variables_array[$group_count]['data'][$variables_count]['looping'] = TRUE;
                                    $variables_count++;
                                }

                                if ($lcount <= $max_lines) {
                                    $template_variables_array[$group_count]['title'] = "Line Options for Line " . $lcount;
                                    $group_count++;
                                } else {
                                    unset($template_variables_array[$group_count]);
                                }

                                continue 2;
                            case "loop":
                                foreach ($config_options as $var_name => $var_items) {
                                    //loop|remotephonebook_url_0
                                    $tv = explode('_', $variable);
                                    $key = "loop|" . $tv[0] . "_" . $var_name . (isset($var_items['loop_count']) ? "_" . $var_items['loop_count'] : '');
                                    $template_variables_array[$group_count]['data'][$variables_count] = $this->generate_form_data($variables_count, $var_items, $key, $custom_cfg_data, $admin, $user_cfg_data, $extra_data, $template_type);
                                    $template_variables_array[$group_count]['data'][$variables_count]['looping'] = TRUE;
                                    $variables_count++;
                                }
                                continue 2;
                        }
                    } else {
                        //ARI View Only
                        switch ($type) {
                            case "loop_line_options":
                                //$a is the line number
                                $sql = 'SELECT line FROM endpointman_line_list WHERE  ext = ?';
                                $sql_p = array($ext);
                                $a = epm_sql($sql, 'getOne', $sql_p);
                                //TODO: fix this area
                                $template_variables_array[$group_count]['data'][$variables_count]['type'] = "break";
                                $variables_count++;
                                continue 2;
                            case "loop":
                                foreach ($config_options as $var_name => $var_items) {
                                    $tv = explode('_', $variable);
                                    $key = "loop|" . $tv[0] . "_" . $var_name . "_" . $var_items['loop_count'];
                                    if (isset($extra_data[$key])) {
                                        $template_variables_array[$group_count]['data'][$variables_count] = $this->generate_form_data($variables_count, $var_items, $key, $custom_cfg_data, $admin, $user_cfg_data, $extra_data, $template_type);
                                        $template_variables_array[$group_count]['data'][$variables_count]['looping'] = TRUE;
                                        $variables_count++;
                                    }
                                }
                                continue 2;
                        }
                    }
                    //Both Views
                    switch ($config_options['type']) {
                        case "break":
                            $template_variables_array[$group_count]['data'][$variables_count] = $this->generate_form_data($variables_count, $config_options, $key, $custom_cfg_data, $admin, $user_cfg_data, $extra_data, $template_type);
                            $variables_count++;
                            break;
                        default:
                            if (array_key_exists('variable', $config_options)) {
                                $key = str_replace('$', '', $config_options['variable']);
                                //TODO: Move this into the sync function
                                //Checks to see if values are defined in the database, if not then we assume this is a new option and we need a default value here!
                                if (!isset($custom_cfg_data[$key])) {
                                    //xml2array will take values that have no data and turn them into arrays, we want to avoid the word 'array' as a default value, so we blank it out here if we are an array
                                    if ((array_key_exists('default_value', $config_options)) AND (is_array($config_options['default_value']))) {
                                        $custom_cfg_data[$key] = "";
                                    } elseif ((array_key_exists('default_value', $config_options)) AND (!is_array($config_options['default_value']))) {
                                        $custom_cfg_data[$key] = $config_options['default_value'];
                                    }
                                }
                                if ((!$admin) AND (isset($extra_data[$key]))) {
                                    $custom_cfg_data[$key] = $user_cfg_data[$key];
                                    $template_variables_array[$group_count]['data'][$variables_count] = $this->generate_form_data($variables_count, $config_options, $key, $custom_cfg_data, $admin, $user_cfg_data, $extra_data, $template_type);
                                    $variables_count++;
                                } elseif ($admin) {
                                    $template_variables_array[$group_count]['data'][$variables_count] = $this->generate_form_data($variables_count, $config_options, $key, $custom_cfg_data, $admin, $user_cfg_data, $extra_data, $template_type);
                                    $variables_count++;
                                }
                            }
                            break;
                    }
                    continue;
                }
            }
        }

        return($template_variables_array);
    }

    /**
     * Generate an array that will get parsed as HTML from an array of values from XML
     * @param int $i
     * @param array $cfg_data
     * @param string $key
     * @param array $custom_cfg_data
     * @return array
     */

    function generate_form_data($i, $cfg_data, $key=NULL, $custom_cfg_data=NULL, $admin=FALSE, $user_cfg_data=NULL, $extra_data=NULL, $template_type='GENERAL') {
        switch ($cfg_data['type']) {
            case "input":
                if ((!$admin) && (isset($user_cfg_data[$key]))) {
                    $custom_cfg_data[$key] = $user_cfg_data[$key];
                }
                $template_variables_array['type'] = "input";
                if (isset($cfg_data['max_chars'])) {
                    $template_variables_array['max_chars'] = $cfg_data['max_chars'];
                }
                $template_variables_array['key'] = $key;
                $template_variables_array['value'] = isset($custom_cfg_data[$key]) ? $custom_cfg_data[$key] : $cfg_data['default_value'];
                $template_variables_array['description'] = $cfg_data['description'];
                break;
            case "radio":
                if ((!$admin) && (isset($user_cfg_data[$key]))) {
                    $custom_cfg_data[$key] = $user_cfg_data[$key];
                }
                $num = isset($custom_cfg_data[$key]) ? $custom_cfg_data[$key] : $cfg_data['default_value'];
                $template_variables_array['type'] = "radio";
                $template_variables_array['key'] = $key;
                $template_variables_array['description'] = $cfg_data['description'];
                $z = 0;
                while ($z < count($cfg_data['data'])) {
				//$key = $key . $z;


                    $template_variables_array['data'][$z]['key'] = $key;
                    $template_variables_array['data'][$z]['value'] = $cfg_data['data'][$z]['value'];
                    $template_variables_array['data'][$z]['description'] = $cfg_data['data'][$z]['text'];

                    if ($cfg_data['data'][$z]['value'] == $num) {
                        $template_variables_array['data'][$z]['checked'] = 'checked';
                    }
                    $z++;
                }
                break;
            case "list":
                if ((!$admin) && (isset($user_cfg_data[$key]))) {
                    $custom_cfg_data[$key] = $user_cfg_data[$key];
                }
                $num = isset($custom_cfg_data[$key]) ? $custom_cfg_data[$key] : $cfg_data['default_value'];
                $template_variables_array['type'] = "list";
                $template_variables_array['key'] = $key;
                $template_variables_array['description'] = $cfg_data['description'];
                $z = 0;
                while ($z < count($cfg_data['data'])) {
                    $template_variables_array['data'][$z]['value'] = $cfg_data['data'][$z]['value'];
                    $template_variables_array['data'][$z]['description'] = $cfg_data['data'][$z]['text'];
                    if (isset($cfg_data['data'][$z]['disable'])) {
                        $cfg_data['data'][$z]['disable'] = str_replace('{$count}', $z, $cfg_data['data'][$z]['disable']);
                        $template_variables_array['data'][$z]['disables'] = explode(",", $cfg_data['data'][$z]['disable']);
                    }
                    if (isset($cfg_data['data'][$z]['enable'])) {
                        $cfg_data['data'][$z]['enable'] = str_replace('{$count}', $z, $cfg_data['data'][$z]['enable']);
                        $template_variables_array['data'][$z]['enables'] = explode(",", $cfg_data['data'][$z]['enable']);
                    }
                    if ($cfg_data['data'][$z]['value'] == $num) {
                        $template_variables_array['data'][$z]['selected'] = 'selected';
                    }
                    $z++;
                }
                break;
            case "checkbox":
                if ((!$admin) && (isset($user_cfg_data[$key]))) {
                    $custom_cfg_data[$key] = $user_cfg_data[$key];
                }
                $num = isset($custom_cfg_data[$key]) ? $custom_cfg_data[$key] : $cfg_data['default_value'];
                $template_variables_array['type'] = "checkbox";
                $template_variables_array['key'] = $key;
                $template_variables_array['description'] = $cfg_data['description'];
                // Use isset(), as in the line above. A checkbox that the saved blob does not
                // have is unchecked.
                $template_variables_array['checked'] = !empty($custom_cfg_data[$key]) ? TRUE : NULL;
                $template_variables_array['value'] = $key;
                break;
            case "group";
                $template_variables_array['type'] = "group";
                $template_variables_array['description'] = $cfg_data['description'];
                break;
            case "header";
                $template_variables_array['type'] = "header";
                $template_variables_array['description'] = $cfg_data['description'];
                break;
            case "textarea":
                if ((!$admin) && (isset($user_cfg_data[$key]))) {
                    $custom_cfg_data[$key] = $user_cfg_data[$key];
                }
                $template_variables_array['type'] = "textarea";
                if (isset($cfg_data['rows'])) {
                    $template_variables_array['rows'] = $cfg_data['rows'];
                }
                if (isset($cfg_data['cols'])) {
                    $template_variables_array['cols'] = $cfg_data['cols'];
                }
                $template_variables_array['key'] = $key;
                $template_variables_array['value'] = isset($custom_cfg_data[$key]) ? $custom_cfg_data[$key] : $cfg_data['default_value'];
                $template_variables_array['description'] = $cfg_data['description'];
                break;
            case "break":
                if ($admin) {
                    $template_variables_array['type'] = "break";
                } else {
                    $template_variables_array['type'] = "NA";
                }
                break;
            default:
                $template_variables_array['type'] = "NA";
                break;
        }

        // Return the value as plain text. The views escape each field where they
        // print it, so all seven fields follow one rule.
        if (isset($cfg_data['tooltip'])) {
            $template_variables_array['tooltip'] = $cfg_data['tooltip'];
        }

        if ($template_type == 'GLOBAL') {
            $template_variables_array['freepbxed'] = 1;
            $template_variables_array['freepbx']['key'] = $key;
            if (empty($extra_data)) {
                $template_variables_array['freepbx']['checked'] = TRUE;
            } elseif (isset($extra_data[$key])) {
                $template_variables_array['freepbx']['checked'] = TRUE;
            }
        }
        return($template_variables_array);
    }

    /**
     * Save template from the template view pain
     * @param int $id Either the MAC ID or Template ID
     * @param int $custom Either 0 or 1, it determines if $id is MAC ID or Template ID
     * @param array $variables The variables sent from the form. usually everything in $_REQUEST[]
     * @return string Location of area to return to in Endpoint Manager
     */

    function areaAvailable($model, $area=NULL) {
        $sql = 'SELECT max_lines FROM endpointman_model_list WHERE id = ?';
        $sql_p = array($model);
        $count = epm_sql($sql, 'getOne', $sql_p);

        for ($z = 0; $z < $count; $z++) {
            $result[$z]['id'] = $z + 1;
            $result[$z]['model'] = $z + 1;
        }

        $i = 1;
        foreach ($result as $row) {
            if ($row['id'] == $area) {
                $temp[$i]['value'] = $row['id'];
                $temp[$i]['text'] = $row['model'];
                $temp[$i]['selected'] = 'selected';
            } else {
                $temp[$i]['value'] = $row['id'];
                $temp[$i]['text'] = $row['model'];
                $temp[$i]['selected'] = 0;
            }
            $i++;
        }

        return($temp);
    }

    /**
     *
     * @param integer $model model ID
     * @param integer $brand brand ID
     * @param integer $product product ID
     * @return array
     */

    function models_available($model=NULL, $brand=NULL, $product=NULL) {

        if ((!isset($oui)) && (!isset($brand)) && (!isset($model))) {
            $result1 = $this->eda->all_models();
        } elseif ((isset($brand)) && ($brand != 0)) {
            $result1 = $this->eda->all_models_by_brand($brand);
        } elseif ((isset($product)) && ($product != 0)) {
            $result1 = $this->eda->all_models_by_product($product);
        } else {
            $result1 = $this->eda->all_models();
        }

        $i = 1;
        foreach ($result1 as $row) {
            if ($row['id'] == $model) {
                $temp[$i]['value'] = $row['id'];
                $temp[$i]['text'] = $row['model'];
                $temp[$i]['selected'] = 'selected';
            } else {
                $temp[$i]['value'] = $row['id'];
                $temp[$i]['text'] = $row['model'];
                $temp[$i]['selected'] = 0;
            }
            $i++;
        }

        if (!isset($temp)) {
            if (!isset($this->global_cfg['new'])) {
                $this->error['modelsAvailable'] = _("No model is enabled. Enable a model on the Brands page.");
            }
            return(FALSE);
        } else {
            return($temp);
        }
    }
}
