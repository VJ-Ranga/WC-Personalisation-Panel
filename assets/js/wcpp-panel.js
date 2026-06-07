/**
 * WC Personalisation Panel — Front-end wizard
 *
 * ES5. IIFE. Reads window.wcpp (localised from PHP).
 * Events namespaced .wcppPanel. Scoped to #wcpp-panel only.
 */
(function ($, wcpp) {
	'use strict';

	if ( !wcpp || !wcpp.config || !wcpp.config.options || !wcpp.config.options.length ) {
		return;
	}

	var design = wcpp.config.design || {};

	// Build steps from config options + summary.
	var steps = [];
	$.each( wcpp.config.options, function ( i, opt ) {
		steps.push({ id: opt.id, name: opt.name, option: opt });
	});
	steps.push({ id: '__summary__', name: wcpp.i18n.summary, option: null });

	var state = { currentStep: 0, productId: 0, variationId: 0, selections: {} };

	var $panel, $overlay, $content, $title, $back, $close, $next, $addToBag;

	$( document ).ready( function () {
		$panel    = $( '#wcpp-panel' );
		$overlay  = $( '#wcpp-overlay' );
		$content  = $( '#wcpp-step-content' );
		$title    = $( '#wcpp-panel-title' );
		$back     = $( '#wcpp-back' );
		$close    = $( '#wcpp-close' );
		$next     = $( '#wcpp-next' );
		$addToBag = $( '#wcpp-add-to-bag' );

		if ( !$panel.length ) { return; }
		bindEvents();
	});

	function bindEvents() {
		$( document ).on( 'click.wcppPanel', '#wcpp-open-panel', function () {
			state.productId   = parseInt( $( this ).data( 'product-id' ), 10 ) || 0;
			state.variationId = 0;
			var $form = $( 'form.cart' );
			if ( $form.length ) {
				var varId = parseInt( $form.find( 'input[name="variation_id"]' ).val(), 10 );
				if ( varId ) { state.variationId = varId; }
			}
			openPanel();
		});

		$( document ).on( 'click.wcppPanel', '#wcpp-close',   function () { maybeClose(); });
		$( document ).on( 'click.wcppPanel', '#wcpp-overlay', function () { maybeClose(); });
		$( document ).on( 'keydown.wcppPanel', function ( e ) {
			if ( e.key === 'Escape' && $panel.hasClass( 'wcpp-is-open' ) ) { maybeClose(); }
		});

		$( document ).on( 'click.wcppPanel', '#wcpp-back', function () {
			if ( state.currentStep > 0 ) { state.currentStep--; renderStep(); }
		});
		$( document ).on( 'click.wcppPanel', '#wcpp-next', function () {
			if ( canAdvance() ) { state.currentStep++; renderStep(); }
			else { hintRequired(); }
		});
		$( document ).on( 'click.wcppPanel', '#wcpp-add-to-bag', function () { submitToCart(); });
	}

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
		if ( Object.keys( state.selections ).length > 0 ) {
			if ( window.confirm( wcpp.i18n.confirmClose ) ) { closePanel(); }
		} else { closePanel(); }
	}

	function resetState() { state.selections = {}; state.variationId = 0; }

	function renderStep() {
		var step    = steps[ state.currentStep ];
		var isFirst = state.currentStep === 0;
		var isLast  = state.currentStep === steps.length - 1;

		$title.text( step.name );
		updateProgress();

		isFirst ? $back.hide() : $back.show();

		if ( isLast ) {
			$next.hide();
			$addToBag.show().text( wcpp.i18n.addToBag ).prop( 'disabled', false );
		} else {
			$next.show();
			$addToBag.hide();
		}

		$content.empty();
		if ( step.id === '__summary__' ) { renderSummaryStep(); }
		else { renderOptionStep( step.option ); }
		$content.scrollTop( 0 );
	}

	// ─── Progress (bar / dots / text) ────────────────────────────────────
	function updateProgress() {
		var totalOptions = steps.length - 1;
		var styleAttr    = $panel.attr( 'data-progress-style' );

		if ( styleAttr === 'bar' ) {
			var pct = ( state.currentStep === steps.length - 1 )
				? 100
				: Math.round( ( state.currentStep / totalOptions ) * 100 );
			$( '#wcpp-progress-fill' ).css( 'width', pct + '%' );
		} else if ( styleAttr === 'dots' ) {
			var $dots = $( '#wcpp-progress-dots' );
			$dots.empty();
			for ( var i = 0; i < steps.length; i++ ) {
				var $d = $( '<span></span>' );
				if ( i <= state.currentStep ) { $d.addClass( 'wcpp-dot-active' ); }
				$dots.append( $d );
			}
		} else if ( styleAttr === 'text' ) {
			var label = ( wcpp.i18n.stepOf || 'Step %1$d of %2$d' )
				.replace( '%1$d', state.currentStep + 1 )
				.replace( '%2$d', steps.length );
			$( '#wcpp-progress-text' ).text( label );
		}
	}

	// ─── Option step ─────────────────────────────────────────────────────
	function renderOptionStep( option ) {
		var currentSel = state.selections[ option.id ];

		$content.append( $( '<h3 class="wcpp-step__heading"></h3>' ).text( option.name ) );

		var layoutClass = 'wcpp-options';
		if ( design.card_layout === 'grid2' ) { layoutClass += ' wcpp-options--grid2'; }
		else if ( design.card_layout === 'grid3' ) { layoutClass += ' wcpp-options--grid3'; }

		var $wrap = $( '<div class="' + layoutClass + '"></div>' );
		var showPrice = parseInt( design.show_choice_price, 10 ) !== 0;
		var imgSize   = parseInt( design.card_img_size, 10 );

		$.each( option.choices, function ( i, choice ) {
			var $btn = $( '<button type="button" class="wcpp-option-btn"></button>' );

			if ( choice.image_url && imgSize > 0 ) {
				$btn.append(
					$( '<div class="wcpp-option-img-wrap"></div>' ).append(
						$( '<img class="wcpp-option-img" />' ).attr( 'src', choice.image_url ).attr( 'alt', choice.name )
					)
				);
			}

			var $labelWrap = $( '<div class="wcpp-option-label-wrap"></div>' );
			$labelWrap.append( $( '<span class="wcpp-option-name"></span>' ).text( choice.name ) );
			if ( showPrice && parseFloat( choice.price ) > 0 ) {
				$labelWrap.append( $( '<span class="wcpp-option-price"></span>' ).text( '+' + money( choice.price ) ) );
			}
			$btn.append( $labelWrap );
			$btn.append( $( '<span class="wcpp-option-tick">✓</span>' ) );

			if ( currentSel && currentSel.choice_id === choice.id ) { $btn.addClass( 'wcpp-selected' ); }

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

	// ─── Summary step ────────────────────────────────────────────────────
	function renderSummaryStep() {
		$content.append( $( '<h3 class="wcpp-step__heading"></h3>' ).text( wcpp.i18n.yourChoices ) );

		var $list = $( '<ul class="wcpp-summary-list"></ul>' );
		var total = 0;
		var showPrice = parseInt( design.show_choice_price, 10 ) !== 0;

		$.each( wcpp.config.options, function ( i, opt ) {
			var sel  = state.selections[ opt.id ];
			var $li  = $( '<li class="wcpp-summary-item"></li>' );
			var $left= $( '<div class="wcpp-summary-left"></div>' );

			if ( sel && sel.choice_image_url ) {
				$left.append( $( '<img class="wcpp-summary-img" />' ).attr( 'src', sel.choice_image_url ).attr( 'alt', sel.choice_name ) );
			}
			$left.append( $( '<div class="wcpp-summary-text"></div>' )
				.append( $( '<span class="wcpp-summary-label"></span>' ).text( opt.name ) )
				.append( $( '<span class="wcpp-summary-value"></span>' ).text( sel ? sel.choice_name : '—' ) )
			);
			$li.append( $left );

			if ( sel && parseFloat( sel.choice_price ) > 0 ) {
				total += parseFloat( sel.choice_price );
				if ( showPrice ) {
					$li.append( $( '<span class="wcpp-summary-price"></span>' ).text( '+' + money( sel.choice_price ) ) );
				}
			}
			$list.append( $li );
		});

		// Flat set fee (one-time charge for the whole set).
		var setFee = parseFloat( wcpp.config.set_price || 0 );
		if ( setFee > 0 ) {
			total += setFee;
			if ( showPrice ) {
				var $feeLi = $( '<li class="wcpp-summary-item wcpp-summary-fee"></li>' );
				$feeLi.append( $( '<div class="wcpp-summary-left"></div>' ).append(
					$( '<div class="wcpp-summary-text"></div>' )
						.append( $( '<span class="wcpp-summary-label"></span>' ).text( wcpp.i18n.feeLabel || 'Personalisation fee' ) )
				) );
				$feeLi.append( $( '<span class="wcpp-summary-price"></span>' ).text( '+' + money( setFee ) ) );
				$list.append( $feeLi );
			}
		}

		$content.append( $list );

		if ( parseInt( design.show_total, 10 ) !== 0 && total > 0 ) {
			$content.append(
				$( '<div class="wcpp-summary-total"></div>' )
					.append( $( '<span>' ).text( ( wcpp.i18n.total || 'Total:' ) + ' ' ) )
					.append( $( '<strong>' ).text( money( total ) ) )
			);
		}
	}

	function canAdvance() {
		var step = steps[ state.currentStep ];
		if ( !step || step.id === '__summary__' ) { return true; }
		return state.selections[ step.option.id ] !== undefined;
	}

	function hintRequired() {
		var $opts = $content.find( '.wcpp-options' );
		$opts.addClass( 'wcpp-shake' );
		setTimeout( function () { $opts.removeClass( 'wcpp-shake' ); }, 500 );
	}

	function submitToCart() {
		$addToBag.prop( 'disabled', true ).text( wcpp.i18n.adding );

		var selectionsArr = [];
		$.each( wcpp.config.options, function ( i, opt ) {
			if ( state.selections[ opt.id ] ) { selectionsArr.push( state.selections[ opt.id ] ); }
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
					alert( ( response.data && response.data.message ) ? response.data.message : wcpp.i18n.errorGeneric );
					$addToBag.prop( 'disabled', false ).text( wcpp.i18n.addToBag );
				}
			},
			error: function () {
				alert( wcpp.i18n.errorGeneric );
				$addToBag.prop( 'disabled', false ).text( wcpp.i18n.addToBag );
			}
		});
	}

	// Format a price using WooCommerce's currency settings (symbol + position).
	function money( amount ) {
		var c = wcpp.currency || {};
		var decimals = ( typeof c.decimals === 'number' ) ? c.decimals : 2;
		var num = parseFloat( amount || 0 ).toFixed( decimals );

		// Apply thousand + decimal separators.
		var parts = num.split( '.' );
		parts[0] = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, c.thousand || ',' );
		var formatted = parts.join( c.decimal || '.' );

		var sym = c.symbol || '';
		switch ( c.position ) {
			case 'right':        return formatted + sym;
			case 'left_space':   return sym + ' ' + formatted;
			case 'right_space':  return formatted + ' ' + sym;
			default:             return sym + formatted; // 'left'.
		}
	}

}( jQuery, window.wcpp || {} ));
