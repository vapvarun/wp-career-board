<?php
/**
 * WP-CLI command smoke tests.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-cli-commands.php
 *
 * Requires seed data to exist:
 *   wp eval-file wp-content/plugins/wp-career-board/tests/fixtures/seed-data.php
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
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
 * Run a WP-CLI command and capture output.
 *
 * @param string $cmd          Command string (without leading "wp").
 * @param bool   $expect_error Whether an error exit code is expected.
 * @return array{stdout: string, stderr: string, code: int}
 */
function wcb_run( string $cmd, bool $expect_error = false ): array {
	$result = WP_CLI::runcommand( $cmd, array( 'return' => 'all', 'exit_error' => false ) );
	return array(
		'stdout' => $result->stdout ?? '',
		'stderr' => $result->stderr ?? '',
		'code'   => $result->return_code ?? -1,
	);
}

WP_CLI::log( '' );
WP_CLI::log( '========================================' );
WP_CLI::log( '  WP-CLI Command Tests' );
WP_CLI::log( '========================================' );
WP_CLI::log( '' );

// ---------------------------------------------------------------------------
// Look up seed user IDs for role-specific tests.
// ---------------------------------------------------------------------------

$candidate_users = get_users( array( 'role' => 'wcb_candidate', 'number' => 1 ) );
$candidate_id    = ! empty( $candidate_users ) ? (int) $candidate_users[0]->ID : 0;

$employer_users = get_users( array( 'role' => 'wcb_employer', 'number' => 1 ) );
$employer_id    = ! empty( $employer_users ) ? (int) $employer_users[0]->ID : 0;

// Find a pending job for approve/reject tests.
$pending_jobs = get_posts(
	array(
		'post_type'      => 'wcb_job',
		'post_status'    => 'pending',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	)
);
$pending_job_id = ! empty( $pending_jobs ) ? (int) $pending_jobs[0] : 0;

// Find a published job for expire test.
$published_jobs = get_posts(
	array(
		'post_type'      => 'wcb_job',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	)
);
$published_job_id = ! empty( $published_jobs ) ? (int) $published_jobs[0] : 0;

// Find an application for status update test.
$applications = get_posts(
	array(
		'post_type'      => 'wcb_application',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	)
);
$app_id = ! empty( $applications ) ? (int) $applications[0] : 0;

WP_CLI::log( 'Seed lookups:' );
WP_CLI::log( "  Candidate ID: {$candidate_id}" );
WP_CLI::log( "  Employer ID:  {$employer_id}" );
WP_CLI::log( "  Pending Job:  {$pending_job_id}" );
WP_CLI::log( "  Published Job:{$published_job_id}" );
WP_CLI::log( "  Application:  {$app_id}" );
WP_CLI::log( '' );

// ---------------------------------------------------------------------------
// 1. wp wcb status
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Test 1: wp wcb status ---' );
$r = wcb_run( 'wcb status' );
wcb_assert( 0 === $r['code'], 'exit code is 0' );
wcb_assert( false !== strpos( $r['stdout'], 'Job' ), 'stdout contains "Job"' );

// ---------------------------------------------------------------------------
// 2. wp wcb abilities --format=json
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Test 2: wp wcb abilities --format=json ---' );
$r = wcb_run( 'wcb abilities --format=json' );
wcb_assert( 0 === $r['code'], 'exit code is 0' );
$abilities = json_decode( $r['stdout'], true );
wcb_assert( is_array( $abilities ), 'output is valid JSON array' );
wcb_assert( count( $abilities ) > 0, 'has at least one ability' );

// ---------------------------------------------------------------------------
// 3. wp wcb abilities --user-id={candidate} --format=json
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Test 3: wp wcb abilities --user-id (candidate) ---' );
if ( $candidate_id ) {
	$r = wcb_run( "wcb abilities --user-id={$candidate_id} --format=json" );
	wcb_assert( 0 === $r['code'], 'exit code is 0 for candidate user' );
	$data = json_decode( $r['stdout'], true );
	wcb_assert( is_array( $data ), 'output is valid JSON for candidate' );
} else {
	WP_CLI::warning( '  SKIP: no candidate user found' );
}

