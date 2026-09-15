<?php
/**
 * Cron event tests.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-cron-events.php
 *
 * Every scheduled event this plugin owns, fired the way WP-Cron fires it, with
 * the effect asserted afterwards. These read 0/4 in qa-coverage because nothing
 * exercised them, and a background job nobody tests is one nobody notices has
 * stopped: the failure mode is silence, not an error.
 *
 * Each test creates its own fixture and removes it, so the suite is re-runnable
 * and does not depend on the smoke seed.
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
 * Create a throwaway job.
 *
 * @param  array<string,mixed> $meta   Meta to attach.
 * @param  string              $status Post status.
 * @return int
 */
function wcb_cron_make_job( array $meta = array(), string $status = 'publish' ): int {
	$id = wp_insert_post(
		array(
			'post_type'    => 'wcb_job',
			'post_status'  => $status,
			'post_title'   => 'Cron test job ' . wp_generate_password( 6, false ),
			'post_content' => 'fixture',
		)
	);
	foreach ( $meta as $k => $v ) {
		update_post_meta( (int) $id, $k, $v );
	}
	return (int) $id;
}

WP_CLI::log( '' );
WP_CLI::log( '========================================' );
WP_CLI::log( '  Cron Event Tests' );
WP_CLI::log( '========================================' );

// ---------------------------------------------------------------------------
// Registry: every hook this plugin schedules must be listed, or deactivation
// leaves orphaned events behind. The registry is the teardown's source of truth.
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Cron registry ---' );

$wcb_registered = \WCB\Core\CronRegistry::all();

foreach ( $wcb_registered as $wcb_hook ) {
	wcb_assert( has_action( $wcb_hook ) !== false, "registry hook {$wcb_hook} has a live listener" );
}

// ---------------------------------------------------------------------------
// wcb_check_job_expiry — a published job past its deadline stops being published.
// ---------------------------------------------------------------------------

WP_CLI::log( '--- wcb_check_job_expiry ---' );

// The sweep is gated on deadline_auto_close, which ships OFF - a job past its
// deadline stays published unless the owner opted in. Both states are asserted,
// because "does nothing by default" is the contract, not a bug.
$wcb_settings_backup = get_option( 'wcb_settings', array() );
$wcb_settings_backup = is_string( $wcb_settings_backup ) ? json_decode( $wcb_settings_backup, true ) : $wcb_settings_backup;
$wcb_settings_backup = is_array( $wcb_settings_backup ) ? $wcb_settings_backup : array();

$wcb_off = $wcb_settings_backup;
unset( $wcb_off['deadline_auto_close'] );
update_option( 'wcb_settings', $wcb_off );
\WCB\Admin\Settings::flush_cache();

$wcb_stale_off = wcb_cron_make_job( array( '_wcb_deadline' => gmdate( 'Y-m-d', strtotime( '-10 days' ) ) ) );
do_action( 'wcb_check_job_expiry' );
wcb_assert( 'publish' === get_post_status( $wcb_stale_off ), 'with deadline_auto_close OFF (the default) an overdue job stays published' );
wp_delete_post( $wcb_stale_off, true );

$wcb_on                        = $wcb_settings_backup;
$wcb_on['deadline_auto_close'] = true;
update_option( 'wcb_settings', $wcb_on );
\WCB\Admin\Settings::flush_cache();

$wcb_stale  = wcb_cron_make_job( array( '_wcb_deadline' => gmdate( 'Y-m-d', strtotime( '-10 days' ) ) ) );
$wcb_future = wcb_cron_make_job( array( '_wcb_deadline' => gmdate( 'Y-m-d', strtotime( '+30 days' ) ) ) );

do_action( 'wcb_check_job_expiry' );

wcb_assert( 'publish' !== get_post_status( $wcb_stale ), 'with it ON, a job past its deadline is no longer published' );
wcb_assert( 'publish' === get_post_status( $wcb_future ), 'a job with a future deadline is left alone' );

wp_delete_post( $wcb_stale, true );
wp_delete_post( $wcb_future, true );

update_option( 'wcb_settings', $wcb_settings_backup );
\WCB\Admin\Settings::flush_cache();

// ---------------------------------------------------------------------------
// wcb_expire_featured_jobs — the featured flag clears once featured-until passes.
// ---------------------------------------------------------------------------

WP_CLI::log( '--- wcb_expire_featured_jobs ---' );

// FeaturedExpiry works from _wcb_featured_since plus the apply_featured_days
// window (default 30), not an explicit until-date. Fixtures are dated either
// side of that window rather than either side of "now".
$wcb_feature_days = max( 1, min( 365, \WCB\Admin\Settings::int( 'apply_featured_days', 30 ) ) );

$wcb_was_featured = wcb_cron_make_job(
	array(
		'_wcb_featured'       => '1',
		'_wcb_featured_since' => gmdate( 'Y-m-d H:i:s', time() - ( ( $wcb_feature_days + 5 ) * DAY_IN_SECONDS ) ),
	)
);
$wcb_still_featured = wcb_cron_make_job(
	array(
		'_wcb_featured'       => '1',
		'_wcb_featured_since' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
	)
);

do_action( 'wcb_expire_featured_jobs' );

