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

out("Installing OSS Endpoint Manager");

// FreePBX makes the module class before it calls install(), so these
// properties have values when this file runs.
$PHONE_MODULES_PATH = FreePBX::Endpointman()->PHONE_MODULES_PATH;
$LOCAL_PATH         = FreePBX::Endpointman()->LOCAL_PATH;


if (!file_exists($PHONE_MODULES_PATH)) {
    mkdir($PHONE_MODULES_PATH, 0764);
    out("Making the phone modules directory");
}

// Remove the downloaded PHP from the data tree.
//
// Before 17.0.0.2, the module downloaded the provisioner library into
// _ep_phone_modules/: autoload.php and setup.php at the top,
// endpoint/base.php, and one base.php or phone.php for each brand and family.
// The library now ships with the module, and nothing loads from this tree.
// Thus the old files are unused code in the web root. From this version on,
// the tree holds only data. extract_package() keeps it so for new downloads,
// and this step removes the files of earlier versions.
$stale_php = array();
foreach (array("setup.php", "autoload.php") as $f) {
    if (file_exists($PHONE_MODULES_PATH . $f)) {
        $stale_php[] = $PHONE_MODULES_PATH . $f;
    }
}
if (is_dir($PHONE_MODULES_PATH . "endpoint")) {
    $walk = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($PHONE_MODULES_PATH . "endpoint", FilesystemIterator::SKIP_DOTS)
    );
    foreach ($walk as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $stale_php[] = $file->getPathname();
        }
    }
}
if (!empty($stale_php)) {
    outn(sprintf("Removing %d downloaded PHP file(s) from the phone modules tree..", count($stale_php)));
    foreach ($stale_php as $f) {
        @unlink($f);
    }
    out("Done");
}

// Remove each file that a data-only package cannot contain.
//
// Older brand packages also put other files into endpoint/, for example the
// Aastra program endpoint/aastra/anacrypt. extract_package() now refuses a
// package with such a file. This step removes the copies from earlier
// versions, with the same allowlist, and names each file that it removes.
$not_data = FreePBX::Endpointman()->system->remove_non_data_files($PHONE_MODULES_PATH . "endpoint");
foreach ($not_data['removed'] as $f) {
    out("Removed " . substr($f, strlen($PHONE_MODULES_PATH)) . ", because it is not a data file");
}
foreach ($not_data['failed'] as $f) {
    out("<strong>Warning: Cannot remove " . substr($f, strlen($PHONE_MODULES_PATH)) . ". It is not a data file. Remove it yourself.</strong>");
}

if (!file_exists($PHONE_MODULES_PATH . "temp/")) {
    mkdir($PHONE_MODULES_PATH . "temp/", 0764);
    out("Making the temp directory");
}

// endpoint/ holds brand data from the package server and one file of the
// library: global_template_data.json. The drivers read it through $root_dir.
// Copy it from the library that ships with the module. A later brand
// download does not overwrite it.
if (!file_exists($PHONE_MODULES_PATH . "endpoint/")) {
    mkdir($PHONE_MODULES_PATH . "endpoint/", 0775, true);
    out("Making the endpoint directory");
}
$global_template_data = $PHONE_MODULES_PATH . "endpoint/global_template_data.json";
$shipped_template_data = $LOCAL_PATH . "lib/provisioner/endpoint/global_template_data.json";
if (file_exists($shipped_template_data)) {
    if (!file_exists($global_template_data) || filemtime($shipped_template_data) > filemtime($global_template_data)) {
        copy($shipped_template_data, $global_template_data);
        out("Installing the global template data");
    }
} else {
    out("<strong>Warning: lib/provisioner/endpoint/global_template_data.json is missing. The module cannot write configs.</strong>");
}

$modinfo = module_getinfo('endpointman');
$epmxmlversion = $modinfo['endpointman']['version'];
$epmdbversion = !empty($modinfo['endpointman']['dbversion']) ? $modinfo['endpointman']['dbversion'] : null;

$epm = FreePBX::Endpointman();

// --- Settings storage -------------------------------------------------------
//
// Before 17.0.0.2, the settings were in `endpointman_global_vars`, a table
// with three columns and 29 fixed rows. The settings are now in the BMO
// KVStore (`kvstore_FreePBX_modules_Endpointman`). DB_Helper makes it on
// first use. Other FreePBX 17 modules keep their configuration there, and the
// backup framework can see it.
//
// This step moves the rows and then drops the table. module.xml does not
// declare the table, so nothing makes it again.

$dropped_vars = array(
	// Repo mode does not exist.
	'use_repo',
	// Nothing reads these. The .htaccess provisioning path does not exist.
	'endpoint_vers',
	'disable_htaccess',
);

$has_old_table = false;
try {
	FreePBX::Database()->query('SELECT 1 FROM `endpointman_global_vars` LIMIT 1');
	$has_old_table = true;
} catch (\Exception $e) {
	// 42S02: no such table. This is a new install, or the move is complete.
	$has_old_table = false;
}

if ($has_old_table) {
	outn("Moving the global settings to the KVStore... ");
	$sth = FreePBX::Database()->prepare('SELECT `var_name`, `value` FROM `endpointman_global_vars`');
	$sth->execute();
	$moved = 0;
	foreach ($sth->fetchAll(\PDO::FETCH_ASSOC) as $row) {
		if (in_array($row['var_name'], $dropped_vars, true)) { continue; }
		$epm->configmod->save($row['var_name'], (string) $row['value']);
		$moved++;
	}
	FreePBX::Database()->query('DROP TABLE `endpointman_global_vars`');
	out("Done. Moved " . $moved . " settings, and removed the old table.");
}

