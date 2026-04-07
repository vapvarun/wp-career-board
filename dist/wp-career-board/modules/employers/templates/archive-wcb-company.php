<?php
/**
 * Company archive page template.
 *
 * Used by EmployersModule via the template_include filter to replace the
 * default theme archive template with the company-archive block.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

do_action( 'reign_before_content_section' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reign theme compatibility hook.
?>

<div class="content-wrapper">
	<?php
	echo render_block( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		array(
			'blockName'    => 'wp-career-board/company-archive',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		)
	);
	?>
</div>

<?php
do_action( 'reign_after_content_section' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reign theme compatibility hook.

get_footer();
