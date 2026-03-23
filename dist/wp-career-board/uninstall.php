<?php
/**
 * Uninstall: drop custom tables and options.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wcb_tables = array(
	$wpdb->prefix . 'wcb_notifications_log',
	$wpdb->prefix . 'wcb_job_views',
	$wpdb->prefix . 'wcb_gdpr_log',
);

foreach ( $wcb_tables as $wcb_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall must drop tables directly; caching is irrelevant here.
	$wpdb->query( "DROP TABLE IF EXISTS `{$wcb_table}`" );
}

delete_option( 'wcb_version' );
delete_option( 'wcb_settings' );
delete_option( 'wcb_db_version' );

remove_role( 'wcb_employer' );
remove_role( 'wcb_candidate' );
remove_role( 'wcb_board_moderator' );
