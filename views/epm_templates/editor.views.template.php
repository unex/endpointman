<?php
/**
 * The template editor: the template data, the config files and the settings
 * of the template.
 *
 * Each value that this file prints is data and goes through epm_esc(): `key`,
 * `value`, `description` and `tooltip` of each field, the tab titles, the
 * template name, family and model, the config file names, and `max_chars`,
 * `rows` and `cols`. The last three are vendor data in attributes.
 *
 * The data-url attribute of the config file selects contains
 * $_REQUEST['idsel'] and $_REQUEST['custom']. Both are request values, so
 * they are escaped.
 *
 * @var array $request  $_REQUEST
 * @var array $dtemplate TemplatesPage::edit_template_display()
 */
	if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

$custom = ($request['custom'] != 0);

/**
 * Print one field of the settings. generate_gui_html() gives the fields.
 * A tooltip of the package becomes the help text of the field.
 */
$field = function (array $f) {
	$type = isset($f['type']) ? $f['type'] : '';
	$key = isset($f['key']) ? $f['key'] : '';
	$label = isset($f['description']) ? epm_esc($f['description']) : '';
	$help = isset($f['tooltip']) ? epm_esc($f['tooltip']) : '';
	$value = isset($f['value']) ? $f['value'] : '';

	switch ($type) {
		case 'input':
			echo epm_form_row($key, $label,
				'<input type="text" class="form-control" id="' . epm_esc($key) . '" name="' . epm_esc($key) . '" value="' . epm_esc($value) . '"'
				. (isset($f['max_chars']) ? ' maxlength="' . epm_esc($f['max_chars']) . '"' : '') . '>', $help);
			break;

		case 'textarea':
			echo epm_form_row($key, $label,
				'<textarea class="form-control" id="' . epm_esc($key) . '" name="' . epm_esc($key) . '" rows="' . epm_esc(isset($f['rows']) ? $f['rows'] : '2') . '">' . epm_esc($value) . '</textarea>', $help);
			break;

		case 'radio':
			$control = '<span class="radioset">';
			$z = 0;
			$first = $key;
			foreach ((isset($f['data']) ? $f['data'] : array()) as $opt) {
				$id = $opt['key'] . $z;
				if ($z == 0) {
					$first = $id;
				}
				$control .= '<input type="radio" id="' . epm_esc($id) . '" name="' . epm_esc($key) . '" value="' . epm_esc($opt['value']) . '"' . (array_key_exists('checked', $opt) ? ' checked' : '') . '>'
					. '<label for="' . epm_esc($id) . '">' . epm_esc($opt['description']) . '</label>';
				$z++;
			}
			echo epm_form_row($first, $label, $control . '</span>', $help);
			break;

		case 'list':
			$control = '<select class="form-control" id="' . epm_esc($key) . '" name="' . epm_esc($key) . '">';
			foreach ((isset($f['data']) ? $f['data'] : array()) as $opt) {
				$control .= '<option value="' . epm_esc($opt['value']) . '"' . (array_key_exists('selected', $opt) ? ' selected' : '') . '>' . epm_esc($opt['description']) . '</option>';
			}
			echo epm_form_row($key, $label, $control . '</select>', $help);
			break;

		case 'checkbox':
			echo epm_form_row($key, $label,
				'<input type="checkbox" id="' . epm_esc($key) . '" name="' . epm_esc($key) . '" value="' . epm_esc($value) . '"' . (!empty($f['checked']) ? ' checked' : '') . '>', $help);
			break;

		case 'group':
			echo '<h3 class="epm-field-group">' . $label . '</h3>';
			if ($help !== '') {
				echo '<p class="text-muted">' . $help . '</p>';
			}
			break;

		case 'header':
			echo '<h4 class="epm-field-header">' . $label . '</h4>';
			break;

		case 'break':
			echo '<div class="epm-field-break"></div>';
			break;
	}
};

