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
	 * Apply URL filter parameters to the main job archive query.
	 *
	 * Supports: wcb_category, wcb_job_type, wcb_location, wcb_experience, remote.
	 * Only runs on the main query for the wcb_job archive — never on admin queries.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Query $query The current WordPress query object.
	 * @return void
	 */
	public function filter_job_archive( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( ! $query->is_post_type_archive( 'wcb_job' ) ) {
			return;
		}

		$tax_query = array();

		$category = isset( $_GET['wcb_category'] ) ? sanitize_text_field( wp_unslash( $_GET['wcb_category'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $category ) {
			$tax_query[] = array(
				'taxonomy' => 'wcb_category',
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_text_field', explode( ',', $category ) ),
			);
		}

		$job_type = isset( $_GET['wcb_job_type'] ) ? sanitize_text_field( wp_unslash( $_GET['wcb_job_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $job_type ) {
			$tax_query[] = array(
				'taxonomy' => 'wcb_job_type',
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_text_field', explode( ',', $job_type ) ),
			);
		}

		$location = isset( $_GET['wcb_location'] ) ? sanitize_text_field( wp_unslash( $_GET['wcb_location'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $location ) {
			$tax_query[] = array(
				'taxonomy' => 'wcb_location',
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_text_field', explode( ',', $location ) ),
			);
		}

		$experience = isset( $_GET['wcb_experience'] ) ? sanitize_text_field( wp_unslash( $_GET['wcb_experience'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $experience ) {
			$tax_query[] = array(
				'taxonomy' => 'wcb_experience',
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_text_field', explode( ',', $experience ) ),
			);
		}

		if ( ! empty( $tax_query ) ) {
			$existing = $query->get( 'tax_query' );
			if ( ! is_array( $existing ) ) {
				$existing = array();
			}
			$query->set( 'tax_query', array_merge( $existing, $tax_query ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$remote = isset( $_GET['wcb_remote'] ) ? sanitize_text_field( wp_unslash( $_GET['wcb_remote'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '1' === $remote ) {
			$query->set(
				'meta_query', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					array(
						'key'   => '_wcb_remote',
						'value' => '1',
					),
				)
			);
		}
	}
}
