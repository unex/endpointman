<?php
/**
 * The popup that edits the specific settings of one device.
 * includes/popup.inc.php draws it for pop_type=edit_specifics. The devices
 * list opens it.
 *
 * @var array  $html_array Sections from generate_gui_html()
 * @var string $value      Opaque token echoed back on submit
 *
 * `value` and the section titles print through epm_esc(). _setting_fields
 * escapes the rows (see lib/esc.inc.php for the rule). A section title is a
 * category name from the vendor template file or a literal of
 * generate_gui_html(). Both are data.
 *
 * The page loads no jQuery. FreePBX 17 has no jquery-1.7.1 or jquery-ui-1.8.9
 * under /admin/assets/js/. includes/popup.inc.php writes only when the request
 * has button_save. The Submit button has name="button_save", so the save
 * needs no script. Cancel is the only JavaScript on the page.
 */
?>
<html>
<head>
<title>PBX Endpoint Configuration Manager</title>
<link href="assets/endpointman/css/main.css" rel="stylesheet" type="text/css">
<link href="assets/css/font-awesome.min-4.7.0.css" rel="stylesheet" type="text/css" />
<script language="javascript">
function Close() {
	self.close();
}
</script>
</head>
<body name="specific_pop">
<div id="page_body">
	<h1><face="Arial"><center><?php echo _('End Point Configuration Manager')?></center></h1>
        <hr>
<?php if (isset($show_error_box)) { $this->render('message_box', $this->vars()); } ?>
<?php $this->render('variables', $this->vars()); ?>
	<form name="myform" id="myform" method="post" action=""><input type="hidden" id="value" name="value" value="<?php echo epm_esc(isset($value) ? $value : ''); ?>" />
		<table width="90%" class="alt_table" border="0" cellspacing="0" cellpadding="0">
<?php
// generate_gui_html() does not give each section a title. Its lineloop case
// increments the group counter after it sets a title. The next group then
// has only 'data' until the outer loop names it, and the last group can have
// no name. Read both keys with a default.
foreach ((isset($html_array) ? $html_array : array()) as $section) { ?>
			<tr><th align='left' colspan="2"><strong><?php echo epm_esc(isset($section['title']) ? $section['title'] : ''); ?></strong></th><th>Use Defaults</th></tr>
<?php     $this->render('_setting_fields', array('fields' => isset($section['data']) ? $section['data'] : array())); ?>
<?php } ?>
		</table>
		<p>
		<button type="submit" name="button_save" value="Save"><i class="fa fa-check-square-o green"></i> Submit</button>
		<button type=button onClick="javascript:Close();"><i class="fa fa-times red"></i> Cancel</button>
	</p>
	</form>
</div>
</body>
</html>
