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
 * The textarea is plain. The page loads no CodeMirror files.
 */
?>
<html>
    <head>
        <title>PBX Endpoint Configuration Manager</title>
        <link href="assets/endpointman/theme/codemirror.css" rel="stylesheet" type="text/css" />
        <link href="assets/endpointman/css/main.css" rel="stylesheet" type="text/css">
        <link href="assets/css/font-awesome.min-4.7.0.css" rel="stylesheet" type="text/css" />
        <link href="assets/endpointman/css/default.css" rel="stylesheet" type="text/css" />
        <script language="javascript">
            function Close() {
                self.close();
            }
<?php if (!empty($saved)) { ?>
            // The save is complete when the server sends this document. Tell
            // the page that opened the popup to list its files again.
            // assets/js/views/epm_templates.js defines Reload() for the
            // template editor. The devices page has no such function.
            if (window.opener && !window.opener.closed
                && typeof window.opener.Reload === 'function') {
                window.opener.Reload();
            }
<?php } ?>
        </script>
    </head>
    <body name="alt_config_pop">
     <div id="page_body">
        <div id="spinner">
        </div>
        <h1><face="Arial"><center><?php echo _('End Point Configuration Manager')?></center></h1>
        <hr>
<?php if (isset($show_error_box)) { $this->render('message_box', $this->vars()); } ?>
<?php
// popup.inc.php assigns `file` for a file on disk and `filename` for a saved
// alternative in the database. Use the one that is set for the heading.
$heading_file = isset($file) ? $file : (isset($filename) ? $filename : '');
?>
	<center><h4><?php echo _("File Configuration Editor For")?>: <?php echo epm_esc($heading_file); ?></h4>
	    <font color="red">Warning! You can really mess up your phone by messing with these settings. Potentially causing it to not boot!</font><br/>
<?php if (isset($location) && isset($allow_hdfiles)) { ?>
	    <strong>You are editing a global file. This will effect any & all phones that reference this hard file
		<br/>It is <font color="red">NOT</font> individual to this template!!!</strong><br/>
	    <i>You can setup individual templates in the "Product Configuration Editor" under "End Point Advanced Settings"</i>
<?php } ?>
	    <form name="myform" id="myform" method="post" action=""><input type="hidden" id="value" name="value" value="<?php echo epm_esc(isset($value) ? $value : ''); ?>" />
		<table width="90%" border="0" cellspacing="4" cellpadding="4">
		    <tr><td><div style="font-size: 14px" align="left"><?php if (isset($location)) { ?>File Location: <i><?php echo epm_esc($location); ?></i><?php } ?></div></td></tr>
		    <tr>
			<td>
			    <label>
				<textarea name="config_text" id="textarea" cols="100"><?php echo epm_esc(isset($config_data) ? $config_data : ''); ?></textarea>
			    </label>
			    <br><div style="font-size: 15px">
				<label>
				    <button type="submit" name="button_save" value="Save"><i class='fa fa-floppy-o blue'></i> <?php echo _('Save')?></button>
                    <button type=button onClick="javascript:Close();"><i class='fa fa-floppy-o blue'></i> <?php echo _('Cancel')?></button>
				</label>
		    </tr>
		</table>
	    </form>
	</center>
    </div>
    </body>
</html>
