/*
 * The script of the Settings page.
 *
 * Each field saves when it changes. The command is saveconfig on
 * SettingsPage, with the field name and the value. The server checks the
 * value and can refuse it with a message. views/settings.php defines
 * epmSettingsText.
 */
"use strict";

var epmSettings = (function () {
	var text = (typeof epmSettingsText === 'object') ? epmSettingsText : {};

	function save($field) {
		var name = $field.attr('name');
		var value = $field.val();
		$.post('ajax.php', { module: 'endpointman', view: 'settings', command: 'saveconfig', name: name, value: value }, null, 'json')
			.done(function (data) {
				if (data && data.status) {
					fpbxToast(text.saved, '', 'success');
					// The server can change the value, for example add a slash to
					// a directory or change http to https.
					if (typeof data.value === 'string' && $field.is('input[type=text]') && data.value !== value) {
						$field.val(data.value);
					}
					if (name === 'cfg_type' || name === 'srvip') { updateUrl(); }
				} else {
					fpbxToast((data && data.message) || text.error, '', 'error');
				}
			})
			.fail(function () { fpbxToast(text.error, '', 'error'); });
	}

	function updateUrl() {
		var type = $('#cfg_type').val();
		var web = (type === 'http' || type === 'https');
		$('#cfg_type_alert').toggleClass('d-none', !web);
		$('#cfg_type_url').text((type === 'https' ? 'https' : 'http') + '://' + $('#srvip').val() + '/provisioning/p.php/');
	}

	return {
		init: function () {
			var $form = $('#epm-settings');
			if (!$form.length) { return; }
			$form.on('change', 'input[type=text], select', function () { save($(this)); });
			$form.on('change', 'input[type=radio]', function () { if (this.checked) { save($(this)); } });
			$form.on('click', '[data-fill]', function () {
				var $target = $($(this).data('fill'));
				if ($target.val() !== String($(this).data('value'))) {
					$target.val($(this).data('value')).trigger('change');
				}
			});
		}
	};
})();

$(function () { epmSettings.init(); });
