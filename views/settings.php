<?php
/**
 * The Settings page.
 *
 * Each field saves when it changes (SettingsPage::_settings_saveconfig, by
 * the field name). The page shows the result of the TFTP check, when the
 * phones get their configs over TFTP.
 *
 * @license MPL / GPLv2 / LGPL
 * @package Endpoint Manager
 */
if (!defined('FREEPBX_IS_AUTH')) die('No direct script access allowed');

$epm = FreePBX::Endpointman();
$cfg = $epm->configmod;
$cfg->getConfigModuleSQL(false);

$server_type = $cfg->get('server_type');
if ($server_type == 'file' && $epm->_get_page('settings')->_config_loc_is_writable()) {
	$epm->tftp_check();
}
$server = epm_esc($_SERVER['SERVER_ADDR']);

$text_input = function ($name, $value, $attrs = '') {
	return '<input type="text" class="form-control" id="' . $name . '" name="' . $name . '" value="' . epm_esc($value) . '" ' . $attrs . '>';
};
$with_button = function ($control, $target, $value, $label) {
	return '<div class="input-group">' . $control . '<div class="input-group-append">'
		. '<button class="btn btn-default" type="button" data-fill="' . $target . '" data-value="' . epm_esc($value) . '">' . $label . '</button></div></div>';
};

$text = array(
	'saved' => _('Saved.'),
	'error' => _('The change was not saved.'),
	'phones_url' => _('Set the provisioning server of the phones to:'),
);
?>
<script>var epmSettingsText = <?php echo json_encode($text); ?>;</script>
<p class="text-muted"><?php echo _('Each change saves when you leave the field.'); ?></p>

<?php if (!empty($epm->error['tftp_check'])) { ?>
<div class="alert alert-warning" role="alert"><?php echo $epm->error['tftp_check']; ?></div>
<?php } ?>

<form id="epm-settings" autocomplete="off" onsubmit="return false;">

<h3><?php echo _('Phone server'); ?></h3>
<?php
	echo epm_form_row('srvip', _('Server address'),
		$with_button($text_input('srvip', $cfg->get('srvip')), '#srvip', $_SERVER['SERVER_ADDR'], _('Use this server')),
		_('The address that the phones use to register and to get their configs. A new install has no value, and the configs do not work without it.'));
	echo epm_form_row('intsrvip', _('Internal server address'),
		$with_button($text_input('intsrvip', $cfg->get('intsrvip')), '#intsrvip', $_SERVER['SERVER_ADDR'], _('Use this server')),
		_('The address for phones on the internal network, when it is not the same as the server address. Keep it blank to use the server address.'));

	$types = array('file' => _('File (TFTP or FTP)'), 'http' => _('Web (HTTP)'), 'https' => _('Web (HTTPS)'));
	$control = '<select class="form-control" id="cfg_type" name="cfg_type">';
	foreach ($types as $value => $label) {
		$control .= '<option value="' . $value . '"' . ($server_type == $value ? ' selected' : '') . '>' . $label . '</option>';
	}
	$control .= '</select>';
	$url = ($server_type === 'https' ? 'https' : 'http') . '://' . $cfg->get('srvip') . '/provisioning/p.php/';
	$control .= '<div class="alert alert-info mt-2 mb-0' . (in_array($server_type, array('http', 'https'), true) ? '' : ' d-none') . '" id="cfg_type_alert">'
		. _('Set the provisioning server of the phones to:') . ' <span class="text-monospace" id="cfg_type_url">' . epm_esc($url) . '</span></div>';
	echo epm_form_row('cfg_type', _('Provisioning type'), $control,
		_('How the phones get their config files: from the TFTP server, or from this web server.'));

	echo epm_form_row('config_loc', _('Config directory'), $text_input('config_loc', $cfg->get('config_location')),
		_('The directory where the module writes the config files, for example /tftpboot/. The TFTP server must serve this directory.'));

	$acl = $cfg->get('provision_acl');
	$control = $text_input('provision_acl', $acl, 'placeholder="10.0.0.0/8, 192.168.0.0/16"');
	if (in_array($server_type, array('http', 'https'), true) && trim($acl) === '') {
		$control .= '<div class="alert alert-danger mt-2 mb-0" role="alert">' . _('The list is empty, so no phone can get its config. Add the networks of your phones.') . '</div>';
	}
	echo epm_form_row('provision_acl', _('Provisioning allowlist'), $control,
		_('The addresses that can get a config over HTTP or HTTPS. Use addresses or CIDR blocks, IPv4 or IPv6, with commas between them. The configs contain the SIP passwords, and the phones do not log in, so this list is the only control of who can read them. An empty list refuses all addresses. The list does not apply to File provisioning.'));

	echo epm_form_row('trusted_proxies', _('Trusted reverse proxies'), $text_input('trusted_proxies', $cfg->get('trusted_proxies'), 'placeholder="' . epm_esc(_('none')) . '"'),
		_('Keep it blank when the phones do not connect through a reverse proxy. Else, use the addresses or CIDR blocks of the proxies. For a request from a proxy, the module uses the phone address and the scheme from X-Forwarded-For and X-Forwarded-Proto. The allowlist then applies to the phone, and HTTPS works when the proxy ends TLS.'));
