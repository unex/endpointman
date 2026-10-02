<?php
if (!defined('FREEPBX_IS_AUTH')) die('No direct script access allowed');
$brands = epm_sql('SELECT id, name FROM endpointman_brand_list WHERE id > 0 ORDER BY name ASC', 'getAll');
?>
<p class="text-muted"><?php echo _('A MAC prefix (OUI) is the first 6 characters of a MAC address. It tells the brand of a phone that Find phones discovers. The brand packages give the built-in prefixes. Add a custom prefix for a phone that Find phones does not know.'); ?></p>
<div id="epm-oui-toolbar">
	<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#epm-oui-add"><i class="fa fa-plus"></i> <?php echo _('Add MAC prefix'); ?></button>
</div>
<table id="epm-oui"
	data-url="ajax.php?module=endpointman&amp;view=settings&amp;command=oui"
	data-cache="false"
	data-toolbar="#epm-oui-toolbar"
	data-toggle="table"
	data-pagination="true"
	data-page-size="25"
	data-search="true"
	data-sort-name="oui"
	class="table table-striped">
	<thead>
		<tr>
			<th data-field="oui" data-sortable="true" data-formatter="epmBrands.ouiFormatter"><?php echo _('MAC prefix'); ?></th>
			<th data-field="brand" data-sortable="true" data-escape="true"><?php echo _('Brand'); ?></th>
			<th data-field="custom" data-sortable="true" data-formatter="epmBrands.ouiTypeFormatter"><?php echo _('Source'); ?></th>
			<th data-field="id" data-formatter="epmBrands.ouiActionFormatter" data-width="80"><?php echo _('Actions'); ?></th>
		</tr>
	</thead>
</table>

<div class="modal fade" id="epm-oui-add" tabindex="-1" role="dialog" aria-labelledby="epm-oui-add-title" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form id="epm-oui-form">
				<div class="modal-header">
					<h5 class="modal-title" id="epm-oui-add-title"><?php echo _('Add MAC prefix'); ?></h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="<?php echo epm_esc(_('Close')); ?>"><span aria-hidden="true">&times;</span></button>
				</div>
				<div class="modal-body">
					<div class="form-group">
						<label for="number_new_oui"><?php echo _('MAC prefix'); ?></label>
						<input type="text" class="form-control text-monospace" id="number_new_oui" name="number_new_oui" maxlength="8" required pattern="\s*([0-9A-Fa-f]{2}[:\-]?){2}[0-9A-Fa-f]{2}\s*" placeholder="0004F2">
					</div>
					<div class="form-group">
						<label for="brand_new_oui"><?php echo _('Brand'); ?></label>
						<select class="form-control" id="brand_new_oui" name="brand_new_oui" required>
							<option value=""></option>
<?php foreach ($brands as $row) { ?>
							<option value="<?php echo epm_esc($row['id']); ?>"><?php echo epm_esc($row['name']); ?></option>
<?php } ?>
						</select>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _('Cancel'); ?></button>
					<button type="submit" class="btn btn-primary"><?php echo _('Add'); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
