<?php
/**
 * The Phones page.
 *
 * The page has three forms: the phone list, the add form (?form=add) and the
 * edit form (?sub_type=edit&edit_id=<id>). Each action posts sub_type to this
 * file. The file runs the action, and then shows one of the three forms.
 *
 * The phone list sends the ids of the selected phones in selected[]. Each id
 * is endpointman_mac_list.id. A phone with two lines has two rows in the list,
 * so the same id can come two times.
 *
 * @author Andrew Nagy
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */

if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }


global $endpoint, $debug;
$debug = NULL;
$endpoint = FreePBX::Endpointman() -> _get_page('devices');   // this view's own page class
$tpl = FreePBX::Endpointman() -> tpl;   // Endpointman\View, owned by Endpointman

$no_add = FALSE;
$final = NULL;
$searched = NULL;
$mode = NULL;

$family_list = $endpoint->eda->all_products();
$full_device_list = $endpoint->eda->all_devices();

if ((empty($family_list)) && (empty($full_device_list))) {
    $endpoint->message['page:devices_manager'] = _("No brand is installed.") . ' <a href="config.php?display=endpointman&amp;view=brands">' . _("Install a brand on the Brands page.") . '</a>';
    $no_add = TRUE;
    $endpoint->global_cfg['new'] = 1;
} elseif ($endpoint->global_cfg['srvip'] == "") {
    $endpoint->error['page:devices_manager'] = _("The IP address of the phone server is not set. The phones cannot get their configs.") . ' <a href="config.php?display=endpointman&amp;view=settings">' . _("Set it on the Settings page.") . '</a>';
    $no_add = TRUE;
}

/**
 * Get the selected phone ids from the request.
 *
 * @return array unique integer ids, maybe empty
 */
$selected_ids = function () {
    $ids = array();
    if (isset($_REQUEST['selected']) && is_array($_REQUEST['selected'])) {
        foreach ($_REQUEST['selected'] as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $ids[(int) $id] = (int) $id;
            }
        }
    }
    return array_values($ids);
};

/**
 * Write the configs of one phone, and restart it when $reboot is set.
 *
 * The line descriptions come from the FreePBX extensions, which can change.
 * get_phone_info() reads the new values, and this function saves them.
 */
$rebuild = function ($mac_id, $reboot) use ($endpoint) {
    $phone_info = $endpoint->get_phone_info($mac_id);
    if (empty($phone_info)) {
        return;
    }
    if (isset($phone_info['line']) && is_array($phone_info['line'])) {
        foreach ($phone_info['line'] as $line) {
            $sql = 'UPDATE endpointman_line_list SET description = ? WHERE luid = ?';
            epm_sql($sql, 'query', array($line['description'], $line['luid']));
        }
    }
    $endpoint->prepare_configs($phone_info, $reboot);
};

$reboot = isset($_REQUEST['reboot']);

// These actions add phones, so they need a brand and the server address.
// The other actions work on phones that exist.
$needs_add = array('add', 'go', 'add_selected_phones');
$sub_type = (isset($_REQUEST['sub_type']) && is_string($_REQUEST['sub_type'])) ? $_REQUEST['sub_type'] : '';
if ($no_add && in_array($sub_type, $needs_add, true)) {
    $sub_type = '';
}
$sub_type_sub = (isset($_REQUEST['sub_type_sub']) && is_string($_REQUEST['sub_type_sub'])) ? $_REQUEST['sub_type_sub'] : '';

