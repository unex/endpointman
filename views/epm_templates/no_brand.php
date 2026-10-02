<?php
/**
 * The notice for a system with no installed brand. The template pages need
 * the families of an installed brand.
 */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
?>
<div class="alert alert-info" role="alert">
	<?php echo _('No brand is installed.'); ?>
	<a href="config.php?display=endpointman&amp;view=brands" class="alert-link"><?php echo _('Install a brand on the Brands page.'); ?></a>
</div>
