<?php
/**
 * Community notification contract tests (1.8.0).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-community-notification-contract.php
 *
 * Covers CommunityNotificationContract::build()/filter_visible() and the
 * do_action( 'wcb_notification_created', $legacy, $contract ) shape a
 * centralised notification center (BuddyNext) reads. Each test creates its
 * own fixtures and removes them, so the suite is re-runnable.
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
use WCB\Modules\Notifications\CommunityNotificationContract;

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_cnc_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::warning( "  FAIL: {$label}" );
	}
}

WP_CLI::log( '=== Community notification contract ===' );
add_filter( 'pre_wp_mail', '__return_true' );

$suffix    = wp_generate_password( 6, false );
$employer  = wp_insert_user( array( 'user_login' => 'cnc-emp-' . $suffix, 'user_pass' => wp_generate_password(), 'user_email' => 'cnc-emp-' . $suffix . '@example.test', 'role' => 'wcb_employer' ) );
$candidate = wp_insert_user( array( 'user_login' => 'cnc-cand-' . $suffix, 'user_pass' => wp_generate_password(), 'user_email' => 'cnc-cand-' . $suffix . '@example.test', 'role' => 'wcb_candidate' ) );
$job       = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'CNC job ' . $suffix, 'post_author' => $employer ) );
$app       = (int) wp_insert_post( array( 'post_type' => 'wcb_application', 'post_status' => 'publish', 'post_title' => 'CNC app', 'post_author' => $candidate ) );
update_post_meta( $app, '_wcb_job_id', $job );
update_post_meta( $app, '_wcb_candidate_id', $candidate );
update_post_meta( $app, '_wcb_status', 'submitted' );

// 1. Payload shape on a real notification, second argument only.
$captured = array();
$listener = static function ( array $legacy, ?array $contract ) use ( &$captured ): void {
	$captured[] = array( $legacy, $contract );
};
add_action( 'wcb_notification_created', $listener, 10, 2 );

ApplicationLifecycle::transition( $app, 'reviewing', 'test', $employer );

wcb_cnc_assert( 1 === count( $captured ), 'status change fires the signal once' );
$contract = $captured[0][1] ?? null;
wcb_cnc_assert( is_array( $contract ), 'contract payload is an array, not null, when object_type is set' );
wcb_cnc_assert( isset( $contract['recipient_id'] ) && $candidate === (int) $contract['recipient_id'], 'recipient_id is the candidate' );
wcb_cnc_assert( isset( $contract['object_type'] ) && 'application' === $contract['object_type'], 'object_type is application' );
wcb_cnc_assert( isset( $contract['object_id'] ) && $app === (int) $contract['object_id'], 'object_id is the application' );
wcb_cnc_assert( isset( $contract['actor_id'] ) && $employer === (int) $contract['actor_id'], 'actor_id is who changed the status' );
wcb_cnc_assert( ! empty( $contract['message'] ) && ! empty( $contract['url'] ), 'message and url are present' );
wcb_cnc_assert( ! empty( $contract['group_key'] ), 'group_key is derived when not explicitly set' );

remove_action( 'wcb_notification_created', $listener, 10 );

// 2. Old (1-arg) listeners are unaffected — never see the second argument.
$legacy_args = null;
$legacy      = static function ( array $legacy_payload ) use ( &$legacy_args ): void {
	$legacy_args = func_num_args();
};
add_action( 'wcb_notification_created', $legacy, 10, 1 );
$captured = array();
ApplicationLifecycle::transition( $app, 'shortlisted', 'test', $employer );
wcb_cnc_assert( 1 === $legacy_args, 'a listener registered with accepted_args=1 receives exactly one argument' );
remove_action( 'wcb_notification_created', $legacy, 10 );

// 3. A transactional email (no object_type) yields a null contract payload.
wcb_cnc_assert(
	null === CommunityNotificationContract::build( $candidate, 'verify-account', 'Verify your account', 'https://example.test/verify' ),
	'no object_type => no contract payload'
);
wcb_cnc_assert(
	null === CommunityNotificationContract::build( 0, 'job-approved', 'x', 'https://example.test/', array( 'object_type' => 'job', 'object_id' => $job ) ),
	'recipient_id <= 0 => no contract payload'
);

// 4. Visibility: trashed hides from everyone; withdrawn hides from the
// employer only, the candidate keeps their own record.
$targets = array(
	'a' => array( 'object_type' => 'application', 'object_id' => $app, 'actor_id' => 0 ),
);
$visible = array_fill_keys( array_keys( $targets ), true );

update_post_meta( $app, '_wcb_status', 'withdrawn' );
$employer_view  = CommunityNotificationContract::filter_visible( $visible, $employer, $targets );
$candidate_view = CommunityNotificationContract::filter_visible( $visible, $candidate, $targets );
wcb_cnc_assert( false === $employer_view['a'], 'a withdrawn application is hidden from the employer' );
wcb_cnc_assert( true === $candidate_view['a'], 'a withdrawn application stays visible to the candidate' );

update_post_meta( $app, '_wcb_status', 'reviewing' );
wp_trash_post( $app );
$trashed_view = CommunityNotificationContract::filter_visible( $visible, $candidate, $targets );
wcb_cnc_assert( false === $trashed_view['a'], 'a trashed application is hidden from everyone' );
wp_untrash_post( $app );
update_post_meta( $app, '_wcb_status', 'reviewing' );

// 5. Removal: wcb_community_notification_removed fires exactly once, on
// permanent delete only (never on trash).
$removed = array();
add_action(
	'wcb_community_notification_removed',
	static function ( string $object_type, int $object_id ) use ( &$removed ): void {
		$removed[] = array( $object_type, $object_id );
	},
	10,
	2
);

wp_trash_post( $app );
wcb_cnc_assert( array() === $removed, 'trashing an application does not fire the removal hook' );
wp_untrash_post( $app );

wp_delete_post( $app, true );
wcb_cnc_assert( array( array( 'application', $app ) ) === $removed, 'permanently deleting an application fires the removal hook once' );

$removed = array();
wp_delete_post( $job, true );
wcb_cnc_assert( array( array( 'job', $job ) ) === $removed, 'permanently deleting a job fires the removal hook once' );

// Teardown.
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( (int) $employer );
wp_delete_user( (int) $candidate );

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All community notification contract tests passed.' );
}
