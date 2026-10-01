<?PHP

/**
 * Polycom Base File
 *
 * @author Andrew Nagy
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */
abstract class endpoint_polycom_base extends endpoint_base {

    public $brand_name = 'polycom';

    function config_files() {
        $result = parent::config_files();
		return $result;
	}
        
    function prepare_for_generateconfig() {
        $this->mac = strtolower($this->mac);
        parent::prepare_for_generateconfig();
    }

    function reboot() {
        $this->send_notify('polycom-check-cfg');
    }

}