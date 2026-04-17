(function ($) {
	'use strict';

	$(function () {
		var $phone = $('#wcgw_recipient_phone');
		if (!$phone.length) {
			return;
		}

		$phone.on('blur', function () {
			var v = ($(this).val() || '').trim();
			if (!v) { return; }
			var digits = v.replace(/[^0-9+]/g, '');
			if (digits.charAt(0) !== '+') {
				digits = '+' + digits.replace(/^\+*/, '');
			}
			$(this).val(digits);
		});

		$('form.cart').on('submit', function (e) {
			var name = ($('#wcgw_recipient_name').val() || '').trim();
			var phone = ($phone.val() || '').trim();
			var sender = ($('#wcgw_sender_name').val() || '').trim();
			if (!name || !phone || !sender) {
				alert('Please fill in the recipient name, WhatsApp number and your name before adding to cart.');
				e.preventDefault();
				return false;
			}
			if (!/^\+?[0-9\s\-]{8,20}$/.test(phone)) {
				alert('Please enter a valid WhatsApp number in international format, e.g. +201234567890.');
				e.preventDefault();
				return false;
			}
		});
	});
})(jQuery);
