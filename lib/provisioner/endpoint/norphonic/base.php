<?PHP
/**
 * Norphonic Base File
 *
 * @author Andrew Nagy
 * @modified for norphonic phones by Thord Matre
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */
class endpoint_norphonic_base extends endpoint_base {
	
	public $brand_name = 'norphonic';
	
	function prepare_for_generateconfig() {
		//Traphone likes upper case letters in its mac address
		$this->mac = strtoupper($this->mac);
		parent::prepare_for_generateconfig();
		$this->config_file_replacements['$mac']=strtoupper($this->mac);
		$this->config_file_replacements['$model']=strtoupper($this->model);
	}
    function reboot() {
        if (($this->engine == "asterisk") AND ($this->system == "unix")) {
            if ($this->family_line == "traphone") {
                // Local patch: the registered address comes from the module over AMI,
                // not from `sip show peers` in a shell.
                $ip = $this->device_address();
                if ($ip !== '') {
                    if (function_exists('curl_init')) {
                        $ch = curl_init('http://' . $ip . '/cgi-bin/syscmd.cgi?sys_restart=Restart+phone+software');
                        curl_setopt($ch, CURLOPT_USERPWD, 'phoneadmin:blank');
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                        curl_exec($ch);
                        curl_close($ch);
                    }
                }
            }
        }
    }

}
