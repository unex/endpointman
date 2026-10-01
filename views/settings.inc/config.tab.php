<?php
	if (!defined('FREEPBX_IS_AUTH')) die('No direct script access allowed');

	FreePBX::Endpointman()->configmod->getConfigModuleSQL(false);


	if ((FreePBX::Endpointman()->configmod->get("server_type") == 'file') AND (FreePBX::Endpointman()->_get_page('settings')->_config_loc_is_writable())) {
		FreePBX::Endpointman()->tftp_check();
	}

?>

<div class="section-title" data-for="setting_provision">
	<h3><i class="fa fa-minus"></i><?php echo _("Setting Provision") ?></h3>
</div>
<div class="section" data-id="setting_provision">

	<!--IP address of phone server-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="srvip"><?php echo _("IP address of phone server")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="srvip"></i>
						</div>
						<div class="col-md-9">
							<div class="input-group">
      							<input type="text" class="form-control" placeholder="Server PBX..." id="srvip" name="srvip" value="<?php echo FreePBX::Endpointman()->configmod->get("srvip"); ?>">
      							<span class="input-group-btn">
        							<button class="btn btn-default" type="button" id='autodetect' onclick="epm_advanced_tab_setting_input_value_change_bt('#srvip', sValue = '<?php echo epm_esc($_SERVER["SERVER_ADDR"]); ?>', bSaveChange = true);"><i class='fa fa-search'></i> <?php echo _("Use me!")?></button>
      							</span>
    						</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="srvip-help"><?php echo _("IP Address of your PBX."); ?></span>
			</div>
		</div>
	</div>
	<!--END IP address of phone server-->
	<!--Internal IP address of phone server-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="intsrvip"><?php echo _("Internal IP address of phone server")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="intsrvip"></i>
						</div>
						<div class="col-md-9">
							<div class="input-group">
      							<input type="text" class="form-control" placeholder="Server PBX..." id="intsrvip" name="intsrvip" value="<?php echo FreePBX::Endpointman()->configmod->get("intsrvip"); ?>">
      							<span class="input-group-btn">
        							<button class="btn btn-default" type="button" id='autodetect' onclick="epm_advanced_tab_setting_input_value_change_bt('#intsrvip', sValue = '<?php echo epm_esc($_SERVER["SERVER_ADDR"]); ?>', bSaveChange = true);"><i class='fa fa-search'></i> <?php echo _("Use me!")?></button>
      							</span>
    						</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="intsrvip-help"><?php echo _("Internal IP address of phone server"); ?></span>
			</div>
		</div>
	</div>
	<!--END Internal IP address of phone server-->
	<!--Configuration Type-->
	<?php
		$server_type = FreePBX::Endpointman()->configmod->get("server_type");
	?>
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="cfg_type"><?php echo _("Configuration Type")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="cfg_type"></i>
						</div>
						<div class="col-md-9">
	                        <select class="form-control selectpicker show-tick" data-style="btn-info" name="cfg_type" id="cfg_type">
                            	<option data-icon="fa fa-upload" value="file" <?php echo ($server_type == "file" ? 'selected="selected"' : '') ?> ><?php echo _("File (TFTP/FTP)")?></option>
								<option data-icon="fa fa-upload" value="http" <?php echo ($server_type == "http"? 'selected="selected"' : '') ?> ><?php echo _("Web (HTTP)")?></option>
                                <option data-icon="fa fa-upload" value="https" <?php echo ($server_type == "https"? 'selected="selected"' : '') ?>><?php echo _("Web (HTTPS)")?></option>
							</select>
                            <br /><br />
							<?php
							if ($server_type == 'http') {
							echo '<div class="alert alert-info" role="alert" id="cfg_type_alert">';
								echo '<strong>' . _("Updated!") . '</strong>' . _(" - Point your phones to: ") . '<a href="' . epm_esc($server_type) . '://' . epm_esc(FreePBX::Endpointman()->configmod->get("srvip")) . '/provisioning/p.php/" class="alert-link" target="_blank">' . epm_esc($server_type) . '://' . epm_esc(FreePBX::Endpointman()->configmod->get("srvip")) . '/provisioning/p.php/</a>';
							echo '</div>';
							}
							if ($server_type == 'https') {
							echo '<div class="alert alert-info" role="alert" id="cfg_type_alert">';
								echo '<strong>' . _("Updated!") . '</strong>' . _(" - Point your phones to: ") . '<a href="' . epm_esc($server_type) . '://' . epm_esc(FreePBX::Endpointman()->configmod->get("srvip")) . '/provisioning/p.php/" class="alert-link" target="_blank">' . epm_esc($server_type) . '://' . epm_esc(FreePBX::Endpointman()->configmod->get("srvip")) . '/provisioning/p.php/</a>';
							echo '</div>';
							}
								?>

						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="cfg_type-help"><?php echo _("Type the server by aprovisonament setting. Server TFTP, Server HTTP, Server HTTPS."); ?></span>
			</div>
		</div>
	</div>
	<?php
		unset($server_type);
	?>
	<!--END Configuration Type-->
	<!--Provisioning allowlist-->
	<?php
		$provision_acl = FreePBX::Endpointman()->configmod->get("provision_acl");
		$provision_web = in_array(FreePBX::Endpointman()->configmod->get("server_type"), array('http', 'https'), true);
	?>
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="provision_acl"><?php echo _("Provisioning allowlist")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="provision_acl"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" placeholder="10.0.0.0/8, 192.168.0.0/16" id="provision_acl" name="provision_acl" value="<?php echo htmlspecialchars($provision_acl, ENT_QUOTES, 'UTF-8'); ?>">
							<?php
							if ($provision_web AND (trim($provision_acl) === '')) {
								echo '<br /><div class="alert alert-danger" role="alert" id="provision_acl_alert">';
								echo '<strong>' . _("Nothing can provision.") . '</strong> ' . _("The allowlist is empty and the transport is Web, so the server refuses all requests. Add the networks of your phones.");
								echo '</div>';
							}
							?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="provision_acl-help"><?php echo _("The addresses that can get a device configuration over HTTP or HTTPS. Use addresses or CIDR blocks, IPv4 or IPv6, separated by commas. Device configurations contain SIP secrets, and phones cannot authenticate. Thus this list is the only control of who can read them. An empty list refuses all addresses. The module ignores this list when the Configuration Type is File (TFTP/FTP)."); ?></span>
			</div>
		</div>
	</div>
	<?php
		unset($provision_acl, $provision_web);
	?>
	<!--END Provisioning allowlist-->
	<!--Trusted proxies-->
	<?php
		$trusted_proxies = FreePBX::Endpointman()->configmod->get("trusted_proxies");
	?>
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="trusted_proxies"><?php echo _("Trusted reverse proxies")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="trusted_proxies"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" placeholder="<?php echo _("none"); ?>" id="trusted_proxies" name="trusted_proxies" value="<?php echo htmlspecialchars($trusted_proxies, ENT_QUOTES, 'UTF-8'); ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="trusted_proxies-help"><?php echo _("Leave empty if phones do not reach this server through a reverse proxy. Use the addresses or CIDR blocks of the proxies. For a request from these addresses, the module uses the client address and scheme from X-Forwarded-For and X-Forwarded-Proto. Thus the allowlist above applies to the phone and not to the proxy, and HTTPS provisioning works when the proxy ends TLS. The module ignores the forwarding headers of all other requests."); ?></span>
			</div>
		</div>
	</div>
	<?php
		unset($trusted_proxies);
	?>
	<!--END Trusted proxies-->
	<!--Global Final Config & Firmware Directory-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="config_loc"><?php echo _("Global Final Config & Firmware Directory")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="config_loc"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="config_loc" name="config_loc" value="<?php echo FreePBX::Endpointman()->configmod->get("config_location"); ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="config_loc-help"><?php echo _("Path location root TFTP server."); ?></span>
			</div>
		</div>
	</div>
	<!--END Global Final Config & Firmware Directory-->
	<!--Global Admin Password-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="adminpass"><?php echo _("Phone Admin Password")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="adminpass"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="adminpass" name="adminpass" value="<?php echo FreePBX::Endpointman()->configmod->get("adminpass"); ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="adminpass-help"><?php echo _("Enter a admin password for your phones. Must be 6 characters and only nummeric is recommendet!"); ?></span>
			</div>
		</div>
	</div>
	<!--Global Admin Password-->
	<!--Global User Password-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="userpass"><?php echo _("Phone User Password")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="userpass"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="userpass" name="userpass" value="<?php echo FreePBX::Endpointman()->configmod->get("userpass"); ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="userpass-help"><?php echo _("Enter a user password for your phones. Must be 6 characters and only nummeric is recommendet!"); ?></span>
			</div>
		</div>
	</div>
	<!--Global User Password-->
