"use strict";
var cmeditor = null;

function epm_templates_document_ready () {

	var arrayJs = ['assets/endpointman/js/addon/simplescrollbars.js', 'assets/endpointman/js/mode/xml.js'];
	arrayJs.forEach(function (item, index, array) {
		var x = document.createElement('script');
		x.src = item;
		document.getElementsByTagName("head")[0].appendChild(x);
	});


	// Reset the Add dialog every time it opens.
	//
	// Clearing the values is not sufficient. Both selects are bootstrap-select
	// pickers. A picker draws its own button and reads the <select> again only
	// on 'refresh'. Without the refresh, the dialog shows the product of the last
	// time while the value is "", and Save reports "Faltan Datos!" for a field
	// that looks filled. Also empty the clone list. It comes from the selected
	// product, so old models in it do not belong to the next product.
	$('#AddDlgModal').on('show.bs.modal', function (event) {
		$(this).find('input').val("");
		$('#NewProductSelect').val("");
		$('#NewCloneModel').html("");
		$(this).find('select.selectpicker').selectpicker('refresh');
	});
	$('#AddDlgModal_bt_new').on("click", function() { epm_templates_grid_add(); });
	$('#NewProductSelect').on('change', function() { epm_templates_add_NewProductSelect_Change (this); });

	//Al iniciar la apertura de la ventana
	$('#CfgGlobalTemplate').on('show.bs.modal', function (e) {
		epm_template_custom_config_get_global(e);
	});

	//Al finalizar la apertura de la ventana	$('#CfgGlobalTemplate').on('shown.bs.modal', function (e) { });
	//Antes de iniciar el cierre de la ventana	$('#CfgGlobalTemplate').on('hide.bs.modal', function (e) { });
	//Despues de Cerrar la ventana				$('#CfgGlobalTemplate').on('hidden.bs.modal', function (e) { });















	$('#CfgEditFileTemplate').on('show.bs.modal', function (e) {         });

	$('#CfgEditFileTemplate').on('shown.bs.modal', function (e) {
		if (cmeditor === null) {
			cmeditor = CodeMirror.fromTextArea(document.getElementById("config_textarea"), {
				lineNumbers: true,
				matchBrackets: true,
				readOnly: false,
				viewportMargin: Infinity,
				scrollbarStyle: "simple"
			});
		}
		cmeditor.setValue(document.getElementById("config_textarea").value);
	});

	$('#CfgEditFileTemplate').on('hidden.bs.modal', function (e) {
		/* DESPUES DE CERRAR: CODIGO QUE ACTUALIZA EL SELECT... */

		$('#edit_file_name_path').val("No Selected");
		$('#config_textarea').val("");
		cmeditor.setValue("");
	});
	$('#CfgEditFileTemplate').on('hidden.bs.modal', function (e) {        });




	// Open the config file editor popup for the file that the select next to
	// the button names. The value of the select is "<id>_<name>", the `value`
	// that the popup parses. 0 means the stock file. Any other id means a
	// saved alternative in endpointman_custom_configs.
	//
	// The #CfgEditFileTemplate modal is not finished. It stays in the page
	// until it is finished or removed.
	$('.files_edit_configs button').click(function (e) {
		var value = $(this).closest('.files_edit_configs').find('select').val();
		if (!value) {
			return false;
		}
		var url = 'config.php?display=endpointman&quietmode=1&handler=file'
			+ '&file=popup.html.php&module=endpointman&pop_type=alt_cfg_edit'
			+ '&custom=' + encodeURIComponent($('#custom').val())
			+ '&tid=' + encodeURIComponent($('#id').val())
			+ '&value=' + encodeURIComponent(value)
			+ '&rand=' + new Date().getTime();
		var popup = window.open(url, 'epm_alt_cfg', 'height=710,width=800,scrollbars=yes,location=no');
		if (window.focus && popup) { popup.focus(); }
		return false;
	});

	// The popup calls opener.Reload() after saving.
	window.Reload = function () {
		window.location.reload();
	};

	$('select[class~="selectpicker"][data-url]').each(function(index, value) { epm_template_update_select_files_config($(this)); });





}

function epm_templates_windows_load (nTab = "") {

}

function epm_templates_change_tab (nTab = "") {

}