switch ($sub_type) {
    case "edit":
        $mode = "EDIT";
        switch ($sub_type_sub) {
            case "add_line_x":
                $endpoint->add_line($_REQUEST['edit_id']);
                break;
            case "button_edit":
                // Save the model, and then open the template editor.
                if (empty($_REQUEST['edit_id'])) {
                    $endpoint->error['page:devices_manager'] = _("Select a phone to edit.");
                } else {
                    $sql = 'UPDATE endpointman_mac_list SET model = ? WHERE id = ?';
                    epm_sql($sql, 'query', array($_REQUEST['model_list'], $_REQUEST['edit_id']));

                    redirect(FreePBX::Endpointman()->_get_page('templates')
                        ->editorUrl($_REQUEST['edit_id'], $_REQUEST['template_list']));
                }
                break;
            case "button_save":
                $sql = 'SELECT * FROM endpointman_line_list WHERE mac_id = ?';
                $lines_list = epm_sql($sql, 'getAll', array($_REQUEST['edit_id']));

                foreach ($lines_list as $row) {
                    $sql = 'SELECT description FROM devices WHERE id = ?';
                    $name = epm_sql($sql, 'getOne', array($_REQUEST['ext_list_' . $row['luid']]));

                    $sql = 'UPDATE endpointman_line_list SET ipei = ?, line = ?, ext = ?, description = ? WHERE luid = ?';
                    $sql_p = array($_REQUEST['ipei_' . $row['luid']], $_REQUEST['line_list_' . $row['luid']], $_REQUEST['ext_list_' . $row['luid']], $name, $row['luid']);
                    epm_sql($sql, 'query', $sql_p);
                }

                $sql = 'UPDATE endpointman_mac_list SET template_id = ?, model = ? WHERE id = ?';
                epm_sql($sql, 'query', array($_REQUEST['template_list'], $_REQUEST['model_list'], $_REQUEST['edit_id']));

                $endpoint->prepare_configs($endpoint->get_phone_info($_REQUEST['edit_id']));

                $endpoint->message['edit_save'] = _("The phone is saved, and its configs are written.");
                $mode = NULL;
                break;
            case "delete":
                // edit_id is the line id here. Show the phone of the line again.
                $sql = 'SELECT mac_id FROM endpointman_line_list WHERE luid = ?';
                $mac_id = epm_sql($sql, 'getOne', array($_REQUEST['edit_id']));

                $endpoint->delete_line($_REQUEST['edit_id'], FALSE);
                $_REQUEST['edit_id'] = $mac_id;
                break;
        }
        if ($mode === "EDIT") {
            $edit_row = $endpoint->get_phone_info($_REQUEST['edit_id']);
            $edit_row['id'] = $_REQUEST['edit_id'];
        }
        break;

    case "add":
        $mac_id = $endpoint->add_device($_REQUEST['mac'], $_REQUEST['model_list'], $_REQUEST['ext_list'], $_REQUEST['template_list'], $_REQUEST['line_list']);
        if ($mac_id) {
            $endpoint->prepare_configs($endpoint->get_phone_info($mac_id));
        } else {
            // Show the add form again, so that the admin can correct the values.
            $mode = "ADD";
        }
        break;

    case "delete_selected_phones":
        $ids = $selected_ids();
        if (empty($ids)) {
            $endpoint->error['page:devices_manager'] = _("Select one or more phones.");
        } else {
            foreach ($ids as $id) {
                $endpoint->delete_device($id);
            }
            $endpoint->message['page:devices_manager'] = sprintf(ngettext("%d phone is deleted.", "%d phones are deleted.", count($ids)), count($ids));
        }
        break;

    case "delete_device":
        $endpoint->delete_device($_REQUEST['edit_id']);
        $endpoint->message['page:devices_manager'] = _("The phone is deleted.");
        break;

    case "rebuild_selected_phones":
        $ids = $selected_ids();
        if (empty($ids)) {
            $endpoint->error['page:devices_manager'] = _("Select one or more phones.");
        } else {
            foreach ($ids as $id) {
                $rebuild($id, $reboot);
            }
            $endpoint->message['page:devices_manager'] = $reboot
                ? _("The configs of the selected phones are written, and the phones restart.")
                : _("The configs of the selected phones are written.");
        }
        break;

    case "rebuild_configs_for_all_phones":
        $sql = "SELECT endpointman_mac_list.id FROM endpointman_mac_list, endpointman_brand_list, endpointman_product_list, endpointman_model_list WHERE endpointman_brand_list.id = endpointman_product_list.brand AND endpointman_product_list.id = endpointman_model_list.product_id AND endpointman_mac_list.model = endpointman_model_list.id ORDER BY endpointman_product_list.cfg_dir ASC";
        $mac_list = epm_sql($sql, 'getAll', array());
        foreach ($mac_list as $data) {
            $rebuild($data['id'], $reboot);
        }
        $endpoint->message['page:devices_manager'] = $reboot
            ? _("The configs of all phones are written, and the phones restart.")
            : _("The configs of all phones are written.");
        break;

    case "reboot_brand":
        if (empty($_REQUEST['rb_brand'])) {
            $endpoint->error['page:devices_manager'] = _("Select a brand.");
            break;
        }
        $sql = 'SELECT endpointman_mac_list.id FROM endpointman_mac_list , endpointman_model_list , endpointman_brand_list , endpointman_product_list WHERE endpointman_brand_list.id = endpointman_model_list.brand AND endpointman_model_list.id = endpointman_mac_list.model AND endpointman_model_list.product_id = endpointman_product_list.id AND endpointman_brand_list.id = ? ORDER BY endpointman_product_list.cfg_dir ASC';
        $data = epm_sql($sql, 'getAll', array($_REQUEST['rb_brand']));
        if (empty($data)) {
            $endpoint->error['page:devices_manager'] = _("This brand has no phones.");
            break;
        }
        if (!class_exists('ProvisionerConfig')) {
            // The provisioner library ships with the module.
            require_once(FreePBX::Endpointman()->LOCAL_PATH . 'lib/provisioner/autoload.php');
        }
        foreach ($data as $row) {
            $phone_info = $endpoint->get_phone_info($row['id']);

            $class = "endpoint_" . $phone_info['directory'] . "_" . $phone_info['cfg_dir'] . '_phone';
            $base_class = "endpoint_" . $phone_info['directory'] . '_base';
            $master_class = "endpoint_base";
            // Load the classes in order, because the autoloader of the FreePBX
            // distro does not find them.
            if (!class_exists($master_class)) {
                ProvisionerConfig::endpointsAutoload($master_class);
            }
            if (!class_exists($base_class)) {
                ProvisionerConfig::endpointsAutoload($base_class);
            }
            if (!class_exists($class)) {
                ProvisionerConfig::endpointsAutoload($class);
            }

            $provisioner_lib = new $class();
            $provisioner_lib->root_dir = FreePBX::Endpointman()->PHONE_MODULES_PATH;
            $provisioner_lib->engine = 'asterisk';
            $provisioner_lib->system = 'unix';
            $provisioner_lib->brand_name = $phone_info['directory'];
            $provisioner_lib->family_line = $phone_info['cfg_dir'];

            $provisioner_lib->settings['line'][0] = array('username' => $phone_info['line'][1]['ext'], 'authname' => $phone_info['line'][1]['ext'], 'tech' => $phone_info['line'][1]['tech']);
            $endpoint->attach_reboot_context($provisioner_lib);
            $provisioner_lib->reboot();
            unset($provisioner_lib);
        }
        $endpoint->message['page:devices_manager'] = sprintf(_("The %s phones restart."), epm_esc($phone_info['name']));
        break;

    case "go":
        $endpoint->configmod->save('nmap_search', $_REQUEST['netmask']);
        $endpoint->global_cfg['nmap_search'] = $_REQUEST['netmask'];
        $use_nmap = (isset($_REQUEST['nmap']) && $_REQUEST['nmap'] == 1);
        $temp = $endpoint->discover_new($_REQUEST['netmask'], $use_nmap);

        // discover_new() returns FALSE for an invalid netmask, for a host that cannot
        // read the neighbor table, and for a search that found nothing. foreach()
        // over FALSE is a PHP 8 warning, and Whoops turns it into a 500. Thus check
        // the result first, and give $final a value for the test below.
        $final = array();
        foreach ((is_array($temp) ? $temp : array()) as $key => $data) {
            if ((!$data['endpoint_managed']) AND ($data['brand'])) {
                $final[$key] = $data;
                $final[$key]['id'] = $key;
                $sqln = 'SELECT * FROM endpointman_model_list WHERE enabled = 1 AND brand =?';
                $final[$key]['list'] = epm_sql($sqln, 'getAll', array($data['brand_id']));
            }
        }

        if (!$final) {
            $final = NULL;
            // discover_new() sets its own error when it cannot search: no iproute2, no
            // net-tools, or a netmask that does not parse. Keep that error, and do not
            // replace it with "none found".
            if (empty($endpoint->error['discover_new'])) {
                $endpoint->message['page:devices_manager'] = _("No new phones are found.");
            }
        }
        $searched = 1;
        break;

    case "add_selected_phones":
        $added = 0;
        if (isset($_REQUEST['add']) && is_array($_REQUEST['add'])) {
            foreach ($_REQUEST['add'] as $num) {
                $mac_id = $endpoint->add_device($_REQUEST['mac_' . $num], $_REQUEST['model_list_' . $num], $_REQUEST['ext_list_' . $num]);
                if ($mac_id) {
                    $endpoint->prepare_configs($endpoint->get_phone_info($mac_id), $reboot);
                    $added++;
                }
            }
        }
        if ($added == 0 && empty($endpoint->error)) {
            $endpoint->error['page:devices_manager'] = _("Select one or more phones.");
        }
        break;

    case "change_brand":
        $ids = $selected_ids();
        if (empty($ids)) {
            $endpoint->error['page:devices_manager'] = _("Select one or more phones.");
        } elseif (empty($_REQUEST['model_list_selected']) || !is_numeric($_REQUEST['model_list_selected'])) {
            $endpoint->error['page:devices_manager'] = _("Select a brand and a model.");
        } else {
            foreach ($ids as $id) {
                // A new model has different settings, so the custom values of the
                // old model go.
                $sql = 'UPDATE endpointman_mac_list SET global_custom_cfg_data = \'\', template_id = 0, global_user_cfg_data = \'\', config_files_override = \'\', model = ? WHERE id = ?';
                epm_sql($sql, 'query', array($_REQUEST['model_list_selected'], $id));
                $endpoint->prepare_configs($endpoint->get_phone_info($id), $reboot);
            }
            $endpoint->message['page:devices_manager'] = $reboot
                ? _("The model of the selected phones is changed, and the phones restart.")
                : _("The model of the selected phones is changed.");
        }
        break;

    case "rebuild_reboot":
    case "mrebuild_reboot":
        // Apply a template to all phones of a family (rebuild_reboot) or of a
        // model (mrebuild_reboot).
        if ($sub_type === "rebuild_reboot") {
            $target = isset($_REQUEST['product_select']) ? $_REQUEST['product_select'] : '';
            $template = isset($_REQUEST['template_selector']) ? $_REQUEST['template_selector'] : '';
            $where = 'endpointman_product_list.id = ?';
            $no_target = _("Select a family.");
        } else {
            $target = isset($_REQUEST['model_select']) ? $_REQUEST['model_select'] : '';
            $template = isset($_REQUEST['model_template_selector']) ? $_REQUEST['model_template_selector'] : '';
            $where = 'endpointman_model_list.id = ?';
            $no_target = _("Select a model.");
        }
        if ($target === '' || $target === '0') {
            $endpoint->error['page:devices_manager'] = $no_target;
        } elseif ($template === '') {
            $endpoint->error['page:devices_manager'] = _("Select a template.");
        } else {
            $sql = 'SELECT endpointman_mac_list.id FROM endpointman_mac_list, endpointman_brand_list, endpointman_product_list, endpointman_model_list WHERE endpointman_brand_list.id = endpointman_product_list.brand AND endpointman_product_list.id = endpointman_model_list.product_id AND endpointman_mac_list.model = endpointman_model_list.id AND ' . $where;
            $data = epm_sql($sql, 'getAll', array($target));
            foreach ($data as $row) {
                $sql = 'UPDATE endpointman_mac_list SET template_id = ? WHERE id = ?';
                epm_sql($sql, 'query', array($template, $row['id']));
                $rebuild($row['id'], $reboot);
            }
            $endpoint->message['page:devices_manager'] = $reboot
                ? _("The template is applied. The configs are written, and the phones restart.")
                : _("The template is applied, and the configs are written.");
        }
        break;
}