</div>

<div class="section-title" data-for="setting_time">
	<h3><i class="fa fa-minus"></i><?php echo _("Time") ?></h3>
</div>
<div class="section" data-id="setting_time">
	<!--Time Zone-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="tz"><?php echo _("Time Zone")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="tz"></i>
						</div>
						<div class="col-md-9">
                        	<div class="input-group input-group-br">
                            	<select class="form-control selectpicker show-tick" data-style="btn-primary" data-live-search-placeholder="Search" data-size="10" data-live-search="true" name="tz" id="tz">
								<?php
									$list_tz = FreePBX::Endpointman()->listTZ(FreePBX::Endpointman()->configmod->get("tz"));
								   	foreach ($list_tz as $row) {
										echo '<option data-icon="fa fa-clock-o" value="'.epm_esc($row['value']).'" '.($row['selected'] == 1 ? 'selected="selected"' : '' ).'>'.epm_esc($row['text']).'</option>';
									}
									unset ($list_tz);
								?>
								</select>
								<span class="input-group-btn">
									<button class="btn btn-default" type="button" id='tzphp' onclick="epm_advanced_tab_setting_input_value_change_bt('#tz', sValue = '<?php echo FreePBX::Endpointman()->config->get('PHPTIMEZONE'); ?>', bSaveChange = true);"><i class="fa fa-clock-o"></i> <?php echo _("TimeZone PBX")?></button>
								</span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="tz-help"><?php echo _("TimeZone configuration terminasl. Like England/London"); ?></span>
			</div>
		</div>
	</div>
	<!--END Time Zone-->
	<!--Time Server - NTP Server-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="ntp_server"><?php echo _("Time Server (NTP Server)")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="ntp_server"></i>
						</div>
						<div class="col-md-9">
							<div class="input-group">
      							<input type="text" class="form-control" placeholder="Server NTP..." id="ntp_server" name="ntp_server" value="<?php echo FreePBX::Endpointman()->configmod->get("ntp"); ?>">
      							<span class="input-group-btn">
        							<button class="btn btn-default" type="button" id='autodetectntp' onclick="epm_advanced_tab_setting_input_value_change_bt('#ntp_server', sValue = '<?php echo epm_esc($_SERVER["SERVER_ADDR"]); ?>', bSaveChange = true);"><i class='fa fa-search'></i> <?php echo _("Use me!")?></button>
      							</span>
    						</div>

						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="ntp_server-help"><?php echo _("Server NTP use the configuration terminals."); ?></span>
			</div>
		</div>
	</div>
	<!--END Time Server - NTP Server-->