function epm_template_update_select_files_config (e) {
	var select = e;
	var url    = e.attr('data-url');
	var id     = e.attr('data-id');
	var label  = e.attr('data-label');
	select.html('');
	select.append('<option data-icon="fa fa-refresh fa-spin fa-fw" value="" selected>Loading...</option>');
	select.selectpicker('refresh');
	$.getJSON(url, function(data)
	{
		select.html('');
		$.each(data.only_configs, function(key, val)
		{
			if (val['select'] == "ON") {
				select.append('<option data-icon="fa fa-files-o" value="' + val[id] + '_' + val[label] + '" selected>' + val[label] + ' (No Change)</option>');
			}
			else {
				select.append('<option data-icon="fa fa-files-o" value="' + val[id] + '_' + val[label] + '">' + val[label] + ' (No Change)</option>');
			}
		});
		if (data.alt_configs != null)
		{
			select.append('<optgroup label="Modificaiones"></optgroup>');
			var seloptgroup = select.find("optgroup");
			$.each(data.alt_configs, function(key, val)
			{
				if (val['select'] == "ON") {
					seloptgroup.append('<option data-icon="fa fa-pencil-square-o" style="background: #5cb85c; color: #fff;" value="' + val[id] + '_' + val[label] + '" selected>' + val[label] + '</option>');
				}
				else {
					seloptgroup.append('<option data-icon="fa fa-pencil-square-o" style="background: #5cb85c; color: #fff;" value="' + val[id] + '_' + val[label] + '">' + val[label] + '</option>');
				}
			});

		};
		select.selectpicker('refresh');
	});
}














// A click on a row of the side navigation list of the editor opens that
// template.
//
// Use a delegated handler. #table-all-side is the right navigation, which
// config.php writes into the footer, after this script runs. A direct
// $("#table-all-side").on(...) thus binds to an empty set. A handler on
// document also works before bootstrap-table starts the table, because the
// event bubbles.
$(document).on('click-row.bs.table', '#table-all-side', function (e, row, elem) {
	window.location = '?display=endpointman&view=templates&subpage=editor&custom='+row['custom']+'&idsel='+row['id'];
});

function epm_templates_grid_FormatThEnabled(value, row, index){
	var html = '';
    if (value == 1) {
    	html += '<i class="fa fa-check-square-o fa-lg"></i> Enabled';
	}
    else {
    	html += '<i class="fa fa-square-o fa-lg"></i> Disabled';
    }
    return html;
}

function epm_templates_grid_FormatThAction(value, row, index){
	var html = '';
	html += '<a href="?display=endpointman&view=templates&subpage=editor&custom='+row.custom+'&idsel='+value+'">';
	html += '<i class="fa fa-edit"></i>';
	html += '</a>&nbsp;';
	if (row.custom == "0"){
		html += '<a class="delAction" href="javascript:epm_templates_grid_del('+value+')">';
		html += '<i class="fa fa-trash"></i>';
		html += '</a>&nbsp;';
	}
    return html;
}





function epm_templates_add_NewProductSelect_Change (obj)
{
	if ($(obj).val() != "") {
		$.ajax({
			type: 'POST',
			url: "ajax.php",
			data: {
				module: "endpointman",
				view: "templates",
				command: "model_clone",
				id : $(obj).val()
			},
			dataType: 'json',
			timeout: 60000,
			error: function(xhr, ajaxOptions, thrownError) {
				fpbxToast('ERROR AJAX:' + thrownError,'ERROR (' + xhr.status + ')!','error');
				return false;
			},
			success: function(data)
			{
				var options = '';
				if (data.status == true)
				{
					$(data.listopt).each(function(index, itemData)
					{
						options += '<option value="' + itemData.optionValue + '">' + itemData.optionDisplay + '</option>';
					});
				}
				else {
					options = '<option value="">'+ data.message +'</option>';
					fpbxToast(data.message, "Error!", 'error');
				}
				// Refresh, then select the first model through the picker, not through the
				// `selected` attribute of the option. The attribute selects the model in the
				// <select>, but the button of the picker continues to show "Nothing
				// selected". 'val' sets the value and draws the button again.
				$("#NewCloneModel").html(options);
				$('#NewCloneModel').selectpicker('refresh');
				$('#NewCloneModel').selectpicker('val', $('#NewCloneModel option:first').val());
			}
		});
	}
	else {
		$("#NewCloneModel").html('');
		$('#NewCloneModel').selectpicker('refresh');
	}
}

