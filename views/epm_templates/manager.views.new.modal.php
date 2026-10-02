<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
$families = epm_sql("SELECT DISTINCT endpointman_product_list.* FROM endpointman_product_list, endpointman_model_list WHERE endpointman_product_list.id = endpointman_model_list.product_id AND endpointman_model_list.hidden = 0 AND endpointman_model_list.enabled = 1 AND endpointman_product_list.hidden != 1 AND endpointman_product_list.cfg_dir !=  ''", 'getAll');
?>
<div class="modal fade" id="AddDlgModal" tabindex="-1" role="dialog" aria-labelledby="AddDlgModal-title" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="AddDlgModal-title"><?php echo _('Add template'); ?></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="<?php echo epm_esc(_('Close')); ?>"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body">
				<div class="form-group">
					<label for="NewTemplateName"><?php echo _('Name'); ?></label>
					<input type="text" class="form-control" id="NewTemplateName" name="NewTemplateName" value="" maxlength="100">
				</div>
				<div class="form-group">
					<label for="NewProductSelect"><?php echo _('Family'); ?></label>
					<select class="form-control" name="NewProductSelect" id="NewProductSelect">
						<option value=""></option>
<?php foreach ($families as $row) { ?>
						<option value="<?php echo epm_esc($row['id']); ?>"><?php echo epm_esc($row['short_name']); ?></option>
<?php } ?>
					</select>
					<small class="form-text text-muted"><?php echo _('The list shows the families that have an enabled model.'); ?></small>
				</div>
				<div class="form-group">
					<label for="NewCloneModel"><?php echo _('Model'); ?></label>
					<select class="form-control" name="NewCloneModel" id="NewCloneModel"><option value=""></option></select>
					<small class="form-text text-muted"><?php echo _('The template starts with the default settings of this model.'); ?></small>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _('Cancel'); ?></button>
				<button type="button" class="btn btn-primary" name="button_save" id="AddDlgModal_bt_new"><?php echo _('Add'); ?></button>
			</div>
		</div>
	</div>
</div>
