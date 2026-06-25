/**
 * Header mega menu: client-side search/filter for the Services panel only.
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

	function initMegaSearch(panel) {
		var input = panel.querySelector('.lf-mega-search__input');
		if (!input || input.dataset.lfMegaBound === '1') {
			return;
		}
		input.dataset.lfMegaBound = '1';

		var tiles = panel.querySelectorAll('.lf-mega-tile');
		if (!tiles.length) {
			return;
		}

		var categories = panel.querySelectorAll('.lf-mega-category');
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
			categories.forEach(function (category) {
				var catTiles = category.querySelectorAll('.lf-mega-tile');
				var catVisible = 0;
				catTiles.forEach(function (tile) {
					if (!tile.hidden && !tile.classList.contains('lf-mega-tile--hidden')) {
						catVisible += 1;
					}
				});
				category.hidden = query !== '' && catVisible === 0;
			});
			setEmptyVisible(empty, query !== '' && visible === 0);
		});
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
