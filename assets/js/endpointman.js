"use strict";

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
