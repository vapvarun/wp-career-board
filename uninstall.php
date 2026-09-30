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

// Keep everything unless the owner chose otherwise (Settings > Advanced).
// Deleting a plugin to reinstall it, or to swap Free for a fresh copy, must
// never take jobs, applications or credit balances with it.
$wcb_settings = (array) get_option( 'wcb_settings', array() );
if ( empty( $wcb_settings['remove_data_on_uninstall'] ) ) {
	return;
}

// Everything the plugin created: its posts (with meta, terms, and the private
// candidate files), its taxonomies, its user meta.
$wcb_post_types = array( 'wcb_job', 'wcb_company', 'wcb_application', 'wcb_board', 'wcb_resume' );
$wcb_in         = implode( ',', array_fill( 0, count( $wcb_post_types ), '%s' ) );

// Private candidate files: attachments with their folder on disk.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
foreach ( (array) $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wcb_private_file'" ) as $wcb_file_id ) {
	wp_delete_attachment( (int) $wcb_file_id, true );
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- set-based deletes at uninstall; placeholders built above in $wcb_in.
$wpdb->query( $wpdb->prepare( "DELETE pm FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type IN ({$wcb_in})", $wcb_post_types ) );
$wpdb->query( $wpdb->prepare( "DELETE tr FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE p.post_type IN ({$wcb_in})", $wcb_post_types ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE post_type IN ({$wcb_in})", $wcb_post_types ) );

// Every wcb_ taxonomy, Pro's included (resume skills).
$wpdb->query( "DELETE t FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy LIKE 'wcb\\_%'" );
$wpdb->query( "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy LIKE 'wcb\\_%'" );

$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '\\_wcb\\_%' OR meta_key LIKE 'wcb\\_%'" );

// Every wcb_ option and transient (term-children caches, license and preset
// state included), not a hand-kept list that falls behind.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wcb\\_%' OR option_name LIKE '\\_transient\\_wcb\\_%' OR option_name LIKE '\\_transient\\_timeout\\_wcb\\_%'" );
// phpcs:enable

// The private candidate files folder, with its guard files.
$wcb_uploads = wp_upload_dir();
$wcb_private = trailingslashit( (string) $wcb_uploads['basedir'] ) . 'wcb-private';
if ( is_dir( $wcb_private ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	global $wp_filesystem;
	if ( $wp_filesystem ) {
		$wp_filesystem->delete( $wcb_private, true );
	}
}

// Pro's uninstall (it may run after this) needs to know removal was asked for.
update_option( 'wcb_remove_data_pending', 1, false );

$wcb_tables = array(
	$wpdb->prefix . 'wcb_notifications_log',
	$wpdb->prefix . 'wcb_job_views',
	$wpdb->prefix . 'wcb_gdpr_log',
);

foreach ( $wcb_tables as $wcb_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema change at uninstall; %i identifier placeholder requires WP 6.2+ (plugin requires 6.9+).
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wcb_table ) );
}


// Version-keyed / TTL caches stored as transients.

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
// Our caps come off every role (the moderator cap was only removed from
// administrators before).
foreach ( wp_roles()->roles as $wcb_role_slug => $wcb_role_data ) {
	$wcb_role = get_role( $wcb_role_slug );
	if ( ! $wcb_role ) {
		continue;
	}
	foreach ( array_keys( $wcb_role->capabilities ) as $wcb_cap ) {
		if ( 0 === strpos( (string) $wcb_cap, 'wcb_' ) ) {
			$wcb_role->remove_cap( (string) $wcb_cap );
		}
	}
}

// Members keep their accounts: anyone on one of our roles becomes a
// subscriber before the role goes (they were left with no role at all).
$wcb_roles = array( 'wcb_employer', 'wcb_candidate', 'wcb_board_moderator' );
foreach ( get_users(
	array(
		'role__in' => $wcb_roles,
		'fields'   => 'ID',
	)
) as $wcb_user_id ) {
	$wcb_user = new WP_User( (int) $wcb_user_id );
	foreach ( $wcb_roles as $wcb_r ) {
		$wcb_user->remove_role( $wcb_r );
	}
	if ( empty( $wcb_user->roles ) ) {
		$wcb_user->add_role( 'subscriber' );
	}
}
foreach ( $wcb_roles as $wcb_r ) {
	remove_role( $wcb_r );
}
