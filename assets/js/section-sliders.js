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
		
		// Get items per slide from data attribute, fallback to calculated value
		const itemsPerSlide = parseInt(root.dataset.sliderItemsPerSlide || '0', 10);
		const styles = window.getComputedStyle(track);
		const gap = parseFloat(styles.columnGap || styles.gap || 0);
		const itemWidth = items[0].getBoundingClientRect().width;
		const viewWidth = viewport ? viewport.clientWidth : track.clientWidth;
		
		let perView;
		if (itemsPerSlide > 0) {
			// Use configured items per slide
			perView = itemsPerSlide;
		} else {
			// Fallback to calculated value based on width
			perView = Math.max(1, Math.round((viewWidth + gap) / (itemWidth + gap)));
		}
		
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
		attachDrag(root);
		
		// Autoplay functionality
		const autoplay = root.dataset.sliderAutoplay === '1';
		const delay = parseInt(root.dataset.sliderDelay || '5', 10) * 1000;
		let autoplayInterval;
		
		if (autoplay && delay > 0) {
			const startAutoplay = () => {
				autoplayInterval = setInterval(() => {
					const currentIndex = parseInt(state.track.dataset.index || '0', 10);
					const nextIndex = currentIndex >= state.maxIndex ? 0 : currentIndex + 1;
					setIndex(root, nextIndex);
				}, delay);
			};
			
			const stopAutoplay = () => {
				if (autoplayInterval) {
					clearInterval(autoplayInterval);
					autoplayInterval = null;
				}
			};
			
			// Start autoplay
			startAutoplay();
			
			// Stop on hover
			root.addEventListener('mouseenter', stopAutoplay);
			root.addEventListener('mouseleave', startAutoplay);
			
			// Stop on touch
			root.addEventListener('touchstart', stopAutoplay);
			root.addEventListener('touchend', startAutoplay);
		}
	};

	const attachDrag = (root) => {
		if (root.dataset.lfSliderDrag === 'true') {
			return;
		}
		const viewport = getViewport(root);
		const track = getTrack(root);
		if (!viewport || !track) return;

		let isDown = false;
		let isDragging = false;
		let startX = 0;
		let startY = 0;
		let startIndex = 0;

		const getPoint = (event) => (event.touches ? event.touches[0] : event);

		const onDown = (event) => {
			if (event.button && event.button !== 0) return;
			const point = getPoint(event);
			isDown = true;
			isDragging = false;
			startX = point.clientX;
			startY = point.clientY;
			startIndex = parseInt(track.dataset.index || '0', 10) || 0;
			track.style.transition = 'none';
		};

		const onMove = (event) => {
			if (!isDown) return;
			const point = getPoint(event);
			const deltaX = point.clientX - startX;
			const deltaY = point.clientY - startY;
			if (!isDragging) {
				if (Math.abs(deltaX) < 4 && Math.abs(deltaY) < 4) return;
				if (Math.abs(deltaY) > Math.abs(deltaX)) {
					isDown = false;
					track.style.transition = '';
					return;
				}
				isDragging = true;
			}
			event.preventDefault();
			const state = buildSliderState(root);
			if (!state) return;
			track.style.transform = `translate3d(${-(startIndex * state.pageWidth) + deltaX}px, 0, 0)`;
		};

		const onUp = (event) => {
			if (!isDown) return;
			const point = getPoint(event);
			const deltaX = point.clientX - startX;
			isDown = false;
			track.style.transition = '';
			const state = buildSliderState(root);
			if (!state) return;
			const threshold = Math.min(120, state.pageWidth * 0.2);
			let targetIndex = startIndex;
			if (Math.abs(deltaX) > threshold) {
				targetIndex = startIndex + (deltaX < 0 ? 1 : -1);
			}
			setIndex(root, targetIndex);
		};

		viewport.addEventListener('mousedown', onDown);
		viewport.addEventListener('touchstart', onDown, { passive: true });
		window.addEventListener('mousemove', onMove, { passive: false });
		window.addEventListener('touchmove', onMove, { passive: false });
		window.addEventListener('mouseup', onUp);
		window.addEventListener('touchend', onUp);
		window.addEventListener('touchcancel', onUp);

		root.dataset.lfSliderDrag = 'true';
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
