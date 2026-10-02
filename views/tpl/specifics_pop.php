<?php
/**
 * The phone settings popup. includes/popup.inc.php draws it for
 * pop_type=edit_specifics. The Phones page opens it from the edit form.
 *
 * These settings are for one phone, and they override its template.
 *
 * @var array  $html_array Sections from generate_gui_html()
 * @var string $value      Opaque token that the form sends back
 *
 * The section titles print through epm_esc(). _setting_fields escapes the
 * fields.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo _('Phone settings'); ?></title>
<link href="assets/css/bootstrap-4.6.1.min.css" rel="stylesheet" type="text/css">
<link href="assets/css/font-awesome.min-4.7.0.css" rel="stylesheet" type="text/css">
<style>body { padding: 1rem; } h2 { font-size: 1.25rem; }</style>
</head>
<body>
	<h1 class="h4"><?php echo _('Phone settings'); ?></h1>
	<p class="text-muted"><?php echo _('These settings apply to this phone only. They override the template of the phone.'); ?></p>
<?php if (isset($show_error_box)) { $this->render('message_box', $this->vars()); } ?>
	<form name="myform" id="myform" method="post" action="">
		<input type="hidden" id="value" name="value" value="<?php echo epm_esc(isset($value) ? $value : ''); ?>">
<?php
// generate_gui_html() does not give each section a title. Its lineloop case
// increments the group counter after it sets a title. The next group then
// has only 'data' until the outer loop names it, and the last group can have
// no name. Read both keys with a default.
foreach ((isset($html_array) && is_array($html_array) ? $html_array : array()) as $section) { ?>
		<h2 class="mt-3"><?php echo epm_esc(isset($section['title']) ? $section['title'] : ''); ?></h2>
<?php     $this->render('_setting_fields', array('fields' => isset($section['data']) ? $section['data'] : array())); ?>
<?php } ?>
		<div class="mt-3">
			<button type="submit" class="btn btn-primary" name="button_save" value="Save"><?php echo _('Save'); ?></button>
			<button type="button" class="btn btn-secondary" onclick="window.close();"><?php echo _('Close'); ?></button>
		</div>
	</form>
</body>
</html>
