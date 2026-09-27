<?php
/**
 * Candidate / resume visibility tests.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-candidate-visibility.php
 *
 * One rule decides who sees a candidate: CandidatesModule::resume_is_readable()
 * and has_applied_to(). These pin it, plus the core surfaces that used to leak
 * applicants (/wp/v2/users, author archives) and the /candidates/{id} contact
 * fields.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Modules\Candidates\CandidatesModule;

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

/**
 * Dispatch an internal REST request.
 *
 * @param string   $method  HTTP method.
 * @param string   $route   REST route path.
 * @param array    $params  Request parameters.
 * @param int|null $user_id User ID to set (null = leave unchanged, 0 = anonymous).
 * @return WP_REST_Response
 */
function wcb_rest( string $method, string $route, array $params = array(), ?int $user_id = null ): WP_REST_Response {
	if ( null !== $user_id ) {
		wp_set_current_user( $user_id );
	}
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $k => $v ) {
		$request->set_param( $k, $v );
	}
	return rest_do_request( $request );
}

rest_get_server();

WP_CLI::log( '' );
WP_CLI::log( '  Candidate Visibility Tests' );

$wcb_s     = wp_generate_password( 6, false );
$wcb_cand  = wp_insert_user( array( 'user_login' => 'cv-cand-' . $wcb_s, 'user_pass' => wp_generate_password(), 'user_email' => 'cv-cand-' . $wcb_s . '@example.test', 'role' => 'wcb_candidate' ) );
$wcb_emp   = wp_insert_user( array( 'user_login' => 'cv-emp-' . $wcb_s, 'user_pass' => wp_generate_password(), 'user_email' => 'cv-emp-' . $wcb_s . '@example.test', 'role' => 'wcb_employer' ) );
$wcb_other = wp_insert_user( array( 'user_login' => 'cv-oth-' . $wcb_s, 'user_pass' => wp_generate_password(), 'user_email' => 'cv-oth-' . $wcb_s . '@example.test', 'role' => 'wcb_employer' ) );
update_user_meta( $wcb_cand, '_wcb_resume_data', array( 'phone' => '+1-555-0100' ) );

$wcb_job = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'CV job ' . $wcb_s, 'post_author' => $wcb_emp ) );
$wcb_app = (int) wp_insert_post( array( 'post_type' => 'wcb_application', 'post_status' => 'publish', 'post_title' => 'CV app ' . $wcb_s, 'post_author' => $wcb_cand ) );
update_post_meta( $wcb_app, '_wcb_job_id', $wcb_job );
update_post_meta( $wcb_app, '_wcb_candidate_id', $wcb_cand );

WP_CLI::log( '--- has_applied_to ---' );
wcb_assert( CandidatesModule::has_applied_to( $wcb_cand, $wcb_emp ), 'true for the employer whose job was applied to' );
wcb_assert( ! CandidatesModule::has_applied_to( $wcb_cand, $wcb_other ), 'false for an unrelated employer' );

WP_CLI::log( '--- resume_is_readable (private resume) ---' );
if ( post_type_exists( 'wcb_resume' ) ) {
	$wcb_resume = (int) wp_insert_post( array( 'post_type' => 'wcb_resume', 'post_status' => 'publish', 'post_title' => 'CV resume ' . $wcb_s, 'post_author' => $wcb_cand ) );
	update_post_meta( $wcb_resume, '_wcb_resume_public', '0' );
	wp_set_current_user( $wcb_other );
	wcb_assert( ! CandidatesModule::resume_is_readable( $wcb_resume ), 'hidden from an unrelated employer' );
	wp_set_current_user( $wcb_emp );
	wcb_assert( CandidatesModule::resume_is_readable( $wcb_resume ), 'readable by the applied-to employer' );
	wp_set_current_user( 0 );
	wcb_assert( ! CandidatesModule::resume_is_readable( $wcb_resume ), 'hidden from guests' );
	wp_delete_post( $wcb_resume, true );
} else {
	WP_CLI::log( '  (skipped: wcb_resume is registered by Pro)' );
}

WP_CLI::log( '--- core surfaces ---' );
$wcb_users = wcb_rest( 'GET', '/wp/v2/users', array( 'per_page' => 100 ), 0 )->get_data();
wcb_assert( ! in_array( $wcb_cand, array_map( static fn ( $u ) => (int) $u['id'], (array) $wcb_users ), true ), 'anonymous /wp/v2/users does not list the applicant' );

WP_CLI::log( '--- GET /wcb/v1/candidates/{id} ---' );
$wcb_guest = wcb_rest( 'GET', "/wcb/v1/candidates/{$wcb_cand}", array(), 0 )->get_data();
wcb_assert( empty( $wcb_guest['resume_data'] ), 'guest gets no contact fields' );
$wcb_hired = wcb_rest( 'GET', "/wcb/v1/candidates/{$wcb_cand}", array(), $wcb_emp )->get_data();
wcb_assert( '+1-555-0100' === ( $wcb_hired['resume_data']['phone'] ?? '' ), 'applied-to employer gets contact fields' );
wcb_assert( 404 === wcb_rest( 'GET', "/wcb/v1/candidates/{$wcb_emp}", array(), 0 )->get_status(), 'a non-candidate id is 404' );
wp_set_current_user( 0 );

// Teardown.
wp_delete_post( $wcb_app, true );
wp_delete_post( $wcb_job, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( $wcb_cand, $wcb_emp, $wcb_other ) as $wcb_u ) {
	wp_delete_user( (int) $wcb_u );
}

WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All candidate-visibility tests passed.' );
}
