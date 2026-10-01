<?php
global $active_modules;

if (!empty($active_modules['endpoint']['rawname'])) {
	if (FreePBX::Endpointman()->configmod->get("disable_endpoint_warning") !== "1") {
		include(__DIR__ . '/../page.epm_warning.php');
	}
}
?>
<h2>Open Source Information</h2>

<p>OSS PBX End Point Manager is the PBX endpoint manager for FreePBX that the community maintains.<br>This fork is for FreePBX 17 on PHP 8.2 and Asterisk 21 or later.<br>The module (web UI) is at: <a href="https://github.com/unex/endpointman" class="external-link" rel="nofollow">https://github.com/unex/endpointman</a><br>The phone drivers and packages are at: <a href="https://github.com/unex/provisioner" class="external-link" rel="nofollow">https://github.com/unex/provisioner</a><br>Pull requests to both repositories are welcome.</p>

<div><span class="aui-icon aui-icon-small aui-iconfont-info confluence-information-macro-icon"> </span><div class="confluence-information-macro-body"><p>This module is not the commercial EPM. FreePBX and Sangoma Technologies Inc. do <strong>NOT</strong> support it. For an endpoint manager with commercial support, see the Commercial Endpoint Manager of <span>Sangoma Technologies Inc.</span></p></div></div>
