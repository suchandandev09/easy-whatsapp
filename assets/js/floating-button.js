/**
 * Floating WhatsApp Button JS
 */
document.addEventListener('DOMContentLoaded', function () {
	var buttons = document.querySelectorAll('.easy-whatsapp-floating-button');
	var modal = document.getElementById('easy-whatsapp-modal');
	var form = modal ? modal.querySelector('.easy-whatsapp-lead-form') : null;
	var errorBox = modal ? modal.querySelector('.easy-whatsapp-form-error') : null;
	var loadingBox = modal ? modal.querySelector('.easy-whatsapp-loading') : null;
	var activeNumber = '';
	var activePostId = 0;

	if (!modal || !form) {
		return;
	}

	function isMobileDevice() {
		return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
	}

	function buildWhatsAppUrl(number, message) {
		var cleanNumber = number.replace(/[^0-9]/g, '');
		if (!cleanNumber) {
			return '';
		}

		var baseUrl = isMobileDevice()
			? 'https://api.whatsapp.com/send?phone=' + cleanNumber
			: 'https://web.whatsapp.com/send?phone=' + cleanNumber;

		if (!message) {
			return baseUrl;
		}

		return baseUrl + '&text=' + encodeURIComponent(message);
	}

	function saveLeadData(name, phone, email) {
		if (!window.easyWhatsappData || !window.easyWhatsappData.ajaxUrl || !window.easyWhatsappData.nonce || !window.easyWhatsappData.action) {
			return Promise.reject(new Error('Lead save API is not configured.'));
		}

		var payload = new URLSearchParams();
		payload.append('action', window.easyWhatsappData.action);
		payload.append('nonce', window.easyWhatsappData.nonce);
		payload.append('name', name);
		payload.append('phone', phone);
		payload.append('email', email);
		payload.append('post_id', String(activePostId));
		payload.append('whatsapp_number', activeNumber);
		payload.append('page_url', window.location.href);

		return fetch(window.easyWhatsappData.ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			credentials: 'same-origin',
			body: payload.toString()
		}).then(function (response) {
			return response.json().catch(function () {
				throw new Error('Unexpected API response.');
			}).then(function (data) {
				if (!response.ok || !data || data.success !== true) {
					var errorMessage = (data && data.data && data.data.message)
						? String(data.data.message)
						: 'Failed to store lead data. Please try again.';
					throw new Error(errorMessage);
				}

				return data;
			});
		});
	}

	function openModal(number, postId) {
		activeNumber = number || '';
		activePostId = postId || 0;
		modal.hidden = false;
		document.body.classList.add('easy-whatsapp-modal-open');

		if (loadingBox) {
			loadingBox.hidden = true;
		}

		if (errorBox) {
			errorBox.textContent = '';
		}

		var nameInput = form.querySelector('#easy-whatsapp-name');
		if (nameInput) {
			nameInput.focus();
		}
	}

	function closeModal() {
		modal.hidden = true;
		document.body.classList.remove('easy-whatsapp-modal-open');
		if (loadingBox) {
			loadingBox.hidden = true;
		}
		if (errorBox) {
			errorBox.textContent = '';
		}
	}

	buttons.forEach(function (button) {
		button.addEventListener('click', function (e) {
			e.preventDefault();

			var number = this.getAttribute('data-number');
			var postId = parseInt(this.getAttribute('data-post-id') || '0', 10);
			if (!number) {
				return;
			}

			openModal(number, postId);
		});
	});

	modal.addEventListener('click', function (event) {
		if (event.target && event.target.getAttribute('data-easy-whatsapp-close') === '1') {
			closeModal();
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && !modal.hidden) {
			closeModal();
		}
	});

	form.addEventListener('submit', function (event) {
		event.preventDefault();

		var nameInput = form.querySelector('#easy-whatsapp-name');
		var phoneInput = form.querySelector('#easy-whatsapp-phone');
		var emailInput = form.querySelector('#easy-whatsapp-email');

		var name = nameInput ? nameInput.value.trim() : '';
		var phone = phoneInput ? phoneInput.value.trim() : '';
		var email = emailInput ? emailInput.value.trim() : '';

		if (!name || !phone) {
			if (errorBox) {
				errorBox.textContent = 'Please fill in Name and Phone.';
			}
			return;
		}

		if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
			if (errorBox) {
				errorBox.textContent = 'Please enter a valid email address.';
			}
			return;
		}

		var message = 'Name: ' + name + '\nPhone: ' + phone;
		if (email) {
			message += '\nEmail: ' + email;
		}

		var url = buildWhatsAppUrl(activeNumber, message);
		if (!url) {
			if (errorBox) {
				errorBox.textContent = 'Unable to start WhatsApp. Please try again.';
			}
			return;
		}

		var submitButton = form.querySelector('.easy-whatsapp-submit');
		if (submitButton) {
			submitButton.disabled = true;
		}

		if (loadingBox) {
			loadingBox.hidden = false;
		}

		saveLeadData(name, phone, email)
			.then(function () {
				window.open(url, '_blank', 'noopener,noreferrer');
				form.reset();
				closeModal();
			})
			.catch(function (error) {
				if (errorBox) {
					errorBox.textContent = error && error.message
						? error.message
						: 'Failed to save details. Please try again.';
				}
			})
			.finally(function () {
				if (submitButton) {
					submitButton.disabled = false;
				}

				if (loadingBox) {
					loadingBox.hidden = true;
				}
			});
	});
});
