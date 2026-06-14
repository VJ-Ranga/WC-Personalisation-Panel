/**
 * WC Personalisation Panel — Settings page JS
 * Initialises WP colour pickers on the Panel Settings page.
 */
(function ($) {
	'use strict';
	$(document).ready(function () {
		$('.wcpp-color-picker').wpColorPicker();

		// Range ↔ Number sync
		$(document).on('input', '.wcpp-range-row input[type="range"]', function () {
			$(this).siblings('input[type="number"]').val(this.value);
		});
		$(document).on('input', '.wcpp-range-row input[type="number"]', function () {
			$(this).siblings('input[type="range"]').val(this.value);
		});
	});
}(jQuery));
