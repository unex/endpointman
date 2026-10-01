<?php
/**
 * Snom 320, 360, 370 with Exp.Module Provisioning System
 *
 * @author Corrado Mella
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */
class endpoint_snom_3xxEM_phone extends endpoint_snom_base {

	public $family_line = '3xxEM';
        
        function prepare_for_generateconfig() {
            parent::prepare_for_generateconfig();
            $this->mac = strtolower($this->mac);
            
            // Local patch: the old code filled the function keys from
            // `database show SIP` in a shell. That chan_sip data does not exist on
            // Asterisk 21, so the keys stay as the template sets them.
        
        
        }
	
}
