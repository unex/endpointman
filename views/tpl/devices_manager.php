<?php
/**
 * The phone list of the Phones page.
 *
 * views/devices.php assigns each variable below, except the four message box
 * variables (DevicesPage::display_message_box).
 *
 * @var array  $list         Phones, each with a 'line' list
 * @var array  $brand_ava    Brand <option> rows
 * @var array  $display_ext  Free extension <option> rows
 * @var array  $unmanaged    Phones that the network search found, or null
 * @var int    $searched     1 after a network search
 * @var array  $product_list Family <option> rows
 * @var array  $model_list   Model <option> rows
 * @var bool   $no_add       TRUE when phones cannot be added
 * @var string $netmask      The last search network
 *
 * Each value that this page prints goes through epm_esc(): MAC and IPEI,
 * brand, model and template names, extension numbers and their FreePBX
 * descriptions, the network, the addresses of found phones, and each
 * <option> value and label. An admin types the extension description in
 * Applications > Extensions, so it is the first field where a hostile value
 * can arrive.
 *
 * Each id in a data- attribute is an integer: endpointman_mac_list.id, or the
 * index of a found phone in views/devices.php.
 */
$option_rows = function ($rows) {
	foreach ($rows as $row) {
		echo '<option value="' . epm_esc($row['value']) . '"' . (!empty($row['selected']) ? ' selected' : '') . '>' . epm_esc($row['text']) . '</option>';
	}
};
$text = array(
	'confirm_delete_one'  => _('Delete this phone?'),
	'confirm_delete_many' => _('Delete the selected phones?'),
	'none_selected'       => _('Select one or more phones.'),
	'searching'           => _('Searching...'),
	'importing'           => _('Importing...'),
	'import_failed'       => _('The import failed.'),
);
?>
<script>var epmPhonesText = <?php echo json_encode($text); ?>;</script>
<?php include(dirname(__DIR__, 2) . '/page.epm_warning.php'); ?>
<?php if (isset($show_error_box)) { $this->render('message_box', $this->vars()); } ?>

<?php if ($searched == 1 && is_array($unmanaged)) { ?>
<div class="card epm-found mb-3">
	<div class="card-header"><?php echo _('New phones on the network'); ?></div>
	<div class="card-body">
		<form method="post" action="config.php?display=endpointman&amp;view=phones">
			<input type="hidden" name="sub_type" value="add_selected_phones">
			<table class="table table-sm">
				<thead>
					<tr>
						<th><?php echo _('Add'); ?></th>
						<th><?php echo _('MAC address'); ?></th>
						<th><?php echo _('IP address'); ?></th>
						<th><?php echo _('Brand'); ?></th>
						<th><?php echo _('Model'); ?></th>
						<th><?php echo _('Extension'); ?></th>
					</tr>
				</thead>
				<tbody>
<?php foreach ($unmanaged as $device) { $n = epm_esc($device['id']); ?>
					<tr>
						<td>
							<input type="checkbox" name="add[]" value="<?php echo $n; ?>" checked>
							<input type="hidden" name="mac_<?php echo $n; ?>" value="<?php echo epm_esc($device['mac_strip']); ?>">
							<input type="hidden" name="brand_<?php echo $n; ?>" value="<?php echo epm_esc($device['brand_id']); ?>">
						</td>
						<td><?php echo epm_esc($device['mac_strip']); ?></td>
						<td><?php echo epm_esc($device['ip']); ?></td>
						<td><?php echo epm_esc($device['brand']); ?></td>
						<td>
							<select class="form-control form-control-sm" name="model_list_<?php echo $n; ?>">
<?php     foreach ($device['list'] as $row) { ?>
								<option value="<?php echo epm_esc($row['id']); ?>"><?php echo epm_esc($row['model']); ?></option>
<?php     } ?>
							</select>
						</td>
						<td>
							<select class="form-control form-control-sm" name="ext_list_<?php echo $n; ?>">
								<?php $option_rows($display_ext); ?>
							</select>
						</td>
					</tr>
<?php } ?>
				</tbody>
			</table>
			<div class="form-check form-check-inline">
				<input class="form-check-input" type="checkbox" name="reboot" id="epm-found-reboot">
				<label class="form-check-label" for="epm-found-reboot"><?php echo _('Restart the phones after they are added'); ?></label>
			</div>
			<button type="submit" class="btn btn-primary"><?php echo _('Add selected phones'); ?></button>
		</form>
	</div>
</div>
<?php } ?>

