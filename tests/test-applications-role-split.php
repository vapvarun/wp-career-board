<?php
/**
 * Role-aware application response shape — F-3 in
 * plan/role-data-baseline-2026-05-07.md.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-applications-role-split.php
 *
 * Verifies /wcb/v1/applications/{id} returns three different shapes
 * depending on viewer role:
 *
 * - candidate (own application): no status_history.
 * - employer (job owner): status_history with reviewer redacted to
 *   "Hiring team", no reviewer_user_id.
 * - admin: status_history with reviewer_user_id preserved.
 *
 * Requires seed data (employers + candidates + jobs + applications) and
 * adds one synthetic status-log row to the application under test so we
 * can verify the redaction logic on a non-empty audit trail.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['wcb_role_test_pass'] = 0;
$GLOBALS['wcb_role_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_role_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		WP_CLI::log( "  PASS: {$label}" );
		++$GLOBALS['wcb_role_test_pass'];
	} else {
		WP_CLI::warning( "  FAIL: {$label}" );
		++$GLOBALS['wcb_role_test_fail'];
	}
}

/**
 * Dispatch an internal REST GET as a specific user.
 *
 * @param string $route   REST route path.
 * @param int    $user_id User ID to authenticate as.
 * @return array<string, mixed>
 */
function wcb_role_rest_get_as( string $route, int $user_id ): array {
	wp_set_current_user( $user_id );
	$response = rest_do_request( new WP_REST_Request( 'GET', $route ) );
	return is_array( $response->get_data() ) ? $response->get_data() : array();
}

rest_get_server();

WP_CLI::log( '' );
WP_CLI::log( '========================================' );
WP_CLI::log( '  Application Role-Split Tests (F-3)' );
WP_CLI::log( '========================================' );

$apps = get_posts(
	array(
		'post_type'      => 'wcb_application',
		'post_status'    => 'any',
		'posts_per_page' => 50,
		'fields'         => 'ids',
	)
);

if ( empty( $apps ) ) {
	WP_CLI::warning( 'No applications found — seed test data first.' );
	return;
}

$app_id       = 0;
$candidate_id = 0;
$employer_id  = 0;
$admin_id     = 1;

// Find an application with a real candidate AND a real employer so all
// three role views are exercised. Guest applications (candidate_id = 0)
// don't cover the candidate path.
foreach ( $apps as $candidate_app_id ) {
	$cid = (int) get_post_meta( (int) $candidate_app_id, '_wcb_candidate_id', true );
	$jid = (int) get_post_meta( (int) $candidate_app_id, '_wcb_job_id', true );
	$job = $jid ? get_post( $jid ) : null;
	$eid = $job instanceof WP_Post ? (int) $job->post_author : 0;
	if ( $cid > 0 && $eid > 0 ) {
		$app_id       = (int) $candidate_app_id;
		$candidate_id = $cid;
		$employer_id  = $eid;
		break;
	}
}

if ( $app_id <= 0 ) {
	WP_CLI::warning( 'No application found with both candidate + employer wiring.' );
	return;
}

// Seed a synthetic status-log row so the redaction logic is exercised.
update_post_meta(
	$app_id,
	'_wcb_status_log',
	array(
		array(
			'from' => 'submitted',
			'to'   => 'reviewing',
			'by'   => $employer_id,
			'at'   => gmdate( 'Y-m-d H:i:s' ),
		),
	)
);

WP_CLI::log( "  app_id={$app_id} candidate={$candidate_id} employer={$employer_id} admin={$admin_id}" );

$route = "/wcb/v1/applications/{$app_id}";

// ---------------------------------------------------------------------------
// Candidate view — own application.
// ---------------------------------------------------------------------------
WP_CLI::log( '' );
WP_CLI::log( '— Candidate view —' );
$candidate_data = wcb_role_rest_get_as( $route, $candidate_id );

wcb_role_assert(
	isset( $candidate_data['id'] ) && (int) $candidate_data['id'] === $app_id,
	'candidate sees own application id'
);
wcb_role_assert(
	! array_key_exists( 'status_history', $candidate_data ),
	'candidate does NOT see status_history (audit trail)'
);
wcb_role_assert(
	isset( $candidate_data['status'] ),
	'candidate sees current status'
);

// ---------------------------------------------------------------------------
// Employer view — job owner.
// ---------------------------------------------------------------------------
WP_CLI::log( '' );
WP_CLI::log( '— Employer view —' );
$employer_data = wcb_role_rest_get_as( $route, $employer_id );

wcb_role_assert(
	isset( $employer_data['status_history'] ) && is_array( $employer_data['status_history'] ),
	'employer sees status_history'
);

$first_employer_row = $employer_data['status_history'][0] ?? array();
wcb_role_assert(
	isset( $first_employer_row['reviewer'] ) && 'Hiring team' === $first_employer_row['reviewer'],
	'employer sees reviewer redacted to "Hiring team"'
);
wcb_role_assert(
	! array_key_exists( 'reviewer_user_id', $first_employer_row ),
	'employer does NOT see reviewer_user_id (raw user id)'
);

// ---------------------------------------------------------------------------
// Admin view — full audit trail.
// ---------------------------------------------------------------------------
WP_CLI::log( '' );
WP_CLI::log( '— Admin view —' );
$admin_data = wcb_role_rest_get_as( $route, $admin_id );

wcb_role_assert(
	isset( $admin_data['status_history'] ) && is_array( $admin_data['status_history'] ),
	'admin sees status_history'
);
$first_admin_row = $admin_data['status_history'][0] ?? array();
wcb_role_assert(
	isset( $first_admin_row['reviewer_user_id'] ) && (int) $first_admin_row['reviewer_user_id'] === $employer_id,
	'admin sees reviewer_user_id preserved'
);

// ---------------------------------------------------------------------------
// Summary.
// ---------------------------------------------------------------------------
WP_CLI::log( '' );
WP_CLI::log( '========================================' );
WP_CLI::log(
	sprintf(
		'  Results: %d passed, %d failed',
		$GLOBALS['wcb_role_test_pass'],
		$GLOBALS['wcb_role_test_fail']
	)
);
WP_CLI::log( '========================================' );

if ( $GLOBALS['wcb_role_test_fail'] > 0 ) {
	WP_CLI::error( 'Role-split tests failed.', false );
}
