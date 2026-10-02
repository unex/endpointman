<?php
/**
 * The popup that edits one alternative config file.
 * includes/popup.inc.php draws it for pop_type=alt_cfg_edit.
 *
 * @var string $file          File being edited, basename only
 * @var string $config_data   Its contents
 * @var string $location      Full path, set only when editing a file on disk
 * @var string $allow_hdfiles Set only when hard files may be edited
 * @var string $value         Opaque token echoed back on submit
 *
 * @var bool   $saved         Set when this request just wrote a file or row
 *
 * Each value on this page prints through epm_esc(): the heading, the path,
 * the token and the file itself. See lib/esc.inc.php.
 *
 * The file contents arrive raw and are escaped once, here. A config file that
 * contains `</textarea><script>` thus cannot close the field. The single
 * escape also keeps the file unchanged on a save, because the browser decodes
 * the entities to the bytes of the file before the POST.
 *
 * The page loads no jQuery. FreePBX 17 has no jquery-1.7.1 or jquery-ui-1.8.9
 * under /admin/assets/js/. The Save button has name="button_save", so the
 * native submit writes the file. The parent page refreshes from the response
 * to the save, which is the first moment when the write is complete.
 *
 * The textarea is plain. The page loads no CodeMirror files. The page uses
 * the Bootstrap file of FreePBX.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo _('Config file'); ?></title>
<link href="assets/css/bootstrap-4.6.1.min.css" rel="stylesheet" type="text/css">
<style>body { padding: 1rem; } textarea { font-family: monospace; font-size: 0.85rem; }</style>
<script>
<?php if (!empty($saved)) { ?>
// The save is complete when the server sends this document. Tell the page
// that opened the popup to list its files again. assets/js/views/epm_templates.js
// defines Reload() for the template editor.
if (window.opener && !window.opener.closed && typeof window.opener.Reload === 'function') {
	window.opener.Reload();
}
<?php } ?>
</script>
</head>
<body>
<?php
// popup.inc.php assigns `file` for a file on disk and `filename` for a saved
// version in the database. Use the one that is set for the heading.
$heading_file = isset($file) ? $file : (isset($filename) ? $filename : '');
?>
	<h1 class="h4"><?php echo sprintf(_('Config file %s'), '<span class="text-monospace">' . epm_esc($heading_file) . '</span>'); ?></h1>
<?php if (isset($show_error_box)) { $this->render('message_box', $this->vars()); } ?>
	<div class="alert alert-warning"><?php echo _('A wrong value can stop the phone. The phone can then fail to start.'); ?></div>
<?php if (isset($location) && (!empty($allow_hd) || !empty($allow_hdfiles))) { ?>
	<div class="alert alert-danger"><?php echo _('You edit the file of the package. The change applies to all phones that use this file, not only to this template. A brand update can overwrite it.'); ?></div>
<?php } elseif (isset($location)) { ?>
	<p class="text-muted"><?php echo _('Save makes your version of this file for this template. The file of the package does not change.'); ?></p>
<?php } ?>
<?php if (isset($location)) { ?>
	<p class="small text-muted text-monospace"><?php echo epm_esc($location); ?></p>
<?php } ?>
	<form name="myform" id="myform" method="post" action="">
		<input type="hidden" id="value" name="value" value="<?php echo epm_esc(isset($value) ? $value : ''); ?>">
		<textarea class="form-control" name="config_text" id="textarea" rows="24"><?php echo epm_esc(isset($config_data) ? $config_data : ''); ?></textarea>
		<div class="mt-2">
			<button type="submit" class="btn btn-primary" name="button_save" value="Save"><?php echo _('Save'); ?></button>
			<button type="button" class="btn btn-secondary" onclick="window.close();"><?php echo _('Close'); ?></button>
		</div>
	</form>
</body>
</html>
