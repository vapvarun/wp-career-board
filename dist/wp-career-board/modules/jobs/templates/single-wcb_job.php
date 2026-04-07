<?php
/**
 * Generic single job template for all themes.
 *
 * Renders the wcb/job-single block inside the active theme's standard
 * header/footer layout so single job pages always show the full job detail
 * view (apply button, sidebar, company card, etc.) regardless of theme.
 *
 * Theme integrations (BuddyX Pro, Reign) may load their own version of this
 * template; this file is the universal fallback.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks output is safe rendered HTML.
echo do_blocks( '<!-- wp:wp-career-board/job-single /-->' );
get_footer();
