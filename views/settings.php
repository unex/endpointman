<link href="modules/endpointman/assets/css/views/settings.css" rel="stylesheet" type="text/css">

<h3>Settings</h3>

<?php
/**
 * Endpoint Manager FreePBX File
 *
 * @author Javier Pastor
 * @license MPL / GPLv2 / LGPL
 * @package Endpoint Manager
 */

$tabs = array(
	__DIR__ . '/settings.inc/config.tab.php' => _("Config"),
	__DIR__ . '/settings.inc/oui_manager.tab.php' => _("OUI Manager"),
	__DIR__ . '/settings.inc/poce.tab.php' => _("Product Configuration Editor"),
	__DIR__ . '/settings.inc/iedl.tab.php' => _("Import/Export My Devices List"),
	__DIR__ . '/settings.inc/manual_upload.tab.php' => _("Package Import/Export"),
);

echo load_view(__DIR__ . '/tabbed.php', array('tabs' => $tabs));

?>

<script type="text/javascript" src="modules/endpointman/assets/js/views/settings.js"></script>
