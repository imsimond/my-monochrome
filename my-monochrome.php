<?php
/**
 * Plugin Name:       My Monochrome Admin Palette
 * Description:       Generate a custom WordPress admin theme from a single colour.
 * Version:           2026.02
 * Author:            Simon Dickson
 * License:           GPL-2.0-or-later
 * Text Domain:       my-monochrome
 *
 * @package           My_Monochrome
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validate hex color.
 *
 * Ensures the given string is a valid 6-digit hex color.
 *
 * @param string $color Hex color string.
 * @return string Validated hex color (defaults to #000000 if invalid).
 */
function mymono_sanitize_hex( $color ) {
	return preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ? $color : '#000000';
}

/**
 * Generate a dark base color with minimum luminance.
 */
function mymono_generate_base_color() {
	do {
		$r         = wp_rand( 0, 90 );
		$g         = wp_rand( 0, 90 );
		$b         = wp_rand( 0, 90 );
		$luminance = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;
	} while ( $luminance < 0.05 );

	return mymono_sanitize_hex( sprintf( '#%02x%02x%02x', $r, $g, $b ) );
}

/**
 * Adjust a hex color by a percentage.
 *
 * @param string $hex     The hex color to adjust.
 * @param float  $percent The percentage to adjust by.
 */
function mymono_adjust_color( $hex, $percent = 0.1 ) {
	$hex = mymono_sanitize_hex( $hex );
	$hex = str_replace( '#', '', $hex );

	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );

	if ( $percent > 0 ) {
		$r = round( $r + ( 255 - $r ) * $percent );
		$g = round( $g + ( 255 - $g ) * $percent );
		$b = round( $b + ( 255 - $b ) * $percent );
	} else {
		$r = round( $r * ( 1 + $percent ) );
		$g = round( $g * ( 1 + $percent ) );
		$b = round( $b * ( 1 + $percent ) );
	}

	$r = max( 0, min( 255, $r ) );
	$g = max( 0, min( 255, $g ) );
	$b = max( 0, min( 255, $b ) );

	return mymono_sanitize_hex( sprintf( '#%02x%02x%02x', $r, $g, $b ) );
}

/**
 * Return black or white for contrast.
 *
 * @param string $hex The hex color.
 */
function mymono_get_contrast_color( $hex ) {
	$hex = mymono_sanitize_hex( $hex );
	$hex = str_replace( '#', '', $hex );

	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );

	$luminance = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;
	return ( $luminance > 0.5 ) ? '#000000' : '#ffffff';
}

/**
 * Generate or retrieve the current user's custom admin color palette.
 *
 * @param int|null $user_id The user ID.
 */
function mymono_get_or_generate_palette( $user_id = null ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	$palette = get_user_meta( $user_id, '_mymono_palette', true );

	if ( ! is_array( $palette ) || empty( $palette['base_color'] ) ) {
		$base_color      = mymono_generate_base_color();
		$text_color      = mymono_get_contrast_color( $base_color );
		$base_lighter    = mymono_adjust_color( $base_color, 0.4 );
		$adminbar_color  = mymono_adjust_color( $base_color, -0.3 );
		$adminbar_text   = mymono_get_contrast_color( $adminbar_color );
		$adminbar_darker = mymono_adjust_color( $adminbar_color, -0.3 );

		$palette = array(
			'base_color'      => $base_color,
			'text_color'      => $text_color,
			'base_lighter'    => $base_lighter,
			'adminbar_color'  => $adminbar_color,
			'adminbar_text'   => $adminbar_text,
			'adminbar_darker' => $adminbar_darker,
		);

		update_user_meta( $user_id, '_mymono_palette', $palette );
	}

	return $palette;
}

/**
 * Register mymono palette option.
 */
function mymono_register_mymono_palette_option() {
	$user_id = get_current_user_id();
	$palette = mymono_get_or_generate_palette( $user_id );

	wp_admin_css_color(
		'mymono',
		__( 'Mono', 'my-monochrome' ),
		'',
		array( $palette['base_color'] )
	);
}
add_action( 'admin_init', 'mymono_register_mymono_palette_option' );

