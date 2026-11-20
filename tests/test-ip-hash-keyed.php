<?php
/**
 * Stored visitor IP hashes are keyed, not a bare sha256 (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-ip-hash-keyed.php
 *
 * A bare sha256 of an IPv4 address is reversible in minutes. The job-view row
 * must hold hash_hmac( sha256, ip, wp_salt( auth ) ): not equal to sha256( ip ),
 * and identical for the same IP so the one-view-per-day count still works.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

global $wpdb;
$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

$ip                    = '203.0.113.77';
$_SERVER['REMOTE_ADDR'] = $ip;
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) Firefox/120.0';

$job = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'IP hash probe', 'post_author' => 1 ) );
wp_set_current_user( 0 );
$table = $wpdb->prefix . 'wcb_job_views';
$wpdb->delete( $table, array( 'job_id' => $job ) );

rest_do_request( new WP_REST_Request( 'GET', '/wcb/v1/jobs/' . $job ) );
rest_do_request( new WP_REST_Request( 'GET', '/wcb/v1/jobs/' . $job ) );
$rows = (array) $wpdb->get_col( $wpdb->prepare( "SELECT ip_hash FROM {$table} WHERE job_id = %d", $job ) );

$ok( count( $rows ) >= 1, 'a view row was written for the visit' );
$ok( ! in_array( hash( 'sha256', $ip ), $rows, true ), 'row is NOT the bare sha256 of the known IP' );
$ok( in_array( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), $rows, true ), 'row is the keyed hash of the IP' );
$ok( 1 === count( array_unique( $rows ) ), 'same IP, same hash every time (the daily count still works)' );

$wpdb->delete( $table, array( 'job_id' => $job ) );
wp_delete_post( $job, true );

WP_CLI::log( "{$pass} passed, {$fail} failed" );
exit( $fail ? 1 : 0 );
