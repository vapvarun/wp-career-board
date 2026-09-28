<?php
/**
 * Application status lifecycle tests (1.8.0, W10).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-application-lifecycle.php
 *
 * Pins: one writer (ApplicationLifecycle::transition) logs and announces each
 * real change exactly once; a same-status save sends nothing; candidates read
 * "Not selected" where employers read "Rejected"; every payload carries
 * status, status_label and status_tone; withdrawing keeps the application,
 * tells the employer and still lets the candidate apply again.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

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
function wcb_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
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
	if ( 'GET' === $method ) {
		foreach ( $params as $k => $v ) {
			$request->set_param( $k, $v );
		}
	} else {
		$request->set_body_params( $params );
	}
	return rest_do_request( $request );
}

rest_get_server();

WP_CLI::log( '=== Application lifecycle ===' );

// Count what reaches the outside world instead of sending it.
$GLOBALS['wcb_mail_to'] = array();
add_filter(
	'pre_wp_mail',
	static function ( $short, array $atts ) {
		$GLOBALS['wcb_mail_to'][] = (string) ( is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : $atts['to'] );
		return true;
	},
	10,
	2
);
$GLOBALS['wcb_events'] = array();
add_action(
	'wcb_application_status_changed',
	static function ( $id, $from, $to ) {
		$GLOBALS['wcb_events'][] = "{$id}:{$from}>{$to}";
	},
	1,
	3
);

$wcb_suffix    = wp_generate_password( 6, false );
$wcb_employer  = wp_insert_user( array( 'user_login' => 'lc-emp-' . $wcb_suffix, 'user_pass' => wp_generate_password(), 'user_email' => 'lc-emp-' . $wcb_suffix . '@example.test', 'role' => 'wcb_employer' ) );
$wcb_candidate = wp_insert_user( array( 'user_login' => 'lc-cand-' . $wcb_suffix, 'user_pass' => wp_generate_password(), 'user_email' => 'lc-cand-' . $wcb_suffix . '@example.test', 'role' => 'wcb_candidate' ) );
$wcb_job       = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'LC job ' . $wcb_suffix, 'post_author' => $wcb_employer ) );
update_post_meta( $wcb_job, '_wcb_deadline', gmdate( 'Y-m-d', strtotime( '+10 days' ) ) );

/**
 * Create an application.
 *
 * @param int    $job       Job ID.
 * @param int    $candidate Candidate user ID.
 * @param string $status    Initial status.
 * @return int
 */
function wcb_lc_app( int $job, int $candidate, string $status = 'submitted' ): int {
	$id = (int) wp_insert_post( array( 'post_type' => 'wcb_application', 'post_status' => 'publish', 'post_title' => 'LC app', 'post_author' => $candidate ) );
	update_post_meta( $id, '_wcb_job_id', $job );
	update_post_meta( $id, '_wcb_candidate_id', $candidate );
	update_post_meta( $id, '_wcb_status', $status );
	return $id;
}

// ── Labels ───────────────────────────────────────────────────────────────
wcb_assert( 'Not selected' === ApplicationStatus::label( 'rejected', 'candidate' ), 'candidate reads "Not selected"' );
wcb_assert( 'Rejected' === ApplicationStatus::label( 'rejected', 'employer' ), 'employer reads "Rejected"' );
wcb_assert( 'Submitted' === ApplicationStatus::label( '' ), 'empty status reads as Submitted' );
wcb_assert( 'danger' === ApplicationStatus::tone( 'rejected' ) && 'success' === ApplicationStatus::tone( 'hired' ), 'tone follows the outcome' );
$wcb_payload = ApplicationStatus::payload( 'hired', 'candidate' );
wcb_assert( array( 'status', 'status_label', 'status_tone', 'statusLabel' ) === array_keys( $wcb_payload ), 'payload carries status, status_label, status_tone (+ legacy statusLabel)' );

