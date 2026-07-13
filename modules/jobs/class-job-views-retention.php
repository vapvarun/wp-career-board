<?php
/**
 * Retention pruning for the wcb_job_views analytics table.
 *
 * @package WP_Career_Board
 * @since   1.2.9
 */

declare( strict_types=1 );

namespace WCB\Modules\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedules a daily cron that deletes aged rows from wcb_job_views.
 *
 * The table records one row per job view and had no prune path, so it grew
 * unbounded (DATA-LIFECYCLE §7). Both of its readers — Pro's `job_views_30d`
 * count and `top_jobs_by_views()` aggregate — are bounded to a 30-day window,
 * so rows older than the retention window serve no query and are safe to
 * delete. There is no lifetime per-job view counter anywhere, so a rollup
 * summary would be dead weight; a plain bounded prune is sufficient.
 *
 * @since 1.2.9
 */
final class JobViewsRetention {

	/**
	 * Cron hook name.
	 *
	 * @since 1.2.9
	 * @var string
	 */
	public const HOOK = 'wcb_prune_job_views';

	/**
	 * Max rows deleted per run. Bounds the delete so a huge backlog drains
	 * across ticks instead of one unbounded statement.
	 *
	 * @since 1.2.9
	 * @var int
	 */
	private const BATCH = 5000;

	/**
	 * Boot the retention handler.
	 *
	 * @since 1.2.9
	 * @return void
	 */
	public function boot(): void {
		add_action( self::HOOK, array( $this, 'prune' ) );

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Delete job-view rows older than the retention window.
	 *
	 * @since 1.2.9
	 * @return void
	 */
	public function prune(): void {
		global $wpdb;

		/**
		 * Filter the job-view retention window, in days.
		 *
		 * Rows older than this are pruned. Must stay comfortably above the
		 * 30-day analytics window so the aggregates never lose in-window data.
		 *
		 * @since 1.2.9
		 *
		 * @param int $days Retention window in days. Default 90.
		 */
		$days = (int) apply_filters( 'wcb_job_views_retention_days', 90 );
		if ( $days < 30 ) {
			$days = 30;
		}

		$table = $wpdb->prefix . 'wcb_job_views';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- bounded prune of a plugin-owned analytics table; the viewed_at index makes the range sargable.
		$deleted = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE viewed_at < DATE_SUB( NOW(), INTERVAL %d DAY ) ORDER BY viewed_at ASC LIMIT %d",
				$days,
				self::BATCH
			)
		);

		// A full batch means more aged rows remain; re-arm shortly so the
		// backlog drains without waiting a day. Mirrors JobsExpiry.
		if ( $deleted >= self::BATCH ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::HOOK );
		}
	}
}
