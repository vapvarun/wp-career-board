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
		add_filter( 'the_content', array( $this, 'inject_job_single' ) );
		add_filter( 'body_class', array( $this, 'add_job_body_class' ) );
		// Member blocking on the SSR frontend. REST already excludes blocked
		// authors (class-jobs-endpoint.php author__not_in / is_hidden); mirror it
		// on the server-rendered listings + single job so a blocked employer's
		// jobs don't leak on the pretty permalink or archive.
		add_filter( 'wcb_job_listings_query_args', array( $this, 'exclude_blocked_authors' ) );
		add_action( 'template_redirect', array( $this, 'guard_hidden_single_job' ) );
	}

	/**
	 * Drop authors the current viewer has blocked (or who blocked them) from the
	 * server-rendered job listings. Mirrors the REST list filter so member
	 * blocking is enforced on the Find Jobs block + CPT archive, not just the
	 * REST-driven pagination. No-op for guests / viewers with no blocks.
	 *
	 * @since 1.7.0
	 *
	 * @param array<string, mixed> $args WP_Query args for the listings query.
	 * @return array<string, mixed>
	 */
	public function exclude_blocked_authors( array $args ): array {
		$wcb_hidden = \WCB\Core\Blocks::hidden_author_ids( get_current_user_id() );
		if ( ! empty( $wcb_hidden ) ) {
			$wcb_existing           = isset( $args['author__not_in'] ) ? (array) $args['author__not_in'] : array();
			$args['author__not_in'] = array_values( array_unique( array_merge( $wcb_existing, $wcb_hidden ) ) );
		}
		return $args;
	}

	/**
	 * 404 a single wcb_job whose author is hidden from the current viewer, so a
	 * blocked employer's job is not reachable via its pretty permalink. Mirrors
	 * the REST single 404 (class-jobs-endpoint.php is_hidden guard).
	 *
	 * @since 1.7.0
	 * @return void
	 */
	public function guard_hidden_single_job(): void {
		if ( ! is_singular( 'wcb_job' ) ) {
			return;
		}
		$wcb_author_id = (int) get_post_field( 'post_author', get_queried_object_id() );
		if ( \WCB\Core\Blocks::is_hidden( get_current_user_id(), $wcb_author_id ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	/**
	 * Replace default post content with the job-single block on single wcb_job pages.
	 *
	 * Mirrors `Employers_Module::inject_company_profile()` and Pro's
	 * `Resume_Module::inject_resume_block()`. With this filter in place the
	 * single-wcb_job template can render via the standard WP loop +
	 * `the_content()`, which keeps theme typography (`.entry-content` wrappers)
	 * applied — eliminating the font/size divergence between `/find-jobs/`
	 * (page, runs `the_content`) and `/jobs/{slug}/` (single, was bypassing
	 * it when the template emitted `do_blocks( '<!-- wp:... /-->' )` directly).
	 *
	 * Guarded to the main loop on the singular wcb_job query so block calls
	 * elsewhere (REST, search excerpts, oEmbed previews) keep the post body
	 * verbatim.
	 *
	 * @since 1.2.0
	 *
	 * @param string $content Original post content.
	 * @return string Block-rendered output or original content.
	 */
	public function inject_job_single( string $content ): string {
		if ( ! is_singular( 'wcb_job' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		return render_block(
			array(
				'blockName'    => 'wp-career-board/job-single',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
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
