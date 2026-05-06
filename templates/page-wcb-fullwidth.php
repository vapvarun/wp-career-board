<?php
/**
 * Plugin-shipped page template for WCB-hosted singular pages.
 *
 * Pages that exist solely to host a WCB dashboard / form / archive block
 * get routed through this template by `Plugin::use_wcb_archive_template()`
 * so the block renders inside our canonical `.wcb-archive-shell` (1280 px
 * max, centered) regardless of which theme is active. Without this,
 * /find-jobs/ on Astra renders flush-left at viewport edge, while
 * /companies/ (which already used our archive template) renders centered
 * — two pages, two visual languages, same plugin.
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
			the_content();
		endwhile;
		?>
	</main>
</div>
<?php
get_footer();
