/*
 * The script of the template list and the template editor.
 *
 * The pages define epmTemplatesText with the translated strings. The
 * commands go to TemplatesPage (view=templates). Template, family, model and
 * file names are data, so this script adds them with text() or escapes them.
 */
"use strict";

var epmTemplates = (function () {
	var text = (typeof epmTemplatesText === 'object') ? epmTemplatesText : {};

	function esc(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function fmt(s, v) {
		return String(s).replace('%s', v);
	}

	function call(command, data) {
		return $.ajax({
			type: 'POST',
			url: 'ajax.php',
			data: $.extend({ module: 'endpointman', view: 'templates', command: command }, data || {}),
			dataType: 'json',
			timeout: 60000
		}).fail(function () { fpbxToast(text.error, '', 'error'); });
	}

	function toast(data) {
		fpbxToast((data && data.message) || text.error, '', (data && data.status) ? 'success' : 'error');
	}

	/* The template list. */

	function bindList() {
		$('#AddDlgModal').on('show.bs.modal', function () {
			$('#NewTemplateName').val('');
			$('#NewProductSelect').val('');
			$('#NewCloneModel').empty().append($('<option value="">'));
		});

		$('#NewProductSelect').on('change', function () {
			var $models = $('#NewCloneModel').empty();
			if (!$(this).val()) {
				$models.append($('<option value="">'));
				return;
			}
			call('model_clone', { id: $(this).val() }).done(function (data) {
				if (!data || !data.status) { toast(data); return; }
				$.each(data.listopt || [], function (i, row) {
					$models.append($('<option>').val(row.optionValue).text(row.optionDisplay));
				});
			});
		});

		$('#AddDlgModal_bt_new').on('click', function () {
			var name = $.trim($('#NewTemplateName').val());
			var family = $('#NewProductSelect').val();
			var model = $('#NewCloneModel').val();
			if (!name) { alert(text.name_required); return; }
			if (!family) { alert(text.family_required); return; }
			if (!model) { alert(text.model_required); return; }
			call('add_template', { newnametemplate: name, newproductselec: family, newclonemodel: model }).done(function (data) {
				toast(data);
				if (data && data.status) {
					window.location.href = 'config.php?display=endpointman&view=templates&subpage=editor&custom=0&idsel=' + encodeURIComponent(data.newid);
				}
			});
		});

		$('#mygrid').on('click', '.epm-template-delete', function (e) {
			e.preventDefault();
			if (!confirm(fmt(text.confirm_delete, $(this).data('name')))) { return; }
			call('del_template', { idsel: $(this).data('id') }).done(function (data) {
				toast(data);
				$('#mygrid').bootstrapTable('refresh');
			});
		});
	}

	/* The template editor. */

	function fillFileSelect($select) {
		var id = $select.data('id');
		var label = $select.data('label');
		$select.empty().append($('<option value="">').text(text.loading));
		$.getJSON($select.data('url'), function (data) {
			$select.empty();
			$.each(data.only_configs || [], function (key, val) {
				$select.append($('<option>').val(val[id] + '_' + val[label]).text(fmt(text.stock, val[label])).prop('selected', val.select === 'ON'));
			});
			if (data.alt_configs && data.alt_configs.length) {
				var $group = $('<optgroup>').attr('label', text.versions);
				$.each(data.alt_configs, function (key, val) {
					$group.append($('<option>').val(val[id] + '_' + val[label]).text(val[label]).prop('selected', val.select === 'ON'));
				});
				$select.append($group);
			}
		});
	}

	function readGlobals(showToast) {
		call('custom_config_get_gloabl', { custom: $('#custom').val(), tid: $('#id').val() }).done(function (data) {
			if (!data || !data.status) { toast(data); return; }
			$('#srvip').val(data.settings.srvip);
			$('#server_type').val(data.settings.server_type);
			$('#config_loc').val(data.settings.config_location);
			$('#tz').val(data.settings.tz);
			$('#ntp_server').val(data.settings.ntp);
			if (showToast) { toast(data); }
		});
	}

	function bindEditor() {
		$('.files_edit_configs select[data-url]').each(function () { fillFileSelect($(this)); });

		// Edit opens the config file editor popup for the version that the
		// select names. The value is "<id>_<name>". 0 is the package file. Any
		// other id is a version in endpointman_custom_configs.
		$('.files_edit_configs button').on('click', function () {
			var value = $(this).closest('.files_edit_configs').find('select').val();
			if (!value) { return; }
			var url = 'config.php?display=endpointman&quietmode=1&handler=file'
				+ '&file=popup.html.php&module=endpointman&pop_type=alt_cfg_edit'
				+ '&custom=' + encodeURIComponent($('#custom').val())
				+ '&tid=' + encodeURIComponent($('#id').val())
				+ '&value=' + encodeURIComponent(value)
				+ '&rand=' + Date.now();
			var popup = window.open(url, 'epm_alt_cfg', 'height=710,width=800,scrollbars=yes,location=no');
			if (popup && window.focus) { popup.focus(); }
		});

		// The popup calls opener.Reload() after a save.
		window.Reload = function () { window.location.reload(); };

		$('#area_list').on('change', function () {
			window.location.href = 'config.php?display=endpointman&view=templates&subpage=editor&custom='
				+ encodeURIComponent($('#custom').val()) + '&idsel=' + encodeURIComponent($('#id').val())
				+ '&maxlines=' + encodeURIComponent($(this).val());
		});

		$('#CfgGlobalTemplate').on('show.bs.modal', function () { readGlobals(false); });
		$('#CfgGlobalTemplate [data-fill]').on('click', function () {
			$($(this).data('fill')).val($(this).data('value'));
		});
		$('#CfgGlobalTemplate [name=button_update_globals]').on('click', function () {
			var form = $('#FormCfgGlobalTemplate');
			call('custom_config_update_gloabl', {
				custom: $('#custom').val(),
				tid: $('#id').val(),
				tz: form.find('[name=tz]').val(),
				ntp_server: form.find('[name=ntp_server]').val(),
				srvip: form.find('[name=srvip]').val(),
				config_loc: form.find('[name=config_loc]').val(),
				server_type: form.find('[name=server_type]').val()
			}).done(function (data) {
				toast(data);
				if (data && data.status) { $('#CfgGlobalTemplate').modal('hide'); }
			});
		});
		$('#CfgGlobalTemplate [name=button_reset_globals]').on('click', function () {
			call('custom_config_reset_gloabl', { custom: $('#custom').val(), tid: $('#id').val() }).done(function (data) {
				toast(data);
				if (data && data.status) { readGlobals(false); }
			});
		});
	}

	return {
		init: function () {
			if ($('#mygrid').length) { bindList(); }
			if ($('#epm_template_edit_form').length) { bindEditor(); }
		},
		formatEnabled: function (value) {
			return value == 1 ? esc(text.enabled) : '<span class="text-muted">' + esc(text.disabled) + '</span>';
		},
		formatActions: function (value, row) {
			var html = '<a href="config.php?display=endpointman&amp;view=templates&amp;subpage=editor&amp;custom=' + encodeURIComponent(row.custom)
				+ '&amp;idsel=' + encodeURIComponent(value) + '" title="' + esc(text.edit) + '"><i class="fa fa-edit"></i></a>';
			if (row.custom == '0') {
				html += '&nbsp; <a href="#" class="epm-template-delete" data-id="' + esc(value) + '" data-name="' + esc(row.name) + '" title="' + esc(text.delete) + '"><i class="fa fa-trash"></i></a>';
			}
			return html;
		}
	};
})();

/* The grid of views/epm_templates/manager.views.grid.php names these. */
function epm_templates_grid_FormatThEnabled(value, row, index) { return epmTemplates.formatEnabled(value, row); }
function epm_templates_grid_FormatThAction(value, row, index) { return epmTemplates.formatActions(value, row); }

$(function () { epmTemplates.init(); });
