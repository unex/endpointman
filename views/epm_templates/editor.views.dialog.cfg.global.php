<?php
/**
 * The dialog with the server settings of one template. These values replace
 * the values of the Settings page for the phones of the template.
 *
 * The dialog opens empty, and the page script reads the values
 * (custom_config_get_gloabl).
 */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

$server = epm_esc($_SERVER['SERVER_ADDR']);
$tz = '<select class="form-control" name="tz" id="tz">';
foreach (FreePBX::Endpointman()->listTZ("") as $row) {
	$tz .= '<option value="' . epm_esc($row['value']) . '">' . epm_esc($row['text']) . '</option>';
}
$tz .= '</select>';
?>
<div class="modal fade" id="CfgGlobalTemplate" tabindex="-1" role="dialog" aria-labelledby="CfgGlobalTemplate-title" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="CfgGlobalTemplate-title"><?php echo _('Server settings of this template'); ?></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="<?php echo epm_esc(_('Close')); ?>"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body">
				<p class="text-muted"><?php echo _('These values replace the values of the Settings page for the phones of this template.'); ?></p>
				<form action="" id="FormCfgGlobalTemplate" name="FormCfgGlobalTemplate">
<?php
	echo epm_form_row('srvip', _('Phone server address'),
		'<div class="input-group"><input type="text" class="form-control" id="srvip" name="srvip" value="">'
		. '<div class="input-group-append"><button class="btn btn-default" type="button" data-fill="#srvip" data-value="' . $server . '">' . _('Use this server') . '</button></div></div>',
		_('The address of the PBX that the phones register to.'));
	echo epm_form_row('server_type', _('Provisioning type'),
		'<select class="form-control" name="server_type" id="server_type">'
		. '<option value="file">' . _('File (TFTP or FTP)') . '</option>'
		. '<option value="http">' . _('Web (HTTP)') . '</option>'
		. '<option value="https" disabled>' . _('Web (HTTPS)') . '</option></select>',
		_('How the phones get their config files.'));
	echo epm_form_row('config_loc', _('Config directory'),
		'<input type="text" class="form-control" id="config_loc" name="config_loc" value="">',
		_('The directory of the config files on this server, for example /tftpboot/.'));
	echo epm_form_row('tz', _('Time zone'),
		'<div class="input-group">' . $tz
		. '<div class="input-group-append"><button class="btn btn-default" type="button" data-fill="#tz" data-value="' . epm_esc(FreePBX::Endpointman()->config->get('PHPTIMEZONE')) . '">' . _('Use the PBX time zone') . '</button></div></div>',
		_('The time zone of the phones.'));
	echo epm_form_row('ntp_server', _('Time server'),
		'<div class="input-group"><input type="text" class="form-control" id="ntp_server" name="ntp_server" value="">'
		. '<div class="input-group-append"><button class="btn btn-default" type="button" data-fill="#ntp_server" data-value="' . $server . '">' . _('Use this server') . '</button></div></div>',
		_('The NTP server of the phones.'));
?>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default mr-auto" name="button_reset_globals"><?php echo _('Use the Settings page values'); ?></button>
				<button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _('Cancel'); ?></button>
				<button type="button" class="btn btn-primary" name="button_update_globals"><?php echo _('Save'); ?></button>
			</div>
		</div>
	</div>
</div>
