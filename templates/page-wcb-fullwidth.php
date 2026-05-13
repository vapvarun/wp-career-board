<?php
/**
 * Plugin-shipped page template for WCB-hosted singular pages.
 *
 * Pages that exist solely to host a WCB dashboard / form / archive block
 * (candidate-dashboard, employer-dashboard, find-jobs, find-candidates,
 * companies archive, post-a-job, etc.) get routed through this template
 * by `Plugin::use_wcb_archive_template()` so the block renders inside
 * our canonical `.wcb-archive-shell` (1280px max, centered) regardless
 * of which theme is active. Without this, `/find-jobs/` on Astra renders
 * flush-left at viewport edge while `/companies/` (which already used
 * our archive template) renders centered — two pages, two visual
 * languages, same plugin.
 *
 * The content is wrapped in `<article class="entry-content">` so themes
 * that key body typography off `.entry-content` (Astra and most modern
 * themes do — they don't apply body font-family on `ast-plain-container`
 * pages without an `.entry-content` element) inject their font-family
 * uniformly across pages and single-CPT templates. Without the wrapper,
 * the candidate-dashboard, employer-dashboard, and find-* pages fell
 * back to the browser default serif (Times) while single-CPT pages
 * (which DO have `.entry-content` via the single-wcb_* templates)
 * rendered with the proper sans-serif stack — root cause of the
 * "different fonts on different pages" audit finding.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div id="primary" class="wcb-archive-shell wcb-archive-shell--page">
	<main class="wcb-archive-main">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'wcb-page-article entry-content' ); ?>>
				<?php the_content(); ?>
			</article>
			<?php
		endwhile;
		?>
	</main>
</div>
<?php
get_footer();
