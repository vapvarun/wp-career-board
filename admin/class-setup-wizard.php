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
	 * Sample companies: name, tagline, industry slug, size, HQ, website.
	 *
	 * Kept in step with the Wbcom Demo Installer's Career Board content, so the
	 * wizard and the installer build the same board.
	 *
	 * @since 1.8.3
	 */
	private const SAMPLE_COMPANIES = array(
		array( 'Roastworks', 'Coffee subscriptions for people who care about the bean', 'retail', '11-50', 'Berlin, DE', 'https://roastworks.example' ),
		array( 'Paperplane Studio', 'A remote-first product studio for small brands', 'design', '11-50', 'Lagos, NG', 'https://paperplane.example' ),
		array( 'Crumb', 'Ordering and stock software for independent bakeries', 'technology', '1-10', 'Kochi, IN', 'https://crumb.example' ),
	);

	/**
	 * Sample jobs: company index, title, category, type, location, experience,
	 * salary min, salary max, currency, pay period (yearly / monthly / hourly),
	 * remote, description (blank line between paragraphs).
	 *
	 * @since 1.8.3
	 */
	private const SAMPLE_JOBS = array(
		array( 0, 'Backend Engineer (Go)', 'Engineering', 'Full-time', 'Berlin, DE', 'Mid Level', 65000, 85000, 'EUR', 'yearly', true, "We are rebuilding our subscription engine in Go and need a second backend engineer.\n\nYou will own billing and delivery scheduling, work closely with our two-person ops team, and help us choose what not to build.\n\nYou have shipped a Go service to production, you like boring reliable systems, and you write things down." ),
		array( 0, 'Customer Happiness Lead', 'Customer Success', 'Part-time', 'Remote', 'Mid Level', 30000, 38000, 'EUR', 'yearly', true, 'Help 9,000 coffee lovers get the right beans. You will answer email and chat, spot patterns in what people ask, and tell product what to fix first.' ),
		array( 1, 'Senior Product Designer', 'Design', 'Full-time', 'Remote', 'Senior', 70000, 90000, 'USD', 'yearly', true, "Design end to end for two client products at a time.\n\nYou run research, sketch quickly, prototype, and stay with the work until it ships. You are comfortable presenting to founders and saying no with reasons." ),
		array( 1, 'Frontend Developer', 'Engineering', 'Full-time', 'Remote', 'Mid Level', 55000, 72000, 'USD', 'yearly', true, 'Build accessible, fast interfaces with our designers. Modern CSS, a little JavaScript, a lot of care. We test with real keyboards and screen readers.' ),
		array( 1, 'UX Writer (Contract)', 'Design', 'Contract', 'Remote', 'Mid Level', 50, 70, 'USD', 'hourly', true, 'Three months, two products, every word on screen. Error messages, onboarding, empty states. Hourly rate, flexible hours.' ),
		array( 2, 'Full-stack Developer', 'Engineering', 'Full-time', 'Kochi, IN', 'Mid Level', 1800000, 2600000, 'INR', 'yearly', false, 'Join employee number three. Laravel and Vue today, whatever we need tomorrow. You will talk to bakers every week.' ),
		array( 2, 'Mobile Developer (Android)', 'Engineering', 'Contract', 'Remote', 'Senior', 30000, 45000, 'USD', 'yearly', true, 'Our ordering app has to work in kitchens with flour on the screen and no wifi. Offline-first experience is a must.' ),
		array( 2, 'Junior Support Engineer', 'Customer Success', 'Full-time', 'Kochi, IN', 'Entry Level', 600000, 800000, 'INR', 'yearly', false, 'Learn our product inside out, fix small bugs, and help bakeries get set up. Great first role, we will teach you a lot.' ),
	);



	/**
	 * Detect whether first-run setup has completed.
	 *
	 * Composes two signals so a desynced flag (DB restore, manual option
	 * deletion, partial wizard run that never persisted the flag) doesn't
	 * silently re-launch the wizard over a configured site:
	 *
	 *  1. The legacy `wcb_setup_complete` flag we explicitly write at the
	 *     end of the wizard.
	 *  2. Content fallback — if `wcb_settings` already maps any of the core
	 *     page IDs (employer dashboard, candidate dashboard, jobs archive)
	 *     to a real post, the wizard has run at some point in this site's
	 *     life and we should not restart it.
	 *
	 * @since 1.1.1
	 *
	 * @return bool
	 */
	public static function is_setup_complete(): bool {
		if ( (bool) get_option( 'wcb_setup_complete', false ) ) {
			return true;
		}
		foreach ( array( 'employer_dashboard_page', 'candidate_dashboard_page', 'jobs_archive_page' ) as $wcb_key ) {
			$wcb_id = Settings::int( $wcb_key, 0 );
			if ( $wcb_id > 0 && get_post( $wcb_id ) ) {
				return true;
			}
		}
		return false;
	}

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
		add_action( 'admin_post_wcb_install_demo', array( $this, 'handle_install_demo' ) );
		add_filter( 'admin_body_class', array( $this, 'focus_mode_class' ) );
	}

	/**
	 * Hide the admin menu while the wizard runs, so the owner finishes one
	 * task instead of wandering off half configured. "Exit setup" in the
	 * wizard header is the way out.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed  $classes Space-separated body classes.
	 * @return mixed
	 */
	public function focus_mode_class( $classes ) {
		if ( ! is_string( $classes ) ) {
			return $classes; // Another filter handed back a non-string: pass it on untouched.
		}
		$screen = get_current_screen();
		return $screen && 'admin_page_wcb-setup' === $screen->id ? $classes . ' wcb-wizard-focus' : $classes;
	}

	/**
	 * Install sample data from the Settings page (server-side form action), so
	 * the demo content can be created without re-running the setup wizard.
	 *
	 * @since 1.3.1
	 * @return void
	 */
	public function handle_install_demo(): void {
		check_admin_referer( 'wcb_install_demo' );

		if ( ! wp_is_ability_granted( 'wcb/manage-settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
			wp_die( esc_html__( 'You do not have permission to do this.', 'wp-career-board' ) );
		}

		$this->install_sample_data();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'wcb-settings',
					'tab'      => 'listings',
					'wcb_demo' => 'installed',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
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

		if ( self::is_setup_complete() ) {
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
		// Parent slug 'options.php' keeps the page accessible at
		// admin.php?page=wcb-setup but registers it in the $submenu global
		// so get_admin_page_title() resolves the title without WordPress
		// tripping on PHP 8.1+ strip_tags(null) in admin-header.php.
		add_submenu_page(
			'options.php',
			__( 'Setup wizard', 'wp-career-board' ),
			__( 'Setup wizard', 'wp-career-board' ),
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
					'title'       => __( 'Pages', 'wp-career-board' ),
					'template'    => WCB_DIR . 'admin/views/wizard-steps/create-pages.php',
					'button_text' => __( 'Create Pages & Continue', 'wp-career-board' ),
				),
				'registration' => array(
					'title'       => __( 'Sign-ups', 'wp-career-board' ),
					'template'    => WCB_DIR . 'admin/views/wizard-steps/registration.php',
					'button_text' => __( 'Save & Continue', 'wp-career-board' ),
				),
				'jobs'         => array(
					'title'       => __( 'Jobs', 'wp-career-board' ),
					'template'    => WCB_DIR . 'admin/views/wizard-steps/jobs.php',
					'button_text' => __( 'Save & Continue', 'wp-career-board' ),
				),
				'emails'       => array(
					'title'       => __( 'Emails', 'wp-career-board' ),
					'template'    => WCB_DIR . 'admin/views/wizard-steps/emails.php',
					'button_text' => __( 'Save & Continue', 'wp-career-board' ),
				),
				'anti-spam'    => array(
					'title'       => __( 'Spam Protection', 'wp-career-board' ),
					'template'    => WCB_DIR . 'admin/views/wizard-steps/anti-spam.php',
					'button_text' => __( 'Save & Continue', 'wp-career-board' ),
				),
				'sample-data'  => array(
					'title'       => __( 'Sample data', 'wp-career-board' ),
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
				'restUrl'    => esc_url_raw( untrailingslashit( rest_url( 'wcb/v1/wizard' ) ) ),
				'steps'      => array_keys( $steps ),
				'totalSteps' => count( $steps ),
				'i18n'       => array(
					'saveFailed' => __( 'Could not save. Check your connection and try again.', 'wp-career-board' ),
				),
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
			'/wizard/settings',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'settings_handler' ),
				'permission_callback' => array( $this, 'wizard_permission_check' ),
				'args'                => array(
					'settings' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
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
					'add_menu'       => array(
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
	 * @return bool|\WP_Error
	 */
	public function wizard_permission_check(): bool|\WP_Error {
		if ( $this->check_ability( 'wcb/manage-settings' ) ) {
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
	public function create_pages_handler( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {   // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		$created = Pages::create_missing();
		return rest_ensure_response( $created );
	}

	/**
	 * REST handler - save the answers of a settings step.
	 *
	 * Accepts any key in the settings schema plus WordPress's own
	 * `users_can_register`, and saves through the same sanitizer as the
	 * Settings screen, merged over what is stored.
	 *
	 * @since 1.8.0
	 *
	 * @param  \WP_REST_Request $request REST request; `settings` is key => value.
	 * @return \WP_REST_Response
	 */
	public function settings_handler( \WP_REST_Request $request ): \WP_REST_Response {
		$input = (array) $request->get_param( 'settings' );

		// On multisite core reads the network's setting, so a subsite value would only mislead.
		if ( array_key_exists( 'users_can_register', $input ) && ! is_multisite() ) {
			update_option( 'users_can_register', rest_sanitize_boolean( $input['users_can_register'] ) ? 1 : 0 );
		}

		$input = array_intersect_key( $input, SettingsSchema::fields() );
		if ( $input ) {
			$input['_wcb_form'] = 1;
			update_option( 'wcb_settings', ( new AdminSettings() )->sanitize( $input ) );
		}

		return rest_ensure_response( array( 'saved' => true ) );
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
		return rest_ensure_response(
			array(
				'installed' => (bool) $request->get_param( 'install_sample' ),
				'menu'      => $request->get_param( 'add_menu' ) ? self::create_jobs_menu() : 0,
			)
		);
	}

	/**
	 * REST handler — mark setup complete and return the dashboard redirect URL.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request REST request (unused — no params required).
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function complete_handler( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {   // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
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
	public function remove_sample_data_handler( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {   // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
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

		if ( self::is_setup_complete() && ! $force_render ) {
			include_once WCB_DIR . 'admin/views/setup-wizard-complete.php';
			return;
		}

		$steps = $this->get_steps(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- template variable, not a global.
		include_once WCB_DIR . 'admin/views/setup-wizard.php';
	}

	/**
	 * The canonical sample vocabulary, by taxonomy.
	 *
	 * Public because it is the ONE source for "what terms does a demo site have".
	 * The scale-benchmark harness (cli/class-scale-command.php) reads this rather
	 * than keeping a parallel list: it used to derive display names by ucwords-ing
	 * a slug, so 'full-time' became "Full Time" and claimed the `full-time` slug,
	 * and this seeder's "Full-time" then landed on `full-time-2`. A site that had
	 * run both showed candidates two near-identical Job Type checkboxes, one of
	 * them matching nothing.
	 *
	 * @since 1.7.2
	 *
	 * @return array<string, string[]> Taxonomy => display names.
	 */
	public static function sample_terms(): array {
		return array(
			'wcb_category'   => array( 'Engineering', 'Design', 'Marketing', 'Product', 'Data', 'Customer Success' ),
			'wcb_job_type'   => array( 'Full-time', 'Part-time', 'Contract', 'Freelance', 'Internship' ),
			'wcb_location'   => array( 'Remote', 'Berlin, DE', 'Lagos, NG', 'Kochi, IN' ),
			'wcb_experience' => array( 'Entry Level', 'Mid Level', 'Senior', 'Lead', 'Executive' ),
			'wcb_tag'        => array( 'Remote-first', 'SaaS', 'Fintech', 'E-commerce', 'Open Source' ),
		);
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
		// With the Demo Installer active it owns the demo; never build a second set.
		if ( '' !== self::demo_installer_url() ) {
			return;
		}
		$author_id   = get_current_user_id() ? get_current_user_id() : 1;
		$created_ids = array(
			'companies' => array(),
			'jobs'      => array(),
			'terms'     => array(),
		);

		// -----------------------------------------------------------------
		// 1. Taxonomy terms.
		// -----------------------------------------------------------------
		$terms = self::sample_terms();

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
		// The same companies and jobs the Wbcom Demo Installer builds, so a
		// site gets one demo board whichever way it was set up.
		$companies = array();
		foreach ( self::SAMPLE_COMPANIES as $co ) {
			list( $title, $tagline, $industry, $size, $hq, $site ) = $co;
			$companies[] = array(
				'title' => $title,
				'slug'  => sanitize_title( $title ),
				'meta'  => array(
					'_wcb_tagline'      => $tagline,
					'_wcb_website'      => $site,
					'_wcb_industry'     => $industry,
					'_wcb_company_size' => $size,
					'_wcb_hq_location'  => $hq,
				),
			);
		}

		$company_ids = array();
		foreach ( $companies as $co ) {
			$existing = get_page_by_path( $co['slug'], OBJECT, 'wcb_company' );
			if ( $existing ) {
				$company_ids[ $co['slug'] ] = (int) $existing->ID;
				// Re-track on re-run so wcb_sample_data_ids stays complete
				// instead of being overwritten with an empty map.
				$created_ids['companies'][] = (int) $existing->ID;
				continue;
			}
			// Companies joined before their jobs were posted.
			$joined_gmt = gmdate( 'Y-m-d H:i:s', time() - wp_rand( 25, 60 ) * DAY_IN_SECONDS );
			$cid        = wp_insert_post(
				array(
					'post_type'     => 'wcb_company',
					'post_title'    => $co['title'],
					'post_name'     => $co['slug'],
					'post_status'   => 'publish',
					'post_author'   => $author_id,
					'post_date_gmt' => $joined_gmt,
				)
			);
			if ( $cid && ! is_wp_error( $cid ) ) {
				foreach ( $co['meta'] as $k => $v ) {
					update_post_meta( $cid, $k, $v );
				}
				update_post_meta( $cid, '_wcb_sample', 1 );
				\WCB\Core\Locations::sync_company_hq( (int) $cid, (string) $co['meta']['_wcb_hq_location'] );
				$company_ids[ $co['slug'] ] = $cid;
				$created_ids['companies'][] = $cid;
			}
		}

		// Link admin to first company (primary).
		if ( ! empty( $company_ids ) && ! get_user_meta( $author_id, '_wcb_company_id', true ) ) {
			update_user_meta( $author_id, '_wcb_company_id', reset( $company_ids ) );
		}

		// -----------------------------------------------------------------
		// 3. Jobs - 8 published across 3 companies.
		// -----------------------------------------------------------------
		$company_slugs = array_column( $companies, 'slug' );
		$jobs          = array();
		foreach ( self::SAMPLE_JOBS as $i => $j ) {
			list( $ci, $title, $cat, $type, $loc, $exp, $min, $max, $currency, $period, $remote, $body ) = $j;
			$jobs[] = array(
				'title'   => $title,
				'slug'    => 'wcb-sample-' . sanitize_title( $title ),
				'company' => $company_slugs[ $ci ],
				'content' => $body,
				'meta'    => array(
					'_wcb_salary_min'      => (string) $min,
					'_wcb_salary_max'      => (string) $max,
					// Each job's own currency and pay period, so Kochi pay reads in rupees
					// and a contract rate reads per hour, whatever the site currency.
					'_wcb_salary_currency' => $currency,
					'_wcb_salary_type'     => $period,
					'_wcb_remote'          => $remote ? '1' : '0',
					'_wcb_deadline'        => gmdate( 'Y-m-d', (int) strtotime( '+' . ( 20 + $i * 5 ) . ' days' ) ),
					'_wcb_featured'        => 0 === $i % 3 ? '1' : '0',
				),
				'tax'     => array(
					'wcb_category'   => array( $cat ),
					'wcb_job_type'   => array( $type ),
					'wcb_location'   => array( $loc ),
					'wcb_experience' => array( $exp ),
				),
			);
		}

		// Spread the sample over the last three weeks: "posted today" on every
		// job read as fake on a fresh board.
		$job_age = array( 1, 2, 4, 6, 9, 12, 15, 19 );
		foreach ( $jobs as $job_index => $job ) {
			$existing = get_page_by_path( $job['slug'], OBJECT, 'wcb_job' );
			if ( $existing ) {
				// Re-track on re-run so wcb_sample_data_ids stays complete.
				$created_ids['jobs'][] = (int) $existing->ID;
				continue;
			}
			$cid = $company_ids[ $job['company'] ] ?? 0;

			$posted_gmt = gmdate( 'Y-m-d H:i:s', time() - $job_age[ $job_index % count( $job_age ) ] * DAY_IN_SECONDS - wp_rand( 0, 8 ) * HOUR_IN_SECONDS );
			$jid        = wp_insert_post(
				array(
					'post_type'     => 'wcb_job',
					'post_title'    => $job['title'],
					'post_name'     => $job['slug'],
					'post_content'  => self::sample_content_to_html( $job['content'] ),
					'post_status'   => 'publish',
					'post_author'   => $author_id,
					'post_date_gmt' => $posted_gmt,
				)
			);

			if ( ! $jid || is_wp_error( $jid ) ) {
				continue;
			}

			foreach ( $job['meta'] as $k => $v ) {
				update_post_meta( $jid, $k, $v );
			}
			update_post_meta( $jid, '_wcb_sample', 1 );
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

		/**
		 * Fires after sample/demo data is installed. Pro hooks this to seed
		 * demo resumes (and other Pro-owned content) tied to the sample set.
		 *
		 * @since 1.3.1
		 *
		 * @param array{companies: int[], jobs: int[], terms: list<array{term_id: int, taxonomy: string}>} $created_ids Created sample IDs.
		 */
		do_action( 'wcb_sample_data_installed', $created_ids );
	}

	/**
	 * Convert the lightweight markdown used in sample job content (blank-line
	 * paragraphs, `**Heading:**` lines, `- ` bullets) into clean HTML, so the
	 * demo jobs render as structured content - headings, paragraphs, and lists -
	 * on the frontend and in the editor, instead of raw markdown.
	 *
	 * @since 1.3.1
	 *
	 * @param  string $content Markdown-ish sample content.
	 * @return string HTML.
	 */
	private static function sample_content_to_html( string $content ): string {
		$html = '';
		$list = array();

		foreach ( explode( "\n", trim( $content ) ) as $raw ) {
			$line = trim( $raw );
			if ( '' === $line ) {
				continue;
			}

			if ( preg_match( '/^[-*]\s+(.+)/', $line, $m ) ) {
				$list[] = $m[1];
				continue;
			}

			if ( $list ) {
				$html .= '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $list ) ) . '</li></ul>';
				$list  = array();
			}

			if ( preg_match( '/^\*\*(.+?):?\*\*$/', $line, $m ) ) {
				$html .= '<h3>' . esc_html( $m[1] ) . '</h3>';
			} else {
				$html .= '<p>' . esc_html( $line ) . '</p>';
			}
		}

		if ( $list ) {
			$html .= '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $list ) ) . '</li></ul>';
		}

		return $html;
	}

	/**
	 * The Wbcom Demo Installer's wizard, when that plugin is active.
	 *
	 * It builds the full demo (people, applications, logos, menu) from one
	 * shared cast for every Wbcom product, so the wizard sends the owner there
	 * instead of creating a second set. Support, not a dependency: '' when the
	 * installer is absent.
	 *
	 * @since 1.8.3
	 * @return string Admin URL, or ''.
	 */
	public static function demo_installer_url(): string {
		return class_exists( '\\WbcomDemo\\Admin\\Page' ) ? (string) call_user_func( array( '\\WbcomDemo\\Admin\\Page', 'url' ) ) : '';
	}

	/**
	 * Create a "Jobs" menu with Find Jobs, Companies and Post a Job.
	 *
	 * Opt-in from the wizard. The menu goes to the theme's first menu location
	 * only when that location has no menu yet; an owner's existing menu is
	 * never replaced, they assign this one in Appearance > Menus.
	 *
	 * @since 1.8.3
	 * @return int Menu ID, or 0 when nothing was created.
	 */
	public static function create_jobs_menu(): int {
		$items = array_filter(
			array(
				\WCB\Admin\Pages::get_id( 'jobs_archive_page' ),
				\WCB\Admin\Pages::get_id( 'company_archive_page' ),
				\WCB\Admin\Pages::get_id( 'post_job_page' ),
			),
			static fn( int $id ): bool => $id > 0 && 'publish' === get_post_status( $id )
		);
		if ( array() === $items ) {
			return 0;
		}
		$name = __( 'Jobs', 'wp-career-board' );
		$menu = wp_get_nav_menu_object( $name ) ? 0 : wp_create_nav_menu( $name );
		if ( ! $menu || is_wp_error( $menu ) ) {
			return 0;
		}
		foreach ( array_values( $items ) as $i => $page_id ) {
			wp_update_nav_menu_item(
				(int) $menu,
				0,
				array(
					'menu-item-object'    => 'page',
					'menu-item-object-id' => $page_id,
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
					'menu-item-position'  => $i + 1,
				)
			);
		}
		$location = array_key_first( get_registered_nav_menus() );
		$assigned = (array) get_theme_mod( 'nav_menu_locations', array() );
		if ( null !== $location && empty( $assigned[ $location ] ) ) {
			$assigned[ $location ] = (int) $menu;
			set_theme_mod( 'nav_menu_locations', $assigned );
		}
		return (int) $menu;
	}

	/**
	 * Detect whether wizard sample data still exists on the site.
	 *
	 * Considers four signals so the cleanup button never silently
	 * disappears while demo content is still around:
	 * 1. The legacy `wcb_sample_data_installed` flag.
	 * 2. The tracked id map in `wcb_sample_data_ids`.
	 * 3. The `_wcb_sample = 1` post meta marker stamped at install time
	 *    (covers wcb_job, wcb_company, wcb_candidate).
	 * 4. Orphan markers — wcb_job slugs prefixed with `wcb-sample-` or
	 *    wcb_company posts whose `_wcb_website` ends with `example.com`.
	 *
	 * @since 1.1.1
	 *
	 * @return bool
	 */
	public static function has_sample_data(): bool {
		if ( (bool) get_option( 'wcb_sample_data_installed', false ) ) {
			return true;
		}
		$ids = (array) get_option( 'wcb_sample_data_ids', array() );
		if ( ! empty( $ids ) ) {
			return true;
		}

		global $wpdb;

		// Meta marker — any CPT explicitly tagged at install time, including
		// posts an admin added manually then marked with `_wcb_sample = 1`.
		$tagged = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(p.ID)
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s AND pm.meta_value = %s
				 WHERE p.post_type IN ( 'wcb_job', 'wcb_company', 'wcb_candidate' )",
				'_wcb_sample',
				'1'
			)
		);
		if ( $tagged > 0 ) {
			return true;
		}

		$jobs = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_name LIKE %s",
				'wcb_job',
				'wcb-sample-%'
			)
		);
		if ( $jobs > 0 ) {
			return true;
		}
		$companies = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				 WHERE p.post_type = %s AND pm.meta_value LIKE %s",
				'_wcb_website',
				'wcb_company',
				'%example.com%'
			)
		);
		return $companies > 0;
	}

	/**
	 * Remove all sample data created by the wizard, plus any wizard-shaped
	 * orphans (slug prefix `wcb-sample-`, company website containing
	 * `example.com`) and any post explicitly tagged with `_wcb_sample = 1`
	 * (covers manually-added demo jobs, companies, and candidates that an
	 * admin or import script marked as sample). Idempotent — re-running
	 * with nothing to remove returns zero counts, never an error.
	 *
	 * @since 1.0.0
	 *
	 * @return array{jobs: int, companies: int, candidates: int, terms: int} Counts of removed items.
	 */
	public function remove_sample_data(): array {
		$ids     = (array) get_option( 'wcb_sample_data_ids', array() );
		$removed = array(
			'jobs'       => 0,
			'companies'  => 0,
			'candidates' => 0,
			'terms'      => 0,
		);
		$deleted = array(
			'wcb_job'       => array(),
			'wcb_company'   => array(),
			'wcb_candidate' => array(),
		);

		// Jobs — tracked ids.
		foreach ( $ids['jobs'] ?? array() as $jid ) {
			$jid = (int) $jid;
			if ( $jid && get_post( $jid ) ) {
				wp_delete_post( $jid, true );
				$deleted['wcb_job'][ $jid ] = true;
				++$removed['jobs'];
			}
		}

		// Companies — tracked ids.
		foreach ( $ids['companies'] ?? array() as $cid ) {
			$cid = (int) $cid;
			if ( $cid && get_post( $cid ) ) {
				wp_delete_post( $cid, true );
				$deleted['wcb_company'][ $cid ] = true;
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

		global $wpdb;

		// Meta marker sweep — every CPT post explicitly tagged `_wcb_sample = 1`
		// (install-time auto-tag plus anything an admin/import script marked
		// manually). Covers wcb_job, wcb_company, wcb_candidate so candidates
		// added during a demo are cleaned up alongside jobs and companies.
		$tagged = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_type
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s AND pm.meta_value = %s
				 WHERE p.post_type IN ( 'wcb_job', 'wcb_company', 'wcb_candidate' )
				 LIMIT 1000",
				'_wcb_sample',
				'1'
			)
		);
		foreach ( $tagged as $row ) {
			$post_id   = (int) $row->ID;
			$post_type = (string) $row->post_type;
			if ( isset( $deleted[ $post_type ][ $post_id ] ) ) {
				continue;
			}
			if ( ! get_post( $post_id ) ) {
				continue;
			}
			wp_delete_post( $post_id, true );
			$deleted[ $post_type ][ $post_id ] = true;
			$bucket                            = 'wcb_job' === $post_type ? 'jobs' : ( 'wcb_company' === $post_type ? 'companies' : 'candidates' );
			++$removed[ $bucket ];
		}

		// Orphan sweep — sites whose `wcb_sample_data_ids` got out of sync (manual
		// db edits, partial restores, an older wizard run that didn't track ids)
		// otherwise leave demo content stranded on the site forever. Match on
		// the wizard-emitted slug prefix and the *.example.com website signal,
		// both of which are unique to the seeder. Ignore anything posted by a
		// real employer.
		$wcb_orphan_jobs = array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name LIKE %s LIMIT 200",
					'wcb_job',
					'wcb-sample-%'
				)
			)
		);
		foreach ( $wcb_orphan_jobs as $wcb_orphan_job_id ) {
			if ( isset( $deleted['wcb_job'][ $wcb_orphan_job_id ] ) ) {
				continue;
			}
			if ( get_post( (int) $wcb_orphan_job_id ) ) {
				wp_delete_post( (int) $wcb_orphan_job_id, true );
				$deleted['wcb_job'][ $wcb_orphan_job_id ] = true;
				++$removed['jobs'];
			}
		}

		$wcb_orphan_companies = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				 WHERE p.post_type = %s AND pm.meta_value LIKE %s
				 LIMIT 100",
				'_wcb_website',
				'wcb_company',
				'%example.com%'
			)
		);
		foreach ( $wcb_orphan_companies as $wcb_orphan_co_id ) {
			$wcb_orphan_co_id = (int) $wcb_orphan_co_id;
			if ( isset( $deleted['wcb_company'][ $wcb_orphan_co_id ] ) ) {
				continue;
			}
			if ( get_post( $wcb_orphan_co_id ) ) {
				wp_delete_post( $wcb_orphan_co_id, true );
				$deleted['wcb_company'][ $wcb_orphan_co_id ] = true;
				++$removed['companies'];
			}
		}

		// Remove orphaned _wcb_company_id from the user who ran the wizard.
		$admin_company = get_user_meta( get_current_user_id(), '_wcb_company_id', true );
		if ( $admin_company && ! get_post( (int) $admin_company ) ) {
			delete_user_meta( get_current_user_id(), '_wcb_company_id' );
		}

		delete_option( 'wcb_sample_data_ids' );
		delete_option( 'wcb_sample_data_installed' );

		/**
		 * Fires after sample/demo data is removed. Pro hooks this to delete the
		 * demo resumes (and candidate users) it seeded.
		 *
		 * @since 1.3.1
		 */
		do_action( 'wcb_sample_data_removed' );

		return $removed;
	}
}
