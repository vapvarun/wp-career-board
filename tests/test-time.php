<?php
/**
 * Time Standard guard (1.8.3): times are stored in UTC and shown in one zone.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-time.php
 *
 * Sets the site to Asia/Kolkata (UTC+5:30) for this process only, then pins:
 * display, edit fields, deadlines (end of the local day, stored UTC), the
 * wcb/v1 ISO output, the member-timezone filter moving display and edit
 * fields together, and UTC writes to the email log.
 *
 * Self-contained: restores the timezone and deletes what it creates.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WCB\Core\JobDeadline;
use WCB\Core\Time;

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
		WP_CLI::warning( "  FAIL: {$label}" );
	}
}

$wcb_tz_was  = get_option( 'timezone_string' );
$wcb_off_was = get_option( 'gmt_offset' );
update_option( 'timezone_string', 'Asia/Kolkata' );
update_option( 'gmt_offset', '' );

WP_CLI::log( '--- display ---' );
wcb_assert( '2026-01-15 05:30' === Time::date_label( '2026-01-15 00:00:00', '', 'Y-m-d H:i' ), 'a stored UTC time shows in the site timezone (+5:30)' );
wcb_assert( '2026-01-16' === Time::date_label( '2026-01-15 20:00:00', '', 'Y-m-d' ), 'near UTC midnight the local date is the next day' );
wcb_assert( '2026-01-15 05:30' === Time::date_label( '2026-01-15T00:00:00+00:00', '', 'Y-m-d H:i' ), 'ISO input reads the same instant' );
wcb_assert( '—' === Time::date_label( '0000-00-00 00:00:00' ) && 'x' === Time::date_label( '', 'x' ), 'zero and empty times give the fallback' );
wcb_assert( '2026-01-15' === Time::calendar_date_label( '2026-01-15', '', 'Y-m-d' ), 'a calendar date never moves with the zone' );

WP_CLI::log( '--- edit fields round trip ---' );
$wcb_utc = Time::datetime_input_to_utc( '2026-03-10T09:00' );
wcb_assert( '2026-03-10 03:30:00' === $wcb_utc, 'what an editor types is saved as UTC' );
wcb_assert( '2026-03-10T09:00' === Time::datetime_local_input( $wcb_utc ), 'the edit field shows it back unchanged' );

WP_CLI::log( '--- deadlines ---' );
wcb_assert( '2026-05-20 18:29:59' === Time::end_of_day_utc( '2026-05-20' ), 'a picked day ends at 23:59:59 local, stored UTC' );
wcb_assert( '2026-05-20 18:29:59' === Time::end_of_day_utc( '2026-05-20 18:29:59' ), 'a stored UTC deadline passes through' );
wcb_assert( '2026-05-20' === Time::date_input( '2026-05-20 18:29:59' ), 'the picker shows the local day' );
$wcb_job = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'Time guard job' ) );
update_post_meta( $wcb_job, '_wcb_deadline', '2026-05-20' );
wcb_assert( '2026-05-20 18:29:59' === get_post_meta( $wcb_job, '_wcb_deadline', true ), 'every deadline write is stored as the UTC end of day' );
update_post_meta( $wcb_job, '_wcb_deadline', Time::today() );
wcb_assert( ! JobDeadline::has_passed( $wcb_job ), 'a job is open through the last minute of its deadline day' );
update_post_meta( $wcb_job, '_wcb_deadline', gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS ) );
wcb_assert( JobDeadline::has_passed( $wcb_job ), 'a past deadline has passed' );
update_post_meta( $wcb_job, '_wcb_deadline', 'not a date' );
wcb_assert( '' === get_post_meta( $wcb_job, '_wcb_deadline', true ), 'junk is stored as no deadline' );

WP_CLI::log( '--- REST ---' );
update_post_meta( $wcb_job, '_wcb_deadline', '2026-05-20' );
wp_set_current_user( 1 );
$wcb_data = rest_get_server()->response_to_data( rest_do_request( new WP_REST_Request( 'GET', '/wcb/v1/jobs/' . $wcb_job ) ), false );
wcb_assert( '2026-05-20T18:29:59+00:00' === ( $wcb_data['deadline'] ?? '' ), 'wcb/v1 sends the deadline as ISO 8601 with offset' );
wcb_assert( '2026-05-20' === ( $wcb_data['deadline_day'] ?? '' ), 'and its local day for pickers' );
wcb_assert( 1 === preg_match( '/\+00:00$/', (string) ( $wcb_data['created_at'] ?? '' ) ), 'created_at is ISO with offset' );

WP_CLI::log( '--- member timezone filter ---' );
$wcb_member_tz = static fn() => new DateTimeZone( 'America/New_York' );
add_filter( 'wcb_display_timezone', $wcb_member_tz );
wcb_assert( '2026-01-14 19:00' === Time::date_label( '2026-01-15 00:00:00', '', 'Y-m-d H:i' ), 'the filter moves display' );
wcb_assert( '2026-01-14T19:00' === Time::datetime_local_input( '2026-01-15 00:00:00' ), 'and edit fields with it' );
remove_filter( 'wcb_display_timezone', $wcb_member_tz );

WP_CLI::log( '--- storage ---' );
global $wpdb;
$wcb_before = gmdate( 'Y-m-d H:i:s', time() - 5 );
$wcb_log = new ReflectionMethod( \WCB\Modules\Gdpr\GdprModule::class, 'log_action' );
$wcb_log->setAccessible( true );
$wcb_log->invoke( null, 1, 'time_guard' );
$wcb_row = (string) $wpdb->get_var( $wpdb->prepare( "SELECT created_at FROM {$wpdb->prefix}wcb_gdpr_log WHERE action = %s ORDER BY id DESC LIMIT 1", 'time_guard' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
wcb_assert( $wcb_row >= $wcb_before && $wcb_row <= gmdate( 'Y-m-d H:i:s', time() + 5 ), 'history rows are written in UTC' );
$wpdb->delete( $wpdb->prefix . 'wcb_gdpr_log', array( 'action' => 'time_guard' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

WP_CLI::log( '--- converting old site-time rows (DST, rerun) ---' );
update_option( 'timezone_string', 'America/New_York' );
$wcb_tt = $wpdb->prefix . 'wcb_time_guard';
$wpdb->query( "CREATE TABLE {$wcb_tt} ( id INT AUTO_INCREMENT PRIMARY KEY, at DATETIME NOT NULL )" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->insert( $wcb_tt, array( 'at' => '2025-07-10 09:00:00' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->insert( $wcb_tt, array( 'at' => '2025-12-10 09:00:00' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wcb_conv_was = get_option( 'wcb_utc_converted', array() );
Time::local_column_to_utc( $wcb_tt, 'at' );
$wcb_rows = $wpdb->get_col( "SELECT at FROM {$wcb_tt} ORDER BY id" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
wcb_assert( '2025-07-10 13:00:00' === $wcb_rows[0], 'a July row on a New York site moves +4h (daylight time)' );
wcb_assert( '2025-12-10 14:00:00' === $wcb_rows[1], 'a December row moves +5h (standard time)' );
Time::local_column_to_utc( $wcb_tt, 'at' );
wcb_assert( $wcb_rows === $wpdb->get_col( "SELECT at FROM {$wcb_tt} ORDER BY id" ), 'running the conversion again changes nothing' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE {$wcb_tt}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
update_option( 'wcb_utc_converted', $wcb_conv_was, false );

wp_delete_post( $wcb_job, true );
update_option( 'timezone_string', $wcb_tz_was );
update_option( 'gmt_offset', $wcb_off_was );

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All time tests passed.' );
}
