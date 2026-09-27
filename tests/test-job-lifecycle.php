<?php
/**
 * Job end-of-life tests (1.8.0, W12, owner decisions D5 and D15).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-job-lifecycle.php
 *
 * Pins: one "open" rule (published and not past the deadline); the sweep ends
 * past-deadline jobs only when expiry is on; an ended job's URL stays a 200
 * page with noindex and no JobPosting schema; closing a job closes its open
 * applications (position_closed) while expiry leaves them alone; reopening
 * gives a fresh deadline; REST carries accepting_applications and closes_at.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Core\JobDeadline;
use WCB\Modules\Applications\ApplicationLifecycle;
use WCB\Modules\Applications\ApplicationStatus;

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_jl_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

/**
 * Create a job for the fixture employer.
 *
 * @param int    $author   Employer user ID.
 * @param string $deadline Y-m-d deadline, '' for none.
 * @param string $status   Post status.
 * @return int
 */
function wcb_jl_job( int $author, string $deadline, string $status = 'publish' ): int {
	$id = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => $status, 'post_title' => 'JL job ' . wp_generate_password( 4, false ), 'post_content' => 'JL', 'post_author' => $author ) );
	if ( '' !== $deadline ) {
		update_post_meta( $id, '_wcb_deadline', $deadline );
	}
	return $id;
}

rest_get_server();
add_filter( 'pre_wp_mail', '__return_true' );

WP_CLI::log( '=== Job end-of-life ===' );

$wcb_suffix    = wp_generate_password( 6, false );
$wcb_employer  = wp_insert_user( array( 'user_login' => 'jl-emp-' . $wcb_suffix, 'user_pass' => wp_generate_password(), 'user_email' => 'jl-emp-' . $wcb_suffix . '@example.test', 'role' => 'wcb_employer' ) );
$wcb_candidate = wp_insert_user( array( 'user_login' => 'jl-cand-' . $wcb_suffix, 'user_pass' => wp_generate_password(), 'user_email' => 'jl-cand-' . $wcb_suffix . '@example.test', 'role' => 'wcb_candidate' ) );
$wcb_today     = current_time( 'Y-m-d' );
$wcb_past      = gmdate( 'Y-m-d', strtotime( $wcb_today . ' -3 days' ) );
$wcb_future    = gmdate( 'Y-m-d', strtotime( $wcb_today . ' +10 days' ) );
$wcb_settings  = get_option( 'wcb_settings', array() );
$wcb_made      = array();

// ── The open rule ────────────────────────────────────────────────────────
$wcb_open    = wcb_jl_job( $wcb_employer, $wcb_future );
$wcb_nodate  = wcb_jl_job( $wcb_employer, '' );
$wcb_lastday = wcb_jl_job( $wcb_employer, $wcb_today );
$wcb_late    = wcb_jl_job( $wcb_employer, $wcb_past );
$wcb_closed  = wcb_jl_job( $wcb_employer, $wcb_future, 'wcb_closed' );
$wcb_old     = wcb_jl_job( $wcb_employer, gmdate( 'Y-m-d', strtotime( $wcb_today . ' -30 days' ) ) );
array_push( $wcb_made, $wcb_open, $wcb_nodate, $wcb_lastday, $wcb_late, $wcb_closed, $wcb_old );

wcb_jl_assert( JobDeadline::accepts_applications( $wcb_open ) && JobDeadline::accepts_applications( $wcb_nodate ), 'open: future deadline or none' );
wcb_jl_assert( JobDeadline::accepts_applications( $wcb_lastday ), 'open on the deadline day itself' );
wcb_jl_assert( ! JobDeadline::accepts_applications( $wcb_late ) && 'expired' === JobDeadline::ended( $wcb_late ), 'past the deadline: ended as expired, even before the sweep' );
wcb_jl_assert( ! JobDeadline::accepts_applications( $wcb_closed ) && 'closed' === JobDeadline::ended( $wcb_closed ), 'closed by the employer: ended as closed' );

