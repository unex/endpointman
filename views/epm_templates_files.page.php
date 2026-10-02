<?php
/**
 * The config file editor of the Templates page.
 *
 * The admin selects a family and then a file. There are three kinds:
 * - Package files come from the brand package. Save changes the file, and a
 *   brand update can overwrite it.
 * - Template files are the templates of the family in the package. They are
 *   read only.
 * - Your versions are in the database. A brand update does not change them,
 *   and a template can use them in place of the package file.
 *
 * The page script sends its commands to view=settings (poce_*).
 */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

echo load_view(__DIR__ . '/epm_templates/subnav.php', array('current' => 'files'));

if (!epm_sql("SELECT id FROM endpointman_product_list WHERE id > 0 LIMIT 1", 'getOne')) {
	echo load_view(__DIR__ . '/epm_templates/no_brand.php');
	return;
}

$text = array(
	'none'           => _('None'),
	'no_families'    => _('No family is installed.'),
	'select_file'    => _('Select a file.'),
	'loading'        => _('Loading...'),
	'error'          => _('The request failed.'),
	'confirm_save'   => _('Save the changes to the package file? A brand update can overwrite this file.'),
	'confirm_delete' => _('Delete your version of this file?'),
	'name_required'  => _('Type a name for your version.'),
	'read_only'      => _('This file is read only. Use Save as to make your version.'),
);
?>
<script>var epmFilesText = <?php echo json_encode($text); ?>;</script>
<p class="text-muted"><?php echo _('Change the config files of a family. A brand update can overwrite a package file. Save as makes your version, which a brand update does not change. A template can use your version in place of the package file.'); ?></p>

<div class="row">
	<div class="col-md-3">
		<div class="list-group epm-files-families" id="epm-files-families">
			<span class="list-group-item text-muted"><?php echo _('Loading...'); ?></span>
		</div>
	</div>
	<div class="col-md-9">
		<div class="d-flex flex-wrap mb-2 epm-files-pickers">
<?php foreach (array('file' => _('Package files'), 'tfile' => _('Template files'), 'sql' => _('Your versions')) as $kind => $label) { ?>
			<div class="btn-group mr-2 mb-1">
				<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" data-kind="<?php echo $kind; ?>" disabled>
					<?php echo $label; ?> <span class="badge badge-light epm-count">0</span>
				</button>
				<div class="dropdown-menu epm-file-menu" data-kind="<?php echo $kind; ?>"></div>
			</div>
<?php } ?>
		</div>
		<p class="mb-1"><strong id="epm-file-family"></strong> <span class="text-monospace" id="epm-file-path"><?php echo _('Select a family.'); ?></span></p>
		<textarea name="config_textarea" id="config_textarea" rows="5" disabled></textarea>
		<div class="d-flex flex-wrap align-items-center mt-2 epm-files-actions">
			<button type="button" class="btn btn-primary mr-2 mb-1" id="epm-file-save" disabled><?php echo _('Save'); ?></button>
			<button type="button" class="btn btn-default mr-3 mb-1" id="epm-file-delete" disabled><?php echo _('Delete'); ?></button>
			<div class="input-group mr-3 mb-1 epm-save-as">
				<input type="text" class="form-control" id="epm-save-as-name" placeholder="<?php echo epm_esc(_('Name of your version')); ?>" aria-label="<?php echo epm_esc(_('Name of your version')); ?>" disabled>
				<div class="input-group-append"><button type="button" class="btn btn-default" id="epm-file-save-as" disabled><?php echo _('Save as'); ?></button></div>
			</div>
			<button type="button" class="btn btn-default mb-1 ml-auto" id="epm-file-full" disabled><i class="fa fa-arrows-alt"></i> <?php echo _('Full screen'); ?></button>
		</div>
		<small class="form-text text-muted"><?php echo _('F11 opens the full screen editor. Esc closes it.'); ?></small>
	</div>
</div>

<script type="text/javascript" src="modules/endpointman/assets/js/views/files.js"></script>
