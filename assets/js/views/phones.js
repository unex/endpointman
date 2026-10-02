/*
 * The script of the Phones page: the phone list, and the add and edit forms.
 *
 * views/tpl/devices_manager.php defines epmPhonesText with the translated
 * strings. The forms post to config.php, and views/devices.php runs the
 * action. See that file for each sub_type.
 */
"use strict";

var epmPhones = (function () {
	var text = (typeof epmPhonesText === 'object') ? epmPhonesText : {};
	var base = 'config.php?display=endpointman&view=phones';

	/*
	 * Fill a <select> from one of the option commands of DevicesPage. The
	 * labels are data from the brand packages, so the options are built with
	 * text(), never with HTML.
	 */
	function fillSelect(target, command, id, after) {
		var $target = $(target);
		$target.empty();
		if (id === '' || id === null || typeof id === 'undefined') {
			$target.append($('<option>').val(''));
			return;
		}
		$.getJSON('ajax.php', { module: 'endpointman', view: 'devices', command: command, id: id, _: Date.now() }, function (rows) {
			$.each(rows || [], function (i, row) {
				$target.append($('<option>').val(row.optionValue).text(row.optionDisplay));
			});
			$target.trigger('change.epm');
			if (typeof after === 'function') { after(); }
		});
	}

	function selectedIds() {
		var ids = {};
		$.each($('#epm-phones').bootstrapTable('getSelections'), function (i, row) {
			ids[String(row.id)] = true;
		});
		return Object.keys(ids);
	}

	function appendIds($form, ids) {
		$form.find('input.epm-selected').remove();
		$.each(ids, function (i, id) {
			$form.append($('<input type="hidden" class="epm-selected" name="selected[]">').val(id));
		});
	}

	function post(fields) {
		var $form = $('#epm-bulk');
		$form.find('input:not([name=sub_type])').remove();
		$.each(fields, function (name, value) {
			if (name === 'sub_type') {
				$form.find('input[name=sub_type]').val(value);
			} else if ($.isArray(value)) {
				$.each(value, function (i, v) { $form.append($('<input type="hidden">').attr('name', name + '[]').val(v)); });
			} else {
				$form.append($('<input type="hidden">').attr('name', name).val(value));
			}
		});
		$form.trigger('submit');
	}

	function updateSelection() {
		var count = selectedIds().length;
		$('.epm-needs-selection').prop('disabled', count === 0);
		$('.epm-selection-count').text(count > 0 ? '(' + count + ')' : '');
	}

	function bindList() {
		$('#epm-phones').on('check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table post-body.bs.table', updateSelection);

		$('[data-bulk]').on('click', function (e) {
			e.preventDefault();
			var ids = selectedIds();
			if (ids.length === 0) { alert(text.none_selected); return; }
			if ($(this).data('confirm') === 'many' && !confirm(text.confirm_delete_many)) { return; }
			var fields = { sub_type: $(this).data('bulk'), selected: ids };
			if ($(this).data('reboot')) { fields.reboot = 'on'; }
			post(fields);
		});

		$('[data-all]').on('click', function (e) {
			e.preventDefault();
			var fields = { sub_type: $(this).data('all') };
			if ($(this).data('reboot')) { fields.reboot = 'on'; }
			post(fields);
		});

		$('#epm-phones').on('click', '.epm-delete-phone', function (e) {
			e.preventDefault();
			e.stopPropagation();
			if (confirm(text.confirm_delete_one)) {
				post({ sub_type: 'delete_device', edit_id: $(this).data('id') });
			}
		});

		$('form.epm-with-selection').on('submit', function (e) {
			var ids = selectedIds();
			if (ids.length === 0) { e.preventDefault(); alert(text.none_selected); return; }
			appendIds($(this), ids);
		});

		$('#epm-find form').on('submit', function () {
			$(this).find('button[type=submit]').prop('disabled', true).text(text.searching);
		});

		$('#brand_list_selected').on('change', function () { fillSelect('#model_list_selected', 'model', $(this).val()); });
		$('#product_select').on('change', function () { fillSelect('#template_selector', 'template', $(this).val()); });
		$('#model_select').on('change', function () { fillSelect('#model_template_selector', 'mtemplate', $(this).val()); });

		bindImport();
		updateSelection();
	}

	function bindImport() {
		var imported = false;
		$('#epm-import-form').on('submit', function (e) {
			e.preventDefault();
			var $result = $(this).find('.epm-import-result');
			var $button = $(this).find('button[type=submit]');
			$button.prop('disabled', true);
			$result.removeClass('alert alert-danger').text(text.importing);
			$.ajax({
				url: 'config.php?display=endpointman&view=settings&command=import',
				type: 'POST',
				data: new FormData(this),
				processData: false,
				contentType: false
			}).done(function (html) {
				// The server escapes each value in this output.
				$result.html(html);
				imported = true;
			}).fail(function () {
				$result.addClass('alert alert-danger').text(text.import_failed);
			}).always(function () {
				$button.prop('disabled', false);
			});
		});
		$('#epm-import').on('hidden.bs.modal', function () {
			if (imported) { window.location.href = base; }
		});
	}

	function bindForm() {
		var $form = $('#epm-phone-form');

		$('#brand_edit').on('change', function () {
			fillSelect('#model_new', 'model', $(this).val());
			fillSelect('#template_list', 'template2', '');
			fillSelect('#line_list', 'lines', '');
		});

		// The add form has a line list. The edit form lists the lines in a
		// table, and only the templates change with the model.
		$('#model_new').on('change', function () {
			fillSelect('#template_list', 'template2', $(this).val());
			if ($('#line_list').length) {
				fillSelect('#line_list', 'lines', $(this).val());
			}
		});

		// Buttons that post a different sub_type_sub than Submit: add a line,
		// delete a line, open the template editor.
		$form.on('click', '.epm-sub-action', function () {
			$form.find('input[name=sub_type_sub]').val($(this).data('sub'));
			if ($(this).data('edit-id')) {
				$form.find('input[name=edit_id]').val($(this).data('edit-id'));
			}
			$form[0].submit();
		});

		$('.epm-specifics').on('click', function () {
			var url = 'config.php?display=endpointman&quietmode=1&handler=file&file=popup.html.php&module=endpointman&pop_type=edit_specifics&edit_id='
				+ encodeURIComponent($(this).data('id')) + '&rand=' + Date.now();
			var win = window.open(url, 'epm_specifics', 'height=700,width=750,scrollbars=yes,location=no');
			if (win && window.focus) { win.focus(); }
		});
	}

	return {
		init: function () {
			if ($('#epm-phones').length) { bindList(); }
			if ($('#epm-phone-form').length) { bindForm(); }
		}
	};
})();

$(function () { epmPhones.init(); });