// ── One writer, one event per real change ────────────────────────────────
$wcb_app = wcb_lc_app( $wcb_job, $wcb_candidate );
$r       = wcb_rest( 'PATCH', '/wcb/v1/applications/' . $wcb_app . '/status', array( 'status' => 'reviewing' ), $wcb_employer );
wcb_assert( 200 === $r->get_status() && true === $r->get_data()['changed'], 'employer moves submitted -> reviewing' );
wcb_assert( 'Reviewing' === $r->get_data()['status_label'] && 'warning' === $r->get_data()['status_tone'], 'response carries label + tone' );
$r = wcb_rest( 'PATCH', '/wcb/v1/applications/' . $wcb_app . '/status', array( 'status' => 'reviewing' ), $wcb_employer );
wcb_assert( false === $r->get_data()['changed'], 'same-status save reports unchanged' );
wcb_rest( 'PATCH', '/wcb/v1/applications/' . $wcb_app . '/status', array( 'status' => 'shortlisted' ), $wcb_employer );
wcb_rest( 'PATCH', '/wcb/v1/applications/' . $wcb_app . '/status', array( 'status' => 'rejected' ), $wcb_employer );
$wcb_mine = array_values( array_filter( $GLOBALS['wcb_events'], static fn( $e ) => str_starts_with( $e, $wcb_app . ':' ) ) );
wcb_assert( array( "{$wcb_app}:submitted>reviewing", "{$wcb_app}:reviewing>shortlisted", "{$wcb_app}:shortlisted>rejected" ) === $wcb_mine, 'exactly one event per real change, none for the repeat' );
$wcb_log = ApplicationLifecycle::log( $wcb_app );
wcb_assert( 3 === count( $wcb_log ) && $wcb_employer === $wcb_log[0]['by'] && str_contains( $wcb_log[0]['at'], 'T' ), 'log: one row per change, actor + ISO time' );
$wcb_cand_mail = count( array_filter( $GLOBALS['wcb_mail_to'], static fn( $to ) => str_contains( $to, 'lc-cand-' ) ) );
wcb_assert( 3 === $wcb_cand_mail, 'candidate got 3 status emails, not 4' );
wcb_assert( false === ApplicationLifecycle::transition( $wcb_app, 'closed' ), 'unknown status is refused' );
$r = wcb_rest( 'PATCH', '/wcb/v1/applications/' . $wcb_app . '/status', array( 'status' => 'withdrawn' ), $wcb_employer );
wcb_assert( 400 === $r->get_status(), 'employer cannot set withdrawn' );

// Candidate sees the candidate wording.
$r    = wcb_rest( 'GET', '/wcb/v1/candidates/' . $wcb_candidate . '/applications', array(), $wcb_candidate );
$rows = wp_list_pluck( (array) $r->get_data()['applications'], 'status_label', 'id' );
wcb_assert( 'Not selected' === ( $rows[ $wcb_app ] ?? '' ), 'candidate list says "Not selected"' );
$r = wcb_rest( 'GET', '/wcb/v1/jobs/' . $wcb_job . '/applications', array(), $wcb_employer );
$e = wp_list_pluck( (array) $r->get_data()['applications'], 'status_label', 'id' );
wcb_assert( 'Rejected' === ( $e[ $wcb_app ] ?? '' ), 'employer list says "Rejected"' );

// ── Counts and pagination come from the server, not the loaded page ─────
$wcb_extra = array( wcb_lc_app( $wcb_job, $wcb_candidate, 'shortlisted' ), wcb_lc_app( $wcb_job, $wcb_candidate, 'shortlisted' ) );
$r         = wcb_rest( 'GET', '/wcb/v1/jobs/' . $wcb_job . '/applications', array( 'per_page' => 1 ), $wcb_employer );
$d         = $r->get_data();
wcb_assert( 1 === count( $d['applications'] ) && true === $d['has_more'] && 3 === $d['counts']['total'], 'job list: one row per page, but counts cover all 3' );
wcb_assert( 2 === $d['counts']['by_status']['shortlisted'] && 1 === $d['counts']['by_status']['rejected'], 'job counts per status match SQL' );
$r = wcb_rest( 'GET', '/wcb/v1/jobs/' . $wcb_job . '/applications', array( 'status' => 'shortlisted' ), $wcb_employer );
wcb_assert( array( 'shortlisted' ) === array_values( array_unique( wp_list_pluck( $r->get_data()['applications'], 'status' ) ) ) && 2 === count( $r->get_data()['applications'] ), 'status filter runs on the server' );
$r = wcb_rest( 'GET', '/wcb/v1/candidates/' . $wcb_candidate . '/applications', array( 'per_page' => 1 ), $wcb_candidate );
wcb_assert( 3 === $r->get_data()['counts']['total'] && 2 === $r->get_data()['counts']['by_status']['shortlisted'], 'candidate counts cover every page' );
foreach ( $wcb_extra as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}

