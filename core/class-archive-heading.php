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
