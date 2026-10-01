<?PHP
/**
 * Cisco Base File
 *
 * @author Andrew Nagy
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */
class endpoint_cisco_base extends endpoint_base {

    public $brand_name = 'cisco';

    function prepare_for_generateconfig() {
        //spa likes lower case letters in its mac address
        $this->mac = strtoupper($this->mac);
		if ($this->family_line == 'sip79x1G' && isset($this->settings['image_name']) && ($this->settings['image_name']!='') && (strpos("loadInformation",$this->settings['image_name'])===FALSE))
			$this->settings['image_name']='<loadInformation>'.$this->settings['image_name'].'</loadInformation>';
        parent::prepare_for_generateconfig();
        $this->config_file_replacements['$mac'] = strtolower($this->mac);
        $this->config_file_replacements['$model'] = str_replace('SPA', 'spa', strtoupper($this->model));
    }

    function reboot() {
        // Local patch: send NOTIFY over AMI. The old ata18x branch also ran
        // /tftpboot/cfgfmt.linux, a program in a directory that the web server
        // can write. It is removed.
        $options = array(
            'sip99xx' => 'cisco-restart',
            'sip79xx' => 'cisco-check-cfg',
            'ata18x' => 'cisco-check-cfg',
            'spa' => 'spa-reboot',
            'spa5xx' => 'spa-reboot',
            'linksysata' => 'spa-reboot',
        );
        if (isset($options[$this->family_line])) {
            $this->send_notify($options[$this->family_line]);
        }
    }

}
