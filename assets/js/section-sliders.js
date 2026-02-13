(() => {
	const getTrack = (root) =>
		root.querySelector('[data-lf-slider-track]') || root.querySelector('.lf-slider__track');

	const getScrollAmount = (track) => Math.max(260, Math.round(track.clientWidth * 0.9));

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
		const direction = button.hasAttribute('data-lf-slider-next') ? 1 : -1;
		track.scrollBy({ left: direction * getScrollAmount(track), behavior: 'smooth' });
	});
})();
