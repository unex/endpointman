<?php

class PageBase {
    public $epm;        //the Endpointman BMO object
    public $freepbx;    //FreePBX BMO
    public $db;         //Database from FreePBX
    public $config;     //FreePBX Config
    public $configmod;  //this module's Config wrapper

    public $MODULES_PATH;
	public $LOCAL_PATH;
	public $PHONE_MODULES_PATH;

	public function __construct($epm) {
        $this -> epm        = $epm;
        $this -> freepbx    = $epm -> freepbx;
        $this -> db         = $epm -> db;
        $this -> config     = $epm -> config;
        $this -> configmod  = $epm -> configmod;
        // $this -> epm_config = $epm -> epm_config;

        $this -> MODULES_PATH       = $epm -> MODULES_PATH;
        $this -> LOCAL_PATH         = $epm -> LOCAL_PATH;
        $this -> PHONE_MODULES_PATH = $epm -> PHONE_MODULES_PATH;
    }
}
