<?php
/**
 * The menu of the module. It shows as tabs under the title.
 *
 * Each tab is a link to its page, not a Bootstrap tab. The <ul> has no
 * role="tablist", because the tab hook in assets/js/endpointman.js binds to
 * that role.
 */
$list = array(
	'phones'    => _('Phones'),
	'brands'    => _('Brands'),
	'templates' => _('Templates'),
	'settings'  => _('Settings'),
	'about'     => _('About'),
);

// ?view= can have any value, so menuItem() reads it. $current is always one of
// the keys above.
$current = FreePBX::Endpointman()->menuItem(isset($_REQUEST['view']) ? $_REQUEST['view'] : '');
?>
<ul class="nav nav-tabs epm-menu">
<?php foreach ($list as $view => $name) { ?>
	<li class="nav-item"><a class="nav-link<?php echo ($current === $view) ? ' active' : ''; ?>" href="config.php?display=endpointman&amp;view=<?php echo $view; ?>"><?php echo $name; ?></a></li>
<?php } ?>
</ul>
