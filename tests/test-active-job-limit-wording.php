<?php
/**
 * The active-job limit message says "1 active job" and "2 active jobs" (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-active-job-limit-wording.php
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

global $wpdb;
$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$emp = (int) wp_insert_user( array( 'user_login' => 'ajl_emp_' . wp_generate_password( 5, false ), 'user_pass' => wp_generate_password( 20 ), 'user_email' => 'ajl' . wp_generate_password( 5, false ) . '@example.test', 'role' => 'wcb_employer' ) );
$co  = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => 'AJL Co', 'post_author' => $emp ) );
update_user_meta( $emp, '_wcb_company_id', $co );
add_filter( 'wcb_credits_enabled', '__return_false', 99 );

// The limit check runs after the credits gate on a site with Pro credits, so call it directly.
$check   = new ReflectionMethod( \WCB\Api\Endpoints\JobsEndpoint::class, 'check_active_job_limit' );
$check->setAccessible( true );
$message = static function ( int $limit ) use ( $emp, $check ): string {
	$cb = static fn(): int => $limit;
	add_filter( 'wcb_employer_active_job_limit', $cb );
	$err = $check->invoke( new \WCB\Api\Endpoints\JobsEndpoint(), $emp, new WP_REST_Request( 'POST', '/wcb/v1/jobs' ) );
	remove_filter( 'wcb_employer_active_job_limit', $cb );
	return $err instanceof WP_Error ? $err->get_error_message() : '';
};

wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'AJL one', 'post_author' => $emp ) );
$one = $message( 1 );
$ok( str_contains( $one, 'You can have 1 active job at a time' ), 'a limit of 1 reads "1 active job"' );

wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'AJL two', 'post_author' => $emp ) );
$two = $message( 2 );
$ok( str_contains( $two, 'You can have 2 active jobs at a time' ), 'a limit of 2 reads "2 active jobs"' );

$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
wp_cache_flush();

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'All limit-wording tests passed.' );
