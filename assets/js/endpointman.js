"use strict";
var box = null;

/*
 * Each page hook has the name of the old FreePBX display of its page:
 * epm_advanced for the settings page, epm_templates for the templates page.
 * All pages now use display=endpointman&view=<view>, so the dispatcher below
 * maps the view to the hook prefix.
 *
 * A view that is not in this map has no hooks. The scripts of those pages do
 * their own binding.
 */
var epmViewHooks = {
	'devices': 'epm_devices',
	'phones': 'epm_devices',
	'settings': 'epm_advanced'
};

function epm_global_getHookPrefix () {
	var view = $.getUrlVar('view');
	if ((typeof view === 'undefined') || (view === null)) { return ''; }
	view = String(view).replace('#', '');
	return epmViewHooks.hasOwnProperty(view) ? epmViewHooks[view] : '';
}

function epm_global_call_hook (name, arg) {
	var prefix = epm_global_getHookPrefix();
	if (prefix === '') { return false; }
	var func = prefix + name;
	if (typeof window[func] !== 'function') { return false; }
	window[func](arg);
	return true;
}

$(document).ready(function() {
	$('ul[role=tablist] li a').on("click", function(){
		var tabclick =  $(this).attr('aria-controls');
		if ((tabclick !== "") && (typeof tabclick !== 'undefined')) {
			setTimeout(function () { epm_global_call_hook("_change_tab", tabclick); }, 500);
		}
	});

	epm_global_call_hook("_document_ready");
});

$(window).on("load", function() {
	epm_global_call_hook("_windows_load", epm_global_get_tab_actual());
});


function epm_global_html_find_hide_and_remove(name = "", tDelay = 1, bSlow = false) {
	if ($(name).length > 0) {
		$(name).delay(tDelay).hide(((bSlow === true)  ? "slow" : ""), function () {
			$(this).remove();
		});
	}
}

function epm_global_html_find_show_hide(name = "", bShow = "auto", tDelay = 1, slow = false) {
	if ($(name).length > 0) {
		if (bShow === true) 		{ $(name).delay(tDelay).show(((slow === true)  ? "slow" : "")); }
		else if (bShow === false)	{ $(name).delay(tDelay).hide(((slow === true)  ? "slow" : "")); }
		else if (bShow === "auto")	{
			if( $(name).is(":visible") ) {
				$(name).delay(tDelay).hide(((slow === true)  ? "slow" : ""));
			} else{
				$(name).delay(tDelay).show(((slow === true)  ? "slow" : ""));
			}
		}
	}
}

function epm_global_html_css_name(name, bStatus, classname)
{
	if ($(name).length > 0) {
		if (bStatus === true) 			{ $(name).addClass(classname); }
		else if (bStatus === false)		{ $(name).removeClass(classname); }
		else if (bStatus === "auto")	{
			if($(name).hasClass(classname)) { $(name).removeClass(classname); }
			else							{ $(name).addClass(classname); }
		}
	}
}

function epm_global_get_tab_actual()
{
	/*
	 * Read the active pane, not the tab. FreePBX 17 serves Bootstrap 4, which
	 * puts the active class on the <a> and not on the <li>. The pane has the
	 * class in both versions, and aria-controls points to its id.
	 */
	return $(".tab-content > .tab-pane.active").first().attr('id') || "";
}

function epm_global_get_value_by_form(sform, snameopt, formtype = "name")
{
	var rdata = null;
	$('form['+formtype+'='+sform+']')
	.find("input, textarea, select")
	.each( function(index) {
		var input = $(this);
		if (snameopt === input.attr('name'))
		{
			rdata = input.val();
		}
	});
	return rdata;
}

