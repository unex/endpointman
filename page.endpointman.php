<?php
// global $active_modules;

// if (!empty($active_modules['endpoint']['rawname'])) {
// 	if (FreePBX::Endpointman()->configmod->get("disable_endpoint_warning") !== "1") {
// 		include('page.epm_warning.php');
// 	}
// }
?>
<!--
 * Endpoint Manager Master Page File
 *
 * @author Andrew Nagy
 * @license MPL / GPLv2 / LGPL
 * @package Endpoint Manager
-->

<div class="container-fluid">
	<h1>OSS Endpoint Manager</h1>
	<div class="display full-border">
		<div class="row">
			<div class="col-sm-9">
                <div class="fpbx-container">
                    <div class="display full-border">
                        <?php echo FreePBX::create()->Endpointman->showPage(); ?>
                    </div>
                </div>
            </div>
            <div class="col-sm-3 bootnav">
				<div class="list-group">
                    <?php echo load_view(__DIR__ . '/views/bootnav.php'); ?>
                </div>
            </div>
        </div>
    </div>
</div>