$wcb_found = get_posts(
	array(
		'post_type'      => 'wcb_job',
		'post_status'    => 'publish',
		'post__in'       => array( $wcb_open, $wcb_nodate, $wcb_lastday, $wcb_late ),
		'fields'         => 'ids',
		'posts_per_page' => 10,
		'meta_query'     => JobDeadline::open_jobs_meta_query(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	)
);
sort( $wcb_found );
$wcb_want = array( $wcb_open, $wcb_nodate, $wcb_lastday );
sort( $wcb_want );
wcb_jl_assert( $wcb_want === $wcb_found, 'open_jobs_meta_query keeps exactly the open ones' );

// ── Sweep: only when expiry is on ────────────────────────────────────────
// The sweep acts on the whole site: note the site's own past-deadline jobs so
// they can be put back afterwards, without firing publish hooks (alerts).
$wcb_bystanders = array_diff(
	get_posts(
		array(
			'post_type'      => 'wcb_job',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 500,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_wcb_deadline',
					'value'   => $wcb_today,
					'compare' => '<',
				),
			),
		)
	),
	$wcb_made
);
global $wpdb;
foreach ( $wcb_bystanders as $wcb_id ) {
	// Parked outside the sweep (no hooks, so no emails, bell rows or alerts).
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $wcb_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	clean_post_cache( $wcb_id );
}
$wcb_expiry     = new \WCB\Modules\Jobs\JobsExpiry();
update_option( 'wcb_settings', array_merge( $wcb_settings, array( 'deadline_auto_close' => false ) ) );
$wcb_expiry->expire_jobs();
wcb_jl_assert( 'publish' === get_post_status( $wcb_late ), 'expiry paused (pre-1.8.0 site): past-deadline job stays listed' );
update_option( 'wcb_settings', array_merge( $wcb_settings, array( 'deadline_auto_close' => true ) ) );
$wcb_announced = array();
add_action(
	'wcb_job_expired',
	static function ( int $id ) use ( &$wcb_announced ): void {
		$wcb_announced[] = $id;
	}
);
$wcb_expiry->expire_jobs();
wcb_jl_assert( in_array( $wcb_late, $wcb_announced, true ) && ! in_array( $wcb_old, $wcb_announced, true ) && 'wcb_expired' === get_post_status( $wcb_old ), 'a job 3 days late is announced; one 30 days late (backlog) expires silently' );
wcb_jl_assert( 'wcb_expired' === get_post_status( $wcb_late ) && 'publish' === get_post_status( $wcb_lastday ), 'expiry on: past-deadline job moves to expired, deadline-day job stays' );
foreach ( $wcb_bystanders as $wcb_id ) {
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $wcb_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	clean_post_cache( $wcb_id );
}
wcb_jl_assert( 'hourly' === wp_get_schedule( 'wcb_check_job_expiry' ), 'sweep runs hourly' );
wcb_jl_assert( ! str_contains( (string) get_permalink( $wcb_late ), '?' ) && str_contains( (string) get_permalink( $wcb_late ), '/jobs/' ), 'an expired job keeps its /jobs/{slug}/ link' );

// ── The ended page: 200, noindex, no JobPosting ──────────────────────────
wp_set_current_user( 0 );
$wcb_page = wp_remote_get( (string) get_permalink( $wcb_late ), array( 'timeout' => 20, 'sslverify' => false ) );
$wcb_body = (string) wp_remote_retrieve_body( $wcb_page );
wcb_jl_assert( 200 === (int) wp_remote_retrieve_response_code( $wcb_page ), 'expired job URL answers 200, not 404 (' . wp_remote_retrieve_response_code( $wcb_page ) . ')' );
wcb_jl_assert( str_contains( $wcb_body, 'class="wcb-job-ended"' ) && (bool) preg_match( '/<meta name=.robots. content=.[^>]*noindex/', $wcb_body ), 'expired page shows the notice and is noindex' );
wcb_jl_assert( ! str_contains( $wcb_body, '"JobPosting"' ), 'expired page has no JobPosting schema' );
$wcb_page = wp_remote_get( (string) get_permalink( $wcb_open ), array( 'timeout' => 20, 'sslverify' => false ) );
$wcb_body = (string) wp_remote_retrieve_body( $wcb_page );
wcb_jl_assert( ! str_contains( $wcb_body, 'class="wcb-job-ended"' ) && ! (bool) preg_match( '/<meta name=.robots. content=.[^>]*noindex/', $wcb_body ), 'control: an open job page is indexable with no ended notice' );

