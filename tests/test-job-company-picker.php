<?php
/**
 * Post a Job: an employer running several companies picks which one.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-job-company-picker.php
 *
 * Proves REST create and update take `company_id` for a company the employer
 * runs, refuse one they do not, fall back to the linked company, and that the
 * location dropdown offers every company's HQ. Creates its own users, companies
 * and jobs and deletes them.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
 * Dispatch an internal REST request as a user.
 *
 * @param string $method  HTTP method.
 * @param string $route   REST route path.
 * @param array  $params  Request parameters.
 * @param int    $user_id User to authenticate as.
 * @return WP_REST_Response
 */
function wcb_jcp_rest( string $method, string $route, array $params, int $user_id ): WP_REST_Response {
	wp_set_current_user( $user_id );
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $k => $v ) {
		$request->set_param( $k, $v );
	}
	return rest_do_request( $request );
}

rest_get_server();

WP_CLI::log( '' );
WP_CLI::log( '  Job Company Picker Tests' );

$wcb_free_board = 0;
foreach ( get_posts( array( 'post_type' => 'wcb_board', 'numberposts' => -1, 'fields' => 'ids' ) ) as $wcb_b ) {
	if ( 0 === (int) apply_filters( 'wcb_board_credit_cost', 0, (int) $wcb_b ) ) {
		$wcb_free_board = (int) $wcb_b;
		break;
	}
}

$wcb_suffix  = wp_generate_password( 6, false );
$wcb_user    = static fn ( string $login, string $role ): int => (int) wp_insert_user( array( 'user_login' => $login . '-' . $wcb_suffix, 'user_pass' => wp_generate_password(), 'user_email' => $login . '-' . $wcb_suffix . '@example.test', 'role' => $role ) );
$wcb_agency  = $wcb_user( 'jcp-recruiter', 'wcb_employer' );
$wcb_rival   = $wcb_user( 'jcp-rival', 'wcb_employer' );
$wcb_admin   = $wcb_user( 'jcp-admin', 'administrator' );
$wcb_company = static fn ( string $title, int $author ): int => (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => $title, 'post_author' => $author ) );
$wcb_co_a    = $wcb_company( 'Harbourline Logistics', $wcb_agency );
$wcb_co_b    = $wcb_company( 'Brightwater Health', $wcb_agency );
$wcb_co_c    = $wcb_company( 'Copperleaf Studios', $wcb_rival );
update_user_meta( $wcb_agency, '_wcb_company_id', $wcb_co_a );
$wcb_created = array( $wcb_co_a, $wcb_co_b, $wcb_co_c );

$wcb_hq = wp_insert_term( 'Leeds, UK ' . $wcb_suffix, 'wcb_location' );
wp_set_object_terms( $wcb_co_b, array( (int) $wcb_hq['term_id'] ), 'wcb_location' );

$wcb_job = static fn ( string $title, array $extra = array() ): array => array_merge(
	array(
		'title'       => $title,
		'description' => 'Join a small team caring for patients across three community clinics.',
		'board_id'    => $wcb_free_board,
	),
	$extra
);
$wcb_company_of = static fn ( int $id ): int => (int) get_post_meta( $id, '_wcb_company_id', true );

// 1. Pick a second company.
$wcb_r         = wcb_jcp_rest( 'POST', '/wcb/v1/jobs', $wcb_job( 'Community Nurse', array( 'company_id' => $wcb_co_b ) ), $wcb_agency );
$wcb_nurse     = (int) ( $wcb_r->get_data()['id'] ?? 0 );
$wcb_created[] = $wcb_nurse;
wcb_assert( $wcb_nurse > 0 && $wcb_co_b === $wcb_company_of( $wcb_nurse ), 'employer posts for their second company (got ' . $wcb_r->get_status() . ')' );
wcb_assert( 'Brightwater Health' === get_post_meta( $wcb_nurse, '_wcb_company_name', true ), 'the job carries that company name' );

// 2. No pick = linked company.
$wcb_r         = wcb_jcp_rest( 'POST', '/wcb/v1/jobs', $wcb_job( 'Warehouse Shift Supervisor' ), $wcb_agency );
$wcb_shift     = (int) ( $wcb_r->get_data()['id'] ?? 0 );
$wcb_created[] = $wcb_shift;
wcb_assert( $wcb_co_a === $wcb_company_of( $wcb_shift ), 'no company_id posts for the linked company' );

// 3. Someone else's company is refused.
$wcb_r = wcb_jcp_rest( 'POST', '/wcb/v1/jobs', $wcb_job( 'Motion Designer', array( 'company_id' => $wcb_co_c ) ), $wcb_agency );
wcb_assert( 403 === $wcb_r->get_status(), "posting for another employer's company is refused (got {$wcb_r->get_status()})" );
$wcb_r = wcb_jcp_rest( 'POST', '/wcb/v1/jobs', $wcb_job( 'Motion Designer', array( 'company_id' => $wcb_free_board ) ), $wcb_agency );
wcb_assert( 400 === $wcb_r->get_status(), 'a company_id that is not a company is refused (400)' );

// 4. Update: move between own companies, never to someone else's.
$wcb_r = wcb_jcp_rest( 'PATCH', '/wcb/v1/jobs/' . $wcb_shift, array( 'company_id' => $wcb_co_b ), $wcb_agency );
wcb_assert( 200 === $wcb_r->get_status() && $wcb_co_b === $wcb_company_of( $wcb_shift ), 'employer moves a job to their other company' );
$wcb_r = wcb_jcp_rest( 'PATCH', '/wcb/v1/jobs/' . $wcb_shift, array( 'company_id' => $wcb_co_c ), $wcb_agency );
wcb_assert( 403 === $wcb_r->get_status() && $wcb_co_b === $wcb_company_of( $wcb_shift ), "moving a job to another employer's company is refused" );

// 5. Admin may pick any company; the employer can then still save it unchanged.
$wcb_r         = wcb_jcp_rest( 'POST', '/wcb/v1/jobs', $wcb_job( 'Lead Animator', array( 'company_id' => $wcb_co_c ) ), $wcb_admin );
$wcb_anim      = (int) ( $wcb_r->get_data()['id'] ?? 0 );
$wcb_created[] = $wcb_anim;
wcb_assert( $wcb_co_c === $wcb_company_of( $wcb_anim ), 'admin posts for any company' );
$wcb_r = wcb_jcp_rest( 'PATCH', '/wcb/v1/jobs/' . $wcb_anim, array( 'title' => 'Lead 2D Animator', 'company_id' => $wcb_co_c ), $wcb_rival );
wcb_assert( 200 === $wcb_r->get_status(), "the company's owner saves the job keeping its company (got {$wcb_r->get_status()})" );

// 6. Locations: every company's HQ.
wp_set_current_user( $wcb_agency );
$wcb_slugs = wp_list_pluck( \WCB\Core\Locations::get_dropdown_terms( $wcb_agency ), 'term_id' );
wcb_assert( in_array( (int) $wcb_hq['term_id'], array_map( 'intval', $wcb_slugs ), true ), "the location list offers the second company's HQ" );

// Teardown.
foreach ( array_filter( $wcb_created ) as $wcb_id ) {
	wp_delete_post( (int) $wcb_id, true );
}
wp_delete_term( (int) $wcb_hq['term_id'], 'wcb_location' );
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( $wcb_agency, $wcb_rival, $wcb_admin ) as $wcb_u ) {
	wp_delete_user( (int) $wcb_u );
}

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All job company picker tests passed.' );
}
