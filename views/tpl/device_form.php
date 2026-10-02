<?php
/**
 * The add form and the edit form of the Phones page.
 *
 * views/devices.php assigns the variables. The action bar of FreePBX submits
 * the form (DevicesPage::getActionBar). Each value prints through epm_esc().
 *
 * Add form:
 * @var array  $brand_ava   Brand <option> rows
 * @var array  $display_ext Free extension <option> rows
 * @var string $add_mac     The typed MAC address after a failed add
 * @var string $add_ipei    The typed IPEI after a failed add
 *
 * Edit form:
 * @var int    $edit_id           endpointman_mac_list.id
 * @var string $mac               MAC address
 * @var string $name              Brand name
 * @var array  $models_ava        Model <option> rows
 * @var array  $display_templates Template <option> rows
 * @var array  $line_list_edit    Lines, each with reg_list and line_list
 *
 * @var string $mode "ADD" or "EDIT"
 */
$edit = ($mode === 'EDIT');

$option_rows = function ($rows) {
	foreach ((is_array($rows) ? $rows : array()) as $row) {
		echo '<option value="' . epm_esc($row['value']) . '"' . (!empty($row['selected']) ? ' selected' : '') . '>' . epm_esc($row['text']) . '</option>';
	}
};

/**
 * Print one row of the form, in the layout of the FreePBX modules.
 *
 * @param string   $id      id of the control
 * @param string   $label   translated label
 * @param callable $control prints the control
 * @param string   $help    translated help text, or ''
 */
$row = function ($id, $label, $control, $help = '') {
?>
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="<?php echo $id; ?>"><?php echo $label; ?></label>
<?php if ($help !== '') { ?>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="<?php echo $id; ?>"></i>
<?php } ?>
						</div>
						<div class="col-md-9"><?php $control(); ?></div>
					</div>
				</div>
			</div>
		</div>
<?php if ($help !== '') { ?>
		<div class="row">
			<div class="col-md-12">
				<span id="<?php echo $id; ?>-help" class="help-block fpbx-help-block"><?php echo $help; ?></span>
			</div>
		</div>
<?php } ?>
	</div>
<?php
};

$base = 'config.php?display=endpointman&amp;view=phones';
?>
<?php if (isset($show_error_box)) { $this->render('message_box', $this->vars()); } ?>

<h2><?php echo $edit ? _('Edit phone') : _('Add phone'); ?></h2>

