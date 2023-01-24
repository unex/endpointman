<?PHP

/**
 * Endpoint Manager Installer
 *
 * @author Andrew Nagy
 * @license MPL / GPLv2 / LGPL
 * @package Endpoint Manager
 */

function epm_find_exec($exec) {
    $usr_bin = glob("/usr/bin/" . $exec);
    $usr_sbin = glob("/usr/sbin/" . $exec);
    $sbin = glob("/sbin/" . $exec);
    $bin = glob("/bin/" . $exec);
    $etc = glob("/etc/" . $exec);
    if (isset($usr_bin[0])) {
        return("/usr/bin/" . $exec);
    } elseif (isset($usr_sbin[0])) {
        return("/usr/sbin/" . $exec);
    } elseif (isset($sbin[0])) {
        return("/sbin/" . $exec);
    } elseif (isset($bin[0])) {
        return("/bin/" . $exec);
    } elseif (isset($etc[0])) {
        return("/etc/" . $exec);
    } else {
        return($exec);
    }
}

global $db;

out("Endpoint Manager Installer");

//define("PHONE_MODULES_PATH", $amp_conf['AMPWEBROOT'] . '/admin/modules/_ep_phone_modules/');
//define("LOCAL_PATH", $amp_conf['AMPWEBROOT'] . '/admin/modules/endpointman/');


if (!file_exists(PHONE_MODULES_PATH)) {
    mkdir(PHONE_MODULES_PATH, 0764);
    out("Creating Phone Modules Directory");
}

if (!file_exists(PHONE_MODULES_PATH . "setup.php")) {
    copy(LOCAL_PATH . "install/setup.php", PHONE_MODULES_PATH . "setup.php");
    out("Moving Auto Provisioner Class");
}

if (!file_exists(PHONE_MODULES_PATH . "temp/")) {
    mkdir(PHONE_MODULES_PATH . "temp/", 0764);
    out("Creating temp folder");
}

$modinfo = module_getinfo('endpointman');
$epmxmlversion = $modinfo['endpointman']['version'];
$epmdbversion = !empty($modinfo['endpointman']['dbversion']) ? $modinfo['endpointman']['dbversion'] : null;

if (empty($epmdbversion)) {
	out("Locating NMAP + ARP + ASTERISK Executables");
	$nmap = epm_find_exec("nmap");
	$arp = epm_find_exec("arp");
	$asterisk = epm_find_exec("asterisk");

	$sth = FreePBX::Database()->prepare('SELECT * FROM `endpointman_global_vars`');
	$sth->execute();
	$defaults = $sth->fetchAll(\PDO::FETCH_ASSOC);
	if(empty($defaults)) {

		outn("Inserting data into the global vars Table..");
		$sql = "INSERT INTO `endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES
		(1, 'srvip', ''),
		(2, 'tz', ''),
		(3, 'gmtoff', ''),
		(4, 'gmthr', ''),
		(5, 'config_location', '/tftpboot/'),
		(6, 'update_server', 'http://provision.lol/'),
		(7, 'version', '" . $epmxmlversion  . "'),
		(8, 'enable_ari', '0'),
		(9, 'debug', '0'),
		(10, 'arp_location', '" . $arp . "'),
		(11, 'nmap_location', '" . $nmap . "'),
		(12, 'asterisk_location', '" . $asterisk . "'),
		(13, 'language', ''),
		(14, 'check_updates', '0'),
		(15, 'disable_htaccess', ''),
		(16, 'endpoint_vers', '0'),
		(17, 'disable_help', '0'),
		(18, 'show_all_registrations', '0'),
		(19, 'ntp', ''),
		(20, 'server_type', 'file'),
		(21, 'allow_hdfiles', '0'),
		(22, 'tftp_check', '0'),
		(23, 'nmap_search', ''),
		(24, 'backup_check', '0'),
		(25, 'use_repo', '0'),
		(26, 'adminpass', '123456'),
		(27, 'userpass', '111111'),
		(28, 'intsrvip', ''),
		(29, 'disable_endpoint_warning', '0')";
		
		$db->query($sql);
		out("Done");
	}
	outn('Creating symlink to web provisioner..');
	if(!file_exists($amp_conf['AMPWEBROOT'] . "/provisioning")) {
		if (!symlink(LOCAL_PATH . "provisioning", $amp_conf['AMPWEBROOT'] . "/provisioning")) {
			//out("<strong>Your permissions are wrong on ".$amp_conf['AMPWEBROOT'].", web provisioning link not created!</strong>");
		}
	}
	out("Done");
}

out("Update Version Number to " . $epmxmlversion);
$sql = "UPDATE endpointman_global_vars SET value = '" . $epmxmlversion . "' WHERE var_name = 'version'";
$db->query($sql);


if ($epmdbversion < "14.0.0.1"){
$sql = "UPDATE endpointman_global_vars SET value = 'http://provision.lol/' WHERE var_name = 'update_server'";
$db->query($sql);
}

if ($epmdbversion < "14.0.1.4"){
		$sql = "INSERT INTO `endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES
		(26, 'adminpass', '123456'),
		(27, 'userpass', '111111'),
		(28, 'intsrvip', ''),
		(29, 'disable_endpoint_warning', '0')";
		$db->query($sql);
		out("Done");
	}
