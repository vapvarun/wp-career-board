<?php
/**
 * Theme-compat job archive template (Reign / BuddyX Pro).
 *
 * Same `.wcb-archive-shell` wrapper as the plugin's own `archive-wcb_job.php`
 * (root `templates/`), so the listings grid on our own themes gets the
 * canonical width and `.entry-content` typography instead of the theme's
 * automatic archive sidebar layout.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div id="primary" class="wcb-archive-shell wcb-archive-shell--jobs">
	<main class="wcb-archive-main">
		<article class="wcb-archive-article entry-content">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks output is safe rendered HTML.
			echo do_blocks( '<!-- wp:wp-career-board/job-listings {"showHeading":true} /-->' );
			?>
		</article>
	</main>
</div>
<?php
get_footer();
