/**
 * WC Personalisation Panel — Admin Builder JS
 *
 * Handles the dynamic option/choice builder on the
 * personalisation set edit screen.
 * ES5. IIFE-wrapped. No external dependencies beyond jQuery.
 */
(function ($) {
	'use strict';

	// Global option counter — starts from however many options PHP already rendered.
	var optCount = (typeof wcppOptionCount !== 'undefined') ? wcppOptionCount : 0;

	// Per-option choice counters — keyed by option index.
	var chCount = {};

	// ─── Init ────────────────────────────────────────────────────────────
	$(document).ready(function () {

		// Seed choice counters for options already on the page (from PHP).
		$('.wcpp-option-block').each(function () {
			var idx = $(this).data('option-index');
			chCount[idx] = $(this).find('.wcpp-choice-row').length;
			bindOptionEvents($(this), idx);
		});

		// Add new option.
		$('#wcpp-add-option').on('click', function () {
			var idx = optCount;
			optCount++;
			chCount[idx] = 0;

			var $block = $(buildOptionHTML(idx));
			$('#wcpp-options-container').append($block);
			bindOptionEvents($block, idx);
			updateStepNumbers();

			// Scroll to new option and focus name input.
			$block[0].scrollIntoView({ behavior: 'smooth' });
			$block.find('.wcpp-option-name-input').focus();
		});
	});

	// ─── Bind events to an option block ──────────────────────────────────
	function bindOptionEvents($block, idx) {

		// Delete option.
		$block.find('.wcpp-delete-option').on('click', function () {
			if (window.confirm(wcppAdmin.confirmDelete)) {
				$block.remove();
				updateStepNumbers();
			}
		});

		// Add choice.
		$block.find('.wcpp-add-choice-btn').on('click', function () {
			var chIdx = chCount[idx] || 0;
			chCount[idx] = chIdx + 1;

			var $row = $(buildChoiceHTML(idx, chIdx));
			$block.find('.wcpp-choices-list').append($row);
			bindChoiceEvents($row);

			$row.find('.wcpp-choice-name').focus();
		});

		// Bind existing choice rows.
		$block.find('.wcpp-choice-row').each(function () {
			bindChoiceEvents($(this));
		});
	}

	// ─── Bind events to a choice row ─────────────────────────────────────
	function bindChoiceEvents($row) {

		// Delete choice.
		$row.find('.wcpp-delete-choice').on('click', function () {
			if (window.confirm(wcppAdmin.confirmDelete)) {
				$row.remove();
			}
		});

		// Select image via WP media library.
		$row.find('.wcpp-select-image').on('click', function () {
			var $btn = $(this);
			var $imageId  = $row.find('.wcpp-image-id');
			var $imageUrl = $row.find('.wcpp-image-url');
			var $preview  = $row.find('.wcpp-image-preview');
			var $img      = $preview.find('img');

			var frame = wp.media({
				title:    wcppAdmin.mediaTitle,
				button:   { text: wcppAdmin.mediaButton },
				multiple: false,
				library:  { type: 'image' }
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				// Use medium size if available, fall back to full.
				var url = (attachment.sizes && attachment.sizes.medium)
					? attachment.sizes.medium.url
					: attachment.url;

				$imageId.val(attachment.id);
				$imageUrl.val(attachment.url); // always save full URL.
				$img.attr('src', url);
				$preview.show();
				$btn.text(wcppAdmin.changeImage);
			});

			frame.open();
		});

		// Remove image.
		$row.find('.wcpp-remove-image').on('click', function () {
			$row.find('.wcpp-image-id').val('');
			$row.find('.wcpp-image-url').val('');
			$row.find('.wcpp-image-preview').hide().find('img').attr('src', '');
			$row.find('.wcpp-select-image').text(wcppAdmin.addImage);
		});
	}

	// ─── Update step numbers after add/delete ────────────────────────────
	function updateStepNumbers() {
		$('#wcpp-options-container .wcpp-option-block').each(function (i) {
			$(this).find('.wcpp-step-num').text(i + 1);
		});
	}

	// ─── Build option block HTML ─────────────────────────────────────────
	function buildOptionHTML(idx) {
		return '' +
			'<div class="wcpp-option-block" data-option-index="' + idx + '">' +
				'<div class="wcpp-option-header">' +
					'<span class="wcpp-option-label">Step <span class="wcpp-step-num">' + (idx + 1) + '</span></span>' +
					'<input type="hidden" name="wcpp_options[' + idx + '][id]" value="opt_' + Date.now() + '" />' +
					'<input type="text" name="wcpp_options[' + idx + '][name]" value="" ' +
						'placeholder="' + escAttr(wcppAdmin.namePlaceholder) + '" ' +
						'class="wcpp-option-name-input" />' +
					'<button type="button" class="button wcpp-delete-option" title="Delete">' +
						'<span class="dashicons dashicons-trash"></span>' +
					'</button>' +
				'</div>' +
				'<div class="wcpp-choices-list"></div>' +
				'<button type="button" class="button wcpp-add-choice-btn">&#43; ' + escAttr(wcppAdmin.addChoice) + '</button>' +
			'</div>';
	}

	// ─── Build choice row HTML ────────────────────────────────────────────
	function buildChoiceHTML(optIdx, chIdx) {
		var prefix = 'wcpp_options[' + optIdx + '][choices][' + chIdx + ']';
		var uid    = 'ch_' + Date.now() + '_' + chIdx;

		return '' +
			'<div class="wcpp-choice-row">' +
				'<input type="hidden" name="' + prefix + '[id]" value="' + uid + '" />' +

				'<div class="wcpp-choice-image-wrap">' +
					'<div class="wcpp-image-preview" style="display:none;">' +
						'<img src="" alt="" />' +
						'<button type="button" class="wcpp-remove-image" title="Remove">&#10005;</button>' +
					'</div>' +
					'<button type="button" class="button wcpp-select-image">' + escAttr(wcppAdmin.addImage) + '</button>' +
					'<input type="hidden" name="' + prefix + '[image_id]" class="wcpp-image-id" value="" />' +
					'<input type="hidden" name="' + prefix + '[image_url]" class="wcpp-image-url" value="" />' +
				'</div>' +

				'<div class="wcpp-choice-field">' +
					'<label>Name</label>' +
					'<input type="text" name="' + prefix + '[name]" value="" ' +
						'placeholder="' + escAttr(wcppAdmin.choicePlaceholder) + '" ' +
						'class="wcpp-choice-name" />' +
				'</div>' +

				'<div class="wcpp-choice-field wcpp-choice-field--price">' +
					'<label>Price</label>' +
					'<input type="number" name="' + prefix + '[price]" value="0.00" ' +
						'step="0.01" min="0" placeholder="0.00" class="wcpp-choice-price" />' +
				'</div>' +

				'<button type="button" class="button wcpp-delete-choice" title="Delete">' +
					'<span class="dashicons dashicons-trash"></span>' +
				'</button>' +
			'</div>';
	}

	// ─── Utility ─────────────────────────────────────────────────────────
	function escAttr(str) {
		return String(str || '')
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

}(jQuery));
