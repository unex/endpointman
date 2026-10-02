<?php
/**
 * The Placeholders page: the values that the config files of a brand
 * package can use.
 *
 * Each row is: brand, placeholder, where it works, description. "L" is a
 * {line_loop} block, "S" is outside of one, and "LS" is both.
 */
if (!defined('FREEPBX_IS_AUTH')) die('No direct script access allowed');

echo load_view(__DIR__ . '/epm_templates/subnav.php', array('current' => 'placeholders'));

$rows = array(
	array('Global', '$globaladminpassword', 'LS', _('The admin password of the phones, from the Settings page')),
	array('Global', '$globaluserpassword', 'LS', _('The user password of the phones, from the Settings page')),
	array('Global', '$provisuser', 'LS', _('The HTTP provisioning user of Sysadmin Pro')),
	array('Global', '$provispass', 'LS', _('The HTTP provisioning password of Sysadmin Pro')),
	array('Global', '$hpro', 'LS', _('The HTTP provisioning port of Sysadmin Pro')),
	array('Global', '$sslhpro', 'LS', _('The HTTPS provisioning port of Sysadmin Pro')),
	array('Global', '$primtimeserver', 'LS', _('The time server from the Settings page')),
	array('Global', '$myvoicemail', 'LS', _('The voicemail feature code')),
	array('Global', '$line', 'L', _('The line number of the extension on the phone')),
	array('Global', '$username', 'L', _('The user name of the extension, usually the extension number')),
	array('Global', '$authname', 'L', _('The authentication name of the extension, usually the extension number')),
	array('Global', '$secret', 'L', _('The password of the extension')),
	array('Global', '$displayname', 'L', _('The display name of the extension')),
	array('Global', '$server_host', 'L', _('The server address for the extension, from the Settings page')),
	array('Global', '$server_port', 'L', _('The port that the extension connects to')),
	array('Global', '$proto', 'L', _('The protocol of the extension')),
	array('Global', '$user_extension', 'L', _('The extension number')),
	array('Global', '$extension', 'L', _('The extension number')),
	array('Global', '$allowedcodec', 'L', _('The allowed codecs of the extension')),
	array('Global', '$forcerport', 'L', _('The rport setting of the extension')),
	array('Global', '$media_encryption', 'L', _('The media encryption setting of the extension')),
	array('Global', '$encryption', 'L', _('The encryption setting of the extension')),
	array('Global', '$sipdriver', 'L', _('The channel driver of the extension: SIP or PJSIP')),
	array('Global', '$tech', 'L', _('The channel driver of the extension: SIP or PJSIP')),
	array('Global', '$transport', 'L', _('The transport protocol of the extension')),
	array('Global', '$trustrpid', 'L', _('The trustrpid setting of the extension')),
	array('Global', '$callerid', 'L', _('The caller ID of the extension, with the name and the number')),
	array('Global', '$accXsecret', 'S', _('The password of the extension')),
	array('Global', '$accXdisplayname', 'S', _('The display name of the extension')),
	array('Global', '$accXuser_extension', 'S', _('The extension number')),
	array('Global', '$accXusername', 'S', _('The user name of the extension')),
	array('Global', '$accXauthname', 'S', _('The authentication name of the extension')),
	array('Global', '$accXextension', 'S', _('The extension number')),
	array('Global', '$accXserver_port', 'S', _('The port that the extension connects to')),
	array('Global', '$accXallowedcodec', 'S', _('The allowed codecs of the extension')),
	array('Global', '$accXforcerport', 'S', _('The rport setting of the extension')),
	array('Global', '$accXmedia_encryption', 'S', _('The media encryption setting of the extension')),
	array('Global', '$accXencryption', 'S', _('The encryption setting of the extension')),
	array('Global', '$accXsipdriver', 'S', _('The channel driver of the extension: SIP or PJSIP')),
	array('Global', '$accXtransport', 'S', _('The transport protocol of the extension')),
	array('Global', '$accXtrustrpid', 'S', _('The trustrpid setting of the extension')),
	array('Global', '$accXcallerid', 'S', _('The caller ID of the extension, with the name and the number')),
	array('Yealink', '$yealinktransport', 'L', _('The transport protocol in the Yealink form: UDP, TCP or TLS')),
	array('Yealink', '$accXyealinktransport', 'S', _('The transport protocol in the Yealink form: UDP, TCP or TLS')),
	array('Yealink', '$yealinksrtp', 'L', _('The SRTP value in the Yealink form')),
	array('Yealink', '$accXyealinksrtp', 'S', _('The SRTP value in the Yealink form')),
	array('Grandstream', '$gsSRTP', 'L', _('The SRTP value in the Grandstream form, 0 or 1. Tested with the HT812.')),
	array('Grandstream', '$accXgsSRTP', 'S', _('The SRTP value in the Grandstream form, 0 or 1. Tested with the HT812.')),
	array('Grandstream', '$gsproto', 'L', _('The protocol in the Grandstream form: 0 for UDP, 1 for TCP, 2 for TLS. Tested with the HT812.')),
	array('Grandstream', '$accXgsproto', 'S', _('The protocol in the Grandstream form: 0 for UDP, 1 for TCP, 2 for TLS. Tested with the HT812.')),
);
$where = array(
	'L'  => _('Line loop'),
	'S'  => _('Static'),
	'LS' => _('Line loop, static'),
);
$brands = array('Global' => _('All brands'), 'Yealink' => 'Yealink', 'Grandstream' => 'Grandstream');
?>
<p><?php echo _('Use these placeholders in the config files of a brand package. When the module writes the configs of a phone, it replaces each placeholder with its value.'); ?></p>
<ul>
	<li><?php echo _('Line loop: use the placeholder in a {line_loop} block. The module repeats the block for each line of the phone.'); ?></li>
	<li><?php echo _('Static: use the placeholder outside of a {line_loop} block. Replace the X in accX with the line number, for example $acc1secret.'); ?></li>
</ul>
<p class="text-muted"><?php echo _('To ask for a new placeholder, open an issue in the endpointman project.'); ?></p>

<table id="hwgrid" data-toggle="table" data-pagination="true" data-page-size="25" data-search="true" class="table table-striped">
	<thead>
		<tr>
			<th data-field="placeholder" data-sortable="true"><?php echo _('Placeholder'); ?></th>
			<th data-field="brand" data-sortable="true"><?php echo _('Brand'); ?></th>
			<th data-field="where" data-sortable="true"><?php echo _('Where'); ?></th>
			<th data-field="description" data-sortable="true"><?php echo _('Description'); ?></th>
		</tr>
	</thead>
	<tbody>
<?php foreach ($rows as $row) { ?>
		<tr>
			<td class="text-monospace"><?php echo epm_esc($row[1]); ?></td>
			<td><?php echo epm_esc($brands[$row[0]]); ?></td>
			<td><?php echo epm_esc($where[$row[2]]); ?></td>
			<td><?php echo epm_esc($row[3]); ?></td>
		</tr>
<?php } ?>
	</tbody>
</table>
