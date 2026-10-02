<?php
if (!defined('FREEPBX_IS_AUTH')) die('No direct script access allowed');
$installed = FreePBX::Endpointman()->brands_available('', false);
?>
<h3><?php echo _('Upload a package'); ?></h3>
<p class="text-muted"><?php echo _('Upload a brand package when this server cannot reach the package server. The package must be a .tgz file with the name of the brand directory, for example polycom.tgz. The module refuses a package that contains a program or a script.'); ?></p>
<form id="epm-upload-form" class="d-flex flex-wrap align-items-center mb-4" enctype="multipart/form-data">
	<input type="file" class="form-control-file w-auto mr-2" id="epm-upload-file" name="files[]" accept=".tgz" aria-label="<?php echo epm_esc(_('Package file')); ?>">
	<button type="submit" class="btn btn-default"><i class="fa fa-upload"></i> <?php echo _('Upload'); ?></button>
</form>

<h3><?php echo _('Export a brand'); ?></h3>
<p class="text-muted"><?php echo _('Make a package of an installed brand, with your changes to its files. You can upload the package to a different server.'); ?> <a href="https://github.com/unex/provisioner" target="_blank" rel="noopener noreferrer"><?php echo _('The provisioner project tells how to make a package.'); ?></a></p>
<form id="epm-export-form" class="d-flex flex-wrap align-items-center mb-4">
	<select class="form-control w-auto epm-wide-select mr-2" id="epm-export-brand" aria-label="<?php echo epm_esc(_('Brand')); ?>">
		<option value=""></option>
<?php foreach ((is_array($installed) ? $installed : array()) as $row) { ?>
		<option value="<?php echo epm_esc($row['value']); ?>"><?php echo epm_esc($row['text']); ?></option>
<?php } ?>
	</select>
	<button type="submit" class="btn btn-default"><i class="fa fa-download"></i> <?php echo _('Export'); ?></button>
</form>

<h4><?php echo _('Exported packages'); ?></h4>
<table class="table table-sm">
	<thead>
		<tr>
			<th><?php echo _('File'); ?></th>
			<th><?php echo _('Brand'); ?></th>
			<th><?php echo _('Date'); ?></th>
		</tr>
	</thead>
	<tbody id="epm-export-list">
		<tr><td colspan="3" class="text-muted"><?php echo _('Loading...'); ?></td></tr>
	</tbody>
</table>
