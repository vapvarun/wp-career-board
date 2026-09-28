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
// Members with standing: accounts more than a week old (see the throwaway-account cases below).
foreach ( $wcb_reporters as $wcb_id ) {
	wp_update_user( array( 'ID' => $wcb_id, 'user_registered' => gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) );
}
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

WP_CLI::log( '--- Dismiss, then report again ---' );
$wcb_mark = $wcb_log_id();
$r        = wcb_ts_rest( 'POST', '/wcb/v1/users/' . $wcb_employer . '/report', array( 'reason' => 'scam' ), $wcb_reporters[0] );
wcb_assert( 200 === $r->get_status() && 1 === ModerationModule::open_member_reports( $wcb_employer ), 'the same member can report again after Dismiss' );
wcb_assert( 1 === wcb_ts_mail( $wcb_mark, 'report-received' ), 'the owner is told about a report made after a Dismiss' );
ModerationModule::resolve_member_flags( $wcb_employer );

WP_CLI::log( '--- reports from throwaway accounts do not hide a job ---' );
$wcb_gj  = wcb_ts_post( 'wcb_job', $wcb_employer, 'publish' );
$wcb_new = array( wcb_ts_user( 'wcb_candidate' ), wcb_ts_user( 'wcb_candidate' ), wcb_ts_user( 'wcb_candidate' ) );
foreach ( $wcb_new as $wcb_reporter ) {
	wcb_ts_rest( 'POST', '/wcb/v1/jobs/' . $wcb_gj . '/report', array( 'reason' => 'scam' ), $wcb_reporter );
}
wcb_assert( 'publish' === get_post_status( $wcb_gj ) && '' === HiddenContent::reason( $wcb_gj ), 'three brand-new accounts reporting do not hide a job' );
wcb_assert( 3 === (int) get_post_meta( $wcb_gj, '_wcb_flag_count', true ), 'their reports are still recorded for the owner' );

// Each way of having standing: a week-old account, an application, a published job.
$wcb_gj2   = wcb_ts_post( 'wcb_job', $wcb_employer, 'publish' );
$wcb_aged  = wcb_ts_user( 'wcb_candidate' );
wp_update_user( array( 'ID' => $wcb_aged, 'user_registered' => gmdate( 'Y-m-d H:i:s', time() - 10 * DAY_IN_SECONDS ) ) );
$wcb_applied = wcb_ts_user( 'wcb_candidate' );
$wcb_app_row = wcb_ts_post( 'wcb_application', $wcb_applied, 'publish' );
update_post_meta( $wcb_app_row, '_wcb_candidate_id', $wcb_applied );
$wcb_fresh   = wcb_ts_user( 'wcb_candidate' );
$wcb_poster  = wcb_ts_user( 'wcb_employer' );
$wcb_poster_job = wcb_ts_post( 'wcb_job', $wcb_poster, 'publish' );
foreach ( array( $wcb_aged, $wcb_applied, $wcb_fresh ) as $wcb_reporter ) {
	wcb_ts_rest( 'POST', '/wcb/v1/jobs/' . $wcb_gj2 . '/report', array( 'reason' => 'scam' ), $wcb_reporter );
}
wcb_assert( 'publish' === get_post_status( $wcb_gj2 ), 'a week-old account and an applicant count, a fresh account does not: two of three, still live' );
wcb_ts_rest( 'POST', '/wcb/v1/jobs/' . $wcb_gj2 . '/report', array( 'reason' => 'scam' ), $wcb_poster );
wcb_assert( 'pending' === get_post_status( $wcb_gj2 ) && 'reports' === HiddenContent::reason( $wcb_gj2 ), 'an employer with a published job counts: the third with standing hides it' );

$wcb_gj3   = wcb_ts_post( 'wcb_job', $wcb_employer, 'publish' );
$wcb_open  = array( wcb_ts_user( 'wcb_candidate' ), wcb_ts_user( 'wcb_candidate' ), wcb_ts_user( 'wcb_candidate' ) );
add_filter( 'wcb_reporter_has_standing', '__return_true' );
foreach ( $wcb_open as $wcb_reporter ) {
	wcb_ts_rest( 'POST', '/wcb/v1/jobs/' . $wcb_gj3 . '/report', array( 'reason' => 'scam' ), $wcb_reporter );
}
remove_filter( 'wcb_reporter_has_standing', '__return_true' );
wcb_assert( 'pending' === get_post_status( $wcb_gj3 ), 'wcb_reporter_has_standing lets a site count everyone' );
foreach ( array( $wcb_gj, $wcb_gj2, $wcb_gj3, $wcb_app_row, $wcb_poster_job ) as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}
foreach ( array_merge( $wcb_new, $wcb_open, array( $wcb_aged, $wcb_applied, $wcb_fresh, $wcb_poster ) ) as $wcb_id ) {
	wp_delete_user( $wcb_id );
}

