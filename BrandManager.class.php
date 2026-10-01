<?php

class BrandManager {
    public $epm;        //the Endpointman BMO object
    public $system;     //epm_system helper
    public $configmod;  //this module's Config wrapper
    public $UPDATE_PATH;
    public $error;      //error construct

    // public $MODULES_PATH;
	// public $LOCAL_PATH;
	public $PHONE_MODULES_PATH;

	public function __construct($epm) {
        $this -> epm        = $epm;
        $this -> system     = $epm -> system;
        $this -> configmod  = $epm -> configmod;

        // $this -> MODULES_PATH       = $epm -> MODULES_PATH;
        // $this -> LOCAL_PATH         = $epm -> LOCAL_PATH;
        $this -> PHONE_MODULES_PATH = $epm -> PHONE_MODULES_PATH;

        $this -> UPDATE_PATH = $this -> configmod -> get('update_server');
    }

    function brand_update_check($brand_name_find = NULL) {
		if ($brand_name_find == NULL) { return $this->brand_update_check_all(); }

		$sql = 'SELECT * FROM  endpointman_brand_list WHERE directory = ?';
		$sql_p = array($brand_name_find);
		$row = epm_sql($sql, 'getRow', $sql_p);

		$out = array();
		if  (! isset($row['directory']))
		{
			$out['update'] = -2;
		}
		else
		{
			if (file_exists($this->PHONE_MODULES_PATH . "endpoint/" . $row['directory'] . "/brand_data.json"))
			{
				$temp = $this->file2json($this->PHONE_MODULES_PATH . "endpoint/" . $row['directory'] . "/brand_data.json");
				$temp = $temp['data']['brands'];

				$version = $temp['last_modified'];
				$last_mod = "";
				foreach ($temp['family_list'] as $list) {
					$last_mod = max($last_mod, $list['last_modified']);
				}
				$last_mod = max($last_mod, $version);
				$version = $last_mod;

				if ($row['cfg_ver'] < $version) {
					$out['update'] = 1;
					$out['update_vers'] = $version;
				} else {
					$out['update'] = NULL;
					$out['update_vers'] = $version;
				}
			}
			else {
				$out['update'] = -1;
			}
		}
		return $out;
	}



	function brand_update_check_all()
	{
		$temp = $this->file2json($this->PHONE_MODULES_PATH . 'endpoint/master.json');
		$endpoint_package = $temp['data']['package'];
		$endpoint_last_mod = $temp['data']['last_modified'];

		$version = array();
		$out = $temp['data']['brands'];
		foreach ($out as $data) {
			if (file_exists($this->PHONE_MODULES_PATH . "endpoint/" . $data['directory'] . "/brand_data.json")) {
				$temp = $this->file2json($this->PHONE_MODULES_PATH . "endpoint/" . $data['directory'] . "/brand_data.json");
				$temp = $temp['data']['brands'];

				$brand_name = $temp['directory'];
				$version[$brand_name] = $temp['last_modified'];
				$last_mod = "";
				foreach ($temp['family_list'] as $list) {
					$last_mod = max($last_mod, $list['last_modified']);
				}
				$last_mod = max($last_mod, $version[$brand_name]);
				$version[$brand_name] = $last_mod;
			}
		}

		$sql = 'SELECT * FROM  endpointman_brand_list WHERE id > 0';
		$sql_p = array();
		$row = epm_sql($sql, 'getAll', $sql_p);
		foreach ($row as $ava_brands) {
			$key = $this->system->arraysearchrecursive($ava_brands['directory'], $out, 'directory');

			if ($key === FALSE) {
				$tmp = $ava_brands;
				$tmp['update'] = -1;
				$out[] = $tmp;
			}
			else {
				$key = $key[0];
				$brand_name = $ava_brands['directory'];
				if (! isset($version[$brand_name])) { $version[$brand_name] = 0; }
				if ($ava_brands['cfg_ver'] < $version[$brand_name]) {
					$out[$key]['update'] = 1;
					$out[$key]['update_vers'] = $version[$brand_name];
				} else {
					$out[$key]['update'] = NULL;
				}
			}
		}
		return $out;
	}



