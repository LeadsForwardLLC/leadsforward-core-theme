(() => {
	const getTrack = (root) =>
		root.querySelector('[data-lf-slider-track]') || root.querySelector('.lf-slider__track');
	const getViewport = (root) =>
		root.querySelector('[data-lf-slider-viewport]') || root;

	const buildSliderState = (root) => {
		const viewport = getViewport(root);
		const track = getTrack(root);
		if (!track) return null;
		const items = Array.from(track.children);
		if (!items.length) return null;
		const styles = window.getComputedStyle(track);
		const gap = parseFloat(styles.columnGap || styles.gap || 0);
		const itemWidth = items[0].getBoundingClientRect().width;
		const viewWidth = viewport ? viewport.clientWidth : track.clientWidth;
		const perView = Math.max(1, Math.round((viewWidth + gap) / (itemWidth + gap)));
		const pageWidth = (itemWidth + (isNaN(gap) ? 0 : gap)) * perView;
		const maxIndex = Math.max(0, Math.ceil(items.length / perView) - 1);
		return { track, items, gap, perView, pageWidth, maxIndex };
	};

	const setIndex = (root, index) => {
		const state = buildSliderState(root);
		if (!state) return;
		const clamped = Math.max(0, Math.min(state.maxIndex, index));
		state.track.dataset.index = String(clamped);
		state.track.style.transform = `translate3d(${-clamped * state.pageWidth}px, 0, 0)`;
		updateNavState(root, clamped, state.maxIndex);
		updateDots(root, clamped, state.maxIndex);
	};

	const updateNavState = (root, index, maxIndex) => {
		const prev = root.querySelector('[data-lf-slider-prev]');
		const next = root.querySelector('[data-lf-slider-next]');
		if (prev) prev.classList.toggle('is-disabled', index <= 0);
		if (next) next.classList.toggle('is-disabled', index >= maxIndex);
	};

	const updateDots = (root, index = 0, maxIndex = 0) => {
		const dotsWrap = root.querySelector('[data-lf-slider-dots]');
		if (!dotsWrap) return;
		const pageCount = Math.max(1, maxIndex + 1);
		if (dotsWrap.children.length !== pageCount) {
			dotsWrap.innerHTML = '';
			for (let i = 0; i < pageCount; i++) {
				const dot = document.createElement('button');
				dot.type = 'button';
				dot.className = 'lf-slider__dot';
				dot.setAttribute('aria-label', `Go to slide ${i + 1}`);
				dot.dataset.index = String(i);
				dotsWrap.appendChild(dot);
			}
			dotsWrap.addEventListener('click', (event) => {
				const dot = event.target.closest('.lf-slider__dot');
				if (!dot) return;
				const idx = parseInt(dot.dataset.index || '0', 10);
				setIndex(root, idx);
			});
		}
		Array.from(dotsWrap.children).forEach((dot, idx) => {
			dot.classList.toggle('is-active', idx === index);
		});
	};

	const initSlider = (root) => {
		const state = buildSliderState(root);
		if (!state) return;
		const current = parseInt(state.track.dataset.index || '0', 10);
		setIndex(root, isNaN(current) ? 0 : current);
	};

	const initSliders = () => {
		document.querySelectorAll('[data-lf-slider]').forEach((root) => initSlider(root));
	};

	window.addEventListener('resize', () => initSliders());
	document.addEventListener('DOMContentLoaded', initSliders);

	document.addEventListener('click', (event) => {
		const button = event.target.closest('[data-lf-slider-prev], [data-lf-slider-next]');
		if (!button) {
			return;
		}
		const root = button.closest('[data-lf-slider]');
		if (!root) {
			return;
		}
		const track = getTrack(root);
		if (!track) {
			return;
		}
		const current = parseInt(track.dataset.index || '0', 10);
		const direction = button.hasAttribute('data-lf-slider-next') ? 1 : -1;
		setIndex(root, (isNaN(current) ? 0 : current) + direction);
	});
})();
