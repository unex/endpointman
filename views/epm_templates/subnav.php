<?php
/**
 * The sub-menu of the Templates page: the template list, the config file
 * editor and the placeholder list. Each item is a link to its page.
 *
 * @var string $current templates, files or placeholders
 */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
$items = array(
	'templates'    => array(_('Templates'), 'config.php?display=endpointman&amp;view=templates'),
	'files'        => array(_('Config files'), 'config.php?display=endpointman&amp;view=templates&amp;subpage=files'),
	'placeholders' => array(_('Placeholders'), 'config.php?display=endpointman&amp;view=placeholders'),
);
$current = isset($current) ? $current : 'templates';
?>
<div class="btn-group mb-3 epm-subnav">
<?php foreach ($items as $key => $item) { ?>
	<a class="btn btn-default<?php echo ($key === $current) ? ' active' : ''; ?>" href="<?php echo $item[1]; ?>"><?php echo $item[0]; ?></a>
<?php } ?>
</div>
