<?PHP
/**
 * Thomson Base File
 *
 * @author Andrew Nagy
 * @modified by Thord Matre
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */
class endpoint_thomson_base extends endpoint_base {

    public $brand_name = 'thomson';
    public $protected_files = array('general_custom.xml');

    function prepare_for_generateconfig() {
        parent::prepare_for_generateconfig();
        $this->mac = strtoupper($this->mac);
    }

    function reboot() {
        $this->send_notify('reboot-yealink');
    }

}
