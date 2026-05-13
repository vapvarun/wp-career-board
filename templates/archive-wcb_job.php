<?php
/**
 * Plugin-shipped archive template for the wcb_job post type.
 *
 * Same rationale as `archive-wcb_company.php` — bypasses theme archive
 * sidebar layouts so the jobs listing grid renders at full content width
 * regardless of which theme is active.
 *
 * Wraps the rendered block in `<article class="entry-content">` so themes
 * that key body typography off `.entry-content` (Astra in particular)
 * inject their font-family uniformly across archive and single pages.
 * Without the wrapper, archives fall back to browser default serif on
 * Astra `ast-plain-container` layouts while singles get the proper
 * sans-serif stack — the source of the cross-page font divergence.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div id="primary" class="wcb-archive-shell wcb-archive-shell--jobs">
	<main class="wcb-archive-main">
		<article class="wcb-archive-article entry-content">
			<?php
			echo do_blocks( '<!-- wp:wp-career-board/job-listings /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block render escapes internally.
			?>
		</article>
	</main>
</div>
<?php
get_footer();
