<?php
/**
 * BuddyX Pro-compatible job archive template.
 *
 * Wraps the wcb/job-listings block inside the BuddyX Pro theme's standard
 * header/footer layout so the job board page inherits the site's global
 * navigation, sidebar, and footer.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks output is safe rendered HTML.
echo do_blocks( '<!-- wp:wp-career-board/job-listings /-->' );
get_footer();
