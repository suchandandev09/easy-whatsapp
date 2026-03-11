/**
 * Floating WhatsApp Button JS
 */
document.addEventListener('DOMContentLoaded', function() {
	var buttons = document.querySelectorAll('.easy-whatsapp-floating-button');

	buttons.forEach(function(button) {
		button.addEventListener('click', function(e) {
			e.preventDefault();
			
			var number = this.getAttribute('data-number');
			if (!number) {
				return;
			}

			// Clean the number format if necessary (though it should be sanitized from backend)
			number = number.replace(/[^0-9]/g, '');

			var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
			var url = '';

			if (isMobile) {
				// Use mobile app deep link
				url = 'https://api.whatsapp.com/send?phone=' + number;
				// Alternatively can use 'whatsapp://send?phone=' + number
			} else {
				// Use Web WhatsApp for desktop
				url = 'https://web.whatsapp.com/send?phone=' + number;
			}

			window.open(url, '_blank', 'noopener,noreferrer');
		});
	});
});