// ---------------------------------------------------------------------------
// 4. wp wcb job list --format=json
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Test 4: wp wcb job list --format=json ---' );
$r = wcb_run( 'wcb job list --format=json' );
wcb_assert( 0 === $r['code'], 'exit code is 0' );
$jobs = json_decode( $r['stdout'], true );
wcb_assert( is_array( $jobs ), 'output is valid JSON array' );
wcb_assert( count( $jobs ) >= 17, 'count >= 17 seeded jobs' );

// ---------------------------------------------------------------------------
// 5. wp wcb job list --status=pending --format=json
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Test 5: wp wcb job list --status=pending ---' );
$r = wcb_run( 'wcb job list --status=pending --format=json' );
wcb_assert( 0 === $r['code'], 'exit code is 0' );
$pending = json_decode( $r['stdout'], true );
wcb_assert( is_array( $pending ), 'output is valid JSON' );
$all_pending = true;
if ( is_array( $pending ) ) {
	foreach ( $pending as $item ) {
		if ( isset( $item['post_status'] ) && 'pending' !== $item['post_status'] ) {
			$all_pending = false;
		}
	}
}
wcb_assert( $all_pending, 'all items have pending status' );

// ---------------------------------------------------------------------------
// 6. wp wcb job list --format=ids
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Test 6: wp wcb job list --format=ids ---' );
$r = wcb_run( 'wcb job list --format=ids' );
wcb_assert( 0 === $r['code'], 'exit code is 0' );
$ids_raw   = trim( $r['stdout'] );
$ids_parts = preg_split( '/\s+/', $ids_raw );
$all_ints  = true;
foreach ( $ids_parts as $part ) {
	if ( '' !== $part && ! ctype_digit( $part ) ) {
		$all_ints = false;
	}
}
wcb_assert( $all_ints, 'output is space-separated integers' );

// ---------------------------------------------------------------------------
// 7. wp wcb job approve {pending_job_id}
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Test 7: wp wcb job approve ---' );
if ( $pending_job_id ) {
	$original_status = get_post_status( $pending_job_id );
	$r               = wcb_run( "wcb job approve {$pending_job_id}" );
	wcb_assert( 0 === $r['code'], 'exit code is 0' );
	clean_post_cache( $pending_job_id );
	wcb_assert( 'publish' === get_post_status( $pending_job_id ), 'post_status is publish after approve' );
	// Restore original status.
	wp_update_post( array( 'ID' => $pending_job_id, 'post_status' => $original_status ) );
} else {
	WP_CLI::warning( '  SKIP: no pending job found' );
}

// ---------------------------------------------------------------------------
// 8. wp wcb job reject {job_id} --reason="Test rejection"
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Test 8: wp wcb job reject ---' );
if ( $published_job_id ) {
	$original_status = get_post_status( $published_job_id );
	$r               = wcb_run( "wcb job reject {$published_job_id} --reason=\"Test rejection\"" );
	wcb_assert( 0 === $r['code'], 'exit code is 0' );
	clean_post_cache( $published_job_id );
	wcb_assert( 'draft' === get_post_status( $published_job_id ), 'post_status is draft after reject' );
	$reason = get_post_meta( $published_job_id, '_wcb_rejection_reason', true );
	wcb_assert( 'Test rejection' === $reason, 'rejection reason meta saved' );
	// Restore.
	wp_update_post( array( 'ID' => $published_job_id, 'post_status' => $original_status ) );
	delete_post_meta( $published_job_id, '_wcb_rejection_reason' );
} else {
	WP_CLI::warning( '  SKIP: no published job found' );
}

