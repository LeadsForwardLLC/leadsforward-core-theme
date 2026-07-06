/**
 * Header mega menu: search/filter, desktop flyouts, mobile category accordions.
 */
(function () {
	'use strict';

	function normalize(value) {
		return String(value || '')
			.toLowerCase()
			.replace(/\s+/g, ' ')
			.trim();
	}

	function setEmptyVisible(empty, show) {
		if (!empty) {
			return;
		}
		empty.hidden = !show;
		empty.classList.toggle('lf-mega-empty--visible', show);
	}

	function isDesktop() {
		try {
			return !!(window.matchMedia && window.matchMedia('(min-width: 901px)').matches);
		} catch (e) {
			return (window.innerWidth || 0) >= 901;
		}
	}

	function setCategoryExpanded(category, open) {
		category.classList.toggle('is-open', open);
		var catLink = category.querySelector(':scope > a.lf-menu-service-category__link, :scope > a');
		if (catLink) {
			catLink.setAttribute('aria-expanded', open ? 'true' : 'false');
		}
	}

	function cleanupMobileCategoryToggles(panel) {
		panel.querySelectorAll('.lf-menu-service-category > .site-header__category-toggle').forEach(function (btn) {
			btn.remove();
		});
	}

	function bindMobileCategoryAccordions(panel) {
		cleanupMobileCategoryToggles(panel);
		if (panel.dataset.lfMobileCatBound === '1') {
			return;
		}
		panel.dataset.lfMobileCatBound = '1';

		panel.addEventListener('click', function (event) {
			if (isDesktop()) {
				return;
			}
			if (event.target.closest('.lf-mega-tile a')) {
				return;
			}

			var category = event.target.closest('.lf-menu-service-category');
			if (!category || !panel.contains(category)) {
				return;
			}

			var onLink = event.target.closest('a.lf-menu-service-category__link, .lf-menu-service-category > a');
			if (!onLink) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();

			var wasOpen = category.classList.contains('is-open');
			panel.querySelectorAll(':scope > .lf-menu-service-category').forEach(function (peer) {
				if (peer !== category) {
					setCategoryExpanded(peer, false);
				}
			});
			setCategoryExpanded(category, !wasOpen);
		});
	}

	function bindCategoryFlyouts(panel) {
		var categories = panel.querySelectorAll(':scope > .lf-menu-service-category');
		if (!categories.length) {
			return;
		}

		var closeTimer = null;

		function closeAllFlyouts() {
			Array.prototype.forEach.call(categories, function (peer) {
				peer.classList.remove('is-flyout-open');
			});
		}

		function cancelFlyoutClose() {
			if (closeTimer) {
				window.clearTimeout(closeTimer);
				closeTimer = null;
			}
		}

		function scheduleFlyoutClose() {
			cancelFlyoutClose();
			closeTimer = window.setTimeout(closeAllFlyouts, 160);
		}

		function isWithinCategoryTree(category, sub, target) {
			if (!target) {
				return false;
			}
			return category.contains(target) || (sub && sub.contains(target));
		}

		categories.forEach(function (category) {
			if (category.dataset.lfMegaFlyoutBound === '1') {
				return;
			}
			category.dataset.lfMegaFlyoutBound = '1';

			var sub = category.querySelector(':scope > .sub-menu');

			category.addEventListener('mouseenter', function () {
				if (!isDesktop()) {
					return;
				}
				cancelFlyoutClose();
				Array.prototype.forEach.call(categories, function (peer) {
					if (peer !== category) {
						peer.classList.remove('is-flyout-open');
					}
				});
				category.classList.add('is-flyout-open');
			});

			category.addEventListener('mouseleave', function (event) {
				if (!isDesktop()) {
					return;
				}
				if (isWithinCategoryTree(category, sub, event.relatedTarget)) {
					return;
				}
				scheduleFlyoutClose();
			});

			if (sub) {
				sub.addEventListener('mouseenter', function () {
					if (!isDesktop()) {
						return;
					}
					cancelFlyoutClose();
					category.classList.add('is-flyout-open');
				});

				sub.addEventListener('mouseleave', function (event) {
					if (!isDesktop()) {
						return;
					}
					if (isWithinCategoryTree(category, sub, event.relatedTarget)) {
						return;
					}
					scheduleFlyoutClose();
				});
			}
		});

		panel.addEventListener('mouseenter', cancelFlyoutClose);
		panel.addEventListener('mouseleave', function (event) {
			if (!isDesktop()) {
				return;
			}
			if (event.relatedTarget && panel.contains(event.relatedTarget)) {
				return;
			}
			scheduleFlyoutClose();
		});
	}

	function initMegaSearch(panel) {
		var input = panel.querySelector('.lf-mega-search__input');
		if (!input || input.dataset.lfMegaBound === '1') {
			bindMobileCategoryAccordions(panel);
			bindCategoryFlyouts(panel);
			return;
		}
		input.dataset.lfMegaBound = '1';

		var categories = panel.querySelectorAll(':scope > .lf-menu-service-category');
		var tiles = panel.querySelectorAll('.lf-mega-tile');
		var categorized = categories.length > 0;

		var empty = panel.querySelector('.lf-mega-empty');
		if (!empty) {
			empty = document.createElement('li');
			empty.className = 'lf-mega-empty menu-item';
			empty.hidden = true;
			empty.innerHTML = '<span class="lf-mega-empty__text">No matches</span>';
			panel.appendChild(empty);
		}
		setEmptyVisible(empty, false);

		input.addEventListener('input', function () {
			var query = normalize(input.value);
			var visible = 0;

			if (categorized) {
				categories.forEach(function (category) {
					var catLabelEl = category.querySelector('.lf-mega-cat-tile__label');
					var catLabel = normalize(catLabelEl ? catLabelEl.textContent : category.textContent);
					var catTiles = category.querySelectorAll('.lf-mega-tile');
					var catVisible = 0;
					catTiles.forEach(function (tile) {
						var label = tile.querySelector('.lf-mega-tile__label');
						var text = normalize(label ? label.textContent : tile.textContent);
						var show = query === '' || text.indexOf(query) !== -1 || catLabel.indexOf(query) !== -1;
						tile.classList.toggle('lf-mega-tile--hidden', !show);
						tile.hidden = !show;
						if (show) {
							catVisible += 1;
							visible += 1;
						}
					});
					var showCategory = query === '' || catLabel.indexOf(query) !== -1 || catVisible > 0;
					category.hidden = !showCategory;
					var expandForSearch = query !== '' && showCategory && catVisible > 0;
					category.classList.toggle('is-flyout-open', expandForSearch);
					if (!isDesktop()) {
						setCategoryExpanded(category, expandForSearch);
					}
				});
			} else {
				tiles.forEach(function (tile) {
					var label = tile.querySelector('.lf-mega-tile__label');
					var text = normalize(label ? label.textContent : tile.textContent);
					var show = query === '' || text.indexOf(query) !== -1;
					tile.classList.toggle('lf-mega-tile--hidden', !show);
					tile.hidden = !show;
					if (show) {
						visible += 1;
					}
				});
			}

			setEmptyVisible(empty, query !== '' && visible === 0);
		});

		bindMobileCategoryAccordions(panel);
		bindCategoryFlyouts(panel);
	}

	function init() {
		document.querySelectorAll('.lf-mega-menu--services > .sub-menu').forEach(function (panel) {
			initMegaSearch(panel);
		});
	}

	function onViewportChange() {
		document.querySelectorAll('.lf-mega-menu--services > .sub-menu').forEach(function (panel) {
			cleanupMobileCategoryToggles(panel);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	try {
		if (window.matchMedia) {
			window.matchMedia('(max-width: 900px)').addEventListener('change', onViewportChange);
		}
	} catch (e) {
		window.addEventListener('resize', onViewportChange);
	}
})();
