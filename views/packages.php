<?php
/**
 * The Brands page: the brand list, the MAC prefixes, and package upload and
 * export.
 *
 * The page scripts send their commands to the old view names: "packages"
 * for the brands and "settings" for the MAC prefixes and the packages.
 */
if (!defined('FREEPBX_IS_AUTH')) die('No direct script access allowed');

$text = array(
	'close'            => _('Close'),
	'running'          => _('Running. Do not close this page.'),
	'done'             => _('Finished.'),
	'failed'           => _('The task failed. The output above can show why.'),
	'check'            => _('Check for updates'),
	'install'          => _('Install'),
	'update'           => _('Update'),
	'remove'           => _('Remove'),
	'hide'             => _('Hide'),
	'show'             => _('Show'),
	'models'           => _('Models'),
	'install_fw'       => _('Install firmware'),
	'remove_fw'        => _('Remove firmware'),
	'enable_all'       => _('Enable all'),
	'disable_all'      => _('Disable all'),
	'not_installed'    => _('Not installed'),
	'installed'        => _('Installed'),
	'update_available' => _('Update available'),
	'hidden'           => _('Hidden'),
	'no_data'          => _('No brand data. Click Check for updates to get the brand list from the package server.'),
	'enabled_count'    => _('%1$s of %2$s models enabled'),
	'confirm_remove'   => _('Remove the brand %s? The phones of this brand keep their configs, but you cannot change them until you install the brand again.'),
	'confirm_remove_fw'=> _('Remove the firmware of %s from this server?'),
	'confirm_oui'      => _('Delete the MAC prefix %s?'),
	'saved'            => _('Saved.'),
	'error'            => _('The change was not saved.'),
	'title_install'    => _('Install %s'),
	'title_update'     => _('Update %s'),
	'title_remove'     => _('Remove %s'),
	'title_fw'         => _('Firmware of %s'),
	'title_upload'     => _('Upload package'),
	'title_export'     => _('Export %s'),
	'required'         => _('Built in'),
	'custom'           => _('Custom'),
	'no_exports'       => _('No exported packages.'),
	'select_file'      => _('Select a package file.'),
	'select_brand'     => _('Select a brand.'),
	'oui_invalid'      => _('A MAC prefix is 6 hexadecimal characters, for example 0004F2.'),
);

$tabs = array(
	'brands' => array(_('Brands'), __DIR__ . '/packages.inc/brands.tab.php'),
	'oui'    => array(_('MAC prefixes'), __DIR__ . '/packages.inc/oui.tab.php'),
	'files'  => array(_('Upload and export'), __DIR__ . '/packages.inc/files.tab.php'),
);
?>
<script>var epmBrandsText = <?php echo json_encode($text); ?>;</script>
<?php include(dirname(__DIR__) . '/page.epm_warning.php'); ?>

<div class="btn-group mb-3 epm-subnav" id="epm-brands-tabs" role="tablist">
<?php $first = TRUE; foreach ($tabs as $id => $tab) { ?>
	<a class="btn btn-default<?php echo $first ? ' active' : ''; ?>" id="epm-tab-<?php echo $id; ?>-link" data-toggle="tab" href="#epm-tab-<?php echo $id; ?>" role="tab" aria-controls="epm-tab-<?php echo $id; ?>" aria-selected="<?php echo $first ? 'true' : 'false'; ?>"><?php echo $tab[0]; ?></a>
<?php $first = FALSE; } ?>
</div>
<div class="tab-content">
<?php $first = TRUE; foreach ($tabs as $id => $tab) { ?>
	<div class="tab-pane fade<?php echo $first ? ' show active' : ''; ?>" id="epm-tab-<?php echo $id; ?>" role="tabpanel" aria-labelledby="epm-tab-<?php echo $id; ?>-link">
		<?php echo load_view($tab[1]); ?>
	</div>
<?php $first = FALSE; } ?>
</div>

<script type="text/javascript" src="modules/endpointman/assets/js/views/brands.js"></script>