	/**
     * Check for new packges for brands. These packages will include phone models and such which the user can remove if they want
     * This function will alos auto-update the provisioner.net library incase anything has changed
     * @return array An array of all the brands/products/models and information about what's  enabled, installed or otherwise
     */
    function update_check($echomsg = false, &$error=array()) {
    	if ($echomsg == true) {
    		$master_result = $this->system->download_file_with_progress_bar($this->UPDATE_PATH . "master.json", $this->PHONE_MODULES_PATH . "endpoint/master.json");
    	} else {
    		$master_result = $this->system->download_file($this->UPDATE_PATH . "master.json", $this->PHONE_MODULES_PATH . "endpoint/master.json");
    	}


        if (!$master_result || !file_exists($this->PHONE_MODULES_PATH . "endpoint/master.json")) {
        	$error['brand_update_check_master'] = _("Error: Not able to connect to repository. Using local master file instead.");
        	if ($echomsg == true ) {
        		out($error['brand_update_check_master']);
        	}
        }

        $temp = $this->file2json($this->PHONE_MODULES_PATH . 'endpoint/master.json');

        // Without a download and without an older copy, there is no brand list. This
        // happens on a first install when the web server cannot write endpoint/.
        if (!is_array($temp) || !isset($temp['data']['brands']) || !is_array($temp['data']['brands'])) {
        	$error['brand_update_check_master_file'] = sprintf(_("Error: No brand list. Make sure that '%s' exists and that the web server can write to it. 'fwconsole chown' sets the correct owner."), $this->PHONE_MODULES_PATH . 'endpoint/');
        	if ($echomsg == true ) {
        		out($error['brand_update_check_master_file']);
        	}
        	return;
        }

        // The provisioner library ships with the module in lib/provisioner/. Thus
        // download only brand data below.
        //
        // update_status is the flag of the backend for "rebuild in progress, try
        // later". A failed read must not look like a rebuild. Thus hide its error and
        // treat it as "no rebuild".
        $contents = @file_get_contents($this->UPDATE_PATH . "update_status");

        if ($contents != '1') {
            $out = $temp['data']['brands'];
            //Assume that if we can't connect and find the master file then why should we try to find every other file.
            if ($master_result) {
            	$sql = 'SELECT * FROM  endpointman_brand_list WHERE id > 0';
            	$sql_p = array();
                $row = epm_sql($sql, 'getAll', $sql_p);
                foreach ($out as $data) {

                    // $data comes from the downloaded master.json, so bind the directory. Do not
                    // put it into the SQL text.
                    $local = epm_sql('SELECT local FROM endpointman_brand_list WHERE directory = ?', 'getOne', array($data['directory']));
                    if (!$local) {
                    	if ($echomsg == true) {
                    		out(sprintf(_("Update Brand (%s):"), $data['name']));
                    		$result = $this->system->download_file_with_progress_bar($this->UPDATE_PATH . $data['directory'] . "/" . $data['directory'] . ".json", $this->PHONE_MODULES_PATH . "endpoint/" . $data['directory'] . "/brand_data.json");
                    	} else {
                    		$result = $this->system->download_file($this->UPDATE_PATH . $data['directory'] . "/" . $data['directory'] . ".json", $this->PHONE_MODULES_PATH . "endpoint/" . $data['directory'] . "/brand_data.json");
                    	}
                        if (!$result) {
                        	$error['brand_update_check'] = sprintf(_("Not able to connect to repository. Using local brand [%s] file instead."), $data['name']);
                        	if ($echomsg == true ) {
                        		out($error['brand_update_check']);
                        	}
                        }
                    }

                    if (file_exists($this->PHONE_MODULES_PATH . "endpoint/" . $data['directory'] . "/brand_data.json")) {
                        $temp = $this->file2json($this->PHONE_MODULES_PATH . "endpoint/" . $data['directory'] . "/brand_data.json");
                        $temp = $temp['data']['brands'];
						if (array_key_exists('oui_list', $temp)) {
                        	foreach ($temp['oui_list'] as $oui) {
								$sql = 'REPLACE INTO endpointman_oui_list (`oui`, `brand`, `custom`) VALUES (?, ?, \'0\')';
								$sql_p = array($oui, $temp['brand_id']);
                           		epm_sql($sql, 'query', $sql_p);
							}
						}
                        $brand_name = $temp['directory'];
                        $version[$brand_name] = $temp['last_modified'];
                        $last_mod = "";
                        foreach ($temp['family_list'] as $list) {
                            $last_mod = max($last_mod, $list['last_modified']);
                        }
                        $last_mod = max($last_mod, $version[$brand_name]);
                        $version[$brand_name] = $last_mod;

						if (!($this->system->arraysearchrecursive($brand_name, $row, 'directory')))
						{
							$sql = 'SELECT directory FROM endpointman_brand_list where id = ?';
							$sql_p = array($temp['brand_id']);
							$datoif = epm_sql($sql, 'getOne', $sql_p);
							if ($datoif != "") {
								$error['brand_update_id_exist_other_brand'] = sprintf(_("You can not add the mark (%s) as the ID (%d) already exists in the database!"), $temp['name'], $temp['brand_id']);
								if ($echomsg == true ) {
									out($error['brand_update_id_exist_other_brand']);
								}
							}
							else {
                            	$sql = 'INSERT INTO endpointman_brand_list (id, name, directory, cfg_ver) VALUES (?, ?, ?, ?)';
                            	$sql_p = array($temp['brand_id'], $temp['name'], $temp['directory'], $version[$brand_name]);
                            	epm_sql($sql, 'query', $sql_p);
							}
                        } else {
                            //in database already!
                        }
                    } else {
                    	$error['brand_update_check_local_file'] = sprintf(_("Error: No Local File for %s !"), $data['name'])."<br />"._("A brand package can be uploaded by hand from Advanced Settings, Manual Upload.");
                    	if ($echomsg == true ) {
                    		out($error['brand_update_check_local_file']);
                    	}
                    }
                }

                foreach ($row as $ava_brands) {
					$key = $this->system->arraysearchrecursive($ava_brands['directory'], $out, 'directory');
                    if ($key === FALSE) {
						$this->remove_brand($ava_brands['id']);
                    } else {
                        $key = $key[0];
                        $brand_name = $ava_brands['directory'];
                        //TODO: This seems old
                        if ($ava_brands['cfg_ver'] < $version[$brand_name]) {
                            $out[$key]['update'] = 1;
                            $out[$key]['update_vers'] = $version[$brand_name];
                        } else {
                            $out[$key]['update'] = NULL;
                        }
                    }
                }
            } else {
            	$error['brand_update_check_master_file'] = _("Error: Aborting Brand Downloads. Can't Get Master File, Assuming Timeout Issues!")."<br />"._("You can upload a brand package by hand from Advanced Settings.");
            	if ($echomsg == true ) {
            		out($error['brand_update_check_master_file']);
            	}
            }
            return $out;
        } else {
        	$error['remote_server'] = _("Error: The Remote Server Is Currently Syncing With the Master Server, Please try again later");
        	if ($echomsg == true ) {
        		out($error['remote_server']);
        	}
        }
    }

