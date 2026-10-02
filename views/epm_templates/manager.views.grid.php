<?php if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); } ?>
<p class="text-muted"><?php echo _('A template holds the settings for the phones of one family. Apply a template to many phones on the Phones page. A phone with no template has its own settings.'); ?></p>
<div id="epm-templates-toolbar">
	<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#AddDlgModal"><i class="fa fa-plus"></i> <?php echo _('Add template'); ?></button>
</div>
<table id="mygrid"
	data-url="ajax.php?module=endpointman&amp;view=templates&amp;command=list_current_template"
	data-cache="false"
	data-toolbar="#epm-templates-toolbar"
	data-toggle="table"
	data-pagination="true"
	data-search="true"
	data-sort-name="name"
	class="table table-striped">
	<thead>
		<tr>
			<th data-field="name" data-sortable="true" data-escape="true"><?php echo _('Name'); ?></th>
			<th data-field="model_class" data-sortable="true" data-escape="true"><?php echo _('Family'); ?></th>
			<th data-field="model_clone" data-sortable="true" data-escape="true"><?php echo _('Model'); ?></th>
			<th data-field="enabled" data-sortable="true" data-formatter="epm_templates_grid_FormatThEnabled"><?php echo _('Status'); ?></th>
			<th data-field="id" data-formatter="epm_templates_grid_FormatThAction" data-width="80"><?php echo _('Actions'); ?></th>
		</tr>
	</thead>
</table>
