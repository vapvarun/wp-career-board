<?php
/**
 * Employer dashboard scope across many companies (agencies, recruiters).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-employer-multi-company.php
 *
 * One employer runs two companies: A (they created it) and B (created by
 * someone else, naming them in `_wcb_company_owner`). Proves that
 * GET /employers/me/jobs and /employers/me/applications cover both companies,
 * that the dashboard header counts equal what the lists return, that
 * `company_id` narrows to one owned company and is refused for anyone else's,
 * and that applicants on a company job the employer did not post are reachable.
 *
 * Self-contained: creates its own users, companies, jobs and applications and
 * deletes them at the end.
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
 * Dispatch an internal REST GET as a user.
 *
 * @param string $route   REST route path.
 * @param array  $params  Request parameters.
 * @param int    $user_id User to authenticate as.
 * @return WP_REST_Response
 */
function wcb_mc_get( string $route, array $params, int $user_id ): WP_REST_Response {
	wp_set_current_user( $user_id );
	$request = new WP_REST_Request( 'GET', $route );
	foreach ( $params as $k => $v ) {
		$request->set_param( $k, $v );
	}
	return rest_do_request( $request );
}

rest_get_server();

WP_CLI::log( '' );
WP_CLI::log( '  Employer Multi-Company Scope Tests' );

$wcb_suffix   = wp_generate_password( 6, false );
$wcb_employer = wp_insert_user( array( 'user_login' => 'mc-emp-' . $wcb_suffix, 'user_pass' => wp_generate_password(), 'user_email' => 'mc-emp-' . $wcb_suffix . '@example.test', 'role' => 'wcb_employer' ) );
$wcb_other    = wp_insert_user( array( 'user_login' => 'mc-other-' . $wcb_suffix, 'user_pass' => wp_generate_password(), 'user_email' => 'mc-other-' . $wcb_suffix . '@example.test', 'role' => 'wcb_employer' ) );
$wcb_cand     = wp_insert_user( array( 'user_login' => 'mc-cand-' . $wcb_suffix, 'user_pass' => wp_generate_password(), 'user_email' => 'mc-cand-' . $wcb_suffix . '@example.test', 'role' => 'wcb_candidate' ) );
$wcb_staff    = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 1 );

$wcb_company = static fn ( string $title, int $author ): int => (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => $title, 'post_author' => $author ) );
$wcb_co_a    = $wcb_company( 'MC Alpha ' . $wcb_suffix, $wcb_employer );
$wcb_co_b    = $wcb_company( 'MC Beta ' . $wcb_suffix, $wcb_staff );
$wcb_co_c    = $wcb_company( 'MC Other ' . $wcb_suffix, $wcb_other );
update_post_meta( $wcb_co_b, '_wcb_company_owner', $wcb_employer );
update_user_meta( $wcb_employer, '_wcb_company_id', $wcb_co_a );
update_user_meta( $wcb_other, '_wcb_company_id', $wcb_co_c );

$wcb_job = static function ( string $title, int $author, int $company ): int {
	$id = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => $title, 'post_author' => $author ) );
	update_post_meta( $id, '_wcb_company_id', $company );
	return $id;
};
$wcb_app = static function ( int $job_id ) use ( $wcb_cand ): int {
	$id = (int) wp_insert_post( array( 'post_type' => 'wcb_application', 'post_status' => 'publish', 'post_title' => 'MC app', 'post_author' => $wcb_cand ) );
	update_post_meta( $id, '_wcb_job_id', $job_id );
	update_post_meta( $id, '_wcb_candidate_id', $wcb_cand );
	return $id;
};

$wcb_job_a  = $wcb_job( 'MC job A', $wcb_employer, $wcb_co_a );
$wcb_job_b1 = $wcb_job( 'MC job B1', $wcb_employer, $wcb_co_b );
$wcb_job_b2 = $wcb_job( 'MC job B2 (posted by staff)', $wcb_staff, $wcb_co_b );
$wcb_job_b3 = $wcb_job( 'MC job B3 (no applicants)', $wcb_employer, $wcb_co_b );
$wcb_job_c  = $wcb_job( 'MC job C (someone else)', $wcb_other, $wcb_co_c );
$wcb_apps   = array( $wcb_app( $wcb_job_a ), $wcb_app( $wcb_job_b1 ), $wcb_app( $wcb_job_b1 ), $wcb_app( $wcb_job_b2 ), $wcb_app( $wcb_job_c ) );

$wcb_mine = array( $wcb_job_a, $wcb_job_b1, $wcb_job_b2, $wcb_job_b3 );

