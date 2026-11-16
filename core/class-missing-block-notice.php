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
 * Appends a short notice to a page that holds an unregistered Career Board block,
 * and keeps that empty page out of search engines and the sitemap.
 *
 * @since 1.8.4
 */
class MissingBlockNotice {

	/**
	 * Hook the content, robots and sitemap filters.
	 *
	 * @since 1.8.4
	 * @return void
	 */
	public static function boot(): void {
		add_filter( 'the_content', array( self::class, 'append' ), 20 );
		add_filter( 'wp_robots', array( self::class, 'robots' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( self::class, 'sitemap_args' ), 10, 2 );
	}

	/**
	 * Whether a page holds a Career Board block that is no longer registered.
	 *
	 * @since 1.8.4
	 *
	 * @param int $post_id Page ID.
	 * @return bool
	 */
	public static function page_is_empty( int $post_id ): bool {
		// By the_content priority 20 do_blocks() has already turned an unregistered block into
		// nothing, so the blocks are read from the stored content.
		$stored = (string) get_post_field( 'post_content', $post_id );
		return has_blocks( $stored ) && self::has_missing_block( parse_blocks( $stored ) );
	}

	/**
	 * Ask search engines to skip an empty page: it is thin content, not a listing.
	 *
	 * @since 1.8.4
	 *
	 * @param array<string, bool|string> $robots Robots directives.
	 * @return array<string, bool|string>
	 */
	public static function robots( array $robots ): array {
		return is_page() && self::page_is_empty( (int) get_queried_object_id() ) ? wp_robots_no_robots( $robots ) : $robots;
	}

	/**
	 * Leave empty pages out of the pages sitemap.
	 *
	 * @since 1.8.4
	 *
	 * @param array<string, mixed> $args      WP_Query args for the sitemap page.
	 * @param string               $post_type Post type being listed.
	 * @return array<string, mixed>
	 */
	public static function sitemap_args( array $args, string $post_type ): array {
		if ( 'page' !== $post_type ) {
			return $args;
		}
		// Only pages that mention one of our blocks can be affected, so the search keeps this small.
		$candidates = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				's'              => 'wp:wcb/',
				'fields'         => 'ids',
				'posts_per_page' => 100,
				'no_found_rows'  => true,
			)
		);
		$empty      = array_values( array_filter( $candidates, array( self::class, 'page_is_empty' ) ) );
		if ( $empty ) {
			$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), $empty );
		}
		return $args;
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

		if ( ! self::page_is_empty( (int) get_the_ID() ) ) {
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
