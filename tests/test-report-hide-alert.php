<?php
/**
 * The report that hides a job always alerts the owner (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-report-hide-alert.php
 *
 * Pins: the "job reported" email (and Pro's bell, when active) go out on the
 * first report and on the report that hides the job, even when reports from
 * members without standing have pushed the total past the threshold. Reports
 * between those two send nothing.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Admin\Settings;
use WCB\Modules\Moderation\HiddenContent;

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

global $wpdb;
$title = 'Senior Payroll Analyst ' . wp_generate_password( 5, false, false );

// Threshold 3 and the report email on, forced in memory only.
$force = static function ( $settings ) {
	$settings                               = is_array( $settings ) ? $settings : array();
	$settings['report_auto_hide_threshold'] = 3;
	$emails                                 = is_array( $settings['emails'] ?? null ) ? $settings['emails'] : (array) get_option( 'wcb_email_settings', array() );
	$emails['report-received']              = array_merge( (array) ( $emails['report-received'] ?? array() ), array( 'enabled' => true ) );
	$settings['emails']                     = $emails;
	return $settings;
};
add_filter( 'option_wcb_settings', $force );
add_filter( 'default_option_wcb_settings', $force );
Settings::flush_cache();

$mails   = 0;
$capture = static function ( $short, array $atts ) use ( &$mails, $title ) {
	if ( str_contains( (string) ( $atts['subject'] ?? '' ) . (string) ( $atts['message'] ?? '' ), $title ) ) {
		++$mails;
	}
	return true; // Never actually sent.
};
add_filter( 'pre_wp_mail', $capture, 10, 2 );

$bell_table = $wpdb->prefix . 'wcb_notifications';
$has_bell   = class_exists( '\WCB\Pro\Modules\NotificationsBell\NotificationsBellModule' ) && $bell_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bell_table ) );
$bell_from  = $has_bell ? (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$bell_table}" ) : 0; // phpcs:ignore WordPress.DB
$bells      = static function () use ( $wpdb, $bell_table, $bell_from, $title ): int {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bell_table} WHERE id > %d AND user_id = 1 AND event_type = 'report_received' AND message LIKE %s", $bell_from, '%' . $wpdb->esc_like( $title ) . '%' ) );
};

// One reporter without standing, then four with it.
$users = array();
foreach ( array( 'priya', 'tomas', 'aiko', 'lena', 'omar' ) as $name ) {
	$login   = $name . '-' . wp_generate_password( 6, false, false );
	$users[] = (int) wp_insert_user(
		array(
			'user_login' => $login,
			'user_pass'  => wp_generate_password( 16 ),
			'user_email' => $login . '@example.test',
			'role'       => 'subscriber',
		)
	);
}
$no_standing = $users[0];
$standing    = static fn ( bool $s, int $uid ): bool => $uid === $no_standing ? false : ( in_array( $uid, $users, true ) ? true : $s );
add_filter( 'wcb_reporter_has_standing', $standing, 10, 2 );

$job = (int) wp_insert_post(
	array(
		'post_type'    => 'wcb_job',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_content' => 'Own month-end payroll for 400 staff across three countries.',
		'post_author'  => 1,
	)
);

$report = static function ( int $uid ) use ( $job ): int {
	wp_set_current_user( $uid );
	$req = new WP_REST_Request( 'POST', '/wcb/v1/jobs/' . $job . '/report' );
	$req->set_body_params( array( 'reason' => 'spam' ) );
	return rest_do_request( $req )->get_status();
};

$report( $users[0] );
$ok( 1 === $mails, 'first report (no standing): one email' );
$report( $users[1] );
$report( $users[2] );
$ok( 1 === $mails && 'publish' === get_post_status( $job ), 'reports 2 and 3 (2 with standing): job stays up, no email' );
$mails_before = $mails;
$bells_before = $has_bell ? $bells() : 0;
$report( $users[3] );
$ok( 'reports' === HiddenContent::reason( $job ), 'report 4 (3rd with standing) hides the job at count 4' );
$ok( 1 === $mails - $mails_before, 'the hiding report sends exactly one email (sent ' . ( $mails - $mails_before ) . ')' );
if ( $has_bell ) {
	$ok( 1 === $bells() - $bells_before && 2 === $bells(), 'Pro bell: one on the first report, one on the hiding report (got ' . $bells() . ' in total)' );
} else {
	WP_CLI::log( '  SKIP: Pro bell not active' );
}
$status = $report( $users[4] );
$ok( 404 === $status && 2 === $mails, 'a 5th report after the hide sends nothing' );

wp_set_current_user( 1 );
remove_filter( 'wcb_reporter_has_standing', $standing, 10 );
remove_filter( 'pre_wp_mail', $capture, 10 );
remove_filter( 'option_wcb_settings', $force );
remove_filter( 'default_option_wcb_settings', $force );
Settings::flush_cache();
if ( $has_bell ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$bell_table} WHERE id > %d AND message LIKE %s", $bell_from, '%' . $wpdb->esc_like( $title ) . '%' ) );
}
wp_delete_post( $job, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( $users as $uid ) {
	wp_delete_user( $uid );
}

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Report hide alert tests passed.' );
