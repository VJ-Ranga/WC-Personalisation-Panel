/**
 * WC Personalisation Panel — Front-end wizard (placement model)
 *
 * Flow:  choose placement → run its steps → review → (add another) → Add to Bag
 * Stacked mode: all steps visible; steps 2+ are locked until the previous is answered.
 * Sequential mode: one step at a time.
 *
 * ES5. IIFE. Events namespaced .wcppPanel. Scoped to #wcpp-panel.
 */
(function ($, wcpp) {
	'use strict';

	if ( !wcpp || !wcpp.config || !wcpp.config.placements || !wcpp.config.placements.length ) {
		return;
	}

	var cfg    = wcpp.config;
	var design = cfg.design || {};
	var i18n   = wcpp.i18n || {};

	var isSequential = ( design.step_flow === 'sequential' );

	// phase: 'select' | 'step' | 'review'
	var state = {
		phase:       'select',
		productId:   0,
		variationId: 0,
		variation:   {},
		current:     null,   // { placement, stepIndex, selections:{stepId: choiceObj} }
		completed:   []      // [{ placement_id, placement_name, selections:[...] }]
	};

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
	} );

	function bindEvents() {
		$( document ).on( 'click.wcppPanel', '#wcpp-open-panel', function () {
			state.productId   = parseInt( $( this ).data( 'product-id' ), 10 ) || 0;
			state.variationId = 0;
			state.variation   = {};
			state.quantity    = parseInt( $( 'form.cart input.qty' ).val(), 10 ) || 1;

			var $vform = $( 'form.variations_form' );
			if ( $vform.length ) {
				var vid = parseInt( $vform.find( 'input[name="variation_id"]' ).val(), 10 );
				if ( !vid ) {
					notify( wcpp.i18n.selectVariation || 'Please choose the product options before personalising.' );
					return;
				}
				state.variationId = vid;
				$vform.find( '[name^="attribute_"]' ).each( function () {
					var n = $( this ).attr( 'name' );
					if ( n ) { state.variation[ n ] = $( this ).val(); }
				} );
			} else {
				var $form = $( 'form.cart' );
				if ( $form.length ) {
					var v = parseInt( $form.find( 'input[name="variation_id"]' ).val(), 10 );
					if ( v ) { state.variationId = v; }
				}
			}

			openPanel();
		} );

		$( document ).on( 'click.wcppPanel', '#wcpp-close',   maybeClose );
		$( document ).on( 'click.wcppPanel', '#wcpp-overlay', maybeClose );
		$( document ).on( 'keydown.wcppPanel', function ( e ) {
			if ( e.key === 'Escape' && $panel.hasClass( 'wcpp-is-open' ) ) { maybeClose(); }
		} );

		$( document ).on( 'click.wcppPanel', '#wcpp-back', goBack );
		$( document ).on( 'click.wcppPanel', '#wcpp-next', goNext );
		$( document ).on( 'click.wcppPanel', '#wcpp-add-to-bag', submitToCart );
	}

	// ─── Open / close ──────────────────────────────────────────────────────
	function openPanel() {
		state.completed = [];
		state.current   = null;
		$panel.attr( 'aria-hidden', 'false' ).addClass( 'wcpp-is-open' );
		$overlay.addClass( 'wcpp-is-open' );
		$( '#wcpp-open-panel' ).attr( 'aria-expanded', 'true' );
		$( 'body' ).css( 'overflow', 'hidden' );
		$close.focus();

		if ( cfg.placements.length === 1 ) {
			startPlacement( cfg.placements[0] );
		} else {
			state.phase = 'select';
			render();
		}
	}

	function closePanel() {
		$panel.attr( 'aria-hidden', 'true' ).removeClass( 'wcpp-is-open' );
		$overlay.removeClass( 'wcpp-is-open' );
		$( '#wcpp-open-panel' ).attr( 'aria-expanded', 'false' );
		$( 'body' ).css( 'overflow', '' );
	}

	function maybeClose() {
		if ( state.completed.length || state.current ) {
			if ( window.confirm( i18n.confirmClose ) ) { closePanel(); }
		} else {
			closePanel();
		}
	}

	// ─── Helpers ───────────────────────────────────────────────────────────
	function availablePlacements() {
		var usedIds = {};
		$.each( state.completed, function ( i, c ) { usedIds[ c.placement_id ] = true; } );
		return $.grep( cfg.placements, function ( pl ) { return ! usedIds[ pl.id ]; } );
	}

	function startPlacement( pl ) {
		state.current = { placement: pl, stepIndex: 0, selections: {} };
		state.phase   = 'step';
		render();
	}

	function editPlacement( idx ) {
		var c  = state.completed[ idx ];
		var pl = null;
		$.each( cfg.placements, function ( i, p ) { if ( p.id === c.placement_id ) { pl = p; } } );
		if ( ! pl ) { return; }

		var selections = {};
		$.each( c.selections, function ( i, sel ) {
			if ( sel.type === 'text' ) {
				selections[ sel.step_id ] = { type: 'text', text: sel.text || sel.value || '', price: parseFloat( sel.price ) || 0 };
			} else {
				selections[ sel.step_id ] = { id: sel.choice_id, name: sel.value, price: parseFloat( sel.price ) || 0, image_url: sel.image_url || '', color: sel.color || '' };
			}
		} );

		state.current = { placement: pl, stepIndex: 0, selections: selections, editIndex: idx };
		state.phase   = 'step';
		render();
	}

	// Returns true when a step has no valid answer yet.
	function isStepMissing( step ) {
		var sel = state.current ? state.current.selections[ step.id ] : undefined;
		if ( sel === undefined ) { return true; }
		if ( step.type === 'text' && ! sel.text ) { return true; }
		return false;
	}

	// ─── Step locking (stacked mode) ──────────────────────────────────────
	function refreshStepLocks() {
		if ( isSequential || ! state.current ) { return; }
		var steps = state.current.placement.steps || [];
		var $sections = $content.find( '.wcpp-step-section' );
		$.each( steps, function ( i, step ) {
			var $sec = $sections.filter( '[data-step-id="' + step.id + '"]' );
			if ( i === 0 ) {
				$sec.removeClass( 'wcpp-step-section--locked' );
			} else {
				var prevDone = ! isStepMissing( steps[ i - 1 ] );
				$sec.toggleClass( 'wcpp-step-section--locked', ! prevDone );
			}
		} );
	}

	// ─── Navigation ────────────────────────────────────────────────────────
	function goNext() {
		if ( state.phase !== 'step' ) { return; }
		var steps = state.current.placement.steps || [];

		if ( isSequential ) {
			var currentStep = steps[ state.current.stepIndex || 0 ];
			if ( currentStep && isStepMissing( currentStep ) ) {
				var $sec = $content.find( '.wcpp-step-section' );
				$sec.addClass( 'wcpp-shake wcpp-step-section--error' );
				setTimeout( function () { $sec.removeClass( 'wcpp-shake' ); }, 500 );
				return;
			}
			var nextIdx = ( state.current.stepIndex || 0 ) + 1;
			if ( nextIdx < steps.length ) {
				state.current.stepIndex = nextIdx;
				render();
			} else {
				finishPlacement();
			}
		} else {
			// Stacked: validate all steps.
			var missing = null;
			$.each( steps, function ( i, step ) {
				if ( missing === null && isStepMissing( step ) ) { missing = step.id; }
			} );

			if ( missing !== null ) {
				var $sec2 = $content.find( '.wcpp-step-section[data-step-id="' + missing + '"]' );
				if ( $sec2.length ) {
					$content.animate( { scrollTop: $content.scrollTop() + $sec2.position().top - 20 }, 250 );
					$sec2.addClass( 'wcpp-shake wcpp-step-section--error' );
					setTimeout( function () { $sec2.removeClass( 'wcpp-shake' ); }, 500 );
				}
				return;
			}
			finishPlacement();
		}
	}

	function goBack() {
		if ( state.phase === 'step' ) {
			if ( isSequential && state.current.stepIndex > 0 ) {
				state.current.stepIndex--;
				render();
			} else if ( state.current.editIndex !== undefined && state.current.editIndex !== null ) {
				state.current = null;
				state.phase   = 'review';
				render();
			} else {
				state.current = null;
				state.phase   = 'select';
				render();
			}
		} else if ( state.phase === 'select' ) {
			if ( state.completed.length ) { state.phase = 'review'; render(); }
		}
	}

	function finishPlacement() {
		var pl   = state.current.placement;
		var sels = [];
		$.each( pl.steps || [], function ( i, step ) {
			var sel = state.current.selections[ step.id ];
			if ( ! sel ) { return; }
			if ( sel.type === 'text' ) {
				sels.push( { step_id: step.id, step_name: step.name, type: 'text', text: sel.text, value: sel.text, price: parseFloat( sel.price ) || 0, image_url: '' } );
			} else {
				sels.push( { step_id: step.id, step_name: step.name, type: sel.color ? 'color' : 'choice', choice_id: sel.id, value: sel.name, price: parseFloat( sel.price ) || 0, image_url: sel.image_url || '', color: sel.color || '' } );
			}
		} );
		var entry = { placement_id: pl.id, placement_name: pl.name, selections: sels };

		if ( state.current.editIndex !== undefined && state.current.editIndex !== null && state.completed[ state.current.editIndex ] ) {
			state.completed[ state.current.editIndex ] = entry;
		} else {
			state.completed.push( entry );
		}

		state.current = null;
		state.phase   = 'review';
		render();
	}

	// ─── Render ───────────────────────────────────────────────────────────
	function render() {
		$content.empty();
		updateChrome();
		if ( state.phase === 'select' )      { renderSelect(); }
		else if ( state.phase === 'step' )   { renderAllSteps(); }
		else if ( state.phase === 'review' ) { renderReview(); }
		$content.scrollTop( 0 );
	}

	function updateChrome() {
		if ( state.phase === 'select' )      { $title.text( i18n.choosePlacement || 'Choose placement' ); }
		else if ( state.phase === 'review' ) { $title.text( i18n.review || 'Review' ); }
		else if ( state.current )            { $title.text( state.current.placement.name ); }

		var showBack = ( state.phase === 'step' ) || ( state.phase === 'select' && state.completed.length );
		showBack ? $back.show() : $back.hide();

		if ( state.phase === 'step' ) {
			$next.show();
			if ( isSequential && state.current ) {
				var steps  = state.current.placement.steps || [];
				var isLast = ( ( state.current.stepIndex || 0 ) >= steps.length - 1 );
				$next.text( isLast ? ( i18n.review || 'Review' ) : ( i18n.next || 'Next' ) );
			} else {
				$next.text( i18n.continue || i18n.next || 'Continue' );
			}
			$addToBag.hide();
		} else if ( state.phase === 'review' ) {
			$next.hide();
			$addToBag.show().prop( 'disabled', state.completed.length === 0 ).text( i18n.addToBag );
		} else {
			$next.hide();
			$addToBag.hide();
		}

		updateProgress();
	}

	function updateProgress() {
		var styleAttr = $panel.attr( 'data-progress-style' );
		var pct = 0, filled = 0, tot = 0;
		if ( state.phase === 'step' && state.current ) {
			var steps = state.current.placement.steps || [];
			tot = steps.length;
			$.each( steps, function ( i, s ) { if ( ! isStepMissing( s ) ) { filled++; } } );
			pct = tot ? Math.round( ( filled / tot ) * 100 ) : 0;
		} else if ( state.phase === 'review' ) {
			pct = 100;
		}
		if ( styleAttr === 'bar' ) {
			$( '#wcpp-progress-fill' ).css( 'width', pct + '%' );
		} else if ( styleAttr === 'dots' && state.phase === 'step' && state.current ) {
			var $d = $( '#wcpp-progress-dots' ).empty();
			for ( var i = 0; i < tot; i++ ) {
				$d.append( $( '<span></span>' ).addClass( i < filled ? 'wcpp-dot-active' : '' ) );
			}
		} else if ( styleAttr === 'text' && state.phase === 'step' && state.current ) {
			$( '#wcpp-progress-text' ).text(
				( i18n.stepOf || 'Step %1$d of %2$d' ).replace( '%1$d', filled ).replace( '%2$d', tot )
			);
		} else if ( styleAttr === 'dots' ) {
			$( '#wcpp-progress-dots' ).empty();
		} else if ( styleAttr === 'text' ) {
			$( '#wcpp-progress-text' ).text( '' );
		}
	}

	// ─── Phase: placement select ────────────────────────────────────────────
	function renderSelect() {
		var avail = availablePlacements();
		$content.append( $( '<h3 class="wcpp-step__heading"></h3>' ).text( i18n.choosePlacementHeading || 'Where would you like it?' ) );

		var listClass = 'wcpp-select-list';
		if ( design.placement_layout === 'grid2' ) { listClass += ' wcpp-select-list--grid2'; }
		var $wrap = $( '<div class="' + listClass + '"></div>' );

		$.each( avail, function ( i, pl ) {
			var $card = $( '<div class="wcpp-select-card" role="button" tabindex="0"></div>' );
			if ( pl.image_url ) {
				$card.append( $( '<div class="wcpp-select-card__img"></div>' ).append(
					$( '<img />' ).attr( 'src', pl.image_url ).attr( 'alt', pl.name )
				) );
			}
			var $foot = $( '<div class="wcpp-select-card__foot"></div>' );
			$foot.append( $( '<span class="wcpp-select-card__name"></span>' ).text( pl.name ) );
			$foot.append( $( '<span class="wcpp-select-card__arrow">&#8250;</span>' ) );
			$card.append( $foot );
			$card.on( 'click.wcppPanel', function () { startPlacement( pl ); } );
			$card.on( 'keydown.wcppPanel', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); startPlacement( pl ); }
			} );
			$wrap.append( $card );
		} );
		$content.append( $wrap );
	}

	// ─── Phase: step ──────────────────────────────────────────────────────
	function renderAllSteps() {
		var steps = state.current.placement.steps || [];

		if ( isSequential ) {
			var idx  = state.current.stepIndex || 0;
			var step = steps[ idx ];
			if ( step ) {
				var $section = $( '<div class="wcpp-step-section"></div>' ).attr( 'data-step-id', step.id );
				renderStepBody( step, $section );
				$content.append( $section );
			}
		} else {
			$.each( steps, function ( i, step ) {
				$content.append(
					$( '<div class="wcpp-step-divider"></div>' ).append(
						$( '<span></span>' ).text( ( i18n.stepN || 'Step %d' ).replace( '%d', i + 1 ) )
					)
				);
				var $section = $( '<div class="wcpp-step-section"></div>' ).attr( 'data-step-id', step.id );
				// Lock steps 2+ until the previous step is answered.
				if ( i > 0 && isStepMissing( steps[ i - 1 ] ) ) {
					$section.addClass( 'wcpp-step-section--locked' );
				}
				renderStepBody( step, $section );
				$content.append( $section );
			} );
		}
	}

	function renderStepBody( step, $into ) {
		var currentSel = state.current.selections[ step.id ];

		// ── Text-input step ─────────────────────────────────────────────────
		if ( step.type === 'text' ) {
			var maxChars = parseInt( step.max_chars, 10 ) || 0;
			var curText  = ( currentSel && currentSel.type === 'text' ) ? currentSel.text : '';

			var $head    = $( '<div class="wcpp-step__head"></div>' );
			$head.append( $( '<h3 class="wcpp-step__heading"></h3>' ).text( step.name ) );
			var $counter = $( '<span class="wcpp-text-counter"></span>' );
			if ( maxChars > 0 ) { $head.append( $counter ); }
			$into.append( $head );

			if ( step.description ) {
				$into.append( $( '<p class="wcpp-step__desc"></p>' ).text( step.description ) );
			}

			var $field = $( '<input type="text" class="wcpp-text-input" />' )
				.attr( 'placeholder', step.placeholder || '' )
				.val( curText );
			if ( maxChars > 0 ) { $field.attr( 'maxlength', maxChars ); }

			var updateCounter = function () {
				if ( maxChars > 0 ) { $counter.text( $field.val().length + ' / ' + maxChars ); }
			};
			$field.on( 'input.wcppPanel', function () {
				var v = $field.val();
				if ( v !== '' ) {
					state.current.selections[ step.id ] = { type: 'text', text: v, price: parseFloat( step.price ) || 0 };
				} else {
					delete state.current.selections[ step.id ];
				}
				$into.removeClass( 'wcpp-step-section--error' );
				updateCounter();
				updateProgress();
				refreshStepLocks();
			} );

			$into.append( $( '<div class="wcpp-text-wrap"></div>' ).append( $field ) );
			if ( parseInt( design.show_choice_price, 10 ) !== 0 && parseFloat( step.price ) > 0 ) {
				$into.append( $( '<div class="wcpp-text-price"></div>' ).text( '+' + money( step.price ) ) );
			}
			updateCounter();
			return;
		}

		$into.append( $( '<h3 class="wcpp-step__heading"></h3>' ).text( step.name ) );
		if ( step.description ) {
			$into.append( $( '<p class="wcpp-step__desc"></p>' ).text( step.description ) );
		}

		// ── Colour step (swatches) ──────────────────────────────────────────
		if ( step.type === 'color' ) {
			var $sw        = $( '<div class="wcpp-swatches"></div>' );
			var showPriceC = parseInt( design.show_choice_price, 10 ) !== 0;
			$.each( step.choices || [], function ( i, choice ) {
				var $item = $( '<div class="wcpp-swatch" role="button" tabindex="0"></div>' );
				$item.append( $( '<span class="wcpp-swatch__dot"></span>' ).css( 'background-color', choice.color || '#000' ) );
				$item.append( $( '<span class="wcpp-swatch__name"></span>' ).text( choice.name ) );
				if ( showPriceC && parseFloat( choice.price ) > 0 ) {
					$item.append( $( '<span class="wcpp-swatch__price"></span>' ).text( '+' + money( choice.price ) ) );
				}
				if ( currentSel && currentSel.id === choice.id ) { $item.addClass( 'wcpp-selected' ); }
				var pickC = function () {
					state.current.selections[ step.id ] = choice;
					$into.removeClass( 'wcpp-step-section--error' );
					$sw.find( '.wcpp-swatch' ).removeClass( 'wcpp-selected' );
					$item.addClass( 'wcpp-selected' );
					updateProgress();
					refreshStepLocks();
				};
				$item.on( 'click.wcppPanel', pickC );
				$item.on( 'keydown.wcppPanel', function ( e ) {
					if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); pickC(); }
				} );
				$sw.append( $item );
			} );
			$into.append( $sw );
			return;
		}

		// ── Choice step (image cards) ───────────────────────────────────────
		var layout = 'wcpp-options';
		if ( design.card_layout === 'grid2' ) { layout += ' wcpp-options--grid2'; }
		else if ( design.card_layout === 'grid3' ) { layout += ' wcpp-options--grid3'; }

		var $wrap     = $( '<div class="' + layout + '"></div>' );
		var showPrice = parseInt( design.show_choice_price, 10 ) !== 0;
		var imgSize   = parseInt( design.card_img_size, 10 );

		$.each( step.choices || [], function ( i, choice ) {
			var $btn = $( '<div class="wcpp-option-btn" role="button" tabindex="0"></div>' );
			if ( choice.image_url && imgSize > 0 ) {
				$btn.append( $( '<div class="wcpp-option-img-wrap"></div>' ).append(
					$( '<img class="wcpp-option-img" />' ).attr( 'src', choice.image_url ).attr( 'alt', choice.name )
				) );
			}
			var $lw = $( '<div class="wcpp-option-label-wrap"></div>' )
				.append( $( '<span class="wcpp-option-name"></span>' ).text( choice.name ) );
			if ( showPrice && parseFloat( choice.price ) > 0 ) {
				$lw.append( $( '<span class="wcpp-option-price"></span>' ).text( '+' + money( choice.price ) ) );
			}
			$btn.append( $lw );
			$btn.append( $( '<span class="wcpp-option-tick">&#10003;</span>' ) );

			if ( currentSel && currentSel.id === choice.id ) { $btn.addClass( 'wcpp-selected' ); }
			var pick = function () {
				state.current.selections[ step.id ] = choice;
				$into.removeClass( 'wcpp-step-section--error' );
				$wrap.find( '.wcpp-option-btn' ).removeClass( 'wcpp-selected' );
				$btn.addClass( 'wcpp-selected' );
				updateProgress();
				refreshStepLocks();
			};
			$btn.on( 'click.wcppPanel', pick );
			$btn.on( 'keydown.wcppPanel', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); pick(); }
			} );
			$wrap.append( $btn );
		} );
		$into.append( $wrap );
	}

	// ─── Phase: review ──────────────────────────────────────────────────────
	function renderReview() {
		$content.append( $( '<h3 class="wcpp-step__heading"></h3>' ).text( i18n.yourChoices || 'Your personalisation' ) );

		var total     = 0;
		var showPrice = parseInt( design.show_choice_price, 10 ) !== 0;

		$.each( state.completed, function ( idx, c ) {
			var $card = $( '<div class="wcpp-review-placement"></div>' );
			var $head = $( '<div class="wcpp-review-placement__head"></div>' );
			$head.append( $( '<span class="wcpp-review-placement__name"></span>' ).text( c.placement_name ) );

			var $actions = $( '<span class="wcpp-review-actions"></span>' );
			var $edit    = $( '<button type="button" class="wcpp-review-edit"></button>' ).text( i18n.edit || 'Edit' );
			$edit.on( 'click.wcppPanel', function () { editPlacement( idx ); } );
			$actions.append( $edit );

			var $del = $( '<button type="button" class="wcpp-review-remove" title="' + ( i18n.remove || 'Remove' ) + '">&#10005;</button>' );
			$del.on( 'click.wcppPanel', function () {
				state.completed.splice( idx, 1 );
				if ( !state.completed.length ) { state.phase = 'select'; }
				render();
			} );
			$actions.append( $del );

			$head.append( $actions );
			$card.append( $head );

			var $ul = $( '<ul class="wcpp-summary-list"></ul>' );
			$.each( c.selections, function ( i, sel ) {
				var $li   = $( '<li class="wcpp-summary-item"></li>' );
				var $left = $( '<div class="wcpp-summary-left"></div>' );
				if ( sel.image_url ) {
					$left.append( $( '<img class="wcpp-summary-img" />' ).attr( 'src', sel.image_url ).attr( 'alt', sel.value ) );
				}
				$left.append( $( '<div class="wcpp-summary-text"></div>' )
					.append( $( '<span class="wcpp-summary-label"></span>' ).text( sel.step_name ) )
					.append( $( '<span class="wcpp-summary-value"></span>' ).text( sel.value || sel.text || '' ) ) );
				$li.append( $left );

				if ( parseFloat( sel.price ) > 0 ) {
					total += parseFloat( sel.price );
					if ( showPrice ) {
						$li.append( $( '<span class="wcpp-summary-price"></span>' ).text( '+' + money( sel.price ) ) );
					}
				}
				$ul.append( $li );
			} );
			$card.append( $ul );
			$content.append( $card );
		} );

		var setFee = parseFloat( cfg.set_price || 0 );
		if ( setFee > 0 && state.completed.length ) {
			total += setFee;
			if ( showPrice ) {
				$content.append( $( '<div class="wcpp-summary-fee-line"></div>' )
					.append( $( '<span></span>' ).text( i18n.feeLabel || 'Personalisation fee' ) )
					.append( $( '<strong></strong>' ).text( '+' + money( setFee ) ) ) );
			}
		}

		if ( availablePlacements().length ) {
			var $add = $( '<button type="button" class="wcpp-add-another"></button>' )
				.text( '＋ ' + ( i18n.addAnother || 'Add another placement' ) );
			$add.on( 'click.wcppPanel', function () { state.phase = 'select'; render(); } );
			$content.append( $add );
		}

		if ( parseInt( design.show_total, 10 ) !== 0 && total > 0 ) {
			$content.append( $( '<div class="wcpp-summary-total"></div>' )
				.append( $( '<span></span>' ).text( ( i18n.total || 'Total:' ) + ' ' ) )
				.append( $( '<strong></strong>' ).text( money( total ) ) ) );
		}
	}

	// ─── Submit ──────────────────────────────────────────────────────────────
	function submitToCart() {
		if ( !state.completed.length ) { return; }
		$addToBag.prop( 'disabled', true ).text( i18n.adding || 'Adding…' );

		$.ajax( {
			url:    wcpp.ajaxUrl,
			method: 'POST',
			data: {
				action:       'wcpp_add_to_cart',
				nonce:        wcpp.nonce,
				product_id:   state.productId,
				variation_id: state.variationId,
				variation:    JSON.stringify( state.variation || {} ),
				set_id:       cfg.id,
				quantity:     state.quantity || 1,
				placements:   JSON.stringify( buildPayload() ),
			},
			success: function ( res ) {
				if ( res.success ) {
					closePanel();
					$( document.body ).trigger( 'wc_fragment_refresh' );
					if ( res.data && res.data.cart_url ) { window.location.href = res.data.cart_url; }
				} else {
					notify( ( res.data && res.data.message ) ? res.data.message : ( i18n.errorGeneric || 'Error' ) );
					$addToBag.prop( 'disabled', false ).text( i18n.addToBag );
				}
			},
			error: function () {
				notify( i18n.errorGeneric || 'Error' );
				$addToBag.prop( 'disabled', false ).text( i18n.addToBag );
			},
		} );
	}

	// Only IDs (and typed text) are sent; server re-derives names + prices.
	function buildPayload() {
		var out = [];
		$.each( state.completed, function ( i, c ) {
			var steps = [];
			$.each( c.selections, function ( j, sel ) {
				if ( sel.type === 'text' ) {
					steps.push( { step_id: sel.step_id, text: sel.text || sel.value || '' } );
				} else {
					steps.push( { step_id: sel.step_id, choice_id: sel.choice_id } );
				}
			} );
			out.push( { placement_id: c.placement_id, steps: steps } );
		} );
		return out;
	}

	// ─── Utils ────────────────────────────────────────────────────────────────
	function notify( msg ) {
		$( '.wcpp-toast' ).remove();
		var $t = $( '<div class="wcpp-toast" role="alert"></div>' );
		$t.append( $( '<span class="wcpp-toast__icon">&#9888;</span>' ) );
		$t.append( $( '<span class="wcpp-toast__msg"></span>' ).text( msg ) );
		$( 'body' ).append( $t );
		setTimeout( function () { $t.addClass( 'wcpp-toast--show' ); }, 10 );
		setTimeout( function () {
			$t.removeClass( 'wcpp-toast--show' );
			setTimeout( function () { $t.remove(); }, 350 );
		}, 4000 );
	}

	function money( amount ) {
		var c   = wcpp.currency || {};
		var dec = ( typeof c.decimals === 'number' ) ? c.decimals : 2;
		var num = parseFloat( amount || 0 ).toFixed( dec );
		var parts = num.split( '.' );
		parts[0] = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, c.thousand || ',' );
		var f = parts.join( c.decimal || '.' );
		var s = c.symbol || '';
		switch ( c.position ) {
			case 'right':       return f + s;
			case 'left_space':  return s + ' ' + f;
			case 'right_space': return f + ' ' + s;
			default:            return s + f;
		}
	}

}( jQuery, window.wcpp || {} ));
