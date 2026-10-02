<?php
	if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

	echo load_view(__DIR__ . '/epm_templates/subnav.php', array('current' => 'templates'));

	if (!epm_sql("SELECT id FROM endpointman_product_list WHERE id > 0 LIMIT 1", 'getOne')) {
		echo load_view(__DIR__ . '/epm_templates/no_brand.php');
		return;
	}

	$text = array(
		'confirm_delete' => _('Delete the template %s? The phones that use it change to no template.'),
		'enabled'        => _('Enabled'),
		'disabled'       => _('Disabled'),
		'edit'           => _('Edit'),
		'delete'         => _('Delete'),
		'saved'          => _('Saved.'),
		'deleted'        => _('The template is deleted.'),
		'error'          => _('The change was not saved.'),
		'name_required'  => _('Type a name for the template.'),
		'family_required'=> _('Select a family.'),
		'model_required' => _('Select a model.'),
	);
?>
<script>var epmTemplatesText = <?php echo json_encode($text); ?>;</script>
<?php
	echo load_view(__DIR__ . '/epm_templates/manager.views.grid.php', array('request' => $_REQUEST));
	echo load_view(__DIR__ . '/epm_templates/manager.views.new.modal.php', array('request' => $_REQUEST));
?>
<script type="text/javascript" src="modules/endpointman/assets/js/views/epm_templates.js"></script>
