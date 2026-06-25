/**
 * Header mega menu: client-side search/filter + category flyout helpers.
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

	function bindCategoryFlyouts(panel) {
		var categories = panel.querySelectorAll(':scope > .lf-menu-service-category');
		if (!categories.length) {
			return;
		}
		categories.forEach(function (category) {
			if (category.dataset.lfMegaFlyoutBound === '1') {
				return;
			}
			category.dataset.lfMegaFlyoutBound = '1';
			category.addEventListener('mouseenter', function () {
				if (!isDesktop()) {
					return;
				}
				Array.prototype.forEach.call(categories, function (peer) {
					if (peer !== category) {
						peer.classList.remove('is-flyout-open');
					}
				});
				category.classList.add('is-flyout-open');
			});
			category.addEventListener('mouseleave', function () {
				category.classList.remove('is-flyout-open');
			});
		});
		panel.addEventListener('mouseleave', function () {
			Array.prototype.forEach.call(categories, function (category) {
				category.classList.remove('is-flyout-open');
			});
		});
	}

	function initMegaSearch(panel) {
		var input = panel.querySelector('.lf-mega-search__input');
		if (!input || input.dataset.lfMegaBound === '1') {
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
						category.classList.toggle('is-open', expandForSearch);
						var catToggle = category.querySelector(':scope > .site-header__category-toggle');
						var catLink = category.querySelector(':scope > a.lf-menu-service-category__link, :scope > a');
						if (catToggle) {
							catToggle.setAttribute('aria-expanded', expandForSearch ? 'true' : 'false');
						}
						if (catLink) {
							catLink.setAttribute('aria-expanded', expandForSearch ? 'true' : 'false');
						}
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

		bindCategoryFlyouts(panel);
	}

	function init() {
		document.querySelectorAll('.lf-mega-menu--services > .sub-menu').forEach(function (panel) {
			initMegaSearch(panel);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
