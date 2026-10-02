<?php
/**
 * The notice that shows when the commercial Endpoint Manager is also active.
 *
 * The Phones and Brands pages include this file. It prints nothing when the
 * commercial module is not active, or when the admin dismissed the notice.
 * "Do not show again" sets disable_endpoint_warning, which the Settings page
 * also shows.
 */
global $active_modules;

if (empty($active_modules['endpoint']['rawname'])) {
	return;
}
if (FreePBX::Endpointman()->configmod->get('disable_endpoint_warning') === '1') {
	return;
}
?>
<div class="alert alert-warning epm-conflict" role="alert">
	<strong><?php echo _('The commercial Endpoint Manager is also active.'); ?></strong>
	<?php echo _('Do not use the two modules for the same brand or the same extension. Sangoma does not support a system that runs the two modules.'); ?>
	<a href="#" class="alert-link epm-conflict-dismiss"><?php echo _('Do not show again'); ?></a>
</div>
<script>
$(function () {
	$('.epm-conflict-dismiss').on('click', function (e) {
		e.preventDefault();
		$.post('ajax.php', { module: 'endpointman', view: 'settings', command: 'saveconfig', name: 'disable_endpoint_warning', value: 'yes' }, function () {
			$('.epm-conflict').remove();
		}, 'json');
	});
});
</script>
