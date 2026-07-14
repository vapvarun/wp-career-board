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
		// The response intentionally renames internal sanitizer keys to
		// stable client-facing field names (jobs_per_page → per_page,
		// salary_currency → currency, auto_publish_jobs → moderation_mode).
		// Reads use \WCB\Admin\Settings so internal callers and this endpoint
		// share one source of truth for canonical keys.
		$is_pro_active  = (bool) apply_filters( 'wcb_pro_active', false );
		$captcha_driver = wcb_get_captcha_driver();

		$data = array(
			'site_name'        => (string) get_bloginfo( 'name' ),
			'site_url'         => (string) home_url( '/' ),
			'plugin_version'   => defined( 'WCB_VERSION' ) ? WCB_VERSION : '',
			'pro_version'      => (string) apply_filters( 'wcb_pro_version', '' ),
			'is_pro_active'    => $is_pro_active,
			'is_pro_licensed'  => (bool) apply_filters( 'wcb_pro_licensed', false ),
			'per_page'         => \WCB\Admin\Settings::int( 'jobs_per_page', 10 ),
			'currency'         => \WCB\Admin\Settings::string( 'salary_currency', 'USD' ),
			'moderation_mode'  => \WCB\Admin\Settings::bool( 'auto_publish_jobs', false ) ? 'auto_publish' : 'pending_review',
			'allow_withdraw'   => \WCB\Admin\Settings::bool( 'allow_withdraw', false ),
			'feature_toggles'  => array(
				'guest_apply'          => true,
				'bookmarks'            => true,
				'job_alerts'           => $is_pro_active,
				'application_pipeline' => $is_pro_active,
				'resume_archive'       => $is_pro_active,
				'credits'              => $is_pro_active,
				'ai_matching'          => $is_pro_active && (bool) apply_filters( 'wcb_pro_ai_enabled', false ),
				// Compliance surfaces the mobile app must know about before it
				// renders a control. These state what WORKS today: job reporting
				// ships in Free; member block + in-app account deletion do not
				// exist yet and stay false until their endpoints land, so the app
				// never shows a button that 403s on this version.
				'reporting'            => true,
				'blocking'             => false,
				'account_deletion'     => true,
			),
			// White-label branding. Free serves its own settings (with neutral
			// defaults); Pro overrides from its white-label option via the
			// wcb_rest_app_config filter. Never restate site name/icon here —
			// those come from the core /wp-json/ index.
			'accent_color'     => \WCB\Admin\Settings::string( 'accent_color', '#2563EB' ),
			'logo_url'         => \WCB\Admin\Settings::string( 'logo_url', '' ),
			'login_bg_url'     => \WCB\Admin\Settings::string( 'login_bg_url', '' ),
			'dark_mode_default' => \WCB\Admin\Settings::bool( 'dark_mode_default', false ),
			// Per-site legal surface (Apple 1.2 / 5.1.1). Each site owns its own
			// policies; privacy defaults to WP core, abuse contact to the admin.
			// Unset values are null, never a placeholder URL the app would treat
			// as a live link.
			'legal'            => array(
				'privacy_policy_url'       => get_privacy_policy_url() ?: null,
				'terms_url'                => \WCB\Admin\Settings::string( 'terms_url', '' ) ?: null,
				'eula_url'                 => \WCB\Admin\Settings::string( 'eula_url', '' ) ?: null,
				'community_guidelines_url' => \WCB\Admin\Settings::string( 'guidelines_url', '' ) ?: null,
				'abuse_contact_email'      => \WCB\Admin\Settings::string( 'abuse_contact_email', '' ) ?: (string) get_option( 'admin_email' ),
			),
			// Version floor + contract version so a client can force-upgrade and
			// a strict parser can pin the shape. Additive-only: never rename or
			// retype an existing key above.
			'min_app_version'  => (string) apply_filters( 'wcb_min_app_version', '1.0.0' ),
			'contract_version' => 1,
			// The mobile app runs against the live REST API directly. This is
			// NOT license-gated: Career Board's rule is "license = updates only,
			// never gate functionality." A site owner can still turn the app
			// surface off via the filter.
			'app_enabled'      => (bool) apply_filters( 'wcb_app_enabled', true ),
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
