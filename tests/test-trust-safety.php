<?php
/**
 * Trust and safety tests (1.8.0, W14, owner decision D10).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-trust-safety.php
 *
 * Pins: a ban hides the member's live and pending jobs and company page
 * without emailing anyone; lifting it restores exactly what the ban hid;
 * a post someone changed while hidden is left alone; enough reports hide
 * a job pending review and dismissing them puts it back; the owner is
 * alerted on the first report and on the hide, not on every report; member
 * reports reach the owner once and show in the Reported view.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Modules\Moderation\HiddenContent;
use WCB\Modules\Moderation\ModerationModule;

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

/**
 * Create a user.
 *
 * @param string $role Role.
 * @return int
 */
function wcb_ts_user( string $role ): int {
	$login = 'ts_' . $role . '_' . wp_generate_password( 6, false );
	return (int) wp_insert_user( array( 'user_login' => $login, 'user_email' => $login . '@example.test', 'user_pass' => wp_generate_password(), 'role' => $role ) );
}

/**
 * Create a post without firing publish side effects we don't test.
 *
 * @param string $type   Post type.
 * @param int    $author Author.
 * @param string $status Status.
 * @return int
 */
function wcb_ts_post( string $type, int $author, string $status ): int {
	return (int) wp_insert_post(
		array(
			'post_type'   => $type,
			'post_status' => $status,
			'post_title'  => 'TS ' . $type . ' ' . $status,
			'post_author' => $author,
			'meta_input'  => array( '_wcb_deadline' => gmdate( 'Y-m-d', strtotime( '+30 days' ) ) ),
		)
	);
}

/**
 * Dispatch a REST request as a user.
 *
 * @param string $method Method.
 * @param string $route  Route.
 * @param array  $params Params.
 * @param int    $user   User ID.
 * @return WP_REST_Response
 */
function wcb_ts_rest( string $method, string $route, array $params, int $user ): WP_REST_Response {
	wp_set_current_user( $user );
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $k => $v ) {
		$request->set_param( $k, $v );
	}
	return rest_do_request( $request );
}

/**
 * Rows logged since an ID, by event type.
 *
 * @param int    $since Last log ID before the step.
 * @param string $event Event type ('' = any).
 * @return int
 */
function wcb_ts_mail( int $since, string $event = '' ): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wcb_notifications_log WHERE id > %d AND ( %s = '' OR event_type = %s )", $since, $event, $event ) );
}

global $wpdb;
require_once ABSPATH . 'wp-admin/includes/user.php';
$wcb_pro      = class_exists( '\WCB\Pro\Modules\NotificationsBell\NotificationsBellModule' );
$wcb_settings = get_option( 'wcb_settings', array() );
$wcb_log_id   = static fn (): int => (int) $wpdb->get_var( "SELECT COALESCE( MAX(id), 0 ) FROM {$wpdb->prefix}wcb_notifications_log" );
$wcb_bell     = static fn ( string $event ): int => $wcb_pro ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wcb_notifications WHERE event_type = %s", $event ) ) : 0;

$wcb_employer = wcb_ts_user( 'wcb_employer' );
$wcb_company  = wcb_ts_post( 'wcb_company', $wcb_employer, 'publish' );
update_user_meta( $wcb_employer, '_wcb_company_id', $wcb_company );
$wcb_live     = wcb_ts_post( 'wcb_job', $wcb_employer, 'publish' );
$wcb_live2    = wcb_ts_post( 'wcb_job', $wcb_employer, 'publish' );
$wcb_pending  = wcb_ts_post( 'wcb_job', $wcb_employer, 'pending' );
$wcb_closed   = wcb_ts_post( 'wcb_job', $wcb_employer, 'wcb_closed' );

WP_CLI::log( '--- ban hides ---' );
$wcb_v    = (int) get_option( 'wcb_jobs_cache_v', 0 );
$wcb_mark = $wcb_log_id();
update_user_meta( $wcb_employer, '_wcb_employer_banned', '1' );
wcb_assert( 'draft' === get_post_status( $wcb_live ) && 'draft' === get_post_status( $wcb_live2 ) && 'draft' === get_post_status( $wcb_pending ), 'live and pending jobs leave the site' );
wcb_assert( 'draft' === get_post_status( $wcb_company ), 'company page leaves the site' );
wcb_assert( 'wcb_closed' === get_post_status( $wcb_closed ), 'a closed job is left as it was' );
wcb_assert( 'ban' === HiddenContent::reason( $wcb_live ) && 'pending' === get_post_meta( $wcb_pending, HiddenContent::META_STATUS, true ), 'each hidden post remembers why and what it was' );
wcb_assert( $wcb_v < (int) get_option( 'wcb_jobs_cache_v', 0 ), 'job list cache version moves' );
wcb_assert( 0 === wcb_ts_mail( $wcb_mark ), 'no email is sent' );
$r = wcb_ts_rest( 'GET', '/wcb/v1/jobs/' . $wcb_live, array(), 0 );
wcb_assert( 404 === $r->get_status(), 'a hidden job answers 404 to visitors (' . $r->get_status() . ')' );
$r = wcb_ts_rest( 'GET', '/wcb/v1/jobs', array( 'per_page' => 100 ), 0 );
wcb_assert( ! in_array( $wcb_live, array_map( 'intval', wp_list_pluck( (array) ( $r->get_data()['jobs'] ?? array() ), 'id' ) ), true ), 'hidden job is not in the public list' );

