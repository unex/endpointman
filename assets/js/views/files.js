/*
 * The script of the config file editor on the Templates page.
 *
 * views/epm_templates_files.page.php defines epmFilesText. The commands go to
 * SettingsPage (view=settings, poce_*). File and family names are data from
 * the brand packages, so this script adds them with text().
 */
"use strict";

var epmFiles = (function () {
	var text = (typeof epmFilesText === 'object') ? epmFilesText : {};
	var editor = null;
	var family = null;
	var file = null;

	function call(command, data) {
		return $.ajax({
			type: 'POST',
			url: 'ajax.php',
			data: $.extend({ module: 'endpointman', view: 'settings', command: command }, data || {}),
			dataType: 'json',
			timeout: 60000
		}).fail(function () { fpbxToast(text.error, '', 'error'); });
	}

	function setButtons() {
		var kind = file ? file.type : '';
		$('#epm-file-save').prop('disabled', !(kind === 'file' || kind === 'sql'));
		$('#epm-file-delete').prop('disabled', kind !== 'sql');
		$('#epm-file-save-as, #epm-save-as-name').prop('disabled', !(kind === 'file' || kind === 'sql'));
		$('#epm-file-full').prop('disabled', !file);
		if (editor) { editor.setOption('readOnly', !(kind === 'file' || kind === 'sql')); }
	}

	function clearFile(message) {
		file = null;
		$('#epm-file-path').text(message || text.select_file);
		$('#epm-save-as-name').val('');
		if (editor) { editor.setValue(''); }
		setButtons();
	}

	function fillMenu(kind, list) {
		var $menu = $('.epm-file-menu[data-kind=' + kind + ']').empty();
		var $button = $('.epm-files-pickers button[data-kind=' + kind + ']');
		list = $.isArray(list) ? list : [];
		$button.find('.epm-count').text(list.length);
		$button.prop('disabled', list.length === 0);
		$.each(list, function (i, item) {
			$menu.append($('<a class="dropdown-item" href="#">').text(item.text).on('click', function (e) {
				e.preventDefault();
				openFile(kind, item);
			}));
		});
	}

	function loadFamily(id) {
		call('poce_select', { product_select: id }).done(function (data) {
			if (!data || !data.status) {
				fpbxToast((data && data.message) || text.error, '', 'error');
				return;
			}
			family = data.product_select;
			$('#epm-file-family').text(data.product_select_info ? data.product_select_info.long_name : '');
			fillMenu('file', data.file_list);
			fillMenu('tfile', data.template_file_list);
			fillMenu('sql', data.sql_file_list);
		});
	}

	function openFile(kind, item) {
		call('poce_select_file', { product_select: family, file_id: item.value, file_name: item.text, type_file: kind }).done(function (data) {
			if (!data || !data.status) {
				clearFile();
				fpbxToast((data && data.message) || text.error, '', 'error');
				return;
			}
			file = data;
			$('#epm-file-path').text(data.location);
			$('#epm-save-as-name').val(data.save_as_name_value || '');
			editor.setValue(data.config_data || '');
			setButtons();
			if (kind === 'tfile') { fpbxToast(text.read_only, '', 'info'); }
		});
	}

	function fileFields() {
		return {
			type_file: file.type,
			sendid: file.sendidt,
			product_select: file.product_select,
			original_name: file.original_name,
			file_name: file.filename,
			config_text: editor.getValue()
		};
	}

	function bind() {
		editor = CodeMirror.fromTextArea(document.getElementById('config_textarea'), {
			lineNumbers: true,
			matchBrackets: true,
			readOnly: true,
			viewportMargin: Infinity,
			scrollbarStyle: 'simple',
			extraKeys: {
				'F11': function (cm) { cm.setOption('fullScreen', !cm.getOption('fullScreen')); },
				'Esc': function (cm) { if (cm.getOption('fullScreen')) { cm.setOption('fullScreen', false); } }
			}
		});

		call('poce_list_brands').done(function (data) {
			var $list = $('#epm-files-families').empty();
			var rows = (data && data.status && $.isArray(data.ldatos)) ? data.ldatos : [];
			if (rows.length === 0) {
				$list.append($('<span class="list-group-item text-muted">').text(text.no_families));
				return;
			}
			$.each(rows, function (i, row) {
				$list.append($('<a href="#" class="list-group-item list-group-item-action">').attr('title', row.name).text(row.name_mini).on('click', function (e) {
					e.preventDefault();
					$list.find('.active').removeClass('active');
					$(this).addClass('active');
					clearFile();
					loadFamily(row.id);
				}));
			});
		});

		$('#epm-file-save').on('click', function () {
			if (!file) { return; }
			if (file.type === 'file' && !confirm(text.confirm_save)) { return; }
			call('poce_save_file', $.extend(fileFields(), { save_as_name: $('#epm-save-as-name').val() })).done(function (data) {
				fpbxToast(data.message, '', data.status ? 'success' : 'error');
				if (data.status) { loadFamily(family); }
			});
		});

		$('#epm-file-save-as').on('click', function () {
			if (!file) { return; }
			var name = $.trim($('#epm-save-as-name').val());
			if (name === '') { alert(text.name_required); return; }
			call('poce_save_as_file', $.extend(fileFields(), { save_as_name: name })).done(function (data) {
				fpbxToast(data.message, '', data.status ? 'success' : 'error');
				if (data.status) {
					file.type = data.type_file || 'sql';
					file.sendidt = data.sendidt;
					$('#epm-file-path').text(data.location);
					setButtons();
					loadFamily(family);
				}
			});
		});

		$('#epm-file-delete').on('click', function () {
			if (!file || !confirm(text.confirm_delete)) { return; }
			call('poce_delete_config_custom', { type_file: file.type, product_select: file.product_select, sql_select: file.sendidt }).done(function (data) {
				fpbxToast(data.message, '', data.status ? 'success' : 'error');
				if (data.status) { clearFile(); loadFamily(family); }
			});
		});

		$('#epm-file-full').on('click', function () {
			editor.setOption('fullScreen', !editor.getOption('fullScreen'));
		});
	}

	/*
	 * FreePBX loads codemirror.min.js in the page footer, after the page
	 * scripts. The add-ons need CodeMirror, so load them when the page is
	 * ready, one after the other.
	 */
	function loadAddons(done) {
		var base = 'modules/endpointman/assets/js/';
		var list = ['addon/simplescrollbars.js', 'addon/fullscreen.js', 'mode/xml.js'];
		(function next(i) {
			if (i >= list.length) { done(); return; }
			$.ajax({ url: base + list[i], dataType: 'script', cache: true }).always(function () { next(i + 1); });
		})(0);
	}

	return { init: function () { if ($('#epm-files-families').length) { loadAddons(bind); } } };
})();

$(function () { epmFiles.init(); });
