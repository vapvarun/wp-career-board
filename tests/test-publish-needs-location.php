<?php
/**
 * "Require a location" holds every publish path, not only the editor (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-publish-needs-location.php
 *
 * Pins: wp_update_post (Quick/Bulk Edit), REST PATCH status, Approve, and that a
 * job with a location or Remote publishes, and that the setting off changes nothing.
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

$old = get_option( 'wcb_settings' );
update_option( 'wcb_settings', array_merge( (array) $old, array( 'require_job_location' => true ) ) );
wp_set_current_user( 1 );

$mk = static fn( string $status = 'draft' ) => (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => $status, 'post_title' => 'PNL Job', 'post_author' => 1 ) );
$st = static fn( int $id ) => get_post_status( $id );

// Quick Edit / Bulk Edit / any wp_update_post flip.
$j = $mk();
wp_update_post( array( 'ID' => $j, 'post_status' => 'publish' ) );
$ok( 'draft' === $st( $j ), 'wp_update_post to publish keeps a no-location draft as draft' );
update_post_meta( $j, '_wcb_remote', '1' );
wp_update_post( array( 'ID' => $j, 'post_status' => 'publish' ) );
$ok( 'publish' === $st( $j ), 'the same job publishes once it is remote' );
wp_update_post( array( 'ID' => $j, 'post_title' => 'PNL renamed' ) );
$ok( 'publish' === $st( $j ), 'an already published job can still be renamed' );

// Pending stays pending, not draft.
$p = $mk( 'pending' );
wp_update_post( array( 'ID' => $p, 'post_status' => 'publish' ) );
$ok( 'pending' === $st( $p ), 'a pending no-location job stays pending' );

// REST PATCH status only, then with a location in the same request.
$r = $mk();
$req = new WP_REST_Request( 'PATCH', '/wcb/v1/jobs/' . $r );
$req->set_header( 'content-type', 'application/json' );
$req->set_body( wp_json_encode( array( 'status' => 'publish' ) ) );
$res = rest_do_request( $req );
$ok( 400 === $res->get_status() && 'wcb_location_required' === ( $res->get_data()['code'] ?? '' ) && 'draft' === $st( $r ), 'PATCH status=publish without a location is refused' );
$req = new WP_REST_Request( 'PATCH', '/wcb/v1/jobs/' . $r );
$req->set_header( 'content-type', 'application/json' );
$req->set_body( wp_json_encode( array( 'status' => 'publish', 'remote' => true ) ) );
$res = rest_do_request( $req );
$ok( 200 === $res->get_status() && 'publish' === $st( $r ), 'PATCH status=publish with remote in the same request publishes' );

// Approve: the admin's call. It publishes and says what is missing.
$a = $mk( 'pending' );
$res = rest_do_request( new WP_REST_Request( 'POST', '/wcb/v1/jobs/' . $a . '/approve' ) );
$ok( 200 === $res->get_status() && 'publish' === $st( $a ), 'Approve on a no-location pending job publishes it' );
$ok( ! empty( $res->get_data()['notice'] ), 'and the response carries a heads-up' );
$b = $mk( 'pending' );
update_post_meta( $b, '_wcb_remote', '1' );
$res = rest_do_request( new WP_REST_Request( 'POST', '/wcb/v1/jobs/' . $b . '/approve' ) );
$ok( 200 === $res->get_status() && 'publish' === $st( $b ) && empty( $res->get_data()['notice'] ), 'a remote job approves with no notice' );

// Core's /wp/v2 route: an API client (app password) is held to the rule; the block editor (cookie) is not.
if ( ! defined( 'REST_REQUEST' ) ) {
	define( 'REST_REQUEST', true ); // A live /wp/v2 request has it; the status guard keys off it with the route below.
}
$core = static function ( int $id, array $body ) use ( $st ): WP_REST_Response {
	$GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/wcb_job/' . $id;
	$req = new WP_REST_Request( 'POST', '/wp/v2/wcb_job/' . $id );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_body( wp_json_encode( $body ) );
	$res = rest_do_request( $req );
	unset( $GLOBALS['wp']->query_vars['rest_route'] );
	return $res;
};
$c1 = $mk();
$GLOBALS['wp_rest_application_password_uuid'] = 'test-uuid';
$res = $core( $c1, array( 'status' => 'publish' ) );
$ok( 400 === $res->get_status() && 'draft' === $st( $c1 ), 'an API client publishing through /wp/v2 without a location is refused' );
$res = $core( $c1, array( 'status' => 'publish', 'meta' => array( '_wcb_remote' => '1' ) ) );
$ok( 200 === $res->get_status() && 'publish' === $st( $c1 ), 'the same request marked remote publishes' );
$c2 = $mk();
unset( $GLOBALS['wp_rest_application_password_uuid'] );
$res = $core( $c2, array( 'status' => 'publish' ) );
$ok( 200 === $res->get_status() && 'publish' === $st( $c2 ), 'a cookie session (the block editor) is not blocked' );

// Bulk Edit tells the list why: the redirect carries how many were kept back.
$ok( str_contains( \WCB\Core\Locations::flag_held_redirect( admin_url( 'edit.php?post_type=wcb_job' ) ), 'wcb_held=' ), 'the Jobs list redirect carries the held count' );

// Setting off: nothing changes.
update_option( 'wcb_settings', array_merge( (array) $old, array( 'require_job_location' => false ) ) );
$o = $mk();
wp_update_post( array( 'ID' => $o, 'post_status' => 'publish' ) );
$ok( 'publish' === $st( $o ), 'with the setting off a no-location job publishes' );

update_option( 'wcb_settings', $old );
foreach ( array( $j, $p, $r, $a, $o ) as $id ) {
	wp_delete_post( $id, true );
}

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Every publish path needs a location.' );
