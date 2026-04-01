<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated file name is intentional.
/**
 * Admin setup wizard — auto-creates required pages on first activation.
 *
 * Wizard steps are powered by REST endpoints (no admin-ajax.php).
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Guides new site owners through first-run configuration via a REST-powered wizard.
 *
 * @since 1.0.0
 */
class SetupWizard extends \WCB\Api\RestController {

	/**
	 * Boot the setup wizard.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_wizard_assets' ) );
	}

	/**
	 * Redirect to the wizard page on first activation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function maybe_redirect(): void {
		if ( ! get_transient( 'wcb_activation_redirect' ) ) {
			return;
		}

		if ( get_option( 'wcb_setup_complete', false ) ) {
			return;
		}

		delete_transient( 'wcb_activation_redirect' );
		wp_safe_redirect( admin_url( 'admin.php?page=wcb-setup' ) );
		exit;
	}

	/**
	 * Register the hidden setup wizard submenu page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_page(): void {
		add_submenu_page(
			'',
			__( 'Setup Wizard', 'wp-career-board' ),
			'',
			'wcb_manage_settings', // phpcs:ignore WordPress.WP.Capabilities.Unknown -- wcb_manage_settings is a registered WCB custom capability.
			'wcb-setup',
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue wizard assets only on the wizard admin page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_wizard_assets( string $hook ): void {
		if ( 'admin_page_wcb-setup' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'wcb-wizard',
			WCB_URL . 'assets/js/wizard.js',
			array( 'wp-api-fetch' ),
			WCB_VERSION,
			true
		);

		wp_localize_script(
			'wcb-wizard',
			'wcbWizard',
			array(
				'restUrl' => esc_url_raw( rest_url( 'wcb/v1/wizard/' ) ),
			)
		);
	}

	/**
	 * Register wizard REST routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/wizard/create-pages',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_pages_handler' ),
				'permission_callback' => array( $this, 'wizard_permission_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/wizard/sample-data',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sample_data_handler' ),
				'permission_callback' => array( $this, 'wizard_permission_check' ),
				'args'                => array(
					'install_sample' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/wizard/complete',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'complete_handler' ),
				'permission_callback' => array( $this, 'wizard_permission_check' ),
			)
		);
	}

	/**
	 * Permission check for all wizard REST routes.
	 *
	 * @since 1.0.0
	 * @return true|\WP_Error
	 */
	public function wizard_permission_check(): true|\WP_Error {
		if ( $this->check_ability( 'wcb_manage_settings' ) ) {
			return true;
		}
		return $this->permission_error();
	}

