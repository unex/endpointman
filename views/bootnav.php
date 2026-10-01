<?php
$list = array(
    'settings'	   => _('Settings'),
    'devices'      => _('Extension Mapping'),
    'packages'	   => _('Package Manager'),
    'templates'	   => _('Template Manager'),
    'placeholders' => _('Config File Placeholders')
);

// ?view= is absent on the landing page and can have any value on other
// pages, so read it with care. $view is always one of the keys above.
$current = isset($_REQUEST['view']) && is_string($_REQUEST['view']) ? $_REQUEST['view'] : '';

foreach ($list as $view => $name) {
    echo '<a class="list-group-item' . ($current === $view ? " active":"") . '" href="config.php?display=endpointman&view=' . $view . '">' . $name . '</a>' . PHP_EOL;
}
