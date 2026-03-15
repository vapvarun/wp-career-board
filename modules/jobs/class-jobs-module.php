<?php
/**
 * Jobs module — registers wcb_job CPT and all job taxonomies.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Jobs module class.
 *
 * @since 1.0.0
 */
final class JobsModule {

	/**
	 * Boot the module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_filter( 'template_include', array( $this, 'taxonomy_archive_template' ) );
	}

	/**
	 * Serve the job-listings block for all WCB taxonomy archive pages.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template Current template path.
	 * @return string
	 */
	public function taxonomy_archive_template( string $template ): string {
		if ( ! is_tax( array( 'wcb_category', 'wcb_job_type', 'wcb_tag', 'wcb_location', 'wcb_experience' ) ) ) {
			return $template;
		}
		$override = plugin_dir_path( __FILE__ ) . 'templates/archive-tax.php';
		return file_exists( $override ) ? $override : $template;
	}

	/**
	 * Register the wcb_job post type.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			'wcb_job',
			array(
				'labels'          => array(
					'name'               => __( 'Jobs', 'wp-career-board' ),
					'singular_name'      => __( 'Job', 'wp-career-board' ),
					'add_new_item'       => __( 'Add New Job', 'wp-career-board' ),
					'edit_item'          => __( 'Edit Job', 'wp-career-board' ),
					'view_item'          => __( 'View Job', 'wp-career-board' ),
					'search_items'       => __( 'Search Jobs', 'wp-career-board' ),
					'not_found'          => __( 'No jobs found.', 'wp-career-board' ),
					'not_found_in_trash' => __( 'No jobs found in Trash.', 'wp-career-board' ),
				),
				'public'          => true,
				'show_in_rest'    => true,
				'show_in_menu'    => false,
				'supports'        => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
				'rewrite'         => array(
					'slug'       => 'jobs',
					'with_front' => false,
				),
				'has_archive'     => 'jobs',
				'menu_icon'       => 'dashicons-portfolio',
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Register all job-related taxonomies.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_taxonomies(): void {
		register_taxonomy(
			'wcb_category',
			'wcb_job',
			array(
				'label'             => __( 'Job Categories', 'wp-career-board' ),
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'job-category' ),
				'show_admin_column' => true,
			)
		);

		register_taxonomy(
			'wcb_job_type',
			'wcb_job',
			array(
				'label'             => __( 'Job Types', 'wp-career-board' ),
				'hierarchical'      => false,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'job-type' ),
				'show_admin_column' => true,
			)
		);

		register_taxonomy(
			'wcb_tag',
			'wcb_job',
			array(
				'label'        => __( 'Job Tags', 'wp-career-board' ),
				'hierarchical' => false,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'job-tag' ),
			)
		);

		register_taxonomy(
			'wcb_location',
			'wcb_job',
			array(
				'label'             => __( 'Locations', 'wp-career-board' ),
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'job-location' ),
				'show_admin_column' => true,
			)
		);

		register_taxonomy(
			'wcb_experience',
			'wcb_job',
			array(
				'label'        => __( 'Experience Levels', 'wp-career-board' ),
				'hierarchical' => false,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'job-experience' ),
			)
		);
	}
}
