<?php
/**
 * Theme-compat single job template (Reign / BuddyX Pro).
 *
 * Wraps `wcb/job-single` in the same `.wcb-archive-shell` container as the
 * plugin's own generic `single-wcb_job.php`, so pages on our own themes get
 * the canonical width instead of falling back to `get_header()`/`get_footer()`
 * alone and inheriting whatever the theme's automatic content column does
 * (Basecamp 10348287376 / 10348287177 / 10348289393 — a job page measured a
 * different width than a company page on the same theme and viewport).
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
		<?php while ( have_posts() ) : the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'wcb-single entry-content' ); ?>>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks output is safe rendered HTML.
				echo do_blocks( '<!-- wp:wp-career-board/job-single /-->' );
				?>
			</article>
		<?php endwhile; ?>
	</main>
</div>
<?php
get_footer();