// An admin trashes one hidden job while the ban is on: it is theirs now.
wp_trash_post( $wcb_live2 );

WP_CLI::log( '--- unban restores ---' );
$wcb_mark = $wcb_log_id();
delete_user_meta( $wcb_employer, '_wcb_employer_banned' );
wcb_assert( 'publish' === get_post_status( $wcb_live ) && 'pending' === get_post_status( $wcb_pending ) && 'publish' === get_post_status( $wcb_company ), 'unban restores exactly what was live and pending' );
wcb_assert( 'trash' === get_post_status( $wcb_live2 ), 'a post changed while hidden is not resurrected' );
wcb_assert( '' === HiddenContent::reason( $wcb_live ) && '' === get_post_meta( $wcb_live, HiddenContent::META_STATUS, true ), 'restore clears the markers' );
wcb_assert( 0 === wcb_ts_mail( $wcb_mark ), 'no email on restore' );

WP_CLI::log( '--- reports hide a job ---' );
update_option( 'wcb_settings', array_merge( (array) $wcb_settings, array( 'report_auto_hide_threshold' => 3 ) ) );
\WCB\Admin\Settings::flush_cache();
$wcb_reporters = array( wcb_ts_user( 'wcb_candidate' ), wcb_ts_user( 'wcb_candidate' ), wcb_ts_user( 'wcb_candidate' ) );
$wcb_mark      = $wcb_log_id();
$wcb_bell0     = $wcb_bell( 'report_received' );
foreach ( $wcb_reporters as $i => $wcb_reporter ) {
	$r = wcb_ts_rest( 'POST', '/wcb/v1/jobs/' . $wcb_live . '/report', array( 'reason' => 'scam' ), $wcb_reporter );
	if ( 1 === $i ) {
		wcb_assert( 'publish' === get_post_status( $wcb_live ), 'two reports: job still live' );
	}
}
wcb_assert( 200 === $r->get_status() && 'pending' === get_post_status( $wcb_live ) && 'reports' === HiddenContent::reason( $wcb_live ), 'third report hides the job pending review' );
wcb_assert( 2 === wcb_ts_mail( $wcb_mark, 'report-received' ), 'owner emailed on the first report and on the hide, not on each report' );
wcb_assert( ! $wcb_pro || ( $wcb_bell( 'report_received' ) - $wcb_bell0 ) === 2 * count( get_users( array( 'role' => 'administrator', 'fields' => 'ID', 'number' => 20 ) ) ), 'each administrator gets the same two bell alerts' );
wp_set_current_user( 1 );
ModerationModule::resolve_job_flags( $wcb_live, 'dismiss' );
wcb_assert( 'publish' === get_post_status( $wcb_live ) && '' === HiddenContent::reason( $wcb_live ), 'dismissing the reports puts the job back' );

WP_CLI::log( '--- member reports ---' );
$wcb_mark = $wcb_log_id();
foreach ( array_slice( $wcb_reporters, 0, 2 ) as $wcb_reporter ) {
	wcb_ts_rest( 'POST', '/wcb/v1/users/' . $wcb_employer . '/report', array( 'reason' => 'scam' ), $wcb_reporter );
}
wcb_assert( 2 === ModerationModule::open_member_reports( $wcb_employer ), 'member report count' );
wcb_assert( 1 === wcb_ts_mail( $wcb_mark, 'report-received' ), 'owner emailed once for a reported member' );
$wcb_reported = get_users( array( 'include' => array( $wcb_employer ), 'fields' => 'ID', 'meta_query' => array( array( 'key' => '_wcb_member_flag_status', 'value' => 'open' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
wcb_assert( array( (string) $wcb_employer ) === array_map( 'strval', $wcb_reported ), 'reported employer matches the Reported view query' );
ModerationModule::resolve_member_flags( $wcb_employer );
wcb_assert( 0 === ModerationModule::open_member_reports( $wcb_employer ), 'Dismiss reports clears the badge' );

// Teardown.
update_option( 'wcb_settings', $wcb_settings );
\WCB\Admin\Settings::flush_cache();
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wcb_notifications_log WHERE id > %d AND event_type = %s", 0, 'report-received' ) );
if ( $wcb_pro ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wcb_notifications WHERE event_type = %s", 'report_received' ) );
}
foreach ( array( $wcb_live, $wcb_live2, $wcb_pending, $wcb_closed, $wcb_company ) as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}
foreach ( array_merge( $wcb_reporters, array( $wcb_employer ) ) as $wcb_id ) {
	wp_delete_user( $wcb_id );
}

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All trust and safety tests passed.' );
}
