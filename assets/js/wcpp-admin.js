/**
 * WC Personalisation Panel — Admin Builder JS
 *
 * Structure: Placement (parent) → Steps → Choices.
 * Field names are keyed by unique IDs (pl_/st_/ch_), so PHP just iterates
 * values — no numeric re-indexing needed.
 */
(function ($) {
	'use strict';

	function uid( prefix ) {
		return prefix + Date.now() + Math.floor( Math.random() * 1000 );
	}

	$( document ).ready( function () {
		// Bind existing placements.
		$( '.wcpp-placement-block' ).each( function () { bindPlacement( $( this ) ); } );

		// Add placement.
		$( '#wcpp-add-placement' ).on( 'click', function () {
			var $pl = $( buildPlacementHTML( uid( 'pl_' ) ) );
			$( '#wcpp-placements-container' ).append( $pl );
			bindPlacement( $pl );
			$pl[0].scrollIntoView( { behavior: 'smooth', block: 'center' } );
			$pl.find( '.wcpp-placement-name-input' ).focus();
		} );
	} );

	// ─── Placement ───────────────────────────────────────────────────────
	function bindPlacement( $pl ) {
		// Placement image picker (lives in the header).
		bindImagePicker( $pl.find( '> .wcpp-placement-header .wcpp-placement-image' ) );

		// Delete placement.
		$pl.find( '> .wcpp-placement-header .wcpp-delete-placement' ).off( 'click' ).on( 'click', function () {
			if ( window.confirm( wcppAdmin.confirmDelete ) ) { $pl.remove(); }
		} );

		// Duplicate placement.
		$pl.find( '> .wcpp-placement-header .wcpp-duplicate-placement' ).off( 'click' ).on( 'click', function () {
			var $copy = duplicatePlacement( $pl );
			$pl.after( $copy );
			bindPlacement( $copy );
			$copy[0].scrollIntoView( { behavior: 'smooth', block: 'center' } );
			$copy.find( '.wcpp-placement-name-input' ).focus();
		} );

		// Add step.
		$pl.find( '> .wcpp-add-step-btn' ).off( 'click' ).on( 'click', function () {
			var plid = $pl.data( 'plid' );
			var $step = $( buildStepHTML( plid, uid( 'st_' ) ) );
			$pl.find( '> .wcpp-steps-container' ).append( $step );
			bindStep( $step, plid );
			$step.find( '.wcpp-option-name-input' ).focus();
		} );

		// Bind existing steps.
		$pl.find( '> .wcpp-steps-container > .wcpp-step-block' ).each( function () {
			bindStep( $( this ), $pl.data( 'plid' ) );
		} );
	}

	// ─── Step ─────────────────────────────────────────────────────────────
	function bindStep( $step, plid ) {
		$step.find( '> .wcpp-option-header .wcpp-delete-option' ).off( 'click' ).on( 'click', function () {
			if ( window.confirm( wcppAdmin.confirmDelete ) ) { $step.remove(); }
		} );

		var stid = $step.data( 'stid' );

		// Type toggle: Choices (image) / Colours / Text.
		$step.find( '> .wcpp-option-header .wcpp-step-type' ).off( 'change' ).on( 'change', function () {
			var val    = $( this ).val();
			var isText = ( val === 'text' );
			$step.attr( 'data-type', val );                 // CSS toggles image vs colour media.
			$step.find( '> .wcpp-step-choices' ).toggle( ! isText );
			$step.find( '> .wcpp-step-text' ).toggle( isText );
		} );

		$step.find( '.wcpp-add-choice-btn' ).off( 'click' ).on( 'click', function () {
			var $row = $( buildChoiceHTML( plid, stid, uid( 'ch_' ) ) );
			$step.find( '.wcpp-choices-list' ).append( $row );
			bindChoice( $row );
			$row.find( '.wcpp-choice-name' ).focus();
		} );

		$step.find( '.wcpp-choices-list > .wcpp-choice-row' ).each( function () {
			bindChoice( $( this ) );
		} );
	}

	// ─── Choice ───────────────────────────────────────────────────────────
	function bindChoice( $row ) {
		$row.find( '.wcpp-delete-choice' ).off( 'click' ).on( 'click', function () {
			if ( window.confirm( wcppAdmin.confirmDelete ) ) { $row.remove(); }
		} );
		bindImagePicker( $row );
	}

	// ─── Shared image picker (choices + placements) ─────────────────────────
	function bindImagePicker( $scope ) {
		if ( !$scope || !$scope.length ) { return; }
		var $select  = $scope.find( '.wcpp-select-image' ).first();
		var $remove  = $scope.find( '.wcpp-remove-image' ).first();
		var addLabel = $scope.hasClass( 'wcpp-placement-image' ) ? wcppAdmin.imageShort : wcppAdmin.addImage;

		$select.off( 'click' ).on( 'click', function () {
			var $btn = $( this );
			var frame = wp.media( {
				title: wcppAdmin.mediaTitle,
				button: { text: wcppAdmin.mediaButton },
				multiple: false,
				library: { type: 'image' }
			} );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				var url = ( att.sizes && att.sizes.medium ) ? att.sizes.medium.url : att.url;
				$scope.find( '.wcpp-image-id' ).first().val( att.id );
				$scope.find( '.wcpp-image-url' ).first().val( att.url );
				$scope.find( '.wcpp-image-preview img' ).first().attr( 'src', url );
				$scope.find( '.wcpp-image-preview' ).first().show();
				$btn.text( wcppAdmin.changeImage );
			} );
			frame.open();
		} );

		$remove.off( 'click' ).on( 'click', function () {
			$scope.find( '.wcpp-image-id' ).first().val( '' );
			$scope.find( '.wcpp-image-url' ).first().val( '' );
			$scope.find( '.wcpp-image-preview' ).first().hide().find( 'img' ).attr( 'src', '' );
			$select.text( addLabel );
		} );
	}

	// ─── Duplicate: clone DOM, re-key all field names with fresh IDs ───────
	function duplicatePlacement( $pl ) {
		var $copy   = $pl.clone();
		var newPlid = uid( 'pl_' );
		var oldPlid = $pl.data( 'plid' );

		$copy.attr( 'data-plid', newPlid ).data( 'plid', newPlid );

		// Placement id field + name.
		$copy.find( '> .wcpp-placement-header input[name$="[id]"]' ).val( newPlid );
		var curName = $copy.find( '.wcpp-placement-name-input' ).val();
		$copy.find( '.wcpp-placement-name-input' ).val( curName ? curName + ' copy' : '' );

		// Re-key every field name from old placement id to the new one, and
		// give each step/choice a fresh id so nothing collides.
		$copy.find( '[name]' ).each( function () {
			var name = $( this ).attr( 'name' );
			if ( name ) {
				$( this ).attr( 'name', name.split( oldPlid ).join( newPlid ) );
			}
		} );

		// Fresh step ids.
		$copy.find( '.wcpp-step-block' ).each( function () {
			var $st = $( this );
			var oldSt = $st.data( 'stid' );
			var newSt = uid( 'st_' );
			$st.attr( 'data-stid', newSt ).data( 'stid', newSt );
			$st.find( '[name]' ).each( function () {
				var nm = $( this ).attr( 'name' );
				if ( nm ) { $( this ).attr( 'name', nm.split( oldSt ).join( newSt ) ); }
			} );
			$st.find( 'input[name$="[id]"]' ).first().val( newSt );
		} );

		// Fresh choice ids.
		$copy.find( '.wcpp-choice-row' ).each( function () {
			var $ch = $( this );
			var oldCh = $ch.find( 'input[name$="[id]"]' ).val();
			var newCh = uid( 'ch_' );
			if ( oldCh ) {
				$ch.find( '[name]' ).each( function () {
					var nm = $( this ).attr( 'name' );
					if ( nm ) { $( this ).attr( 'name', nm.split( oldCh ).join( newCh ) ); }
				} );
			}
		} );

		return $copy;
	}

	// ─── HTML builders ─────────────────────────────────────────────────────
	function buildPlacementHTML( plid ) {
		var b = 'wcpp_placements[' + plid + ']';
		return '' +
			'<div class="wcpp-placement-block" data-plid="' + plid + '">' +
				'<div class="wcpp-placement-header">' +
					'<div class="wcpp-placement-image">' +
						'<div class="wcpp-image-preview" style="display:none;"><img src="" alt="" /><button type="button" class="wcpp-remove-image">&#10005;</button></div>' +
						'<button type="button" class="button wcpp-select-image wcpp-placement-select-image">' + esc( wcppAdmin.imageShort ) + '</button>' +
						'<input type="hidden" name="' + b + '[image_id]" class="wcpp-image-id" value="" />' +
						'<input type="hidden" name="' + b + '[image_url]" class="wcpp-image-url" value="" />' +
					'</div>' +
					'<input type="hidden" name="' + b + '[id]" value="' + plid + '" />' +
					'<input type="text" name="' + b + '[name]" value="" placeholder="' + esc( wcppAdmin.placementPlaceholder ) + '" class="wcpp-placement-name-input" />' +
					'<button type="button" class="button wcpp-duplicate-placement"><span class="dashicons dashicons-admin-page"></span> ' + esc( wcppAdmin.duplicate ) + '</button>' +
					'<button type="button" class="button wcpp-delete-placement"><span class="dashicons dashicons-trash"></span></button>' +
				'</div>' +
				'<div class="wcpp-steps-container"></div>' +
				'<button type="button" class="button wcpp-add-step-btn">&#43; ' + esc( wcppAdmin.addStep ) + '</button>' +
			'</div>';
	}

	function buildStepHTML( plid, stid ) {
		var b = 'wcpp_placements[' + plid + '][steps][' + stid + ']';
		return '' +
			'<div class="wcpp-option-block wcpp-step-block" data-stid="' + stid + '" data-type="choice">' +
				'<div class="wcpp-option-header">' +
					'<span class="wcpp-option-label">' + esc( wcppAdmin.stepLabel ) + '</span>' +
					'<input type="hidden" name="' + b + '[id]" value="' + stid + '" />' +
					'<input type="text" name="' + b + '[name]" value="" placeholder="' + esc( wcppAdmin.stepPlaceholder ) + '" class="wcpp-option-name-input" />' +
					'<select name="' + b + '[type]" class="wcpp-step-type">' +
						'<option value="choice">' + esc( wcppAdmin.typeChoices ) + '</option>' +
						'<option value="text">' + esc( wcppAdmin.typeText ) + '</option>' +
					'</select>' +
					'<button type="button" class="button wcpp-delete-option"><span class="dashicons dashicons-trash"></span></button>' +
				'</div>' +
				'<div class="wcpp-step-choices">' +
					'<div class="wcpp-choices-list"></div>' +
					'<button type="button" class="button wcpp-add-choice-btn">&#43; ' + esc( wcppAdmin.addChoice ) + '</button>' +
				'</div>' +
				'<div class="wcpp-step-text" style="display:none;">' +
					'<div class="wcpp-text-settings">' +
						'<label>' + esc( wcppAdmin.tPlaceholder ) + '<input type="text" name="' + b + '[placeholder]" value="" /></label>' +
						'<label>' + esc( wcppAdmin.tMax ) + '<input type="number" name="' + b + '[max_chars]" value="20" min="1" max="200" style="width:80px;" /></label>' +
						'<label>' + esc( wcppAdmin.tPrice ) + '<input type="number" name="' + b + '[price]" value="0.00" step="0.01" min="0" style="width:90px;" /></label>' +
					'</div>' +
				'</div>' +
			'</div>';
	}

	function buildChoiceHTML( plid, stid, chid ) {
		var p = 'wcpp_placements[' + plid + '][steps][' + stid + '][choices][' + chid + ']';
		return '' +
			'<div class="wcpp-choice-row">' +
				'<input type="hidden" name="' + p + '[id]" value="' + chid + '" />' +
				'<div class="wcpp-choice-image-wrap wcpp-choice-media--image">' +
					'<div class="wcpp-image-preview" style="display:none;"><img src="" alt="" /><button type="button" class="wcpp-remove-image">&#10005;</button></div>' +
					'<button type="button" class="button wcpp-select-image">' + esc( wcppAdmin.addImage ) + '</button>' +
					'<input type="hidden" name="' + p + '[image_id]" class="wcpp-image-id" value="" />' +
					'<input type="hidden" name="' + p + '[image_url]" class="wcpp-image-url" value="" />' +
				'</div>' +
				'<div class="wcpp-choice-color-wrap wcpp-choice-media--color">' +
					'<input type="color" name="' + p + '[color]" value="#000000" class="wcpp-choice-color" />' +
				'</div>' +
				'<div class="wcpp-choice-field"><label>Name</label>' +
					'<input type="text" name="' + p + '[name]" value="" placeholder="' + esc( wcppAdmin.choicePlaceholder ) + '" class="wcpp-choice-name" /></div>' +
				'<div class="wcpp-choice-field wcpp-choice-field--price"><label>Price</label>' +
					'<input type="number" name="' + p + '[price]" value="0.00" step="0.01" min="0" class="wcpp-choice-price" /></div>' +
				'<button type="button" class="button wcpp-delete-choice"><span class="dashicons dashicons-trash"></span></button>' +
			'</div>';
	}

	function esc( s ) {
		return String( s || '' ).replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
	}

}( jQuery ));