function epm_templates_grid_del (iddel)
{
	if (iddel == "")
	{
		fpbxToast("ID no send!", "Error!", 'error');
	}
	else {
		$.ajax({
	        cache: false,
			type: 'POST',
			url: "ajax.php",
			data: {
				module: "endpointman",
				view: "templates",
				command: "del_template",
				idsel : iddel
			},
			dataType: 'json',
			timeout: 60000,
			error: function(xhr, ajaxOptions, thrownError) {
				fpbxToast('ERROR AJAX:' + thrownError,'ERROR (' + xhr.status + ')!','error');
				return false;
			},
			success: function(data) {
				if (data.status == true) { fpbxToast(data.message, '', 'success'); }
				else { fpbxToast(data.message, "Error!", 'error'); }
				$("#mygrid").bootstrapTable('refresh');
			}
		});
	}
}

function epm_templates_grid_add()
{
	var NameTemplate = $('#NewTemplateName').val();
	var ProductSelec = $('#NewProductSelect').val();
	var CloneModel = $('#NewCloneModel').val();

	// Use `!x`, not `x == ""`. An empty <select> has no selected option, so
	// .val() gives null, and null == "" is false. Without this test, a missing
	// clone model goes to the server, which then reports a different error
	// ("Value send is not number!").
	if (!NameTemplate || !ProductSelec || !CloneModel)
	{
		fpbxToast("Faltan Datos!", "Error!", 'error');
	}
	else {
		$.ajax({
			type: 'POST',
			url: "ajax.php",
			data: {
				module: "endpointman",
				view: "templates",
				command: "add_template",
				newnametemplate : NameTemplate,
				newproductselec : ProductSelec,
				newclonemodel : CloneModel
			},
			dataType: 'json',
			timeout: 60000,
			error: function(xhr, ajaxOptions, thrownError) {
				fpbxToast('ERROR AJAX:' + thrownError,'ERROR (' + xhr.status + ')!','error');
				return false;
			},
			success: function(data) {
				if (data.status == true)
				{
					fpbxToast(data.message, '', 'success');
					setTimeout (function () { window.location.href = "config.php?display=endpointman&view=templates&subpage=editor&custom=0&idsel="+data.newid; }, 500);
				}
				else { fpbxToast(data.message, "Error!", 'error'); }
			}
		});
	}
}







function epm_template_custom_config_get_global(elmnt)
{
	$.ajax({
		type: 'POST',
		url: "ajax.php",
		data: {
			module: "endpointman",
			view: "templates",
			command: "custom_config_get_gloabl",
			custom : $.getUrlVar('custom'),
			tid : $.getUrlVar('idsel')
		},
		dataType: 'json',
		timeout: 60000,
		error: function(xhr, ajaxOptions, thrownError) {
			fpbxToast('ERROR AJAX:' + thrownError,'ERROR (' + xhr.status + ')!','error');
			return false;
		},
		success: function(data) {
			if (data.status == true)
			{
				epm_global_input_value_change_bt("#srvip", data.settings.srvip, false);
				epm_global_input_value_change_bt("#server_type", data.settings.server_type, false);
				epm_global_input_value_change_bt("#config_loc", data.settings.config_location, false);
				epm_global_input_value_change_bt("#tz", data.settings.tz, false);
				epm_global_input_value_change_bt("#ntp_server", data.settings.ntp, false);

				if (elmnt.name == "button_undo_globals") {
					fpbxToast(data.message, '', 'success');
				}
			}
			else { fpbxToast(data.message, "Error!", 'error'); }
		}
	});
}

function epm_template_custom_config_update_global(elmnt)
{
	$.ajax({
		type: 'POST',
		url: "ajax.php",
		data: {
			module: "endpointman",
			view: "templates",
			command: "custom_config_update_gloabl",
			custom : $.getUrlVar('custom'),
			tid : $.getUrlVar('idsel'),
			tz: epm_global_get_value_by_form("FormCfgGlobalTemplate","tz"),
			ntp_server: epm_global_get_value_by_form("FormCfgGlobalTemplate","ntp_server"),
			srvip: epm_global_get_value_by_form("FormCfgGlobalTemplate","srvip"),
			config_loc: epm_global_get_value_by_form("FormCfgGlobalTemplate","config_loc"),
			server_type: epm_global_get_value_by_form("FormCfgGlobalTemplate","server_type")
		},
		dataType: 'json',
		timeout: 60000,
		error: function(xhr, ajaxOptions, thrownError) {
			fpbxToast('ERROR AJAX:' + thrownError,'ERROR (' + xhr.status + ')!','error');
			return false;
		},
		success: function(data) {
			if (data.status == true)
			{
				fpbxToast(data.message, '', 'success');
			}
			else { fpbxToast(data.message, "Error!", 'error'); }
		}
	});
}

