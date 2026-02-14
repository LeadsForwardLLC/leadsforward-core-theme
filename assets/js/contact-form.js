(() => {
	const forms = document.querySelectorAll('[data-lf-contact-form]');
	if (!forms.length) return;
	const ajaxUrl = (window.lfContactForm && window.lfContactForm.ajax_url) || '';
	const nonce = (window.lfContactForm && window.lfContactForm.nonce) || '';
	forms.forEach((form) => {
		form.addEventListener('submit', (event) => {
			event.preventDefault();
			const status = form.querySelector('.lf-contact-form__status');
			if (status) status.textContent = 'Sending...';
			const data = new FormData(form);
			data.append('action', 'lf_contact_form_submit');
			data.append('nonce', nonce);
			data.append('page_url', window.location.href);
			data.append('page_title', document.title || '');
			fetch(ajaxUrl, {
				method: 'POST',
				body: data,
				credentials: 'same-origin',
			})
				.then((res) => res.json())
				.then((payload) => {
					if (payload && payload.success) {
						form.reset();
						if (status) status.textContent = payload.data?.message || 'Thanks! We will be in touch.';
					} else {
						if (status) status.textContent = payload?.data?.message || 'Please check the form and try again.';
					}
				})
				.catch(() => {
					if (status) status.textContent = 'Unable to send request right now.';
				});
		});
	});
})();
