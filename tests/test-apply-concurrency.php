<?php
/**
 * Concurrency tests for applying and hiring-team notes.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-apply-concurrency.php
 *
 * Fires real parallel HTTP requests at the REST API (Basecamp 10350213909,
 * 10352603157):
 * - a burst of applies from one candidate to one job creates one application;
 * - a request that fails resume validation never makes a parallel valid
 *   request look like "already applied";
 * - parallel note POSTs keep every note.
 *
 * Needs application passwords over HTTP (a local/development site).
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	echo "This file must be run via wp eval-file.\n";
	exit( 1 );
}

use WpOrg\Requests\Requests;

$GLOBALS['wcb_conc_test_pass'] = 0;
$GLOBALS['wcb_conc_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_conc_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		WP_CLI::log( "  PASS: {$label}" );
		++$GLOBALS['wcb_conc_test_pass'];
	} else {
		WP_CLI::warning( "  FAIL: {$label}" );
		++$GLOBALS['wcb_conc_test_fail'];
	}
}

/**
 * Send requests in parallel and return their status codes, in order.
 *
 * @param array<int, array{url:string, data:array<string,mixed>}> $calls Requests.
 * @param string                                               $auth  "user:app-password".
 * @return int[]
 */
function wcb_conc_parallel( array $calls, string $auth ): array {
	$requests = array();
	foreach ( $calls as $call ) {
		$requests[] = array(
			'url'     => $call['url'],
			'type'    => Requests::POST,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $auth ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth header.
				'Content-Type'  => 'application/json',
			),
			'data'    => wp_json_encode( $call['data'] ),
		);
	}
	return array_map(
		static fn ( $r ): int => $r instanceof \WpOrg\Requests\Response ? (int) $r->status_code : 0,
		Requests::request_multiple( $requests, array( 'timeout' => 30 ) )
	);
}

/**
 * Count applications a candidate has for a job.
 *
 * @param int $job_id       Job ID.
 * @param int $candidate_id Candidate user ID.
 * @return int
 */
function wcb_conc_app_count( int $job_id, int $candidate_id ): int {
	return count(
		get_posts(
			array(
				'post_type'      => 'wcb_application',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- test fixture lookup.
					array(
						'key'   => '_wcb_job_id',
						'value' => $job_id,
					),
					array(
						'key'   => '_wcb_candidate_id',
						'value' => $candidate_id,
					),
				),
			)
		)
	);
}

WP_CLI::log( 'Apply and notes concurrency' );

$wcb_suffix    = wp_generate_password( 6, false, false );
$wcb_candidate = wp_insert_user(
	array(
		'user_login' => 'wcbconc_cand_' . $wcb_suffix,
		'user_pass'  => wp_generate_password(),
		'user_email' => 'wcbconc_cand_' . $wcb_suffix . '@example.test',
		'role'       => 'wcb_candidate',
	)
);
$wcb_other     = wp_insert_user(
	array(
		'user_login' => 'wcbconc_other_' . $wcb_suffix,
		'user_pass'  => wp_generate_password(),
		'user_email' => 'wcbconc_other_' . $wcb_suffix . '@example.test',
		'role'       => 'wcb_candidate',
	)
);
$wcb_admin     = (int) ( get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
)[0] ?? 0 );

if ( is_wp_error( $wcb_candidate ) || is_wp_error( $wcb_other ) || ! $wcb_admin ) {
	WP_CLI::error( 'Could not create test users.' );
}

$wcb_cand_login = get_userdata( $wcb_candidate )->user_login;
$wcb_admin_user = get_userdata( $wcb_admin );
$wcb_cand_pw    = WP_Application_Passwords::create_new_application_password( $wcb_candidate, array( 'name' => 'wcbconc' ) );
$wcb_admin_pw   = WP_Application_Passwords::create_new_application_password( $wcb_admin, array( 'name' => 'wcbconc' ) );
$wcb_cand_auth  = $wcb_cand_login . ':' . $wcb_cand_pw[0];
$wcb_admin_auth = $wcb_admin_user->user_login . ':' . $wcb_admin_pw[0];

// A resume the candidate owns, and one they don't (fails validation).
$wcb_own_cv   = wp_insert_attachment(
	array(
		'post_title'     => 'wcbconc cv',
		'post_mime_type' => 'application/pdf',
		'post_author'    => $wcb_candidate,
	)
);
$wcb_other_cv = wp_insert_attachment(
	array(
		'post_title'     => 'wcbconc other cv',
		'post_mime_type' => 'application/pdf',
		'post_author'    => $wcb_other,
	)
);

$wcb_jobs = array();
$wcb_new_job = static function () use ( &$wcb_jobs ): int {
	$id         = wp_insert_post(
		array(
			'post_type'   => 'wcb_job',
			'post_status' => 'publish',
			'post_title'  => 'wcbconc job',
		)
	);
	$wcb_jobs[] = $id;
	return $id;
};

