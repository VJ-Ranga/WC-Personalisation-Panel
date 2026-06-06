/**
 * WC Personalisation Panel — Admin Builder JS
 * Handles the dynamic option/choice builder + color picker.
 */
(function ($) {
	'use strict';

	var optCount = (typeof wcppOptionCount !== 'undefined') ? wcppOptionCount : 0;
	var chCount  = {};

	$(document).ready(function () {

		// Init WP color picker.
		$('.wcpp-color-picker').wpColorPicker();

		// Seed choice counters for existing options.
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
			$block[0].scrollIntoView({ behavior: 'smooth' });
			$block.find('.wcpp-option-name-input').focus();
		});
	});

	function bindOptionEvents($block, idx) {
		$block.find('.wcpp-delete-option').on('click', function () {
			if (window.confirm(wcppAdmin.confirmDelete)) {
				$block.remove();
				updateStepNumbers();
			}
		});

		$block.find('.wcpp-add-choice-btn').on('click', function () {
			var chIdx = chCount[idx] || 0;
			chCount[idx] = chIdx + 1;
			var $row = $(buildChoiceHTML(idx, chIdx));
			$block.find('.wcpp-choices-list').append($row);
			bindChoiceEvents($row);
			$row.find('.wcpp-choice-name').focus();
		});

		$block.find('.wcpp-choice-row').each(function () {
			bindChoiceEvents($(this));
		});
	}

	function bindChoiceEvents($row) {
		$row.find('.wcpp-delete-choice').on('click', function () {
			if (window.confirm(wcppAdmin.confirmDelete)) {
				$row.remove();
			}
		});

		$row.find('.wcpp-select-image').on('click', function () {
			var $btn      = $(this);
			var $imageId  = $row.find('.wcpp-image-id');
			var $imageUrl = $row.find('.wcpp-image-url');
			var $preview  = $row.find('.wcpp-image-preview');

			var frame = wp.media({
				title:    wcppAdmin.mediaTitle,
				button:   { text: wcppAdmin.mediaButton },
				multiple: false,
				library:  { type: 'image' }
			});

			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
				$imageId.val(att.id);
				$imageUrl.val(att.url);
				$preview.find('img').attr('src', url);
				$preview.show();
				$btn.text(wcppAdmin.changeImage);
			});

			frame.open();
		});

		$row.find('.wcpp-remove-image').on('click', function () {
			$row.find('.wcpp-image-id').val('');
			$row.find('.wcpp-image-url').val('');
			$row.find('.wcpp-image-preview').hide().find('img').attr('src', '');
			$row.find('.wcpp-select-image').text(wcppAdmin.addImage);
		});
	}

	function updateStepNumbers() {
		$('#wcpp-options-container .wcpp-option-block').each(function (i) {
			$(this).find('.wcpp-step-num').text(i + 1);
		});
	}

	function buildOptionHTML(idx) {
		return '<div class="wcpp-option-block" data-option-index="' + idx + '">' +
			'<div class="wcpp-option-header">' +
				'<span class="wcpp-option-label">Step <span class="wcpp-step-num">' + (idx + 1) + '</span></span>' +
				'<input type="hidden" name="wcpp_options[' + idx + '][id]" value="opt_' + Date.now() + '" />' +
				'<input type="text" name="wcpp_options[' + idx + '][name]" value="" ' +
					'placeholder="' + escAttr(wcppAdmin.namePlaceholder) + '" class="wcpp-option-name-input" />' +
				'<button type="button" class="button wcpp-delete-option"><span class="dashicons dashicons-trash"></span></button>' +
			'</div>' +
			'<div class="wcpp-choices-list"></div>' +
			'<button type="button" class="button wcpp-add-choice-btn">&#43; ' + escAttr(wcppAdmin.addChoice) + '</button>' +
		'</div>';
	}

	function buildChoiceHTML(optIdx, chIdx) {
		var prefix = 'wcpp_options[' + optIdx + '][choices][' + chIdx + ']';
		var uid    = 'ch_' + Date.now() + '_' + chIdx;
		return '<div class="wcpp-choice-row">' +
			'<input type="hidden" name="' + prefix + '[id]" value="' + uid + '" />' +
			'<div class="wcpp-choice-image-wrap">' +
				'<div class="wcpp-image-preview" style="display:none;">' +
					'<img src="" alt="" />' +
					'<button type="button" class="wcpp-remove-image">&#10005;</button>' +
				'</div>' +
				'<button type="button" class="button wcpp-select-image">' + escAttr(wcppAdmin.addImage) + '</button>' +
				'<input type="hidden" name="' + prefix + '[image_id]" class="wcpp-image-id" value="" />' +
				'<input type="hidden" name="' + prefix + '[image_url]" class="wcpp-image-url" value="" />' +
			'</div>' +
			'<div class="wcpp-choice-field">' +
				'<label>Name</label>' +
				'<input type="text" name="' + prefix + '[name]" value="" ' +
					'placeholder="' + escAttr(wcppAdmin.choicePlaceholder) + '" class="wcpp-choice-name" />' +
			'</div>' +
			'<div class="wcpp-choice-field wcpp-choice-field--price">' +
				'<label>Price</label>' +
				'<input type="number" name="' + prefix + '[price]" value="0.00" step="0.01" min="0" class="wcpp-choice-price" />' +
			'</div>' +
			'<button type="button" class="button wcpp-delete-choice"><span class="dashicons dashicons-trash"></span></button>' +
		'</div>';
	}

	function escAttr(str) {
		return String(str || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
	}

}(jQuery));