</div>

<div class="section-title" data-for="setting_local_paths">
	<h3><i class="fa fa-minus"></i><?php echo _("Local Paths") ?></h3>
</div>
<div class="section" data-id="setting_local_paths">
	<!--NMAP Executable Path-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="nmap_loc"><?php echo _("NMAP Executable Path")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="nmap_loc"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="nmap_loc" name="nmap_loc" value="<?php echo FreePBX::Endpointman()->configmod->get("nmap_location"); ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="nmap_loc-help"><?php echo _("Path location NMAP."); ?></span>
			</div>
		</div>
	</div>
	<!--END NMAP Executable Path-->
	<!--ARP Executable Path-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="arp_loc"><?php echo _("ARP / IP Neighbour Executable Path")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="arp_loc"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="arp_loc" name="arp_loc" value="<?php echo FreePBX::Endpointman()->configmod->get("arp_location"); ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="arp_loc-help"><?php echo _("The path of the tool that reads the network neighbor table: ip from iproute2, or arp from net-tools. Leave empty to search PATH."); ?></span>
			</div>
		</div>
	</div>
	<!--END ARP Executable Path-->
	<!--Asterisk Executable Path-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="asterisk_loc"><?php echo _("Asterisk Executable Path")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="asterisk_loc"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="asterisk_loc" name="asterisk_loc" value="<?php echo FreePBX::Endpointman()->configmod->get("asterisk_location"); ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="asterisk_loc-help"><?php echo _("Path location Asterisk."); ?></span>
			</div>
		</div>
	</div>
	<!--END Asterisk Executable Path-->
	<!--Aastra anacrypt Path-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="anacrypt_loc"><?php echo _("Aastra anacrypt Executable Path")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="anacrypt_loc"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="anacrypt_loc" name="anacrypt_loc" value="<?php echo epm_esc(FreePBX::Endpointman()->configmod->get("anacrypt_location")); ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="anacrypt_loc-help"><?php echo _("The full path of the Aastra anacrypt program, for encrypted Aastra config files. If the path is empty, the module never runs the program. Before each run, the module makes sure that the file is executable and is outside _ep_phone_modules. The web server user must not be able to write to the file or to its directory. anacrypt is an i386 program. On Debian 12, run these commands as root: 'dpkg --add-architecture i386', 'apt update' and 'apt install libc6:i386 libstdc++6:i386 libgcc-s1:i386'."); ?></span>
			</div>
		</div>
	</div>
	<!--END Aastra anacrypt Path-->
	<!--Aastra anacrypt Key-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="anacrypt_key"><?php echo _("Aastra anacrypt Key")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="anacrypt_key"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="anacrypt_key" name="anacrypt_key" value="<?php echo epm_esc(FreePBX::Endpointman()->configmod->get("anacrypt_key")); ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="anacrypt_key-help"><?php echo _("The password that anacrypt uses (-p) to encrypt Aastra config files. The default, 1234abcd, is the value that earlier versions used."); ?></span>
			</div>
		</div>
	</div>
	<!--END Aastra anacrypt Key-->