//http://oldblog.jesusyepes.com/jquery/limpiar-todos-los-campos-de-un-formulario-con-jquery/
function epm_global_limpiaForm(miForm) {
	// recorremos todos los campos que tiene el formulario
	$(':input', miForm).each(function() {
		var type = this.type;
		var tag = this.tagName.toLowerCase();
		//limpiamos los valores de los campos…
		if (type == 'text' || type == 'password' || tag == 'textarea')
			this.value = "";
			// excepto de los checkboxes y radios, le quitamos el checked
			// pero su valor no debe ser cambiado
		else if (type == 'checkbox' || type == 'radio')
			this.checked = false;
			// los selects le ponesmos el indice a -
		else if (tag == 'select')
			this.selectedIndex = -1;
	});
}




function epm_global_dialog_action(actionname = "", urlStr = "", formname = null, titleStr = "Status", ClassDlg = "", buttons = "")
{
	var oData = null;

	if ((actionname === "") || (urlStr === "")) { return null; }
	box = $('<div id="moduledialogwrapper" ></div>')
	.dialog({
		title: titleStr,
		resizable: false,
		dialogClass: ClassDlg,
		modal: true,
		width: 410,
		height: 'auto',
		maxHeight: 350,
		scroll: true,
		position: { my: "top-175", at: "center", of: window },
		buttons: buttons,
		open: function (e) {
			$('#moduledialogwrapper').html('Loading... ' + '<i class="fa fa-spinner fa-spin fa-2x">');
			$('#moduledialogwrapper').dialog('widget').find('div.ui-dialog-buttonpane div.ui-dialog-buttonset button').eq(0).button('disable');

			if (formname !== null) {
				var form = document.forms.namedItem(formname);
				oData = new FormData(form);
			}

			var xhr = new XMLHttpRequest(),
			timer = null;
			xhr.open('POST', urlStr, true);
			xhr.send(oData);
			timer = window.setInterval(function() {
				//$('#moduledialogwrapper').animate({ scrollTop: $(this).scrollTop() + $(document).height() });
				if (xhr.readyState === XMLHttpRequest.DONE) {
					window.clearTimeout(timer);
					if (typeof end_module_actions === 'function') {
						$('#moduledialogwrapper').dialog('widget').find('div.ui-dialog-buttonpane div.ui-dialog-buttonset button').eq(0).button('enable');
						end_module_actions(actionname);
					}
				}
				if (xhr.responseText.length > 0) {
					if ($('#moduledialogwrapper').html().trim() !== xhr.responseText.trim()) {
						$('#moduledialogwrapper').html(xhr.responseText);
						//$('#moduleprogress').scrollTop(1E10);
						$('#moduledialogwrapper').animate({ scrollTop: $(this).scrollTop() + $(document).height() });
					}
				}
				if (xhr.readyState === XMLHttpRequest.DONE) {
					//$("#moduleprogress").css("overflow", "auto");
					//$('#moduleprogress').scrollTop(1E10);
					$('#moduledialogwrapper').animate({ scrollTop: $(this).scrollTop() + $(document).height() });
					$("#moduleBoxContents a").focus();
				}
			}, 500);

		},
		close: function(e) {
			if (typeof close_module_actions === 'function') {
				close_module_actions(false, actionname);
			}
			$(e.target).dialog("destroy").remove();
		}
	});
}

function close_module_actions(goback, acctionname = "")
{
	if (box !== null) {
		box.dialog("destroy").remove();
	}

	var prefix = epm_global_getHookPrefix();
	if (prefix !== "") {
		var func = 'close_module_actions_'+prefix;
		if (typeof window[func] === 'function') {
			window[func](goback, acctionname);
		}
	}

	if (goback) {
		location.reload();
	}
}

function end_module_actions(acctionname = "")
{
	var prefix = epm_global_getHookPrefix();
	if (prefix !== "") {
		var func = 'end_module_actions_'+prefix;
		if (typeof window[func] === 'function') {
			window[func](acctionname);
		}
	}
}

function epm_global_refresh_table(snametable = "", showmsg = false)
{
	if (snametable === "") { return; }
	console.log($(snametable).bootstrapTable('refresh'));
	if (showmsg === true) {
		fpbxToast("Table Refrash Ok!", '', 'success');
	}
}


