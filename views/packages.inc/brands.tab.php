<?php if (!defined('FREEPBX_IS_AUTH')) die('No direct script access allowed'); ?>
<div class="epm-brands-toolbar d-flex flex-wrap align-items-center mb-2">
	<button type="button" class="btn btn-default mr-2" id="epm-check-updates"><i class="fa fa-refresh"></i> <?php echo _('Check for updates'); ?></button>
	<div class="form-check mr-auto">
		<input class="form-check-input" type="checkbox" id="epm-show-hidden">
		<label class="form-check-label" for="epm-show-hidden"><?php echo _('Show hidden brands, families and models'); ?></label>
	</div>
	<input type="search" class="form-control epm-model-search" id="epm-model-search" placeholder="<?php echo epm_esc(_('Find a model')); ?>" aria-label="<?php echo epm_esc(_('Find a model')); ?>">
</div>
<p class="text-muted"><?php echo _('Install the brands of your phones, and enable their models. Only enabled models show on the Phones page.'); ?></p>

<table class="table epm-brand-table">
	<thead>
		<tr>
			<th><?php echo _('Brand'); ?></th>
			<th><?php echo _('Status'); ?></th>
			<th><?php echo _('Installed version'); ?></th>
			<th><?php echo _('Available version'); ?></th>
			<th class="text-right"><?php echo _('Actions'); ?></th>
		</tr>
	</thead>
	<tbody id="epm-brand-list">
		<tr><td colspan="5" class="text-center text-muted"><i class="fa fa-spinner fa-pulse"></i> <?php echo _('Loading...'); ?></td></tr>
	</tbody>
</table>
