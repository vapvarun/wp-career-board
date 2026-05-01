<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
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
		add_filter( 'template_include', array( $this, 'single_job_template' ) );
		add_filter( 'template_include', array( $this, 'taxonomy_archive_template' ) );
		add_filter( 'the_content_feed', array( $this, 'append_job_meta_to_feed' ) );
		add_filter( 'body_class', array( $this, 'add_job_body_class' ) );
		add_filter( 'wcb_jobs_allowed_meta_filters', array( $this, 'register_default_meta_filters' ) );
	}

	/**
	 * Register Free's first-party job meta keys in the allow-list so the
	 * `metaFilter` shortcode/REST attribute works zero-config for the most
	 * common cases. Custom integrator keys still require their own filter
	 * hook so arbitrary-meta probes stay blocked.
	 *
	 * @since 1.2.0
	 *
	 * @param array<int,string> $keys Existing allow-list (typically empty).
	 * @return array<int,string>
	 */
	public function register_default_meta_filters( array $keys ): array {
		return array_values(
			array_unique(
				array_merge(
					$keys,
					array(
						'_wcb_featured',
						'_wcb_company_id',
					)
				)
			)
		);
	}

	/**
	 * Add wcb-job-page body class on wcb_job single pages.
	 *
	 * Enables the block stylesheet to suppress any active theme's sidebar and
	 * duplicate post title so the layout works consistently across all themes.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $classes Existing body classes.
	 * @return string[]
	 */
	public function add_job_body_class( array $classes ): array {
		if ( is_singular( 'wcb_job' ) ) {
			$classes[] = 'wcb-job-page';
		}
		return $classes;
	}

	/**
	 * Append salary, location, and company metadata to job feed items.
	 *
	 * Only fires when the current post is a wcb_job and we're in a feed context.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Post content being sent to the feed.
	 * @return string
	 */
	public function append_job_meta_to_feed( string $content ): string {
		if ( ! is_feed() || get_post_type() !== 'wcb_job' ) {
			return $content;
		}

		$id   = get_the_ID();
		$meta = array();

		$company = (string) get_post_meta( $id, '_wcb_company_name', true );
		if ( $company ) {
			$meta[] = esc_html__( 'Company', 'wp-career-board' ) . ': ' . esc_html( $company );
		}

		$location_terms = wp_get_object_terms( (int) $id, 'wcb_location', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $location_terms ) && $location_terms ) {
			$meta[] = esc_html__( 'Location', 'wp-career-board' ) . ': ' . esc_html( implode( ', ', $location_terms ) );
		}

		$type_terms = wp_get_object_terms( (int) $id, 'wcb_job_type', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $type_terms ) && $type_terms ) {
			$meta[] = esc_html__( 'Type', 'wp-career-board' ) . ': ' . esc_html( implode( ', ', $type_terms ) );
		}

		$sal_min  = (string) get_post_meta( $id, '_wcb_salary_min', true );
		$sal_max  = (string) get_post_meta( $id, '_wcb_salary_max', true );
		$currency = (string) get_post_meta( $id, '_wcb_salary_currency', true );
		if ( $sal_min && $sal_max ) {
			$meta[] = esc_html__( 'Salary', 'wp-career-board' ) . ': ' . esc_html( $currency . ' ' . number_format( (int) $sal_min ) . ' – ' . number_format( (int) $sal_max ) );
		}

		$deadline = (string) get_post_meta( $id, '_wcb_deadline', true );
		if ( $deadline ) {
			$meta[] = esc_html__( 'Apply by', 'wp-career-board' ) . ': ' . esc_html( $deadline );
		}

		if ( ! $meta ) {
			return $content;
		}

		$header = '<ul>' . implode( '', array_map( fn( $line ) => '<li>' . $line . '</li>', $meta ) ) . '</ul>';
		return $header . $content;
	}

	/**
	 * Render single wcb_job pages using the wcb/job-single block.
	 *
	 * Ensures every theme displays the full job detail view (apply button,
	 * sidebar, company card) instead of the raw post content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template Current template path.
	 * @return string
	 */
	public function single_job_template( string $template ): string {
		if ( ! is_singular( 'wcb_job' ) ) {
			return $template;
		}
		// Theme integrations (Reign, BuddyX Pro) set their own template via single_template.
		if ( str_contains( $template, 'wp-career-board' ) ) {
			return $template;
		}
		$override = plugin_dir_path( __FILE__ ) . 'templates/single-wcb_job.php';
		return file_exists( $override ) ? $override : $template;
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
				'show_in_nav_menus' => true,
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
				'show_in_nav_menus' => true,
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
				'show_in_nav_menus' => true,
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
