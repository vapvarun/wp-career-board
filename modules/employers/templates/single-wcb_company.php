<?php
/**
 * Generic single company template for all themes.
 *
 * Mirrors `single-wcb_job.php` and Pro's `single-wcb_resume.php`: renders
 * the wcb/company-profile block inside the active theme's standard
 * header/footer wrapped in `.wcb-archive-shell` so single company pages
 * inherit the same 1280px max-width + responsive padding as the archive
 * pages. Without the shell wrapper the company-profile hero card stretches
 * to the viewport edges (no left/right gap) and butts against the theme's
 * sticky header (no top gap) — the gap-collapse observed on the
 * `/companies/starter-labs/` audit.
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
?>
<div id="primary" class="wcb-archive-shell wcb-archive-shell--single">
	<main class="wcb-archive-main">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks output is safe rendered HTML.
		echo do_blocks( '<!-- wp:wp-career-board/company-profile /-->' );
		?>
	</main>
</div>
<?php
get_footer();
