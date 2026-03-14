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

		if ( is_admin() ) {
			if ( class_exists( \WCB\Admin\Admin::class ) ) {
				( new \WCB\Admin\Admin() )->boot();
			}
		}

		$this->load_integrations();
	}

	/**
	 * Boot all feature modules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function boot_modules(): void {
		$module_classes = array(
			\WCB\Modules\Jobs\JobsModule::class,
			\WCB\Modules\Employers\EmployersModule::class,
			\WCB\Modules\Candidates\CandidatesModule::class,
			\WCB\Modules\Applications\ApplicationsModule::class,
			\WCB\Modules\Search\SearchModule::class,
			\WCB\Modules\Notifications\NotificationsEmail::class,
			\WCB\Modules\Moderation\ModerationModule::class,
			\WCB\Modules\Seo\SeoModule::class,
			\WCB\Modules\Gdpr\GdprModule::class,
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