wcb_assert( '1' !== (string) get_post_meta( $wcb_was_featured, '_wcb_featured', true ), 'an elapsed featured job is demoted' );
wcb_assert( '1' === (string) get_post_meta( $wcb_still_featured, '_wcb_featured', true ), 'a current featured job keeps its flag' );

wp_delete_post( $wcb_was_featured, true );
wp_delete_post( $wcb_still_featured, true );

// ---------------------------------------------------------------------------
// wcb_send_deadline_reminders — runs without fatal and does not re-notify a job
// it has already reminded about. The send itself is covered by the email tests;
// what matters here is that the sweep is idempotent.
// ---------------------------------------------------------------------------

WP_CLI::log( '--- wcb_send_deadline_reminders ---' );

$wcb_soon = wcb_cron_make_job( array( '_wcb_deadline' => gmdate( 'Y-m-d', strtotime( '+2 days' ) ) ) );

do_action( 'wcb_send_deadline_reminders' );
$wcb_first = get_post_meta( $wcb_soon, '_wcb_deadline_reminder_sent', true );

do_action( 'wcb_send_deadline_reminders' );
$wcb_second = get_post_meta( $wcb_soon, '_wcb_deadline_reminder_sent', true );

wcb_assert( true, 'deadline reminder sweep runs without fatal' );
wcb_assert( $wcb_first === $wcb_second, 'deadline reminder sweep is idempotent (no double notify)' );

wp_delete_post( $wcb_soon, true );

// ---------------------------------------------------------------------------
// wcb_prune_job_views — retention actually deletes, and only old rows.
// ---------------------------------------------------------------------------

WP_CLI::log( '--- wcb_prune_job_views ---' );

global $wpdb;

$wcb_views_table = $wpdb->prefix . 'wcb_job_views';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test fixture on a custom table.
$wcb_has_views = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wcb_views_table ) );

if ( $wcb_has_views ) {
	$wcb_view_job = wcb_cron_make_job();

	$wpdb->insert( $wcb_views_table, array( 'job_id' => $wcb_view_job, 'viewed_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-400 days' ) ) ), array( '%d', '%s' ) );
	$wpdb->insert( $wcb_views_table, array( 'job_id' => $wcb_view_job, 'viewed_at' => gmdate( 'Y-m-d H:i:s' ) ), array( '%d', '%s' ) );

	do_action( 'wcb_prune_job_views' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test assertion on a custom table.
	$wcb_old = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wcb_views_table} WHERE job_id = %d AND viewed_at < DATE_SUB(NOW(), INTERVAL 365 DAY)", $wcb_view_job ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test assertion on a custom table.
	$wcb_new = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wcb_views_table} WHERE job_id = %d", $wcb_view_job ) );

	wcb_assert( 0 === $wcb_old, 'prune removes view rows past the retention window' );
	wcb_assert( $wcb_new >= 1, 'prune keeps recent view rows' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->delete( $wcb_views_table, array( 'job_id' => $wcb_view_job ), array( '%d' ) );
	wp_delete_post( $wcb_view_job, true );
} else {
	WP_CLI::log( '  skip: wcb_job_views table absent' );
}

// ---------------------------------------------------------------------------
// wcb_process_account_deletions — a member past their grace date is finalised,
// one still inside it is not. This is the destructive one, so it runs on a
// throwaway user created here and nothing else.
// ---------------------------------------------------------------------------

WP_CLI::log( '--- wcb_process_account_deletions ---' );

require_once ABSPATH . 'wp-admin/includes/user.php';

$wcb_due_id = wp_insert_user(
	array(
		'user_login' => 'wcb_cron_due_' . wp_generate_password( 6, false ),
		'user_pass'  => wp_generate_password( 16 ),
		'user_email' => 'wcb_cron_due_' . wp_generate_password( 6, false ) . '@example.test',
		'role'       => 'wcb_candidate',
	)
);
$wcb_wait_id = wp_insert_user(
	array(
		'user_login' => 'wcb_cron_wait_' . wp_generate_password( 6, false ),
		'user_pass'  => wp_generate_password( 16 ),
		'user_email' => 'wcb_cron_wait_' . wp_generate_password( 6, false ) . '@example.test',
		'role'       => 'wcb_candidate',
	)
);

update_user_meta( $wcb_due_id, '_wcb_deletion_scheduled_at', time() - DAY_IN_SECONDS );
update_user_meta( $wcb_wait_id, '_wcb_deletion_scheduled_at', time() + ( 7 * DAY_IN_SECONDS ) );

do_action( 'wcb_process_account_deletions' );

wcb_assert( false === get_userdata( $wcb_due_id ), 'a member past their grace date is deleted' );
wcb_assert( false !== get_userdata( $wcb_wait_id ), 'a member inside the grace window is kept' );

if ( get_userdata( $wcb_wait_id ) ) {
	wp_delete_user( $wcb_wait_id );
}
if ( get_userdata( $wcb_due_id ) ) {
	wp_delete_user( $wcb_due_id );
}

WP_CLI::log( '' );
WP_CLI::log( '========================================' );
WP_CLI::log( '  Cron Event Test Summary' );
WP_CLI::log( '========================================' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) );
WP_CLI::log( '  Pass:  ' . $GLOBALS['wcb_test_pass'] );
WP_CLI::log( '  Fail:  ' . $GLOBALS['wcb_test_fail'] );
WP_CLI::log( '' );

if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All cron event tests passed.' );
}
