<?PHP
/**
 * Unidata Files
 *
 * @author Graeme Moss
 * @modified
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */
class endpoint_unidata_base extends endpoint_base {

    public $brand_name = 'unidata';

    function prepare_for_generateconfig() {
        $this->mac = strtolower($this->mac);
        parent::prepare_for_generateconfig();
    }

    function reboot() {
        $this->send_notify('reboot-yealink');
    }

}
