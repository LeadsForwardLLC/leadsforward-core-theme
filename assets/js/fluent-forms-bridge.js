/* Fluent Forms quote takeover — opens from existing [data-lf-quote-trigger] CTAs. */
(function () {
	'use strict';

	function init() {
		var modal = document.getElementById('lf-fluent-quote');
		if (!modal) {
			return;
		}

		var body = document.body;
		var dialog = modal.querySelector('.lf-fluent-quote-modal__dialog');
		var formWrap = modal.querySelector('.lf-fluent-quote-modal__form');
		var isOpen = false;
		var lastFocus = null;

		function openModal() {
			if (isOpen) {
				return;
			}
			isOpen = true;
			lastFocus = document.activeElement;
			modal.classList.add('is-open');
			body.classList.add('lf-fluent-quote-open');
			body.classList.add('lf-quote-open');
			modal.setAttribute('aria-hidden', 'false');
			setTimeout(function () {
				var firstField = formWrap ? formWrap.querySelector('input:not([type="hidden"]), select, textarea, button') : null;
				if (firstField && typeof firstField.focus === 'function') {
					try { firstField.focus(); } catch (err) {}
				} else if (dialog) {
					dialog.focus();
				}
			}, 30);
			document.addEventListener('keydown', onKeyDown);
		}

		function closeModal() {
			if (!isOpen) {
				return;
			}
			isOpen = false;
			modal.classList.remove('is-open');
			body.classList.remove('lf-fluent-quote-open');
			body.classList.remove('lf-quote-open');
			modal.setAttribute('aria-hidden', 'true');
			document.removeEventListener('keydown', onKeyDown);
			if (lastFocus && typeof lastFocus.focus === 'function') {
				lastFocus.focus();
			}
		}

		function onKeyDown(e) {
			if (e.key === 'Escape') {
				closeModal();
			}
		}

		document.addEventListener('click', function (e) {
			var closeEl = e.target.closest('[data-lf-fluent-quote-close]');
			if (closeEl) {
				e.preventDefault();
				closeModal();
				return;
			}

			var trigger = e.target.closest('[data-lf-quote-trigger]');
			if (!trigger) {
				return;
			}
			if (modal.contains(e.target)) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			openModal();
		}, true);

		window.lfFluentQuoteOpen = openModal;
		window.lfFluentQuoteClose = closeModal;
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
