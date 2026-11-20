<?php
/**
 * Public REST writes refuse junk and repeat probes (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-rest-write-hardening.php
 *
 * Pins: a visitor counts once per job an hour on the view route; a save for an
 * item that does not exist (or is not a live job or company) is refused with
 * 404 while removing a save always works; the register routes count an attempt
 * for an address that already has an account, so the 409 is not a free probe.
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

$call = static function ( string $method, string $route, array $params, int $user_id ): WP_REST_Response {
	wp_set_current_user( $user_id );
	$req = new WP_REST_Request( $method, $route );
	foreach ( $params as $k => $v ) {
		$req->set_param( $k, $v );
	}
	return rest_do_request( $req );
};

$server_was = array( $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null );
$ip         = '203.0.113.' . wp_rand( 20, 250 );
$_SERVER['REMOTE_ADDR']     = $ip;
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) Test';

$suffix = wp_generate_password( 5, false, false );
$user   = (int) wp_insert_user(
	array(
		'user_login' => 'wh_' . $suffix,
		'user_pass'  => wp_generate_password(),
		'user_email' => 'wh_' . $suffix . '@example.test',
		'role'       => 'wcb_candidate',
	)
);
$job   = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'Hardening probe job' ) );
$draft = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'draft', 'post_title' => 'Hardening probe draft' ) );
$co    = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => 'Hardening Probe Co' ) );

// --- 1. views: once per visitor per job per hour -------------------------------------------------
$views = static fn ( int $id ): int => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wcb_job_views WHERE job_id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
for ( $i = 0; $i < 6; $i++ ) {
	$r = $call( 'POST', "/wcb/v1/jobs/{$job}/view", array(), 0 );
}
$ok( 204 === $r->get_status(), 'R1: POST /wcb/v1/jobs/{id}/view still answers 204 to a repeat visitor' );
$ok( 1 === $views( $job ), 'R1: POST /wcb/v1/jobs/{id}/view: six calls from one guest record one view (old code recorded six; got ' . $views( $job ) . ')' );
$_SERVER['REMOTE_ADDR'] = '198.51.100.' . wp_rand( 20, 250 );
$call( 'POST', "/wcb/v1/jobs/{$job}/view", array(), 0 );
$ok( 2 === $views( $job ), 'R1: a different visitor counts as another view' );
$call( 'POST', "/wcb/v1/jobs/{$job}/view", array(), $user );
$call( 'POST', "/wcb/v1/jobs/{$job}/view", array(), $user );
$ok( 3 === $views( $job ), 'R1: a signed-in member counts once, however often they reload' );
$_SERVER['REMOTE_ADDR'] = $ip;

// --- 2. saves: a live job or company, else 404; removing always works -----------------------------
$r = $call( 'POST', '/wcb/v1/jobs/999999999/bookmark', array(), $user );
$ok( 404 === $r->get_status(), 'R2: POST /wcb/v1/jobs/{id}/bookmark for an id that does not exist is 404 (old code: 200; got ' . $r->get_status() . ')' );
$r = $call( 'POST', '/wcb/v1/companies/999999998/bookmark', array(), $user );
$ok( 404 === $r->get_status(), 'R3: POST /wcb/v1/companies/{id}/bookmark for an id that does not exist is 404 (old code: 200; got ' . $r->get_status() . ')' );
$r = $call( 'POST', "/wcb/v1/jobs/{$co}/bookmark", array(), $user );
$ok( 404 === $r->get_status(), 'R2: a company id is not a job' );
$r = $call( 'POST', "/wcb/v1/companies/{$job}/bookmark", array(), $user );
$ok( 404 === $r->get_status(), 'R3: a job id is not a company' );
$r = $call( 'POST', "/wcb/v1/jobs/{$draft}/bookmark", array(), $user );
$ok( 404 === $r->get_status(), 'R2: a draft job cannot be saved' );
$ok( ! in_array( 999999999, array_map( 'intval', (array) get_user_meta( $user, '_wcb_bookmark', false ) ), true ), 'no save row was written for the id that does not exist' );
$r = $call( 'POST', "/wcb/v1/jobs/{$job}/bookmark", array(), $user );
$ok( 200 === $r->get_status() && true === $r->get_data()['bookmarked'], 'R2: a live job still saves' );
$r = $call( 'POST', "/wcb/v1/companies/{$co}/bookmark", array(), $user );
$ok( 200 === $r->get_status() && true === $r->get_data()['bookmarked'], 'R3: a live company still saves' );
// A save for a job that has since ended can still be removed.
add_user_meta( $user, '_wcb_bookmark', $draft, false );
$r = $call( 'POST', "/wcb/v1/jobs/{$draft}/bookmark", array(), $user );
$ok( 200 === $r->get_status() && false === $r->get_data()['bookmarked'] && ! in_array( $draft, array_map( 'intval', (array) get_user_meta( $user, '_wcb_bookmark', false ) ), true ), 'R2: removing a save for a job that is no longer live works' );

// --- 3. register: an existing address counts against the sign-up limit ----------------------------
$limit = (int) apply_filters( 'wcb_registration_rate_limit', 5 );
foreach ( array( 'candidates', 'employers' ) as $who ) {
	delete_transient( 'wcb_reg_' . md5( wp_salt() . $ip ) );
	$codes = array();
	for ( $i = 0; $i < $limit + 3; $i++ ) {
		$r       = $call(
			'POST',
			"/wcb/v1/{$who}/register",
			array(
				'first_name'   => 'Probe',
				'last_name'    => 'Person',
				'company_name' => 'Probe Works',
				'email'        => 'wh_' . $suffix . '@example.test', // Already has an account.
				'password'     => 'a-long-enough-password',
			),
			0
		);
		$codes[] = $r->get_status();
	}
	$label = "R4: POST /wcb/v1/{$who}/register";
	$ok( array_fill( 0, $limit, 409 ) === array_slice( $codes, 0, $limit ), "{$label}: the first {$limit} probes still get the 409 message" );
	$ok( 429 === $codes[ $limit ] && 429 === $codes[ $limit + 2 ], "{$label}: the probes after the limit get 429 (old code: 409 every time; got " . implode( ',', $codes ) . ')' );
}
delete_transient( 'wcb_reg_' . md5( wp_salt() . $ip ) );

// Clean up.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wcb_job_views WHERE job_id = %d", $job ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( array( $ip, '198.51.100.0' ) as $addr ) {
	delete_transient( 'wcb_job_view_' . $job . '_' . md5( wp_salt() . $addr ) );
}
delete_transient( 'wcb_job_view_' . $job . '_' . md5( wp_salt() . 'user:' . $user ) );
wp_set_current_user( 0 );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user );
foreach ( array( $job, $draft, $co ) as $pid ) {
	wp_delete_post( $pid, true );
}
foreach ( array( 'REMOTE_ADDR', 'HTTP_USER_AGENT' ) as $i => $k ) {
	if ( null === $server_was[ $i ] ) {
		unset( $_SERVER[ $k ] );
	} else {
		$_SERVER[ $k ] = $server_was[ $i ];
	}
}

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'REST write hardening tests passed.' );