<div id="epm-phones-toolbar">
<?php if (!$no_add) { ?>
	<a href="config.php?display=endpointman&amp;view=phones&amp;form=add" class="btn btn-primary"><i class="fa fa-plus"></i> <?php echo _('Add phone'); ?></a>
	<button type="button" class="btn btn-default" data-toggle="modal" data-target="#epm-find"><i class="fa fa-search"></i> <?php echo _('Find phones'); ?></button>
<?php } ?>
	<div class="btn-group">
		<button type="button" class="btn btn-default dropdown-toggle epm-needs-selection" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" disabled>
			<?php echo _('Selected phones'); ?> <span class="epm-selection-count"></span>
		</button>
		<div class="dropdown-menu">
			<a class="dropdown-item" href="#" data-bulk="rebuild_selected_phones"><?php echo _('Write configs'); ?></a>
			<a class="dropdown-item" href="#" data-bulk="rebuild_selected_phones" data-reboot="1"><?php echo _('Write configs and restart'); ?></a>
			<a class="dropdown-item" href="#" data-toggle="modal" data-target="#epm-change-model"><?php echo _('Change model...'); ?></a>
			<div class="dropdown-divider"></div>
			<a class="dropdown-item text-danger" href="#" data-bulk="delete_selected_phones" data-confirm="many"><?php echo _('Delete'); ?></a>
		</div>
	</div>
	<div class="btn-group">
		<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
			<?php echo _('All phones'); ?>
		</button>
		<div class="dropdown-menu">
			<a class="dropdown-item" href="#" data-all="rebuild_configs_for_all_phones"><?php echo _('Write configs'); ?></a>
			<a class="dropdown-item" href="#" data-all="rebuild_configs_for_all_phones" data-reboot="1"><?php echo _('Write configs and restart'); ?></a>
			<a class="dropdown-item" href="#" data-toggle="modal" data-target="#epm-reboot-brand"><?php echo _('Restart the phones of a brand...'); ?></a>
			<div class="dropdown-divider"></div>
			<a class="dropdown-item" href="#" data-toggle="modal" data-target="#epm-apply-family"><?php echo _('Apply a template to a family...'); ?></a>
			<a class="dropdown-item" href="#" data-toggle="modal" data-target="#epm-apply-model"><?php echo _('Apply a template to a model...'); ?></a>
			<div class="dropdown-divider"></div>
			<a class="dropdown-item" href="config.php?display=endpointman&amp;view=settings&amp;command=export"><?php echo _('Export to CSV'); ?></a>
			<a class="dropdown-item" href="#" data-toggle="modal" data-target="#epm-import"><?php echo _('Import from CSV...'); ?></a>
		</div>
	</div>
</div>

<table id="epm-phones"
	data-toggle="table"
	data-toolbar="#epm-phones-toolbar"
	data-search="true"
	data-pagination="true"
	data-page-size="25"
	data-page-list="[25, 50, 100, all]"
	data-show-columns="true"
	data-click-to-select="true"
	data-maintain-meta-data="true"
	data-sort-name="ext"
	class="table table-striped">
	<thead>
		<tr>
			<th data-field="state" data-checkbox="true"></th>
			<th data-field="id" data-visible="false" data-switchable="false"></th>
			<th data-field="status" data-width="40" data-switchable="false"></th>
			<th data-field="ext" data-sortable="true"><?php echo _('Extension'); ?></th>
			<th data-field="mac" data-sortable="true"><?php echo _('MAC address'); ?></th>
			<th data-field="brand" data-sortable="true"><?php echo _('Brand'); ?></th>
			<th data-field="model" data-sortable="true"><?php echo _('Model'); ?></th>
			<th data-field="line" data-sortable="true"><?php echo _('Line'); ?></th>
			<th data-field="ipei" data-sortable="true" data-visible="false"><?php echo _('IPEI'); ?></th>
			<th data-field="template" data-sortable="true"><?php echo _('Template'); ?></th>
			<th data-field="actions" data-switchable="false" data-width="80"><?php echo _('Actions'); ?></th>
		</tr>
	</thead>
	<tbody>