</div>

<div class="section-title" data-for="setting_web_directories">
	<h3><i class="fa fa-minus"></i><?php echo _("Web Directories") ?></h3>
</div>
<div class="section" data-id="setting_web_directories">
	<!--Package Server-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="package_server"><?php echo _("Package Server")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="package_server"></i>
						</div>
						<div class="col-md-9">
							<div class="input-group">
      							<input type="text" class="form-control" placeholder="Server Packages..." id="package_server" name="package_server" value="<?php echo FreePBX::Endpointman()->configmod->get("update_server"); ?>">
      							<span class="input-group-btn">
        							<button class="btn btn-default" type="button" id='default_package_server' onclick="epm_advanced_tab_setting_input_value_change_bt('#package_server', sValue = '<?php echo epm_esc(\FreePBX\modules\Endpointman::DEFAULT_UPDATE_SERVER); ?>', bSaveChange = true);"><i class='fa fa-undo'></i> <?php echo _("Default Package Server")?></button>
      							</span>
    						</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="package_server-help"><?php echo _("URL download files and packages the configuration terminals."); ?></span>
			</div>
		</div>
	</div>
	<!--END Package Server-->
</div>

<div class="section-title" data-for="setting_other">
	<h3><i class="fa fa-minus"></i><?php echo _("Other Settings") ?></h3>
