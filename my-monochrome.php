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

if ( ! defined( 'MYMONO_VERSION' ) ) {
	define( 'MYMONO_VERSION', '2026.02' );
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
	if ( preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ) {
		return $color;
	}

	return '#000000';
}

/**
 * Generate a dark base color with minimum luminance.
 *
 * @return string
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
 * @return string
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
 * @return string
 */
function mymono_get_contrast_color( $hex ) {
	$hex = mymono_sanitize_hex( $hex );
	$hex = str_replace( '#', '', $hex );

	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );

	$luminance = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;
	if ( $luminance > 0.5 ) {
		return '#000000';
	}

	return '#ffffff';
}

/**
 * Generate or retrieve the current user's custom admin color palette.
 *
 * @param int|null $user_id The user ID.
 * @return array
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
 * Resolve the user ID being edited on profile screens.
 *
 * Falls back to the current user if no valid target is available.
 *
 * @return int
 */
function mymono_get_profile_target_user_id() {
	$user_id = 0;

	$filtered_user_id = filter_input( INPUT_GET, 'user_id', FILTER_VALIDATE_INT );
	if ( false !== $filtered_user_id && null !== $filtered_user_id ) {
		$user_id = absint( $filtered_user_id );
	}

	if ( $user_id && current_user_can( 'edit_user', $user_id ) ) {
		return $user_id;
	}

	return get_current_user_id();
}

/**
 * Resolve the user ID for profile-related AJAX requests.
 *
 * Falls back to the current user if the supplied ID is missing or unauthorized.
 *
 * @param int $user_id Candidate user ID.
 * @return int
 */
function mymono_get_profile_target_user_id_from_request( $user_id ) {
	$user_id = absint( $user_id );

	if ( $user_id && current_user_can( 'edit_user', $user_id ) ) {
		return $user_id;
	}

	return get_current_user_id();
}

/**
 * Register mymono palette option.
 *
 * @return void
 */
function mymono_register_mymono_palette_option() {
	$user_id = mymono_get_profile_target_user_id();
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
 * Get a cache-busted asset version.
 *
 * @param string $relative_path Relative path within the plugin.
 * @return string
 */
function mymono_get_asset_version( $relative_path ) {
	$relative_path = ltrim( $relative_path, '/' );
	$asset_path    = plugin_dir_path( __FILE__ ) . $relative_path;

	if ( file_exists( $asset_path ) ) {
		return (string) filemtime( $asset_path );
	}

	return MYMONO_VERSION;
}

/**
 * Build CSS variables using the palette array.
 *
 * @param array|null $palette The palette values.
 * @return string
 */
function mymono_get_admin_css_vars( $palette = null ) {
	if ( ! is_array( $palette ) ) {
		$palette = mymono_get_or_generate_palette();
	}

	return sprintf(
		'body.admin-color-mymono{--mymono-base:%1$s;--mymono-text:%2$s;--mymono-base-lighter:%3$s;--mymono-adminbar:%4$s;--mymono-adminbar-text:%5$s;--mymono-adminbar-darker:%6$s;}',
		$palette['base_color'],
		$palette['text_color'],
		$palette['base_lighter'],
		$palette['adminbar_color'],
		$palette['adminbar_text'],
		$palette['adminbar_darker']
	);
}

/**
 * Enqueue admin palette styles.
 *
 * @return void
 */
function mymono_apply_base_admin_colors() {
	if ( get_user_option( 'admin_color' ) !== 'mymono' ) {
		return;
	}

	$handle = 'mymono-admin-colors';
	$src    = plugin_dir_url( __FILE__ ) . 'assets/css/admin-colors.css';

	wp_enqueue_style( $handle, $src, array(), mymono_get_asset_version( 'assets/css/admin-colors.css' ) );
	wp_add_inline_style( $handle, mymono_get_admin_css_vars() );
}
add_action( 'admin_enqueue_scripts', 'mymono_apply_base_admin_colors' );

/**
 * AJAX endpoint: randomize palette.
 *
 * @return void
 */
function mymono_handle_randomize_palette_ajax() {
	check_ajax_referer( 'mymono-randomize-palette' );

	$user_id          = 0;
	$filtered_user_id = filter_input( INPUT_POST, 'user_id', FILTER_VALIDATE_INT );
	if ( false !== $filtered_user_id && null !== $filtered_user_id ) {
		$user_id = absint( $filtered_user_id );
	}
	$user_id    = mymono_get_profile_target_user_id_from_request( $user_id );
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
			'cssVars' => mymono_get_admin_css_vars( $palette ),
			'base'    => $palette['base_color'],
		)
	);
}
add_action( 'wp_ajax_mymono_randomize_palette_ajax', 'mymono_handle_randomize_palette_ajax' );

/**
 * AJAX endpoint: Set palette via color picker.
 *
 * @return void
 */
function mymono_handle_set_palette_ajax() {
	check_ajax_referer( 'mymono-set-palette' );

	$user_id          = 0;
	$filtered_user_id = filter_input( INPUT_POST, 'user_id', FILTER_VALIDATE_INT );
	if ( false !== $filtered_user_id && null !== $filtered_user_id ) {
		$user_id = absint( $filtered_user_id );
	}
	$user_id = mymono_get_profile_target_user_id_from_request( $user_id );
	$color   = '#000000';
	if ( isset( $_POST['color'] ) && ! empty( $_POST['color'] ) ) {
		$color = sanitize_hex_color( wp_unslash( $_POST['color'] ) );
	}
	if ( empty( $color ) ) {
		$color = '#000000';
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
			'cssVars' => mymono_get_admin_css_vars( $palette ),
			'base'    => $palette['base_color'],
		)
	);
}
add_action( 'wp_ajax_mymono_set_palette_ajax', 'mymono_handle_set_palette_ajax' );

/**
 * Enqueue color picker assets.
 *
 * @param string $hook The current admin page hook.
 * @return void
 */
function mymono_enqueue_color_picker( $hook ) {
	if ( 'profile.php' !== $hook && 'user-edit.php' !== $hook ) {
		return;
	}

	$user_id = mymono_get_profile_target_user_id();
	if ( ! $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );

	$style_handle = 'mymono-admin-profile';
	$style_src    = plugin_dir_url( __FILE__ ) . 'assets/css/admin-profile.css';

	wp_enqueue_style( $style_handle, $style_src, array(), mymono_get_asset_version( 'assets/css/admin-profile.css' ) );

	$script_handle = 'mymono-admin-profile';
	$script_src    = plugin_dir_url( __FILE__ ) . 'assets/js/admin-profile.js';

	wp_enqueue_script( $script_handle, $script_src, array( 'jquery', 'wp-color-picker' ), mymono_get_asset_version( 'assets/js/admin-profile.js' ), true );

	$palette  = mymono_get_or_generate_palette( $user_id );
	$settings = array(
		'baseColor'   => $palette['base_color'],
		'setNonce'    => wp_create_nonce( 'mymono-set-palette' ),
		'randomNonce' => wp_create_nonce( 'mymono-randomize-palette' ),
		'userId'      => $user_id,
		'styleId'     => 'mymono-admin-colors-inline-css',
	);

	wp_add_inline_script(
		$script_handle,
		'window.mymonoSettings = ' . wp_json_encode( $settings ) . ';',
		'before'
	);
}
add_action( 'admin_enqueue_scripts', 'mymono_enqueue_color_picker' );
