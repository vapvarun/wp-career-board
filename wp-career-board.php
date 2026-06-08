<?php
/**
 * Plugin Name: WP Career Board
 * Plugin URI:  https://store.wbcomdesigns.com/wp-career-board/
 * Description: The community-powered job board for WordPress.
 * Version:     1.2.0
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * Author:      Wbcom Designs
 * Author URI:  https://wbcomdesigns.com
 * License:     GPL-2.0-or-later
 * Text Domain: wp-career-board
 * Domain Path: /languages
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'WCB_VERSION', '1.2.0' );
define( 'WCB_FILE', __FILE__ );
define( 'WCB_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCB_URL', plugin_dir_url( __FILE__ ) );
define( 'WCB_BASENAME', plugin_basename( __FILE__ ) );
define( 'WCB_EDD_ITEM_ID', 1659888 );

// Polyfill wp_is_ability_granted() — WP 6.9 core ships the Abilities API
// registry but no top-level "is granted" helper. Loaded early so any block
// render, REST controller, or admin page can call it. function_exists guard
// inside the file means this yields the moment WP core ships the helper.
require_once WCB_DIR . 'core/abilities-api-polyfill.php';

// EDD Software Licensing SDK — automatic updates from wbcomdesigns.com.
// Free plugin uses a shared community license key for silent update checks.
add_action(
	'edd_sl_sdk_registry',
	static function ( \EasyDigitalDownloads\Updater\Registry $registry ): void {
		$registry->register(
			array(
				'id'      => 'wp-career-board',
				'url'     => 'https://wbcomdesigns.com',
				'item_id' => WCB_EDD_ITEM_ID,
				'version' => WCB_VERSION,
				'file'    => WCB_FILE,
				'license' => 'wbcomfree5b8c1e7a9d3f2a4c6e0d1b7f9c2a6e00',
				// Keyless: Free updates silently via the preset community key, so
				// the SDK must NOT add the "Manage License" plugins-row button or
				// hook its modal — that modal enqueued build/js/edd-sl-sdk.js +
				// css, which 404'd (Basecamp 9919578285). Keyless skips the modal
				// entirely; auto_updater() still runs. Pro stays non-keyless (it
				// has a real license tab; its modal assets load from this libs copy).
				'keyless' => true,
			)
		);
	}
);
// The EDD SL SDK is bundled (built) in /libs — not /vendor — so it survives
// release packaging (which strips /vendor) and is the single shared copy that
// WP Career Board Pro loads too, rather than duplicating the SDK.
if ( file_exists( __DIR__ . '/libs/edd-sl-sdk/edd-sl-sdk.php' ) ) {
	require_once __DIR__ . '/libs/edd-sl-sdk/edd-sl-sdk.php';
}

/*
 * Auto-activate the preset license key on first load so updates work.
 *
 * Wbcom licensing model: the license is for AUTOMATIC UPDATES via EDD
 * Software Licensing only. It does NOT gate any plugin functionality —
 * if activation fails (network error, EDD store rejecting the key, 403
 * Forbidden response), the plugin still works the same. So we set the
 * `wcb_preset_activated` flag unconditionally after one attempt; the
 * remote call is best-effort and never retries.
 *
 * Earlier behaviour retried the remote on every admin_init when the
 * response was anything other than a valid license, surfacing the
 * remote 403 as a recurring PHP error in admin.
 */
add_action(
	'admin_init',
	function () {
		$preset_key = 'wbcomfree5b8c1e7a9d3f2a4c6e0d1b7f9c2a6e00';
		$option     = 'wcb_license_key';
		$activated  = 'wcb_preset_activated';

		if ( get_option( $activated ) ) {
			return;
		}

		update_option( $option, $preset_key, false );
		update_option( $activated, 1, false );

		// Best-effort remote activation; result is not enforced anywhere.
		wp_remote_post(
			'https://wbcomdesigns.com',
			array(
				'timeout'  => 15,
				'blocking' => false,
				'body'     => array(
					'edd_action' => 'activate_license',
					'license'    => $preset_key,
					'item_id'    => WCB_EDD_ITEM_ID,
					'url'        => home_url(),
				),
			)
		);
	}
);

// Autoloader: maps WCB\ namespace to /core, /modules, /api, /integrations, /admin.
// NAMING CONSTRAINT: Class names must use PascalCase for acronyms.
// e.g. RestController, CptLoader — NOT RESTController, CPTLoader.
// ALL-CAPS prefixes produce broken filenames: RESTController → class-r-e-s-t-controller.php.
spl_autoload_register(
	function ( string $fqcn ): void {
		if ( 0 !== strpos( $fqcn, 'WCB\\' ) ) {
			return;
		}
		$relative   = str_replace( array( 'WCB\\', '\\' ), array( '', '/' ), $fqcn );
		$parts      = explode( '/', $relative );
		$class_name = array_pop( $parts );
		$filename   = 'class-' . strtolower( (string) preg_replace( '/([A-Z])/', '-$1', lcfirst( $class_name ) ) ) . '.php';
		$file       = WCB_DIR . implode( '/', array_map( 'strtolower', $parts ) ) . '/' . $filename;
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Read the email-template settings sub-array.
 *
 * 1.2.0 (F-5) consolidated `wcb_email_settings` into `wcb_settings.emails` so
 * everything sanitises through the Settings API. The legacy option is read as
 * a fallback for the upgrade window — once Install::maybe_upgrade has run on
 * a site, the legacy row is gone and this helper returns the new sub-array
 * directly. Calling code should never read the raw option.
 *
 * @since  1.2.0
 * @return array<string,mixed>
 */
function wcb_get_email_settings(): array {
	$emails = \WCB\Admin\Settings::get( 'emails' );
	if ( is_array( $emails ) ) {
		return $emails;
	}
	return (array) get_option( 'wcb_email_settings', array() );
}

/**
 * Read the captcha driver slug.
 *
 * Same F-5 consolidation as wcb_get_email_settings().
 *
 * @since  1.2.0
 * @return string
 */
function wcb_get_captcha_driver(): string {
	$captcha = \WCB\Admin\Settings::get( 'captcha' );
	if ( is_array( $captcha ) && isset( $captcha['driver'] ) ) {
		return (string) $captcha['driver'];
	}
	return (string) get_option( 'wcb_captcha_driver', '' );
}

register_activation_hook( WCB_FILE, array( 'WCB\\Core\\Install', 'activate' ) );
register_deactivation_hook( WCB_FILE, array( 'WCB\\Core\\Install', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WCB\\Core\\Plugin', 'instance' ) );

// Runtime DB-version self-heal — covers WP-CLI / managed-host auto-updates
// that bypass register_activation_hook. Runs at init@5 so Pro's
// `wcb_pro_active` filter (registered on plugins_loaded@10) is in place.
add_action( 'init', array( 'WCB\\Core\\Install', 'maybe_migrate' ), 5 );
