<?php
/**
 * Notice on a Career Board page whose blocks are not available.
 *
 * Pages that Pro creates (Find Candidates, Job Map, Hiring pipeline) stay on
 * the site when Pro is deactivated, but their blocks no longer exist, so the
 * body renders empty. This says why, instead of leaving a blank page.
 *
 * @package WP_Career_Board
 * @since   1.8.4
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Appends a short notice to a page that holds an unregistered Career Board block.
 *
 * @since 1.8.4
 */
class MissingBlockNotice {

	/**
	 * Hook the content filter.
	 *
	 * @since 1.8.4
	 * @return void
	 */
	public static function boot(): void {
		add_filter( 'the_content', array( self::class, 'append' ), 20 );
	}

	/**
	 * Add the notice to the page's own content.
	 *
	 * @since 1.8.4
	 *
	 * @param string $content Page content.
	 * @return string
	 */
	public static function append( string $content ): string {
		if ( ! is_page() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		// By priority 20 do_blocks() has already turned an unregistered block into
		// nothing, so the blocks are read from the stored content.
		$stored = (string) get_post_field( 'post_content', (int) get_the_ID() );
		if ( ! has_blocks( $stored ) || ! self::has_missing_block( parse_blocks( $stored ) ) ) {
			return $content;
		}

		$text = wp_is_ability_granted( 'wcb/manage-settings' ) // phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
			? __( 'This page needs WP Career Board Pro to be active, so its content is empty. Activate Pro, or take this page out of your menu.', 'wp-career-board' )
			: __( 'This page is not available right now.', 'wp-career-board' );

		return $content . '<p class="wcb-missing-block">' . esc_html( $text ) . '</p>';
	}

	/**
	 * Whether any block is ours by namespace but no longer registered.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return bool
	 */
	private static function has_missing_block( array $blocks ): bool {
		$registry = \WP_Block_Type_Registry::get_instance();
		foreach ( $blocks as $block ) {
			$name = (string) ( $block['blockName'] ?? '' );
			if ( preg_match( '#^(wcb|wp-career-board)/#', $name ) && ! $registry->is_registered( $name ) ) {
				return true;
			}
			if ( ! empty( $block['innerBlocks'] ) && self::has_missing_block( $block['innerBlocks'] ) ) {
				return true;
			}
		}
		return false;
	}
}