<form class="fpbx-submit" name="epm_phone" id="epm-phone-form" method="post" action="<?php echo $base; ?>"
<?php if ($edit) { ?>
	data-fpbx-delete="<?php echo $base; ?>&amp;sub_type=delete_device&amp;edit_id=<?php echo epm_esc($edit_id); ?>"
<?php } ?>>
<?php if ($edit) { ?>
	<input type="hidden" name="sub_type" value="edit">
	<input type="hidden" name="sub_type_sub" value="button_save">
	<input type="hidden" name="edit_id" value="<?php echo epm_esc($edit_id); ?>">

<?php
	$row('epm-mac', _('MAC address'), function () use ($mac) {
		echo '<p class="form-control-plaintext text-monospace" id="epm-mac">' . epm_esc($mac) . '</p>';
	});
	$row('epm-brand', _('Brand'), function () use ($name) {
		echo '<p class="form-control-plaintext" id="epm-brand">' . epm_esc($name) . '</p>';
	});
	$row('model_new', _('Model'), function () use ($models_ava, $option_rows) {
		echo '<select class="form-control" name="model_list" id="model_new">';
		$option_rows($models_ava);
		echo '</select>';
	});
	$row('template_list', _('Template'), function () use ($display_templates, $option_rows) {
		echo '<div class="input-group"><select class="form-control" name="template_list" id="template_list">';
		$option_rows($display_templates);
		echo '</select><div class="input-group-append">';
		echo '<button type="button" class="btn btn-default epm-sub-action" data-sub="button_edit">' . _('Edit template') . '</button>';
		echo '</div></div>';
	}, _('The template gives the settings of the phone. Custom keeps the settings of this phone only. Edit template opens the selected template in the template editor.'));
	$row('epm-specifics', _('Phone settings'), function () use ($edit_id) {
		echo '<button type="button" class="btn btn-default epm-specifics" id="epm-specifics" data-id="' . epm_esc($edit_id) . '">' . _('Open phone settings') . '</button>';
	}, _('Settings for this phone only, for example a static IP address. These settings override the template.'));
?>

	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<h3><?php echo _('Lines'); ?></h3>
				<table class="table table-sm epm-lines">
					<thead>
						<tr>
							<th><?php echo _('Line'); ?></th>
							<th><?php echo _('Extension'); ?></th>
							<th><?php echo _('IPEI (DECT handsets only)'); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
<?php foreach ((isset($line_list_edit) ? $line_list_edit : array()) as $line) { $luid = epm_esc($line['luid']); ?>
						<tr>
							<td>
								<select class="form-control form-control-sm" name="line_list_<?php echo $luid; ?>" aria-label="<?php echo epm_esc(_('Line')); ?>">
									<?php $option_rows($line['line_list']); ?>
								</select>
							</td>
							<td>
								<select class="form-control form-control-sm" name="ext_list_<?php echo $luid; ?>" aria-label="<?php echo epm_esc(_('Extension')); ?>">
									<?php $option_rows($line['reg_list']); ?>
								</select>
							</td>
							<td><input type="text" class="form-control form-control-sm" name="ipei_<?php echo $luid; ?>" value="<?php echo epm_esc($line['ipei']); ?>" maxlength="17" aria-label="IPEI"></td>
							<td>
<?php     if (count($line_list_edit) > 1) { ?>
								<button type="button" class="btn btn-link text-danger epm-sub-action" data-sub="delete" data-edit-id="<?php echo $luid; ?>" title="<?php echo epm_esc(_('Delete line')); ?>"><i class="fa fa-trash"></i></button>
<?php     } ?>
							</td>
						</tr>
<?php } ?>
					</tbody>
				</table>
				<button type="button" class="btn btn-default epm-sub-action" data-sub="add_line_x"><i class="fa fa-plus"></i> <?php echo _('Add line'); ?></button>
			</div>
		</div>
	</div>
<?php } else { ?>
	<input type="hidden" name="sub_type" value="add">

<?php
	$row('epm-mac', _('MAC address'), function () use ($add_mac) {
		echo '<input type="text" class="form-control" id="epm-mac" name="mac" value="' . epm_esc($add_mac) . '" required'
			. ' pattern="\s*([0-9A-Fa-f]{2}[:\-.]?){5}[0-9A-Fa-f]{2}\s*" placeholder="00:11:22:33:44:55"'
			. ' data-invalid="' . epm_esc(_('Type the MAC address of the phone, for example 001122334455.')) . '">';
	}, _('The MAC address is on a label on the phone. Colons and dashes are optional.'));
	$row('brand_edit', _('Brand'), function () use ($brand_ava, $option_rows) {
		echo '<select class="form-control" name="brand_list" id="brand_edit" required data-invalid="' . epm_esc(_('Select a brand.')) . '">';
		$option_rows($brand_ava);
		echo '</select>';
	}, _('The list shows the installed brands that have an enabled model.'));
	$row('model_new', _('Model'), function () {
		echo '<select class="form-control" name="model_list" id="model_new" required data-invalid="' . epm_esc(_('Select a model.')) . '"><option value=""></option></select>';
	});
	$row('line_list', _('Line'), function () {
		echo '<select class="form-control" name="line_list" id="line_list"><option value=""></option></select>';
	}, _('The line key of the phone that the extension uses. Line 1 is the default.'));
	$row('epm-ext', _('Extension'), function () use ($display_ext, $option_rows) {
		echo '<select class="form-control" name="ext_list" id="epm-ext" required data-invalid="' . epm_esc(_('Select an extension.')) . '">';
		if (empty($display_ext)) {
			echo '<option value="">' . _('No free extension. Add an extension in Applications > Extensions.') . '</option>';
		}
		$option_rows($display_ext);
		echo '</select>';
	}, _('The list shows the PJSIP extensions that are not on a phone.'));
	$row('template_list', _('Template'), function () {
		echo '<select class="form-control" name="template_list" id="template_list"><option value=""></option></select>';
	}, _('The template gives the settings of the phone. You can change the template later.'));
	$row('epm-ipei', _('IPEI'), function () use ($add_ipei) {
		echo '<input type="text" class="form-control" id="epm-ipei" name="ipei" value="' . epm_esc($add_ipei) . '" maxlength="17">';
	}, _('Only for DECT handsets. Keep it blank for other phones.'));
?>
<?php } ?>
</form>

<script type="text/javascript" src="modules/endpointman/assets/js/views/phones.js"></script>