$files = FreePBX::Endpointman()->_get_page('templates')->edit_template_display_files_list($_REQUEST['idsel'], $_REQUEST['custom']);
?>

<p><a href="<?php echo $custom ? 'config.php?display=endpointman&amp;view=phones' : 'config.php?display=endpointman&amp;view=templates'; ?>"><i class="fa fa-angle-left"></i> <?php echo $custom ? _('Phones') : _('Templates'); ?></a></p>
<h2>
<?php if ($custom) { ?>
	<?php echo sprintf(_('Settings of the phone with extension %s'), epm_esc($dtemplate['ext'])); ?>
<?php } else { ?>
	<?php echo sprintf(_('Template %s'), epm_esc($dtemplate['template_name'])); ?>
<?php } ?>
</h2>

<form action="config.php?display=endpointman&amp;view=templates" method="post" id="epm_template_edit_form" name="epm_template_edit_form" class="fpbx-submit">
	<?php if (isset($_REQUEST['silent_mode'])) { echo '<input name="silent_mode" id="silent_mode" type="hidden" value="1">'; } ?>
	<input name="id" id="id" type="hidden" value="<?php echo epm_esc($dtemplate['hidden_id']); ?>">
	<input name="custom" id="custom" type="hidden" value="<?php echo epm_esc($dtemplate['hidden_custom']); ?>">
	<input name="command" type="hidden" value="save_template">

	<div class="row">
		<div class="col-lg-6">
			<h3><?php echo _('Template'); ?></h3>
<?php
	echo epm_form_row('epm-family', _('Family'), '<p class="form-control-plaintext" id="epm-family">' . epm_esc($dtemplate['product']) . '</p>');
	if ($custom) {
		echo epm_form_row('epm-model', _('Model'), '<p class="form-control-plaintext" id="epm-model">' . epm_esc($dtemplate['model']) . '</p>');
	} else {
		$models = '<select class="form-control" name="model_list" id="model_list">';
		foreach ($dtemplate['models_ava'] as $row) {
			$models .= '<option value="' . epm_esc($row['value']) . '"' . (!empty($row['selected']) ? ' selected' : '') . '>' . epm_esc($row['text']) . '</option>';
		}
		echo epm_form_row('model_list', _('Model'), $models . '</select>',
			_('The template uses the settings of this model. The model must be in the family.'));

		$areas = '<select class="form-control" name="area_list" id="area_list"><option value=""></option>';
		foreach ($dtemplate['area_ava'] as $row) {
			$areas .= '<option value="' . epm_esc($row['value']) . '"' . (!empty($row['selected']) ? ' selected' : '') . '>' . epm_esc($row['text']) . '</option>';
		}
		echo epm_form_row('area_list', _('Lines on this page'), $areas . '</select>',
			_('The number of lines that this page shows settings for. It is not the number of lines that the phone supports.'));
	}
	echo epm_form_row('epm-globals', _('Server settings'),
		'<button type="button" class="btn btn-default" id="epm-globals" data-toggle="modal" data-target="#CfgGlobalTemplate">' . _('Change server settings') . '</button>',
		_('The server address, the provisioning type, the config directory, the time zone and the time server for the phones of this template. The values of the Settings page apply when you do not change them here.'));
?>
		</div>
		<div class="col-lg-6">
			<h3><?php echo _('Config files'); ?></h3>
			<p class="text-muted"><?php echo _('Select the version of each config file. Edit opens the file. A changed file is saved as a new version, and the brand updates do not overwrite it.'); ?></p>
			<table class="table table-sm epm-config-files">
				<tbody>
