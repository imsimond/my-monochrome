(function ($) {
	'use strict';

	$(function () {
		if (typeof window.mymonoSettings === 'undefined') {
			return;
		}

		var settings = window.mymonoSettings;
		var mymonoOption = $('input[value="mymono"]').closest('.color-option');
		var inlineStyleId = settings.styleId || 'mymono-admin-colors-inline-css';

		if (!mymonoOption.length) {
			return;
		}

		mymonoOption.addClass('mymono-color-option');

		var label = mymonoOption.find('label');
		if (!label.length) {
			return;
		}

		var iconBtn = $('<span class="dashicons dashicons-randomize" title="Randomize colors"></span>');
		var pickBtn = $('<span class="dashicons dashicons-color-picker" title="Choose a color"></span>');

		var pickInput = $(
			'<input type="text" class="mymono-color-picker" value="' +
				settings.baseColor +
				'" style="position:absolute;left:-9999px;"/>'
		);
		mymonoOption.append(pickInput);

		var updateInlineVars = function (cssVars) {
			if (!cssVars) {
				return;
			}

			var styleEl = document.getElementById(inlineStyleId);
			if (!styleEl) {
				styleEl = document.createElement('style');
				styleEl.id = inlineStyleId;
				document.head.appendChild(styleEl);
			}

			styleEl.textContent = cssVars;
		};

		var updateShade = function (baseColor) {
			var shadeBox = mymonoOption.find('.color-palette-shade');
			shadeBox.css('background-color', baseColor);
			shadeBox.attr('title', baseColor);
		};

		var handlePaletteResponse = function (response) {
			if (!response || !response.success) {
				return;
			}

			updateInlineVars(response.data.cssVars);
			updateShade(response.data.base);
		};

		pickInput.wpColorPicker({
			hide: true,
			clear: false,
			change: function (event, ui) {
				var newColor = ui.color.toString();
				$.post(
					ajaxurl,
					{
						action: 'mymono_set_palette_ajax',
						_wpnonce: settings.setNonce,
						color: newColor,
						user_id: settings.userId
					},
					handlePaletteResponse
				);
			}
		});

		pickBtn.on('click', function (event) {
			event.preventDefault();
			pickInput.iris('toggle');
		});

		$(document).on('mousedown.mymonoColorPicker', function (event) {
			var pickerContainer = pickInput.closest('.wp-picker-container');
			var target = $(event.target);

			if (!pickerContainer.length) {
				return;
			}

			if (!pickerContainer.find('.iris-picker').is(':visible')) {
				return;
			}

			if (target.closest('.wp-picker-container').length) {
				return;
			}

			if (target.closest('.dashicons-color-picker').length) {
				return;
			}

			if (target.closest('.mymono-color-picker').length) {
				return;
			}

			pickInput.iris('hide');
		});

		iconBtn.on('click', function (event) {
			event.preventDefault();
			$.post(
				ajaxurl,
				{
					action: 'mymono_randomize_palette_ajax',
					_wpnonce: settings.randomNonce,
					user_id: settings.userId
				},
				function (response) {
					handlePaletteResponse(response);
					if (response && response.success) {
						pickInput.wpColorPicker('color', response.data.base);
					}
				}
			);
		});

		label.append(iconBtn, pickBtn);
	});
})(jQuery);
