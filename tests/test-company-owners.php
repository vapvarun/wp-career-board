<?php
/**
 * Company co-owners can be added and removed (1.8.6).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-company-owners.php
 *
 * Until 1.8.6 nothing wrote `_wcb_company_owner`: every permission check read
 * it, but no screen, route or command set it. The company's creator now adds
 * and removes co-owners (Employer accounts only); a co-owner sees the list
 * but cannot change it; a stranger sees nothing. Name search never returns
 * email addresses.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Modules\Employers\EmployersModule;

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

require_once ABSPATH . 'wp-admin/includes/user.php';
$made    = array();
$mk_user = static function ( string $name, string $role ) use ( &$made ): int {
	$slug   = strtolower( str_replace( ' ', '.', $name ) ) . wp_generate_password( 4, false, false );
	$id     = (int) wp_insert_user( array( 'user_login' => $slug, 'user_pass' => wp_generate_password(), 'user_email' => $slug . '@example.test', 'display_name' => $name, 'role' => $role ) );
	$made[] = $id;
	return $id;
};
$creator   = $mk_user( 'Kenji Watanabe', 'wcb_employer' );
$colleague = $mk_user( 'Helen Parkhurst', 'wcb_employer' );
$candidate = $mk_user( 'Amara Okafor', 'wcb_candidate' );
$stranger  = $mk_user( 'Diego Morales', 'wcb_employer' );

$company = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => 'Kestrel Robotics', 'post_author' => $creator ) );
$job     = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'Senior Backend Engineer', 'post_author' => $creator ) );
update_post_meta( $job, '_wcb_company_id', $company );

$call = static function ( string $method, string $route, array $params = array() ): array {
	$r = new WP_REST_Request( $method, $route );
	'GET' === $method ? $r->set_query_params( $params ) : $r->set_body_params( $params );
	$res = rest_do_request( $r );
	return array( $res->get_status(), (array) $res->get_data() );
};
$base = "/wcb/v1/employers/{$company}/owners";

// --- The creator ------------------------------------------------------------
wp_set_current_user( $creator );
$ok( ! EmployersModule::owns_job( $colleague, get_post( $job ) ), 'before: the colleague does not run the job' );

[ $status, $found ] = $call( 'GET', "{$base}/search", array( 'search' => 'Helen' ) );
$ids                = wp_list_pluck( (array) ( $found['results'] ?? array() ), 'id' );
$ok( 200 === $status && in_array( $colleague, $ids, true ), 'O1: GET /wcb/v1/employers/{id}/owners/search - name search finds the employer' );
$ok( ! in_array( $candidate, $ids, true ) && ! str_contains( (string) wp_json_encode( $found ), '@' ), 'name search returns no candidates and no email addresses' );

[ , $by_mail ] = $call( 'GET', "{$base}/search", array( 'search' => get_userdata( $colleague )->user_email ) );
$ok( array( $colleague ) === wp_list_pluck( (array) ( $by_mail['results'] ?? array() ), 'id' ), 'a full email address finds exactly that employer' );
[ , $partial ] = $call( 'GET', "{$base}/search", array( 'search' => '@example.test' ) );
$ok( array() === (array) ( $partial['results'] ?? array() ), 'part of an email address finds nobody' );

[ $status, $list ] = $call( 'POST', $base, array( 'user_id' => $colleague ) );
$ok( 200 === $status && in_array( $colleague, wp_list_pluck( (array) ( $list['owners'] ?? array() ), 'id' ), true ), 'O2: POST /wcb/v1/employers/{id}/owners - creator adds an employer as co-owner' );
$ok( EmployersModule::owns_job( $colleague, get_post( $job ) ), 'after: the co-owner runs the company\'s job' );

[ $status, $err ] = $call( 'POST', $base, array( 'user_id' => $colleague ) );
$ok( 409 === $status && 'wcb_owner_exists' === ( $err['code'] ?? '' ), 'adding them twice is refused' );
[ $status, $err ] = $call( 'POST', $base, array( 'user_id' => $candidate ) );
$ok( 400 === $status && 'wcb_owner_not_employer' === ( $err['code'] ?? '' ), 'a candidate account is refused with a reason' );
[ $status, $err ] = $call( 'POST', $base, array( 'user_id' => $creator ) );
$ok( 409 === $status, 'the creator cannot be added as their own co-owner' );

// --- The co-owner and a stranger --------------------------------------------
wp_set_current_user( $colleague );
[ $status ] = $call( 'GET', $base );
$ok( 200 === $status, 'O3: GET /wcb/v1/employers/{id}/owners - the co-owner sees who runs the company' );
[ $status ] = $call( 'POST', $base, array( 'user_id' => $stranger ) );
$ok( 403 === $status, 'the co-owner cannot add anyone' );
[ $status ] = $call( 'DELETE', "{$base}/{$colleague}" );
$ok( 403 === $status, 'the co-owner cannot remove anyone' );

wp_set_current_user( $stranger );
[ $status ] = $call( 'GET', $base );
$ok( 403 === $status, 'a stranger cannot see the list' );
[ $status ] = $call( 'GET', "{$base}/search", array( 'search' => 'Helen' ) );
$ok( 403 === $status, 'a stranger cannot search for co-owners' );

// --- WP-CLI -----------------------------------------------------------------
$cli = static fn ( string $args ): string => (string) WP_CLI::runcommand( "wcb company owners {$args} --user=1", array( 'return' => true, 'exit_error' => false ) );
$listed = json_decode( $cli( "list {$company} --format=json" ), true );
$ok( in_array( $colleague, array_map( 'intval', wp_list_pluck( (array) $listed, 'id' ) ), true ), 'O5: wp wcb company owners list shows the co-owner' );

// --- Employer dashboard -----------------------------------------------------
// A co-owner with no company of their own runs a company: no "Set up your company".
wp_set_current_user( $colleague );
render_block( array( 'blockName' => 'wp-career-board/employer-dashboard', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
$ok( false === ( wp_interactivity_state( 'wcb-employer-dashboard' )['noCompany'] ?? null ), 'a co-owner without a company of their own is not told to set one up' );

// --- Removal ----------------------------------------------------------------
wp_set_current_user( $creator );
[ $status ] = $call( 'DELETE', "{$base}/{$colleague}" );
$ok( 200 === $status && ! EmployersModule::owns_job( $colleague, get_post( $job ) ), 'O4: DELETE /wcb/v1/employers/{id}/owners/{user_id} - creator removes the co-owner, who no longer runs the job' );
[ $status ] = $call( 'DELETE', "{$base}/{$colleague}" );
$ok( 404 === $status, 'removing them again says they are already gone' );

wp_set_current_user( 0 );
wp_delete_post( $job, true );
wp_delete_post( $company, true );
foreach ( $made as $uid ) {
	wp_delete_user( $uid );
}

WP_CLI::log( "{$pass} passed, {$fail} failed" );
exit( $fail ? 1 : 0 );
