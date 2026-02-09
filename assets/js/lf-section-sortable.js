(function ($, window) {
	'use strict';

	function initSortable($list, options) {
		if (!$list || !$list.length || !$.fn.sortable) {
			return;
		}
		var defaults = {
			axis: 'y',
			tolerance: 'pointer'
		};
		$list.sortable($.extend({}, defaults, options || {}));
	}

	function initLibraryDrag($items, $list, options) {
		if (!$items || !$items.length) {
			return;
		}
		var opts = options || {};
		if ($.fn.draggable) {
			$items.draggable({
				helper: opts.helper || 'clone',
				appendTo: opts.appendTo || 'body',
				revert: opts.revert || 'invalid',
				zIndex: opts.zIndex || 9999,
				cancel: opts.cancel || ''
			});
		}
		if ($.fn.droppable && $list && $list.length && typeof opts.onDrop === 'function') {
			$list.droppable({
				accept: opts.accept || '',
				tolerance: opts.tolerance || 'pointer',
				drop: function (e, ui) {
					opts.onDrop(e, ui);
				}
			});
		}
	}

	window.LFSectionSortable = {
		initSortable: initSortable,
		initLibraryDrag: initLibraryDrag
	};
})(jQuery, window);