/**
 * Build full CSS string using the palette array.
 */
function mymono_get_admin_css() {
	$palette = mymono_get_or_generate_palette();

	return sprintf(
		'/* Admin menu background */
body.admin-color-mymono #adminmenu,
body.admin-color-mymono #adminmenuback,
body.admin-color-mymono #adminmenuwrap,
body.admin-color-mymono #adminmenu .wp-submenu,
body.admin-color-mymono #adminmenuback,
body.admin-color-mymono #adminmenuwmymono {
	background-color: %1$s;
}
body.admin-color-mymono #adminmenu a {
    color: %2$s;
}
body.admin-color-mymono #adminmenu .wp-has-current-submenu .wp-submenu,
body.admin-color-mymono #adminmenu .wp-has-current-submenu.opensub .wp-submenu,
body.admin-color-mymono #adminmenu .wp-submenu,
body.admin-color-mymono #adminmenu a.wp-has-current-submenu:focus+.wp-submenu {
    background-color: %6$s;
}
body.admin-color-mymono #adminmenu li.wp-has-submenu.wp-not-current-submenu.opensub:hover:after,
body.admin-color-mymono #adminmenu li.wp-has-submenu.wp-not-current-submenu:focus-within:after {
    border-right-color: %6$s;
}
/* Top-level menu and submenu text */
body.admin-color-mymono #adminmenu li.wp-has-submenu .wp-submenu a {
	color: #fff;
    opacity: 0.7;
}
body.admin-color-mymono #adminmenu li.wp-has-current-submenu .wp-submenu li.current,
body.admin-color-mymono #adminmenu li.wp-has-current-submenu .wp-submenu li.current a {
    opacity: 1;
}

/* Top-level menu hover */
body.admin-color-mymono #adminmenu li.menu-top:hover > a,
body.admin-color-mymono #adminmenu li.wp-has-current-submenu:hover > a,
body.admin-color-mymono #adminmenu li.wp-has-submenu .wp-submenu li:hover,
body.admin-color-mymono #adminmenu li.menu-top:hover,
body.admin-color-mymono #adminmenu li.opensub>a.menu-top,
body.admin-color-mymono #adminmenu li>a.menu-top:focus,
body.admin-color-mymono #adminmenu li.wp-has-current-submenu a.wp-has-current-submenu,
body.admin-color-mymono #wpadminbar .ab-top-menu>li.hover>.ab-item,
body.admin-color-mymono #wpadminbar.nojq .quicklinks .ab-top-menu>li>.ab-item:focus,
body.admin-color-mymono #wpadminbar:not(.mobile) .ab-top-menu>li:hover>.ab-item,
body.admin-color-mymono #wpadminbar:not(.mobile) .ab-top-menu>li>.ab-item:focus {
	background-color: rgba(0,0,0,0.8);
	color: #fff;
}
body.admin-color-mymono #adminmenu .wp-submenu a:focus,
body.admin-color-mymono #adminmenu .wp-submenu a:hover,
body.admin-color-mymono #adminmenu a:hover,
body.admin-color-mymono #adminmenu li.menu-top>a:focus {
    color: %2$s;
    opacity: 0.8;
}

/* Icons */
body.admin-color-mymono #adminmenu li.menu-top .wp-menu-image,
body.admin-color-mymono #adminmenu div.wp-menu-image:before,
body.admin-color-mymono #adminmenu li.wp-has-submenu .wp-submenu .wp-menu-image {
	color: inherit;
}
body.admin-color-mymono #adminmenu li.menu-top.wp-has-current-submenu .wp-menu-image,
body.admin-color-mymono #adminmenu li.menu-top.wp-has-current-submenu div.wp-menu-image:before,
body.admin-color-mymono #adminmenu li.wp-has-current-submenu .wp-submenu .wp-menu-image {
	color: rgba(255,255,255,0.9);
}

