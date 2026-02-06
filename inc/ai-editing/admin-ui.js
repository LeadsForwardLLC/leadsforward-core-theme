/**
 * Edit with AI: generate suggestions, show diff, apply or reject. Rollback.
 * Admin-only; no API keys. All AI calls via server.
 */
(function ($) {
	'use strict';

	var $container, contextType, contextId, currentProposed, currentCurrent, currentLabels, promptSnippet;

	function init() {
		$container = $('.lf-ai-editing');
		if (!$container.length) return;
		contextType = $container.data('context-type');
		contextId = $container.data('context-id');
		$('#lf-ai-submit').on('click', onGenerate);
		$('#lf-ai-apply').on('click', onApply);
		$('#lf-ai-reject').on('click', onReject);
		$container.on('click', '.lf-ai-rollback', onRollback);
	}

	function setStatus(msg, isError) {
		var $s = $('#lf-ai-status');
		$s.removeClass('error').text(msg);
		if (isError) $s.addClass('error');
	}

	function onGenerate() {
		var prompt = $('#lf-ai-prompt').val().trim();
		if (!prompt) {
			setStatus('Please enter a prompt.', true);
			return;
		}
		promptSnippet = prompt.length > 80 ? prompt.slice(0, 77) + '...' : prompt;
		setStatus('Generating…');
		$('#lf-ai-diff').hide();
		$.post(lfAiEditing.ajax_url, {
			action: 'lf_ai_generate',
			nonce: lfAiEditing.nonce,
			context_type: contextType,
			context_id: contextId,
			prompt: prompt
		})
			.done(function (res) {
				if (res.success && res.data && res.data.proposed) {
					currentProposed = res.data.proposed;
					currentCurrent = res.data.current || {};
					currentLabels = res.data.labels || {};
					renderDiff();
					$('#lf-ai-diff').show();
					setStatus('');
				} else {
					setStatus(res.data && res.data.message ? res.data.message : 'No suggestions returned.', true);
				}
			})
			.fail(function (xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : 'Request failed.';
				setStatus(msg, true);
			});
	}

	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	function renderDiff() {
		var $table = $('#lf-ai-diff-table').empty();
		$table.append('<thead><tr><th>Field</th><th>Current</th><th>Suggested</th></tr></thead><tbody></tbody>');
		var $tbody = $table.find('tbody');
		$.each(currentProposed, function (key, newVal) {
			var label = currentLabels[key] || key;
			var oldVal = currentCurrent[key] !== undefined ? currentCurrent[key] : '';
			var oldShort = String(oldVal).length > 200 ? String(oldVal).slice(0, 197) + '…' : oldVal;
			var newShort = String(newVal).length > 200 ? String(newVal).slice(0, 197) + '…' : newVal;
			$tbody.append(
				'<tr><td>' + escapeHtml(label) + '</td><td><pre class="lf-ai-old">' + escapeHtml(oldShort) + '</pre></td><td><pre class="lf-ai-new">' + escapeHtml(newShort) + '</pre></td></tr>'
			);
		});
	}

	function onApply() {
		if (!currentProposed || !Object.keys(currentProposed).length) {
			setStatus('No suggestions to apply.', true);
			return;
		}
		setStatus('Applying…');
		$.post(lfAiEditing.ajax_url, {
			action: 'lf_ai_apply',
			nonce: lfAiEditing.nonce,
			context_type: contextType,
			context_id: contextId,
			prompt_snippet: promptSnippet || ''
		})
			.done(function (res) {
				if (res.success && res.data && res.data.reload) {
					window.location.reload();
				} else {
					setStatus(res.data && res.data.message ? res.data.message : 'Apply failed.', true);
				}
			})
			.fail(function (xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : 'Apply failed.';
				setStatus(msg, true);
			});
	}

	function onReject() {
		$('#lf-ai-diff').hide();
		currentProposed = null;
		currentCurrent = null;
		currentLabels = null;
		setStatus('Suggestions rejected.');
	}

	function onRollback(e) {
		var id = $(e.target).data('id');
		if (!id) return;
		if (!confirm('Restore content to the state before this AI edit?')) return;
		$.post(lfAiEditing.ajax_url, {
			action: 'lf_ai_rollback',
			nonce: lfAiEditing.nonce,
			id: id
		})
			.done(function (res) {
				if (res.success && res.data && res.data.reload) {
					window.location.reload();
				}
			})
			.fail(function () {
				alert('Rollback failed.');
			});
	}

	$(document).ready(init);
})(jQuery);