// 1. A burst of identical applies creates one application.
$wcb_job   = $wcb_new_job();
$wcb_url   = rest_url( 'wcb/v1/jobs/' . $wcb_job . '/apply' );
$wcb_codes = wcb_conc_parallel( array_fill( 0, 10, array( 'url' => $wcb_url, 'data' => array( 'resume_attachment_id' => $wcb_own_cv ) ) ), $wcb_cand_auth );
wcb_conc_assert( 1 === wcb_conc_app_count( $wcb_job, $wcb_candidate ), 'burst of 10 applies creates exactly 1 application (codes ' . implode( ',', $wcb_codes ) . ')' );
wcb_conc_assert( 1 === count( array_keys( $wcb_codes, 200, true ) ), 'exactly one request succeeds' );

// 2. An invalid apply racing a valid one never blocks the valid one.
$wcb_blocked = 0;
for ( $i = 0; $i < 5; $i++ ) {
	$wcb_job   = $wcb_new_job();
	$wcb_url   = rest_url( 'wcb/v1/jobs/' . $wcb_job . '/apply' );
	$wcb_codes = wcb_conc_parallel(
		array(
			array( 'url' => $wcb_url, 'data' => array( 'resume_attachment_id' => $wcb_other_cv ) ),
			array( 'url' => $wcb_url, 'data' => array( 'resume_attachment_id' => $wcb_own_cv ) ),
		),
		$wcb_cand_auth
	);
	if ( 1 !== wcb_conc_app_count( $wcb_job, $wcb_candidate ) || 200 !== $wcb_codes[1] ) {
		++$wcb_blocked;
	}
}
wcb_conc_assert( 0 === $wcb_blocked, 'invalid apply racing a valid one: valid apply succeeds in 5/5 runs' );

// 3. Parallel notes are all kept.
$wcb_app_ids = get_posts(
	array(
		'post_type'      => 'wcb_application',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_wcb_job_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- test fixture lookup.
		'meta_value'     => $wcb_jobs[0], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- test fixture lookup.
	)
);
$wcb_app     = (int) ( $wcb_app_ids[0] ?? 0 );
$wcb_calls   = array();
for ( $i = 0; $i < 10; $i++ ) {
	$wcb_calls[] = array(
		'url'  => rest_url( 'wcb/v1/applications/' . $wcb_app . '/notes' ),
		'data' => array( 'text' => 'wcbconc note ' . $i ),
	);
}
wcb_conc_parallel( $wcb_calls, $wcb_admin_auth );
wp_cache_delete( $wcb_app, 'post_meta' );
wcb_conc_assert( 10 === count( \WCB\Modules\Applications\ApplicationNotes::notes( $wcb_app ) ), '10 parallel notes are all stored' );

// 4. The guest per-IP limit counts every request in a burst (limit 10/hour).
// Empty guest applies are counted, then rejected for the missing name.
$wcb_ip_key = 'wcb_guest_apply_' . md5( wp_salt() . '127.0.0.1' );
delete_transient( $wcb_ip_key );
$wcb_guest = array_fill(
	0,
	12,
	array(
		'url'  => rest_url( 'wcb/v1/jobs/' . $wcb_jobs[0] . '/apply' ),
		'type' => Requests::POST,
	)
);
$wcb_codes = array_map(
	static fn ( $r ): int => $r instanceof \WpOrg\Requests\Response ? (int) $r->status_code : 0,
	Requests::request_multiple( $wcb_guest, array( 'timeout' => 30 ) )
);
wcb_conc_assert( 10 === (int) get_transient( $wcb_ip_key ) && 2 === count( array_keys( $wcb_codes, 429, true ) ), 'guest burst of 12 from one IP: 10 counted, 2 rate-limited (codes ' . implode( ',', $wcb_codes ) . ')' );
delete_transient( $wcb_ip_key );

// Clean up.
foreach ( $wcb_jobs as $wcb_id ) {
	foreach ( get_posts( array( 'post_type' => 'wcb_application', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_wcb_job_id', 'meta_value' => $wcb_id ) ) as $wcb_a ) { // phpcs:ignore WordPress.DB.SlowDBQuery -- test fixture cleanup.
		wp_delete_post( $wcb_a, true );
	}
	wp_delete_post( $wcb_id, true );
}
wp_delete_attachment( $wcb_own_cv, true );
wp_delete_attachment( $wcb_other_cv, true );
WP_Application_Passwords::delete_application_password( $wcb_admin, $wcb_admin_pw[1]['uuid'] );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $wcb_candidate );
wp_delete_user( $wcb_other );

WP_CLI::log( '' );
WP_CLI::log( sprintf( 'Results: %d passed, %d failed', $GLOBALS['wcb_conc_test_pass'], $GLOBALS['wcb_conc_test_fail'] ) );
if ( $GLOBALS['wcb_conc_test_fail'] > 0 ) {
	WP_CLI::halt( 1 );
}