// ---------------------------------------------------------------------------
// 9. wp wcb job expire {job_id}
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Test 9: wp wcb job expire ---' );
if ( $published_job_id ) {
	$original_status = get_post_status( $published_job_id );
	$r               = wcb_run( "wcb job expire {$published_job_id}" );
	wcb_assert( 0 === $r['code'], 'exit code is 0' );
	clean_post_cache( $published_job_id );
	wcb_assert( 'wcb_expired' === get_post_status( $published_job_id ), 'post_status is wcb_expired after expire' );
	// Restore.
	wp_update_post( array( 'ID' => $published_job_id, 'post_status' => $original_status ) );
} else {
	WP_CLI::warning( '  SKIP: no published job found' );
}

// ---------------------------------------------------------------------------
// 10. wp wcb job run-expiry
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Test 10: wp wcb job run-expiry ---' );
$r = wcb_run( 'wcb job run-expiry' );
wcb_assert( 0 === $r['code'], 'exit code is 0' );

// ---------------------------------------------------------------------------
// 11. wp wcb application list --format=json
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Test 11: wp wcb application list --format=json ---' );
$r = wcb_run( 'wcb application list --format=json' );
wcb_assert( 0 === $r['code'], 'exit code is 0' );
$apps = json_decode( $r['stdout'], true );
wcb_assert( is_array( $apps ), 'output is valid JSON array' );
wcb_assert( count( $apps ) >= 13, 'count >= 13 seeded applications' );

// ---------------------------------------------------------------------------
// 12. wp wcb application list --status=shortlisted --format=json
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Test 12: wp wcb application list --status=shortlisted ---' );
$r = wcb_run( 'wcb application list --status=shortlisted --format=json' );
wcb_assert( 0 === $r['code'], 'exit code is 0' );
$shortlisted = json_decode( $r['stdout'], true );
wcb_assert( is_array( $shortlisted ), 'output is valid JSON' );
$all_shortlisted = true;
if ( is_array( $shortlisted ) ) {
	foreach ( $shortlisted as $item ) {
		$status = $item['_wcb_status'] ?? $item['status'] ?? '';
		if ( '' !== $status && 'shortlisted' !== $status ) {
			$all_shortlisted = false;
		}
	}
}
wcb_assert( $all_shortlisted, 'all items have shortlisted status' );

// ---------------------------------------------------------------------------
// 13. wp wcb application update {app_id} --status=reviewing
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Test 13: wp wcb application update --status=reviewing ---' );
if ( $app_id ) {
	$original_app_status = get_post_meta( $app_id, '_wcb_status', true );
	$r                   = wcb_run( "wcb application update {$app_id} --status=reviewing" );
	wcb_assert( 0 === $r['code'], 'exit code is 0' );
	wp_cache_flush();
	$new_status = get_post_meta( $app_id, '_wcb_status', true );
	wcb_assert( 'reviewing' === $new_status, 'application _wcb_status meta changed to reviewing' );
	// Restore.
	update_post_meta( $app_id, '_wcb_status', $original_app_status );
} else {
	WP_CLI::warning( '  SKIP: no application found' );
}

// ---------------------------------------------------------------------------
// 14. wp wcb migrate wpjm --dry-run
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Test 14: wp wcb migrate wpjm --dry-run ---' );
$r = wcb_run( 'wcb migrate wpjm --dry-run', true );
wcb_assert( 0 !== $r['code'], 'exits with error (WPJM not active)' );
$combined_output = $r['stdout'] . $r['stderr'];
$mentions_wpjm   = false !== stripos( $combined_output, 'wpjm' )
	|| false !== stripos( $combined_output, 'wp job manager' )
	|| false !== stripos( $combined_output, 'not active' )
	|| false !== stripos( $combined_output, 'not installed' );
wcb_assert( $mentions_wpjm, 'error mentions WPJM / not active' );

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '========================================' );
WP_CLI::log( '  CLI Test Summary' );
WP_CLI::log( '========================================' );
WP_CLI::log( "  Total: " . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) );
WP_CLI::log( "  Pass:  " . $GLOBALS['wcb_test_pass'] );
WP_CLI::log( "  Fail:  " . $GLOBALS['wcb_test_fail'] );
WP_CLI::log( '' );

if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All CLI tests passed.' );
}
