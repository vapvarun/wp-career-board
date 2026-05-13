<?php
/**
 * Generic single job template for all themes.
 *
 * Renders the wcb/job-single block inside the active theme's standard
 * header/footer wrapped in `.wcb-archive-shell` so single job pages
 * inherit the canonical 1280px max-width + responsive padding shared with
 * the archive pages and the new `single-wcb_company.php` /
 * Pro `single-wcb_resume.php` templates. Without the shell wrapper the
 * job hero stretches to the viewport edges (no left/right gap) and clips
 * against the theme's sticky header (no top gap).
 *
 * Theme integrations (BuddyX Pro, Reign) may load their own version of
 * this template; this file is the universal fallback.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div id="primary" class="wcb-archive-shell wcb-archive-shell--single">
	<main class="wcb-archive-main">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks output is safe rendered HTML.
		echo do_blocks( '<!-- wp:wp-career-board/job-single /-->' );
		?>
	</main>
</div>
<?php
get_footer();