</div>
<div class="section" data-id="setting_other">
	<?php $endpoint_warning_selected = FreePBX::Endpointman()->configmod->get("disable_endpoint_warning"); ?>
	<!--Disable Tooltips-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-12">
							<label class="control-label" for="disable_endpoint_warning"><?php echo _("Disable Endpoint Manager Conflict Warning")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="disable_endpoint_warning"></i>
                            <div class="radioset pull-xs-right">
                                <input type="radio" name="disable_endpoint_warning" id="disable_endpoint_warning_yes" value="Yes" <?php echo ($endpoint_warning_selected  == 1 ? "CHECKED" : "") ?>>
                                <label for="disable_endpoint_warning_yes"><i class="fa fa-check"></i> <?php echo _("Yes");?></label>
                                <input type="radio" name="disable_endpoint_warning" id="disable_endpoint_warning_no" value="No" <?php echo ($endpoint_warning_selected == 0 ? "CHECKED" : "") ?>>
                                <label for="disable_endpoint_warning_no"><i class="fa fa-times"></i> <?php echo _("No");?></label>
                            </div>
                      	</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="disable_endpoint_warning-help">Enable this setting if you dont want to get a warning message anymore if you have the Commercial Endpoint Manager installed together with OSS Endpoint Manager</span>
			</div>
		</div>
	</div>
	<?php unset($help_selected); ?>
	<!--END Disable Tooltips-->

</div>
<div class="section-title" data-for="setting_experimental">
	<h3><i class="fa fa-minus"></i><?php echo _("Experimental") ?></h3>
