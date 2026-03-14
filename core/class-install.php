<?php
/**
 * Plugin install, activation, deactivation, and upgrade handling.
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
 * Handles plugin activation, deactivation, and database schema management.
 *
 * @since 1.0.0
 */
final class Install {

	/**
	 * Current database schema version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DB_VERSION = '1.0';

	/**
	 * Prevent instantiation — all methods are static.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/**
	 * Run on plugin activation.
	 *
	 * Checks requirements, creates database tables, registers roles,
	 * and stores version options.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate(): void {
		self::check_requirements();
		self::maybe_upgrade();
		( new Roles() )->register();
		flush_rewrite_rules();
		update_option( 'wcb_db_version', self::DB_VERSION, false );
		update_option( 'wcb_version', WCB_VERSION, false );
		set_transient( 'wcb_activation_redirect', true, 30 );
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

	/**
	 * Check minimum PHP and WordPress version requirements.
	 *
	 * Deactivates the plugin and halts if requirements are not met.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function check_requirements(): void {
		global $wp_version;

		if ( version_compare( PHP_VERSION, '8.1', '<' ) || version_compare( $wp_version, '6.9', '<' ) ) {
			deactivate_plugins( WCB_BASENAME );
			wp_die(
				esc_html__( 'WP Career Board requires PHP 8.1+ and WordPress 6.9+.', 'wp-career-board' ),
				esc_html__( 'Plugin Activation Error', 'wp-career-board' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Create custom database tables using dbDelta.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}wcb_notifications_log (
				id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id      BIGINT UNSIGNED NOT NULL,
				event_type   VARCHAR(80)     NOT NULL,
				channel      VARCHAR(20)     NOT NULL DEFAULT 'email',
				payload      LONGTEXT,
				status       VARCHAR(20)     NOT NULL DEFAULT 'sent',
				sent_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY user_id  (user_id),
				KEY event_type  (event_type)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}wcb_job_views (
				id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				job_id     BIGINT UNSIGNED NOT NULL,
				viewed_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
				ip_hash    VARCHAR(64),
				PRIMARY KEY  (id),
				KEY job_id  (job_id)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}wcb_gdpr_log (
				id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id    BIGINT UNSIGNED NOT NULL,
				action     VARCHAR(20)     NOT NULL,
				metadata   LONGTEXT,
				ip_hash    VARCHAR(64),
				created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY user_id  (user_id)
			) {$charset};"
		);
	}

	/**
	 * Run upgrade routines when the DB version is outdated.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function maybe_upgrade(): void {
		$installed = get_option( 'wcb_db_version', '0' );

		if ( version_compare( (string) $installed, self::DB_VERSION, '<' ) ) {
			self::create_tables();
			update_option( 'wcb_db_version', self::DB_VERSION, false );
		}
	}
}