function epm_template_custom_config_reset_global(elmnt)
{
	$.ajax({
		type: 'POST',
		url: "ajax.php",
		data: {
			module: "endpointman",
			view: "templates",
			command: "custom_config_reset_gloabl",
			custom : $.getUrlVar('custom'),
			tid : $.getUrlVar('idsel')
		},
		dataType: 'json',
		timeout: 60000,
		error: function(xhr, ajaxOptions, thrownError) {
			fpbxToast('ERROR AJAX:' + thrownError,'ERROR (' + xhr.status + ')!','error');
			return false;
		},
		success: function(data) {
			if (data.status == true)
			{
				fpbxToast(data.message, '', 'success');
				epm_template_custom_config_get_global(elmnt);
			}
			else { fpbxToast(data.message, "Error!", 'error'); }
		}
	});
}









function epm_template_edit_select_area_list (obj)
{

	var maxlines = obj.options[obj.selectedIndex].value;
	var id = epm_global_get_value_by_form("epm_template_edit_form", "id");

	var silent_mode = $.getUrlVar('silent_mode');
	if (silent_mode == true)
	{

		alert ("true");


		if (id == 0) {
			fpbxToast("No Device Selected to Edit!!", "Error!", 'error');
		}
		else {


			/*
			model_list = 126
			template_list = 0
			and = new Date().getTime()

	    <?php if (isset($_REQUEST['silent_mode'])) { echo '<input name="silent_mode" id="silent_mode" type="hidden" value="1">'; } ?>
		<input name="" id="id" type="hidden" value="<?php echo $dtemplate['hidden_id']; ?>">
		<input name="custom" id="custom" type="hidden" value="<?php echo $dtemplate['hidden_custom'] ; ?>">


			// --> PHP
			$template_editor = TRUE;
			$sql = "UPDATE  endpointman_mac_list SET  model =  '".$_REQUEST['model_list']."' WHERE  id =".$_REQUEST['edit_id']; -> id cambiar por template_id
			$endpoint->eda->sql($sql);
			$endpoint->tpl->assign("silent_mode", 1);

			if ($_REQUEST['template_list'] == 0) {
				$endpoint->edit_template_display($_REQUEST['edit_id'],1);
			} else {
				$endpoint->edit_template_display($_REQUEST['template_list'],0);
			}
			// <-- PHP
		*/
		}
	}
	else
	{
		var custom = $.getUrlVar('custom');
		window.location.href='config.php?display=endpointman&view=templates&subpage=editor&custom=' + custom + '&idsel=' + id + '&maxlines=' + maxlines
	}

}



























	/*
		//edit
		//<a href="#" onclick="return popitup('config.php?type=tool&display=epm_config&amp;quietmode=1&amp;handler=file&amp;file=popup.html.php&amp;module=endpointman&amp;pop_type=alt_cfg_edit', '<?php echo $row['name']; ?>')">
		function popitup(url, name) {
            newwindow=window.open(url + '&custom=' + document.getElementById('custom').value + '&tid=' + document.getElementById('id').value + '&value=' + document.getElementById('altconfig_'+ name).value + '&rand=' + new Date().getTime(),'name','height=710,width=800,scrollbars=yes,location=no');
                if (window.focus) {newwindow.focus()}
                return false;
        }
		//edit
		//<a href='#' onclick='return popitup2("config.php?type=tool&display=epm_config&amp;quietmode=1&amp;handler=file&amp;file=popup.html.php&amp;module=endpointman&amp;pop_type=alt_cfg_edit", "<?php echo $row['name']?>")'>
        function popitup2(url, name) {
            newwindow=window.open(url + '&custom=' + document.getElementById('custom').value + '&tid=' + document.getElementById('id').value + '&value=0_' + name + '&rand=' + new Date().getTime(),'name','height=700,width=800,scrollbars=yes,location=no');
                if (window.focus) {newwindow.focus()}
                return false;
        }


*/
