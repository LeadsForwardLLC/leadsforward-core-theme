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
	var hasCompleted = false;
	var isSubmitting = false;
	var stepStart = {};
	var context = (window.lfQuoteBuilder && window.lfQuoteBuilder.context) ? window.lfQuoteBuilder.context : {};
	var sessionKey = 'lf_qb_session';
	var sessionId = (window.sessionStorage && window.sessionStorage.getItem(sessionKey)) || '';
	var returningKey = 'lf_qb_returning';
	var lastOpenKey = 'lf_qb_last_open';
	var serviceTracked = false;
	if (!sessionId) {
		sessionId = 'qb_' + Date.now() + '_' + Math.floor(Math.random() * 10000);
		if (window.sessionStorage) {
			window.sessionStorage.setItem(sessionKey, sessionId);
		}
	}

	function getDeviceType() {
		var width = window.innerWidth || document.documentElement.clientWidth || 1024;
		if (width <= 767) return 'mobile';
		if (width <= 1024) return 'tablet';
		return 'desktop';
	}

	function setHiddenValue(name, value) {
		var input = form ? form.querySelector('input[name="lf_quote[' + name + ']"]') : null;
		if (input) input.value = value;
	}

	function generateSubmissionId() {
		return 'sub_' + Date.now() + '_' + Math.floor(Math.random() * 100000);
	}

	function trackEvent(type, payload) {
		if (!window.lfQuoteBuilder || !window.lfQuoteBuilder.ajax_url) return;
		var data = new FormData();
		data.append('action', 'lf_quote_builder_event');
		data.append('nonce', window.lfQuoteBuilder.nonce || '');
		data.append('event', type);
		data.append('context', context.page_context || '');
		data.append('niche', context.niche || '');
		data.append('variant', context.form_variant || '');
		data.append('session_id', sessionId);
		data.append('device', getDeviceType());
		var returning = (window.sessionStorage && window.sessionStorage.getItem(returningKey)) === '1' ? '1' : '0';
		data.append('returning', returning);
		if (payload && payload.step_id) data.append('step_id', payload.step_id);
		if (payload && payload.duration) data.append('duration', payload.duration);
		if (payload && payload.meta_key) data.append('meta_key', payload.meta_key);
		if (payload && payload.meta_value) data.append('meta_value', payload.meta_value);
		fetch(window.lfQuoteBuilder.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			body: data,
			keepalive: true
		}).catch(function () {});
	}

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
		hasCompleted = false;
		isSubmitting = false;
		lastFocus = document.activeElement;
		modal.classList.add('is-open');
		body.classList.add('lf-quote-open');
		modal.setAttribute('aria-hidden', 'false');
		index = 0;
		var now = Date.now();
		var lastOpen = 0;
		if (window.localStorage) {
			lastOpen = parseInt(window.localStorage.getItem(lastOpenKey) || '0', 10) || 0;
			window.localStorage.setItem(lastOpenKey, String(now));
		}
		var returning = lastOpen > 0 && (now - lastOpen) > (30 * 60 * 1000);
		if (window.sessionStorage) {
			window.sessionStorage.setItem(returningKey, returning ? '1' : '0');
		}
		setHiddenValue('returning', returning ? '1' : '0');
		setHiddenValue('device', getDeviceType());
		setHiddenValue('submission_id', generateSubmissionId());
		trackEvent('open');
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
		if (!hasCompleted) {
			var current = steps[index];
			var stepId = current ? current.getAttribute('data-step-id') : '';
			trackEvent('abandon', { step_id: stepId });
		}
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
		var stepId = current ? current.getAttribute('data-step-id') : '';
		if (current && !isConfirm) {
			stepStart[stepId] = Date.now();
			trackEvent('step_view', { step_id: stepId });
		}
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
			var stepId = current.getAttribute('data-step-id') || '';
			trackEvent('validation_error', { step_id: stepId, meta_key: 'required', meta_value: '1' });
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
			if (isSubmitting) {
				return;
			}
			isSubmitting = true;
			if (nextBtn) nextBtn.disabled = true;
			if (!window.lfQuoteBuilder || !window.lfQuoteBuilder.ajax_url) {
				isSubmitting = false;
				if (nextBtn) nextBtn.disabled = false;
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
				isSubmitting = false;
				if (nextBtn) nextBtn.disabled = false;
				if (json && json.success) {
					hasCompleted = true;
					resolve();
				} else {
					reject((json && json.data && json.data.message) ? json.data.message : 'Submission failed.');
				}
			}).catch(function () {
				isSubmitting = false;
				if (nextBtn) nextBtn.disabled = false;
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
		var stepId = current.getAttribute('data-step-id') || '';
		var startedAt = stepStart[stepId] || Date.now();
		var duration = Date.now() - startedAt;
		trackEvent('step_complete', { step_id: stepId, duration: duration });
		var bucket = duration <= 5000 ? '0-5s' : (duration <= 15000 ? '5-15s' : (duration <= 30000 ? '15-30s' : '30s+'));
		trackEvent('step_time_bucket', { step_id: stepId, meta_key: 'bucket', meta_value: bucket });
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
				if (!serviceTracked && input.name && input.name.indexOf('[service_type]') !== -1) {
					serviceTracked = true;
					trackEvent('service_select', { meta_key: 'service', meta_value: input.value || '' });
				}
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
