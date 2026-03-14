<?php
/**
 * Plugin activation and deactivation handler stub.
 *
 * Full implementation is delivered in T2 (database schema + flush rewrite rules).
 * This stub exists so that the activation hook registered in T1 does not fatal
 * when the plugin is first activated before T2 is complete.
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
 * Handles plugin activation and deactivation routines.
 *
 * @since 1.0.0
 */
final class Install {

	/**
	 * Prevent instantiation — all methods are static.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/**
	 * Run on plugin activation.
	 *
	 * Stores the plugin version so upgrade routines can detect schema changes.
	 * Full table creation and flush_rewrite_rules() are added in T2.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate(): void {
		update_option( 'wcb_version', WCB_VERSION, false );
	}

	/**
	 * Run on plugin deactivation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
