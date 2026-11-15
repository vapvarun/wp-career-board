<?php
/**
 * Candidate dashboard status history (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-candidate-application-timeline.php
 *
 * Pins: GET /candidates/{id}/applications gives each row a `timeline` that
 * starts at Submitted and lists every status change in the candidate's
 * wording ("Not selected", not "Rejected"), with a date per step and no
 * reviewer or note (those stay in the employer's audit trail).
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

$GLOBALS['wcb_tl_pass'] = 0;
$GLOBALS['wcb_tl_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_tl_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_tl_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_tl_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

rest_get_server();
add_filter( 'pre_wp_mail', '__return_false' );

WP_CLI::log( '=== Candidate application timeline ===' );

$wcb_tl_employer  = wp_insert_user(
	array(
		'user_login' => 'wcb_tl_employer_' . wp_generate_password( 6, false ),
		'user_pass'  => wp_generate_password(),
		'user_email' => 'wcb-tl-employer-' . wp_generate_password( 6, false ) . '@example.com',
		'role'       => 'wcb_employer',
	)
);
$wcb_tl_candidate = wp_insert_user(
	array(
		'user_login' => 'wcb_tl_candidate_' . wp_generate_password( 6, false ),
		'user_pass'  => wp_generate_password(),
		'user_email' => 'wcb-tl-candidate-' . wp_generate_password( 6, false ) . '@example.com',
		'role'       => 'wcb_candidate',
	)
);
$wcb_tl_job       = wp_insert_post(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'publish',
		'post_title'  => 'Senior Product Designer',
		'post_author' => $wcb_tl_employer,
	)
);
$wcb_tl_app       = wp_insert_post(
	array(
		'post_type'     => 'wcb_application',
		'post_status'   => 'publish',
		'post_title'    => 'Application',
		'post_author'   => $wcb_tl_candidate,
		'post_date_gmt' => '2026-10-10 09:00:00',
		'post_date'     => '2026-10-10 09:00:00',
	)
);
update_post_meta( $wcb_tl_app, '_wcb_job_id', $wcb_tl_job );
update_post_meta( $wcb_tl_app, '_wcb_candidate_id', $wcb_tl_candidate );
update_post_meta( $wcb_tl_app, '_wcb_status', ApplicationStatus::SUBMITTED );

ApplicationLifecycle::transition( $wcb_tl_app, ApplicationStatus::REVIEWING, 'employer_update', $wcb_tl_employer, 'Strong portfolio, internal note' );
ApplicationLifecycle::transition( $wcb_tl_app, ApplicationStatus::REJECTED, 'employer_update', $wcb_tl_employer );

wp_set_current_user( $wcb_tl_candidate );
$wcb_tl_res  = rest_do_request( new WP_REST_Request( 'GET', '/wcb/v1/candidates/' . $wcb_tl_candidate . '/applications' ) );
$wcb_tl_data = (array) $wcb_tl_res->get_data();
$wcb_tl_row  = array();
foreach ( (array) ( $wcb_tl_data['applications'] ?? array() ) as $wcb_tl_item ) {
	if ( (int) $wcb_tl_item['id'] === (int) $wcb_tl_app ) {
		$wcb_tl_row = $wcb_tl_item;
	}
}

$wcb_tl_steps = (array) ( $wcb_tl_row['timeline'] ?? array() );
wcb_tl_assert( 200 === $wcb_tl_res->get_status(), 'candidate lists own applications' );
wcb_tl_assert( 3 === count( $wcb_tl_steps ), 'timeline has Submitted plus two changes (got ' . count( $wcb_tl_steps ) . ')' );
wcb_tl_assert( 'submitted' === ( $wcb_tl_steps[0]['status'] ?? '' ), 'timeline starts at submitted' );
wcb_tl_assert( ApplicationStatus::label( 'submitted', 'candidate' ) === ( $wcb_tl_steps[0]['label'] ?? '' ), 'first step labelled Submitted' );
wcb_tl_assert( \WCB\Core\Time::date_label( '2026-10-10 09:00:00' ) === ( $wcb_tl_steps[0]['date'] ?? '' ), 'first step dated the day the candidate applied' );
wcb_tl_assert( 'reviewing' === ( $wcb_tl_steps[1]['status'] ?? '' ), 'second step is reviewing' );
wcb_tl_assert( ApplicationStatus::label( 'rejected', 'candidate' ) === ( $wcb_tl_steps[2]['label'] ?? '' ), 'rejection reads in the candidate wording' );
wcb_tl_assert( ApplicationStatus::label( 'rejected', 'candidate' ) !== ApplicationStatus::label( 'rejected', 'employer' ), 'candidate wording differs from employer wording' );
wcb_tl_assert( '' !== ( $wcb_tl_steps[2]['date'] ?? '' ) && '—' !== ( $wcb_tl_steps[2]['date'] ?? '—' ), 'every change carries a date' );

$wcb_tl_keys = array();
foreach ( $wcb_tl_steps as $wcb_tl_step ) {
	$wcb_tl_keys = array_merge( $wcb_tl_keys, array_keys( (array) $wcb_tl_step ) );
}
wcb_tl_assert( array() === array_diff( array_unique( $wcb_tl_keys ), array( 'status', 'label', 'date' ) ), 'steps carry status, label and date only (no reviewer, no note)' );
wcb_tl_assert( false === strpos( (string) wp_json_encode( $wcb_tl_steps ), 'internal note' ), 'employer note never reaches the candidate' );

// Cleanup by captured IDs.
wp_delete_post( $wcb_tl_app, true );
wp_delete_post( $wcb_tl_job, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $wcb_tl_candidate );
wp_delete_user( $wcb_tl_employer );

WP_CLI::log( sprintf( 'Passed: %d  Failed: %d', $GLOBALS['wcb_tl_pass'], $GLOBALS['wcb_tl_fail'] ) );
if ( $GLOBALS['wcb_tl_fail'] > 0 ) {
	WP_CLI::halt( 1 );
}
