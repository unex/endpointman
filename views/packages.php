<?php if (!defined('FREEPBX_IS_AUTH')) die('No direct script access allowed'); ?>

<h3>Package Manager</h3>

<?php
$tabs = array(
	__DIR__ . '/packages.inc/manager.tab.php' => _("Manager"),
	__DIR__ . '/packages.inc/editor.tab.php' => _("Editor"),
);

echo load_view(__DIR__ . '/tabbed.php', array('tabs' => $tabs));

?>

<script type="text/javascript" src="modules/endpointman/assets/js/views/packages.js"></script>