<?php foreach ($list as $device) { ?>
<?php     $lines = !empty($device['line']) ? $device['line'] : array(array('ext' => '', 'description' => '', 'line' => '', 'ipei' => '', 'registered' => FALSE, 'ip' => '', 'port' => '')); ?>
<?php     foreach ($lines as $line) { $id = epm_esc($device['id']); ?>
		<tr>
			<td></td>
			<td><?php echo $id; ?></td>
			<td>
<?php         if ($line['registered']) { ?>
				<i class="fa fa-circle text-success" title="<?php echo epm_esc(sprintf(_('Registered from %s'), $line['ip'] . ($line['port'] !== '' ? ':' . $line['port'] : ''))); ?>"></i>
<?php         } else { ?>
				<i class="fa fa-circle-o text-muted" title="<?php echo epm_esc(_('Not registered')); ?>"></i>
<?php         } ?>
			</td>
			<td><?php echo epm_esc($line['ext']); ?><?php if ($line['description'] !== '') { ?> <span class="text-muted"><?php echo epm_esc($line['description']); ?></span><?php } ?></td>
			<td class="text-monospace"><?php echo epm_esc($device['mac']); ?></td>
			<td><?php echo epm_esc($device['name']); ?></td>
			<td><?php echo epm_esc($device['model']); ?><?php if ($device['model_disabled']) { ?> <span class="badge badge-secondary"><?php echo _('disabled'); ?></span><?php } ?></td>
			<td><?php echo epm_esc($line['line']); ?></td>
			<td><?php echo epm_esc($line['ipei']); ?></td>
			<td><?php echo epm_esc($device['template_name']); ?></td>
			<td>
				<a href="config.php?display=endpointman&amp;view=phones&amp;sub_type=edit&amp;edit_id=<?php echo $id; ?>" title="<?php echo epm_esc(_('Edit')); ?>"><i class="fa fa-edit"></i></a>
				&nbsp;
				<a href="#" class="epm-delete-phone" data-id="<?php echo $id; ?>" title="<?php echo epm_esc(_('Delete')); ?>"><i class="fa fa-trash"></i></a>
			</td>
		</tr>
<?php     } ?>
<?php } ?>
	</tbody>
</table>

<form id="epm-bulk" method="post" action="config.php?display=endpointman&amp;view=phones">
	<input type="hidden" name="sub_type" value="">
</form>

<?php
/**
 * Print a modal dialog with a form that posts one sub_type to this page.
 *
 * @param string $id       element id of the dialog
 * @param string $title    dialog title, already translated
 * @param string $sub_type the action
 * @param string $body     form fields, already escaped HTML
 * @param string $submit   label of the submit button, already translated
 * @param bool   $selected TRUE to send the selected phones with the form
 */
$modal = function ($id, $title, $sub_type, $body, $submit, $selected = FALSE) {
?>
<div class="modal fade" id="<?php echo $id; ?>" tabindex="-1" role="dialog" aria-labelledby="<?php echo $id; ?>-title" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form method="post" action="config.php?display=endpointman&amp;view=phones"<?php echo $selected ? ' class="epm-with-selection"' : ''; ?>>
				<input type="hidden" name="sub_type" value="<?php echo $sub_type; ?>">
				<div class="modal-header">
					<h5 class="modal-title" id="<?php echo $id; ?>-title"><?php echo $title; ?></h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="<?php echo epm_esc(_('Close')); ?>"><span aria-hidden="true">&times;</span></button>
				</div>
				<div class="modal-body"><?php echo $body; ?></div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _('Cancel'); ?></button>
					<button type="submit" class="btn btn-primary"><?php echo $submit; ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
<?php
};

$options = function ($rows, $blank = '') use ($option_rows) {
	ob_start();
	if ($blank !== FALSE) {
		echo '<option value="">' . $blank . '</option>';
	}
	$option_rows($rows);
	return ob_get_clean();
};

$field = function ($id, $label, $control, $help = '') {
	return '<div class="form-group"><label for="' . $id . '">' . $label . '</label>' . $control
		. ($help !== '' ? '<small class="form-text text-muted">' . $help . '</small>' : '') . '</div>';
};