	/**
	 * REST handler — create required WordPress pages.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request REST request (unused — no params required).
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_pages_handler( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		$created = $this->create_required_pages();
		return rest_ensure_response( $created );
	}

	/**
	 * REST handler — optionally install sample taxonomy terms, company, and job.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request REST request; reads `install_sample` param.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function sample_data_handler( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( $request->get_param( 'install_sample' ) ) {
			$this->install_sample_data();
		}
		return rest_ensure_response( array( 'installed' => (bool) $request->get_param( 'install_sample' ) ) );
	}

	/**
	 * REST handler — mark setup complete and return the dashboard redirect URL.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request REST request (unused — no params required).
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function complete_handler( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		update_option( 'wcb_setup_complete', true );
		return rest_ensure_response( array( 'redirect' => admin_url( 'admin.php?page=wp-career-board' ) ) );
	}

	/**
	 * Render the setup wizard view.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render(): void {
		require_once WCB_DIR . 'admin/views/setup-wizard.php';
	}

	/**
	 * Create the four required WCB pages and persist their IDs in wcb_settings.
	 *
	 * Skips any page whose setting key already has a non-zero value.
	 *
	 * @since 1.0.0
	 * @return array Map of setting_key => page_id for each page that was created.
	 */
	private function create_required_pages(): array {
		$settings = (array) get_option( 'wcb_settings', array() );
		$created  = array();

		/**
		 * Filters the pages the setup wizard will create.
		 *
		 * Each entry is keyed by the wcb_settings option key and contains
		 * 'title' and 'content' for the page to insert. Pro (and other add-ons)
		 * use this filter to append their own required pages.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, array{title: string, content: string}> $pages Pages to create.
		 */
		$pages = apply_filters(
			'wcb_wizard_required_pages',
			array(
				'employer_registration_page' => array(
					'title'   => __( 'Employer Registration', 'wp-career-board' ),
					'content' => '<!-- wp:wp-career-board/employer-registration /-->',
				),
				'employer_dashboard_page'    => array(
					'title'   => __( 'Employer Dashboard', 'wp-career-board' ),
					'content' => '<!-- wp:wp-career-board/employer-dashboard /-->',
				),
				'candidate_dashboard_page'   => array(
					'title'   => __( 'Candidate Dashboard', 'wp-career-board' ),
					'content' => '<!-- wp:wp-career-board/candidate-dashboard /-->',
				),
				'jobs_archive_page'          => array(
					'title'   => __( 'Find Jobs', 'wp-career-board' ),
					'content' => '<!-- wp:wp-career-board/job-search /--><!-- wp:wp-career-board/job-filters /--><!-- wp:wp-career-board/job-listings /-->',
				),
				'post_job_page'              => array(
					'title'   => __( 'Post a Job', 'wp-career-board' ),
					'content' => '<!-- wp:wp-career-board/job-form /-->',
				),
				'company_archive_page'       => array(
					'title'   => __( 'Companies', 'wp-career-board' ),
					'content' => '<!-- wp:wp-career-board/company-archive /-->',
				),
			)
		);

		foreach ( $pages as $setting_key => $page_data ) {
			if ( ! empty( $settings[ $setting_key ] ) && get_post( (int) $settings[ $setting_key ] ) ) {
				continue;
			}

			// Re-use an existing published page that already contains this block.
			$block_name = '';
			if ( preg_match( '/<!-- wp:([^ \/]+)/', $page_data['content'], $m ) ) {
				$block_name = $m[1];
			}
			if ( $block_name ) {
				$existing = get_posts(
					array(
						'post_type'      => 'page',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'fields'         => 'ids',
						's'              => $block_name,
						'no_found_rows'  => true,
					)
				);
				if ( $existing ) {
					$settings[ $setting_key ] = $existing[0];
					$created[ $setting_key ]  = $existing[0];
					continue;
				}
			}

			$page_id = wp_insert_post(
				array(
					'post_title'   => $page_data['title'],
					'post_content' => $page_data['content'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);

			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$settings[ $setting_key ] = $page_id;
				$created[ $setting_key ]  = $page_id;
			}
		}

		update_option( 'wcb_settings', $settings );
		return $created;
	}

	/**
	 * Install sample taxonomy terms, a demo company, and a demo job posting.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function install_sample_data(): void {
		// Sample job categories.
		wp_insert_term( 'Technology', 'wcb_category' );
		wp_insert_term( 'Marketing', 'wcb_category' );
		wp_insert_term( 'Design', 'wcb_category' );

		// Sample job types.
		$job_types = array( 'Full-time', 'Part-time', 'Contract', 'Freelance', 'Internship' );
		foreach ( $job_types as $type ) {
			wp_insert_term( $type, 'wcb_job_type' );
		}

		// Sample experience levels.
		$experience_levels = array( 'Entry Level', 'Mid Level', 'Senior', 'Lead', 'Executive' );
		foreach ( $experience_levels as $exp ) {
			wp_insert_term( $exp, 'wcb_experience' );
		}

		// Sample company.
		$company_id = wp_insert_post(
			array(
				'post_type'    => 'wcb_company',
				'post_title'   => 'Acme Corp',
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id() ?: 1,
			)
		);

		// Link company to the current admin user.
		if ( $company_id && ! is_wp_error( $company_id ) ) {
			update_user_meta( get_current_user_id() ?: 1, '_wcb_company_id', $company_id );
		}

		// Sample job posting.
		$job_id = wp_insert_post(
			array(
				'post_type'    => 'wcb_job',
				'post_title'   => 'Senior PHP Developer',
				'post_content' => '<p>We are looking for an experienced PHP developer to join our growing team.</p>',
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id() ?: 1,
			)
		);

		if ( ! $job_id || is_wp_error( $job_id ) ) {
			return;
		}

		update_post_meta( $job_id, '_wcb_company_name', 'Acme Corp' );
		if ( $company_id && ! is_wp_error( $company_id ) ) {
			update_post_meta( $job_id, '_wcb_company_id', $company_id );
		}
		update_post_meta( $job_id, '_wcb_salary_min', 80000 );
		update_post_meta( $job_id, '_wcb_salary_max', 120000 );
		update_post_meta( $job_id, '_wcb_salary_currency', 'USD' );
		update_post_meta( $job_id, '_wcb_remote', '1' );

		$deadline_ts = strtotime( '+60 days' );
		$deadline    = false !== $deadline_ts ? gmdate( 'Y-m-d', $deadline_ts ) : gmdate( 'Y-m-d' );
		update_post_meta( $job_id, '_wcb_deadline', $deadline );

		wp_set_object_terms( $job_id, array( 'Technology' ), 'wcb_category' );
		wp_set_object_terms( $job_id, array( 'Full-time' ), 'wcb_job_type' );
		wp_set_object_terms( $job_id, array( 'Senior' ), 'wcb_experience' );

		update_option( 'wcb_sample_data_installed', true );
	}
}
