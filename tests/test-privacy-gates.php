<?php
/**
 * Privacy gate tests (1.8.2).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-privacy-gates.php
 *
 * Pins: a guest's job page carries no resumes (author => 0 matches every
 * member's); deleting an account writes one row to the privacy request log;
 * a guest request is labelled as a guest, not a deleted member; history
 * retention cuts on the clock the rows are written with, so a site west of
 * UTC keeps a row that is inside the window.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Modules\Gdpr\GdprModule;

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_pg_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

global $wpdb;
WP_CLI::log( 'Privacy gates' );

$wcb_user = (int) wp_insert_user(
	array(
		'user_login' => 'wcb_pg_' . wp_generate_password( 6, false ),
		'user_pass'  => wp_generate_password( 20 ),
		'user_email' => 'wcb-pg-' . wp_generate_password( 6, false ) . '@example.test',
		'role'       => 'wcb_candidate',
	)
);
$wcb_job  = (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'publish',
		'post_title'  => 'PG test job',
	)
);

// A guest's job page has no resumes, so the "attach your resume" check runs.
if ( post_type_exists( 'wcb_resume' ) ) {
	$wcb_resume = (int) wp_insert_post(
		array(
			'post_type'   => 'wcb_resume',
			'post_status' => 'publish',
			'post_title'  => 'PG someone else\'s resume',
			'post_author' => $wcb_user,
		)
	);
	wp_set_current_user( 0 );
	render_block(
		array(
			'blockName'    => 'wp-career-board/job-single',
			'attrs'        => array( 'jobId' => $wcb_job ),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		)
	);
	$wcb_state = wp_interactivity_state( 'wcb-job-single' );
	wcb_pg_assert( array() === ( $wcb_state['userResumes'] ?? null ), 'a guest job page lists no resumes' );
	wcb_pg_assert( 0 === ( $wcb_state['selectedResumeId'] ?? null ), 'a guest job page selects no resume' );
	wp_delete_post( $wcb_resume, true );
} else {
	WP_CLI::log( '  SKIP: resumes need Pro' );
}

// Deleting an account is logged once.
$wcb_log   = $wpdb->prefix . 'wcb_gdpr_log';
$wcb_count = static fn ( int $uid, string $action ): int => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wcb_log} WHERE user_id = %d AND action = %s", $uid, $action ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $wcb_user );
wcb_pg_assert( 1 === $wcb_count( $wcb_user, 'delete' ), 'deleting an account writes one privacy log row' );

// The Privacy tab names a guest request as a guest.
$wcb_ip = hash( 'sha256', 'qa-pg' );
$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wcb_log,
	array(
		'user_id'    => 0,
		'action'     => 'erase',
		'ip_hash'    => $wcb_ip,
		'created_at' => current_time( 'mysql' ),
	)
);
wp_set_current_user( 1 );
$_GET['tab'] = 'privacy';
ob_start();
( new \WCB\Admin\AdminSettings() )->render_privacy_tab();
$wcb_html = (string) ob_get_clean();
unset( $_GET['tab'] );
wcb_pg_assert( str_contains( $wcb_html, 'Guest (no account)' ) && ! str_contains( $wcb_html, 'Deleted member (#0)' ), 'a guest request is labelled Guest, not Deleted member (#0)' );
wcb_pg_assert( str_contains( $wcb_html, 'Account deleted, personal data erased' ), 'an account deletion row has its own label' );
$wpdb->delete( $wcb_log, array( 'ip_hash' => $wcb_ip ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->delete( $wcb_log, array( 'user_id' => $wcb_user ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Retention: a row 4 hours inside the window survives on a UTC-8 site.
$wcb_tz = static fn (): string => 'America/Los_Angeles';
add_filter( 'pre_option_timezone_string', $wcb_tz );
$wcb_days = \WCB\Admin\Settings::int( 'log_retention_days', 180 );
if ( $wcb_days > 0 ) {
	$wcb_inside = ( new DateTimeImmutable( current_time( 'mysql' ), wp_timezone() ) )->modify( "-{$wcb_days} days +4 hours" )->format( 'Y-m-d H:i:s' );
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prefix . 'wcb_notifications_log',
		array(
			'user_id'    => 0,
			'event_type' => 'qa_pg_retention',
			'status'     => 'sent',
			'sent_at'    => $wcb_inside,
		)
	);
	$wcb_row = (int) $wpdb->insert_id;
	GdprModule::prune_logs();
	wcb_pg_assert( '1' === (string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wcb_notifications_log WHERE id = %d", $wcb_row ) ), 'a row inside the retention window is kept on a UTC-8 site' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete( $wpdb->prefix . 'wcb_notifications_log', array( 'id' => $wcb_row ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
remove_filter( 'pre_option_timezone_string', $wcb_tz );

// Cleanup.
wp_delete_post( $wcb_job, true );

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All privacy gate tests passed.' );
}
