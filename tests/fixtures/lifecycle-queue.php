<?php
/**
 * Queue helpers for the application lifecycle tests.
 *
 * ApplicationLifecycle::dispatch() queues through Action Scheduler when it is
 * loaded (WooCommerce and others ship it) and through WP-Cron otherwise, so a
 * test that only reads WP-Cron fails on one kind of site and passes its "not
 * queued" checks vacuously there. These read and clear both.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ApplicationLifecycle::GROUP is private; it is the plugin slug.
const WCB_TEST_QUEUE_GROUP = 'wp-career-board';

/**
 * Whether a lifecycle event is queued.
 *
 * @param string $hook Event hook.
 * @param array  $args Event arguments.
 * @return bool
 */
function wcb_lc_queued( string $hook, array $args ): bool {
	if ( function_exists( 'as_has_scheduled_action' ) ) {
		return as_has_scheduled_action( $hook, $args, WCB_TEST_QUEUE_GROUP );
	}
	return false !== wp_next_scheduled( $hook, $args );
}

/**
 * Drop a queued lifecycle event from both queues.
 *
 * @param string $hook Event hook.
 * @param array  $args Event arguments.
 * @return void
 */
function wcb_lc_clear( string $hook, array $args ): void {
	wp_clear_scheduled_hook( $hook, $args );
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( $hook, $args, WCB_TEST_QUEUE_GROUP );
	}
}
