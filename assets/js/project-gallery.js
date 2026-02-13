(() => {
	const updateToggle = (container, button) => {
		const beforeLabel = button.getAttribute('data-before-label') || 'Show before';
		const afterLabel = button.getAttribute('data-after-label') || 'Show after';
		const current = container.getAttribute('data-state') === 'before' ? 'before' : 'after';
		const next = current === 'after' ? 'before' : 'after';
		container.setAttribute('data-state', next);
		button.textContent = next === 'before' ? afterLabel : beforeLabel;
		button.setAttribute('aria-pressed', next === 'before' ? 'true' : 'false');
	};

	document.addEventListener('click', (event) => {
		const button = event.target.closest('[data-lf-project-toggle]');
		if (!button) {
			return;
		}
		const container = button.closest('[data-lf-project-before-after]');
		if (!container) {
			return;
		}
		updateToggle(container, button);
	});
})();
