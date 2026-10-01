<?PHP

/**
 * Endpoint Manager FreePBX Hooks File
 *
 * @author Andrew Nagy
 * @license MPL / GPLv2 / LGPL
 * @package Endpoint Manager
 */
function endpointman_get_config($engine) {
    global $db;
    global $ext;
    global $core_conf;

    switch ($engine) {
        case "asterisk":
            if (isset($core_conf) && is_a($core_conf, "core_conf") && (method_exists($core_conf, 'addSipNotify'))) {
                $core_conf->addSipNotify('polycom-check-cfg', array('Event' => 'check-sync', 'Content-Length' => '0'));
                $core_conf->addSipNotify('polycom-reboot', array('Event' => 'check-sync', 'Content-Length' => '0'));
                $core_conf->addSipNotify('sipura-check-cfg', array('Event' => 'resync', 'Content-Length' => '0'));
                $core_conf->addSipNotify('grandstream-check-cfg', array('Event' => 'sys-control'));
                $core_conf->addSipNotify('cisco-check-cfg', array('Event' => 'check-sync', 'Content-Length' => '0'));
                $core_conf->addSipNotify('reboot-snom', array('Event' => 'reboot', 'Content-Length' => '0'));
                $core_conf->addSipNotify('aastra-check-cfg', array('Event' => 'check-sync', 'Content-Length' => '0'));
                $core_conf->addSipNotify('linksys-cold-restart', array('Event' => 'reboot_now', 'Content-Length' => '0'));
                $core_conf->addSipNotify('linksys-warm-restart', array('Event' => 'restart_now', 'Content-Length' => '0'));
                $core_conf->addSipNotify('spa-reboot', array('Event' => 'reboot', 'Content-Length' => '0'));
                $core_conf->addSipNotify('reboot-yealink', array('Event' => 'check-sync\;reboot=true', 'Content-Length' => '0'));
			    $core_conf->addSipNotify('reboot-gigaset', array('Event' => 'check-sync;reboot=true', 'Content-Length' => '0'));
				$core_conf->addSipNotify('panasonic-check-cfg', array('Event' => 'check-sync', 'Content-Length' => '0'));
				$core_conf->addSipNotify('snom-check-cfg', array('Event' => 'check-sync', 'Content-Length' => '0'));
            }
            break;
    }
}

function endpointman_hookProcess_core($viewing_itemid, $request) {

}

function endpointman_module_install_check_callback($mods = array()) {
    global $active_modules;

    $ret = array();
    $current_mod = 'endpointman';
    $conflicting_mods = array('restart');

	foreach($mods as $k => $v) {
		if (in_array($k, $conflicting_mods) && !in_array($active_modules[$current_mod]['status'],array(MODULE_STATUS_NOTINSTALLED,MODULE_STATUS_BROKEN))) {
			$ret[] = $v['name'];
		}
	}

	if (!empty($ret)) {
		$modules = implode(',',$ret);
		return _('Failed to install ' . $modules . ' due to the following conflicting module(s): ' . $active_modules[$current_mod]['displayname']);
	}

	return TRUE;
}