// Set each key that the move did not supply. On a new install this is each
// key. On an upgrade it is each key that a later release added.
//
// Read the KVStore directly, not Config::getall(). getall() merges the
// defaults into its result, so each key looks present, and the loop below
// sets nothing.
$stored = $epm->getAll();
if (!is_array($stored)) { $stored = array(); }
$seeded = 0;
foreach (\FreePBX\modules\Endpointman\Config::defaults() as $key => $value) {
	if (array_key_exists($key, $stored)) { continue; }
	$epm->configmod->save($key, $value);
	$seeded++;
}
if ($seeded) { out("Added the default value of " . $seeded . " settings"); }

// These depend on the host, so find them. Set them only when they are empty,
// so that a path that the admin corrected stays.
//
// `arp_location` holds the neighbor table tool of the host. Try `ip` from
// iproute2 first, because Debian 12 (the base of FreePBX 17) has it and does
// not have `arp` from net-tools. epm_system::neighbour_table() reads both
// formats. Do not store a tool that is not on disk. An empty value lets the
// module search PATH at run time, and a later upgrade can fill the key when
// the package is installed.
// The drivers send phone reboots over AMI, so nothing reads the path of
// asterisk now. Remove the key of older versions.
$epm->configmod->del('asterisk_location');

foreach (array('nmap_location'     => array('nmap'),
               'arp_location'      => array('ip', 'arp')) as $key => $execs) {
	// A stored path that is not on disk is not a correction. It is a bare name
	// from older versions ("arp", "nmap"). file_exists() rejects it, and the
	// settings page shows it as a path. Search again, and find `ip` on hosts that
	// got the package later.
	$stored_path = $epm->configmod->get($key);
	if (($stored_path !== '') && file_exists($stored_path)) { continue; }
	out("Finding " . implode(' or ', $execs));
	$found = '';
	foreach ($execs as $exec) {
		$path = epm_find_exec($exec);
		if (($path !== $exec) && file_exists($path)) { $found = $path; break; }
	}
	$epm->configmod->save($key, $found);
}

if (empty($epmdbversion)) {
	outn('Making the link to the provisioning endpoint... ');
	if (!file_exists($amp_conf['AMPWEBROOT'] . "/provisioning")) {
		if (!symlink($LOCAL_PATH . "provisioning", $amp_conf['AMPWEBROOT'] . "/provisioning")) {
			//out("<strong>Your permissions are wrong on ".$amp_conf['AMPWEBROOT'].", web provisioning link not created!</strong>");
		}
	}
	out("Done");
}

// --- Blob columns -----------------------------------------------------------
//
// Eleven columns hold a structured value, not a scalar. Before 17.0.0.3,
// they held PHP serialize() data. The module now writes JSON. This step
// converts the stored rows, so new backups do not contain the old format.
// epm_blob_decode() reads both formats, so a row that this step misses (for
// example a damaged blob) still works.

if ($epmdbversion < "17.0.0.3") {
	$blob_columns = array(
		'endpointman_model_list'    => array('id',   array('template_data')),
		'endpointman_mac_list'      => array('id',   array('global_custom_cfg_data', 'global_user_cfg_data',
		                                                   'global_settings_override', 'specific_settings',
		                                                   'config_files_override')),
		'endpointman_template_list' => array('id',   array('global_custom_cfg_data', 'global_settings_override',
		                                                   'config_files_override')),
		'endpointman_line_list'     => array('luid', array('custom_cfg_data', 'user_cfg_data')),
	);

	outn("Converting the serialized columns to JSON... ");
	$converted = 0;
	$skipped = 0;
	foreach ($blob_columns as $table => $spec) {
		list($pk, $columns) = $spec;
		foreach ($columns as $column) {
			$sth = FreePBX::Database()->prepare(
				"SELECT `$pk`, `$column` FROM `$table` WHERE `$column` IS NOT NULL AND `$column` != ''"
			);
			$sth->execute();
			$upd = FreePBX::Database()->prepare("UPDATE `$table` SET `$column` = ? WHERE `$pk` = ?");
			foreach ($sth->fetchAll(\PDO::FETCH_NUM) as $row) {
				$first = substr(ltrim((string) $row[1]), 0, 1);
				if ($first === '{' || $first === '[') { continue; }   // already JSON
				$value = epm_blob_decode($row[1], null);
				if ($value === null) { $skipped++; continue; }        // unreadable; leave it alone
				$upd->execute(array(epm_blob_encode($value), $row[0]));
				$converted++;
			}
		}
	}
	out("Done. Converted " . $converted . " values" . ($skipped ? ". " . $skipped . " values cannot be read and stay as they are" : "") . ".");
}

out("Setting the version to " . $epmxmlversion);
$epm->configmod->save('version', $epmxmlversion);

// The old default, http://provision.lol/, is a parked domain, and the
// provisioner.net hosts do not resolve. A value that points to one of them
// cannot work, so point it to the live backend.
//
// The root of unex.github.io/provisioner/ keeps the 2023 packages for
// FreePBX 16. They contain PHP, and extract_package() refuses them. This
// version reads the data-only packages under /v4/. A URL that the admin chose
// stays as it is.
$update_server = trim((string) $epm->configmod->get('update_server'));
if ($update_server === ''
    || $update_server === 'https://unex.github.io/provisioner/'
    || strpos($update_server, 'provision.lol') !== false
    || strpos($update_server, 'provisioner.net') !== false) {
	out("Moving the package server from '" . $update_server . "' to " . \FreePBX\modules\Endpointman::DEFAULT_UPDATE_SERVER);
	$epm->configmod->save('update_server', \FreePBX\modules\Endpointman::DEFAULT_UPDATE_SERVER);
}