// ── D15: Close closes open applications, expiry does not ─────────────────
$wcb_apps = array();
foreach ( array( $wcb_open, $wcb_lastday ) as $wcb_job ) {
	foreach ( array( 'submitted', 'shortlisted', 'hired' ) as $wcb_st ) {
		$wcb_a = (int) wp_insert_post( array( 'post_type' => 'wcb_application', 'post_status' => 'publish', 'post_title' => 'JL app', 'post_author' => $wcb_candidate ) );
		update_post_meta( $wcb_a, '_wcb_job_id', $wcb_job );
		update_post_meta( $wcb_a, '_wcb_candidate_id', $wcb_candidate );
		update_post_meta( $wcb_a, '_wcb_status', $wcb_st );
		$wcb_apps[ $wcb_job ][ $wcb_st ] = $wcb_a;
	}
}
wp_update_post( array( 'ID' => $wcb_open, 'post_status' => 'wcb_closed' ) );
wcb_jl_assert( false !== wp_next_scheduled( ApplicationLifecycle::CLOSE_HOOK, array( $wcb_open, 'position_closed' ) ), 'closing a job queues its applications' );
ApplicationLifecycle::close_job_applications( $wcb_open, 'position_closed' );
wp_clear_scheduled_hook( ApplicationLifecycle::CLOSE_HOOK, array( $wcb_open, 'position_closed' ) );
wcb_jl_assert( 'position_closed' === get_post_meta( $wcb_apps[ $wcb_open ]['submitted'], '_wcb_status', true ) && 'position_closed' === get_post_meta( $wcb_apps[ $wcb_open ]['shortlisted'], '_wcb_status', true ), 'open applications become position_closed' );
wcb_jl_assert( 'hired' === get_post_meta( $wcb_apps[ $wcb_open ]['hired'], '_wcb_status', true ), 'decided applications are left alone' );
wcb_jl_assert( 'Position closed' === ApplicationStatus::label( 'position_closed', 'candidate' ) && 'Closed' === ApplicationStatus::label( 'position_closed', 'employer' ), 'labels: candidate "Position closed", employer "Closed"' );
wp_update_post( array( 'ID' => $wcb_lastday, 'post_status' => 'wcb_expired' ) );
wcb_jl_assert( false === wp_next_scheduled( ApplicationLifecycle::CLOSE_HOOK, array( $wcb_lastday, 'position_closed' ) ) && 'shortlisted' === get_post_meta( $wcb_apps[ $wcb_lastday ]['shortlisted'], '_wcb_status', true ), 'expiry leaves applications for the employer to decide' );

// ── Reopen gives a new listing period ────────────────────────────────────
$wcb_request = new WP_REST_Request( 'PATCH', '/wcb/v1/jobs/' . $wcb_late );
$wcb_request->set_body_params( array( 'status' => 'publish' ) );
wp_set_current_user( $wcb_employer );
$wcb_resp = rest_do_request( $wcb_request );
wcb_jl_assert( 200 === $wcb_resp->get_status() && JobDeadline::get( $wcb_late ) > $wcb_today, 'reopening an expired job sets a future deadline (' . JobDeadline::get( $wcb_late ) . ')' );

// ── REST: one answer for clients ─────────────────────────────────────────
wp_set_current_user( 0 );
$wcb_resp = rest_do_request( new WP_REST_Request( 'GET', '/wcb/v1/jobs/' . $wcb_nodate ) );
$wcb_data = $wcb_resp->get_data();
wcb_jl_assert( true === ( $wcb_data['accepting_applications'] ?? null ) && array_key_exists( 'closes_at', $wcb_data ), 'job payload carries accepting_applications and closes_at' );

// Teardown.
update_option( 'wcb_settings', $wcb_settings );
foreach ( $wcb_apps as $wcb_set ) {
	foreach ( $wcb_set as $wcb_a ) {
		wp_delete_post( $wcb_a, true );
	}
}
foreach ( $wcb_made as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
	wp_clear_scheduled_hook( ApplicationLifecycle::CLOSE_HOOK, array( $wcb_id, 'job_removed' ) );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( (int) $wcb_employer );
wp_delete_user( (int) $wcb_candidate );

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All job lifecycle tests passed.' );
}
