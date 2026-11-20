<?php
/**
 * Saved jobs and companies: the heart can never silently unsave (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-bookmark-state.php
 *
 * Pins: the save routes take the state the member wants (a stale heart cannot
 * remove a save), still flip when no state is sent, and the company list says
 * which companies the viewer saved, the way the job list already did.
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

/**
 * Call a REST route as a user (0 = guest).
 *
 * @param string               $method  HTTP method.
 * @param string               $route   Route.
 * @param array<string, mixed> $params  Body params.
 * @param int                  $user_id User ID.
 * @return WP_REST_Response
 */
$call = static function ( string $method, string $route, array $params, int $user_id ): WP_REST_Response {
	wp_set_current_user( $user_id );
	$req = new WP_REST_Request( $method, $route );
	foreach ( $params as $k => $v ) {
		$req->set_param( $k, $v );
	}
	return rest_do_request( $req );
};

$suffix = wp_generate_password( 5, false, false );
$user   = (int) wp_insert_user(
	array(
		'user_login' => 'bm_' . $suffix,
		'user_pass'  => wp_generate_password(),
		'user_email' => 'bm_' . $suffix . '@example.test',
		'role'       => 'wcb_candidate',
	)
);
$job    = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'Bookmark probe job' ) );
$other  = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'Bookmark probe other job' ) );
$co     = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => 'Bookmark Probe Co' ) );
$co2    = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => 'Bookmark Probe Other Co' ) );
$ok( $user && $job && $co, 'fixtures: a candidate, two jobs and two companies' );

foreach ( array( 'job' => "/wcb/v1/jobs/{$job}/bookmark", 'company' => "/wcb/v1/companies/{$co}/bookmark" ) as $what => $route ) {
	$key = 'job' === $what ? '_wcb_bookmark' : '_wcb_company_bookmark';
	$id  = 'job' === $what ? $job : $co;
	$n   = static fn (): int => count( array_filter( (array) get_user_meta( $user, $key, false ), static fn ( $v ): bool => (int) $v === $id ) );

	$label = 'job' === $what ? 'R1: POST /wcb/v1/jobs/{id}/bookmark' : 'R2: POST /wcb/v1/companies/{id}/bookmark';
	$r     = $call( 'POST', $route, array( 'bookmarked' => true ), $user );
	$ok( true === ( $r->get_data()['bookmarked'] ?? null ) && 1 === $n(), "{$label}: bookmarked=true saves it" );
	$r = $call( 'POST', $route, array( 'bookmarked' => true ), $user );
	$ok( true === ( $r->get_data()['bookmarked'] ?? null ) && 1 === $n(), "{$label}: a stale heart sending bookmarked=true again keeps the save, once (old code removed it)" );
	$r = $call( 'POST', $route, array(), $user );
	$ok( false === ( $r->get_data()['bookmarked'] ?? null ) && 0 === $n(), "{$label}: with no state sent it still flips (compatible with the app)" );
	$r = $call( 'POST', $route, array( 'bookmarked' => false ), $user );
	$ok( false === ( $r->get_data()['bookmarked'] ?? null ) && 0 === $n(), "{$label}: bookmarked=false on an unsaved item adds nothing (old code saved it)" );
	$r = $call( 'POST', $route, array( 'bookmarked' => true ), $user );
	$ok( 1 === $n(), "{$label}: saved again for the list checks below" );
}

// The lists say which rows the viewer saved.
$r    = $call( 'GET', '/wcb/v1/jobs', array( 'per_page' => 100 ), $user );
$rows = array();
foreach ( (array) ( $r->get_data()['jobs'] ?? array() ) as $row ) {
	$rows[ (int) $row['id'] ] = $row;
}
$ok( ! empty( $rows[ $job ]['is_bookmarked'] ) && empty( $rows[ $other ]['is_bookmarked'] ), 'R3: GET /wcb/v1/jobs marks the saved job and not the other' );

$r    = $call( 'GET', '/wcb/v1/companies', array( 'per_page' => 100 ), $user );
$rows = array();
foreach ( (array) ( $r->get_data()['companies'] ?? array() ) as $row ) {
	$rows[ (int) $row['id'] ] = $row;
}
$ok( isset( $rows[ $co ] ) && true === $rows[ $co ]['is_bookmarked'], 'R4: GET /wcb/v1/companies marks the saved company (old code had no such key)' );
$ok( isset( $rows[ $co2 ] ) && false === $rows[ $co2 ]['is_bookmarked'], 'R4: GET /wcb/v1/companies leaves the other company unsaved' );
$ok( str_contains( (string) ( $r->get_headers()['Vary'] ?? '' ), 'Cookie' ), 'R4: the company list varies by viewer, so a shared cache cannot reuse it' );

// A guest sees every heart unsaved, with no error.
$g    = $call( 'GET', '/wcb/v1/companies', array( 'per_page' => 100 ), 0 );
$rows = (array) ( $g->get_data()['companies'] ?? array() );
$ok( 200 === $g->get_status() && $rows && ! array_filter( array_column( $rows, 'is_bookmarked' ) ), 'R4: a guest gets every company unsaved and no error' );

// The viewer's saved state is not stored in the shared list cache: a second viewer is not shown the first one's saves.
$second = (int) wp_insert_user(
	array(
		'user_login' => 'bm2_' . $suffix,
		'user_pass'  => wp_generate_password(),
		'user_email' => 'bm2_' . $suffix . '@example.test',
		'role'       => 'wcb_candidate',
	)
);
$r2   = $call( 'GET', '/wcb/v1/companies', array( 'per_page' => 100 ), $second );
$rows = array();
foreach ( (array) ( $r2->get_data()['companies'] ?? array() ) as $row ) {
	$rows[ (int) $row['id'] ] = $row;
}
$ok( isset( $rows[ $co ] ) && false === $rows[ $co ]['is_bookmarked'], 'R4: a second member does not see the first member\'s saved company' );

wp_set_current_user( 0 );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user );
wp_delete_user( $second );
foreach ( array( $job, $other, $co, $co2 ) as $pid ) {
	wp_delete_post( $pid, true );
}

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Bookmark state tests passed.' );
