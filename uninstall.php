<?php
/**
 * Uninstall: drop custom tables and options.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wcb_tables = array(
	$wpdb->prefix . 'wcb_notifications_log',
	$wpdb->prefix . 'wcb_job_views',
	$wpdb->prefix . 'wcb_gdpr_log',
);

foreach ( $wcb_tables as $wcb_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema change at uninstall; %i identifier placeholder requires WP 6.2+ (plugin requires 6.9+).
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wcb_table ) );
}

delete_option( 'wcb_version' );
delete_option( 'wcb_settings' );
delete_option( 'wcb_db_version' );
delete_option( 'wcb_email_settings' );
delete_option( 'wcb_default_board_id' );
delete_option( 'wcb_jobs_cache_v' );
delete_option( 'wcb_setup_complete' );
delete_option( 'wcb_sample_data_installed' );
delete_option( 'wcb_companies_cache_v' );
delete_option( 'wcb_posts_fulltext_supported' );
delete_option( 'wcb_default_board_lock' );
delete_option( 'wcb_flush_rewrite_rules' );
delete_option( 'wcb_sample_data_ids' );

// Version-keyed / TTL caches stored as transients.
delete_transient( 'wcb_app_status_counts' );

// Drop the indexes WCB adds to CORE tables (wp_postmeta composite lookup index
// + wp_posts FULLTEXT) so nothing orphans on a shared table after uninstall.
// MySQL 8 lacks DROP INDEX IF EXISTS, so guard on information_schema.
foreach (
	array(
		array( $wpdb->postmeta, 'wcb_meta_key_value' ),
		array( $wpdb->posts, 'wcb_post_title_ft' ),
	) as $wcb_idx
) {
	$wcb_idx_exists = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(1) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s',
			DB_NAME,
			$wcb_idx[0],
			$wcb_idx[1]
		)
	);
	if ( $wcb_idx_exists > 0 ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "ALTER TABLE {$wcb_idx[0]} DROP INDEX {$wcb_idx[1]}" );
	}
}

// Remove wcb_* capabilities from administrator role.
$admin_role = get_role( 'administrator' );
if ( $admin_role ) {
	foreach ( $admin_role->capabilities as $cap => $granted ) {
		if ( 0 === strpos( $cap, 'wcb_' ) ) {
			$admin_role->remove_cap( $cap );
		}
	}
}

remove_role( 'wcb_employer' );
remove_role( 'wcb_candidate' );
remove_role( 'wcb_board_moderator' );
