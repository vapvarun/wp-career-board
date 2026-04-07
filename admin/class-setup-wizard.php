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
	 * @since  1.0.0
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
	 * @since  1.0.0
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
	 * @since  1.0.0
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
	 * Get the wizard step definitions.
	 *
	 * @since  1.1.0
	 * @return array<string, array{title: string, template: string, button_text: string}>
	 */
	public function get_steps(): array {
		/**
		 * Filters the wizard step definitions.
		 *
		 * Each entry is keyed by a unique slug and contains 'title', 'template'
		 * (absolute path to the step partial), and 'button_text'. Pro (and other
		 * add-ons) use this filter to append their own steps.
		 *
		 * @since 1.1.0
		 *
		 * @param array<string, array{title: string, template: string, button_text: string}> $steps Steps to render.
		 */
		return apply_filters(
			'wcb_wizard_steps',
			array(
				'create-pages' => array(
					'title'       => __( 'Create Pages', 'wp-career-board' ),
					'template'    => WCB_DIR . 'admin/views/wizard-steps/create-pages.php',
					'button_text' => __( 'Create Pages & Continue', 'wp-career-board' ),
				),
				'sample-data'  => array(
					'title'       => __( 'Sample Data', 'wp-career-board' ),
					'template'    => WCB_DIR . 'admin/views/wizard-steps/sample-data.php',
					'button_text' => __( 'Finish Setup', 'wp-career-board' ),
				),
			)
		);
	}

	/**
	 * Enqueue wizard assets only on the wizard admin page.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_wizard_assets( string $hook ): void {
		if ( 'admin_page_wcb-setup' !== $hook ) {
			return;
		}

		$steps = $this->get_steps();

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
				'restUrl'    => esc_url_raw( rest_url( 'wcb/v1/wizard/' ) ),
				'steps'      => array_keys( $steps ),
				'totalSteps' => count( $steps ),
			)
		);
	}

	/**
	 * Register wizard REST routes.
	 *
	 * @since  1.0.0
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

		register_rest_route(
			$this->namespace,
			'/wizard/remove-sample-data',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'remove_sample_data_handler' ),
				'permission_callback' => array( $this, 'wizard_permission_check' ),
			)
		);
	}

	/**
	 * Permission check for all wizard REST routes.
	 *
	 * @since  1.0.0
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
	 * @param  \WP_REST_Request $request REST request (unused — no params required).
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_pages_handler( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {  // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		$created = $this->create_required_pages();
		return rest_ensure_response( $created );
	}

	/**
	 * REST handler — optionally install sample taxonomy terms, company, and job.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request REST request; reads `install_sample` param.
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
	 * @param  \WP_REST_Request $request REST request (unused — no params required).
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function complete_handler( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {  // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		update_option( 'wcb_setup_complete', true );

		/**
		 * Fires after the setup wizard marks itself complete.
		 *
		 * Pro hooks this to set its own wcbp_setup_complete flag.
		 *
		 * @since 1.1.0
		 */
		do_action( 'wcb_wizard_completed' );

		/**
		 * Filters the redirect URL shown after wizard completion.
		 *
		 * @since 1.1.0
		 *
		 * @param string $redirect Dashboard URL.
		 */
		$redirect = apply_filters( 'wcb_wizard_complete_redirect', admin_url( 'admin.php?page=wp-career-board' ) );

		return rest_ensure_response( array( 'redirect' => $redirect ) );
	}

	/**
	 * REST handler — remove all wizard sample data.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request REST request (unused).
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function remove_sample_data_handler( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {  // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		$removed = $this->remove_sample_data();
		return rest_ensure_response( $removed );
	}

	/**
	 * Render the setup wizard view.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render(): void {
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag, no state mutation.
		$rerun = isset( $_GET['wcb_rerun'] ) && '1' === $_GET['wcb_rerun'];

		/**
		 * Allows add-ons to force the wizard to render even when setup is complete.
		 *
		 * Pro uses this for the mini-wizard (wcbp_only=1) so Pro-specific steps
		 * can run after the Free wizard has already finished.
		 *
		 * @since 1.1.0
		 *
		 * @param bool $force Whether to force-render the wizard.
		 */
		$force_render = apply_filters( 'wcb_wizard_force_render', $rerun );

		if ( get_option( 'wcb_setup_complete', false ) && ! $force_render ) {
			include_once WCB_DIR . 'admin/views/setup-wizard-complete.php';
			return;
		}

		$steps = $this->get_steps(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- template variable, not a global.
		include_once WCB_DIR . 'admin/views/setup-wizard.php';
	}

	/**
	 * Create the four required WCB pages and persist their IDs in wcb_settings.
	 *
	 * Skips any page whose setting key already has a non-zero value.
	 *
	 * @since  1.0.0
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
			if ( preg_match( '/<!-- wp:([a-z0-9-]+(?:\/[a-z0-9-]+)?)/', $page_data['content'], $m ) ) {
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
	 * Install rich sample data so the site admin can see the plugin in action.
	 *
	 * Creates 3 companies, 8 published jobs across multiple categories,
	 * and all required taxonomy terms. Every created ID is tracked in the
	 * `wcb_sample_data_ids` option so removal is clean.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function install_sample_data(): void {
		$author_id   = get_current_user_id() ? get_current_user_id() : 1;
		$created_ids = array(
			'companies' => array(),
			'jobs'      => array(),
			'terms'     => array(),
		);

		$deadline_3m = gmdate( 'Y-m-d', (int) strtotime( '+3 months' ) );
		$deadline_6w = gmdate( 'Y-m-d', (int) strtotime( '+6 weeks' ) );
		$deadline_2m = gmdate( 'Y-m-d', (int) strtotime( '+2 months' ) );

		// -----------------------------------------------------------------
		// 1. Taxonomy terms.
		// -----------------------------------------------------------------
		$terms = array(
			'wcb_category'   => array( 'Engineering', 'Design', 'Marketing', 'Product', 'Data', 'Customer Success' ),
			'wcb_job_type'   => array( 'Full-time', 'Part-time', 'Contract', 'Freelance', 'Internship' ),
			'wcb_location'   => array( 'Remote', 'San Francisco, CA', 'New York, NY', 'London, UK' ),
			'wcb_experience' => array( 'Entry Level', 'Mid Level', 'Senior', 'Lead', 'Executive' ),
			'wcb_tag'        => array( 'Remote-first', 'SaaS', 'Fintech', 'E-commerce', 'Open Source' ),
		);

		$term_map = array();
		foreach ( $terms as $taxonomy => $names ) {
			foreach ( $names as $name ) {
				$existing = get_term_by( 'name', $name, $taxonomy );
				if ( $existing instanceof \WP_Term ) {
					$term_map[ $taxonomy ][ $name ] = $existing->term_id;
					continue;
				}
				$result = wp_insert_term( $name, $taxonomy );
				if ( ! is_wp_error( $result ) ) {
					$tid                            = (int) $result['term_id'];
					$term_map[ $taxonomy ][ $name ] = $tid;
					$created_ids['terms'][]         = array(
						'term_id'  => $tid,
						'taxonomy' => $taxonomy,
					);
				}
			}
		}

		// -----------------------------------------------------------------
		// 2. Companies.
		// -----------------------------------------------------------------
		$companies = array(
			array(
				'title' => 'Acme Corp',
				'slug'  => 'acme-corp',
				'meta'  => array(
					'_wcb_tagline'      => 'Building the future, one product at a time',
					'_wcb_website'      => 'https://acme-corp.example.com',
					'_wcb_industry'     => 'SaaS / Productivity',
					'_wcb_company_size' => '201-500',
					'_wcb_hq_location'  => 'San Francisco, CA',
				),
			),
			array(
				'title' => 'Starter Labs',
				'slug'  => 'starter-labs',
				'meta'  => array(
					'_wcb_tagline'      => 'Developer tools for the modern web',
					'_wcb_website'      => 'https://starterlabs.example.com',
					'_wcb_industry'     => 'Developer Tools',
					'_wcb_company_size' => '51-200',
					'_wcb_hq_location'  => 'Remote',
				),
			),
			array(
				'title' => 'PayFlow',
				'slug'  => 'payflow',
				'meta'  => array(
					'_wcb_tagline'      => 'Payments infrastructure for the internet',
					'_wcb_website'      => 'https://payflow.example.com',
					'_wcb_industry'     => 'Fintech',
					'_wcb_company_size' => '501-1000',
					'_wcb_hq_location'  => 'New York, NY',
				),
			),
		);

		$company_ids = array();
		foreach ( $companies as $co ) {
			$existing = get_page_by_path( $co['slug'], OBJECT, 'wcb_company' );
			if ( $existing ) {
				$company_ids[ $co['slug'] ] = (int) $existing->ID;
				continue;
			}
			$cid = wp_insert_post(
				array(
					'post_type'   => 'wcb_company',
					'post_title'  => $co['title'],
					'post_name'   => $co['slug'],
					'post_status' => 'publish',
					'post_author' => $author_id,
				)
			);
			if ( $cid && ! is_wp_error( $cid ) ) {
				foreach ( $co['meta'] as $k => $v ) {
					update_post_meta( $cid, $k, $v );
				}
				update_post_meta( $cid, '_wcb_user_id', $author_id );
				$company_ids[ $co['slug'] ] = $cid;
				$created_ids['companies'][] = $cid;
			}
		}

		// Link admin to first company (primary).
		if ( ! empty( $company_ids ) && ! get_user_meta( $author_id, '_wcb_company_id', true ) ) {
			update_user_meta( $author_id, '_wcb_company_id', reset( $company_ids ) );
		}

		// -----------------------------------------------------------------
		// 3. Jobs — 8 published across 3 companies.
		// -----------------------------------------------------------------
		$jobs = array(
			array(
				'title'   => 'Senior PHP Developer',
				'slug'    => 'wcb-sample-senior-php-developer',
				'company' => 'acme-corp',
				'content' => "We're looking for an experienced PHP developer to join our growing team.\n\n**What you'll do:**\n- Build and maintain scalable backend services with PHP 8+\n- Design RESTful APIs used by our frontend and mobile apps\n- Write tests, participate in code reviews, and mentor junior developers\n\n**You have:**\n- 5+ years of PHP experience (Laravel or WordPress preferred)\n- Strong understanding of SQL, caching, and queue systems",
				'meta'    => array(
					'_wcb_salary_min'      => '120000',
					'_wcb_salary_max'      => '160000',
					'_wcb_salary_currency' => 'USD',
					'_wcb_salary_type'     => 'annual',
					'_wcb_remote'          => '1',
					'_wcb_deadline'        => $deadline_3m,
					'_wcb_featured'        => '1',
				),
				'tax'     => array(
					'wcb_category'   => array( 'Engineering' ),
					'wcb_job_type'   => array( 'Full-time' ),
					'wcb_location'   => array( 'Remote', 'San Francisco, CA' ),
					'wcb_experience' => array( 'Senior' ),
				),
			),
			array(
				'title'   => 'Product Designer',
				'slug'    => 'wcb-sample-product-designer',
				'company' => 'acme-corp',
				'content' => "Shape the product experience for thousands of users.\n\n**Responsibilities:**\n- Own end-to-end design for key product areas\n- Create wireframes, prototypes, and high-fidelity mockups in Figma\n- Run usability studies and translate insights into designs",
				'meta'    => array(
					'_wcb_salary_min'      => '110000',
					'_wcb_salary_max'      => '145000',
					'_wcb_salary_currency' => 'USD',
					'_wcb_salary_type'     => 'annual',
					'_wcb_remote'          => '1',
					'_wcb_deadline'        => $deadline_2m,
					'_wcb_featured'        => '1',
				),
				'tax'     => array(
					'wcb_category'   => array( 'Design' ),
					'wcb_job_type'   => array( 'Full-time' ),
					'wcb_location'   => array( 'Remote' ),
					'wcb_experience' => array( 'Mid Level', 'Senior' ),
				),
			),
			array(
				'title'   => 'Growth Marketing Manager',
				'slug'    => 'wcb-sample-growth-marketing-manager',
				'company' => 'acme-corp',
				'content' => "Drive product-led growth across acquisition, activation, and retention.\n\n**Requirements:**\n- 4+ years in growth marketing at a SaaS company\n- Data-driven — comfortable with SQL, analytics tools, and A/B testing\n- Experience managing paid channels and SEO strategy",
				'meta'    => array(
					'_wcb_salary_min'      => '100000',
					'_wcb_salary_max'      => '135000',
					'_wcb_salary_currency' => 'USD',
					'_wcb_salary_type'     => 'annual',
					'_wcb_remote'          => '0',
					'_wcb_deadline'        => $deadline_6w,
					'_wcb_featured'        => '0',
				),
				'tax'     => array(
					'wcb_category'   => array( 'Marketing' ),
					'wcb_job_type'   => array( 'Full-time' ),
					'wcb_location'   => array( 'San Francisco, CA' ),
					'wcb_experience' => array( 'Senior' ),
				),
			),
			array(
				'title'   => 'Frontend Engineer',
				'slug'    => 'wcb-sample-frontend-engineer',
				'company' => 'starter-labs',
				'content' => "Build beautiful, performant developer tools.\n\n**What you'll do:**\n- Build React/TypeScript components for our developer dashboard\n- Optimize Core Web Vitals and bundle size\n- Work closely with designers to deliver pixel-perfect UIs",
				'meta'    => array(
					'_wcb_salary_min'      => '130000',
					'_wcb_salary_max'      => '175000',
					'_wcb_salary_currency' => 'USD',
					'_wcb_salary_type'     => 'annual',
					'_wcb_remote'          => '1',
					'_wcb_deadline'        => $deadline_3m,
					'_wcb_featured'        => '0',
				),
				'tax'     => array(
					'wcb_category'   => array( 'Engineering' ),
					'wcb_job_type'   => array( 'Full-time' ),
					'wcb_location'   => array( 'Remote' ),
					'wcb_experience' => array( 'Mid Level' ),
					'wcb_tag'        => array( 'Remote-first', 'Open Source' ),
				),
			),
			array(
				'title'   => 'DevOps Engineer',
				'slug'    => 'wcb-sample-devops-engineer',
				'company' => 'starter-labs',
				'content' => "Own our cloud infrastructure and CI/CD pipelines.\n\n**Requirements:**\n- 3+ years with AWS or GCP\n- Strong Kubernetes and Terraform experience\n- Observability mindset — Prometheus, Grafana, OpenTelemetry",
				'meta'    => array(
					'_wcb_salary_min'      => '140000',
					'_wcb_salary_max'      => '185000',
					'_wcb_salary_currency' => 'USD',
					'_wcb_salary_type'     => 'annual',
					'_wcb_remote'          => '1',
					'_wcb_deadline'        => $deadline_2m,
					'_wcb_featured'        => '0',
				),
				'tax'     => array(
					'wcb_category'   => array( 'Engineering' ),
					'wcb_job_type'   => array( 'Full-time' ),
					'wcb_location'   => array( 'Remote' ),
					'wcb_experience' => array( 'Senior' ),
					'wcb_tag'        => array( 'Remote-first' ),
				),
			),
			array(
				'title'   => 'Data Analyst',
				'slug'    => 'wcb-sample-data-analyst',
				'company' => 'payflow',
				'content' => "Turn data into insights that drive business decisions.\n\n**You have:**\n- 3+ years of analytics experience\n- Expert SQL skills and experience with Python or R\n- Experience building dashboards in Looker, Metabase, or similar",
				'meta'    => array(
					'_wcb_salary_min'      => '95000',
					'_wcb_salary_max'      => '130000',
					'_wcb_salary_currency' => 'USD',
					'_wcb_salary_type'     => 'annual',
					'_wcb_remote'          => '0',
					'_wcb_deadline'        => $deadline_6w,
					'_wcb_featured'        => '0',
				),
				'tax'     => array(
					'wcb_category'   => array( 'Data' ),
					'wcb_job_type'   => array( 'Full-time' ),
					'wcb_location'   => array( 'New York, NY' ),
					'wcb_experience' => array( 'Mid Level' ),
					'wcb_tag'        => array( 'Fintech' ),
				),
			),
			array(
				'title'   => 'Backend Engineer — Payments',
				'slug'    => 'wcb-sample-backend-engineer-payments',
				'company' => 'payflow',
				'content' => "Build reliable, secure payment systems processing millions of transactions.\n\n**What you'll do:**\n- Design and build APIs for payment processing, settlements, and reconciliation\n- Work with Go and PostgreSQL in a microservices architecture\n- Ensure PCI DSS compliance across all payment flows",
				'meta'    => array(
					'_wcb_salary_min'      => '160000',
					'_wcb_salary_max'      => '220000',
					'_wcb_salary_currency' => 'USD',
					'_wcb_salary_type'     => 'annual',
					'_wcb_remote'          => '1',
					'_wcb_deadline'        => $deadline_3m,
					'_wcb_featured'        => '1',
				),
				'tax'     => array(
					'wcb_category'   => array( 'Engineering' ),
					'wcb_job_type'   => array( 'Full-time' ),
					'wcb_location'   => array( 'Remote', 'New York, NY' ),
					'wcb_experience' => array( 'Senior', 'Lead' ),
					'wcb_tag'        => array( 'Fintech', 'Remote-first' ),
				),
			),
			array(
				'title'   => 'Customer Success Manager',
				'slug'    => 'wcb-sample-customer-success-manager',
				'company' => 'payflow',
				'content' => "Be the trusted advisor for our enterprise customers.\n\n**Requirements:**\n- 3+ years in customer success at a B2B SaaS company\n- Technical fluency — comfortable discussing APIs and integrations\n- Experience managing enterprise accounts with \$100K+ ARR",
				'meta'    => array(
					'_wcb_salary_min'      => '90000',
					'_wcb_salary_max'      => '120000',
					'_wcb_salary_currency' => 'USD',
					'_wcb_salary_type'     => 'annual',
					'_wcb_remote'          => '0',
					'_wcb_deadline'        => $deadline_2m,
					'_wcb_featured'        => '0',
				),
				'tax'     => array(
					'wcb_category'   => array( 'Customer Success' ),
					'wcb_job_type'   => array( 'Full-time' ),
					'wcb_location'   => array( 'New York, NY' ),
					'wcb_experience' => array( 'Mid Level' ),
					'wcb_tag'        => array( 'Fintech', 'SaaS' ),
				),
			),
		);

		foreach ( $jobs as $job ) {
			$existing = get_page_by_path( $job['slug'], OBJECT, 'wcb_job' );
			if ( $existing ) {
				continue;
			}
			$cid = $company_ids[ $job['company'] ] ?? 0;

			$jid = wp_insert_post(
				array(
					'post_type'    => 'wcb_job',
					'post_title'   => $job['title'],
					'post_name'    => $job['slug'],
					'post_content' => $job['content'],
					'post_status'  => 'publish',
					'post_author'  => $author_id,
				)
			);

			if ( ! $jid || is_wp_error( $jid ) ) {
				continue;
			}

			foreach ( $job['meta'] as $k => $v ) {
				update_post_meta( $jid, $k, $v );
			}
			if ( $cid ) {
				$co_post = get_post( $cid );
				update_post_meta( $jid, '_wcb_company_id', $cid );
				update_post_meta( $jid, '_wcb_company_name', $co_post ? $co_post->post_title : '' );
			}
			foreach ( $job['tax'] as $taxonomy => $names ) {
				$tids = array();
				foreach ( $names as $name ) {
					if ( isset( $term_map[ $taxonomy ][ $name ] ) ) {
						$tids[] = $term_map[ $taxonomy ][ $name ];
					}
				}
				if ( $tids ) {
					wp_set_post_terms( $jid, $tids, $taxonomy );
				}
			}
			$created_ids['jobs'][] = $jid;
		}

		// -----------------------------------------------------------------
		// 4. Track + flag.
		// -----------------------------------------------------------------
		update_option( 'wcb_sample_data_ids', $created_ids );
		update_option( 'wcb_sample_data_installed', true );
	}

	/**
	 * Remove all sample data created by the wizard.
	 *
	 * Uses the `wcb_sample_data_ids` option to surgically delete only
	 * wizard-created content.
	 *
	 * @since  1.0.0
	 * @return array{jobs: int, companies: int, terms: int} Counts of removed items.
	 */
	public function remove_sample_data(): array {
		$ids     = (array) get_option( 'wcb_sample_data_ids', array() );
		$removed = array(
			'jobs'      => 0,
			'companies' => 0,
			'terms'     => 0,
		);

		// Jobs.
		foreach ( $ids['jobs'] ?? array() as $jid ) {
			if ( get_post( (int) $jid ) ) {
				wp_delete_post( (int) $jid, true );
				++$removed['jobs'];
			}
		}

		// Companies.
		foreach ( $ids['companies'] ?? array() as $cid ) {
			if ( get_post( (int) $cid ) ) {
				wp_delete_post( (int) $cid, true );
				++$removed['companies'];
			}
		}

		// Terms (only delete if still created by wizard — skip if user added jobs to them).
		foreach ( $ids['terms'] ?? array() as $entry ) {
			$term = get_term( (int) $entry['term_id'], $entry['taxonomy'] );
			if ( $term instanceof \WP_Term && 0 === $term->count ) {
				wp_delete_term( $term->term_id, $entry['taxonomy'] );
				++$removed['terms'];
			}
		}

		// Remove orphaned _wcb_company_id from the user who ran the wizard.
		$admin_company = get_user_meta( get_current_user_id(), '_wcb_company_id', true );
		if ( $admin_company && ! get_post( (int) $admin_company ) ) {
			delete_user_meta( get_current_user_id(), '_wcb_company_id' );
		}

		delete_option( 'wcb_sample_data_ids' );
		delete_option( 'wcb_sample_data_installed' );

		return $removed;
	}
}
