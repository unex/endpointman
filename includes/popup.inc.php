<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
*/
// include_once('functions.inc.php');

// The device methods that this popup uses (get_phone_info,
// prepare_message_box, display_message_box) and the
// eda/global_cfg/error/message state are in DevicesPage.
$endpoint = FreePBX::Endpointman() -> _get_page('devices');
$epm      = FreePBX::Endpointman();
$tpl = FreePBX::Endpointman() -> tpl;   // Endpointman\View, owned by Endpointman


if($_REQUEST['pop_type'] == 'edit_specifics') {

    if(file_exists($epm->PHONE_MODULES_PATH . 'endpoint/global_template_data.json')) {
        $template_list[0] = 'global_template_data.json';

        $phone_info = $endpoint->get_phone_info($_REQUEST['edit_id']);

        $sql = 'SELECT max_lines FROM endpointman_model_list WHERE id = ?';
        $sql_p = array($phone_info['model_id']);

        $lines = epm_sql($sql, 'getOne', $sql_p);
        $merged = $epm->brandmanager->merge_data($epm->PHONE_MODULES_PATH . 'endpoint/', $template_list,$lines);

        //remove stuff that is meaningless in this context
        unset($merged['data']['globals']['globals']['option|mac']);
        unset($merged['data']['globals']['globals']['break|0']);
        unset($merged['data']['globals']['globals']['option|provision_protocol']);

        $html_array = $endpoint->generate_gui_html($merged, NULL, TRUE, NULL, $lines);

        $specific_data = array();
        if (isset($_REQUEST['button_save'])) {
            foreach ($_REQUEST as $key => $data) {
                $key = str_replace('freepbx_', '', $key);
                if (isset($_REQUEST['freepbx_' . $key])) {
                    $specific_data['freepbx'][$key] = TRUE;
                }
                if ($epm->system->arraysearchrecursive($key, $html_array, 'key')) {
                    if(!isset($specific_data['freepbx'][$key])) {
                        $specific_data['data'][$key] = $data;
                    }
                }
            }
            $specific_data['data']['use_dhcp'] = isset($specific_data['data']['use_dhcp']) ? TRUE : FALSE;

            $sql = 'UPDATE endpointman_mac_list SET specific_settings = ? WHERE id =?';
            $sql_p = array(epm_blob_encode($specific_data), $_REQUEST['edit_id']);
            epm_sql($sql, 'query', $sql_p);
        } else {
            $sql = 'SELECT specific_settings FROM endpointman_mac_list WHERE id =?';
            $sql_p = array($_REQUEST['edit_id']);
            $specific_data = epm_blob_decode(epm_sql($sql, 'getOne', $sql_p));
            // A device without saved specific settings has NULL in the column, and
            // epm_blob_decode() returns false for it. The loop below must not index into
            // false, because PHP 8.1 deprecates that and FreePBX makes it fatal.
            if (!is_array($specific_data)) {
                $specific_data = array();
            }
        }

        foreach($phone_info['line'] as $data) {
                $lid = $data['line'];
                $specific_data['data']['line|'.$lid.'|displayname'] = !isset($specific_data['data']['line|'.$lid.'|displayname']) ? $data['description'] : $specific_data['data']['line|'.$lid.'|displayname'];
                $specific_data['data']['line|'.$lid.'|username'] = !isset($specific_data['data']['line|'.$lid.'|username']) ? $data['ext'] : $specific_data['data']['line|'.$lid.'|username'];
                $specific_data['data']['line|'.$lid.'|authname'] = !isset($specific_data['data']['line|'.$lid.'|authname']) ? $data['ext'] : $specific_data['data']['line|'.$lid.'|authname'];
                $specific_data['data']['line|'.$lid.'|secret'] = !isset($specific_data['data']['line|'.$lid.'|secret']) ? $data['secret'] : $specific_data['data']['line|'.$lid.'|secret'];
                $specific_data['data']['line|'.$lid.'|server_host'] = !isset($specific_data['data']['line|'.$lid.'|server_host']) ? $endpoint->global_cfg['srvip'] : $specific_data['data']['line|'.$lid.'|server_host'];
                $specific_data['data']['line|'.$lid.'|server_port'] = !isset($specific_data['data']['line|'.$lid.'|server_port']) ? 5060 : $specific_data['data']['line|'.$lid.'|server_port'];
                $specific_data['data']['line|'.$lid.'|line_enabled'] = !isset($specific_data['data']['line|'.$lid.'|line_enabled']) ? TRUE : $specific_data['data']['line|'.$lid.'|line_enabled'];
        }

        $specific_data['data']['ntp'] = isset($specific_data['data']['ntp']) ? $specific_data['data']['ntp'] : $endpoint->global_cfg['ntp'];
        $specific_data['data']['use_dhcp'] = isset($specific_data['data']['use_dhcp']) ? TRUE : FALSE;

        $html_array = $endpoint->generate_gui_html($merged, $specific_data, TRUE, NULL, $lines);
    }

    $endpoint->prepare_message_box();
    $tpl->assign("html_array", $html_array);
    echo $tpl->draw( 'specifics_pop' );
}