if ($mode === NULL && isset($_REQUEST['form']) && $_REQUEST['form'] === 'add' && !$no_add) {
    $mode = "ADD";
}

// The phone list. Each line of a phone is one row.
$list = array();
$devices_status = $endpoint->registration_status();

foreach ($endpoint->eda->all_devices() as $devices_row) {
    $device = $devices_row;
    if (($devices_row['template_id'] == 0) && (!empty($devices_row['global_custom_cfg_data']))) {
        $device['template_name'] = _("Custom");
    } elseif ($devices_row['template_id'] == 0) {
        $device['template_name'] = _("None");
    } else {
        $sql = 'SELECT name FROM endpointman_template_list WHERE id = ?';
        $device['template_name'] = epm_sql($sql, 'getOne', array($devices_row['template_id']));
    }
    $device['model_disabled'] = !$devices_row['enabled'];
    $device['line'] = array();
    foreach ($endpoint->eda->get_lines_from_device($devices_row['id']) as $line_row) {
        $ext = $line_row['ext'];
        $line_row['registered'] = isset($devices_status[$ext]['status']) && $devices_status[$ext]['status'] === TRUE;
        $line_row['ip'] = isset($devices_status[$ext]['ip']) ? $devices_status[$ext]['ip'] : '';
        $line_row['port'] = isset($devices_status[$ext]['port']) ? $devices_status[$ext]['port'] : '';
        $device['line'][] = $line_row;
    }
    $list[] = $device;
}

