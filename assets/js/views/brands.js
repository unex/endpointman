/*
 * The script of the Brands page.
 *
 * views/packages.php defines epmBrandsText with the translated strings. The
 * brand list comes from PackagesPage (view=packages, list_all_brand). Brand
 * names, family names and model names are data from the brand packages, so
 * this script adds them with text(), never as HTML.
 */
"use strict";

var epmBrands = (function () {
	var text = (typeof epmBrandsText === 'object') ? epmBrandsText : {};
	var brands = [];

	function fmt(s) {
		var args = Array.prototype.slice.call(arguments, 1);
		var n = 0;
		return String(s).replace(/%(\d+)\$s|%s/g, function (m, pos) {
			return String(pos ? args[pos - 1] : args[n++]);
		});
	}

	function date(iso) {
		return iso ? String(iso).substr(0, 10) : '';
	}

	function taskText() {
		return { close: text.close, running: text.running, done: text.done, failed: text.failed };
	}

	function runTask(title, url, data) {
		epmTask({ title: title, url: url, data: data || null, text: taskText() });
	}

	function save(fields, onDone) {
		$.post('ajax.php', $.extend({ module: 'endpointman', view: 'packages', command: 'saveconfig' }, fields), null, 'json')
			.done(function (data) {
				if (data && data.status) {
					fpbxToast(text.saved, '', 'success');
					if (onDone) { onDone(); }
				} else {
					fpbxToast((data && data.message) ? data.message : text.error, '', 'error');
				}
			})
			.fail(function () { fpbxToast(text.error, '', 'error'); });
	}

	function setHidden(type, id, hidden) {
		save({ typesavecfg: 'hidden', idtype: type, idbt: id, value: hidden ? 1 : 0 }, load);
	}

	function setEnabled(id, enabled, $box) {
		save({ typesavecfg: 'enabled', idtype: 'modelo', idbt: id, value: enabled ? 1 : 0 }, function () {
			$.each(brands, function (i, b) {
				$.each(b.products || [], function (j, p) {
					$.each(p.models || [], function (k, m) {
						if (String(m.id) === String(id)) { m.enabled = enabled ? 1 : 0; }
					});
				});
			});
			updateCounts();
		});
	}

	function brandCounts(brand) {
		var on = 0, all = 0;
		$.each(brand.products || [], function (j, p) {
			$.each(p.models || [], function (k, m) {
				if (m.hidden == 1) { return; }
				all++;
				if (m.enabled == 1) { on++; }
			});
		});
		return { on: on, all: all };
	}

	function updateCounts() {
		$.each(brands, function (i, b) {
			var c = brandCounts(b);
			$('#epm-brand-' + b.id + ' .epm-model-count').text(fmt(text.enabled_count, c.on, c.all));
		});
	}

	function button(label, cls) {
		return $('<button type="button" class="btn btn-sm">').addClass(cls || 'btn-default').text(label);
	}

	function statusCell(b) {
		if (b.installed != 1) { return $('<span class="text-muted">').text(text.not_installed); }
		if (b.update == 1) { return $('<span class="badge badge-warning">').text(text.update_available); }
		return $('<span class="badge badge-success">').text(text.installed);
	}

	function brandRow(b, showHidden) {
		var $tr = $('<tr class="epm-brand-row">').attr('id', 'epm-brand-' + b.id);
		var $name = $('<td>');
		if (b.installed == 1 && b.hidden != 1) {
			$name.append($('<a href="#" class="epm-toggle-models">').attr('data-target', '#epm-models-' + b.id)
				.append($('<i class="fa fa-caret-right mr-1">')).append($('<strong>').text(b.name)));
			$name.append($('<div class="small text-muted epm-model-count">'));
		} else {
			$name.append($('<strong>').text(b.name));
		}
		if (b.hidden == 1) { $name.append(' ').append($('<span class="badge badge-secondary">').text(text.hidden)); }
		$tr.append($name);
		$tr.append($('<td>').append(statusCell(b)));
		$tr.append($('<td>').text(b.installed == 1 ? date(b.cfg_ver_datetime_txt) : ''));
		$tr.append($('<td>').text(date(b.update_vers_txt)));

		var $act = $('<td class="text-right text-nowrap">');
		if (b.installed != 1) {
			$act.append(button(text.install, 'btn-primary').on('click', function () {
				runTask(fmt(text.title_install, b.name), brandUrl('brand_install', b.id));
			}));
		} else {
			if (b.update == 1) {
				$act.append(button(text.update, 'btn-primary').on('click', function () {
					runTask(fmt(text.title_update, b.name), brandUrl('brand_update', b.id));
				}));
			}
			$act.append(' ').append(button(text.remove).on('click', function () {
				if (confirm(fmt(text.confirm_remove, b.name))) {
					runTask(fmt(text.title_remove, b.name), brandUrl('brand_uninstall', b.id));
				}
			}));
		}
		$act.append(' ').append(button(b.hidden == 1 ? text.show : text.hide).on('click', function () {
			setHidden('marca', b.id, b.hidden != 1);
		}));
		$tr.append($act);
		return $tr;
	}

	function modelsRow(b, showHidden) {
		var $cell = $('<td colspan="5" class="epm-models-cell">');
		$.each(b.products || [], function (j, p) {
			if (p.hidden == 1 && !showHidden) { return; }
			var $family = $('<div class="epm-family">').attr('data-family', p.id);
			var $head = $('<div class="epm-family-head d-flex flex-wrap align-items-center">');
			$head.append($('<strong class="mr-2">').text(p.long_name || p.short_name));
			if (p.hidden == 1) { $head.append($('<span class="badge badge-secondary mr-2">').text(text.hidden)); }
			var $links = $('<span class="ml-auto">');
			if (p.hidden != 1) {
				$links.append($('<a href="#" class="mr-3">').text(text.enable_all).on('click', function (e) { e.preventDefault(); setFamily($family, true); }));
				$links.append($('<a href="#" class="mr-3">').text(text.disable_all).on('click', function (e) { e.preventDefault(); setFamily($family, false); }));
			}
			if (p.fw_type === 'install') {
				$links.append(button(text.install_fw).addClass('mr-2').on('click', function () {
					runTask(fmt(text.title_fw, p.long_name), firmwareUrl('fw_install', p.id));
				}));
			} else if (p.fw_type === 'remove') {
				$links.append(button(text.remove_fw).addClass('mr-2').on('click', function () {
					if (confirm(fmt(text.confirm_remove_fw, p.long_name))) {
						runTask(fmt(text.title_fw, p.long_name), firmwareUrl('fw_uninstall', p.id));
					}
				}));
			}
			$links.append($('<a href="#">').text(p.hidden == 1 ? text.show : text.hide).on('click', function (e) {
				e.preventDefault();
				setHidden('producto', p.id, p.hidden != 1);
			}));
			$head.append($links);
			$family.append($head);

			var $grid = $('<div class="epm-model-grid">');
			$.each(p.models || [], function (k, m) {
				if (m.hidden == 1 && !showHidden) { return; }
				var id = 'epm-model-' + m.id;
				var $item = $('<div class="form-check epm-model">').attr('data-name', String(m.model).toLowerCase());
				var $box = $('<input type="checkbox" class="form-check-input">').attr('id', id).prop('checked', m.enabled == 1)
					.prop('disabled', m.hidden == 1)
					.on('change', function () { setEnabled(m.id, this.checked, $(this)); });
				$item.append($box).append($('<label class="form-check-label">').attr('for', id).text(m.model));
				if (showHidden) {
					$item.append(' ').append($('<a href="#" class="small">').text(m.hidden == 1 ? text.show : text.hide).on('click', function (e) {
						e.preventDefault();
						setHidden('modelo', m.id, m.hidden != 1);
					}));
				}
				$grid.append($item);
			});
			$family.append($grid);
			$cell.append($family);
		});
		return $('<tr class="epm-models-row">').attr('id', 'epm-models-' + b.id).append($cell);
	}

	function setFamily($family, enabled) {
		$family.find('.epm-model input[type=checkbox]:not(:disabled)').each(function () {
			if (this.checked !== enabled) {
				this.checked = enabled;
				$(this).trigger('change');
			}
		});
	}

	function brandUrl(sub, id) {
		return 'config.php?display=endpointman&view=packages&command=brand&command_sub=' + sub + '&idfw=' + encodeURIComponent(id);
	}

	function firmwareUrl(sub, id) {
		return 'config.php?display=endpointman&view=packages&command=firmware&command_sub=' + sub + '&idfw=' + encodeURIComponent(id);
	}

	function render() {
		var showHidden = $('#epm-show-hidden').prop('checked');
		var open = {};
		$('.epm-models-row:visible').each(function () { open[this.id] = true; });
		var $list = $('#epm-brand-list').empty();
		var shown = 0;
		$.each(brands, function (i, b) {
			if (b.hidden == 1 && !showHidden) { return; }
			shown++;
			$list.append(brandRow(b, showHidden));
			if (b.installed == 1 && b.hidden != 1) {
				var $models = modelsRow(b, showHidden);
				if (!open[$models.attr('id')]) { $models.hide(); } else { $list.find('#epm-brand-' + b.id + ' .fa-caret-right').toggleClass('fa-caret-right fa-caret-down'); }
				$list.append($models);
			}
		});
		if (shown === 0) {
			$list.append($('<tr>').append($('<td colspan="5" class="text-muted">').text(text.no_data)));
		}
		updateCounts();
		filterModels();
	}

	function load() {
		$.getJSON('ajax.php', { module: 'endpointman', view: 'packages', command: 'list_all_brand', _: Date.now() })
			.done(function (data) {
				brands = (data && data.status && $.isArray(data.datlist)) ? data.datlist : [];
				render();
			})
			.fail(function () {
				$('#epm-brand-list').empty().append($('<tr>').append($('<td colspan="5" class="text-danger">').text(text.error)));
			});
	}

	function filterModels() {
		var q = String($('#epm-model-search').val() || '').trim().toLowerCase();
		if (q === '') {
			$('.epm-model, .epm-family').show();
			return;
		}
		$('.epm-models-row').each(function () {
			var $row = $(this);
			var hits = 0;
			$row.find('.epm-family').each(function () {
				var $f = $(this);
				var n = 0;
				$f.find('.epm-model').each(function () {
					var hit = String($(this).data('name')).indexOf(q) !== -1;
					$(this).toggle(hit);
					if (hit) { n++; }
				});
				$f.toggle(n > 0);
				hits += n;
			});
			if (hits > 0) {
				$row.show();
				$row.prev('.epm-brand-row').find('.fa-caret-right').toggleClass('fa-caret-right fa-caret-down');
			}
		});
	}

	function bindBrands() {
		$('#epm-brand-list').on('click', '.epm-toggle-models', function (e) {
			e.preventDefault();
			$($(this).data('target')).toggle();
			$(this).find('.fa').toggleClass('fa-caret-right fa-caret-down');
		});
		$('#epm-show-hidden').on('change', render);
		$('#epm-model-search').on('input', filterModels);
		$('#epm-check-updates').on('click', function () {
			runTask(text.check, 'config.php?display=endpointman&view=packages&command=check_for_updates');
		});
		load();
	}

	function bindOui() {
		$('#epm-oui-form').on('submit', function (e) {
			e.preventDefault();
			var oui = String($('#number_new_oui').val()).replace(/[\s:\-]/g, '').toUpperCase();
			if (!/^[0-9A-F]{6}$/.test(oui)) { alert(text.oui_invalid); return; }
			$.post('ajax.php', { module: 'endpointman', view: 'settings', command: 'oui_add', number_new_oui: oui, brand_new_oui: $('#brand_new_oui').val() }, null, 'json')
				.done(function (data) {
					if (data && data.status) {
						fpbxToast(text.saved, '', 'success');
						$('#epm-oui-add').modal('hide');
						$('#epm-oui-form')[0].reset();
						$('#epm-oui').bootstrapTable('refresh');
					} else {
						fpbxToast((data && data.message) ? data.message : text.error, '', 'error');
					}
				})
				.fail(function () { fpbxToast(text.error, '', 'error'); });
		});
		$('#epm-oui').on('click', '.epm-oui-delete', function (e) {
			e.preventDefault();
			var id = $(this).data('id');
			if (!confirm(fmt(text.confirm_oui, $(this).data('oui')))) { return; }
			$.post('ajax.php', { module: 'endpointman', view: 'settings', command: 'oui_del', id_del: id }, null, 'json')
				.done(function (data) {
					if (data && data.status) {
						fpbxToast(text.saved, '', 'success');
						$('#epm-oui').bootstrapTable('refresh');
					} else {
						fpbxToast((data && data.message) ? data.message : text.error, '', 'error');
					}
				});
		});
	}

	function loadExports() {
		$.getJSON('ajax.php', { module: 'endpointman', view: 'settings', command: 'list_files_brands_export', _: Date.now() })
			.done(function (data) {
				var $list = $('#epm-export-list').empty();
				var files = (data && data.status && $.isArray(data.list_files)) ? data.list_files : [];
				if (files.length === 0) {
					$list.append($('<tr>').append($('<td colspan="3" class="text-muted">').text(text.no_exports)));
					return;
				}
				$.each(files, function (i, f) {
					var url = 'config.php?display=endpointman&view=settings&command=export_brands_availables_file&file_package=' + encodeURIComponent(f.file);
					$list.append($('<tr>')
						.append($('<td>').append($('<a>').attr('href', url).text(f.file)))
						.append($('<td>').text(f.brand))
						.append($('<td>').text(String(f.timestamp).replace(/[\[\]]/g, ''))));
				});
			});
	}

	function bindFiles() {
		$('#epm-upload-form').on('submit', function (e) {
			e.preventDefault();
			if (!$('#epm-upload-file').val()) { alert(text.select_file); return; }
			runTask(text.title_upload, 'config.php?display=endpointman&view=settings&command=upload_brand', new FormData(this));
		});
		$('#epm-export-form').on('submit', function (e) {
			e.preventDefault();
			var id = $('#epm-export-brand').val();
			if (!id) { alert(text.select_brand); return; }
			epmTask({
				title: fmt(text.title_export, $('#epm-export-brand option:selected').text()),
				url: 'config.php?display=endpointman&view=settings&command=export_brands_availables&package=' + encodeURIComponent(id),
				text: taskText(),
				reload: false
			});
			$(document).one('hidden.bs.modal', '.epm-task', loadExports);
		});
		loadExports();
	}

	return {
		init: function () {
			if ($('#epm-brand-list').length) { bindBrands(); }
			if ($('#epm-oui').length) { bindOui(); }
			if ($('#epm-export-list').length) { bindFiles(); }
		},
		ouiFormatter: function (value) {
			return '<span class="text-monospace">' + epmEsc(value) + '</span>';
		},
		ouiTypeFormatter: function (value) {
			return value == 1 ? epmEsc(text.custom) : '<span class="text-muted">' + epmEsc(text.required) + '</span>';
		},
		ouiActionFormatter: function (value, row) {
			if (row.custom != 1) { return ''; }
			return '<a href="#" class="epm-oui-delete" data-id="' + epmEsc(value) + '" data-oui="' + epmEsc(row.oui) + '" title="' + epmEsc(text.remove) + '"><i class="fa fa-trash"></i></a>';
		}
	};
})();

function epmEsc(s) {
	return String(s).replace(/[&<>"']/g, function (c) {
		return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
	});
}

$(function () { epmBrands.init(); });
