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
	public static function is_theme_template( string $template, string $expected_basename = '' ): bool {
		if ( '' === $template ) {
			return false;
		}

		$resolved     = wp_normalize_path( $template );
		$in_theme_dir = false;

		foreach ( array_unique( array( get_stylesheet_directory(), get_template_directory() ) ) as $theme_dir ) {
			if ( ! is_string( $theme_dir ) || '' === $theme_dir ) {
				continue;
			}
			if ( str_starts_with( $resolved, trailingslashit( wp_normalize_path( $theme_dir ) ) ) ) {
				$in_theme_dir = true;
				break;
			}
		}

		if ( ! $in_theme_dir ) {
			return false;
		}

		// A generic fallback the hierarchy had nothing more specific to serve
		// (single.php, page.php, archive.php, singular.php, index.php…) is not
		// the theme opting in to a WCB page; only a file matching the exact slot
		// name is, e.g. single-wcb_job.php for a wcb_job single. Without this,
		// every theme lacking a CPT-specific template silently loses the
		// canonical container/hero to its own generic wrapper (Basecamp
		// 10348287376 / 10348287177 / 10348287589 / 10348289393).
		return '' === $expected_basename || basename( $resolved ) === $expected_basename;
	}

	/**
	 * Whether the active theme is a block theme (templates/*.html).
	 *
	 * A block theme renders through WordPress's block template canvas, where our
	 * blocks already reach the page (the `the_content` injection and the block
	 * markup in each page). A plugin PHP template on top of it calls
	 * `get_header()` (a bare fallback there) and skips the script-module import
	 * map, so the page is blank or dead. Hybrid themes (Reign, BuddyX: theme.json
	 * plus PHP templates) are not block themes and are unaffected.
	 *
	 * @since 1.8.0
	 * @return bool
	 */
	public static function block_theme(): bool {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
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
	public static function keep( string $template, string $expected_basename = '' ): bool {
		if ( '' === $template ) {
			return false;
		}

		// On a block theme WordPress resolved the block template canvas: keep it.
		if ( self::block_theme() ) {
			return true;
		}

		// A theme shipping its own template wins. WordPress's hierarchy already
		// chose it. Parent as well as child, so a child theme need not copy a
		// parent's template just to keep it.
		if ( self::is_theme_template( $template, $expected_basename ) ) {
			return true;
		}

		$resolved = wp_normalize_path( $template );

		// The bundled integrations (Reign, BuddyX Pro) set their own template via
		// single_template and live inside one of the two plugin directories.
		// Compared against the actual directory, not a substring of the path -
		// an install path that happens to contain the plugin slug (a demo
		// folder, a site named after the plugin) otherwise falsely matches a
		// theme's own generic template (Basecamp 10350370251).
		foreach ( array( WCB_DIR, defined( 'WCBP_DIR' ) ? WCBP_DIR : null ) as $plugin_dir ) {
			if ( ! is_string( $plugin_dir ) || '' === $plugin_dir ) {
				continue;
			}
			if ( str_starts_with( $resolved, trailingslashit( wp_normalize_path( $plugin_dir ) ) ) ) {
				return true;
			}
		}

		return false;
	}
}