	 /**
     * Sync the XML files (incuding all template files) from the hard drive with the database
     * @param int $model Model ID
     * @return boolean True on sync completed. False on sync failed
     */
    function sync_model($model, &$error = array()) {
        if ((!empty($model)) OR ($model > 0)) {
            $sql = 'SELECT * FROM  endpointman_model_list WHERE id=?';
            $sql_p = array($model);
            $model_row = epm_sql($sql, 'getrow', $sql_p);

            $sql = 'SELECT * FROM  endpointman_product_list WHERE id=?';
            $sql_p = array($model_row['product_id']);
            $product_row = epm_sql($sql, 'getRow', $sql_p);

            $sql = 'SELECT * FROM  endpointman_brand_list WHERE id=?';
            $sql_p = array($model_row['brand']);
            $brand_row = epm_sql($sql, 'getRow', $sql_p);


            $path_brand_dir = $this->PHONE_MODULES_PATH . '/endpoint/' . $brand_row['directory'];
            $path_brand_dir_cfg = $path_brand_dir . '/' . $product_row['cfg_dir'];
            $path_brand_dir_cfg_json = $path_brand_dir_cfg . '/family_data.json';

            if (!file_exists($path_brand_dir)) {
            	$error['sync_model'] = sprintf(_("Brand Directory '%s' Doesn't Exist! (%s)"), $brand_row['directory'], $path_brand_dir);
                return(FALSE);
            }
            if (!file_exists($path_brand_dir_cfg)) {
            	$error['sync_model'] = sprintf(_("Product Directory '%s' Doesn't Exist! (%s)"), $product_row['cfg_dir'], $path_brand_dir_cfg);
                return(FALSE);
            }
            if (!file_exists($path_brand_dir_cfg_json)) {
                $error['sync_model'] = sprintf(_("File 'family_data.json' Doesn't exist in directory: %s"), $path_brand_dir_cfg);
                return(FALSE);
            }
            $family_line_json = $this->file2json($path_brand_dir_cfg_json);


            //TODO: Add local file checks to avoid slow reloading on PHP < 5.3
			$key = $this->system->arraysearchrecursive($model_row['model'], $family_line_json['data']['model_list'], 'model');
            if ($key === FALSE) {
                $error['sync_model'] = "Can't locate model in family JSON file";
                return(FALSE);
            } else {
                $template_list = implode(",", $family_line_json['data']['model_list'][$key[0]]['template_data']);
                $template_list_array = $family_line_json['data']['model_list'][$key[0]]['template_data'];
            }
            $maxlines = $family_line_json['data']['model_list'][$key[0]]['lines'];

            $sql = 'UPDATE endpointman_model_list SET max_lines = ?, template_list = ? WHERE id = ?';
            $sql_p = array($maxlines, $template_list, $model);
            epm_sql($sql, 'query', $sql_p);

            $version = isset($family_line_json['data']['last_modified']) ? $family_line_json['data']['last_modified'] : '';
            $long_name = $family_line_json['data']['name'];
            $short_name = preg_replace("/\[(.*?)\]/si", "", $family_line_json['data']['name']);
            $configuration_files = $family_line_json['data']['configuration_files'];

            $sql = 'UPDATE endpointman_product_list SET long_name = ?, short_name = ? , cfg_ver = ? WHERE id = ?';
            $sql_p = array($long_name, $short_name, $version, $product_row['id']);
            epm_sql($sql, 'query', $sql_p);

            $template_data_array = array();
            $template_data_array = $this->merge_data($this->PHONE_MODULES_PATH . '/endpoint/' . $brand_row['directory'] . '/' . $product_row['cfg_dir'] . '/', $template_list_array);

            $sql = 'UPDATE endpointman_model_list SET template_data = ? WHERE id = ?';
            $sql_p = array(epm_blob_encode($template_data_array), $model);
            epm_sql($sql, 'query', $sql_p);
            return(TRUE);
        } else {
            return(FALSE);
        }
    }

	 /**
     * This will download the xml & brand package remotely
     * @param integer $id Brand ID
     */
    function download_brand($id) {
    	out(_("Install/Update Brand..."));

        $temp_directory = $this->system->sys_get_temp_dir() . "/epm_temp/";
        if (!file_exists($temp_directory)) {
            out(_("Creating EPM temp directory"));
            if (! mkdir($temp_directory, 0775, true)) {
                out(sprintf(_("Error: Failed to create the directory '%s', please Check Permissions!"), $temp_directory));
                return false;
            }
        }
        // The usual cause of this failure is a temp directory that a different user
        // left. Report it here, not four steps later as "Can't Find Downloaded File!".
        if (! is_writable($temp_directory)) {
            out(sprintf(_("Error: The web server cannot write to '%s'. Check the owner. 'fwconsole chown' sets the correct owner."), $temp_directory));
            return false;
        }

        outn(_("Downloading Brand JSON..... "));
        $row = epm_sql('SELECT * FROM endpointman_brand_list WHERE id = ?', 'getAll', array($id));
        if (empty($row)) {
            out(sprintf(_("Error: No such brand: %s"), $id));
            return false;
        }

        $download_error = array();
        $result = $this->system->download_file($this->UPDATE_PATH . $row[0]['directory'] . "/" . $row[0]['directory'] . ".json", $this->PHONE_MODULES_PATH . "endpoint/" . $row[0]['directory'] . "/brand_data.json", $download_error);
        if (! $result) {
            out(_("Error!"));
            out(sprintf(_("Error connecting to the package repository at %s. Brand not installed."), $this->UPDATE_PATH));
            // download_file() gives the reason. An unwritable directory and a dead server
            // need different fixes.
            if (!empty($download_error['download_file'])) {
                out($download_error['download_file']);
            }
            out(_("You can also upload the brand package by hand: Advanced Settings, Manual Upload."));
            return false;
        }
        out(_("Done!"));

        $temp = $this->file2json($this->PHONE_MODULES_PATH . 'endpoint/' . $row[0]['directory'] . '/brand_data.json');
        if (! is_array($temp) || ! isset($temp['data']['brands']['package'])) {
            out(sprintf(_("Error: %s served a brand file with no package in it."), $this->UPDATE_PATH));
            return false;
        }
        $package = basename($temp['data']['brands']['package']);

        out(_("Downloading Brand Package..."));
        $download_error = array();
        if (! $this->system->download_file_with_progress_bar($this->UPDATE_PATH . $row[0]['directory'] . '/' . $package, $temp_directory . $package, $download_error)) {
            out(_("Error download Brand package!"));
            if (!empty($download_error['download_file'])) {
                out($download_error['download_file']);
            }
            return false;
        }
        if (! file_exists($temp_directory . $package)) {
            out(_("Error: Can't Find Downloaded File!"));
            return false;
        }

        // The same server publishes the md5sum and the tarball. Thus the sum finds a
        // truncated or damaged download, but not a replaced one. extract_package()
        // drops each .php file in the package, so a replaced package cannot add code.
        $md5_xml = isset($temp['data']['brands']['md5sum']) ? $temp['data']['brands']['md5sum'] : '';
        $md5_pkg = md5_file($temp_directory . $package);

        outn(_("Checking MD5sum of Package.... "));
        if ($md5_xml === '') {
            out(_("Error: The brand file has no md5sum, so the module refuses the package."));
            unlink($temp_directory . $package);
            return false;
        }
        if ($md5_xml !== $md5_pkg) {
            out(_("MD5 Did not match!"));
            out(sprintf(_("MD5 XML: %s"), $md5_xml));
            out(sprintf(_("MD5 PKG: %s"), $md5_pkg));
            unlink($temp_directory . $package);
            return false;
        }
        out(_("Done!"));

        outn(_("Extracting Tarball........ "));
        $extract_error = array();
        if (! $this->system->extract_package($temp_directory . $package, $temp_directory, $extract_error)) {
            out(_("Error!"));
            out($extract_error['extract_package']);
            return false;
        }
        out(_("Done!"));

        //Update File in the temp directory
        copy($this->PHONE_MODULES_PATH . 'endpoint/' . $row[0]['directory'] . '/brand_data.json', $temp_directory . $row[0]['directory'] . '/brand_data.json');
        $this->update_brand($row[0]['directory'], TRUE);
        return true;
    }

