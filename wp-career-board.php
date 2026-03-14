<?php
/**
 * Plugin Name: WP Career Board
 * Plugin URI:  https://wpcareerboard.com
 * Description: The community-powered job board for WordPress.
 * Version:     0.1.0
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

define( 'WCB_VERSION', '0.1.0' );
define( 'WCB_FILE', __FILE__ );
define( 'WCB_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCB_URL', plugin_dir_url( __FILE__ ) );
define( 'WCB_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader: maps WCB\ namespace to /core, /modules, /api, /integrations, /admin.
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