// Phones in the database with no model.
foreach ($endpoint->eda->all_unknown_devices() as $row) {
    $brand_info = $endpoint->get_brand_from_mac($row['mac']);
    $row['name'] = is_array($brand_info) ? $brand_info['name'] : '';
    $row['model'] = _("Unknown");
    $row['model_disabled'] = FALSE;
    $row['template_name'] = _("None");
    $row['line'] = array();
    $list[] = $row;
}

// The options of the "apply a template" forms.
$sql = "SELECT DISTINCT endpointman_product_list.* FROM endpointman_product_list, endpointman_model_list WHERE endpointman_product_list.id = endpointman_model_list.product_id AND endpointman_model_list.hidden = 0 AND endpointman_model_list.enabled = 1 AND endpointman_product_list.hidden != 1 AND endpointman_product_list.cfg_dir !=  ''";
$product_list = array();
foreach (epm_sql($sql, 'getAll', array()) as $row) {
    $product_list[] = array('value' => $row['id'], 'text' => $row['short_name']);
}

$sql = "SELECT DISTINCT endpointman_model_list.* FROM endpointman_product_list, endpointman_model_list WHERE endpointman_product_list.id = endpointman_model_list.product_id AND endpointman_model_list.hidden = 0 AND endpointman_model_list.enabled = 1 AND endpointman_product_list.hidden != 1 AND endpointman_product_list.cfg_dir !=  ''";
$model_list = array();
foreach (epm_sql($sql, 'getAll', array()) as $row) {
    $model_list[] = array('value' => $row['id'], 'text' => $row['model']);
}