// An add-on that records an event itself can stop the email announcing it.
$wcb_signals = 0;
$wcb_count   = static function ( array $n ) use ( &$wcb_signals ): void {
	// Email-sourced signals carry id 0 (a bell row carries its own id).
	if ( 0 === (int) $n['id'] ) {
		++$wcb_signals;
	}
};
add_action( 'wcb_notification_created', $wcb_count );
$wcb_sig = wcb_lc_app( $wcb_job, $wcb_candidate, 'submitted' );
add_filter( 'wcb_email_announces_notification', '__return_false', 99 );
ApplicationLifecycle::transition( $wcb_sig, 'reviewing', 'test', $wcb_employer );
$wcb_suppressed = $wcb_signals;
remove_filter( 'wcb_email_announces_notification', '__return_false', 99 );
remove_action( 'wcb_notification_created', $wcb_count );
wcb_assert( 0 === $wcb_suppressed, 'wcb_email_announces_notification = false: the email fires no signal' );
// Control: without our __return_false the email announces the change,
// unless an active bell registered its own claim for this email.
$wcb_signals = 0;
add_action( 'wcb_notification_created', $wcb_count );
ApplicationLifecycle::transition( $wcb_sig, 'shortlisted', 'test', $wcb_employer );
remove_action( 'wcb_notification_created', $wcb_count );
wcb_assert( ( has_filter( 'wcb_email_announces_notification' ) ? 0 : 1 ) === $wcb_signals, 'without the filter the email announces unless the bell claims it' );
wp_delete_post( $wcb_sig, true );

// Trash and restore: the application comes back published, not as a draft.
$wcb_tr = wcb_lc_app( $wcb_job, $wcb_candidate, 'reviewing' );
wp_trash_post( $wcb_tr );
wp_untrash_post( $wcb_tr );
wcb_assert( 'publish' === get_post_status( $wcb_tr ) && 'reviewing' === get_post_meta( $wcb_tr, '_wcb_status', true ), 'restored application is published again with its status' );
wp_delete_post( $wcb_tr, true );

// CSV cells that a spreadsheet would run as a formula are neutralised.
$wcb_csv = new ReflectionMethod( \WCB\Core\ApplicationsCsv::class, 'csv_row' );
$wcb_csv->setAccessible( true );
$wcb_fh = fopen( 'php://memory', 'w+' );
$wcb_csv->invoke( null, $wcb_fh, array( '=HYPERLINK("x")', '+1', '-2', '@SUM(A1)', 'plain', '' ) );
rewind( $wcb_fh );
wcb_assert( "\"'=HYPERLINK(\"\"x\"\")\",'+1,'-2,'@SUM(A1),plain,\n" === stream_get_contents( $wcb_fh ), 'CSV: = + - @ cells exported as text' );

// Legacy log rows ('Y-m-d H:i:s', blank first row) read back clean.
update_post_meta( $wcb_app, '_wcb_status_log', array( array( 'from' => '', 'to' => '' ), array( 'from' => 'submitted', 'to' => 'reviewing', 'by' => 1, 'at' => '2026-01-02 03:04:05' ) ) );
$wcb_log = ApplicationLifecycle::log( $wcb_app );
wcb_assert( 1 === count( $wcb_log ) && '2026-01-02T03:04:05+00:00' === $wcb_log[0]['at'], 'legacy log: blank row dropped, time as ISO UTC' );