</div>
<div class="section" data-id="setting_experimental">
	<!--Enable Debug Mode-->
	<?php
		$debug_selected = FreePBX::Endpointman()->configmod->get("debug");
		if ($debug_selected) {
			global $debug;
			//$debug = $debug . print_r($_REQUEST, true);
			//$endpointman->tpl->assign("debug", $debug);
		}
	?>
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-12">
							<label class="control-label" for="enable_debug" disabled><?php echo _("Enable Debug Mode")?> </label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="enable_debug"></i>
                            <div class="radioset pull-xs-right">
                                <input disabled type="radio" name="enable_debug" id="enable_debug_yes" value="Yes" <?php echo ($debug_selected  == 1 ? "CHECKED" : "") ?>>
                                <label disabled for="enable_debug_yes"><i class="fa fa-check"></i> <?php echo _("Yes");?></label>
                                <input type="radio" name="enable_debug" id="enable_debug_no" value="No" <?php echo ($debug_selected == 0 ? "CHECKED" : "") ?>>
                                <label for="enable_debug_no"><i class="fa fa-times"></i> <?php echo _("No");?></label>
                            </div>
                     	</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="enable_debug-help">Enable Advanced debugging mode for endpoint manager</span>
			</div>
		</div>
	</div>
	<?php unset($debug_selected); ?>
	<!--END Enable Debug Mode-->
	<!--Disable Tooltips-->
	<?php $help_selected = FreePBX::Endpointman()->configmod->get("disable_help"); ?>
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-12">
							<label class="control-label" for="disable_help"><?php echo _("Disable Tooltips")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="disable_help"></i>
                            <div class="radioset pull-xs-right">
                                <input type="radio" name="disable_help" id="disable_help_yes" value="Yes" <?php echo ($help_selected  == 1 ? "CHECKED" : "") ?>>
                                <label for="disable_help_yes"><i class="fa fa-check"></i> <?php echo _("Yes");?></label>
                                <input type="radio" name="disable_help" id="disable_help_no" value="No" <?php echo ($help_selected == 0 ? "CHECKED" : "") ?>>
                                <label for="disable_help_no"><i class="fa fa-times"></i> <?php echo _("No");?></label>
                            </div>
                      	</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="disable_help-help">Disable Tooltip popups</span>
			</div>
		</div>
	</div>
	<?php unset($help_selected); ?>
	<!--END Disable Tooltips-->
	<!--Allow Duplicate Extensions-->
	<?php $dupext_selected = FreePBX::Endpointman()->configmod->get("show_all_registrations"); ?>
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-12">
							<label class="control-label" for="allow_dupext"><?php echo _("Allow Duplicate Extensions")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="allow_dupext"></i>
                            <div class="radioset pull-xs-right">
                                <input type="radio" name="allow_dupext" id="allow_dupext_yes" value="Yes" <?php echo ($dupext_selected  == 1 ? "CHECKED" : "") ?>>
                                <label for="allow_dupext_yes"><i class="fa fa-check"></i> <?php echo _("Yes");?></label>
                                <input type="radio" name="allow_dupext" id="allow_dupext_no" value="No" <?php echo ($dupext_selected == 0 ? "CHECKED" : "") ?>>
                                <label for="allow_dupext_no"><i class="fa fa-times"></i> <?php echo _("No");?></label>
                            </div>
                    	</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="allow_dupext-help">Assign the same extension to multiple phones (Note: This is not supported by Asterisk)</span>
			</div>
		</div>
	</div>
	<?php unset($dupext_selected); ?>
	<!--END Allow Duplicate Extensions-->
	<!--Allow Saving Over Default Configuration Files-->
	<?php $allow_hdfiles = FreePBX::Endpointman()->configmod->get("allow_hdfiles"); ?>
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-12">
							<label class="control-label" for="allow_hdfiles"><?php echo _("Allow Saving Over Default Configuration Files")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="allow_hdfiles"></i>
                            <div class="radioset pull-xs-right">
                                <input type="radio" name="allow_hdfiles" id="allow_hdfiles_yes" value="Yes" <?php echo ($allow_hdfiles  == 1 ? "CHECKED" : "") ?>>
                                <label for="allow_hdfiles_yes"><i class="fa fa-check"></i> <?php echo _("Yes");?></label>
                                <input type="radio" name="allow_hdfiles" id="allow_hdfiles_no" value="No" <?php echo ($allow_hdfiles == 0 ? "CHECKED" : "") ?>>
                                <label for="allow_hdfiles_no"><i class="fa fa-times"></i> <?php echo _("No");?></label>
                            </div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="allow_hdfiles-help">When editing the configuration files allows one to save over the global template default instead of saving directly to the database. These types of changes can and will be overwritten when updating the brand packages from the configuration/installation page</span>
			</div>
		</div>
	</div>
	<?php unset($allow_hdfiles); ?>
	<!--END Allow Saving Over Default Configuration Files-->
	<!--Disable TFTP Server Check-->
	<?php $tftp_checked = FreePBX::Endpointman()->configmod->get("tftp_check"); ?>
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-12">
							<label class="control-label" for="tftp_check"><?php echo _("Disable TFTP Server Check")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="tftp_check"></i>
							<div class="radioset pull-xs-right">
								<input type="radio" name="tftp_check" id="tftp_check_yes" value="Yes" <?php echo ($tftp_checked  == 1 ? "CHECKED" : "") ?>>
								<label for="tftp_check_yes"><i class="fa fa-check"></i> <?php echo _("Yes");?></label>
								<input type="radio" name="tftp_check" id="tftp_check_no" value="No" <?php echo ($tftp_checked == 0 ? "CHECKED" : "") ?>>
								<label for="tftp_check_no"><i class="fa fa-times"></i> <?php echo _("No");?></label>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="tftp_check-help">Disable checking for a valid, working TFTP server which can sometimes cause Apache to crash</span>
			</div>
		</div>
	</div>
	<?php unset($tftp_checked); ?>
	<!--END Disable TFTP Server Check-->
	<!--Disable Configuration File Backups-->
	<?php $backup_checked = FreePBX::Endpointman()->configmod->get("backup_check"); ?>
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-12">
							<label class="control-label" for="backup_check"><?php echo _("Disable Configuration File Backups")?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="backup_check"></i>
                            <div class="radioset pull-xs-right">
                                <input type="radio" name="backup_check" id="backup_check_yes" value="Yes" <?php echo ($backup_checked  == 1 ? "CHECKED" : "") ?>>
                                <label for="backup_check_yes"><i class="fa fa-check"></i> <?php echo _("Yes");?></label>
                                <input type="radio" name="backup_check" id="backup_check_no" value="No" <?php echo ($backup_checked == 0 ? "CHECKED" : "") ?>>
                                <label for="backup_check_no"><i class="fa fa-times"></i> <?php echo _("No");?></label>
                            </div>
   						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span class="help-block fpbx-help-block" id="backup_check-help">Disable backing up the tftboot directory on every phone rebuild or save</span>
			</div>
		</div>
	</div>
	<?php unset($backup_checked); ?>


	<!--END GIT Branch-->
</div>