$tpl->assign("list", $list);
$serv_address = !empty($endpoint->global_cfg['nmap_search']) ? $endpoint->global_cfg['nmap_search'] : $_SERVER["SERVER_ADDR"] . '/24';
$tpl->assign("netmask", $serv_address);
$tpl->assign("product_list", $product_list);
$tpl->assign("model_list", $model_list);
$tpl->assign("display_ext", $endpoint->display_registration_list());
$tpl->assign("brand_ava", FreePBX::Endpointman()->brands_available());
$tpl->assign("unmanaged", $final);
$tpl->assign("searched", $searched);
$tpl->assign("no_add", $no_add);
$tpl->assign("mode", $mode);
// After a failed add, the add form shows the typed values again.
$tpl->assign("add_mac", ($sub_type === "add" && isset($_REQUEST['mac']) && is_string($_REQUEST['mac'])) ? $_REQUEST['mac'] : '');
$tpl->assign("add_ipei", ($sub_type === "add" && isset($_REQUEST['ipei']) && is_string($_REQUEST['ipei'])) ? $_REQUEST['ipei'] : '');

if ($mode === "EDIT") {
    $ma = $endpoint->models_available($edit_row['model_id'], $edit_row['brand_id']);
    if ($ma != FALSE) {
        $tpl->assign("edit_id", $edit_row['id']);
        $tpl->assign("template_id", $edit_row['template_id']);
        $tpl->assign("mac", $edit_row['mac']);
        $tpl->assign("name", $edit_row['name']);
        foreach ($edit_row['line'] as $data) {
            $edit_row['line'][$data['line']]['reg_list'] = $endpoint->display_registration_list($data['luid']);
            $edit_row['line'][$data['line']]['line_list'] = $endpoint->linesAvailable($data['luid']);
        }
        $tpl->assign("line_list_edit", $edit_row['line']);
        $tpl->assign("models_ava", $ma);
        $tpl->assign("display_templates", $endpoint->display_templates($edit_row['product_id'], $edit_row['template_id']));
    } else {
        $endpoint->error['page:devices_manager'] = _("All models of this brand are disabled or removed. Enable a model on the Brands page before you edit this phone.");
        $mode = NULL;
        $tpl->assign("mode", NULL);
    }
}

$endpoint->form_mode = ($mode === "EDIT") ? 'edit' : (($mode === "ADD") ? 'add' : '');

$endpoint->prepare_message_box();

echo $tpl->draw(($mode === NULL) ? 'devices_manager' : 'device_form');