    /**
     * This will install or updated a brand package (which is the same thing to this)
     * Still needs way to determine when models move...perhaps another function?
     */
    function update_brand($package, $remote=TRUE) {
    	out(sprintf(_("Update Brand %s ... "), $package));

		$temp_directory = $this->system->sys_get_temp_dir() . "/epm_temp/";
		//DEBUG
		out(_("Processing ".$temp_directory.$package."/brand_data.json..."));

        if (file_exists($temp_directory . $package . '/brand_data.json')) {
            $temp = $this->file2json($temp_directory . $package . '/brand_data.json');
            if (key_exists('directory', $temp['data']['brands'])) {
				out(_("Appears to be a valid Provisioner.net JSON file.....Continuing"));
                //Pull in all variables
                $directory = $temp['data']['brands']['directory'];
                $brand_name = $temp['data']['brands']['name'];
                $brand_id = $temp['data']['brands']['brand_id'];
                $brand_version = $temp['data']['brands']['last_modified'];

                //create directory structure and move files
                out(sprintf(_("Creating Directory Structure for Brand '%s' and Moving Files..."), $brand_name));

                if (!file_exists($this->PHONE_MODULES_PATH . "endpoint/" . $directory)) {
                    mkdir($this->PHONE_MODULES_PATH . "endpoint/" . $directory);
                }

                $dir_iterator = new \RecursiveDirectoryIterator($temp_directory . $directory . "/");
                $iterator = new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::SELF_FIRST);
                foreach ($iterator as $file) {
                    if (is_dir($file)) {
                        $dir = str_replace($temp_directory . $directory . "/", "", $file);
                        if (!file_exists($this->PHONE_MODULES_PATH . "endpoint/" . $directory . "/" . $dir)) {
                            mkdir($this->PHONE_MODULES_PATH . "endpoint/" . $directory . "/" . $dir, 0775, TRUE);
//echo ".";
                        }
                    } else {
                        if ((basename($file) != "brand_data.json") OR (!$remote)) {
                            $dir = str_replace($temp_directory . $directory . "/", "", $file);
                            $stats = rename($file, $this->PHONE_MODULES_PATH . "endpoint/" . $directory . "/" . $dir);
                            if ($stats === FALSE) {
                            	out(sprintf(_("- Error Moving %s!"), basename($file)));
                            }
                            chmod($this->PHONE_MODULES_PATH . "endpoint/" . $directory . "/" . $dir, 0775);
//echo ".";
                        }
                    }
                }
                out(_("All Done!"));

                if ($remote) {
                    $local = 0;
                } else {
                    $local = 1;
                }

                // Each value below comes from the JSON of the package, so bind it. Do not put
                // it into the SQL text.
                $b_data = epm_sql('SELECT id FROM endpointman_brand_list WHERE id = ?', 'getOne', array($brand_id));
                if ($b_data) {
                	outn(sprintf(_("Updating %s brand data ..."), $brand_name));
                    $sql = 'UPDATE endpointman_brand_list SET local = ?, name = ?, cfg_ver = ?, installed = 1, hidden = 0 WHERE id = ?';
                    $sql_p = array($local, $brand_name, $brand_version, $brand_id);
                    epm_sql($sql, 'query', $sql_p);
                    out(_("Done!"));
                } else {
                	outn(sprintf(_("Inserting %s brand data ..."), $brand_name));
					$sql = 'INSERT INTO endpointman_brand_list (id, name, directory, cfg_ver, local, installed) VALUES (?, ?, ?, ?, ?, \'1\')';
					$sql_p = array($brand_id, $brand_name, $directory, $brand_version, $local);
                    epm_sql($sql, 'query', $sql_p);
                    out(_("Done!"));
                }

                $last_mod = "";
                foreach ($temp['data']['brands']['family_list'] as $family_list) {
					out(_("Updating Family Lines ..."));

                    $last_mod = max($last_mod, $family_list['last_modified']);

                    $family_line_xml = $this->file2json($this->PHONE_MODULES_PATH . '/endpoint/' . $directory . '/' . $family_list['directory'] . '/family_data.json');
                    $family_line_xml['data']['last_modified'] = isset($family_line_xml['data']['last_modified']) ? $family_line_xml['data']['last_modified'] : '';

                    $require_firmware = NULL;
                    if ((key_exists('require_firmware', $family_line_xml['data'])) && ($remote) && ($family_line_xml['data']['require_firmware'] == "TRUE")) {
						out(_("Firmware Requirment Detected!.........."));
						$this->install_firmware($family_line_xml['data']['id']);
                    }

                    $data = epm_sql('SELECT id FROM endpointman_product_list WHERE id = ?', 'getOne', array($brand_id . $family_line_xml['data']['id']));
                    $short_name = preg_replace("/\[(.*?)\]/si", "", $family_line_xml['data']['name']);

					if ($data) {
						if ($this->configmod->get('debug')) echo "-Updating Family ".$short_name."<br/>";
                        // cfg_ver comes from last_modified, which has a default above. Do not read a
                        // 'version' key, because no family_data.json of the backend has one.
                        $sql = 'UPDATE endpointman_product_list SET short_name = ?, long_name = ?, cfg_ver = ?, config_files=? WHERE id = ?';
                        $sql_p = array($short_name, $family_line_xml['data']['name'], $family_line_xml['data']['last_modified'], $family_line_xml['data']['configuration_files'], $brand_id . $family_line_xml['data']['id']);
                    }
					else {
						if ($this->configmod->get('debug')) echo "-Inserting Family ".$short_name."<br/>";
                        $sql = 'INSERT INTO endpointman_product_list (`id`, `brand`, `short_name`, `long_name`, `cfg_dir`, `cfg_ver`, `config_files`, `hidden`) VALUES (?, ?, ?, ?, ?, ?,?, \'0\')';
                        $sql_p = array($brand_id . $family_line_xml['data']['id'], $brand_id, $short_name, $family_line_xml['data']['name'], $family_line_xml['data']['directory'], $family_line_xml['data']['last_modified'], $family_line_xml['data']['configuration_files']);
                    }
					epm_sql($sql, 'query', $sql_p);


					if (count($family_line_xml['data']['model_list']) > 0) {
						out(_("-- Updating Model Lines ... "));
	                    foreach ($family_line_xml['data']['model_list'] as $model_list) {
	                        $template_list = implode(",", $model_list['template_data']);

	                        $model_final_id = $brand_id . $family_line_xml['data']['id'] . $model_list['id'];
	                        $sql = 'SELECT id, global_custom_cfg_data, global_user_cfg_data FROM endpointman_mac_list WHERE model = ?';
	                        $sql_p = array($model_final_id);
	                        $old_data = NULL;
	                        $old_data = epm_sql($sql, 'getAll', $sql_p);
	                        foreach ($old_data as $data) {
	                            $global_custom_cfg_data = epm_blob_decode($data['global_custom_cfg_data']);
	                            if ((is_array($global_custom_cfg_data)) AND (!array_key_exists('data', $global_custom_cfg_data))) {
outn(_("----Old Data Detected! Migrating ... "));
	                                $new_data = array();
	                                $new_ari = array();
	                                foreach ($global_custom_cfg_data as $key => $old_keys) {
	                                    if (array_key_exists('value', $old_keys)) {
	                                        $new_data[$key] = $old_keys['value'];
	                                    } else {
	                                        $breaks = explode("_", $key);
	                                        $new_data["loop|" . $key] = $old_keys[$breaks[2]];
	                                    }
	                                    if (array_key_exists('ari', $old_keys)) {
	                                        $new_ari[$key] = 1;
	                                    }
	                                }
	                                $final_data = array();
	                                $final_data['data'] = $new_data;
	                                $final_data['ari'] = $new_ari;
	                                $final_data = epm_blob_encode($final_data);
	                                $sql = 'UPDATE endpointman_mac_list SET  global_custom_cfg_data =  ? WHERE  id =?';
	                                $sql_p = array($final_data, $data['id']);
	                                epm_sql($sql, 'query', $sql_p);
									out(_("Done!"));
	                            }

	                            $global_user_cfg_data = epm_blob_decode($data['global_user_cfg_data']);
	                            $old_check = FALSE;
	                            if (is_array($global_user_cfg_data)) {
	                                foreach ($global_user_cfg_data as $stuff) {
	                                    if (is_array($stuff)) {
	                                        if (array_key_exists('value', $stuff)) {
	                                            $old_check = TRUE;
	                                            break;
	                                        } else {
	                                            break;
	                                        }
	                                    } else {
	                                        break;
	                                    }
	                                }
	                            }
	                            if ((is_array($global_user_cfg_data)) AND ($old_check)) {
outn(_("Old Data Detected! Migrating ... "));
	                                $new_data = array();
	                                foreach ($global_user_cfg_data as $key => $old_keys) {
	                                    if (array_key_exists('value', $old_keys)) {
	                                        $exploded = explode("_", $key);
	                                        $counted = count($exploded);
	                                        $counted = $counted - 1;
	                                        if (is_numeric($exploded[$counted])) {
	                                            $key = "loop|" . $key;
	                                        }
	                                        $new_data[$key] = $old_keys['value'];
	                                    }
	                                }
	                                $final_data = epm_blob_encode($new_data);
	                                $sql = 'UPDATE endpointman_mac_list SET  global_user_cfg_data =  ? WHERE  id =?';
	                                $sql_p = array($final_data, $data['id']);
	                                epm_sql($sql, 'query', $sql_p);
									out(_("Done!"));
	                            }
	                        }
	                        $old_data = NULL;
	                        $sql = 'SELECT id, global_custom_cfg_data FROM endpointman_template_list WHERE model_id = ?';
	                        $sql_p = array($model_final_id);
	                        $old_data = epm_sql($sql, 'getAll', $sql_p);
	                        foreach ($old_data as $data) {
	                            $global_custom_cfg_data = epm_blob_decode($data['global_custom_cfg_data']);
	                            if ((is_array($global_custom_cfg_data)) AND (!array_key_exists('data', $global_custom_cfg_data))) {
out(_("Old Data Detected! Migrating ... "));
	                                $new_data = array();
	                                $new_ari = array();
	                                foreach ($global_custom_cfg_data as $key => $old_keys) {
	                                    if (array_key_exists('value', $old_keys)) {
	                                        $new_data[$key] = $old_keys['value'];
	                                    } else {
	                                        $breaks = explode("_", $key);
	                                        $new_data["loop|" . $key] = $old_keys[$breaks[2]];
	                                    }
	                                    if (array_key_exists('ari', $old_keys)) {
	                                        $new_ari[$key] = 1;
	                                    }
	                                }
	                                $final_data = array();
	                                $final_data['data'] = $new_data;
	                                $final_data['ari'] = $new_ari;
	                                $final_data = epm_blob_encode($final_data);
	                                $sql = 'UPDATE endpointman_template_list SET  global_custom_cfg_data =  ? WHERE  id =?';
	                                $sql_p = array($final_data, $data['id']);
	                                epm_sql($sql, 'query', $sql_p);
									out(_("Done!"));
	                            }
	                        }

	                        $m_data = epm_sql('SELECT id FROM endpointman_model_list WHERE id = ?', 'getOne', array($brand_id . $family_line_xml['data']['id'] . $model_list['id']));
	                        if ($m_data) {
if ($this->configmod->get('debug')) echo format_txt(_("---Updating Model %_NAMEMOD_%"), "", array("%_NAMEMOD_%" => $model_list['model']));
	                            $sql = 'UPDATE endpointman_model_list SET max_lines = ?, model = ?, template_list = ? WHERE id = ?';
	                            $sql_p = array($model_list['lines'], $model_list['model'], $template_list, $brand_id . $family_line_xml['data']['id'] . $model_list['id']);
	                        }
							else {
if ($this->configmod->get('debug')) echo format_txt(_("---Inserting Model %_NAMEMOD_%"), "", array("%_NAMEMOD_%" => $model_list['model']));
	                            $sql = 'INSERT INTO endpointman_model_list (`id`, `brand`, `model`, `max_lines`, `product_id`, `template_list`, `enabled`, `hidden`) VALUES (?, ?, ?, ?, ?, ?, \'0\', \'0\')';
	                            $sql_p = array($brand_id . $family_line_xml['data']['id'] . $model_list['id'], $brand_id, $model_list['model'], $model_list['lines'], $brand_id . $family_line_xml['data']['id'], $template_list);
	                        }
	                        epm_sql($sql, 'query', $sql_p);

	                        //echo "brand_id:".$brand_id. " - family_line_xml:" . $family_line_xml['data']['id'] . "- model_list:" . $model_list['id']."<br>";
	                        if (!$this->sync_model($brand_id . $family_line_xml['data']['id'] . $model_list['id'], $errlog)) {
	                        	out(_("Error: System Error in Sync Model Function, Load Failure!"));
								out(_("Error: ").$errlog['sync_model']);
	                        }
	                        unset ($errlog);
	                    }
					}
                    //END Updating Model Lines................

                    //Phone Models Move Here
                    $family_id = $brand_id . $family_line_xml['data']['id'];
                    $sql = 'SELECT * FROM endpointman_model_list WHERE product_id = ?';
                    $sql_p = array($family_id);
                    $products = epm_sql($sql, 'getall', $sql_p);
                    foreach ($products as $data) {
                        if (!$this->system->arraysearchrecursive($data['model'], $family_line_xml['data']['model_list'], 'model')) {
							outn(sprintf(_("Moving/Removing Model '%s' not present in JSON file ... "), $data['model']));
                            $model_name = $data['model'];
                            $sql = 'DELETE FROM endpointman_model_list WHERE id = ?';
                            $sql_p = array($data['id']);
                            epm_sql($sql, 'query', $sql_p);
                            $sql = 'SELECT id FROM endpointman_model_list WHERE model LIKE ?';
                            $sql_p = array($model_name);
                            $new_model_id = epm_sql($sql, 'getOne', $sql_p);
                            if ($new_model_id) {
                                $sql = 'UPDATE  endpointman_mac_list SET  model =  ? WHERE  model = ?';
                                $sql_p = array($new_model_id, $data['id']);
                            } else {
                                $sql = 'UPDATE  endpointman_mac_list SET  model =  \'0\' WHERE  model = ?';
                                $sql_p = array($data['id']);
                            }
                            epm_sql($sql, 'query', $sql_p);
                            out(_("Done!"));
                        }
                    }
                }
				out(_("All Done!"));
				//END Updating Family Lines

				outn(_("Updating OUI list in DB ... "));
				if ((isset($temp['data']['brands']['oui_list'])) AND (count($temp['data']['brands']['oui_list']) > 0))
				{
	                foreach ($temp['data']['brands']['oui_list'] as $oui) {
	                    $sql = 'REPLACE INTO endpointman_oui_list (`oui`, `brand`, `custom`) VALUES (?, ?, \'0\')';
	                    $sql_p = array($oui, $brand_id);
	                    epm_sql($sql, 'query', $sql_p);
	                }
				}
				out(_("Done!"));
            } else {
				outn(sprintf(_("Error: Invalid JSON Structure in %s/brand_data.json"), $temp_directory.$package));
            }
        } else {
			out(_("Error: No 'brand_data.xml' file exists!"));
        }

