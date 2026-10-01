<?php
/**
 * Description of freepbx
 *
 * @author Andrew
 */

namespace FreePBX\modules;
use PDO;

class epm_data_abstraction {

    public $config;      //FreePBX Config object
    public $configmod;   //Endpointman\Config object
    public $db;          //Database from FreePBX
    public $global_cfg;  //endpointman_global_vars, set here and from Endpointman::__construct

    function __construct($endpointman) {

    	$this->config = $endpointman -> config;
    	$this->configmod = $endpointman -> configmod;
        $this->db = $endpointman -> db;
    }

    function all_products() {
        $temp = epm_sql("SELECT * FROM endpointman_product_list WHERE id > 0", 'getAll');
        return($temp);
    }

    function all_devices() {
        $sql = 'SELECT endpointman_mac_list.id , endpointman_mac_list.mac , endpointman_model_list.model, endpointman_model_list.enabled , endpointman_brand_list.name, endpointman_mac_list.global_custom_cfg_data, endpointman_mac_list.template_id FROM endpointman_mac_list , endpointman_model_list , endpointman_brand_list WHERE ( endpointman_model_list.id = endpointman_mac_list.model ) AND ( endpointman_model_list.brand = endpointman_brand_list.id )';
        $temp = epm_sql($sql, 'getAll');
        return($temp);
    }

    /**
     * Get each enabled model whose product is not hidden.
     *
     * @return array rows of endpointman_model_list
     */
    function all_models() {
        $sql="SELECT endpointman_model_list.* FROM endpointman_model_list, endpointman_product_list WHERE endpointman_model_list.product_id = endpointman_product_list.id AND endpointman_model_list.enabled = 1 AND endpointman_product_list.hidden = 0";
        $result1 = epm_sql($sql, 'getAll');
        return($result1);
    }

    /**
     * Get the installed brands that are not hidden and have an enabled model.
     *
     * @return array rows of (name, id)
     */
    function all_active_brands() {
        $sql="SELECT DISTINCT endpointman_brand_list.name, endpointman_brand_list.id FROM  endpointman_brand_list,endpointman_model_list WHERE endpointman_model_list.brand = endpointman_brand_list.id AND endpointman_model_list.enabled = 1 AND endpointman_model_list.hidden = 0 AND endpointman_brand_list.installed = 1 AND endpointman_brand_list.hidden = 0";
        $data = epm_sql($sql, 'getAll');
        return($data);
    }

    function all_models_by_product($product_id) {
        $sql="SELECT * FROM endpointman_model_list WHERE product_id = ?";
        $result1 = epm_sql($sql, 'getAll', array($product_id));
        return($result1);
    }

    function all_models_by_brand($brand_id) {
        $sql="SELECT endpointman_model_list.* FROM endpointman_model_list, endpointman_product_list WHERE endpointman_model_list.product_id = endpointman_product_list.id AND endpointman_model_list.enabled = 1 AND endpointman_product_list.hidden = 0 AND endpointman_model_list.brand = ?";
        $result1 = epm_sql($sql, 'getAll', array($brand_id));
        return($result1);
    }

    function all_unknown_devices() {
        $sql = 'SELECT * FROM  endpointman_mac_list WHERE model = 0';
        $unknown_list = epm_sql($sql, 'getAll');
        return($unknown_list);
    }

    function all_unused_registrations() {
        if($this->configmod->get('show_all_registrations')) {
            $not_added="SELECT devices.id, devices.description FROM devices WHERE tech in ('sip','pjsip') ORDER BY devices.id";
        } else {
            $not_added="SELECT devices.id, devices.description FROM devices WHERE tech in('sip','pjsip') AND devices.id not in (SELECT devices.id FROM devices, endpointman_line_list WHERE tech in ('sip','pjsip') AND devices.id = endpointman_line_list.ext ) ORDER BY devices.id";
        }
        $result = epm_sql($not_added, 'getAll');
        return($result);
    }

    function all_used_registrations() {
        $not_added="SELECT devices.id, devices.description FROM devices WHERE tech in ('sip','pjsip') AND devices.id in (SELECT devices.id FROM devices, endpointman_line_list WHERE tech in ('sip','pjsip') AND devices.id = endpointman_line_list.ext ) ORDER BY devices.id";
        $result = epm_sql($not_added, 'getAll');
        return($result);
    }

    function get_lines_from_device($device_id) {
        $sql = 'SELECT * FROM endpointman_line_list WHERE mac_id = ? ORDER BY  endpointman_line_list.line ASC';
        $line_list = epm_sql($sql, 'getAll', array($device_id));
        return($line_list);
    }

    function get_line_information($line_id) {
        $sql = 'SELECT * FROM endpointman_line_list WHERE luid = ?';
        $line_list = epm_sql($sql, 'getRow', array($line_id));
        return($line_list);
    }

    /**
     * Get each module setting as a flat var_name => value array.
     *
     * The Config wrapper is the only reader of the store. This method stays,
     * because DevicesPage and the popup views expect $global_cfg as a plain
     * array. They also write runtime keys into it, for example `new`.
     *
     * @return array
     */
    function get_stored_globals() {
        $this -> global_cfg = $this -> configmod -> getall();

        return($this -> global_cfg);
    }

    function sql($sql, $type="query", $params=array(), $fetchmode=DB_FETCHMODE_ASSOC) {
        return epm_sql($sql, $type, $params, $fetchmode);
    }
}