body.admin-color-mymono #wpadminbar {
	background-color: %4$s;
	color: %5$s;
}
body.admin-color-mymono #wpadminbar a,
body.admin-color-mymono #wpadminbar .ab-item,
body.admin-color-mymono #wpadminbar .ab-label {
	color: %5$s;
}
body.admin-color-mymono #wpadminbar .quicklinks .ab-sub-wmymonoper,
body.admin-color-mymono #wpadminbar .ab-submenu {
	background-color: %4$s;
}
body.admin-color-mymono #wpadminbar ul li:hover {
	background-color: %6$s;
	color: %5$s;
}
body.admin-color-mymono #wpadminbar .quicklinks .ab-sub-wrapper .menupop.hover>a,
body.admin-color-mymono #wpadminbar .quicklinks .menupop ul li a:focus,
body.admin-color-mymono #wpadminbar .quicklinks .menupop ul li a:focus strong,
body.admin-color-mymono #wpadminbar .quicklinks .menupop ul li a:hover,
body.admin-color-mymono #wpadminbar .quicklinks .menupop ul li a:hover strong,
body.admin-color-mymono #wpadminbar .quicklinks .menupop.hover ul li a:focus,
body.admin-color-mymono #wpadminbar .quicklinks .menupop.hover ul li a:hover,
body.admin-color-mymono #wpadminbar .quicklinks .menupop.hover ul li div[tabindex]:focus,
body.admin-color-mymono #wpadminbar .quicklinks .menupop.hover ul li div[tabindex]:hover,
body.admin-color-mymono #wpadminbar li #adminbarsearch.adminbar-focused:before,
body.admin-color-mymono #wpadminbar li .ab-item:focus .ab-icon:before,
body.admin-color-mymono #wpadminbar li .ab-item:focus:before,
body.admin-color-mymono #wpadminbar li a:focus .ab-icon:before,
body.admin-color-mymono #wpadminbar li.hover .ab-icon:before,
body.admin-color-mymono #wpadminbar li.hover .ab-item:before,
body.admin-color-mymono #wpadminbar li:hover #adminbarsearch:before,
body.admin-color-mymono #wpadminbar li:hover .ab-icon:before,
body.admin-color-mymono #wpadminbar li:hover .ab-item:before,
body.admin-color-mymono #wpadminbar.nojs .quicklinks .menupop:hover ul li a:focus,
body.admin-color-mymono #wpadminbar.nojs .quicklinks .menupop:hover ul li a:hover,
body.admin-color-mymono #wpadminbar:not(.mobile)>#wp-toolbar a:focus span.ab-label,
body.admin-color-mymono #wpadminbar:not(.mobile)>#wp-toolbar li:hover span.ab-label,
body.admin-color-mymono #wpadminbar>#wp-toolbar li.hover span.ab-label,
body.admin-color-mymono #wpadminbar .ab-empty-item,
body.admin-color-mymono #wpadminbar a.ab-item,
body.admin-color-mymono #wpadminbar>#wp-toolbar span.ab-label,
body.admin-color-mymono #wpadminbar>#wp-toolbar span.noticon  {
    color: %5$s;
}
/* In-page elements */
body.admin-color-mymono a,
body.admin-color-mymono.wp-core-ui .button-link {
	color: %4$s;
}
body.admin-color-mymono.wp-core-ui .button,
body.admin-color-mymono.wp-core-ui .button-secondary,
.components-button.is-tertiary {
	color: %4$s;
	border-color: %4$s;
}
body.admin-color-mymono.wp-core-ui .button-primary,
body.admin-color-mymono .components-button.is-primary {
	background-color: %1$s;
	border-color: %1$s;
	color: %2$s;
}
body.admin-color-mymono.wp-core-ui .button-primary.focus,
body.admin-color-mymono.wp-core-ui .button-primary.hover,
body.admin-color-mymono.wp-core-ui .button-primary:focus,
body.admin-color-mymono.wp-core-ui .button-primary:hover {
	background-color: %3$s;
	border-color: %3$s;
	color: %2$s;
}
body.admin-color-mymono .wmymono .page-title-action {
	border-color: %1$s;
}
body.admin-color-mymono .components-form-toggle.is-checked .components-form-toggle__track {
	background-color: %1$s;
	border-color: %1$s;
}
body.admin-color-mymono .health-check-tab.active,
body.admin-color-mymono .privacy-settings-tab.active {
	box-shadow: inset 0 -3px %1$s;
}
body.admin-color-mymono #collapse-button {
    color: %2$s;
    opacity: 0.5;
}
',
		$palette['base_color'],
		$palette['text_color'],
		$palette['base_lighter'],
		$palette['adminbar_color'],
		$palette['adminbar_text'],
		$palette['adminbar_darker']
	);
}

