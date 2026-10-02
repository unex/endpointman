<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
/**
 * The frame of each page of the module: the title, the menu and the page.
 *
 * @license MPL / GPLv2 / LGPL
 * @package Endpoint Manager
 */
?>
<div class="container-fluid">
	<h1><?php echo _('OSS Endpoint Manager'); ?></h1>
	<?php echo load_view(__DIR__ . '/views/bootnav.php'); ?>
	<div class="display no-border">
		<div class="fpbx-container">
			<div class="display no-border epm-page">
				<?php echo FreePBX::create()->Endpointman->showPage(); ?>
			</div>
		</div>
	</div>
</div>
