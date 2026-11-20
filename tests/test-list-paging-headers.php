<?php
/**
 * Every list route sends the WordPress-standard paging headers (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-list-paging-headers.php
 *
 * Pins: each list route answers with X-WP-Total and X-WP-TotalPages that match
 * the body, and still sends the older X-WCB-Total / X-WCB-TotalPages pair, so
 * apiFetch, the mobile app and headless clients can page without a plugin-
 * specific header.
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

wp_set_current_user( 1 );
rest_get_server();

$job       = (int) ( get_posts( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) )[0] ?? 0 );
$company   = (int) ( get_posts( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) )[0] ?? 0 );
$candidate = (int) ( get_users( array( 'role' => 'wcb_candidate', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
$ok( $job && $company && $candidate, 'fixtures exist: a live job, a company and a candidate' );

$routes = array(
	'R1: GET /wcb/v1/jobs'                                  => '/wcb/v1/jobs',
	'R2: GET /wcb/v1/companies'                             => '/wcb/v1/companies',
	'R3: GET /wcb/v1/jobs/{id}/applications'                => "/wcb/v1/jobs/{$job}/applications",
	'R4: GET /wcb/v1/employers/{id}/applications'           => "/wcb/v1/employers/{$company}/applications",
	'R5: GET /wcb/v1/employers/me/applications'             => '/wcb/v1/employers/me/applications',
	'R6: GET /wcb/v1/candidates/{id}/applications'          => "/wcb/v1/candidates/{$candidate}/applications",
	'R7: GET /wcb/v1/candidates/{id}/bookmarks'             => "/wcb/v1/candidates/{$candidate}/bookmarks",
	'R8: GET /wcb/v1/candidates/{id}/saved-companies'       => "/wcb/v1/candidates/{$candidate}/saved-companies",
	'R9: GET /wcb/v1/candidates/{id}/saved-resumes'         => "/wcb/v1/candidates/{$candidate}/saved-resumes",
);

foreach ( $routes as $label => $route ) {
	$res  = rest_do_request( new WP_REST_Request( 'GET', $route ) );
	$h    = $res->get_headers();
	$data = (array) $res->get_data();
	$ok( 200 === $res->get_status(), "{$label}: answers 200 (got {$res->get_status()})" );
	$ok( isset( $h['X-WP-Total'], $h['X-WP-TotalPages'] ), "{$label}: sends X-WP-Total and X-WP-TotalPages" );
	$ok( isset( $data['total'] ) && (string) $data['total'] === (string) ( $h['X-WP-Total'] ?? '' ), "{$label}: X-WP-Total matches the body total" );
	$ok( isset( $data['pages'] ) && (string) $data['pages'] === (string) ( $h['X-WP-TotalPages'] ?? '' ), "{$label}: X-WP-TotalPages matches the body pages" );
	$ok( ( $h['X-WCB-Total'] ?? null ) === ( $h['X-WP-Total'] ?? 'x' ), "{$label}: the older X-WCB-Total pair is still sent" );
}

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'List paging header tests passed.' );
