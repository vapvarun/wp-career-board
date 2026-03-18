<?php
/**
 * Plugin singleton — boots all modules, REST routes, blocks, admin, integrations.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Private constructor — use instance() instead.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/**
	 * Get or create the singleton instance.
	 *
	 * @since 1.0.0
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	/**
	 * Boot the plugin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init(): void {
		load_plugin_textdomain( 'wp-career-board', false, dirname( WCB_BASENAME ) . '/languages' );

		if ( class_exists( \WCB\Core\Roles::class ) ) {
			add_action( 'init', array( new \WCB\Core\Roles(), 'register' ), 5 );
		}

		if ( class_exists( \WCB\Core\Abilities::class ) ) {
			add_action( 'init', array( new \WCB\Core\Abilities(), 'register' ), 5 );
		}

		$this->boot_modules();

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'init', array( $this, 'register_patterns' ) );
		add_filter( 'body_class', array( $this, 'add_page_class' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );

		if ( is_admin() ) {
			if ( class_exists( \WCB\Admin\Admin::class ) ) {
				( new \WCB\Admin\Admin() )->boot();
			}

			if ( class_exists( \WCB\Admin\SetupWizard::class ) ) {
				( new \WCB\Admin\SetupWizard() )->boot();
			}
		}

		$this->load_integrations();
		$this->register_cli_commands();
	}

	/**
	 * Boot all feature modules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function boot_modules(): void {
		$module_classes = array(
			\WCB\Modules\Boards\BoardsModule::class,
			\WCB\Modules\Jobs\JobsModule::class,
			\WCB\Modules\Jobs\JobsMeta::class,
			\WCB\Modules\Jobs\JobsExpiry::class,
			\WCB\Modules\Employers\EmployersModule::class,
			\WCB\Modules\Candidates\CandidatesModule::class,
			\WCB\Modules\Applications\ApplicationsModule::class,
			\WCB\Modules\Applications\ApplicationsMeta::class,
			\WCB\Modules\Search\SearchModule::class,
			\WCB\Modules\Notifications\NotificationsModule::class,
			\WCB\Modules\Moderation\ModerationModule::class,
			\WCB\Modules\AntiSpam\AntiSpamModule::class,
			\WCB\Modules\Seo\SeoModule::class,
			\WCB\Modules\Gdpr\GdprModule::class,
			\WCB\Modules\ThemeIntegration\ThemeIntegrationModule::class,
		);

		foreach ( $module_classes as $class ) {
			if ( class_exists( $class ) ) {
				( new $class() )->boot();
			}
		}
	}

	/**
	 * Register REST API endpoints.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_rest_routes(): void {
		$endpoint_classes = array(
			\WCB\Api\Endpoints\JobsEndpoint::class,
			\WCB\Api\Endpoints\ApplicationsEndpoint::class,
			\WCB\Api\Endpoints\CandidatesEndpoint::class,
			\WCB\Api\Endpoints\EmployersEndpoint::class,
			\WCB\Api\Endpoints\SearchEndpoint::class,
			\WCB\Api\Endpoints\CompaniesEndpoint::class,
		);

		foreach ( $endpoint_classes as $class ) {
			if ( class_exists( $class ) ) {
				( new $class() )->register_routes();
			}
		}
	}

	/**
	 * Register Gutenberg blocks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_blocks(): void {
		$blocks = array(
			'job-listings',
			'job-search',
			'job-filters',
			'job-single',
			'job-form',
			'employer-dashboard',
			'candidate-dashboard',
			'company-profile',
			'company-archive',
			'featured-jobs',
		);

		foreach ( $blocks as $block ) {
			$block_dir = WCB_DIR . 'blocks/' . $block;
			if ( is_dir( $block_dir ) ) {
				register_block_type_from_metadata( $block_dir );
			}
		}
	}

	/**
	 * Register Gutenberg block patterns for the inserter.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_patterns(): void {
		register_block_pattern_category(
			'wp-career-board',
			array( 'label' => __( 'WP Career Board', 'wp-career-board' ) )
		);

		$patterns = array(
			array(
				'name'        => 'wp-career-board/job-board',
				'title'       => __( 'Full Job Board', 'wp-career-board' ),
				'description' => __( 'Search bar, filters, and job listings grid — the complete job board page.', 'wp-career-board' ),
				'categories'  => array( 'wp-career-board' ),
				'content'     => '<!-- wp:wp-career-board/job-search /--><!-- wp:wp-career-board/job-filters /--><!-- wp:wp-career-board/job-listings /-->',
			),
			array(
				'name'        => 'wp-career-board/post-a-job',
				'title'       => __( 'Post a Job Form', 'wp-career-board' ),
				'description' => __( 'Multi-step job posting form for employers.', 'wp-career-board' ),
				'categories'  => array( 'wp-career-board' ),
				'content'     => '<!-- wp:wp-career-board/job-form /-->',
			),
			array(
				'name'        => 'wp-career-board/employer-dashboard',
				'title'       => __( 'Employer Dashboard', 'wp-career-board' ),
				'description' => __( 'Tabbed dashboard for employers to manage jobs and applications.', 'wp-career-board' ),
				'categories'  => array( 'wp-career-board' ),
				'content'     => '<!-- wp:wp-career-board/employer-dashboard /-->',
			),
			array(
				'name'        => 'wp-career-board/candidate-dashboard',
				'title'       => __( 'Candidate Dashboard', 'wp-career-board' ),
				'description' => __( 'Tabbed dashboard for candidates to track applications and saved jobs.', 'wp-career-board' ),
				'categories'  => array( 'wp-career-board' ),
				'content'     => '<!-- wp:wp-career-board/candidate-dashboard /-->',
			),
			array(
				'name'        => 'wp-career-board/company-directory',
				'title'       => __( 'Company Directory', 'wp-career-board' ),
				'description' => __( 'Interactive grid of employer company profiles with industry and size filters.', 'wp-career-board' ),
				'categories'  => array( 'wp-career-board' ),
				'content'     => '<!-- wp:wp-career-board/company-archive /-->',
			),
		);

		foreach ( $patterns as $pattern ) {
			register_block_pattern( $pattern['name'], $pattern );
		}
	}

	/**
	 * Add `wcb-page` body class on any page configured as a WCB app page.
	 *
	 * @since 1.0.0
	 * @param string[] $classes Existing body classes.
	 * @return string[]
	 */
	public function add_page_class( array $classes ): array {
		$page_id = (int) get_queried_object_id();
		if ( ! $page_id ) {
			return $classes;
		}

		$settings  = (array) get_option( 'wcb_settings', array() );
		$page_keys = array( 'jobs_archive_page', 'employer_dashboard_page', 'candidate_dashboard_page', 'post_job_page', 'company_archive_page' );
		$page_ids  = array_values( array_filter( array_map( static fn( string $key ): int => (int) ( $settings[ $key ] ?? 0 ), $page_keys ) ) );

		/**
		 * Filter the page IDs that receive the `wcb-page` body class.
		 *
		 * Pro and other add-ons append their own mapped page IDs here.
		 *
		 * @since 1.0.0
		 * @param int[] $page_ids Array of WordPress page IDs.
		 */
		$page_ids = (array) apply_filters( 'wcb_app_page_ids', $page_ids );

		if ( in_array( $page_id, $page_ids, true ) ) {
			$classes[] = 'wcb-page';
		}

		return $classes;
	}

	/**
	 * Enqueue global frontend stylesheet.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend_styles(): void {
		wp_enqueue_style(
			'wcb-frontend',
			WCB_URL . 'assets/css/frontend.css',
			array(),
			WCB_VERSION
		);
	}

	/**
	 * Register WP-CLI command groups when running under WP-CLI.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_cli_commands(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'wcb', \WCB\Cli\Cli::class );
		\WP_CLI::add_command( 'wcb job', \WCB\Cli\JobCommands::class );
		\WP_CLI::add_command( 'wcb application', \WCB\Cli\ApplicationCommands::class );
	}

	/**
	 * Load optional third-party integrations.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function load_integrations(): void {
		if ( function_exists( 'buddypress' ) && class_exists( \WCB\Integrations\Buddypress\BpIntegration::class ) ) {
			( new \WCB\Integrations\Buddypress\BpIntegration() )->boot();
		}

		add_action(
			'after_setup_theme',
			function (): void {
				$theme = wp_get_theme()->get_template();

				if ( 'reign-theme' === $theme && class_exists( \WCB\Integrations\Reign\ReignIntegration::class ) ) {
					( new \WCB\Integrations\Reign\ReignIntegration() )->boot();
				}

				if ( 'buddyx-pro' === $theme && class_exists( \WCB\Integrations\BuddyxPro\BuddyxProIntegration::class ) ) {
					( new \WCB\Integrations\BuddyxPro\BuddyxProIntegration() )->boot();
				}
			}
		);
	}
}
