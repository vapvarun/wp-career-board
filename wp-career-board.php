<?php
/**
 * Plugin Name: WP Career Board
 * Plugin URI:  https://store.wbcomdesigns.com/wp-career-board/
 * Description: The community-powered job board for WordPress.
 * Version:     1.8.2
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

define( 'WCB_VERSION', '1.8.2' );
define( 'WCB_FILE', __FILE__ );
define( 'WCB_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCB_URL', plugin_dir_url( __FILE__ ) );
define( 'WCB_BASENAME', plugin_basename( __FILE__ ) );
define( 'WCB_EDD_ITEM_ID', 1659888 );
// Licence and update host for WP Career Board and Pro; the SDK falls back to https://wbcomdesigns.com.
define( 'WCB_LICENCE_HOST', 'https://updates.wbcomdesigns.com' );

// Polyfill wp_is_ability_granted() — WP 6.9 core ships the Abilities API
// registry but no top-level "is granted" helper. Loaded early so any block
// render, REST controller, or admin page can call it. function_exists guard
// inside the file means this yields the moment WP core ships the helper.
require_once WCB_DIR . 'core/abilities-api-polyfill.php';

/**
 * One licence-server request (activate, deactivate, check or get_version) for WP Career Board and Pro. Goes
 * through the bundled SDK, so it uses the same host as update checks and falls back to the old host when the
 * new one cannot be reached. Returns the decoded reply, or false when no host answered.
 *
 * @since  1.8.2
 * @param  array<string,mixed> $params EDD Software Licensing parameters (edd_action, license, item_id).
 * @return object|false
 */
function wcb_licence_request( array $params ) {
	if ( ! class_exists( '\\EasyDigitalDownloads\\Updater\\Requests\\API' ) ) {
		return false;
	}
	// Name the fallback here as well: on an early request (plugin activation) the SDK registry has not run yet.
	// method_exists() is a real runtime check: another plugin's older SDK copy (no set_fallback) can own the class.
	// @phpstan-ignore function.alreadyNarrowedType
	if ( method_exists( '\\EasyDigitalDownloads\\Updater\\Requests\\API', 'set_fallback' ) ) {
		\EasyDigitalDownloads\Updater\Requests\API::set_fallback( WCB_LICENCE_HOST, 'https://wbcomdesigns.com' );
	}
	return ( new \EasyDigitalDownloads\Updater\Requests\API( WCB_LICENCE_HOST ) )->make_request( $params );
}

// EDD Software Licensing SDK — automatic updates from updates.wbcomdesigns.com (fallback wbcomdesigns.com).
// Free plugin uses a shared community license key for silent update checks.
add_action(
	'edd_sl_sdk_registry',
	static function ( \EasyDigitalDownloads\Updater\Registry $registry ): void {
		$registry->register(
			array(
				'id'           => 'wp-career-board',
				'url'          => WCB_LICENCE_HOST,
				'fallback_url' => 'https://wbcomdesigns.com',
				'item_id'      => WCB_EDD_ITEM_ID,
				'version'      => WCB_VERSION,
				'file'         => WCB_FILE,
				'license'      => 'wbcomfree5b8c1e7a9d3f2a4c6e0d1b7f9c2a6e00',
				// Free ships a preset community key (auto-activated below); it only
				// enables silent update checks and gates nothing. The SDK shows the
				// "Manage License" plugins-row button and modal, whose JS/CSS load
				// from this bundled libs copy via plugins_url() (no 404). option_name
				// points the SDK at the same wcb_license_key option seeded below, so
				// the modal prefills the key. Pro loads the same shared copy.
				'option_name'  => 'wcb_license_key',
			)
		);
	}
);
// The EDD SL SDK is bundled (built) in /libs — not /vendor — so it survives
// release packaging (which strips /vendor) and is the single shared copy that
// WP Career Board Pro loads too, rather than duplicating the SDK.
// Load it only when its classes are there too: an incomplete upload that lost
// src/ would otherwise fatal every page on the loader's first class call.
// Without it the plugin works; only automatic updates stop.
if ( is_readable( __DIR__ . '/libs/edd-sl-sdk/src/Registry.php' ) ) {
	require_once __DIR__ . '/libs/edd-sl-sdk/edd-sl-sdk.php';
} else {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! function_exists( 'wp_is_ability_granted' ) || ! wp_is_ability_granted( 'wcb/manage-settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
				return;
			}
			echo '<div class="notice wcb-notice notice-error"><p>' .
				esc_html__( 'WP Career Board is missing files from its update library, so automatic updates are turned off. Reinstall WP Career Board to restore them.', 'wp-career-board' ) .
				'</p></div>';
		}
	);
}

/*
 * Auto-activate the preset community license so Free updates work — Free is
 * keyless (no per-user license UI). Fires on plugin activation (immediate) and
 * on admin_init (fallback for activation paths that bypass
 * register_activation_hook: multisite network-activate, WP-CLI). Idempotent via
 * the wcb_preset_activated flag, so running on both hooks is safe.
 *
 * Wbcom licensing model: the license is for AUTOMATIC UPDATES via EDD
 * Software Licensing only. It does NOT gate any plugin functionality, so
 * any reply from the store (valid or a licence "no") ends the attempts and
 * sets `wcb_preset_activated`. Only "no host answered" (or the SDK not
 * loaded yet) leaves it unset, and then a 12h backoff transient stops a
 * failing store from being retried on every admin page.
 */
function wcb_maybe_activate_preset_license(): void {
	$preset_key = 'wbcomfree5b8c1e7a9d3f2a4c6e0d1b7f9c2a6e00';
	$option     = 'wcb_license_key';
	$activated  = 'wcb_preset_activated';

	if ( get_option( $activated ) || get_transient( 'wcb_preset_activation_backoff' ) ) {
		return;
	}

	update_option( $option, $preset_key, false );

	// Best-effort remote activation; the reply's licence status is not enforced anywhere.
	$reply = wcb_licence_request(
		array(
			'edd_action' => 'activate_license',
			'license'    => $preset_key,
			'item_id'    => WCB_EDD_ITEM_ID,
		)
	);
	if ( is_object( $reply ) ) {
		update_option( $activated, 1, false );
	} else {
		set_transient( 'wcb_preset_activation_backoff', 1, 12 * HOUR_IN_SECONDS );
	}
}
add_action( 'admin_init', 'wcb_maybe_activate_preset_license' );
register_activation_hook( WCB_FILE, 'wcb_maybe_activate_preset_license' );

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
 * The CAPTCHA provider in force, or '' when only the honeypot runs (no
 * provider chosen, or its keys are missing).
 *
 * @since  1.2.0
 * @return string
 */
function wcb_get_captcha_driver(): string {
	return \WCB\Modules\AntiSpam\AntiSpamModule::active()['provider'] ?? '';
}

register_activation_hook( WCB_FILE, array( 'WCB\\Core\\Install', 'activate' ) );
register_deactivation_hook( WCB_FILE, array( 'WCB\\Core\\Install', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		\WCB\Core\Plugin::instance();
	}
);

// Runtime DB-version self-heal — covers WP-CLI / managed-host auto-updates
// that bypass register_activation_hook. Runs at init@5 so Pro's
// `wcb_pro_active` filter (registered on plugins_loaded@10) is in place.
add_action( 'init', array( 'WCB\\Core\\Install', 'maybe_migrate' ), 5 );
