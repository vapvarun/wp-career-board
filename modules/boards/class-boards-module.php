<?php
/**
 * Boards module — registers wcb_board CPT and ensures a default board exists.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Boards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boards module class.
 *
 * @since 1.0.0
 */
final class BoardsModule {

	/**
	 * Boot the module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'ensure_default_board' ), 20 );
	}

	/**
	 * Register the wcb_board post type.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			'wcb_board',
			array(
				'labels'          => array(
					'name'          => __( 'Job Boards', 'wp-career-board' ),
					'singular_name' => __( 'Job Board', 'wp-career-board' ),
					'add_new_item'  => __( 'Add New Board', 'wp-career-board' ),
					'edit_item'     => __( 'Edit Board', 'wp-career-board' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_rest'    => true,
				'show_in_menu'    => false,
				'supports'        => array( 'title', 'custom-fields' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Ensures a default board exists on first run.
	 * Free plugin always has exactly one board (multi-board is Pro).
	 *
	 * Runs on every `init` as a self-heal, so it must be race-safe: a naive
	 * get_option-then-insert is a check-then-act race, and two concurrent
	 * post-activation requests both seeing the option empty would each insert
	 * a "Main Board" (the duplicate-board bug). `add_option()` is a single
	 * INSERT guarded by the unique `option_name` key, so exactly one of N
	 * racing requests wins the lock and creates the board.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ensure_default_board(): void {
		if ( get_option( 'wcb_default_board_id', 0 ) ) {
			return;
		}

		// Atomic create-once lock — only the request that successfully inserts
		// this row proceeds; concurrent losers return immediately. Released
		// below so a failed insert can be retried on a later request.
		if ( ! add_option( 'wcb_default_board_lock', time(), '', false ) ) {
			return;
		}

		// Self-heal: adopt an existing board rather than create a duplicate
		// (covers sites that already have a board but lost the option row).
		$existing = get_posts(
			array(
				'post_type'      => 'wcb_board',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$board_id = (int) ( $existing[0] ?? 0 );
		if ( ! $board_id ) {
			$inserted = wp_insert_post(
				array(
					'post_type'   => 'wcb_board',
					'post_title'  => __( 'Main Board', 'wp-career-board' ),
					'post_status' => 'publish',
				)
			);
			if ( $inserted && ! is_wp_error( $inserted ) ) {
				$board_id = (int) $inserted;
			}
		}

		if ( $board_id ) {
			update_option( 'wcb_default_board_id', $board_id, false );
		}

		delete_option( 'wcb_default_board_lock' );
	}

	/**
	 * Get the default board ID.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public static function get_default_board_id(): int {
		return (int) get_option( 'wcb_default_board_id', 0 );
	}
}
