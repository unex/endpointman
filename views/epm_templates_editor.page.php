<?php
	if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

	if (!epm_sql("SELECT id FROM endpointman_product_list WHERE id > 0 LIMIT 1", 'getOne')) {
		echo load_view(__DIR__ . '/epm_templates/no_brand.php');
		return;
	}
	if (!isset($_REQUEST['idsel']) || !isset($_REQUEST['custom'])) {
		echo '<div class="alert alert-warning" role="alert">' . _('Select a template on the Templates page.') . '</div>';
		return;
	}
	$dtemplate = FreePBX::Endpointman()->_get_page('templates')->edit_template_display($_REQUEST['idsel'], $_REQUEST['custom']);

	$text = array(
		'loading'  => _('Loading...'),
		'stock'    => _('%s (from the package)'),
		'versions' => _('Your versions'),
		'error'    => _('The change was not saved.'),
	);
?>
<script>var epmTemplatesText = <?php echo json_encode($text); ?>;</script>
<?php
	echo load_view(__DIR__ . '/epm_templates/editor.views.template.php', array('request' => $_REQUEST, 'dtemplate' => $dtemplate));
	echo load_view(__DIR__ . '/epm_templates/editor.views.dialog.cfg.global.php', array('request' => $_REQUEST));
?>
<script type="text/javascript" src="modules/endpointman/assets/js/views/epm_templates.js"></script>