$reboot_box = function ($id) {
	return '<div class="form-check"><input class="form-check-input" type="checkbox" name="reboot" id="' . $id . '">'
		. '<label class="form-check-label" for="' . $id . '">' . _('Restart the phones') . '</label></div>';
};

// The brand list starts with a blank row. Remove it, because $options adds one.
$brands = array_values(array_filter($brand_ava, function ($row) { return $row['value'] !== ''; }));

$modal('epm-find', _('Find phones'), 'go',
	$field('epm-netmask', _('Network'), '<input type="text" class="form-control" id="epm-netmask" name="netmask" value="' . epm_esc($netmask) . '" required>', _('The search finds phones of the installed brands, for example in 192.168.1.0/24.'))
	. '<div class="form-check"><input class="form-check-input" type="checkbox" name="nmap" value="1" id="epm-nmap" checked>'
	. '<label class="form-check-label" for="epm-nmap">' . _('Use nmap') . '</label>'
	. '<small class="form-text text-muted">' . _('nmap finds more phones. Without nmap, the search uses the neighbor table of this server.') . '</small></div>',
	_('Search'));

$modal('epm-change-model', _('Change model'), 'change_brand',
	$field('brand_list_selected', _('Brand'), '<select class="form-control" id="brand_list_selected" name="brand_list_selected" required>' . $options($brands) . '</select>')
	. $field('model_list_selected', _('Model'), '<select class="form-control" id="model_list_selected" name="model_list_selected" required><option value=""></option></select>',
		_('The custom settings of the phones are removed, because a different model has different settings.'))
	. $reboot_box('epm-change-reboot'),
	_('Change model'), TRUE);

$modal('epm-reboot-brand', _('Restart the phones of a brand'), 'reboot_brand',
	$field('epm-rb-brand', _('Brand'), '<select class="form-control" id="epm-rb-brand" name="rb_brand" required>' . $options($brands) . '</select>'),
	_('Restart'));

$modal('epm-apply-family', _('Apply a template to a family'), 'rebuild_reboot',
	$field('product_select', _('Family'), '<select class="form-control" id="product_select" name="product_select" required>' . $options($product_list) . '</select>')
	. $field('template_selector', _('Template'), '<select class="form-control" id="template_selector" name="template_selector" required><option value=""></option></select>')
	. $reboot_box('epm-family-reboot'),
	_('Apply'));

$modal('epm-apply-model', _('Apply a template to a model'), 'mrebuild_reboot',
	$field('model_select', _('Model'), '<select class="form-control" id="model_select" name="model_select" required>' . $options($model_list) . '</select>')
	. $field('model_template_selector', _('Template'), '<select class="form-control" id="model_template_selector" name="model_template_selector" required><option value=""></option></select>')
	. $reboot_box('epm-model-reboot'),
	_('Apply'));
?>

<div class="modal fade" id="epm-import" tabindex="-1" role="dialog" aria-labelledby="epm-import-title" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<form id="epm-import-form" enctype="multipart/form-data">
				<div class="modal-header">
					<h5 class="modal-title" id="epm-import-title"><?php echo _('Import from CSV'); ?></h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="<?php echo epm_esc(_('Close')); ?>"><span aria-hidden="true">&times;</span></button>
				</div>
				<div class="modal-body">
					<div class="form-group">
						<label for="epm-import-file"><?php echo _('CSV file'); ?></label>
						<input type="file" class="form-control-file" id="epm-import-file" name="files[]" accept=".csv,.txt" required>
					</div>
					<p><?php echo _('Each row is one line of a phone:'); ?></p>
					<pre class="epm-csv-format">MAC,Brand,Model,Extension,Line,IPEI
001122334455,Polycom,VVX 310,1001,1,
112233445566,Cisco/Linksys,7945,1002,,</pre>
					<p class="text-muted"><?php echo _('The MAC address is necessary. Line is 1 when it is blank. IPEI is only for DECT handsets. The export from this page has the same columns.'); ?></p>
					<div class="epm-import-result"></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _('Close'); ?></button>
					<button type="submit" class="btn btn-primary"><?php echo _('Import'); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<script type="text/javascript" src="modules/endpointman/assets/js/views/phones.js"></script>
