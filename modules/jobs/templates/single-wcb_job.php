<?php
/**
 * Generic single job template for all themes.
 *
 * Renders the wcb/job-single block via the standard WP loop with
 * `the_content()` so the theme's `the_content` typography wrappers
 * (`.entry-content` for Astra, equivalents for other themes) apply —
 * keeping single job pages typographically uniform with archive pages
 * (`/find-jobs/`) and the new single-wcb_company / single-wcb_resume
 * templates. The block injection itself is handled by
 * `Jobs_Module::inject_job_single()` hooked on `the_content`, so the
 * block reaches the page even when the wcb_job post body is empty.
 *
 * The output is wrapped in `<article class="entry-content">` so the
 * theme's content typography applies uniformly across archive and
 * single pages, eliminating the font divergence themes introduce when
 * they key typography off `body.single-*` body classes.
 *
 * `.wcb-archive-shell` keeps the 1280px canonical container so single
 * job pages share the gap shape of archive pages. Theme integrations
 * (BuddyX Pro, Reign) may load their own version of this template; this
 * file is the universal fallback.
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
		while ( have_posts() ) :
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'wcb-single entry-content' ); ?>>
				<?php the_content(); ?>
			</article>
			<?php
		endwhile;
		?>
	</main>
</div>
<?php
get_footer();
