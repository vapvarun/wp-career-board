<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Theme integration module — bridges Reign/BuddyX Kirki colors into WCB CSS variables.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\ThemeIntegration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the active theme's Kirki accent color and outputs an inline style
 * that overrides --wcb-primary so all WCB blocks inherit the theme palette.
 *
 * @since 1.0.0
 */
class ThemeIntegrationModule {

	/** WCB style handle that receives the inline override. */
	private const STYLE_HANDLE = 'wcb-job-listings';

	/**
	 * Boot the module.
	 *
	 * @since 1.0.0
	 */
	public function boot(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'output_color_bridge' ), 20 );
	}

	/**
	 * Detect theme, read accent color, inject CSS variable override.
	 *
	 * @since 1.0.0
	 */
	public function output_color_bridge(): void {
		$color = $this->resolve_primary_color();

		/**
		 * Filter the resolved primary color before it is injected.
		 *
		 * Return an empty string to disable the override entirely.
		 *
		 * @since 1.0.0
		 *
		 * @param string $color Hex color resolved from the active theme, e.g. '#e65c00'.
		 */
		$color = (string) apply_filters( 'wcb_theme_primary_color', $color );

		if ( '' === $color || ! $this->is_wcb_style_loaded() ) {
			return;
		}

		$dark = $this->darken( $color, 15 );
		$css  = ":root { --wcb-primary: {$color}; --wcb-primary-dark: {$dark}; }";
		wp_add_inline_style( self::STYLE_HANDLE, $css );
	}

	/**
	 * Resolve the primary/accent color from the active theme.
	 *
	 * @since 1.0.0
	 *
	 * @return string Hex color string, or empty string if not detected.
	 */
	private function resolve_primary_color(): string {
		$template = get_template();

		if ( 'reign-theme' === $template ) {
			return $this->reign_accent_color();
		}

		if ( in_array( $template, array( 'buddyx', 'buddyx-pro' ), true ) ) {
			return $this->buddyx_accent_color();
		}

		return '';
	}

	/**
	 * Read Reign's scheme-scoped accent color.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function reign_accent_color(): string {
		$scheme = (string) get_theme_mod( 'reign_color_scheme', 'light' );
		$color  = (string) get_theme_mod( $scheme . '-reign_accent_color', '' );

		// Fallback to legacy single-key setting.
		if ( '' === $color ) {
			$color = (string) get_theme_mod( 'reign_accent_color', '' );
		}

		return $this->sanitize_color( $color );
	}

	/**
	 * Read BuddyX's accent color (Free and Pro share the same setting keys).
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function buddyx_accent_color(): string {
		// BuddyX Pro uses scheme-scoped keys like Reign; BuddyX Free uses a flat key.
		$scheme = (string) get_theme_mod( 'buddyx_color_scheme', '' );
		$color  = '' !== $scheme
			? (string) get_theme_mod( $scheme . '-buddyx_accent_color', '' )
			: '';

		if ( '' === $color ) {
			$color = (string) get_theme_mod( 'buddyx_primary_color', '' );
		}

		return $this->sanitize_color( $color );
	}

	/**
	 * Darken a hex color by reducing each RGB channel by $amount.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hex    Hex color, with or without leading '#'.
	 * @param int    $amount 0–255.
	 * @return string Darkened hex color with leading '#'.
	 */
	private function darken( string $hex, int $amount ): string {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$r = max( 0, hexdec( substr( $hex, 0, 2 ) ) - $amount );
		$g = max( 0, hexdec( substr( $hex, 2, 2 ) ) - $amount );
		$b = max( 0, hexdec( substr( $hex, 4, 2 ) ) - $amount );

		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * Validate and normalise a color string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $color Raw value from theme mod.
	 * @return string Valid hex color (with '#') or empty string.
	 */
	private function sanitize_color( string $color ): string {
		$clean = sanitize_hex_color( $color );
		return $clean ?? '';
	}

	/**
	 * Check whether the WCB style handle was enqueued (avoids orphan inline styles).
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function is_wcb_style_loaded(): bool {
		return wp_style_is( self::STYLE_HANDLE, 'enqueued' )
			|| wp_style_is( self::STYLE_HANDLE, 'done' );
	}
}