?>

<h3><?php echo _('Phone passwords'); ?></h3>
<?php
	echo epm_form_row('adminpass', _('Admin password'), $text_input('adminpass', $cfg->get('adminpass')),
		_('The admin password of the phones. Use 6 digits, because some phones accept only digits.'));
	echo epm_form_row('userpass', _('User password'), $text_input('userpass', $cfg->get('userpass')),
		_('The user password of the phones. Use 6 digits, because some phones accept only digits.'));
?>

<h3><?php echo _('Time'); ?></h3>
<?php
	$control = '<select class="form-control" id="tz" name="tz">';
	foreach ($epm->listTZ($cfg->get('tz')) as $row) {
		$control .= '<option value="' . epm_esc($row['value']) . '"' . (!empty($row['selected']) ? ' selected' : '') . '>' . epm_esc($row['text']) . '</option>';
	}
	$control .= '</select>';
	echo epm_form_row('tz', _('Time zone'), $with_button($control, '#tz', $epm->config->get('PHPTIMEZONE'), _('Use the PBX time zone')),
		_('The time zone of the phones, for example Europe/London.'));
	echo epm_form_row('ntp_server', _('Time server'), $with_button($text_input('ntp_server', $cfg->get('ntp')), '#ntp_server', $_SERVER['SERVER_ADDR'], _('Use this server')),
		_('The NTP server of the phones.'));
?>

<h3><?php echo _('Package server'); ?></h3>
<?php
	echo epm_form_row('package_server', _('Package server'),
		$with_button($text_input('package_server', $cfg->get('update_server')), '#package_server', \FreePBX\modules\Endpointman::DEFAULT_UPDATE_SERVER, _('Use the default')),
		_('The URL of the brand packages. The module downloads only data files from it.'));
?>

<h3><?php echo _('Programs'); ?></h3>
<?php
	echo epm_form_row('nmap_loc', _('nmap'), $text_input('nmap_loc', $cfg->get('nmap_location')),
		_('The path of nmap, which Find phones uses. Keep it blank to search the PATH.'));
	echo epm_form_row('arp_loc', _('Neighbor table tool'), $text_input('arp_loc', $cfg->get('arp_location')),
		_('The path of the tool that reads the network neighbor table: ip from iproute2, or arp from net-tools. Keep it blank to search the PATH.'));
	echo epm_form_row('anacrypt_loc', _('Aastra anacrypt'), $text_input('anacrypt_loc', $cfg->get('anacrypt_location')),
		_('The full path of the Aastra anacrypt program, which encrypts Aastra config files. When the path is blank, the module never runs the program. Before each run, the module makes sure that the file is executable and is outside _ep_phone_modules. The web server user must not be able to write to the file or to its directory. anacrypt is an i386 program. On Debian 12, run these commands as root: dpkg --add-architecture i386, apt update, and apt install libc6:i386 libstdc++6:i386 libgcc-s1:i386.'));
	echo epm_form_row('anacrypt_key', _('Aastra anacrypt key'), $text_input('anacrypt_key', $cfg->get('anacrypt_key')),
		_('The password that anacrypt uses (-p) to encrypt the Aastra config files. The default, 1234abcd, is the value that earlier versions used.'));
?>

<h3><?php echo _('Options'); ?></h3>
<?php
	echo epm_form_row('allow_dupext_yes', _('Allow duplicate extensions'), epm_form_yesno('allow_dupext', $cfg->get('show_all_registrations') == 1),
		_('Allow one extension on more than one phone. Asterisk does not support this.'));
	echo epm_form_row('allow_hdfiles_yes', _('Save over package files'), epm_form_yesno('allow_hdfiles', $cfg->get('allow_hdfiles') == 1),
		_('Let the config file popup save over the file of the package, and not to the database. A brand update overwrites these changes.'));
	echo epm_form_row('backup_check_yes', _('Skip config backups'), epm_form_yesno('backup_check', $cfg->get('backup_check') == 1),
		_('Do not make a backup of the config directory when the module writes the configs of a phone.'));
	echo epm_form_row('tftp_check_yes', _('Skip the TFTP check'), epm_form_yesno('tftp_check', $cfg->get('tftp_check') == 1),
		_('Do not check the TFTP server when this page opens. On some systems, the check stops the web server.'));
	echo epm_form_row('disable_endpoint_warning_yes', _('Hide the conflict notice'), epm_form_yesno('disable_endpoint_warning', $cfg->get('disable_endpoint_warning') == 1),
		_('Do not show the notice about the commercial Endpoint Manager on the Phones and Brands pages.'));
?>
</form>

<script type="text/javascript" src="modules/endpointman/assets/js/views/settings.js"></script>
