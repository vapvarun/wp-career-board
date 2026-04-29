<?php
/**
 * Settings REST endpoint — public bootstrap data for app clients.
 *
 * Routes:
 *   GET /wcb/v1/settings/app-config — non-sensitive startup config
 *
 * Mobile / SPA clients call this once on launch to learn the site's
 * pagination defaults, currency, moderation mode, feature flags, and
 * whether Pro is active. Public — no auth required.
 *
 * Skill §3.8 (wp-plugin-development): every plugin must expose an
 * app-config endpoint behind the `<prefix>_rest_app_config` filter.
 *
 * @package WP_Career_Board
 * @since   1.1.1
 */

declare( strict_types=1 );

namespace WCB\Api\Endpoints;

use WCB\Api\RestController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles /wcb/v1/settings/* REST routes.
 *
 * @since 1.1.1
 */
final class SettingsEndpoint extends RestController {

	/**
	 * Register settings routes.
	 *
	 * @since 1.1.1
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/settings/app-config',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_app_config' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Return non-sensitive bootstrap config for app/SPA clients.
	 *
	 * @since 1.1.1
	 * @return \WP_REST_Response
	 */
	public function get_app_config(): \WP_REST_Response {
		$wcb_settings   = (array) get_option( 'wcb_settings', array() );
		$is_pro_active  = (bool) apply_filters( 'wcb_pro_active', false );
		$captcha_driver = (string) get_option( 'wcb_captcha_driver', '' );

		$data = array(
			'site_name'        => (string) get_bloginfo( 'name' ),
			'site_url'         => (string) home_url( '/' ),
			'plugin_version'   => defined( 'WCB_VERSION' ) ? WCB_VERSION : '',
			'pro_version'      => (string) apply_filters( 'wcb_pro_version', '' ),
			'is_pro_active'    => $is_pro_active,
			'is_pro_licensed'  => (bool) apply_filters( 'wcb_pro_licensed', false ),
			'per_page'         => (int) ( $wcb_settings['jobs_per_page'] ?? 10 ),
			'currency'         => (string) ( $wcb_settings['salary_currency'] ?? 'USD' ),
			'moderation_mode'  => isset( $wcb_settings['auto_publish_jobs'] ) && $wcb_settings['auto_publish_jobs'] ? 'auto_publish' : 'pending_review',
			'allow_withdraw'   => (bool) ( $wcb_settings['allow_withdraw'] ?? false ),
			'feature_toggles'  => array(
				'guest_apply'          => true,
				'bookmarks'            => true,
				'job_alerts'           => $is_pro_active,
				'application_pipeline' => $is_pro_active,
				'resume_archive'       => $is_pro_active,
				'credits'              => $is_pro_active,
				'ai_matching'          => $is_pro_active && (bool) apply_filters( 'wcb_pro_ai_enabled', false ),
			),
			'timezone'         => (string) wp_timezone_string(),
			'locale'           => (string) get_locale(),
			'rest_namespace'   => 'wcb/v1',
			'captcha_required' => '' !== $captcha_driver,
		);

		/**
		 * Filter the app-config payload before returning to clients.
		 *
		 * Theme integrators and Pro modules hook here to add feature flags
		 * or override defaults for specific deployments.
		 *
		 * @since 1.1.1
		 *
		 * @param array $data App-config response array.
		 */
		$data = (array) apply_filters( 'wcb_rest_app_config', $data );

		return rest_ensure_response( $data );
	}
}
