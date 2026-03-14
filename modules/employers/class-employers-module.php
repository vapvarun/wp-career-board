<?php
/**
 * Employers module — registers wcb_company CPT.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Employers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Employers module class.
 *
 * @since 1.0.0
 */
final class EmployersModule {

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
	 * Register the wcb_company post type.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			'wcb_company',
			array(
				'labels'          => array(
					'name'               => __( 'Companies', 'wp-career-board' ),
					'singular_name'      => __( 'Company', 'wp-career-board' ),
					'add_new_item'       => __( 'Add New Company', 'wp-career-board' ),
					'edit_item'          => __( 'Edit Company', 'wp-career-board' ),
					'view_item'          => __( 'View Company', 'wp-career-board' ),
					'not_found'          => __( 'No companies found.', 'wp-career-board' ),
					'not_found_in_trash' => __( 'No companies found in Trash.', 'wp-career-board' ),
				),
				'public'          => true,
				'show_in_rest'    => true,
				'show_in_menu'    => false,
				'supports'        => array( 'title', 'editor', 'thumbnail' ),
				'rewrite'         => array(
					'slug'       => 'companies',
					'with_front' => false,
				),
				'has_archive'     => 'companies',
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}
}
