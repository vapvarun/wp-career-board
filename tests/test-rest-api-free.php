<?php
/**
 * REST API tests for the Free plugin (wp-career-board).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-rest-api-free.php
 *
 * Uses internal rest_do_request() dispatching -- no HTTP needed.
 * Requires seed data (3 employers, 5 candidates, 5 companies, 17 jobs, 13 applications).
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
		WP_CLI::log( "  PASS: {$label}" );
		++$GLOBALS['wcb_test_pass'];
	} else {
		WP_CLI::warning( "  FAIL: {$label}" );
		++$GLOBALS['wcb_test_fail'];
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
function wcb_rest( string $method, string $route, array $params = [], ?int $user_id = null ): WP_REST_Response {
	if ( null !== $user_id ) {
		wp_set_current_user( $user_id );
	}
	$request = new WP_REST_Request( $method, $route );
	if ( 'GET' === $method ) {
		foreach ( $params as $k => $v ) {
			$request->set_param( $k, $v );
		}
	} else {
		$request->set_body_params( $params );
	}
	return rest_do_request( $request );
}

// Ensure REST server is initialized.
rest_get_server();

WP_CLI::log( '' );
WP_CLI::log( '========================================' );
WP_CLI::log( '  REST API Tests (Free)' );
WP_CLI::log( '========================================' );
WP_CLI::log( '' );

// ---------------------------------------------------------------------------
// Look up seed data IDs.
// ---------------------------------------------------------------------------

$admin_id = 1;

$candidate_users = get_users( array( 'role' => 'wcb_candidate', 'number' => 2 ) );
$candidate_id    = ! empty( $candidate_users ) ? (int) $candidate_users[0]->ID : 0;
$candidate_id_2  = count( $candidate_users ) >= 2 ? (int) $candidate_users[1]->ID : 0;

$employer_users = get_users( array( 'role' => 'wcb_employer', 'number' => 1 ) );
$employer_id    = ! empty( $employer_users ) ? (int) $employer_users[0]->ID : 0;

$company_posts = get_posts( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) );
$company_id    = ! empty( $company_posts ) ? (int) $company_posts[0] : 0;

$job_posts = get_posts( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) );
$job_id    = ! empty( $job_posts ) ? (int) $job_posts[0] : 0;

$pending_jobs   = get_posts( array( 'post_type' => 'wcb_job', 'post_status' => 'pending', 'numberposts' => 1, 'fields' => 'ids' ) );
$pending_job_id = ! empty( $pending_jobs ) ? (int) $pending_jobs[0] : 0;

$app_posts = get_posts( array( 'post_type' => 'wcb_application', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
$app_id    = ! empty( $app_posts ) ? (int) $app_posts[0] : 0;

WP_CLI::log( "Seed IDs => admin:{$admin_id} candidate:{$candidate_id} employer:{$employer_id} company:{$company_id} job:{$job_id} pending_job:{$pending_job_id} app:{$app_id}" );
WP_CLI::log( '' );

// =========================================================================
// JOBS ENDPOINT — /wcb/v1/jobs
// =========================================================================

WP_CLI::log( '--- Jobs: GET /wcb/v1/jobs (public) ---' );
$r = wcb_rest( 'GET', '/wcb/v1/jobs', array(), 0 );
wcb_assert( 200 === $r->get_status(), 'GET /jobs returns 200 for anonymous' );
wcb_assert( is_array( $r->get_data() ), 'response is an array' );
wcb_assert( count( $r->get_data() ) > 0, 'at least one job returned' );

WP_CLI::log( '--- Jobs: GET /wcb/v1/jobs (with filters) ---' );
$r = wcb_rest( 'GET', '/wcb/v1/jobs', array( 'per_page' => 5, 'page' => 1 ), 0 );
wcb_assert( 200 === $r->get_status(), 'GET /jobs with per_page returns 200' );
wcb_assert( count( $r->get_data() ) <= 5, 'respects per_page limit' );

WP_CLI::log( '--- Jobs: POST /wcb/v1/jobs (anon = 401/403) ---' );
$r = wcb_rest( 'POST', '/wcb/v1/jobs', array( 'title' => 'Test Job' ), 0 );
wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'POST /jobs anon returns 401 or 403' );

WP_CLI::log( '--- Jobs: POST /wcb/v1/jobs (employer) ---' );
$r = wcb_rest( 'POST', '/wcb/v1/jobs', array( 'title' => '__wcb_test_job__', 'description' => 'Test job for REST suite' ), $employer_id );
$create_status = $r->get_status();
wcb_assert( in_array( $create_status, array( 200, 201 ), true ), 'POST /jobs as employer returns 200 or 201' );
$test_job_id = $r->get_data()['id'] ?? 0;
// Cleanup: remove the test job.
if ( $test_job_id ) {
	wp_delete_post( $test_job_id, true );
}

// ---------------------------------------------------------------------------
// Jobs: GET /wcb/v1/jobs/{id} (single)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Jobs: GET /wcb/v1/jobs/{id} ---' );
if ( $job_id ) {
	$r = wcb_rest( 'GET', "/wcb/v1/jobs/{$job_id}", array(), 0 );
	wcb_assert( 200 === $r->get_status(), 'GET /jobs/{id} returns 200 for anonymous' );
	wcb_assert( (int) ( $r->get_data()['id'] ?? 0 ) === $job_id, 'returned job has correct ID' );
}

// ---------------------------------------------------------------------------
// Jobs: PUT /wcb/v1/jobs/{id} (auth gate)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Jobs: PUT /wcb/v1/jobs/{id} (anon) ---' );
if ( $job_id ) {
	$r = wcb_rest( 'PUT', "/wcb/v1/jobs/{$job_id}", array( 'title' => 'Modified' ), 0 );
	wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'PUT /jobs/{id} anon returns 401 or 403' );
}

WP_CLI::log( '--- Jobs: PUT /wcb/v1/jobs/{id} (admin) ---' );
if ( $job_id ) {
	$original_title = get_the_title( $job_id );
	$r              = wcb_rest( 'PUT', "/wcb/v1/jobs/{$job_id}", array( 'title' => '__wcb_tmp_title__' ), $admin_id );
	wcb_assert( 200 === $r->get_status(), 'PUT /jobs/{id} as admin returns 200' );
	// Restore.
	wp_update_post( array( 'ID' => $job_id, 'post_title' => $original_title ) );
}

// ---------------------------------------------------------------------------
// Jobs: DELETE /wcb/v1/jobs/{id} (auth gate)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Jobs: DELETE /wcb/v1/jobs/{id} (anon) ---' );
if ( $job_id ) {
	$r = wcb_rest( 'DELETE', "/wcb/v1/jobs/{$job_id}", array(), 0 );
	wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'DELETE /jobs/{id} anon returns 401 or 403' );
}

// ---------------------------------------------------------------------------
// Jobs: POST /wcb/v1/jobs/{id}/bookmark (auth gate)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Jobs: POST /wcb/v1/jobs/{id}/bookmark ---' );
if ( $job_id ) {
	$r = wcb_rest( 'POST', "/wcb/v1/jobs/{$job_id}/bookmark", array(), 0 );
	wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'POST /jobs/{id}/bookmark anon returns 401 or 403' );

	$r = wcb_rest( 'POST', "/wcb/v1/jobs/{$job_id}/bookmark", array(), $candidate_id );
	wcb_assert( 200 === $r->get_status(), 'POST /jobs/{id}/bookmark as candidate returns 200' );
	// Toggle back to remove bookmark.
	wcb_rest( 'POST', "/wcb/v1/jobs/{$job_id}/bookmark", array(), $candidate_id );
}

// ---------------------------------------------------------------------------
// Jobs: GET /wcb/v1/jobs/{id}/applications (auth gate)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Jobs: GET /wcb/v1/jobs/{id}/applications ---' );
if ( $job_id ) {
	$r = wcb_rest( 'GET', "/wcb/v1/jobs/{$job_id}/applications", array(), 0 );
	wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'GET /jobs/{id}/applications anon returns 401 or 403' );

	$r = wcb_rest( 'GET', "/wcb/v1/jobs/{$job_id}/applications", array(), $admin_id );
	wcb_assert( 200 === $r->get_status(), 'GET /jobs/{id}/applications as admin returns 200' );
}

// =========================================================================
// APPLICATIONS ENDPOINT
// =========================================================================

// ---------------------------------------------------------------------------
// POST /wcb/v1/jobs/{id}/apply (guest submission allowed)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Applications: POST /wcb/v1/jobs/{id}/apply (guest) ---' );
if ( $job_id ) {
	$unique_email = 'wcb_test_' . wp_rand( 1000, 9999 ) . '@example.com';
	$r            = wcb_rest( 'POST', "/wcb/v1/jobs/{$job_id}/apply", array(
		'guest_name'  => 'Test Guest',
		'guest_email' => $unique_email,
	), 0 );
	wcb_assert( in_array( $r->get_status(), array( 200, 201 ), true ), 'POST /jobs/{id}/apply as guest returns 200/201' );
	$test_app_id = $r->get_data()['id'] ?? 0;
	if ( $test_app_id ) {
		wp_delete_post( $test_app_id, true );
	}
}

// ---------------------------------------------------------------------------
// GET /wcb/v1/applications/{id} (auth gate)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Applications: GET /wcb/v1/applications/{id} ---' );
if ( $app_id ) {
	$r = wcb_rest( 'GET', "/wcb/v1/applications/{$app_id}", array(), 0 );
	wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'GET /applications/{id} anon returns 401 or 403' );

	$r = wcb_rest( 'GET', "/wcb/v1/applications/{$app_id}", array(), $admin_id );
	wcb_assert( 200 === $r->get_status(), 'GET /applications/{id} as admin returns 200' );
}

// ---------------------------------------------------------------------------
// PUT /wcb/v1/applications/{id}/status (auth gate + success)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Applications: PUT /wcb/v1/applications/{id}/status ---' );
if ( $app_id ) {
	$r = wcb_rest( 'PUT', "/wcb/v1/applications/{$app_id}/status", array( 'status' => 'reviewing' ), 0 );
	wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'PUT /applications/{id}/status anon returns 401 or 403' );

	$original_app_status = get_post_meta( $app_id, '_wcb_status', true );
	$r                   = wcb_rest( 'PUT', "/wcb/v1/applications/{$app_id}/status", array( 'status' => 'reviewing' ), $admin_id );
	wcb_assert( 200 === $r->get_status(), 'PUT /applications/{id}/status as admin returns 200' );
	// Restore.
	update_post_meta( $app_id, '_wcb_status', $original_app_status );
}

// ---------------------------------------------------------------------------
// DELETE /wcb/v1/applications/{id} (withdraw, auth gate)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Applications: DELETE /wcb/v1/applications/{id} (anon) ---' );
if ( $app_id ) {
	$r = wcb_rest( 'DELETE', "/wcb/v1/applications/{$app_id}", array(), 0 );
	wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'DELETE /applications/{id} anon returns 401 or 403' );
}

// ---------------------------------------------------------------------------
// GET /wcb/v1/candidates/{id}/applications (auth gate + success)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Applications: GET /wcb/v1/candidates/{id}/applications ---' );
if ( $candidate_id ) {
	$r = wcb_rest( 'GET', "/wcb/v1/candidates/{$candidate_id}/applications", array(), 0 );
	wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'GET /candidates/{id}/applications anon returns 401 or 403' );

	$r = wcb_rest( 'GET', "/wcb/v1/candidates/{$candidate_id}/applications", array(), $candidate_id );
	wcb_assert( 200 === $r->get_status(), 'GET /candidates/{id}/applications as self returns 200' );
}

// ---------------------------------------------------------------------------
// POST /wcb/v1/candidates/resume-upload (auth gate only)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Applications: POST /wcb/v1/candidates/resume-upload (anon) ---' );
$r = wcb_rest( 'POST', '/wcb/v1/candidates/resume-upload', array(), 0 );
wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'POST /candidates/resume-upload anon returns 401 or 403' );

// =========================================================================
// CANDIDATES ENDPOINT
// =========================================================================

// ---------------------------------------------------------------------------
// GET /wcb/v1/candidates/{id} (public)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Candidates: GET /wcb/v1/candidates/{id} ---' );
if ( $candidate_id ) {
	$r = wcb_rest( 'GET', "/wcb/v1/candidates/{$candidate_id}", array(), 0 );
	wcb_assert( 200 === $r->get_status(), 'GET /candidates/{id} returns 200 for anonymous' );
	wcb_assert( (int) ( $r->get_data()['id'] ?? 0 ) === $candidate_id, 'correct candidate ID returned' );
}

// ---------------------------------------------------------------------------
// PUT /wcb/v1/candidates/{id} (auth gate + success)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Candidates: PUT /wcb/v1/candidates/{id} (anon) ---' );
if ( $candidate_id ) {
	$r = wcb_rest( 'PUT', "/wcb/v1/candidates/{$candidate_id}", array( 'bio' => 'test' ), 0 );
	wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'PUT /candidates/{id} anon returns 401 or 403' );

	$original_bio = get_user_by( 'ID', $candidate_id )->description;
	$r            = wcb_rest( 'PUT', "/wcb/v1/candidates/{$candidate_id}", array( 'bio' => '__wcb_test_bio__' ), $candidate_id );
	wcb_assert( 200 === $r->get_status(), 'PUT /candidates/{id} as self returns 200' );
	// Restore.
	wp_update_user( array( 'ID' => $candidate_id, 'description' => $original_bio ) );
}

// ---------------------------------------------------------------------------
// GET /wcb/v1/candidates/{id}/bookmarks (auth gate + success)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Candidates: GET /wcb/v1/candidates/{id}/bookmarks ---' );
if ( $candidate_id ) {
	$r = wcb_rest( 'GET', "/wcb/v1/candidates/{$candidate_id}/bookmarks", array(), 0 );
	wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'GET /candidates/{id}/bookmarks anon returns 401 or 403' );

	$r = wcb_rest( 'GET', "/wcb/v1/candidates/{$candidate_id}/bookmarks", array(), $candidate_id );
	wcb_assert( 200 === $r->get_status(), 'GET /candidates/{id}/bookmarks as self returns 200' );
}

// ---------------------------------------------------------------------------
// POST /wcb/v1/candidates/register (public)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Candidates: POST /wcb/v1/candidates/register ---' );
$test_email = 'wcb_test_candidate_' . wp_rand( 1000, 9999 ) . '@example.com';
$r          = wcb_rest( 'POST', '/wcb/v1/candidates/register', array(
	'first_name' => 'WCBTest',
	'last_name'  => 'Candidate',
	'email'      => $test_email,
	'password'   => 'SecurePass123!',
), 0 );
$reg_status = $r->get_status();
// May return 200/201 if registration enabled, or 403 if disabled.
wcb_assert( in_array( $reg_status, array( 200, 201, 403 ), true ), 'POST /candidates/register returns 200/201 or 403 (registration disabled)' );
// Cleanup test user.
$test_user = get_user_by( 'email', $test_email );
if ( $test_user ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $test_user->ID );
}

// =========================================================================
// COMPANIES ENDPOINT
// =========================================================================

// ---------------------------------------------------------------------------
// GET /wcb/v1/companies (public)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Companies: GET /wcb/v1/companies ---' );
$r = wcb_rest( 'GET', '/wcb/v1/companies', array(), 0 );
wcb_assert( 200 === $r->get_status(), 'GET /companies returns 200 for anonymous' );
wcb_assert( is_array( $r->get_data() ), 'response is an array' );

// ---------------------------------------------------------------------------
// POST /wcb/v1/companies/{id}/trust (auth gate + success)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Companies: POST /wcb/v1/companies/{id}/trust ---' );
if ( $company_id ) {
	$r = wcb_rest( 'POST', "/wcb/v1/companies/{$company_id}/trust", array( 'trust_level' => 'verified' ), 0 );
	wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'POST /companies/{id}/trust anon returns 401 or 403' );

	$original_trust = get_post_meta( $company_id, '_wcb_trust_level', true );
	$r              = wcb_rest( 'POST', "/wcb/v1/companies/{$company_id}/trust", array( 'trust_level' => 'verified' ), $admin_id );
	wcb_assert( 200 === $r->get_status(), 'POST /companies/{id}/trust as admin returns 200' );
	// Restore.
	if ( $original_trust ) {
		update_post_meta( $company_id, '_wcb_trust_level', $original_trust );
	} else {
		delete_post_meta( $company_id, '_wcb_trust_level' );
	}
}

// =========================================================================
// EMPLOYERS ENDPOINT
// =========================================================================

// ---------------------------------------------------------------------------
// POST /wcb/v1/employers/register (public)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Employers: POST /wcb/v1/employers/register ---' );
$test_emp_email = 'wcb_test_emp_' . wp_rand( 1000, 9999 ) . '@example.com';
$r              = wcb_rest( 'POST', '/wcb/v1/employers/register', array(
	'first_name'   => 'WCBTest',
	'last_name'    => 'Employer',
	'email'        => $test_emp_email,
	'company_name' => 'WCB Test Corp',
	'password'     => 'SecurePass123!',
), 0 );
$reg_emp_status = $r->get_status();
wcb_assert( in_array( $reg_emp_status, array( 200, 201, 403 ), true ), 'POST /employers/register returns 200/201 or 403' );
// Cleanup.
$test_emp_user = get_user_by( 'email', $test_emp_email );
if ( $test_emp_user ) {
	$test_company = (int) get_user_meta( $test_emp_user->ID, '_wcb_company_id', true );
	if ( $test_company ) {
		wp_delete_post( $test_company, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $test_emp_user->ID );
}

// ---------------------------------------------------------------------------
// POST /wcb/v1/employers (create company, auth gate)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Employers: POST /wcb/v1/employers (anon) ---' );
$r = wcb_rest( 'POST', '/wcb/v1/employers', array( 'name' => 'Test Co' ), 0 );
wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'POST /employers anon returns 401 or 403' );

// ---------------------------------------------------------------------------
// GET /wcb/v1/employers/{id} (public)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Employers: GET /wcb/v1/employers/{id} ---' );
if ( $company_id ) {
	$r = wcb_rest( 'GET', "/wcb/v1/employers/{$company_id}", array(), 0 );
	wcb_assert( 200 === $r->get_status(), 'GET /employers/{id} returns 200 for anonymous' );
}

// ---------------------------------------------------------------------------
// PUT /wcb/v1/employers/{id} (auth gate)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Employers: PUT /wcb/v1/employers/{id} (anon) ---' );
if ( $company_id ) {
	$r = wcb_rest( 'PUT', "/wcb/v1/employers/{$company_id}", array( 'name' => 'Modified' ), 0 );
	wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'PUT /employers/{id} anon returns 401 or 403' );
}

// ---------------------------------------------------------------------------
// GET /wcb/v1/employers/{id}/jobs (public)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Employers: GET /wcb/v1/employers/{id}/jobs ---' );
if ( $company_id ) {
	$r = wcb_rest( 'GET', "/wcb/v1/employers/{$company_id}/jobs", array(), 0 );
	wcb_assert( 200 === $r->get_status(), 'GET /employers/{id}/jobs returns 200 for anonymous' );
}

// ---------------------------------------------------------------------------
// GET /wcb/v1/employers/{id}/applications (auth gate)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Employers: GET /wcb/v1/employers/{id}/applications ---' );
if ( $company_id ) {
	$r = wcb_rest( 'GET', "/wcb/v1/employers/{$company_id}/applications", array(), 0 );
	wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'GET /employers/{id}/applications anon returns 401 or 403' );

	$r = wcb_rest( 'GET', "/wcb/v1/employers/{$company_id}/applications", array(), $admin_id );
	wcb_assert( 200 === $r->get_status(), 'GET /employers/{id}/applications as admin returns 200' );
}

// ---------------------------------------------------------------------------
// POST /wcb/v1/employers/{id}/logo (auth gate only — file upload)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Employers: POST /wcb/v1/employers/{id}/logo (anon) ---' );
if ( $company_id ) {
	$r = wcb_rest( 'POST', "/wcb/v1/employers/{$company_id}/logo", array(), 0 );
	wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'POST /employers/{id}/logo anon returns 401 or 403' );
}

// ---------------------------------------------------------------------------
// GET /wcb/v1/employers/me/jobs (auth gate)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Employers: GET /wcb/v1/employers/me/jobs ---' );
$r = wcb_rest( 'GET', '/wcb/v1/employers/me/jobs', array(), 0 );
wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'GET /employers/me/jobs anon returns 401 or 403' );

if ( $employer_id ) {
	$r = wcb_rest( 'GET', '/wcb/v1/employers/me/jobs', array(), $employer_id );
	wcb_assert( 200 === $r->get_status(), 'GET /employers/me/jobs as employer returns 200' );
}

// =========================================================================
// SEARCH ENDPOINT
// =========================================================================

WP_CLI::log( '--- Search: GET /wcb/v1/search ---' );
$r = wcb_rest( 'GET', '/wcb/v1/search', array( 'search' => 'engineer' ), 0 );
wcb_assert( 200 === $r->get_status(), 'GET /search returns 200 for anonymous' );
wcb_assert( is_array( $r->get_data() ), 'search response is an array' );

// =========================================================================
// IMPORT ENDPOINT
// =========================================================================

// ---------------------------------------------------------------------------
// GET /wcb/v1/import/status (auth gate + success)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Import: GET /wcb/v1/import/status ---' );
$r = wcb_rest( 'GET', '/wcb/v1/import/status', array(), 0 );
wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'GET /import/status anon returns 401 or 403' );

$r = wcb_rest( 'GET', '/wcb/v1/import/status', array(), $admin_id );
wcb_assert( 200 === $r->get_status(), 'GET /import/status as admin returns 200' );

// ---------------------------------------------------------------------------
// POST /wcb/v1/import/run (auth gate)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Import: POST /wcb/v1/import/run (anon) ---' );
$r = wcb_rest( 'POST', '/wcb/v1/import/run', array( 'type' => 'wpjm-jobs', 'offset' => 0, 'limit' => 1 ), 0 );
wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'POST /import/run anon returns 401 or 403' );

// =========================================================================
// MODERATION ENDPOINT
// =========================================================================

// ---------------------------------------------------------------------------
// POST /wcb/v1/jobs/{id}/approve (auth gate + success)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Moderation: POST /wcb/v1/jobs/{id}/approve ---' );
if ( $pending_job_id ) {
	$r = wcb_rest( 'POST', "/wcb/v1/jobs/{$pending_job_id}/approve", array(), 0 );
	wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'POST /jobs/{id}/approve anon returns 401 or 403' );

	$original_status = get_post_status( $pending_job_id );
	$r               = wcb_rest( 'POST', "/wcb/v1/jobs/{$pending_job_id}/approve", array(), $admin_id );
	wcb_assert( 200 === $r->get_status(), 'POST /jobs/{id}/approve as admin returns 200' );
	wcb_assert( 'publish' === ( $r->get_data()['status'] ?? '' ), 'approved job status is publish' );
	// Restore.
	wp_update_post( array( 'ID' => $pending_job_id, 'post_status' => $original_status ) );
} else {
	WP_CLI::warning( '  SKIP: no pending job for moderation test' );
}

// ---------------------------------------------------------------------------
// POST /wcb/v1/jobs/{id}/reject (auth gate + success)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Moderation: POST /wcb/v1/jobs/{id}/reject ---' );
if ( $job_id ) {
	$r = wcb_rest( 'POST', "/wcb/v1/jobs/{$job_id}/reject", array( 'reason' => 'test' ), 0 );
	wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'POST /jobs/{id}/reject anon returns 401 or 403' );

	$original_status = get_post_status( $job_id );
	$r               = wcb_rest( 'POST', "/wcb/v1/jobs/{$job_id}/reject", array( 'reason' => 'REST test rejection' ), $admin_id );
	wcb_assert( 200 === $r->get_status(), 'POST /jobs/{id}/reject as admin returns 200' );
	wcb_assert( 'draft' === ( $r->get_data()['status'] ?? '' ), 'rejected job status is draft' );
	// Restore.
	wp_update_post( array( 'ID' => $job_id, 'post_status' => $original_status ) );
	delete_post_meta( $job_id, '_wcb_rejection_reason' );
}

// =========================================================================
// WIZARD ENDPOINT
// =========================================================================

// ---------------------------------------------------------------------------
// POST /wcb/v1/wizard/create-pages (auth gate)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Wizard: POST /wcb/v1/wizard/create-pages (anon) ---' );
$r = wcb_rest( 'POST', '/wcb/v1/wizard/create-pages', array(), 0 );
wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'POST /wizard/create-pages anon returns 401 or 403' );

WP_CLI::log( '--- Wizard: POST /wcb/v1/wizard/create-pages (admin) ---' );
$r = wcb_rest( 'POST', '/wcb/v1/wizard/create-pages', array(), $admin_id );
wcb_assert( 200 === $r->get_status(), 'POST /wizard/create-pages as admin returns 200' );

// ---------------------------------------------------------------------------
// POST /wcb/v1/wizard/sample-data (auth gate)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Wizard: POST /wcb/v1/wizard/sample-data (anon) ---' );
$r = wcb_rest( 'POST', '/wcb/v1/wizard/sample-data', array( 'install_sample' => 0 ), 0 );
wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'POST /wizard/sample-data anon returns 401 or 403' );

// ---------------------------------------------------------------------------
// POST /wcb/v1/wizard/complete (auth gate)
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Wizard: POST /wcb/v1/wizard/complete (anon) ---' );
$r = wcb_rest( 'POST', '/wcb/v1/wizard/complete', array(), 0 );
wcb_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'POST /wizard/complete anon returns 401 or 403' );

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '========================================' );
WP_CLI::log( '  Free REST API Test Summary' );
WP_CLI::log( '========================================' );
WP_CLI::log( "  Total: " . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) );
WP_CLI::log( "  Pass:  " . $GLOBALS['wcb_test_pass'] );
WP_CLI::log( "  Fail:  " . $GLOBALS['wcb_test_fail'] );
WP_CLI::log( '' );

if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All Free REST API tests passed.' );
}
