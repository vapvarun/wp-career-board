<?php
/**
 * Applications module — registers wcb_application CPT.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Applications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applications module class.
 *
 * @since 1.0.0
 */
final class ApplicationsModule {

	/**
	 * Boot the module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Register the wcb_application post type.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			'wcb_application',
			array(
				'labels'          => array(
					'name'               => __( 'Applications', 'wp-career-board' ),
					'singular_name'      => __( 'Application', 'wp-career-board' ),
					'add_new_item'       => __( 'Add New Application', 'wp-career-board' ),
					'edit_item'          => __( 'Edit Application', 'wp-career-board' ),
					'not_found'          => __( 'No applications found.', 'wp-career-board' ),
					'not_found_in_trash' => __( 'No applications found in Trash.', 'wp-career-board' ),
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
}
