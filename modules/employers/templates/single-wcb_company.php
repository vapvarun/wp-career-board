<?php
/**
 * Generic single company template for all themes.
 *
 * Mirrors `single-wcb_job.php` and Pro's `single-wcb_resume.php`: renders
 * the wcb/company-profile block inside the active theme's standard
 * header/footer so single company URLs (`/companies/{slug}/`) feel like a
 * sibling of single job (`/jobs/{slug}/`) and single resume
 * (`/resume/{slug}/`) URLs — same chrome, same width, same product family.
 *
 * Theme integrations may override this template via `single_template`;
 * this file is the universal fallback.
 *
 * @package WP_Career_Board
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks output is safe rendered HTML.
echo do_blocks( '<!-- wp:wp-career-board/company-profile /-->' );
get_footer();
