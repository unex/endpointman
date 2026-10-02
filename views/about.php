<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

$info = FreePBX::Modules()->getInfo('endpointman');
$version = isset($info['endpointman']['version']) ? $info['endpointman']['version'] : '';
?>
<div class="epm-about">
	<p><?php echo _('OSS Endpoint Manager provisions desk phones from FreePBX. The community maintains this module.'); ?></p>
	<p><?php echo _('Sangoma and FreePBX do not support this module. For an endpoint manager with support, use the commercial Endpoint Manager of Sangoma.'); ?></p>

	<table class="table epm-about-table">
		<tbody>
			<tr>
				<th scope="row"><?php echo _('Version'); ?></th>
				<td><?php echo epm_esc($version); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo _('Requirements'); ?></th>
				<td><?php echo _('FreePBX 17, PHP 8.2, Asterisk 21 or later'); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo _('Module source'); ?></th>
				<td><a href="https://github.com/unex/endpointman" target="_blank" rel="noopener noreferrer">github.com/unex/endpointman</a></td>
			</tr>
			<tr>
				<th scope="row"><?php echo _('Phone data'); ?></th>
				<td><a href="https://github.com/unex/provisioner" target="_blank" rel="noopener noreferrer">github.com/unex/provisioner</a></td>
			</tr>
			<tr>
				<th scope="row"><?php echo _('Report a problem'); ?></th>
				<td><a href="https://github.com/unex/endpointman/issues" target="_blank" rel="noopener noreferrer">github.com/unex/endpointman/issues</a></td>
			</tr>
		</tbody>
	</table>

	<p><?php echo _('The phone drivers ship with the module. The module downloads only data files: templates, model lists and phone resources. It refuses a package that contains a program or a script.'); ?></p>
	<p><?php echo _('Pull requests to the two repositories are welcome.'); ?></p>
</div>
