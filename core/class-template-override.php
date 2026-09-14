<?php
/**
 * Template-override resolution shared by every WCB single/archive view.
 *
 * @package WP_Career_Board
 * @since   1.7.1
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides whether a `template_include` filter should leave a template alone.
 *
 * Every WCB module that serves its own CPT template carried the same guard:
 *
 *     if ( str_contains( $template, 'wp-career-board' ) ) { return $template; }
 *
 * That is a plugin-PATH sniff, not a theme check. It exists so the bundled
 * Reign / BuddyX Pro integrations win, because their template paths happen to
 * contain the plugin slug. But `template_include` runs after WordPress has
 * already resolved the hierarchy, so a theme's own `single-wcb_job.php` arrives
 * as `/themes/mytheme/single-wcb_job.php`, fails that test, and gets replaced by
 * the plugin's copy - while the docblocks promised the opposite, that a theme
 * shipping its own template "continues to win via WP's template hierarchy".
 *
 * @since 1.7.1
 */
class TemplateOverride {

	/**
	 * Whether a template path was resolved from the active theme.
	 *
	 * Used by the bundled theme integrations, which run on `single_template` /
	 * `archive_template` - filters that receive whatever WordPress's hierarchy
	 * already found. Returning an integration template unconditionally discarded
	 * a theme's own `single-wcb_job.php`, which is the layer that actually broke
	 * documented overrides.
	 *
	 * @since 1.7.1
	 *
	 * @param  string $template Template path.
	 * @return bool
	 */
	public static function is_theme_template( string $template ): bool {
		if ( '' === $template ) {
			return false;
		}

		$resolved = wp_normalize_path( $template );

		foreach ( array_unique( array( get_stylesheet_directory(), get_template_directory() ) ) as $theme_dir ) {
			if ( ! is_string( $theme_dir ) || '' === $theme_dir ) {
				continue;
			}
			if ( str_starts_with( $resolved, trailingslashit( wp_normalize_path( $theme_dir ) ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the already-resolved template should be kept as-is.
	 *
	 * True when the theme (child or parent) resolved it, or when one of the
	 * bundled theme integrations supplied it.
	 *
	 * @since 1.7.1
	 *
	 * @param  string $template Template path from `template_include`.
	 * @return bool
	 */
	public static function keep( string $template ): bool {
		if ( '' === $template ) {
			return false;
		}

		// A theme shipping its own template wins. WordPress's hierarchy already
		// chose it. Parent as well as child, so a child theme need not copy a
		// parent's template just to keep it.
		if ( self::is_theme_template( $template ) ) {
			return true;
		}

		$resolved = wp_normalize_path( $template );

		// The bundled integrations (Reign, BuddyX Pro) set their own template via
		// single_template and live inside the plugin directory. Preserved so this
		// change is purely additive to the behaviour that already worked.
		return str_contains( $resolved, 'wp-career-board' );
	}
}
