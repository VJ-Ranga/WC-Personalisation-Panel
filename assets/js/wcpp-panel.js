/**
 * WC Personalisation Panel — Front-end wizard
 *
 * ES5. IIFE. Reads window.wcpp (localised from PHP).
 * All events namespaced .wcppPanel
 * Scoped to #wcpp-panel only — no generic WC/theme selectors.
 */
(function ($, wcpp) {
	'use strict';

	// Guard — no config or no options means nothing to show.
	if ( !wcpp || !wcpp.config || !wcpp.config.options || !wcpp.config.options.length ) {
		return;
	}

	// ─── Build steps from config options ─────────────────────────────────
	var steps = [];
	$.each( wcpp.config.options, function ( i, opt ) {
		steps.push({ id: opt.id, name: opt.name, option: opt });
	});
	// Summary is always the last step.
	steps.push({ id: '__summary__', name: wcpp.i18n.summary, option: null });

	// ─── State ────────────────────────────────────────────────────────────
	var state = {
		currentStep:  0,
		productId:    0,
		variationId:  0,
		selections:   {} // keyed by option.id
	};

	// ─── DOM refs ─────────────────────────────────────────────────────────
	var $panel, $overlay, $content, $title, $back, $close, $next, $addToBag, $progressBar;

	// ─── Init ─────────────────────────────────────────────────────────────
	$( document ).ready( function () {
		$panel       = $( '#wcpp-panel' );
		$overlay     = $( '#wcpp-overlay' );
		$content     = $( '#wcpp-step-content' );
		$title       = $( '#wcpp-panel-title' );
		$back        = $( '#wcpp-back' );
		$close       = $( '#wcpp-close' );
		$next        = $( '#wcpp-next' );
		$addToBag    = $( '#wcpp-add-to-bag' );
		$progressBar = $( '#wcpp-progress-bar' );

		if ( !$panel.length ) {
			return;
		}

		bindEvents();
	});

	// ─── Events ───────────────────────────────────────────────────────────
	function bindEvents() {

		// Open.
		$( document ).on( 'click.wcppPanel', '#wcpp-open-panel', function () {
			state.productId   = parseInt( $( this ).data( 'product-id' ), 10 ) || 0;
			state.variationId = 0;

			// Try to get variation ID from the form.
			var $form = $( 'form.cart' );
			if ( $form.length ) {
				var varId = parseInt( $form.find( 'input[name="variation_id"]' ).val(), 10 );
				if ( varId ) {
					state.variationId = varId;
				}
			}

			openPanel();
		});

		// Close — button.
		$( document ).on( 'click.wcppPanel', '#wcpp-close', function () {
			maybeClose();
		});

		// Close — overlay.
		$( document ).on( 'click.wcppPanel', '#wcpp-overlay', function () {
			maybeClose();
		});

		// Close — Escape key.
		$( document ).on( 'keydown.wcppPanel', function ( e ) {
			if ( e.key === 'Escape' && $panel.hasClass( 'wcpp-is-open' ) ) {
				maybeClose();
			}
		});

		// Back.
		$( document ).on( 'click.wcppPanel', '#wcpp-back', function () {
			if ( state.currentStep > 0 ) {
				state.currentStep--;
				renderStep();
			}
		});

		// Next.
		$( document ).on( 'click.wcppPanel', '#wcpp-next', function () {
			if ( canAdvance() ) {
				state.currentStep++;
				renderStep();
			} else {
				// Shake the options to hint user must select.
				$content.find( '.wcpp-options' ).addClass( 'wcpp-shake' );
				setTimeout( function () {
					$content.find( '.wcpp-options' ).removeClass( 'wcpp-shake' );
				}, 500 );
			}
		});

		// Add to Bag.
		$( document ).on( 'click.wcppPanel', '#wcpp-add-to-bag', function () {
			submitToCart();
		});
	}

	// ─── Open / close ─────────────────────────────────────────────────────
	function openPanel() {
		resetState();
		state.currentStep = 0;
		$panel.attr( 'aria-hidden', 'false' ).addClass( 'wcpp-is-open' );
		$overlay.addClass( 'wcpp-is-open' );
		$( '#wcpp-open-panel' ).attr( 'aria-expanded', 'true' );
		$( 'body' ).css( 'overflow', 'hidden' );
		$close.focus();
		renderStep();
	}

	function closePanel() {
		$panel.attr( 'aria-hidden', 'true' ).removeClass( 'wcpp-is-open' );
		$overlay.removeClass( 'wcpp-is-open' );
		$( '#wcpp-open-panel' ).attr( 'aria-expanded', 'false' );
		$( 'body' ).css( 'overflow', '' );
	}

	function maybeClose() {
		var hasSelections = Object.keys( state.selections ).length > 0;
		if ( hasSelections ) {
			if ( window.confirm( wcpp.i18n.confirmClose ) ) {
				closePanel();
			}
		} else {
			closePanel();
		}
	}

	function resetState() {
		state.selections  = {};
		state.variationId = 0;
	}

	// ─── Step rendering ───────────────────────────────────────────────────
	function renderStep() {
		var step  = steps[ state.currentStep ];
		var isFirst = state.currentStep === 0;
		var isLast  = state.currentStep === steps.length - 1;

		// Title.
		$title.text( step.name );

		// Progress bar — exclude summary from the count.
		var totalOptions = steps.length - 1;
		var pct = isLast ? 100 : Math.round( ( state.currentStep / totalOptions ) * 100 );
		$progressBar.css( 'width', pct + '%' ).attr( 'aria-valuenow', state.currentStep );

		// Back button.
		isFirst ? $back.hide() : $back.show();

		// Footer buttons.
		if ( isLast ) {
			$next.hide();
			$addToBag.show().text( wcpp.i18n.addToBag ).prop( 'disabled', false );
		} else {
			$next.show();
			$addToBag.hide();
		}

		$content.empty();

		if ( step.id === '__summary__' ) {
			renderSummaryStep();
		} else {
			renderOptionStep( step.option );
		}

		// Scroll content to top.
		$content.scrollTop( 0 );
	}

	// ─── Option step ──────────────────────────────────────────────────────
	function renderOptionStep( option ) {
		var currentSel = state.selections[ option.id ];

		$content.append(
			$( '<h3 class="wcpp-step__heading"></h3>' ).text( option.name )
		);

		var $wrap = $( '<div class="wcpp-options"></div>' );

		$.each( option.choices, function ( i, choice ) {
			var $btn = $( '<button type="button" class="wcpp-option-btn"></button>' );

			// Image.
			if ( choice.image_url ) {
				$btn.append(
					$( '<div class="wcpp-option-img-wrap"></div>' ).append(
						$( '<img class="wcpp-option-img" />' )
							.attr( 'src', choice.image_url )
							.attr( 'alt', choice.name )
					)
				);
			}

			// Label + price.
			var $labelWrap = $( '<div class="wcpp-option-label-wrap"></div>' );
			$labelWrap.append( $( '<span class="wcpp-option-name"></span>' ).text( choice.name ) );

			if ( parseFloat( choice.price ) > 0 ) {
				$labelWrap.append(
					$( '<span class="wcpp-option-price"></span>' ).text( '+' + formatPrice( choice.price ) )
				);
			}
			$btn.append( $labelWrap );

			// Tick icon.
			$btn.append( $( '<span class="wcpp-option-tick dashicons dashicons-yes"></span>' ) );

			// Selected state.
			if ( currentSel && currentSel.choice_id === choice.id ) {
				$btn.addClass( 'wcpp-selected' );
			}

			$btn.on( 'click.wcppPanel', function () {
				state.selections[ option.id ] = {
					option_id:        option.id,
					option_name:      option.name,
					choice_id:        choice.id,
					choice_name:      choice.name,
					choice_price:     choice.price,
					choice_image_url: choice.image_url || ''
				};
				$wrap.find( '.wcpp-option-btn' ).removeClass( 'wcpp-selected' );
				$btn.addClass( 'wcpp-selected' );
			});

			$wrap.append( $btn );
		});

		$content.append( $wrap );
	}

	// ─── Summary step ─────────────────────────────────────────────────────
	function renderSummaryStep() {
		$content.append(
			$( '<h3 class="wcpp-step__heading"></h3>' ).text( wcpp.i18n.yourChoices )
		);

		var $list = $( '<ul class="wcpp-summary-list"></ul>' );
		var totalPrice = 0;

		$.each( wcpp.config.options, function ( i, opt ) {
			var sel   = state.selections[ opt.id ];
			var $li   = $( '<li class="wcpp-summary-item"></li>' );
			var $left = $( '<div class="wcpp-summary-left"></div>' );

			if ( sel && sel.choice_image_url ) {
				$left.append( $( '<img class="wcpp-summary-img" />' ).attr( 'src', sel.choice_image_url ).attr( 'alt', sel.choice_name ) );
			}

			$left.append( $( '<div class="wcpp-summary-text"></div>' )
				.append( $( '<span class="wcpp-summary-label"></span>' ).text( opt.name ) )
				.append( $( '<span class="wcpp-summary-value"></span>' ).text( sel ? sel.choice_name : '—' ) )
			);

			$li.append( $left );

			if ( sel && parseFloat( sel.choice_price ) > 0 ) {
				totalPrice += parseFloat( sel.choice_price );
				$li.append( $( '<span class="wcpp-summary-price"></span>' ).text( '+' + formatPrice( sel.choice_price ) ) );
			}

			$list.append( $li );
		});

		$content.append( $list );

		// Total price if any.
		if ( totalPrice > 0 ) {
			$content.append(
				$( '<div class="wcpp-summary-total"></div>' )
					.append( $( '<span>' ).text( 'Personalisation total: ' ) )
					.append( $( '<strong>' ).text( formatPrice( totalPrice ) ) )
			);
		}
	}

	// ─── Advance check ────────────────────────────────────────────────────
	function canAdvance() {
		var step = steps[ state.currentStep ];
		if ( !step || step.id === '__summary__' ) {
			return true;
		}
		return state.selections[ step.option.id ] !== undefined;
	}

	// ─── AJAX: add to cart ────────────────────────────────────────────────
	function submitToCart() {
		$addToBag.prop( 'disabled', true ).text( wcpp.i18n.adding );

		// Build selections array in option order.
		var selectionsArr = [];
		$.each( wcpp.config.options, function ( i, opt ) {
			if ( state.selections[ opt.id ] ) {
				selectionsArr.push( state.selections[ opt.id ] );
			}
		});

		$.ajax({
			url:    wcpp.ajaxUrl,
			method: 'POST',
			data: {
				action:       'wcpp_add_to_cart',
				nonce:        wcpp.nonce,
				product_id:   state.productId,
				variation_id: state.variationId,
				set_id:       wcpp.config.id,
				selections:   JSON.stringify( selectionsArr )
			},
			success: function ( response ) {
				if ( response.success ) {
					closePanel();
					$( document.body ).trigger( 'wc_fragment_refresh' );
					if ( response.data && response.data.cart_url ) {
						window.location.href = response.data.cart_url;
					}
				} else {
					var msg = ( response.data && response.data.message ) ? response.data.message : wcpp.i18n.errorGeneric;
					alert( msg );
					$addToBag.prop( 'disabled', false ).text( wcpp.i18n.addToBag );
				}
			},
			error: function () {
				alert( wcpp.i18n.errorGeneric );
				$addToBag.prop( 'disabled', false ).text( wcpp.i18n.addToBag );
			}
		});
	}

	// ─── Utility ─────────────────────────────────────────────────────────
	function formatPrice( amount ) {
		return parseFloat( amount ).toFixed( 2 );
	}

}( jQuery, window.wcpp || {} ));
