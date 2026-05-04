<?php
/**
 * Theme accent auto-bridge.
 *
 * Reads the active theme's well-known customizer color setting and writes
 * it as `--wcb-primary` in an inline `<style>` block. Lets sites running
 * Astra / Kadence / GeneratePress / Neve / OceanWP / Blocksy automatically
 * pick up the customer's brand color without writing any CSS.
 *
 * Bundle partners (Reign, BuddyX, BuddyX Pro) are skipped — their dedicated
 * compat CSS files in `integrations/` already handle the bidirectional
 * token bridge with deeper integration than this generic helper provides.
 *
 * Defaults to no-op when:
 *  - Active theme is a bundle partner (their bridge wins).
 *  - Active theme isn't in the recognised list.
 *  - The theme is recognised but the customer hasn't customised the colour.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme-aware accent color injector.
 *
 * @since 1.1.0
 */
final class ThemeAccentBridge {

	/**
	 * Themes that ship their own dedicated compat bridge — never run the
	 * generic accent injector for these (the dedicated file does more).
	 *
	 * @since 1.1.0
	 * @var string[]
	 */
	private const BUNDLE_PARTNERS = array(
		'reign-theme',
		'reign',
		'buddyx',
		'buddyx-pro',
	);

	/**
	 * Wire up — print the inline style during front-end head emission.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'wp_head', array( $this, 'print_inline_style' ), 100 );
	}

	/**
	 * Output `<style id="wcb-theme-accent">…</style>` when we can resolve
	 * an accent color for the active theme.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function print_inline_style(): void {
		$primary = $this->resolve_primary();
		if ( null === $primary ) {
			return;
		}

		$dark = $this->shift_lightness( $primary, -10 );
		$soft = $this->shift_lightness( $primary, 88 );
		$ring = $this->to_rgba( $primary, 0.18 );

		printf(
			'<style id="wcb-theme-accent">:root{--wcb-primary:%1$s;--wcb-primary-dark:%2$s;--wcb-primary-soft:%3$s;--wcb-primary-ring:%4$s;}</style>',
			esc_attr( $primary ),
			esc_attr( $dark ),
			esc_attr( $soft ),
			esc_attr( $ring )
		);
	}

	/**
	 * Determine the accent color, or null when we should leave defaults alone.
	 *
	 * @since 1.1.0
	 * @return string|null Hex string (e.g. "#2563eb") or null.
	 */
	private function resolve_primary(): ?string {
		$template = get_template();

		if ( in_array( $template, self::BUNDLE_PARTNERS, true ) ) {
			return null;
		}

		$accent = match ( $template ) {
			'astra'         => $this->astra_accent(),
			'kadence'       => $this->kadence_accent(),
			'generatepress' => $this->generatepress_accent(),
			'neve'          => $this->neve_accent(),
			'oceanwp'       => $this->oceanwp_accent(),
			'blocksy'       => $this->blocksy_accent(),
			default         => null,
		};

		/**
		 * Final override — return a hex color string to force the accent,
		 * or null to fall through to plugin defaults.
		 *
		 * @since 1.1.0
		 *
		 * @param string|null $accent   Resolved theme accent (hex) or null.
		 * @param string      $template Active theme template slug.
		 */
		$accent = apply_filters( 'wcb_theme_accent_primary', $accent, $template );

		return is_string( $accent ) && '' !== $accent ? $accent : null;
	}

	/**
	 * Astra: option `astra-settings.theme-color` (Astra 4.x stores brand color here).
	 *
	 * @since 1.1.0
	 * @return string|null
	 */
	private function astra_accent(): ?string {
		$settings = (array) get_option( 'astra-settings', array() );
		$value    = (string) ( $settings['theme-color'] ?? '' );
		return $this->normalise_hex( $value );
	}