// ── Withdraw ─────────────────────────────────────────────────────────────
$wcb_rejected_app       = $wcb_app;
$wcb_app                = wcb_lc_app( $wcb_job, $wcb_candidate, 'shortlisted' );
$GLOBALS['wcb_mail_to'] = array();
$r                      = wcb_rest( 'DELETE', '/wcb/v1/applications/' . $wcb_app, array(), $wcb_candidate );
wcb_assert( 200 === $r->get_status() && true === $r->get_data()['withdrawn'] && 'Withdrawn' === $r->get_data()['status_label'], 'candidate withdraws' );
wcb_assert( get_post( $wcb_app ) instanceof WP_Post && 'withdrawn' === get_post_meta( $wcb_app, '_wcb_status', true ), 'application kept as withdrawn' );
wcb_assert( array( 'lc-emp-' . $wcb_suffix . '@example.test' ) === $GLOBALS['wcb_mail_to'], 'only the employer is emailed' );
$r = wcb_rest( 'DELETE', '/wcb/v1/applications/' . $wcb_app, array(), $wcb_candidate );
wcb_assert( 409 === $r->get_status(), 'withdrawing twice is refused' );
$r = wcb_rest( 'PATCH', '/wcb/v1/applications/' . $wcb_app . '/status', array( 'status' => 'reviewing' ), $wcb_employer );
wcb_assert( 409 === $r->get_status() && 'withdrawn' === get_post_meta( $wcb_app, '_wcb_status', true ), 'employer cannot reopen a withdrawn application' );
$r = wcb_rest( 'DELETE', '/wcb/v1/applications/' . $wcb_rejected_app, array(), $wcb_candidate );
wcb_assert( 409 === $r->get_status(), 'an application with an outcome cannot be withdrawn' );
wp_delete_post( $wcb_rejected_app, true );

// Withdrawn does not count as applied: the candidate may apply again.
$r = wcb_rest( 'POST', '/wcb/v1/jobs/' . $wcb_job . '/apply', array( 'cover_letter' => 'Second try' ), $wcb_candidate );
$wcb_code = (string) ( $r->get_data()['code'] ?? '' );
wcb_assert( 'wcb_already_applied' !== $wcb_code, 'apply after withdrawing is not blocked as a duplicate (' . $r->get_status() . ' ' . $wcb_code . ')' );
// Control: a live application does block a second one.
$wcb_live_block = wcb_lc_app( $wcb_job, $wcb_candidate, 'reviewing' );
$r              = wcb_rest( 'POST', '/wcb/v1/jobs/' . $wcb_job . '/apply', array( 'cover_letter' => 'Third try' ), $wcb_candidate );
wcb_assert( 'wcb_already_applied' === ( $r->get_data()['code'] ?? '' ), 'control: a live application still blocks a duplicate' );
wp_delete_post( $wcb_live_block, true );

// ── Job deleted: rows become job_removed; Remove deletes them ───────────
$wcb_live = wcb_lc_app( $wcb_job, $wcb_candidate, 'reviewing' );
wp_delete_post( $wcb_job, true );
wcb_assert( 'wcb_close_job_applications' === ApplicationLifecycle::CLOSE_HOOK, 'background hook name is the public contract' );
wcb_assert( false !== wp_next_scheduled( ApplicationLifecycle::CLOSE_HOOK, array( $wcb_job, 'job_removed' ) ) && 'reviewing' === get_post_meta( $wcb_live, '_wcb_status', true ), 'job delete queues a background batch instead of working in the request' );
ApplicationLifecycle::close_job_applications( $wcb_job, 'job_removed' );
wp_clear_scheduled_hook( ApplicationLifecycle::CLOSE_HOOK, array( $wcb_job, 'job_removed' ) );
wcb_assert( 'job_removed' === get_post_meta( $wcb_live, '_wcb_status', true ), 'the batch marks open applications job_removed' );
$r = wcb_rest( 'DELETE', '/wcb/v1/applications/' . $wcb_live, array(), $wcb_candidate );
wcb_assert( 200 === $r->get_status() && true === $r->get_data()['deleted'] && null === get_post( $wcb_live ), 'Remove on a dead row deletes it' );

