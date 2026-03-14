/**
 * Floating WhatsApp Button JS
 */
document.addEventListener('DOMContentLoaded', function () {
	var buttons = document.querySelectorAll('.easy-whatsapp-floating-button');
	var modal = document.getElementById('easy-whatsapp-modal');
	var form = modal ? modal.querySelector('.easy-whatsapp-lead-form') : null;
	var errorBox = modal ? modal.querySelector('.easy-whatsapp-form-error') : null;
	var activeNumber = '';

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

	function openModal(number) {
		activeNumber = number || '';
		modal.hidden = false;
		document.body.classList.add('easy-whatsapp-modal-open');

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
		if (errorBox) {
			errorBox.textContent = '';
		}
	}

	buttons.forEach(function (button) {
		button.addEventListener('click', function (e) {
			e.preventDefault();

			var number = this.getAttribute('data-number');
			if (!number) {
				return;
			}

			openModal(number);
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

		window.open(url, '_blank', 'noopener,noreferrer');
		form.reset();
		closeModal();
	});
});
