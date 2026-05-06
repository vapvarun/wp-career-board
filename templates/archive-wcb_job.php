<?php
/**
 * Plugin-shipped archive template for the wcb_job post type.
 *
 * Same rationale as `archive-wcb_company.php` — bypasses theme archive
 * sidebar layouts so the jobs listing grid renders at full content width
 * regardless of which theme is active.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div id="primary" class="wcb-archive-shell wcb-archive-shell--jobs">
	<main class="wcb-archive-main">
		<?php
		echo do_blocks( '<!-- wp:wp-career-board/job-listings /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block render escapes internally.
		?>
	</main>
</div>
<?php
get_footer();