function epm_global_input_value_change_bt(sNameID = "", sValue = "", bSetFocus = false)
{
	if (sNameID === "" ) { return false; }

	if ($(sNameID).hasClass("selectpicker") == true) {
		$(sNameID).selectpicker('val', sValue);
	}
	else {
		$(sNameID).val(sValue);
	}
	if (bSetFocus === true) { $(sNameID).focus(); }
}










// INI: CODIGO DE FREEPBX
function epm_global_update_jquery_msg_help()
{
	if($(".fpbx-container").length>0){
		var loc=window.location.hash.replace("#","");
		if(loc!==""&&$(".fpbx-container li[data-name="+loc+"] a").length>0){
			$(".fpbx-container li[data-name="+loc+"] a").tab('show');
		}
		$(".fpbx-container i.fpbx-help-icon").on("mouseenter",function(){
			var id=$(this).data("for");
			var container=$(this).parents(".element-container");
			$(".fpbx-help-block").removeClass("active");
			$("#"+id+"-help").addClass("active");
			container.one("mouseleave",function(event){
				if(event.relatedTarget&&(event.relatedTarget.type=="submit"||event.relatedTarget.type=="button")){return;}
				var act=$("#"+id+"-help").data("activate");
				if(typeof act!=="undefined"&&act=="locked"){return;}
				$("#"+id+"-help").fadeOut("slow",function(){
					$(this).removeClass("active").css("display","");
				});
				$(this).off("mouseleave");
			});
		});
	}
}
// END: CODIGO DE FREEPBX

/*
 * Run a task of the module in a dialog, and show its output while it runs.
 *
 * The task is a config.php URL that prints its progress, for example a brand
 * install. The server builds that output with out() and escapes each value
 * in it, so the dialog shows it as HTML.
 *
 * options.title    dialog title
 * options.url      task URL
 * options.data     FormData to post, or null for no body
 * options.text     {close, running, done, failed}: translated labels
 * options.reload   true to reload the page when the dialog closes (default)
 */
function epmTask(options) {
	var text = $.extend({ close: 'Close', running: 'Running...', done: 'Done.', failed: 'The task failed.' }, options.text || {});
	var $modal = $('<div class="modal fade epm-task" tabindex="-1" role="dialog" aria-hidden="true">'
		+ '<div class="modal-dialog modal-lg" role="document"><div class="modal-content">'
		+ '<div class="modal-header"><h5 class="modal-title"></h5></div>'
		+ '<div class="modal-body"><div class="epm-task-output"></div><p class="epm-task-state text-muted mb-0"></p></div>'
		+ '<div class="modal-footer"><button type="button" class="btn btn-primary" data-dismiss="modal" disabled></button></div>'
		+ '</div></div></div>');
	$modal.find('.modal-title').text(options.title || '');
	$modal.find('.epm-task-state').text(text.running);
	$modal.find('.modal-footer button').text(text.close);
	$('body').append($modal);

	var $out = $modal.find('.epm-task-output');
	var xhr = new XMLHttpRequest();
	var shown = 0;
	var show = function () {
		if (xhr.responseText.length !== shown) {
			shown = xhr.responseText.length;
			$out.html(xhr.responseText);
			$out.scrollTop($out[0].scrollHeight);
		}
	};
	xhr.open(options.data ? 'POST' : 'GET', options.url, true);
	xhr.onprogress = show;
	xhr.onloadend = function () {
		show();
		var ok = (xhr.status >= 200 && xhr.status < 300);
		$modal.find('.epm-task-state').text(ok ? text.done : text.failed).toggleClass('text-danger', !ok);
		$modal.find('.modal-footer button').prop('disabled', false).focus();
	};
	$modal.on('hidden.bs.modal', function () {
		$modal.remove();
		if (options.reload !== false) { window.location.reload(); }
	});
	$modal.modal({ backdrop: 'static', keyboard: false });
	xhr.send(options.data || null);
}