// All companies.
$wcb_jobs_r = wcb_mc_get( '/wcb/v1/employers/me/jobs', array( 'per_page' => 50 ), $wcb_employer );
$wcb_jobs   = (array) ( $wcb_jobs_r->get_data()['jobs'] ?? array() );
$wcb_ids    = array_map( 'intval', wp_list_pluck( $wcb_jobs, 'id' ) );
sort( $wcb_ids );
wcb_assert( $wcb_ids === $wcb_mine, '/me/jobs lists the jobs of BOTH companies (got ' . wp_json_encode( $wcb_ids ) . ')' );
wcb_assert( 4 === (int) ( $wcb_jobs_r->get_data()['total'] ?? -1 ), '/me/jobs total is 4' );
wcb_assert( ! in_array( $wcb_job_c, $wcb_ids, true ), "/me/jobs never lists another employer's job" );

$wcb_apps_r = wcb_mc_get( '/wcb/v1/employers/me/applications', array(), $wcb_employer );
wcb_assert( 4 === (int) ( $wcb_apps_r->get_data()['total'] ?? -1 ), '/me/applications covers both companies (4)' );

// Header counts equal the lists.
if ( method_exists( '\WCB\Modules\Employers\EmployersModule', 'dashboard_counts' ) ) {
	$wcb_counts = \WCB\Modules\Employers\EmployersModule::dashboard_counts( $wcb_employer );
	$wcb_tiles  = count( array_filter( $wcb_jobs, static fn ( $j ) => (int) ( $j['appCount'] ?? 0 ) > 0 ) );
	wcb_assert( $wcb_counts['total_jobs'] === count( $wcb_jobs ), 'total jobs count == My Jobs tiles' );
	wcb_assert( array_sum( $wcb_counts['jobs_with_apps'] ) === $wcb_tiles && 3 === $wcb_tiles, 'jobs-with-applications count == job selector tiles (3)' );
	wcb_assert( $wcb_counts['total_apps'] === (int) $wcb_apps_r->get_data()['total'], 'total applications count == applications list total' );
	wcb_assert( 2 === ( $wcb_counts['jobs_with_apps'][ $wcb_co_b ] ?? 0 ), 'per-company jobs-with-applications for B is 2' );
} else {
	wcb_assert( false, 'EmployersModule::dashboard_counts() exists (one scope for the header numbers)' );
}

// Company filter.
$wcb_b_jobs = wcb_mc_get( '/wcb/v1/employers/me/jobs', array( 'company_id' => $wcb_co_b ), $wcb_employer );
wcb_assert( 200 === $wcb_b_jobs->get_status() && 3 === (int) ( $wcb_b_jobs->get_data()['total'] ?? -1 ), 'company_id=B narrows /me/jobs to B (3)' );
$wcb_b_apps = wcb_mc_get( '/wcb/v1/employers/me/applications', array( 'company_id' => $wcb_co_b ), $wcb_employer );
wcb_assert( 200 === $wcb_b_apps->get_status() && 3 === (int) ( $wcb_b_apps->get_data()['total'] ?? -1 ), 'company_id=B narrows /me/applications to B (3)' );
wcb_assert( 403 === wcb_mc_get( '/wcb/v1/employers/me/jobs', array( 'company_id' => $wcb_co_c ), $wcb_employer )->get_status(), "company_id of someone else's company is refused on /me/jobs (403)" );
wcb_assert( 403 === wcb_mc_get( '/wcb/v1/employers/me/applications', array( 'company_id' => $wcb_co_c ), $wcb_employer )->get_status(), "company_id of someone else's company is refused on /me/applications (403)" );
wcb_assert( 403 === wcb_mc_get( '/wcb/v1/employers/me/applications', array( 'company_id' => $wcb_co_a ), $wcb_other )->get_status(), 'the other employer cannot filter by company A (403)' );

// Applicants on a company job the employer did not post are reachable, and nobody else's are.
wcb_assert( 200 === wcb_mc_get( '/wcb/v1/jobs/' . $wcb_job_b2 . '/applications', array(), $wcb_employer )->get_status(), "applicants on company B's staff-posted job are reachable" );
wcb_assert( 403 === wcb_mc_get( '/wcb/v1/jobs/' . $wcb_job_b2 . '/applications', array(), $wcb_other )->get_status(), "another employer cannot read company B's applicants" );
wcb_assert( 403 === wcb_mc_get( '/wcb/v1/jobs/' . $wcb_job_c . '/applications', array(), $wcb_employer )->get_status(), "the employer cannot read another company's applicants" );

// Teardown.
foreach ( array_merge( $wcb_apps, $wcb_mine, array( $wcb_job_c, $wcb_co_a, $wcb_co_b, $wcb_co_c ) ) as $wcb_id ) {
	wp_delete_post( (int) $wcb_id, true );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( $wcb_employer, $wcb_other, $wcb_cand ) as $wcb_user ) {
	wp_delete_user( (int) $wcb_user );
}

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All employer multi-company tests passed.' );
}
