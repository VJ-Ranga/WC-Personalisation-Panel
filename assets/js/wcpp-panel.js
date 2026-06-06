/**
 * WC Personalisation Panel — Front-end wizard JS
 *
 * ES5. IIFE-wrapped. Reads window.wcpp (localised from PHP).
 * All events namespaced .wcppPanel.
 * No generic WC/theme selectors. Scoped to #wcpp-panel only.
 */
(function ($, wcpp) {
	'use strict';

	if (!wcpp || !wcpp.config) {
		return;
	}

	// ─── State ────────────────────────────────────────────────────────────
	var state = {
		currentStep: 0,
		productId:   0,
		selections:  {
			location: null,
			type:     null,
			text:     null,
			font:     null,
			colour:   null
		}
	};

	// Step definitions — populated dynamically from config.
	// TODO Phase 3: add type, text, font, colour steps.
	var steps = [
		{ id: 'location', title: wcpp.i18n.stepLocation }
	];

	// ─── DOM refs ─────────────────────────────────────────────────────────
	var $panel, $overlay, $content, $title, $back, $close, $next, $addToBag, $pricePreview, $progressBar;

	// ─── Init ─────────────────────────────────────────────────────────────
	$(document).ready(function () {

		// Inject panel template if it's not already in the DOM (from PHP).
		// The panel.php template is output by PHP — just bind to it here.
		$panel       = $('#wcpp-panel');
		$overlay     = $('#wcpp-overlay');
		$content     = $('#wcpp-step-content');
		$title       = $('#wcpp-panel-title');
		$back        = $('#wcpp-back');
		$close       = $('#wcpp-close');
		$next        = $('#wcpp-next');
		$addToBag    = $('#wcpp-add-to-bag');
		$pricePreview= $('#wcpp-price-preview');
		$progressBar = $('#wcpp-progress-bar');

		if (!$panel.length) {
			return;
		}

		bindEvents();
	});

	// ─── Event binding ────────────────────────────────────────────────────
	function bindEvents() {

		// Open panel.
		$(document).on('click.wcppPanel', '#wcpp-open-panel', function () {
			state.productId = parseInt($(this).data('product-id'), 10) || 0;
			openPanel();
		});

		// Close panel — button.
		$close.on('click.wcppPanel', function () {
			maybeClose();
		});

		// Close panel — overlay click.
		$overlay.on('click.wcppPanel', function () {
			maybeClose();
		});

		// Close panel — Escape key.
		$(document).on('keydown.wcppPanel', function (e) {
			if (e.key === 'Escape' && $panel.hasClass('wcpp-is-open')) {
				maybeClose();
			}
		});

		// Back button.
		$back.on('click.wcppPanel', function () {
			if (state.currentStep > 0) {
				state.currentStep--;
				renderStep();
			}
		});

		// Next button.
		$next.on('click.wcppPanel', function () {
			if (canAdvance()) {
				state.currentStep++;
				renderStep();
			}
		});

		// Add to Bag.
		$addToBag.on('click.wcppPanel', function () {
			submitToCart();
		});
	}

	// ─── Panel open / close ───────────────────────────────────────────────
	function openPanel() {
		resetState();
		$panel.attr('aria-hidden', 'false').addClass('wcpp-is-open');
		$overlay.addClass('wcpp-is-open');
		$('#wcpp-open-panel').attr('aria-expanded', 'true');
		// Trap focus inside panel.
		$close.focus();
		// Prevent body scroll.
		$('body').css('overflow', 'hidden');
		renderStep();
	}

	function closePanel() {
		$panel.attr('aria-hidden', 'true').removeClass('wcpp-is-open');
		$overlay.removeClass('wcpp-is-open');
		$('#wcpp-open-panel').attr('aria-expanded', 'false');
		$('body').css('overflow', '');
	}

	function maybeClose() {
		var hasSelections = false;
		$.each(state.selections, function (key, val) {
			if (val !== null) {
				hasSelections = true;
			}
		});

		if (hasSelections) {
			if (window.confirm(wcpp.i18n.confirmClose)) {
				closePanel();
			}
		} else {
			closePanel();
		}
	}

	// ─── State helpers ────────────────────────────────────────────────────
	function resetState() {
		state.currentStep  = 0;
		state.selections   = { location: null, type: null, text: null, font: null, colour: null };
	}

	function canAdvance() {
		var step = steps[state.currentStep];
		if (!step) {
			return false;
		}
		return state.selections[step.id] !== null;
	}

	// ─── Step rendering ───────────────────────────────────────────────────
	function renderStep() {
		var step = steps[state.currentStep];
		var isLast = state.currentStep === steps.length - 1;

		// Update title.
		$title.text(step ? step.title : wcpp.i18n.stepSummary);

		// Update progress bar.
		var pct = steps.length ? Math.round((state.currentStep / steps.length) * 100) : 0;
		$progressBar.css('width', pct + '%').attr('aria-valuenow', state.currentStep);

		// Show/hide back button.
		if (state.currentStep > 0) {
			$back.show();
		} else {
			$back.hide();
		}

		// Show correct footer button.
		if (isLast) {
			$next.hide();
			$addToBag.show();
		} else {
			$next.show();
			$addToBag.hide();
		}

		// Render step content.
		$content.empty();

		if (!step) {
			return;
		}

		switch (step.id) {
			case 'location':
				renderLocationStep();
				break;
			// TODO Phase 3: add cases for type, text, font, colour, summary.
		}
	}

	// ─── Step: Location ───────────────────────────────────────────────────
	function renderLocationStep() {
		var locations = wcpp.config.locations || [];

		if (!locations.length) {
			$content.append('<p>' + esc(wcpp.i18n.stepLocation) + '</p>');
			return;
		}

		var $wrap = $('<div class="wcpp-options"></div>');

		$.each(locations, function (i, loc) {
			var $btn = $('<button type="button" class="wcpp-option-btn"></button>').text(loc);

			if (state.selections.location === loc) {
				$btn.addClass('wcpp-selected');
			}

			$btn.on('click.wcppPanel', function () {
				state.selections.location = loc;
				$wrap.find('.wcpp-option-btn').removeClass('wcpp-selected');
				$btn.addClass('wcpp-selected');
				// Auto-advance after selection on last step (Phase 2 has 1 step).
				if (steps.length === 1) {
					$next.show();
				}
			});

			$wrap.append($btn);
		});

		$content.append($('<h3 class="wcpp-step__heading"></h3>').text(wcpp.i18n.stepLocation));
		$content.append($wrap);
	}

	// ─── AJAX: submit to cart ─────────────────────────────────────────────
	function submitToCart() {
		$addToBag.prop('disabled', true).text('...');

		$.ajax({
			url:    wcpp.ajaxUrl,
			method: 'POST',
			data: {
				action:       'wcpp_add_to_cart',
				nonce:        wcpp.nonce,
				product_id:   state.productId,
				location:     state.selections.location
				// TODO Phase 3: add type, text, font, colour.
			},
			success: function (response) {
				if (response.success) {
					closePanel();
					// Trigger WC fragment refresh so mini-cart updates.
					$(document.body).trigger('wc_fragment_refresh');
					// Redirect to cart.
					if (response.data && response.data.cart_url) {
						window.location.href = response.data.cart_url;
					}
				} else {
					var msg = (response.data && response.data.message) ? response.data.message : wcpp.i18n.errorGeneric;
					alert(msg);
					$addToBag.prop('disabled', false).text(wcpp.i18n.addToBag);
				}
			},
			error: function () {
				alert(wcpp.i18n.errorGeneric);
				$addToBag.prop('disabled', false).text(wcpp.i18n.addToBag);
			}
		});
	}

	// ─── Utility ──────────────────────────────────────────────────────────
	// Simple HTML escape — never concat untrusted data into HTML without this.
	function esc(str) {
		return $('<div>').text(str).html();
	}

}(jQuery, window.wcpp || {}));
