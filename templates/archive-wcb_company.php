<?php
/**
 * Plugin-shipped archive template for the wcb_company post type.
 *
 * Most WordPress themes ship an `archive.php` that assumes the layout has a
 * sidebar widget area, which collapses our Companies grid to a ~600 px content
 * column on otherwise wide viewports (Astra, Storefront, OceanWP, Twenty
 * Twenty-Three, etc.). This template bypasses the theme's archive layout and
 * renders our block in a full-width main element wrapped by the theme's
 * `get_header()` / `get_footer()` so the global navigation, footer, and styling
 * are still inherited.
 *
 * Wraps the rendered block in `<article class="entry-content">` so themes
 * that key body typography off `.entry-content` (Astra in particular)
 * inject their font-family uniformly across archive and single pages.
 *
 * Wired up via the `archive_template` filter in `WCB\Core\Plugin`.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div id="primary" class="wcb-archive-shell wcb-archive-shell--companies">
	<main class="wcb-archive-main">
		<article class="wcb-archive-article entry-content">
			<?php
			// Render the archive block via do_blocks() so attributes pick up the
			// post-type archive defaults (filters / pagination / search).
			echo do_blocks( '<!-- wp:wp-career-board/company-archive /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block render escapes internally.
			?>
		</article>
	</main>
</div>
<?php
get_footer();