<?php foreach ($files as $row) { $ref = $row['id'] . '_' . $row['id_d'] . '_' . $row['id_p']; ?>
					<tr class="files_edit_configs" id="boxselect_<?php echo epm_esc($ref); ?>">
						<td class="text-monospace align-middle"><?php echo epm_esc($row['name']); ?></td>
						<td>
							<select class="form-control form-control-sm"
								data-url="ajax.php?module=endpointman&amp;view=templates&amp;command=list_files_edit&amp;idsel=<?php echo epm_esc($_REQUEST['idsel']); ?>&amp;custom=<?php echo epm_esc($_REQUEST['custom']); ?>&amp;namefile=<?php echo epm_esc($row['name']); ?>"
								data-id="id" data-label="name"
								name="config_files[<?php echo epm_esc($row['id']); ?>]"
								aria-label="<?php echo epm_esc(sprintf(_('Version of %s'), $row['name'])); ?>">
							</select>
						</td>
						<td class="text-right"><button type="button" class="btn btn-default btn-sm"><?php echo _('Edit'); ?></button></td>
					</tr>
<?php } ?>
				</tbody>
			</table>
		</div>
	</div>

	<details class="epm-variables mb-3">
		<summary><?php echo _('Variables that you can use in the fields'); ?></summary>
		<table class="table table-sm mt-2">
			<tbody>
				<tr><td class="text-monospace">{$username.line.1}</td><td><?php echo _('The user name of line 1, usually the extension'); ?></td></tr>
				<tr><td class="text-monospace">{$displayname.line.1}</td><td><?php echo _('The FreePBX description of line 1, usually the full name'); ?></td></tr>
				<tr><td class="text-monospace">{$server_host.line.1}</td><td><?php echo _('The server address of line 1'); ?></td></tr>
				<tr><td class="text-monospace">{$server_port.line.1}</td><td><?php echo _('The server port of line 1'); ?></td></tr>
				<tr><td class="text-monospace">{$mac}</td><td><?php echo _('The MAC address of the phone'); ?></td></tr>
				<tr><td class="text-monospace">{$model}</td><td><?php echo _('The model of the phone'); ?></td></tr>
			</tbody>
		</table>
		<a href="config.php?display=endpointman&amp;view=placeholders"><?php echo _('All placeholders'); ?></a>
	</details>

<?php
	/*
	 * generate_gui_html() returns a string, not the group array, when the
	 * family has no template data. The only string that it can return here is
	 * a literal in TemplatesPage.class.php, so print it as it is.
	 */
	$epm_tabs = $dtemplate['template_editor'];
	if (!is_array($epm_tabs)) {
		echo '<div class="alert alert-info">' . $epm_tabs . '</div>';
		$epm_tabs = array();
	}
	$n = 0;
?>
	<ul class="nav nav-tabs" role="tablist">
<?php foreach ($epm_tabs as $row) { $n++; ?>
		<li class="nav-item" role="presentation">
			<a class="nav-link<?php echo $n == 1 ? ' active' : ''; ?>" id="epm-tab-<?php echo $n; ?>-label" href="#epm-tab-<?php echo $n; ?>" data-toggle="tab" role="tab" aria-controls="epm-tab-<?php echo $n; ?>" aria-selected="<?php echo $n == 1 ? 'true' : 'false'; ?>"><?php
			/* Some groups have no title. The lineloop case of generate_gui_html()
			 * names one group and then increments its counter, so the next group
			 * has only `data`. A tab without a label cannot be clicked, so give
			 * it a number. */
			echo isset($row['title']) ? epm_esc($row['title']) : sprintf(_('Section %d'), $n);
			?></a>
		</li>
<?php } $n = 0; ?>
	</ul>
	<div class="tab-content">
<?php foreach ($epm_tabs as $row) { $n++; ?>
		<div class="tab-pane<?php echo $n == 1 ? ' active' : ''; ?>" id="epm-tab-<?php echo $n; ?>" role="tabpanel" aria-labelledby="epm-tab-<?php echo $n; ?>-label">
<?php     foreach ($row['data'] as $srow) { $field($srow); } ?>
		</div>
<?php } ?>
	</div>
</form>