// The global settings overrides are in the "Server settings" dialog of the
// template editor
// (views/epm_templates/editor.views.dialog.cfg.global.php). The ajax
// commands custom_config_get_gloabl, _update_gloabl and _reset_gloabl drive
// it.

if($_REQUEST['pop_type'] == "alt_cfg_edit") {
    $value = isset($_POST['value']) ? $_POST['value'] : $_REQUEST['value'];
    preg_match('/^(\d*)\_(.*)/i', $value, $matches);

    $cfg_id = isset($matches[1]) ? $matches[1] : null;
    $cfg_filename = isset($matches[2]) ? $matches[2] : null;

    if($_REQUEST['custom'] == 0) {
        if($cfg_id != 0) {
            //SQL Config Files
            if(isset($_REQUEST['button_save'])) {
                $sql = 'UPDATE endpointman_custom_configs SET data = ? WHERE id = ?';
                $sql_p = array($_REQUEST['config_text'], $cfg_id);
                epm_sql($sql, 'query', $sql_p);
                $endpoint->message['alt_config'] = _("The file is saved as your version.");
            }
            $sql = 'SELECT * FROM endpointman_custom_configs WHERE id =?';
            $sql_p = array($cfg_id);
            $row = epm_sql($sql, 'getRow', $sql_p);
            $tpl->assign("save_as_name_value", $row['name']);
            $tpl->assign("filename", $row['original_name']);
            // Do not escape the value here. views/tpl/alt_config_popup.php escapes it
            // where it prints it. A second escape prints the entities.
            $tpl->assign("config_data", $row['data']);
            $tpl->assign("allow_hdfiles",$endpoint->global_cfg['allow_hdfiles']);
            $tpl->assign("value", $value);

        } else {
            //HD Config Files
            $sql = 'SELECT endpointman_brand_list.directory, endpointman_product_list.cfg_dir FROM endpointman_brand_list, endpointman_product_list WHERE endpointman_brand_list.id = endpointman_product_list.brand AND endpointman_product_list.id = (SELECT product_id FROM endpointman_template_list WHERE id = ?)';
            $sql_p = array($_REQUEST['tid']);
            $row = epm_sql($sql, 'getRow', $sql_p);
            $file=$epm->PHONE_MODULES_PATH.'endpoint/'.$row['directory']."/".$row['cfg_dir']."/".$cfg_filename;

            if((isset($_REQUEST['button_save'])) && ($endpoint->global_cfg['allow_hdfiles'])) {
                file_put_contents($file, $_REQUEST['config_text']);
                $endpoint->message['alt_config'] = _("The package file is saved. A brand update can overwrite it.");
                $contents = file_get_contents($file);
            } elseif((isset($_REQUEST['button_save'])) && (!$endpoint->global_cfg['allow_hdfiles'])) {
                $time = time();
                $sql = 'SELECT endpointman_template_list.name, endpointman_template_list.config_files_override, endpointman_template_list.product_id FROM endpointman_template_list WHERE endpointman_template_list.id = ?';
                $sql_p = array($_REQUEST['tid']);
                $row = epm_sql($sql, 'getRow', $sql_p);
                $config_fs = epm_blob_decode($row['config_files_override']);

                $sql = 'INSERT INTO endpointman_custom_configs (name, original_name, product_id, data) VALUES (?,?,?,?)';
                $sql_p = array($row['name'] . '_' . $time, $cfg_filename, $row['product_id'], $_REQUEST['config_text']);
                epm_sql($sql, 'query', $sql_p);
                $endpoint->message['alt_config'] = _("The file is saved as your version.");
                $new_id = $endpoint->eda->sql('SELECT last_insert_id()','getOne');
                $sql = 'SELECT * FROM endpointman_custom_configs WHERE id =?';
                $sql_p = array($new_id);
                $row = epm_sql($sql, 'getRow', $sql_p);

                $contents = $row['data'];

                // The popup parses `value` as <id>_<filename>, so return that form. $row is
                // the new row of endpointman_custom_configs, which has no mac column.
                $value = $new_id . '_' . $cfg_filename;

                $config_fs = is_array($config_fs) ? $config_fs : array();

                $file = $row['original_name'];
                $row['original_name'] = str_replace(".","_",$row['original_name']);
                $config_fs[$row['original_name']] = $new_id;
                $config_files = epm_blob_encode($config_fs);
                $sql = 'UPDATE endpointman_template_list SET config_files_override = ? WHERE id = ?';
                $sql_p = array($config_files, $_REQUEST['tid']);
                epm_sql($sql, 'query', $sql_p);
            } else {
                $contents = file_get_contents($file);
            }

            // The view escapes this value (see the note above).
            $tpl->assign("config_data", $contents);
            $tpl->assign("location", $file);
            $tpl->assign("file", basename($file));
            $tpl->assign("allow_hd", $endpoint->global_cfg['allow_hdfiles']);
            $tpl->assign("value", $value);

        }
    } else {
        if($cfg_id != 0) {
            if(isset($_REQUEST['button_save'])) {
                $sql = 'UPDATE endpointman_custom_configs SET data = ? WHERE id = ?';
                $sql_p = array($_REQUEST['config_text'], $cfg_id);
                epm_sql($sql, 'query', $sql_p);
                $endpoint->message['alt_config'] = _("The file is saved as your version.");
            }
            $sql = 'SELECT * FROM endpointman_custom_configs WHERE id =?';
            $sql_p = array($cfg_id);
            $row = epm_sql($sql, 'getRow', $sql_p);
            $file = "SQL/".$row['original_name'];
            $tpl->assign("file", basename($file));
            $tpl->assign("save_as_name_value", $row['name']);
            $tpl->assign("filename", $row['original_name']);
            // Do not escape the value here. views/tpl/alt_config_popup.php escapes it
            // where it prints it. A second escape prints the entities.
            $tpl->assign("config_data", $row['data']);
            $tpl->assign("allow_hdfiles",$endpoint->global_cfg['allow_hdfiles']);
            $tpl->assign("value", $value);
        } else {
            $sql = 'SELECT endpointman_brand_list.directory, endpointman_product_list.cfg_dir FROM endpointman_brand_list, endpointman_product_list WHERE endpointman_brand_list.id = endpointman_product_list.brand AND endpointman_product_list.id = (SELECT endpointman_model_list.product_id FROM endpointman_model_list, endpointman_mac_list WHERE endpointman_mac_list.model = endpointman_model_list.id AND endpointman_mac_list.id = ?)';
            $sql_p = array($_REQUEST['tid']);
            $row = epm_sql($sql, 'getRow', $sql_p);
            $file=$epm->PHONE_MODULES_PATH.'endpoint/'.$row['directory']."/".$row['cfg_dir']."/".$cfg_filename;

            if((isset($_REQUEST['button_save'])) && ($endpoint->global_cfg['allow_hdfiles'])) {
                file_put_contents($file, $_REQUEST['config_text']);
                $endpoint->message['alt_config'] = _("The package file is saved. A brand update can overwrite it.");
                $contents = file_get_contents($file);
            } elseif((isset($_REQUEST['button_save'])) && (!$endpoint->global_cfg['allow_hdfiles'])) {
                $time = time();
                $sql = 'SELECT endpointman_mac_list.mac, endpointman_mac_list.config_files_override, endpointman_model_list.product_id FROM endpointman_mac_list, endpointman_model_list WHERE endpointman_mac_list.model = endpointman_model_list.id AND endpointman_mac_list.id = ?';
                $sql_p = array($_REQUEST['tid']);
                $row = epm_sql($sql, 'getRow', $sql_p);
                $config_fs = epm_blob_decode($row['config_files_override']);

                $sql = 'INSERT INTO endpointman_custom_configs (name, original_name, product_id, data) VALUES (?,?,?,?)';
                $sql_p = array($row['mac'] . '_' . $time, $cfg_filename, $row['product_id'], $_REQUEST['config_text']);
                epm_sql($sql, 'query', $sql_p);
                $endpoint->message['alt_config'] = _("The file is saved as your version.");
                $new_id = $endpoint->eda->sql('SELECT last_insert_id()','getOne');
                $sql = 'SELECT * FROM endpointman_custom_configs WHERE id =?';
                $sql_p = array($new_id);
                $row = epm_sql($sql, 'getRow', $sql_p);

                $contents = $row['data'];

                // The popup parses `value` as <id>_<filename>, so return that form. $row is
                // the new row of endpointman_custom_configs, which has no mac column.
                $value = $new_id . '_' . $cfg_filename;

                $config_fs = is_array($config_fs) ? $config_fs : array();

                $file = $row['original_name'];
                $row['original_name'] = str_replace(".","_",$row['original_name']);
                $config_fs[$row['original_name']] = $new_id;
                $config_files = epm_blob_encode($config_fs);
                $sql = 'UPDATE endpointman_mac_list SET config_files_override = ? WHERE id = ?';
                $sql_p = array($config_files, $_REQUEST['tid']);
                epm_sql($sql, 'query', $sql_p);
            } else {
                $contents = file_get_contents($file);
            }

            // The view escapes this value (see the note above).
            $tpl->assign("config_data", $contents);
            $tpl->assign("location", $file);
            $tpl->assign("file", basename($file));
            $tpl->assign("allow_hd", $endpoint->global_cfg['allow_hdfiles']);
            $tpl->assign("value", $value);


        }
    }


    // Each write in this branch needs button_save or button_save_as. When one of
    // them is present, the page that opened the popup shows an old file list. The
    // template calls opener.Reload() in the response to the save, which is the
    // first moment when the write is complete.
    $tpl->assign('saved', isset($_REQUEST['button_save']) || isset($_REQUEST['button_save_as']));

    $endpoint->prepare_message_box();

    echo $tpl->draw( 'alt_config_popup' );
}