	/**
	 * Kadence: `kadence_global_palette` JSON option, palette key 1 (brand).
	 *
	 * @since 1.1.0
	 * @return string|null
	 */
	private function kadence_accent(): ?string {
		$raw = get_option( 'kadence_global_palette' );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded['palette'] ) ) {
			return null;
		}
		foreach ( (array) $decoded['palette'] as $entry ) {
			if ( is_array( $entry ) && isset( $entry['slug'], $entry['color'] ) && 'palette1' === $entry['slug'] ) {
				return $this->normalise_hex( (string) $entry['color'] );
			}
		}
		return null;
	}

	/**
	 * GeneratePress: `generate_settings.form_button_background_color` is the
	 * primary CTA color in GP 3.x. Falls back to the link color.
	 *
	 * @since 1.1.0
	 * @return string|null
	 */
	private function generatepress_accent(): ?string {
		$settings   = (array) get_option( 'generate_settings', array() );
		$candidates = array(
			'form_button_background_color',
			'navigation_background_color',
			'link_color',
		);
		foreach ( $candidates as $key ) {
			$value = (string) ( $settings[ $key ] ?? '' );
			$hex   = $this->normalise_hex( $value );
			if ( null !== $hex ) {
				return $hex;
			}
		}
		return null;
	}

	/**
	 * Neve: theme_mod `neve_link_color` for primary brand color.
	 *
	 * @since 1.1.0
	 * @return string|null
	 */
	private function neve_accent(): ?string {
		return $this->normalise_hex( (string) get_theme_mod( 'neve_link_color', '' ) );
	}

	/**
	 * OceanWP: theme_mod `ocean_primary_color`.
	 *
	 * @since 1.1.0
	 * @return string|null
	 */
	private function oceanwp_accent(): ?string {
		return $this->normalise_hex( (string) get_theme_mod( 'ocean_primary_color', '' ) );
	}

	/**
	 * Blocksy: option `blocksy_options.colorPalette` (modern Blocksy stores
	 * the palette here; theme.json merge handles editor side).
	 *
	 * @since 1.1.0
	 * @return string|null
	 */
	private function blocksy_accent(): ?string {
		$settings = (array) get_option( 'blocksy_options', array() );
		$palette  = $settings['colorPalette']['color1']['color'] ?? null;
		return is_string( $palette ) ? $this->normalise_hex( $palette ) : null;
	}

	/**
	 * Validate + normalise a colour value to `#rrggbb` form, or return null.
	 *
	 * @since 1.1.0
	 * @param string $value Raw color value from a theme option/mod.
	 * @return string|null
	 */
	private function normalise_hex( string $value ): ?string {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}
		if ( preg_match( '/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $value, $m ) ) {
			$hex = $m[1];
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			return '#' . strtolower( $hex );
		}
		return null;
	}

	/**
	 * Shift a hex color's lightness by `$delta` percentage points.
	 *
	 * Negative `$delta` darkens (used for `--wcb-primary-dark`).
	 * Large positive `$delta` (≥80) is treated as "wash to near-white"
	 * for the soft tint variant.
	 *
	 * @since 1.1.0
	 * @param string $hex   Validated `#rrggbb` color.
	 * @param int    $delta Percentage shift (-100..100). Positive lightens.
	 * @return string
	 */
	private function shift_lightness( string $hex, int $delta ): string {
		[ $r, $g, $b ] = $this->hex_to_rgb( $hex );
		if ( $delta >= 80 ) {
			// Soft tint — mix with white at $delta% (≈ light wash).
			$mix_pct = ( $delta / 100 );
			$r       = (int) round( $r + ( ( 255 - $r ) * $mix_pct ) );
			$g       = (int) round( $g + ( ( 255 - $g ) * $mix_pct ) );
			$b       = (int) round( $b + ( ( 255 - $b ) * $mix_pct ) );
		} else {
			$factor = $delta / 100;
			if ( $factor < 0 ) {
				$r = (int) round( $r * ( 1 + $factor ) );
				$g = (int) round( $g * ( 1 + $factor ) );
				$b = (int) round( $b * ( 1 + $factor ) );
			} else {
				$r = (int) round( $r + ( ( 255 - $r ) * $factor ) );
				$g = (int) round( $g + ( ( 255 - $g ) * $factor ) );
				$b = (int) round( $b + ( ( 255 - $b ) * $factor ) );
			}
		}
		return sprintf( '#%02x%02x%02x', max( 0, min( 255, $r ) ), max( 0, min( 255, $g ) ), max( 0, min( 255, $b ) ) );
	}

	/**
	 * Convert hex to rgba() with the given alpha.
	 *
	 * @since 1.1.0
	 * @param string $hex   Validated `#rrggbb` color.
	 * @param float  $alpha 0.0–1.0.
	 * @return string
	 */
	private function to_rgba( string $hex, float $alpha ): string {
		[ $r, $g, $b ] = $this->hex_to_rgb( $hex );
		return sprintf( 'rgba(%d,%d,%d,%s)', $r, $g, $b, rtrim( rtrim( number_format( $alpha, 2, '.', '' ), '0' ), '.' ) );
	}

	/**
	 * Decompose `#rrggbb` into [r, g, b] integers.
	 *
	 * @since 1.1.0
	 * @param string $hex Validated `#rrggbb` color.
	 * @return array{0:int,1:int,2:int}
	 */
	private function hex_to_rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		return array(
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		);
	}
}
