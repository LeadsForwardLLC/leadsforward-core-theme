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
		var actions = null;
		var observer = null;

		function ensureActionsBar() {
			if (actions && actions.parentNode) {
				return actions;
			}
			actions = modal.querySelector('.lf-fluent-quote-modal__actions');
			if (!actions && dialog) {
				actions = document.createElement('div');
				actions.className = 'lf-fluent-quote-modal__actions';
				dialog.appendChild(actions);
			}
			return actions;
		}

		function isNavButton(el) {
			if (!el) {
				return false;
			}
			if (el.tagName !== 'BUTTON' && el.tagName !== 'INPUT' && el.tagName !== 'A') {
				if (el.closest) {
					el = el.closest('button, input[type="submit"], input[type="button"], a.ff-btn');
				}
			}
			if (!el) {
				return false;
			}
			var cls = (el.className && String(el.className)) || '';
			if (cls.indexOf('ff-btn-next') !== -1 || cls.indexOf('ff-btn-prev') !== -1 || cls.indexOf('ff-btn-submit') !== -1) {
				return true;
			}
			if (el.getAttribute && el.getAttribute('type') === 'submit') {
				return true;
			}
			return false;
		}

		function collectNavButtons(root) {
			if (!root) {
				return [];
			}
			var found = [];
			var nodes = root.querySelectorAll('button, input[type="submit"], input[type="button"], a.ff-btn');
			for (var i = 0; i < nodes.length; i++) {
				if (isNavButton(nodes[i])) {
					found.push(nodes[i]);
				}
			}
			return found;
		}

		function syncActionBar() {
			var bar = ensureActionsBar();
			if (!bar || !formWrap) {
				return;
			}
			var buttons = collectNavButtons(formWrap);
			if (!buttons.length) {
				// Keep existing pinned buttons if Fluent temporarily unmounts step chrome.
				return;
			}
			// Prefer prev then next/submit order.
			buttons.sort(function (a, b) {
				var score = function (el) {
					var c = String(el.className || '');
					if (c.indexOf('ff-btn-prev') !== -1) return 0;
					if (c.indexOf('ff-btn-next') !== -1) return 1;
					if (c.indexOf('ff-btn-submit') !== -1) return 2;
					return 3;
				};
				return score(a) - score(b);
			});
			bar.innerHTML = '';
			buttons.forEach(function (btn) {
				// Hide original in-form control but keep it for Fluent handlers if needed.
				// Prefer moving the live node so click handlers stay attached (Safari-safe).
				btn.style.pointerEvents = 'auto';
				bar.appendChild(btn);
			});
		}

		function startObserving() {
			if (!formWrap || typeof MutationObserver === 'undefined') {
				syncActionBar();
				return;
			}
			if (observer) {
				observer.disconnect();
			}
			observer = new MutationObserver(function () {
				window.requestAnimationFrame(syncActionBar);
			});
			observer.observe(formWrap, { childList: true, subtree: true });
			syncActionBar();
		}

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
			startObserving();
			setTimeout(function () {
				syncActionBar();
				var firstField = formWrap ? formWrap.querySelector('input, select, textarea, button') : null;
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
			if (observer) {
				observer.disconnect();
				observer = null;
			}
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
			// Never hijack clicks inside the Fluent modal itself.
			if (modal.contains(e.target)) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			openModal();
		}, true);

		// After Fluent step changes, keep action bar in sync.
		document.addEventListener('click', function (e) {
			if (!isOpen || !modal.contains(e.target)) {
				return;
			}
			if (isNavButton(e.target) || e.target.closest('.ff-el-form-check')) {
				window.setTimeout(syncActionBar, 50);
				window.setTimeout(syncActionBar, 250);
			}
		});

		window.lfFluentQuoteOpen = openModal;
		window.lfFluentQuoteClose = closeModal;
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
