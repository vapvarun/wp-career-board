<?php
/**
 * Plugin Name: WP Career Board
 * Plugin URI:  https://store.wbcomdesigns.com/wp-career-board/
 * Description: The community-powered job board for WordPress.
 * Version:     1.1.0
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

define( 'WCB_VERSION', '1.1.0' );
define( 'WCB_FILE', __FILE__ );
define( 'WCB_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCB_URL', plugin_dir_url( __FILE__ ) );
define( 'WCB_BASENAME', plugin_basename( __FILE__ ) );
define( 'WCB_EDD_ITEM_ID', 1659888 );

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
			)
		);
	}
);
if ( file_exists( __DIR__ . '/vendor/edd-sl-sdk/edd-sl-sdk.php' ) ) {
	require_once __DIR__ . '/vendor/edd-sl-sdk/edd-sl-sdk.php';
}

// Auto-activate the preset license key on first load so updates work.
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

		$response = wp_remote_post(
			'https://wbcomdesigns.com',
			array(
				'timeout' => 15,
				'body'    => array(
					'edd_action' => 'activate_license',
					'license'    => $preset_key,
					'item_id'    => WCB_EDD_ITEM_ID,
					'url'        => home_url(),
				),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( 'valid' === ( $body['license'] ?? '' ) ) {
				update_option( $activated, 1, false );
			}
		}
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

register_activation_hook( WCB_FILE, array( 'WCB\\Core\\Install', 'activate' ) );
register_deactivation_hook( WCB_FILE, array( 'WCB\\Core\\Install', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WCB\\Core\\Plugin', 'instance' ) );
