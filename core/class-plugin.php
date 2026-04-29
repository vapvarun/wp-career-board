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
			$abilities = new \WCB\Core\Abilities();
			add_action( 'wp_abilities_api_categories_init', array( $abilities, 'register_category' ) );
			add_action( 'wp_abilities_api_init', array( $abilities, 'register_abilities' ) );
		}

		$this->boot_modules();

		// Pro-coordination filter API — single source of truth for the Free→Pro contract.
		// Free fires; Pro hooks. See core/class-pro-coordination.php for the documented surface.
		if ( class_exists( \WCB\Core\ProCoordination::class ) ) {
			( new \WCB\Core\ProCoordination() )->boot();
		}

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'init', array( $this, 'register_patterns' ) );

		( new \WCB\Core\Widgets\WidgetShortcode() )->boot();
		add_filter( 'body_class', array( $this, 'add_page_class' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
		add_filter( 'wp_theme_json_data_default', array( $this, 'register_theme_json_defaults' ) );

		// Theme accent auto-bridge — for non-bundle themes only.
		// Reign / BuddyX / BuddyX Pro skip via the bridge's internal allow-list
		// because their dedicated compat CSS files in integrations/ already
		// own the bidirectional token bridge.
		if ( class_exists( \WCB\Core\ThemeAccentBridge::class ) ) {
			( new \WCB\Core\ThemeAccentBridge() )->boot();
		}

		if ( class_exists( \WCB\Admin\SetupWizard::class ) ) {
			( new \WCB\Admin\SetupWizard() )->boot();
		}

		if ( is_admin() ) {
			if ( class_exists( \WCB\Admin\Admin::class ) ) {
				( new \WCB\Admin\Admin() )->boot();
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
			\WCB\Modules\Jobs\DeadlineReminders::class,
			\WCB\Modules\Jobs\FeaturedExpiry::class,
			\WCB\Modules\Employers\EmployersModule::class,
			\WCB\Modules\Candidates\CandidatesModule::class,
			\WCB\Modules\Applications\ApplicationsModule::class,
			\WCB\Modules\Applications\ApplicationsMeta::class,
			\WCB\Modules\Search\SearchModule::class,
			\WCB\Modules\Notifications\NotificationsModule::class,
			\WCB\Modules\Moderation\ModerationModule::class,
			\WCB\Modules\AntiSpam\AntiSpamModule::class,
			\WCB\Modules\Seo\SeoModule::class,
			\WCB\Modules\Seo\RssFeedEnrichment::class,
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
			\WCB\Api\Endpoints\ImportEndpoint::class,
			\WCB\Api\Endpoints\AdminEndpoint::class,
			\WCB\Api\Endpoints\SettingsEndpoint::class,
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
			'job-form-simple',
			'employer-dashboard',
			'employer-registration',
			'candidate-dashboard',
			'company-profile',
			'company-archive',
			'featured-jobs',
			'recent-jobs',
			'job-stats',
			'job-search-hero',
		);

		foreach ( $blocks as $block ) {
			$block_dir = WCB_DIR . 'blocks/' . $block;
			if ( is_dir( $block_dir ) ) {
				register_block_type_from_metadata( $block_dir );
			}
		}
	}

	/**
	 * Register shortcodes that render Gutenberg blocks.
	 *
	 * Provides fallback for sites with the classic editor or page builders
	 * that cannot insert blocks directly.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_shortcodes(): void {
		$shortcodes = array(
			'wcb_job_listings'        => 'wp-career-board/job-listings',
			'wcb_job_search'          => 'wp-career-board/job-search',
			'wcb_job_form'            => 'wp-career-board/job-form',
			'wcb_job_form_simple'     => 'wp-career-board/job-form-simple',
			'wcb_employer_dashboard'  => 'wp-career-board/employer-dashboard',
			'wcb_candidate_dashboard' => 'wp-career-board/candidate-dashboard',
			'wcb_registration'        => 'wp-career-board/employer-registration',
			'wcb_company_archive'     => 'wp-career-board/company-archive',
			'wcb_job_stats'           => 'wp-career-board/job-stats',
			'wcb_recent_jobs'         => 'wp-career-board/recent-jobs',
		);

		foreach ( $shortcodes as $tag => $block_name ) {
			add_shortcode(
				$tag,
				static function ( $atts ) use ( $block_name ): string {
					// Forward shortcode attributes to the block as JSON.
					// Lets page builders / classic editors / any shortcode
					// host scope a block via attributes:
					//   [wcb_job_listings boardId="42" metaFilter="_wcb_partner_id:5"]
					$attrs_json = '';
					if ( ! empty( $atts ) ) {
						$cast = array();
						foreach ( (array) $atts as $key => $value ) {
							if ( ! is_string( $key ) ) {
								continue;
							}
							// Auto-cast numeric and boolean strings so block.json type checks pass.
							if ( is_numeric( $value ) && (string) (int) $value === (string) $value ) {
								$cast[ $key ] = (int) $value;
							} elseif ( 'true' === $value || 'false' === $value ) {
								$cast[ $key ] = ( 'true' === $value );
							} else {
								$cast[ $key ] = (string) $value;
							}
						}
						if ( ! empty( $cast ) ) {
							$attrs_json = ' ' . wp_json_encode( $cast );
						}
					}
					return do_blocks( '<!-- wp:' . $block_name . $attrs_json . ' /-->' );
				}
			);
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
		$page_keys = array( 'jobs_archive_page', 'employer_dashboard_page', 'candidate_dashboard_page', 'company_archive_page', 'employer_registration_page' );
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

		$is_wcb_page = in_array( $page_id, $page_ids, true );

		// Detection-by-content fallback (1.1.0): if the visited page contains a
		// WCB block or shortcode in its post_content, treat it as a WCB app
		// page. Catches user-mapped pages where the customer pasted our block
		// outside the wizard-mapped settings keys, fixing the duplicate-<h1>
		// issue on Neve / OceanWP.
		if ( ! $is_wcb_page ) {
			$post = get_post( $page_id );
			if ( $post instanceof \WP_Post ) {
				$content = (string) $post->post_content;
				if (
					false !== strpos( $content, '<!-- wp:wp-career-board/' )
					|| false !== strpos( $content, '<!-- wp:wcb/' )
				) {
					$is_wcb_page = true;
				} else {
					// Pro adds 'wcbp_' to this filter so its shortcodes also flag the page.
					$shortcode_prefixes = (array) apply_filters( 'wcb_search_active_shortcodes', array( 'wcb_' ) );
					foreach ( $shortcode_prefixes as $prefix ) {
						if ( false !== strpos( $content, '[' . $prefix ) ) {
							$is_wcb_page = true;
							break;
						}
					}
				}
			}
		}

		/**
		 * Final opt-out filter — return false to skip the wcb-page body class
		 * on a specific page even when content detection picked it up.
		 * Useful when a customer wants to keep the theme entry-title visible
		 * alongside our block heading.
		 *
		 * @since 1.1.0
		 *
		 * @param bool $is_wcb_page Whether the body class will be added.
		 * @param int  $page_id     Current queried page ID.
		 */
		$is_wcb_page = (bool) apply_filters( 'wcb_apply_page_class', $is_wcb_page, $page_id );

		if ( $is_wcb_page ) {
			$classes[] = 'wcb-page';
		}

		return $classes;
	}

	/**
	 * Inject WCB color/typography/spacing tokens into the WordPress defaults layer.
	 *
	 * Block themes can override any wcb-* slug in their own theme.json; classic
	 * themes can override --wcb-* custom properties in :root. The hardcoded
	 * fallbacks in frontend.css bridge layer mean the plugin always renders
	 * correctly even when theme.json merging hasn't run (e.g. classic themes).
	 *
	 * @since 1.0.0
	 * @param \WP_Theme_JSON_Data $theme_json Defaults-layer JSON data object.
	 * @return \WP_Theme_JSON_Data
	 */
	public function register_theme_json_defaults( \WP_Theme_JSON_Data $theme_json ): \WP_Theme_JSON_Data {
		$data = wp_json_file_decode( WCB_DIR . 'theme.json', array( 'associative' => true ) );
		if ( is_array( $data ) ) {
			$theme_json->update_with( $data );
		}
		return $theme_json;
	}

	/**
	 * Enqueue global frontend stylesheet — only on pages that contain a WCB block.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend_styles(): void {
		if ( ! $this->current_page_has_wcb_block() ) {
			return;
		}
		wp_enqueue_style(
			'wcb-frontend',
			WCB_URL . 'assets/css/frontend.css',
			array(),
			WCB_VERSION
		);
		wp_style_add_data( 'wcb-frontend', 'rtl', 'replace' );

		wp_enqueue_style(
			'wcb-frontend-tokens',
			WCB_URL . 'assets/css/frontend-tokens.css',
			array(),
			WCB_VERSION
		);

		wp_enqueue_style(
			'wcb-frontend-components',
			WCB_URL . 'assets/css/frontend-components.css',
			array( 'wcb-frontend-tokens' ),
			WCB_VERSION
		);

		wp_register_style(
			'wcb-confirm-modal',
			WCB_URL . 'assets/css/wcb-confirm-modal.css',
			array( 'wcb-frontend-tokens' ),
			WCB_VERSION
		);

		wp_register_script(
			'wcb-confirm-modal',
			WCB_URL . 'assets/js/wcb-confirm-modal.js',
			array(),
			WCB_VERSION,
			true
		);
		wp_localize_script(
			'wcb-confirm-modal',
			'wcbConfirmI18n',
			array(
				'confirm' => __( 'Confirm', 'wp-career-board' ),
				'cancel'  => __( 'Cancel', 'wp-career-board' ),
			)
		);

		wp_enqueue_script(
			'lucide',
			WCB_URL . 'assets/js/vendor/lucide.min.js',
			array(),
			'0.460.0',
			true
		);

		wp_enqueue_script(
			'wcb-frontend-icons',
			WCB_URL . 'assets/js/admin/icons.js',
			array( 'lucide' ),
			WCB_VERSION,
			true
		);
	}

	/**
	 * Return true when the current request needs WCB frontend assets.
	 *
	 * Matches: pages containing a WCB block, WCB CPT archives, and WCB CPT singles.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function current_page_has_wcb_block(): bool {
		// WCB post-type archives and singles always need styles.
		$wcb_types = array( 'wcb_job', 'wcb_application', 'wcb_company', 'wcb_resume' );
		if ( is_post_type_archive( $wcb_types ) || is_singular( $wcb_types ) ) {
			return true;
		}

		// WCB taxonomy archives.
		$wcb_taxes = array( 'wcb_category', 'wcb_job_type', 'wcb_tag', 'wcb_location', 'wcb_experience' );
		if ( is_tax( $wcb_taxes ) ) {
			return true;
		}

		// Pages containing a WCB block.
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		return has_block( 'wp-career-board/', $post )
			|| str_contains( $post->post_content, '<!-- wp:wp-career-board/' )
			|| str_contains( $post->post_content, '<!-- wp:wcb/' );
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
		\WP_CLI::add_command( 'wcb migrate', \WCB\Cli\MigrateCommands::class );
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
