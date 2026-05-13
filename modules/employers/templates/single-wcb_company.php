<?php
/**
 * Generic single company template for all themes.
 *
 * Renders the wcb/company-profile block via the standard WP loop with
 * `the_content()` so the theme's `the_content` typography wrappers
 * (`.entry-content` for Astra, equivalents for other themes) apply —
 * keeping single CPT pages typographically uniform with archive pages
 * (which already run through `the_content`). The block injection itself
 * is handled by `Employers_Module::inject_company_profile()` hooked on
 * `the_content`, so the block reaches the page even when the wcb_company
 * post body is empty.
 *
 * The output is wrapped in `<article class="entry-content">` so the
 * theme's content typography (font-family, font-size, line-height
 * declared on `.entry-content`) applies uniformly between archive pages
 * (where the theme injects `.entry-content` via its own page template)
 * and single CPT pages (where the theme defers to this template).
 * Without the explicit wrapper, themes that key typography off
 * `body.single-*` body classes produced a visible font divergence
 * between `/companies/` and `/companies/{slug}/`.
 *
 * `.wcb-archive-shell` keeps the 1280px canonical container so single
 * CPT pages share the gap shape of archive pages. Theme integrations
 * may override this template via `single_template`; this file is the
 * universal fallback.
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
