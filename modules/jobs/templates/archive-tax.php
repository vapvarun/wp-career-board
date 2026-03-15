<?php
/**
 * Taxonomy archive template for WCB job taxonomies.
 *
 * Renders the wcb/job-listings block pre-filtered to the current taxonomy
 * term, matching the appearance of the main jobs archive page.
 *
 * Supported taxonomies: wcb_category, wcb_job_type, wcb_location, wcb_experience, wcb_tag.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$wcb_term     = get_queried_object();
$wcb_taxonomy = $wcb_term instanceof \WP_Term ? $wcb_term->taxonomy : '';
$wcb_slug     = $wcb_term instanceof \WP_Term ? $wcb_term->slug : '';
$wcb_term_id  = $wcb_term instanceof \WP_Term ? $wcb_term->term_id : 0;

// ── Taxonomy → REST param name map ────────────────────────────────────────────
$wcb_tax_rest_param = array(
	'wcb_category'   => 'category',
	'wcb_job_type'   => 'type',
	'wcb_location'   => 'location',
	'wcb_experience' => 'experience',
);

$wcb_rest_param = $wcb_tax_rest_param[ $wcb_taxonomy ] ?? '';

// ── Inject taxonomy into the initial server-side get_posts() query ─────────────
if ( $wcb_term_id ) {
	add_filter(
		'wcb_job_listings_query_args',
		static function ( array $args ) use ( $wcb_taxonomy, $wcb_term_id ): array {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => $wcb_taxonomy,
					'field'    => 'term_id',
					'terms'    => $wcb_term_id,
				),
			);
			return $args;
		}
	);
}

// ── Inject REST param so "Load more" and filters also scope to this term ───────
if ( $wcb_rest_param && $wcb_slug ) {
	add_filter(
		'wcb_job_listings_api_base',
		static function ( string $url ) use ( $wcb_rest_param, $wcb_slug ): string {
			return add_query_arg( $wcb_rest_param, $wcb_slug, $url );
		}
	);
}

get_header();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks output is safe rendered HTML.
echo do_blocks( '<!-- wp:wp-career-board/job-listings /-->' );
get_footer();
