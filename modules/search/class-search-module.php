<?php
/**
 * Search module — integrates WCB job search with native WordPress query.
 *
 * Hooks into pre_get_posts so that the native WordPress search and the
 * wcb_job archive also respect category/type/location/experience filters
 * when passed as URL query parameters. The REST-based search is handled
 * separately by SearchEndpoint; this module serves the server-rendered path.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrates WCB search filters with native WordPress queries.
 *
 * @since 1.0.0
 */
final class SearchModule {

	/**
	 * Boot the module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'pre_get_posts', array( $this, 'filter_job_archive' ) );
	}

	/**
	 * Apply URL filters to the main job archive query.
	 *
	 * Same search as the listing block and GET /jobs (JobSearch), so a theme
	 * that renders the archive's own loop shows the same jobs. The keyword
	 * uses JobSearch's matching, not core `s` (titles and excerpts only).
	 *
	 * @since 1.0.0
	 * @since 1.8.0 Delegates to JobSearch.
	 *
	 * @param \WP_Query $query The current WordPress query object.
	 * @return void
	 */
	public function filter_job_archive( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'wcb_job' ) ) {
			return;
		}
		$args = \WCB\Modules\Jobs\JobSearch::query_args( \WCB\Modules\Jobs\JobSearch::from_url() );
		foreach ( array( 'tax_query', 'meta_query', 'wcb_search_term', 'wcb_sort', 'wcb_featured_first', 'orderby' ) as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$query->set( $key, $args[ $key ] );
			}
		}
		$query->set( 's', '' );
	}
}