WP_CLI::log( '--- a job hidden by reports meets a ban ---' );
$wcb_j = wcb_ts_post( 'wcb_job', $wcb_employer, 'publish' );
HiddenContent::hide( array( $wcb_j ), 'reports', 'pending' );
update_user_meta( $wcb_employer, '_wcb_employer_banned', '1' );
wcb_assert( 'reports' === HiddenContent::reason( $wcb_j ), 'a ban leaves a report-hidden job under its own marker' );
delete_user_meta( $wcb_employer, '_wcb_employer_banned' );
wcb_assert( 'pending' === get_post_status( $wcb_j ) && 'reports' === HiddenContent::reason( $wcb_j ), 'unban leaves it for its reports' );
ModerationModule::resolve_job_flags( $wcb_j, 'dismiss' );
wcb_assert( 'publish' === get_post_status( $wcb_j ), 'Dismiss still brings it back' );
HiddenContent::hide( array( $wcb_j ), 'reports', 'pending' );
update_user_meta( $wcb_employer, '_wcb_employer_banned', '1' );
ModerationModule::resolve_job_flags( $wcb_j, 'dismiss' );
wcb_assert( 'draft' === get_post_status( $wcb_j ) && 'ban' === HiddenContent::reason( $wcb_j ), 'dismissing reports on a banned employer\'s job does not put it live' );
delete_user_meta( $wcb_employer, '_wcb_employer_banned' );
wcb_assert( 'publish' === get_post_status( $wcb_j ), 'unban then restores it' );

WP_CLI::log( '--- deleting a banned employer ---' );
$wcb_gone = wcb_ts_user( 'wcb_employer' );
$wcb_gj   = wcb_ts_post( 'wcb_job', $wcb_gone, 'publish' );
$wcb_gc   = wcb_ts_post( 'wcb_company', $wcb_gone, 'publish' );
update_user_meta( $wcb_gone, '_wcb_employer_banned', '1' );
wp_delete_user( $wcb_gone );
wcb_assert( 'publish' !== get_post_status( $wcb_gj ) && 'publish' !== get_post_status( $wcb_gc ), 'deleting a banned employer does not put their listings back live' );
foreach ( array( $wcb_gj, $wcb_gc ) as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}

WP_CLI::log( '--- a suspended candidate and a banned employer are off the API ---' );
$wcb_cand   = wcb_ts_user( 'wcb_candidate' );
$wcb_resume = wcb_ts_post( 'wcb_resume', $wcb_cand, 'publish' );
update_post_meta( $wcb_resume, '_wcb_resume_public', '1' );
$wcb_other  = wcb_ts_user( 'wcb_employer' );
wp_set_current_user( $wcb_other );
wcb_assert( \WCB\Modules\Candidates\CandidatesModule::resume_is_readable( $wcb_resume ), 'control: a listed resume is readable by an employer' );
wcb_assert( 200 === wcb_ts_rest( 'GET', '/wcb/v1/candidates/' . $wcb_cand, array(), $wcb_other )->get_status(), 'control: the candidate profile is readable' );
update_user_meta( $wcb_cand, '_wcb_employer_banned', '1' );
wp_set_current_user( $wcb_other );
wcb_assert( ! \WCB\Modules\Candidates\CandidatesModule::resume_is_readable( $wcb_resume ), 'a suspended candidate\'s resume is not readable by an employer' );
wcb_assert( 404 === wcb_ts_rest( 'GET', '/wcb/v1/candidates/' . $wcb_cand, array(), $wcb_other )->get_status(), 'a suspended candidate\'s profile is a 404' );
wcb_assert( 200 === wcb_ts_rest( 'GET', '/wcb/v1/candidates/' . $wcb_cand, array(), $wcb_cand )->get_status(), 'the suspended candidate still reads their own profile' );
wp_set_current_user( $wcb_cand );
wcb_assert( \WCB\Modules\Candidates\CandidatesModule::resume_is_readable( $wcb_resume ), 'and their own resume' );
delete_user_meta( $wcb_cand, '_wcb_employer_banned' );
wp_set_current_user( $wcb_other );
wcb_assert( \WCB\Modules\Candidates\CandidatesModule::resume_is_readable( $wcb_resume ), 'restore brings the resume back' );
wcb_assert( 200 === wcb_ts_rest( 'GET', '/wcb/v1/employers/' . $wcb_company, array(), 0 )->get_status(), 'control: a published company is public' );
update_user_meta( $wcb_employer, '_wcb_employer_banned', '1' );
wcb_assert( 404 === wcb_ts_rest( 'GET', '/wcb/v1/employers/' . $wcb_company, array(), 0 )->get_status(), 'a banned employer\'s hidden company is a 404 to a guest' );
wcb_assert( 200 === wcb_ts_rest( 'GET', '/wcb/v1/employers/' . $wcb_company, array(), $wcb_employer )->get_status(), 'its owner still reads it' );
delete_user_meta( $wcb_employer, '_wcb_employer_banned' );

WP_CLI::log( '--- members banned before this release ---' );
$wcb_old  = wcb_ts_user( 'wcb_employer' );
$wcb_oj   = wcb_ts_post( 'wcb_job', $wcb_old, 'publish' );
$wpdb->insert( $wpdb->usermeta, array( 'user_id' => $wcb_old, 'meta_key' => '_wcb_employer_banned', 'meta_value' => '1' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery -- the pre-1.8.0 state: flag set, nothing hidden.
$wcb_db   = get_option( 'wcb_db_version' );
update_option( 'wcb_db_version', '1.3.4' );
wcb_assert( 'publish' === get_post_status( $wcb_oj ), 'control: a ban set before 1.8.0 left the listing live' );
\WCB\Core\Install::maybe_migrate();
wcb_assert( 'publish' !== get_post_status( $wcb_oj ) && 'ban' === HiddenContent::reason( $wcb_oj ), 'the upgrade hides the listings of members banned before 1.8.0' );
update_option( 'wcb_db_version', $wcb_db );
wp_delete_post( $wcb_oj, true );
foreach ( array( $wcb_old, $wcb_cand, $wcb_other ) as $wcb_id ) {
	wp_delete_user( $wcb_id );
}
wp_delete_post( $wcb_resume, true );
wp_delete_post( $wcb_j, true );

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