/**
 * Enqueue inline CSS.
 */
function mymono_apply_base_admin_colors() {
	if ( get_user_option( 'admin_color' ) !== 'mymono' ) {
		return;
	}

	wp_register_style( 'mymono', false, array(), '2.3.3' );
	wp_enqueue_style( 'mymono' );
	wp_add_inline_style( 'mymono', mymono_get_admin_css() );
}
add_action( 'admin_enqueue_scripts', 'mymono_apply_base_admin_colors' );

/**
 * AJAX endpoint: randomize palette.
 */
add_action(
	'wp_ajax_mymono_randomize_palette_ajax',
	function () {
		check_ajax_referer( 'mymono-randomize-palette' );

		$user_id    = get_current_user_id();
		$base_color = mymono_generate_base_color();
		$palette    = array(
			'base_color'      => $base_color,
			'text_color'      => mymono_get_contrast_color( $base_color ),
			'base_lighter'    => mymono_adjust_color( $base_color, 0.4 ),
			'adminbar_color'  => mymono_adjust_color( $base_color, -0.3 ),
			'adminbar_text'   => mymono_get_contrast_color( mymono_adjust_color( $base_color, -0.3 ) ),
			'adminbar_darker' => mymono_adjust_color( mymono_adjust_color( $base_color, -0.3 ), -0.3 ),
		);

		update_user_meta( $user_id, '_mymono_palette', $palette );

		wp_send_json_success(
			array(
				'css'  => mymono_get_admin_css(),
				'base' => $palette['base_color'],
			)
		);
	}
);

/**
 * AJAX endpoint: Set palette via color picker.
 */
add_action(
	'wp_ajax_mymono_set_palette_ajax',
	function () {
		check_ajax_referer( 'mymono-set-palette' );

		$user_id = get_current_user_id();
		$color   = '#000000';
		if ( isset( $_POST['color'] ) && ! empty( $_POST['color'] ) ) {
			$color = sanitize_hex_color( wp_unslash( $_POST['color'] ) );
		}

		$palette = array(
			'base_color'      => $color,
			'text_color'      => mymono_get_contrast_color( $color ),
			'base_lighter'    => mymono_adjust_color( $color, 0.4 ),
			'adminbar_color'  => mymono_adjust_color( $color, -0.3 ),
			'adminbar_text'   => mymono_get_contrast_color( mymono_adjust_color( $color, -0.3 ) ),
			'adminbar_darker' => mymono_adjust_color( mymono_adjust_color( $color, -0.3 ), -0.3 ),
		);

		update_user_meta( $user_id, '_mymono_palette', $palette );

		wp_send_json_success(
			array(
				'css'  => mymono_get_admin_css(),
				'base' => $palette['base_color'],
			)
		);
	}
);

/**
 * Enqueue color picker assets.
 *
 * @param string $hook The current admin page hook.
 */
