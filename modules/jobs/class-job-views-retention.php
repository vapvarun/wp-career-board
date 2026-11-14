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
	 * SQL for "views": one per visitor per job per day, so reloads do not inflate it.
	 * Every reader of wcb_job_views (employer dashboard, Pro analytics) uses it and
	 * aliases the table as `v`, so it stays valid when joined to wp_posts.
	 *
	 * @since 1.8.4
	 */
	public const VISITS_SQL = 'COUNT(DISTINCT v.job_id, DATE(v.viewed_at), COALESCE(v.ip_hash, v.id))';

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
				"DELETE FROM {$table} WHERE viewed_at < %s ORDER BY viewed_at ASC LIMIT %d",
				gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ),
				self::BATCH
			)
		);

		// A full batch means more aged rows remain; re-arm shortly so the
		// backlog drains without waiting a day. Mirrors JobsExpiry.
		if ( $deleted >= self::BATCH ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::HOOK );
		}
	}

	/**
	 * Views in the last N days for a page of jobs, in ONE grouped query.
	 *
	 * The employer dashboard shows this next to the applicant count. The
	 * job_id and viewed_at indexes serve it; never call it per job.
	 *
	 * @since 1.8.4
	 *
	 * @param int[] $job_ids Job IDs on the page.
	 * @param int   $days    Window in days; kept inside the retention window.
	 * @return array<int,int> Job ID => views (jobs with none are absent).
	 */
	public static function counts( array $job_ids, int $days = 30 ): array {
		$job_ids = array_values( array_filter( array_map( 'intval', $job_ids ) ) );
		if ( ! $job_ids ) {
			return array();
		}
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $job_ids ), '%d' ) );
		$visits       = self::VISITS_SQL;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one grouped read of a plugin-owned table; placeholders are generated.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT v.job_id, {$visits} AS cnt FROM {$wpdb->prefix}wcb_job_views v WHERE v.job_id IN ({$placeholders}) AND v.viewed_at > %s GROUP BY v.job_id",
				...array_merge( $job_ids, array( gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) )
			)
		);
		// phpcs:enable
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->job_id ] = (int) $row->cnt;
		}
		return $counts;
	}
}
