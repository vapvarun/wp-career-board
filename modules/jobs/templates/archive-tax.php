<?php
/**
 * Taxonomy archive template for WCB job taxonomies.
 *
 * Renders the wcb/job-listings block on the main jobs archive layout. The term
 * scoping is JobSearch::from_url(), shared with block themes.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div id="primary" class="wcb-archive-shell wcb-archive-shell--jobs">
	<?php $wcb_main_tag = \WCB\Core\ThemeCompat::main_tag(); ?>
	<<?php echo tag_escape( $wcb_main_tag ); ?> class="wcb-archive-main">
		<article class="wcb-archive-article entry-content">
			<?php
			echo do_blocks( '<!-- wp:wp-career-board/job-listings {"showHeading":true} /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block render escapes internally.
			?>
		</article>
	</<?php echo tag_escape( $wcb_main_tag ); ?>>
</div>
<?php
get_footer();
