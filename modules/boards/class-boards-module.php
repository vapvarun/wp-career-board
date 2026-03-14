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
	 * @since 1.0.0
	 * @return void
	 */
	public function ensure_default_board(): void {
		if ( get_option( 'wcb_default_board_id' ) ) {
			return;
		}

		$board_id = wp_insert_post(
			array(
				'post_type'   => 'wcb_board',
				'post_title'  => __( 'Main Board', 'wp-career-board' ),
				'post_status' => 'publish',
			)
		);

		if ( $board_id && ! is_wp_error( $board_id ) ) {
			update_option( 'wcb_default_board_id', $board_id, false );
		}
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