		outn(_("Removing Temporary Files... "));
        $this->system->rmrf($temp_directory . $package);
        out(_("Done!"));
    }

	/**
     * Remove the brand
     *
     * @param int $id Brand ID
     */
    function remove_brand($id=NULL) {
		out(_("Uninstalla Brand..."));

        $sql = 'SELECT id, firmware_vers FROM endpointman_product_list WHERE brand = ?';
        $sql_p = array($id);
        $products = epm_sql($sql, 'getall', $sql_p);

        foreach ($products as $data) {
            if ($data['firmware_vers'] != "") {
                $this->remove_firmware($data['id']);
            }
        }

        $sql = 'SELECT directory FROM endpointman_brand_list WHERE id = ?';
        $sql_p = array($id);
        $brand_dir = epm_sql($sql, 'getOne', $sql_p);
        $brand_path = $this->PHONE_MODULES_PATH . "endpoint/" . $brand_dir;
        if (!$this->system->rmrf($brand_path)) {
            out(sprintf(_("Warning: Some files under '%s' remain. Check the owner. A new install of this brand fails until you remove them."), $brand_path));
        }

        $sql = 'DELETE FROM endpointman_model_list WHERE brand = ?';
        $sql_p = array($id);
        epm_sql($sql, 'query', $sql_p);

        $sql = 'DELETE FROM endpointman_product_list WHERE brand = ?';
        $sql_p = array($id);
        epm_sql($sql, 'query', $sql_p);

        $sql = 'DELETE FROM endpointman_oui_list WHERE brand = ?';
        $sql_p = array($id);
        epm_sql($sql, 'query', $sql_p);

        $this->system->rmrf($this->PHONE_MODULES_PATH . $brand_dir);
        $sql = 'DELETE FROM endpointman_brand_list WHERE id = ?';
        $sql_p = array($id);
        epm_sql($sql, 'query', $sql_p);

		out(_("All Done!"));
    }

	/**
     * Install Firmware for the specified Product Line
     *
     * Firmware is not published. The package server builds
     * <family>_firmware.tgz only when /usr/src/provisioner_framework exists, and
     * its CI build does not have it. Thus each *_firmware.tgz gives 404. Fail
     * with a message that the admin can act on: upload the firmware manually.
     *
     * @param int $product_id Product ID
     * @return bool
     */
    function install_firmware($product_id) {
    	out(_("Install firmware... "));

        $temp_directory = $this->system->sys_get_temp_dir() . "/epm_temp/";
        $sql = 'SELECT endpointman_product_list.*, endpointman_brand_list.directory FROM endpointman_product_list, endpointman_brand_list WHERE endpointman_product_list.brand = endpointman_brand_list.id AND endpointman_product_list.id = ?';
        $sql_p = array($product_id);
        $row = epm_sql($sql, 'getRow', $sql_p);
        if (empty($row)) {
            out(sprintf(_("Error: No such product: %s"), $product_id));
            return false;
        }
        $json_data = $this->file2json($this->PHONE_MODULES_PATH . "endpoint/" . $row['directory'] . "/" . $row['cfg_dir'] . "/family_data.json");

        if ((! isset($json_data['data']['firmware_ver'])) OR (empty($json_data['data']['firmware_ver']))) {
        	out (_("This family publishes no firmware version, so there is nothing to install."));
        	out (_("Firmware can be uploaded by hand: Advanced Settings, Manual Upload."));
        	return false;
        }

        if ((! isset($json_data['data']['firmware_pkg'])) OR (empty($json_data['data']['firmware_pkg'])) OR ($json_data['data']['firmware_pkg'] == "NULL")) {
        	out (_("This family names no firmware package, so there is nothing to download."));
        	out (_("Firmware can be uploaded by hand: Advanced Settings, Manual Upload."));
        	return false;
        }

        if ($json_data['data']['firmware_ver'] <= $row['firmware_vers']) {
			out(_("Your Firmware is already up to date."));
			return true;
        }

        if (!file_exists($temp_directory)) {
            mkdir($temp_directory);
        }
        $md5_xml = isset($json_data['data']['firmware_md5sum']) ? $json_data['data']['firmware_md5sum'] : '';
        $firmware_pkg = basename($json_data['data']['firmware_pkg']);

        if ($md5_xml === '') {
            out(_("Error: The family has no firmware md5sum, so the module refuses the package."));
            return false;
        }

        $have_local = file_exists($temp_directory . $firmware_pkg)
                   && md5_file($temp_directory . $firmware_pkg) === $md5_xml;
        if ($have_local) {
			out(_("Skipping download, updated local version..."));
        } else {
			out(_("Downloading firmware..."));
            if (! $this->system->download_file_with_progress_bar($this->UPDATE_PATH . $row['directory'] . "/" . $firmware_pkg, $temp_directory . $firmware_pkg)) {
				out(sprintf(_("Error: %s does not serve %s."), $this->UPDATE_PATH, $firmware_pkg));
				out(_("The package repository does not publish firmware. Upload it manually: Advanced Settings, Manual Upload."));
				return false;
			}
        }

		outn(_("Checking MD5sum of Package... "));
        $md5_pkg = md5_file($temp_directory . $firmware_pkg);
        if ($md5_xml !== $md5_pkg) {
			out(_("Firmware MD5 didn't match!"));
			unlink($temp_directory . $firmware_pkg);
			return false;
        }
		out(_("Matches!"));

        $family_dir   = $temp_directory . $row['directory'] . "/" . $row['cfg_dir'];
        $firmware_dir = $family_dir . "/firmware";
        if (file_exists($firmware_dir)) {
            $this->system->rmrf($firmware_dir);
        }
        if (! file_exists($family_dir)) {
            mkdir($family_dir, 0775, TRUE);
        }

		out(_("Installing Firmware..."));
        $extract_error = array();
        if (! $this->system->extract_package($temp_directory . $firmware_pkg, $family_dir, $extract_error)) {
            out($extract_error['extract_package']);
            return false;
        }

        // Handle a firmware tarball without a firmware/ directory. Otherwise $list has
        // no value, and implode() gives a TypeError on PHP 8.
        $files = glob($firmware_dir . "/*");
        if (empty($files)) {
            out(sprintf(_("Error: %s contains no firmware files."), $firmware_pkg));
            $this->system->rmrf($temp_directory . $row['directory']);
            return false;
        }

        $list = array();
        $copy_error = false;
        foreach ($files as $filename) {
            $file = basename($filename);
            $list[] = $file;
            if (!@copy($filename, $this->configmod->get('config_location') . $file)) {
            	out(sprintf(_("- Failed To Copy %s!"), $file));
                $copy_error = TRUE;
            } elseif ($this->configmod->get('debug')) {
				out(sprintf(_("- Copied %s to %s."), $file, $this->configmod->get('config_location')));
            }
        }

        $this->system->rmrf($temp_directory . $row['directory']);
        $sql = 'UPDATE endpointman_product_list SET firmware_vers = ?, firmware_files = ? WHERE id = ?';
        $sql_p = array($json_data['data']['firmware_ver'], implode(",", $list), $row['id']);
        epm_sql($sql, 'query', $sql_p);

        if ($copy_error) {
			out(_("Copy Error Detected! Aborting Install!"));
            $this->remove_firmware($product_id);
			out(_("Info: Please Check Directory/Permissions!"));
			return false;
        }

		out(_("Done!"));
		return true;
    }

	/**
     * Remove firmware from the Hard Drive
     * @param int $id Product ID
     */
    function remove_firmware($id) {
		outn(_("Uninstalla frimware... "));

        $sql = 'SELECT firmware_files FROM  endpointman_product_list WHERE  id =?';
        $sql_p = array($id);
        $files = epm_sql($sql, 'getOne', $sql_p);

        $file_list = explode(",", $files);
        $i = 0;
        foreach ($file_list as $file) {
			if (trim($file) == "") { continue; }
            if (! file_exists($this->configmod->get('config_location') . $file)) { continue; }
			if (! is_file($this->configmod->get('config_location') . $file)) { continue; }
					unlink($this->configmod->get('config_location') . $file);
        }
        $sql = 'UPDATE endpointman_product_list SET firmware_files = \'\', firmware_vers = \'\' WHERE id = ?';
        $sql_p = array($id);
        epm_sql($sql, 'query', $sql_p);

		out(_("Done!"));
    }




		/**
     * Check for new firmware on the servers
     * @param int $id Product ID
     * @return bool True on yes False on no
     */
    function firmware_update_check($id=NULL) {
        $sql = 'SELECT * FROM  endpointman_product_list WHERE  id =?';
        $sql_p = array($id);
        $row = epm_sql($sql, 'getRow', $sql_p);

        $sql = 'SELECT directory FROM  endpointman_brand_list WHERE id =?';
        $sql_p = array($row['brand']);
        $brand_directory = epm_sql($sql, 'getOne', $sql_p);

        //config drive unknown!
        if ($row['cfg_dir'] == "") {
            return FALSE;
        } else {
            $temp = $this->file2json($this->PHONE_MODULES_PATH . "endpoint/" . $brand_directory . "/" . $row['cfg_dir'] . "/family_data.json");
            if (is_array($temp) && ((array_key_exists('data', $temp)) AND (!is_array($temp['data']['firmware_ver'])))) {
                if ($row['firmware_vers'] < $temp['data']['firmware_ver']) {
                    return $temp;
                } else {
                    return FALSE;
                }
            } else {
                return FALSE;
            }
        }
    }

	/**
     * Check to see the status of the firmware locally (installed or not)
     * @param int $id
     * @return string
     */
    function firmware_local_check($id=NULL) {
        $sql = 'SELECT * FROM  endpointman_product_list WHERE hidden = 0 AND id =?';
        $sql_p = array($id);
        $res = epm_sql($sql, 'getAll', $sql_p);

        if ($res) {
            $row = epm_sql($sql, 'getRow', $sql_p);

            $sql = 'SELECT directory FROM  endpointman_brand_list WHERE hidden = 0 AND id =?';
            $sql_p = array($row['brand']);
            $brand_directory = epm_sql($sql, 'getOne', $sql_p);

            //config drive unknown!
            if ($row['cfg_dir'] == "") {
				return("nothing");
            } else {
                $temp = $this->file2json($this->PHONE_MODULES_PATH . "endpoint/" . $brand_directory . "/" . $row['cfg_dir'] . "/family_data.json");
                if ( (isset($temp['data']['firmware_ver'])) AND (! empty ($temp['data']['firmware_ver'])) ) {
                    if ($row['firmware_vers'] == "") {
                        return("install");
                    } else {
                        return("remove");
                    }
                } else {
                    return("nothing");
                }
            }
        } else {
            return("nothing");
        }
    }


	/**
     * Reads a file. Json decodes it and will report any errors back
     * @param string $file location of file
     * @return mixed false on error, array on success
     * @version 2.11
     */
    function file2json($file) {
        if (file_exists($file)) {
            $json = file_get_contents($file);
            $data = json_decode($json, TRUE);
            if(function_exists('json_last_error')) {
                switch (json_last_error()) {
                    case JSON_ERROR_NONE:
                        return($data);
                        break;
                    case JSON_ERROR_DEPTH:
                        $this->error['file2json'] = _('Maximum stack depth exceeded');
                        break;
                    case JSON_ERROR_STATE_MISMATCH:
                        $this->error['file2json'] = _('Underflow or the modes mismatch');
                        break;
                    case JSON_ERROR_CTRL_CHAR:
                        $this->error['file2json'] = _('Unexpected control character found');
                        break;
                    case JSON_ERROR_SYNTAX:
                        $this->error['file2json'] = _('Syntax error, malformed JSON');
                        break;
                    case JSON_ERROR_UTF8:
                        $this->error['file2json'] = _('Malformed UTF-8 characters, possibly incorrectly encoded');
                        break;
                    default:
                        $this->error['file2json'] = _('Unknown error');
                        break;
                }
                return(false);
            } else {
                //Probably an older version of PHP. That's ok though
                return($data);
            }
        } else {
            $this->error['file2json'] = _('Cant find file:').' '.epm_esc($file) ;
            return(false);
        }
    }






	function merge_data($path, $template_list, $maxlines = 12) {
    	//TODO: fix
    	foreach ($template_list as $files_data) {
    		$full_path = $path . $files_data;
    		if (file_exists($full_path)) {
    			$temp_files_data = $this->file2json($full_path);
    			// Names are optional. The upstream grandstream/bts/template_data.json has a
    			// subcategory with only an "item" key. A missing key is a warning on PHP 8,
    			// and FreePBX turns it into an exception during the install. Use an empty
    			// string.
    			foreach ($temp_files_data['template_data']['category'] as $category) {
    				$category_name = $category['name'] ?? '';
    				foreach ($category['subcategory'] as $subcategory) {
    					$subcategory_name = $subcategory['name'] ?? '';
    					$items_fin = array();
    					$items_loop = array();
    					$break_count = 0;
    					foreach ($subcategory['item'] as $item) {
    						switch ($item['type'] ?? '') {
    							case 'loop_line_options':
    								for ($i = 1; $i <= $maxlines; $i++) {
    									$var_nam = "lineloop|line_" . $i;
    									foreach ($item['data']['item'] as $item_loop) {
    										if (($item_loop['type'] ?? '') != 'break') {
    											// A loop item without a variable
    											// cannot have a key or show. snom/7xx
    											// and 8xx speed_dials.json have
    											// {"type":"none"} spacers. PHP 8
    											// warns on the missing key.
    											if (!isset($item_loop['variable'])) { continue; }
    											$z = str_replace("\$", "", $item_loop['variable']);
    											$items_loop[$var_nam][$z] = $item_loop;
    											$items_loop[$var_nam][$z]['description'] = str_replace('{$count}', $i, $items_loop[$var_nam][$z]['description'] ?? '');
    											$items_loop[$var_nam][$z]['default_value'] = $items_loop[$var_nam][$z]['default_value'];
    											$items_loop[$var_nam][$z]['default_value'] = str_replace('{$count}', $i, $items_loop[$var_nam][$z]['default_value']);
    											$items_loop[$var_nam][$z]['line_loop'] = TRUE;
    											$items_loop[$var_nam][$z]['line_count'] = $i;
    										} elseif (($item_loop['type'] ?? '') == 'break') {
    											$items_loop[$var_nam]['break_' . $break_count]['type'] = 'break';
    											$break_count++;
    										}
    									}
    								}
    								$items_fin = array_merge($items_fin, $items_loop);
    								break;
    							case 'loop':
    								for ($i = $item['loop_start']; $i <= $item['loop_end']; $i++) {
    									$name = explode("_", $item['data']['item'][0]['variable'] ?? '');
    									$var_nam = "loop|" . str_replace("\$", "", $name[0]) . "_" . $i;
    									foreach ($item['data']['item'] as $item_loop) {
    										if (($item_loop['type'] ?? '') != 'break') {
    											if (!isset($item_loop['variable'])) { continue; }
    											$z_tmp = explode("_", $item_loop['variable']);
    											$z = $z_tmp[1] ?? $z_tmp[0];
    											$items_loop[$var_nam][$z] = $item_loop;
    											$items_loop[$var_nam][$z]['description'] = str_replace('{$count}', $i, $items_loop[$var_nam][$z]['description'] ?? '');
    											$items_loop[$var_nam][$z]['variable'] = str_replace('_', '_' . $i . '_', $items_loop[$var_nam][$z]['variable']);
    											$items_loop[$var_nam][$z]['default_value'] = isset($items_loop[$var_nam][$z]['default_value']) ? $items_loop[$var_nam][$z]['default_value'] : '';
    											$items_loop[$var_nam][$z]['loop'] = TRUE;
    											$items_loop[$var_nam][$z]['loop_count'] = $i;
    										} elseif (($item_loop['type'] ?? '') == 'break') {
    											$items_loop[$var_nam]['break_' . $break_count]['type'] = 'break';
    											$break_count++;
    										}
    									}
    								}
    								$items_fin = array_merge($items_fin, $items_loop);
    								break;
    							case 'break':
    								$items_fin['break|' . $break_count]['type'] = 'break';
    								$break_count++;
    								break;
    							default:
    								$var_nam = "option|" . str_replace("\$", "", (isset($item['variable'])? $item['variable'] : ""));
    								$items_fin[$var_nam] = $item;
    								break;
    						}
    					}
    					if (isset($data['data'][$category_name][$subcategory_name])) {
    						$old_sc = $data['data'][$category_name][$subcategory_name];
    						$sub_cat_data[$category_name][$subcategory_name] = array();
    						$sub_cat_data[$category_name][$subcategory_name] = array_merge($old_sc, $items_fin);
    					} else {
    						$sub_cat_data[$category_name][$subcategory_name] = $items_fin;
    					}
    				}
    				if (isset($data['data'][$category_name])) {
    					$old_c = $data['data'][$category_name];
    					$new_c = $sub_cat_data[$category_name];
    					$sub_cat_data[$category_name] = array();
    					$data['data'][$category_name] = array_merge($old_c, $new_c);
    				} else {
    					$data['data'][$category_name] = $sub_cat_data[$category_name];
    				}
    			}
    		}
    	}
    	return($data);
    }
}
