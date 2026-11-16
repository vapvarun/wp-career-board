<?php
/**
 * Job form success panel tests (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-job-form-success.php
 *
 * Pins: a job held for review is announced as "submitted for review" by both
 * job forms, and neither form links to the job page while it is pending (the
 * job page only exists once the job is live). The multi-step form already did
 * this; the single-page form said "Job posted" and linked to a 404.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
function wcb_jfs_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

rest_get_server();

WP_CLI::log( '' );
WP_CLI::log( '  Job Form Success Panel Tests' );

$wcb_dir = dirname( __DIR__ ) . '/blocks/';

// 1. The REST answer carries the real status the forms read (free board, so no credit gate).
$wcb_free_board = 0;
foreach ( get_posts( array( 'post_type' => 'wcb_board', 'numberposts' => -1, 'fields' => 'ids' ) ) as $wcb_b ) {
	if ( 0 === (int) apply_filters( 'wcb_board_credit_cost', 0, (int) $wcb_b ) ) {
		$wcb_free_board = (int) $wcb_b;
		break;
	}
}
add_filter( 'wcb_job_default_status', $wcb_force_pending = static fn () => 'pending', 999 );
$wcb_suffix   = wp_generate_password( 6, false );
$wcb_employer = (int) wp_insert_user(
	array(
		'user_login' => 'jfs-emp-' . $wcb_suffix,
		'user_pass'  => wp_generate_password(),
		'user_email' => 'jfs-emp-' . $wcb_suffix . '@example.test',
		'role'       => 'wcb_employer',
	)
);
wp_set_current_user( $wcb_employer );
$wcb_request = new WP_REST_Request( 'POST', '/wcb/v1/jobs' );
$wcb_request->set_param( 'title', 'Senior Product Designer' );
if ( $wcb_free_board ) {
	$wcb_request->set_param( 'board_id', $wcb_free_board );
}
$wcb_request->set_param( 'description', 'Shape the hiring experience for thousands of employers and candidates.' );
$wcb_response = rest_do_request( $wcb_request );
remove_filter( 'wcb_job_default_status', $wcb_force_pending, 999 );
$wcb_data   = (array) $wcb_response->get_data();
$wcb_job_id = (int) ( $wcb_data['id'] ?? 0 );
wcb_jfs_assert( 201 === $wcb_response->get_status() && 'pending' === ( $wcb_data['status'] ?? '' ), 'a job held for review answers status "pending" (got ' . $wcb_response->get_status() . ' / ' . ( $wcb_data['status'] ?? '-' ) . ')' );

// 2. Core hides a pending job's page from its author too (why the link 404s).
if ( $wcb_job_id ) {
	$wcb_q = new WP_Query(
		array(
			'p'         => $wcb_job_id,
			'post_type' => 'wcb_job',
		)
	);
	wcb_jfs_assert( ! $wcb_q->have_posts(), 'the pending job page is not public, even to its author' );
}

// 3. Both forms read the status and branch on it.
$wcb_pending_msg = "Job submitted for review. You\\'ll be notified once it\\'s approved.";
foreach ( array( 'job-form', 'job-form-simple' ) as $wcb_block ) {
	$wcb_render = (string) file_get_contents( $wcb_dir . $wcb_block . '/render.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
	$wcb_view   = (string) file_get_contents( $wcb_dir . $wcb_block . '/view.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
	wcb_jfs_assert( str_contains( $wcb_render, $wcb_pending_msg ), "{$wcb_block}: says the job was submitted for review" );
	$wcb_link_hidden = (bool) preg_match( '/data-wp-bind--href="state\.jobUrl"[^>]*state\.jobPending|state\.jobPending[^>]*data-wp-bind--href="state\.jobUrl"/', $wcb_render )
		|| ( str_contains( $wcb_render, 'data-wp-bind--href="state.jobUrl" data-wp-class--wcb-hidden="!state.showJobLink"' ) && str_contains( $wcb_view, '! state.jobPending' ) );
	wcb_jfs_assert( $wcb_link_hidden, "{$wcb_block}: hides the job link while pending" );
	wcb_jfs_assert( (bool) preg_match( '/jobStatus\s*=\s*.*status/', $wcb_view ), "{$wcb_block}: stores the status from the REST answer" );
	wcb_jfs_assert( str_contains( $wcb_view, "jobStatus === 'pending'" ), "{$wcb_block}: treats pending as held for review" );
	wcb_jfs_assert( (bool) preg_match( '/get hasListingWindow\(\)\s*\{[^\n]*\n(?:[^\n]*store\([^\n]*\n)?[^\n]*\n[^\n]*jobPending/', $wcb_view ), "{$wcb_block}: promises no live-until date while pending" );
}

// 3b. A review message is not cleared by the 8-second reset.
foreach ( array( 'job-form', 'job-form-simple' ) as $wcb_block ) {
	$wcb_view = (string) file_get_contents( $wcb_dir . $wcb_block . '/view.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
	wcb_jfs_assert( (bool) preg_match( '/setTimeout\( \(\) => \{.{0,300}?jobPending/s', $wcb_view ), "{$wcb_block}: keeps the review message instead of auto-resetting" );
}

// 3c. Single-page success panel is the multi-step panel: same markup, shared styles, Post another job.
$wcb_simple_render = (string) file_get_contents( $wcb_dir . 'job-form-simple/render.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
$wcb_simple_view   = (string) file_get_contents( $wcb_dir . 'job-form-simple/view.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
$wcb_ui_css        = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/wcb-ui.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
foreach ( array( 'wcb-form-success" data-wp-class--wcb-form-success--show="state.submitted"', 'wcb-form-success__title', 'wcb-form-success__actions', 'wcb-form-success__reset" data-wp-on--click="actions.resetForm"' ) as $wcb_bit ) {
	wcb_jfs_assert( str_contains( $wcb_simple_render, $wcb_bit ), "single-page success uses {$wcb_bit}" );
}
wcb_jfs_assert( (bool) preg_match( '/\n\t\tresetForm\(\) \{/', $wcb_simple_view ), 'single-page form has the resetForm action' );
wcb_jfs_assert( ! str_contains( $wcb_simple_render, 'wcb-form-simple__success' ) && ! str_contains( $wcb_simple_css, 'wcb-form-simple__success' ), 'the old single-page success rules are gone' );
wcb_jfs_assert( (bool) preg_match( '/\.wcb-form-success__title\s*\{[^}]*color:\s*var\(--wcb-success-fg\)/', $wcb_ui_css ), 'the success styles are shared (wcb-ui.css) with the dark-green title' );

// 4. My Jobs offers "View" only for a job that has a page (live, closed or expired).
$wcb_live_id = (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'publish',
		'post_title'  => 'Lead Frontend Engineer',
		'post_author' => $wcb_employer,
	)
);
wp_set_current_user( $wcb_employer );
$wcb_rows = (array) rest_do_request( new WP_REST_Request( 'GET', '/wcb/v1/employers/me/jobs' ) )->get_data();
$wcb_rows = array_column( (array) ( $wcb_rows['jobs'] ?? $wcb_rows ), 'permalink', 'id' );
wcb_jfs_assert( isset( $wcb_rows[ $wcb_job_id ] ) && '' === $wcb_rows[ $wcb_job_id ], 'My Jobs: a pending job has no page link' );
wcb_jfs_assert( ! empty( $wcb_rows[ $wcb_live_id ] ), 'My Jobs: a live job keeps its page link' );
$wcb_dash = (string) file_get_contents( $wcb_dir . 'employer-dashboard/render.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
wcb_jfs_assert( str_contains( $wcb_dash, 'data-wp-class--wcb-hidden="!context.job.permalink" data-wp-bind--href="context.job.permalink"' ), 'My Jobs: the View item hides when there is no page' );
wp_delete_post( $wcb_live_id, true );

if ( $wcb_job_id ) {
	wp_delete_post( $wcb_job_id, true );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $wcb_employer );

WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
}
WP_CLI::success( 'All job form success tests passed.' );
