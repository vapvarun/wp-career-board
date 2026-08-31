<?php
/**
 * Theme compatibility stylesheet loader.
 *
 * Bundle-partner themes (Reign, BuddyX, BuddyX Pro) each ship a small token
 * bridge in `integrations/<theme>/assets/`. Those files have to load on every
 * request that renders WCB markup — and WCB markup is not only in the queried
 * post's content.
 *
 * Each integration used to gate its own enqueue on "is this a WCB CPT page, or
 * does `$post->post_content` contain a WCB block comment". That check cannot
 * see a block placed in a widget area / FSE template part / page-builder
 * region, because none of those live in `post_content` — so a Recent Jobs
 * sidebar widget on an ordinary page rendered with no token bridge and fell
 * back to raw defaults (Basecamp 10174706441, Zoho #41269).
 *
 * The fix is to stop guessing where a block might be. WordPress already knows
 * exactly when a block renders, and this plugin already relies on that
 * mechanism for `wcb-frontend-tokens` — so attach the compat handle to every
 * registered WCB block type the same way and let core decide. Free
 * (`wp-career-board/*`) and Pro (`wcb/*`) blocks are both covered by reading
 * the block registry rather than a hardcoded list.
 *
 * Plugin-templated screens (the CPT single/archive/taxonomy views) render WCB
 * markup from PHP templates with no block involved, so those keep an explicit
 * enqueue.
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
 * Loads a theme integration's compat stylesheet wherever WCB markup appears.
 *
 * @since 1.7.1
 */
final class ThemeCompat {

	/**
	 * Post types whose single/archive views are rendered by WCB templates.
	 *
	 * @since 1.7.1
	 * @var string[]
	 */
	private const CPTS = array( 'wcb_job', 'wcb_application', 'wcb_company', 'wcb_resume' );

	/**
	 * Taxonomy archives rendered by WCB templates.
	 *
	 * @since 1.7.1
	 * @var string[]
	 */
	private const TAXONOMIES = array( 'wcb_category', 'wcb_job_type', 'wcb_tag', 'wcb_location', 'wcb_experience' );

	/**
	 * Block-name prefixes owned by WCB. Free registers `wp-career-board/*`,
	 * Pro registers `wcb/*`.
	 *
	 * @since 1.7.1
	 * @var string[]
	 */
	private const BLOCK_PREFIXES = array( 'wp-career-board/', 'wcb/' );

	/**
	 * Register a compat stylesheet and wire it to every surface that can
	 * render WCB markup.
	 *
	 * Call from an integration's `boot()`. Both hooks are registered up front:
	 * `init` at 20 runs after Free and Pro have registered their blocks (both
	 * register on `init` at the default priority), and `wp_enqueue_scripts`
	 * covers the PHP-templated CPT screens.
	 *
	 * @since 1.7.1
	 *
	 * @param string   $handle Stylesheet handle.
	 * @param string   $src    Absolute URL to the stylesheet.
	 * @param string[] $deps   Handles this stylesheet must load after.
	 * @return void
	 */
	public static function register( string $handle, string $src, array $deps = array() ): void {
		add_action(
			'init',
			static function () use ( $handle, $src, $deps ): void {
				wp_register_style( $handle, $src, $deps, WCB_VERSION );

				foreach ( array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() ) as $block_name ) {
					if ( ! self::is_wcb_block( (string) $block_name ) ) {
						continue;
					}
					wp_enqueue_block_style( (string) $block_name, array( 'handle' => $handle ) );
				}
			},
			20
		);

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $handle ): void {
				if ( ! self::is_wcb_template_screen() ) {
					return;
				}
				wp_enqueue_style( $handle );
			}
		);
	}

	/**
	 * Is this block name one of ours?
	 *
	 * @since 1.7.1
	 *
	 * @param string $block_name Registered block name.
	 * @return bool
	 */
	private static function is_wcb_block( string $block_name ): bool {
		foreach ( self::BLOCK_PREFIXES as $prefix ) {
			if ( str_starts_with( $block_name, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Is the current request a WCB-templated screen (no block involved)?
	 *
	 * @since 1.7.1
	 * @return bool
	 */
	private static function is_wcb_template_screen(): bool {
		return is_singular( self::CPTS )
			|| is_post_type_archive( self::CPTS )
			|| is_tax( self::TAXONOMIES );
	}
}
