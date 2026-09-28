<?php
/**
 * Resolve the heading used by directory blocks (jobs, companies, candidates).
 *
 * @package WP_Career_Board
 * @since   1.1.1
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for the H1 shown on the three directory archives.
 *
 * The block can land on three different surfaces depending on how the
 * site is configured:
 *   - the admin-configured archive page (e.g. /find-jobs/);
 *   - any other singular page that embeds the block;
 *   - the default WP CPT archive (e.g. /jobs/).
 *
 * All three should render the same `<h1 class="wcb-page-heading">` with a
 * sensible title.
 *
 * @since 1.1.1
 */
final class ArchiveHeading {

	/**
	 * Whether this request has already printed its page heading.
	 *
	 * @var bool
	 */
	private static bool $printed = false;

	/**
	 * The page's H1, once per request, from the first block that asks.
	 *
	 * A listing page can open with a search or filter block and carry the
	 * results block further down, so the heading must come from whichever of
	 * them renders first; the later ones get '' and never add a second H1.
	 *
	 * @since 1.8.0
	 * @param string $cpt_slug    Post type slug (e.g. 'wcb_job').
	 * @param string $setting_key Settings key for the admin-configured archive page.
	 * @return string The `<h1>` markup (already escaped), or '' when there is none to print.
	 */
	public static function render( string $cpt_slug, string $setting_key ): string {
		if ( self::$printed ) {
			return '';
		}
		// A page built with its own H1 (the setup wizard's pages start with a heading block)
		// already has one: do not add a second.
		$page = get_queried_object();
		if ( $page instanceof \WP_Post && preg_match( '/<h1[\s>]|wp:heading \{[^}]*"level":1/', $page->post_content ) ) {
			self::$printed = true;
			return '';
		}
		$title = self::resolve( $cpt_slug, $setting_key );
		if ( '' === $title ) {
			return '';
		}
		self::$printed = true;

		return '<h1 class="wcb-page-heading">' . esc_html( $title ) . '</h1>';
	}

	/**
	 * Resolve the directory heading.
	 *
	 * @since 1.1.1
	 *
	 * @param string $cpt_slug    Post type slug (e.g. 'wcb_job').
	 * @param string $setting_key Settings key for the admin-configured archive page.
	 * @return string Title to render, or '' if none of the sources resolve.
	 */
	public static function resolve( string $cpt_slug, string $setting_key ): string {
		$configured_id = \WCB\Admin\Settings::int( $setting_key, 0 );
		if ( $configured_id && (int) get_queried_object_id() === $configured_id ) {
			return (string) get_the_title( $configured_id );
		}
		if ( is_singular( 'page' ) ) {
			return (string) get_the_title();
		}
		if ( is_post_type_archive( $cpt_slug ) ) {
			return (string) post_type_archive_title( '', false );
		}
		return '';
	}
}
