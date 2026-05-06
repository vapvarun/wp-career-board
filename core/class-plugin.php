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
		add_filter( 'template_include', array( $this, 'use_wcb_archive_template' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
		add_action( 'wp_head', array( $this, 'print_container_width_css_var' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_shared_assets' ) );
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

		// Register tokens stylesheet so blocks can declare it as a dependency.
		// Block style.css references --wcb-* variables that live here; without
		// this, the variables are undefined when WP loads a block's style.css
		// independently (FSE templates, widgets, lazy block-asset loading).
		wp_register_style(
			'wcb-frontend-tokens',
			WCB_URL . 'assets/css/frontend-tokens.css',
			array(),
			WCB_VERSION
		);

		foreach ( $blocks as $block ) {
			$block_dir = WCB_DIR . 'blocks/' . $block;
			if ( is_dir( $block_dir ) ) {
				register_block_type_from_metadata( $block_dir );
				wp_enqueue_block_style(
					'wp-career-board/' . $block,
					array( 'handle' => 'wcb-frontend-tokens' )
				);
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
		/*
		 * Every frontend block also ships as a shortcode so site owners can
		 * drop it into Elementor / Beaver / Bricks / Divi / Visual Composer /
		 * classic editor without leaving the page-builder surface they
		 * already know. Attributes pass through verbatim with numeric +
		 * boolean string-coercion so block.json type checks still validate.
		 * Example:
		 *   [wcb_job_listings boardId="42" perPage="20" showFilters="true"]
		 */
		$shortcodes = array(
			'wcb_job_listings'        => 'wp-career-board/job-listings',
			'wcb_job_search'          => 'wp-career-board/job-search',
			'wcb_job_search_hero'     => 'wp-career-board/job-search-hero',
			'wcb_job_filters'         => 'wp-career-board/job-filters',
			'wcb_job_form'            => 'wp-career-board/job-form',
			'wcb_job_form_simple'     => 'wp-career-board/job-form-simple',
			'wcb_job_single'          => 'wp-career-board/job-single',
			'wcb_employer_dashboard'  => 'wp-career-board/employer-dashboard',
			'wcb_candidate_dashboard' => 'wp-career-board/candidate-dashboard',
			'wcb_registration'        => 'wp-career-board/employer-registration',
			'wcb_company_archive'     => 'wp-career-board/company-archive',
			'wcb_company_profile'     => 'wp-career-board/company-profile',
			'wcb_job_stats'           => 'wp-career-board/job-stats',
			'wcb_recent_jobs'         => 'wp-career-board/recent-jobs',
			'wcb_featured_jobs'       => 'wp-career-board/featured-jobs',
		);

		// WordPress's shortcode parser lowercases attribute keys (jobId →
		// jobid), but block.json schemas use camelCase. Without remapping,
		// the block sees no value and renders the empty-state branch. This
		// table covers every camelCase attr the bundled blocks declare so
		// site owners can write [wcb_job_single jobId="123"] and get the
		// same render as the Gutenberg block. Addons can extend it via
		// the `wcb_shortcode_attr_aliases` filter.
		$camel_aliases = array(
			'jobid'              => 'jobId',
			'boardid'            => 'boardId',
			'companyid'          => 'companyId',
			'resumeid'           => 'resumeId',
			'authorid'           => 'authorId',
			'savedby'            => 'savedBy',
			'perpage'            => 'perPage',
			'orderby'            => 'orderBy',
			'showfilters'        => 'showFilters',
			'showlocation'       => 'showLocation',
			'showtype'           => 'showType',
			'showcategory'       => 'showCategory',
			'showjobcount'       => 'showJobCount',
			'showskills'         => 'showSkills',
			'showjobs'           => 'showJobs',
			'showcandidates'     => 'showCandidates',
			'showcompanies'      => 'showCompanies',
			'showcompanyfield'   => 'showCompanyField',
			'showcategoryfilter' => 'showCategoryFilter',
			'showjobtypefilter'  => 'showJobTypeFilter',
			'showlocationfilter' => 'showLocationFilter',
			'showviewall'        => 'showViewAll',
			'viewallurl'         => 'viewAllUrl',
			'buttonlabel'        => 'buttonLabel',
			'metafilter'         => 'metaFilter',
			'submitlabel'        => 'submitLabel',
			'successmessage'     => 'successMessage',
			'bgimage'            => 'bgImage',
			'subheadline'        => 'subHeadline',
			'centerlat'          => 'centerLat',
			'centerlng'          => 'centerLng',
		);
		/**
		 * Filter the snake/lowercase → camelCase attribute alias map used
		 * by every Free shortcode wrapper.
		 *
		 * @since 1.2.0
		 *
		 * @param array<string,string> $camel_aliases Lowercase → camelCase map.
		 */
		$camel_aliases = (array) apply_filters( 'wcb_shortcode_attr_aliases', $camel_aliases );

		foreach ( $shortcodes as $tag => $block_name ) {
			add_shortcode(
				$tag,
				static function ( $atts ) use ( $block_name, $camel_aliases ): string {
					/*
					 * Forward shortcode attributes to the block as JSON.
					 * Lets page builders / classic editors / any shortcode
					 * host scope a block via attributes. Example:
					 *   [wcb_job_listings boardId="42" perPage="20" showFilters="true"]
					 * metaFilter="key:value" is an integrator extension point:
					 * the key must be registered via the wcb_jobs_allowed_meta_filters
					 * filter, otherwise it is dropped. Empty by default to block
					 * arbitrary-meta probes.
					 */
					$attrs_json = '';
					if ( ! empty( $atts ) ) {
						$cast = array();
						foreach ( (array) $atts as $key => $value ) {
							if ( ! is_string( $key ) ) {
								continue;
							}
							// Map known lowercase aliases back to the camelCase
							// keys block.json declares.
							$key = $camel_aliases[ $key ] ?? $key;
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
	 * Add WCB body classes on every page the plugin owns the layout of.
	 *
	 * Adds `wcb-page` (signal: this page is plugin-controlled) and
	 * `wcb-page-fullwidth` (signal: theme content-area constraints should
	 * be ignored in favour of the canonical `.wcb-archive-shell`). Both
	 * decisions flow from the single shared {@see resolve_page_context()}
	 * helper so this method, the template router, and any future consumer
	 * stay in sync.
	 *
	 * @since 1.0.0
	 * @param string[] $classes Existing body classes.
	 * @return string[]
	 */
	public function add_page_class( array $classes ): array {
		$context = $this->resolve_page_context();
		if ( ! $context['is_wcb_page'] ) {
			return $classes;
		}

		$classes[] = 'wcb-page';
		if ( $context['fullwidth'] ) {
			$classes[] = 'wcb-page-fullwidth';
		}
		return $classes;
	}

	/**
	 * Resolve the WCB context for the current request.
	 *
	 * Single source of truth shared by `add_page_class()` and
	 * `use_wcb_archive_template()`. Returns:
	 *
	 *   - `is_wcb_page` (bool) — whether the page is plugin-controlled
	 *   - `fullwidth`   (bool) — whether the canonical container should apply
	 *   - `mode`        (string) — 'archive' | 'page' | 'embed'
	 *   - `post_type`   (string) — for archive mode, the queried post type
	 *
	 * The `wcb_apply_page_class` filter still fires here so existing opt-out
	 * customisations keep working.
	 *
	 * @since 1.1.0
	 * @return array{is_wcb_page: bool, fullwidth: bool, mode: string, post_type: string}
	 */
	private function resolve_page_context(): array {
		$default = array(
			'is_wcb_page' => false,
			'fullwidth'   => false,
			'mode'        => 'embed',
			'post_type'   => '',
		);

		// Post-type archives — always WCB-controlled and full-width.
		if ( is_post_type_archive( array( 'wcb_company', 'wcb_job', 'wcb_resume' ) ) ) {
			$pt = '';
			foreach ( array( 'wcb_company', 'wcb_job', 'wcb_resume' ) as $candidate ) {
				if ( is_post_type_archive( $candidate ) ) {
					$pt = $candidate;
					break;
				}
			}
			return array(
				'is_wcb_page' => true,
				'fullwidth'   => true,
				'mode'        => 'archive',
				'post_type'   => $pt,
			);
		}

		// Singular pages — three detection paths in order of cost.
		if ( ! is_singular( 'page' ) ) {
			return $default;
		}
		global $post;
		if ( ! ( $post instanceof \WP_Post ) ) {
			return $default;
		}

		$is_wcb_page = false;

		// Path 1 — explicit Settings mapping (cheapest).
		$settings    = (array) get_option( 'wcb_settings', array() );
		$mapped_keys = array(
			'jobs_archive_page',
			'employer_dashboard_page',
			'candidate_dashboard_page',
			'company_archive_page',
			'employer_registration_page',
			'post_job_page',
			'find_candidates_page',
			'resume_archive_page',
		);
		$mapped_ids  = array();
		foreach ( $mapped_keys as $key ) {
			$id = (int) ( $settings[ $key ] ?? 0 );
			if ( $id > 0 ) {
				$mapped_ids[] = $id;
			}
		}
		$mapped_ids = (array) apply_filters( 'wcb_app_page_ids', $mapped_ids );
		if ( in_array( $post->ID, $mapped_ids, true ) ) {
			$is_wcb_page = true;
		}

		// Path 2 — block detection.
		if ( ! $is_wcb_page ) {
			foreach ( $this->wcb_block_names() as $block_name ) {
				if ( has_block( $block_name, $post ) ) {
					$is_wcb_page = true;
					break;
				}
			}
		}

		// Path 3 — shortcode detection.
		if ( ! $is_wcb_page ) {
			$content            = (string) $post->post_content;
			$shortcode_prefixes = (array) apply_filters( 'wcb_search_active_shortcodes', array( 'wcb_', 'wcbp_' ) );
			foreach ( $shortcode_prefixes as $prefix ) {
				if ( false !== strpos( $content, '[' . $prefix ) ) {
					$is_wcb_page = true;
					break;
				}
			}
		}

		/**
		 * Opt-out filter — returning false skips both the wcb-page body class
		 * AND the plugin page template, returning theme-default rendering.
		 *
		 * @since 1.1.0
		 *
		 * @param bool $is_wcb_page Whether the page is plugin-controlled.
		 * @param int  $page_id     Current queried page ID.
		 */
		$is_wcb_page = (bool) apply_filters( 'wcb_apply_page_class', $is_wcb_page, $post->ID );

		return array(
			'is_wcb_page' => $is_wcb_page,
			'fullwidth'   => $is_wcb_page,
			'mode'        => $is_wcb_page ? 'page' : 'embed',
			'post_type'   => '',
		);
	}

	/**
	 * Canonical list of WCB block names — Free + Pro.
	 *
	 * Single list consumed by `resolve_page_context()` and any other
	 * detection callsite. Filterable via `wcb_fullwidth_block_names`.
	 *
	 * @since 1.1.0
	 * @return string[]
	 */
	private function wcb_block_names(): array {
		/**
		 * Filter the list of WCB block names that trigger the plugin's
		 * full-width page template + body class.
		 *
		 * Pro adds resume-archive, recruiter-search, etc. through this
		 * filter so Pro block pages get the same canonical centering as
		 * the Free pages.
		 *
		 * @since 1.1.0
		 *
		 * @param string[] $blocks Block names (namespace/slug).
		 */
		return (array) apply_filters(
			'wcb_fullwidth_block_names',
			array(
				'wp-career-board/employer-dashboard',
				'wp-career-board/candidate-dashboard',
				'wp-career-board/job-form',
				'wp-career-board/job-form-simple',
				'wp-career-board/employer-registration',
				'wp-career-board/job-listings',
				'wp-career-board/company-archive',
				'wp-career-board/company-profile',
				'wp-career-board/job-search',
				'wp-career-board/job-search-hero',
				'wp-career-board/featured-jobs',
				'wp-career-board/recent-jobs',
				'wcb/resume-archive',
				'wcb/recruiter-search',
				'wcb/employer-dashboard',
				'wcb/candidate-dashboard',
				'wcb/job-listings',
				'wcb/company-archive',
				'wcb/company-profile',
			)
		);
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
	 * Print the canonical container width as a CSS custom property in <head>.
	 *
	 * The frontend stylesheet reads `--wcb-container-max-width` everywhere it
	 * needs the canonical container width (archive shells, full-width
	 * dashboard / form pages, etc.). Surfacing the value as a CSS variable
	 * means a site owner / Pro / theme integration can override the entire
	 * layout system in one place — either through:
	 *
	 *   - the `wcb_container_max_width` PHP filter (this method),
	 *   - the `container_max_width` key under `wcb_settings` (admin UI), or
	 *   - a `<style>` block in the active theme that overrides the variable.
	 *
	 * Default: 1280 px. Min: 720, max: 1920 (clamped to keep layouts sane).
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function print_container_width_css_var(): void {
		// Resolution order:
		//   1. `wcb_container_max_width` filter (developer override)
		//   2. wcb_settings.container_max_width (admin UI)
		//   3. Theme `theme.json` wideSize / contentSize (if a px / rem
		//      value is declared by the active theme)
		//   4. Default: 1280 px
		//
		// The theme value is consulted before the default so block themes
		// that already publish a content-width contract (Twenty Twenty-Four,
		// Twenty Twenty-Three, etc.) see their value honoured. Plugin
		// settings + filter sit ABOVE the theme value because customers may
		// have explicitly tuned the WCB layout.
		$settings        = (array) get_option( 'wcb_settings', array() );
		$setting_value   = isset( $settings['container_max_width'] )
			? (int) $settings['container_max_width']
			: 0;
		$theme_value     = $this->resolve_theme_content_width();
		$default_pixels  = 1280;
		$resolved_pixels = $setting_value > 0
			? $setting_value
			: ( $theme_value > 0 ? $theme_value : $default_pixels );

		/**
		 * Filter the canonical WCB container max-width (in pixels).
		 *
		 * Developers can wrap this filter to set a per-site, per-theme, or
		 * per-page width — the value flows down into every WCB block via
		 * `--wcb-container-max-width` so the entire layout system shifts in
		 * one filter call.
		 *
		 * @since 1.1.0
		 *
		 * @param int $width Canonical container width in pixels.
		 */
		$width = (int) apply_filters( 'wcb_container_max_width', $resolved_pixels );
		$width = max( 720, min( 1920, $width ) );

		printf(
			'<style id="wcb-container-width">:root{--wcb-container-max-width:%dpx;}</style>',
			(int) $width
		);
	}

	/**
	 * Read the active theme's content-width contract from theme.json.
	 *
	 * Returns the wideSize value (preferred) or falls back to contentSize.
	 * Px values are returned as integers; rem values are converted at 16 px
	 * per rem. Returns 0 when the theme doesn't publish a layout contract.
	 *
	 * @since 1.1.0
	 * @return int Content width in pixels, or 0 if not declared.
	 */
	private function resolve_theme_content_width(): int {
		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return 0;
		}
		$layout = wp_get_global_settings( array( 'layout' ) );
		if ( ! is_array( $layout ) ) {
			return 0;
		}
		$candidate = '';
		if ( ! empty( $layout['wideSize'] ) && is_string( $layout['wideSize'] ) ) {
			$candidate = $layout['wideSize'];
		} elseif ( ! empty( $layout['contentSize'] ) && is_string( $layout['contentSize'] ) ) {
			$candidate = $layout['contentSize'];
		}
		if ( '' === $candidate ) {
			return 0;
		}
		if ( preg_match( '/^(\d+(?:\.\d+)?)px$/i', $candidate, $m ) ) {
			return (int) round( (float) $m[1] );
		}
		if ( preg_match( '/^(\d+(?:\.\d+)?)rem$/i', $candidate, $m ) ) {
			return (int) round( (float) $m[1] * 16.0 );
		}
		return 0;
	}

	/**
	 * Override the theme's archive template for our post types.
	 *
	 * Most themes ship `archive.php` that assumes the page has a sidebar
	 * widget area, which collapses our Companies / Jobs grid to a ~600 px
	 * column on otherwise wide viewports (Astra, Storefront, OceanWP, T23,
	 * etc.). Routing those archives through our plugin-shipped templates
	 * lets us render the block at full content width while still wrapping
	 * the page in the theme's `get_header()` / `get_footer()` so brand /
	 * navigation / footer remain consistent.
	 *
	 * Filter priority 99 so theme overrides via lower priorities still win.
	 *
	 * @since 1.1.0
	 *
	 * @param string $template Theme-resolved template path.
	 * @return string Plugin-shipped template path or original.
	 */
	public function use_wcb_archive_template( string $template ): string {
		$context = $this->resolve_page_context();
		if ( ! $context['is_wcb_page'] ) {
			return $template;
		}

		if ( 'archive' === $context['mode'] && '' !== $context['post_type'] ) {
			$candidate = WCB_DIR . 'templates/archive-' . $context['post_type'] . '.php';
			if ( file_exists( $candidate ) ) {
				return $candidate;
			}
		}

		if ( 'page' === $context['mode'] ) {
			$candidate = WCB_DIR . 'templates/page-wcb-fullwidth.php';
			if ( file_exists( $candidate ) ) {
				return $candidate;
			}
		}

		return $template;
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
		wp_style_add_data( 'wcb-frontend-tokens', 'rtl', 'replace' );

		wp_enqueue_style(
			'wcb-frontend-components',
			WCB_URL . 'assets/css/frontend-components.css',
			array( 'wcb-frontend-tokens' ),
			WCB_VERSION
		);
		wp_style_add_data( 'wcb-frontend-components', 'rtl', 'replace' );

		$this->enqueue_editor_assets();

		$this->register_confirm_modal_assets();

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
	 * Enqueue the rich-text editor stack — Editor.js core, the supported tool
	 * bundles, our editor CSS, and the WCB bootstrap that initialises them on
	 * any `.wcb-editor` element.
	 *
	 * Vendor bundles are pinned to the same versions used in Learnomy
	 * (assets/js/vendor/editorjs/) so the rich-text experience is identical
	 * across the Wbcom plugin family. Only the tool subset relevant to a job
	 * description is shipped — header, list, quote, marker, inline-code,
	 * delimiter — to keep the page weight under ~290KB.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function enqueue_editor_assets(): void {
		$vendor = WCB_URL . 'assets/js/vendor/editorjs/';

		wp_enqueue_script( 'editorjs', $vendor . 'editor.umd.js', array(), '2.30.8', true );

		$tools = array(
			'editorjs-header'      => 'header.umd.js',
			'editorjs-list'        => 'list.umd.js',
			'editorjs-quote'       => 'quote.umd.js',
			'editorjs-marker'      => 'marker.umd.js',
			'editorjs-inline-code' => 'inline-code.umd.js',
			'editorjs-delimiter'   => 'delimiter.umd.js',
		);
		foreach ( $tools as $handle => $file ) {
			wp_enqueue_script( $handle, $vendor . $file, array( 'editorjs' ), WCB_VERSION, true );
		}

		wp_enqueue_style(
			'wcb-editor',
			WCB_URL . 'assets/css/wcb-editor.css',
			array( 'wcb-frontend-tokens' ),
			WCB_VERSION
		);
		wp_style_add_data( 'wcb-editor', 'rtl', 'replace' );

		wp_enqueue_script(
			'wcb-editor',
			WCB_URL . 'assets/js/wcb-editor.js',
			array_merge( array( 'editorjs' ), array_keys( $tools ) ),
			WCB_VERSION,
			true
		);
	}

	/**
	 * Register the shared confirm-modal style and script.
	 *
	 * Idempotent. The style depends on wcb-frontend-tokens when those are
	 * registered (frontend); on admin pages where tokens are absent, the
	 * dependency is dropped and the CSS falls back to its built-in defaults.
	 * The Pro field-builder enqueues this on admin_enqueue_scripts, which is
	 * why the registration must run on both hooks.
	 *
	 * @since 1.1.1
	 * @return void
	 */
	public function register_confirm_modal_assets(): void {
		if ( wp_script_is( 'wcb-confirm-modal', 'registered' ) ) {
			return;
		}

		$style_deps = wp_style_is( 'wcb-frontend-tokens', 'registered' )
			? array( 'wcb-frontend-tokens' )
			: array();

		wp_register_style(
			'wcb-confirm-modal',
			WCB_URL . 'assets/css/wcb-confirm-modal.css',
			$style_deps,
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
	}

	/**
	 * Register shared assets that admin-side modules may depend on.
	 *
	 * Hooked early so consumers (e.g. the Pro field-builder) can declare
	 * wcb-confirm-modal as a dependency without WP printing a "doing it
	 * wrong" notice.
	 *
	 * @since 1.1.1
	 * @return void
	 */
	public function register_admin_shared_assets(): void {
		$this->register_confirm_modal_assets();
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

				// Same compat shim covers both BuddyX (free) and BuddyX Pro — content
				// width and layout tokens are identical between the two.
				if ( in_array( $theme, array( 'buddyx', 'buddyx-pro' ), true ) && class_exists( \WCB\Integrations\BuddyxPro\BuddyxProIntegration::class ) ) {
					( new \WCB\Integrations\BuddyxPro\BuddyxProIntegration() )->boot();
				}
			}
		);
	}
}