// ── Close, then reopen: the applicants Close moved come back (owner decision) ──
$wcb_rj = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'LC reopen', 'post_author' => $wcb_employer ) );
$wcb_ra = array(
	'submitted' => wcb_lc_app( $wcb_rj, $wcb_candidate, 'submitted' ),
	'reviewing' => wcb_lc_app( $wcb_rj, $wcb_candidate, 'reviewing' ),
	'hired'     => wcb_lc_app( $wcb_rj, $wcb_candidate, 'hired' ),
);
wp_update_post( array( 'ID' => $wcb_rj, 'post_status' => 'wcb_closed' ) );
ApplicationLifecycle::close_job_applications( $wcb_rj, 'position_closed' );
wp_clear_scheduled_hook( ApplicationLifecycle::CLOSE_HOOK, array( $wcb_rj, 'position_closed' ) );
wcb_assert( 'position_closed' === get_post_meta( $wcb_ra['submitted'], '_wcb_status', true ) && 'position_closed' === get_post_meta( $wcb_ra['reviewing'], '_wcb_status', true ) && 'hired' === get_post_meta( $wcb_ra['hired'], '_wcb_status', true ), 'control: Close moves undecided applications and leaves a hire alone' );

$wcb_heard = 0;
$wcb_count = static function () use ( &$wcb_heard ): void {
	++$wcb_heard;
};
add_action( 'wcb_application_status_changed', $wcb_count );
wp_update_post( array( 'ID' => $wcb_rj, 'post_status' => 'publish' ) );
wcb_assert( false !== wp_next_scheduled( ApplicationLifecycle::REOPEN_HOOK, array( $wcb_rj ) ), 'reopening a job queues its applications' );
ApplicationLifecycle::reopen_job_applications( $wcb_rj );
wp_clear_scheduled_hook( ApplicationLifecycle::REOPEN_HOOK, array( $wcb_rj ) );
remove_action( 'wcb_application_status_changed', $wcb_count );
wcb_assert( 'submitted' === get_post_meta( $wcb_ra['submitted'], '_wcb_status', true ) && 'reviewing' === get_post_meta( $wcb_ra['reviewing'], '_wcb_status', true ), 'reopen returns each application to the status it had' );
wcb_assert( 'hired' === get_post_meta( $wcb_ra['hired'], '_wcb_status', true ), 'a hire is untouched by the round trip' );
wcb_assert( 0 === $wcb_heard, 'a reopen tells no one (no status-changed signal, so no email or push)' );
$wcb_log = ApplicationLifecycle::log( $wcb_ra['reviewing'] );
wcb_assert( 'job_reopened' === ( end( $wcb_log )['reason'] ?? '' ), 'the log records why' );
wcb_assert( 'reviewing' === get_post_meta( $wcb_ra['reviewing'], '_wcb_status', true ) && true === ApplicationLifecycle::transition( $wcb_ra['reviewing'], 'shortlisted', 'employer_update', $wcb_employer ), 'the employer can move a restored application again' );

// Closed, then reopened before the queued close ran: the close does nothing.
wp_update_post( array( 'ID' => $wcb_rj, 'post_status' => 'wcb_closed' ) );
wp_update_post( array( 'ID' => $wcb_rj, 'post_status' => 'publish' ) );
ApplicationLifecycle::close_job_applications( $wcb_rj, 'position_closed' );
wp_clear_scheduled_hook( ApplicationLifecycle::CLOSE_HOOK, array( $wcb_rj, 'position_closed' ) );
wp_clear_scheduled_hook( ApplicationLifecycle::REOPEN_HOOK, array( $wcb_rj ) );
wcb_assert( 'submitted' === get_post_meta( $wcb_ra['submitted'], '_wcb_status', true ), 'a close that runs after a reopen does not close an open job\'s applications' );

// One rule for every writer: closed applications do not move.
$wcb_gone = wcb_lc_app( $wcb_rj, $wcb_candidate, 'withdrawn' );
wcb_assert( false === ApplicationLifecycle::transition( $wcb_gone, 'rejected', 'pipeline_stage', 0 ) && 'withdrawn' === get_post_meta( $wcb_gone, '_wcb_status', true ), 'transition() refuses to move a withdrawn application, whichever writer asks' );
wp_delete_post( $wcb_rj, true );
wp_clear_scheduled_hook( ApplicationLifecycle::CLOSE_HOOK, array( $wcb_rj, 'job_removed' ) );

// Teardown.
foreach ( get_posts( array( 'post_type' => 'wcb_application', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1, 'author' => $wcb_candidate ) ) as $wcb_id ) {
	wp_delete_post( (int) $wcb_id, true );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( (int) $wcb_employer );
wp_delete_user( (int) $wcb_candidate );

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All application lifecycle tests passed.' );
}
