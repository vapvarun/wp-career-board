<?php
/**
 * Taxonomy archive template for WCB job taxonomies.
 *
 * Renders the wcb/job-listings block on the main jobs archive layout. The term
 * scoping is JobsModule::scope_listing_to_term(), shared with block themes.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

get_header();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks output is safe rendered HTML.
echo do_blocks( '<!-- wp:wp-career-board/job-listings {"showHeading":true} /-->' );
get_footer();
