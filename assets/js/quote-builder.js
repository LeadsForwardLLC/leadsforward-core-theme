/* LeadsForward Quote Builder: modal + multi-step flow */
(function () {
	'use strict';

	var modal = document.getElementById('lf-quote-builder');
	if (!modal) return;

	var body = document.body;
	var dialog = modal.querySelector('.lf-quote-modal__dialog');
	var form = modal.querySelector('.lf-quote-form');
	var steps = Array.prototype.slice.call(modal.querySelectorAll('.lf-quote-step'));
	var backBtn = modal.querySelector('[data-lf-quote-back]');
	var nextBtn = modal.querySelector('[data-lf-quote-next]');
	var status = modal.querySelector('.lf-quote-modal__status');
	var progressLabel = modal.querySelector('#lf-quote-step-label');
	var progressFill = modal.querySelector('.lf-quote-modal__bar-fill');
	var index = 0;
	var lastFocus = null;
	var isOpen = false;

	function setStatus(msg, isError) {
		if (!status) return;
		status.textContent = msg || '';
		status.classList.toggle('is-error', !!isError);
	}

	function getFocusable() {
		return dialog.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])');
	}

	function trapFocus(e) {
		if (e.key !== 'Tab') return;
		var focusable = getFocusable();
		if (!focusable.length) return;
		var first = focusable[0];
		var last = focusable[focusable.length - 1];
		if (e.shiftKey && document.activeElement === first) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	}

	function openModal() {
		if (isOpen) return;
		isOpen = true;
		lastFocus = document.activeElement;
		modal.classList.add('is-open');
		body.classList.add('lf-quote-open');
		modal.setAttribute('aria-hidden', 'false');
		index = 0;
		updateStep();
		setTimeout(function () {
			if (dialog) dialog.focus();
		}, 10);
		document.addEventListener('keydown', onKeyDown);
	}

	function closeModal() {
		if (!isOpen) return;
		isOpen = false;
		modal.classList.remove('is-open');
		body.classList.remove('lf-quote-open');
		modal.setAttribute('aria-hidden', 'true');
		setStatus('');
		if (lastFocus && lastFocus.focus) lastFocus.focus();
		document.removeEventListener('keydown', onKeyDown);
	}

	function onKeyDown(e) {
		if (!isOpen) return;
		if (e.key === 'Escape') {
			e.preventDefault();
			closeModal();
			return;
		}
		trapFocus(e);
	}

	function updateStep() {
		steps.forEach(function (step, i) {
			step.classList.toggle('is-active', i === index);
		});
		var total = steps.length;
		if (progressLabel) {
			progressLabel.textContent = 'Step ' + (index + 1) + ' of ' + total;
		}
		if (progressFill) {
			progressFill.style.width = ((index + 1) / total * 100) + '%';
		}
		if (backBtn) {
			backBtn.disabled = index === 0;
			backBtn.style.visibility = index === 0 ? 'hidden' : 'visible';
		}
		var current = steps[index];
		var isConfirm = current && current.getAttribute('data-step-type') === 'confirmation';
		if (nextBtn) {
			if (isConfirm) {
				nextBtn.textContent = 'Close';
			} else if (index === total - 2) {
				nextBtn.textContent = 'Submit request';
			} else {
				nextBtn.textContent = 'Continue';
			}
		}
		setStatus('');
	}

	function validateStep() {
		var current = steps[index];
		if (!current) return true;
		var required = current.querySelectorAll('[required]');
		var ok = true;
		required.forEach(function (field) {
			field.classList.remove('is-invalid');
			field.setAttribute('aria-invalid', 'false');
			if ((field.type === 'radio')) {
				var group = current.querySelectorAll('input[name="' + field.name + '"]');
				var checked = false;
				group.forEach(function (r) { if (r.checked) checked = true; });
				if (!checked) ok = false;
			} else if (!field.value || String(field.value).trim() === '') {
				ok = false;
			}
		});
		if (!ok) {
			setStatus('Please complete the required fields to continue.', true);
			required.forEach(function (field) {
				if (field.type !== 'radio') {
					field.classList.add('is-invalid');
					field.setAttribute('aria-invalid', 'true');
				}
			});
		}
		return ok;
	}

	function submitQuote() {
		return new Promise(function (resolve, reject) {
			if (!window.lfQuoteBuilder || !window.lfQuoteBuilder.ajax_url) {
				reject('Submission is not available.');
				return;
			}
			var data = new FormData(form);
			data.append('action', 'lf_quote_builder_submit');
			data.append('nonce', window.lfQuoteBuilder.nonce || '');
			setStatus('Submitting…');
			fetch(window.lfQuoteBuilder.ajax_url, {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			}).then(function (res) {
				return res.json();
			}).then(function (json) {
				if (json && json.success) {
					resolve();
				} else {
					reject((json && json.data && json.data.message) ? json.data.message : 'Submission failed.');
				}
			}).catch(function () {
				reject('Submission failed.');
			});
		});
	}

	function onNext() {
		var current = steps[index];
		if (!current) return;
		var isConfirm = current.getAttribute('data-step-type') === 'confirmation';
		if (isConfirm) {
			closeModal();
			return;
		}
		if (!validateStep()) return;
		var next = steps[index + 1];
		var nextIsConfirm = next && next.getAttribute('data-step-type') === 'confirmation';
		if (nextIsConfirm) {
			submitQuote().then(function () {
				index += 1;
				updateStep();
			}).catch(function (message) {
				setStatus(message, true);
			});
			return;
		}
		index += 1;
		updateStep();
	}

	function onBack() {
		if (index === 0) return;
		index -= 1;
		updateStep();
	}

	function bindEvents() {
		document.addEventListener('click', function (e) {
			var trigger = e.target.closest('[data-lf-quote-trigger]');
			if (trigger) {
				e.preventDefault();
				openModal();
			}
			var close = e.target.closest('[data-lf-quote-close]');
			if (close) {
				e.preventDefault();
				closeModal();
			}
		});
		if (backBtn) backBtn.addEventListener('click', onBack);
		if (nextBtn) nextBtn.addEventListener('click', onNext);
		if (form) form.addEventListener('submit', function (e) { e.preventDefault(); });
		document.querySelectorAll('.lf-quote-choice__card input[type="radio"]').forEach(function (input) {
			input.addEventListener('change', function () {
				var parent = input.closest('.lf-quote-choice');
				if (!parent) return;
				parent.querySelectorAll('.lf-quote-choice__card').forEach(function (card) {
					card.classList.toggle('is-selected', card.querySelector('input').checked);
				});
			});
		});
		document.querySelectorAll('.lf-quote-choice').forEach(function (group) {
			group.querySelectorAll('.lf-quote-choice__card').forEach(function (card) {
				var radio = card.querySelector('input');
				card.classList.toggle('is-selected', radio && radio.checked);
			});
		});
	}

	bindEvents();
})();