function mymono_enqueue_color_picker( $hook ) {
	if ( 'profile.php' !== $hook && 'user-edit.php' !== $hook ) {
		return;
	}

	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );

	wp_register_style( 'mymono-admin', false, array(), '2026.02' );
	wp_enqueue_style( 'mymono-admin' );
	wp_add_inline_style( 'mymono-admin', mymono_get_admin_inline_styles() );

	wp_register_script( 'mymono-admin', '', array( 'jquery', 'wp-color-picker' ), '2026.02', true );
	wp_enqueue_script( 'mymono-admin' );
	wp_add_inline_script( 'mymono-admin', mymono_get_admin_inline_script(), 'after' );
}
add_action( 'admin_enqueue_scripts', 'mymono_enqueue_color_picker' );

/**
 * Build admin inline script.
 *
 * @return string
 */
function mymono_get_admin_inline_script() {
	$palette  = mymono_get_or_generate_palette();
	$settings = array(
		'baseColor'   => $palette['base_color'],
		'setNonce'    => wp_create_nonce( 'mymono-set-palette' ),
		'randomNonce' => wp_create_nonce( 'mymono-randomize-palette' ),
	);

	$settings_json = wp_json_encode( $settings );

	return "jQuery(function($){
	var mymonoSettings = {$settings_json};
	var mymonoOption = $('input[value=\"mymono\"]').closest('.color-option');
	if (!mymonoOption.length) {
		return;
	}
	mymonoOption.addClass('mymono-color-option');

	var label = mymonoOption.find('label');
	if (!label.length) {
		return;
	}

	var iconBtn = $('<span class=\"dashicons dashicons-randomize\" title=\"Randomize colors\"></span>');
	var pickBtn = $('<span class=\"dashicons dashicons-color-picker\" title=\"Choose a color\"></span>');

	var pickInput = $('<input type=\"text\" class=\"mymono-color-picker\" value=\"' + mymonoSettings.baseColor + '\" style=\"position:absolute;left:-9999px;\"/>');
	mymonoOption.append(pickInput);

	pickInput.wpColorPicker({
		hide: true,
		clear: false,
		change: function(event, ui){
			var newColor = ui.color.toString();
			$.post(ajaxurl,{
				action:'mymono_set_palette_ajax',
				_wpnonce:mymonoSettings.setNonce,
				color:newColor
			}, function(response){
				if(response.success){
					$('#mymono-inline').remove();
					$('<style id=\"mymono-inline\"></style>').text(response.data.css).appendTo('head');
					var shadeBox = mymonoOption.find('.color-palette-shade');
					shadeBox.css('background-color',response.data.base);
					shadeBox.attr('title', response.data.base);
				}
			});
		}
	});

	pickBtn.on('click', function(e){
		e.preventDefault();
		pickInput.iris('toggle');
	});

	$(document).on('mousedown.mymonoColorPicker', function(event){
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

	iconBtn.on('click', function(e){
		e.preventDefault();
		$.post(ajaxurl,{
			action:'mymono_randomize_palette_ajax',
			_wpnonce:mymonoSettings.randomNonce
		}, function(response){
			if(response.success){
				$('#mymono-inline').remove();
				$('<style id=\"mymono-inline\"></style>').text(response.data.css).appendTo('head');
				mymonoOption.find('.color-palette-shade').css('background-color',response.data.base);
				pickInput.wpColorPicker('color', response.data.base);
			}
		});
	});

	label.append(iconBtn,pickBtn);
});";
}

/**
 * Build admin inline styles.
 *
 * @return string
 */
function mymono_get_admin_inline_styles() {
	return '.color-option { position: relative; }
.mymono-color-option .dashicons-randomize,
.mymono-color-option .dashicons-color-picker {
	font-size: 16px;
	cursor: pointer;
	margin-left: 5px;
}
.dashicons-randomize:hover,
.dashicons-color-picker:hover {
	color: #00a0d2;
}

/* Hide Iris buttons to prevent layout break. */
.mymono-color-option .wp-picker-container {
	position: absolute !important;
}
.mymono-color-option .wp-picker-container button {
	display: none !important;
}';
}
