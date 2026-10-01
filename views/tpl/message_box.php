<?php
/**
 * The message box of the devices page and the two popups.
 *
 * DevicesPage::display_message_box() assigns four variables: show_error_box,
 * advanced_debug, fatal_error and error_message. prepare_message_box()
 * collects the messages, for example "Invalid Netmask", "No Devices Found",
 * "neither ss nor netstat is installed" and "neither ip nor arp is
 * installed". This view prints them.
 *
 * The markup is Bootstrap, as on the other pages of the module
 * (views/epm_templates_manager.page.php and others). The popups load no
 * Bootstrap, so there the box shows as plain text, which is sufficient.
 * $error_message is already HTML. prepare_message_box() joins the strings
 * with <br />, and some of them contain links.
 *
 * @var int    $show_error_box Each include site checks it, this view does not
 * @var int    $fatal_error    1 for the error list, 0 for the message list
 * @var int    $advanced_debug Debug mode, which appends " Function: [key]"
 * @var string $error_message  The joined messages
 *
 * $error_message is the one field in these views of kind 3 under the rule in
 * lib/esc.inc.php: HTML that other code builds. This view cannot escape it.
 * prepare_message_box() joins the strings with <br />, and some contain <i>
 * emphasis and links. An escape of the join prints all of that as text.
 *
 * This is safe only if the joined strings contain only markup that the
 * module wrote. Thus each site that puts data into a message escapes that
 * data where it adds it. Examples are the FreePBX extension description, the
 * brand name, config and file paths, the config_location setting and
 * exception messages. Only that site can tell the data from the markup.
 */

if (empty($error_message)) {
	return;
}
?>
<div class="alert <?php echo empty($fatal_error) ? 'alert-info' : 'alert-danger'; ?>" role="alert">
	<?php echo $error_message; ?>
</div>
