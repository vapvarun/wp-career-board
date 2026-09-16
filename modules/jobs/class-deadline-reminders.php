<?php
/**
 * Application deadline reminder cron.
 *
 * Each day, find jobs whose deadline is 3 days or 1 day away, then for every
 * candidate who bookmarked the job but hasn't applied, fire the
 * wcb_deadline_reminder action so EmailDeadlineReminder can send the email.
 * A per-(candidate, job, bucket) user-meta flag prevents duplicate sends if
 * the cron runs more than once a day.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Daily sweeper that emits wcb_deadline_reminder for bookmarked-but-unapplied jobs.
 *
 * @since 1.1.0
 */
final class DeadlineReminders {

	/**
	 * Cron hook name.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const HOOK = 'wcb_send_deadline_reminders';

	/**
	 * Days-out buckets that trigger reminders.
	 *
	 * @since 1.1.0
	 * @var int[]
	 */
	const BUCKETS = array( 3, 1 );

	/**
	 * Boot the module.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'maybe_schedule' ) );
		add_action( self::HOOK, array( $this, 'sweep' ) );
	}

	/**
	 * Schedule the daily cron once.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function maybe_schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Find jobs whose deadline matches a bucket and emit reminders for each
	 * bookmarker who hasn't already applied.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function sweep(): void {
		foreach ( self::BUCKETS as $days_left ) {
			$target_day_iso = gmdate( 'Y-m-d', time() + ( $days_left * DAY_IN_SECONDS ) );

			$jobs = get_posts(
				array(
					'post_type'      => 'wcb_job',
					'post_status'    => 'publish',
					// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded by posts with this exact deadline date.
					'posts_per_page' => 500,
					'fields'         => 'ids',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_wcb_deadline',
							'value' => $target_day_iso,
						),
					),
				)
			);

			foreach ( $jobs as $job_id ) {
				$this->process_job( (int) $job_id, $days_left );
			}
		}
	}

	/**
	 * For one job, emit a reminder per candidate who bookmarked but hasn't applied.
	 *
	 * @since 1.1.0
	 *
	 * @param int $job_id    Job post ID.
	 * @param int $days_left Days remaining bucket.
	 * @return void
	 */
	private function process_job( int $job_id, int $days_left ): void {
		global $wpdb;

		// Bookmarkers — non-unique usermeta `_wcb_bookmark` row per saved job.
		// String compare: add_user_meta stores the job id as a string, and an
		// unquoted %d would make MySQL convert the meta_value column instead of
		// the literal, costing the meta_key index path.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$bookmarker_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_wcb_bookmark' AND meta_value = %s",
				(string) $job_id
			)
		);

		$bookmarker_ids = array_values( array_filter( array_map( 'intval', $bookmarker_ids ) ) );

		if ( empty( $bookmarker_ids ) ) {
			return;
		}

		// Which of those bookmarkers already applied. Resolved in ONE indexed
		// query joining the application's job id to its candidate id, scoped to
		// the bookmarkers we actually hold. This used to fetch every application
		// for the job with posts_per_page => -1 and then call get_post_meta()
		// per row (unbounded load + N+1 inside a cron tick); a popular job near
		// its deadline made that thousands of queries per sweep.
		$wcb_placeholders = implode( ', ', array_fill( 0, count( $bookmarker_ids ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wcb_placeholders is a generated %s list, every value is bound below.
		$applied_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT cand.meta_value
				 FROM {$wpdb->postmeta} job_meta
				 INNER JOIN {$wpdb->postmeta} cand
				         ON cand.post_id = job_meta.post_id
				        AND cand.meta_key = '_wcb_candidate_id'
				 WHERE job_meta.meta_key = '_wcb_job_id'
				   AND job_meta.meta_value = %s
				   AND cand.meta_value IN ( {$wcb_placeholders} )",
				array_merge( array( (string) $job_id ), array_map( 'strval', $bookmarker_ids ) )
			)
		);

		$applied_user_ids = array();
		foreach ( $applied_ids as $wcb_applied_id ) {
			$applied_user_ids[ (int) $wcb_applied_id ] = true;
		}

		$flag_key = '_wcb_deadline_reminded_' . $job_id . '_' . $days_left;

		foreach ( $bookmarker_ids as $user_id ) {
			$user_id = (int) $user_id;
			if ( $user_id <= 0 ) {
				continue;
			}
			if ( isset( $applied_user_ids[ $user_id ] ) ) {
				continue;
			}
			if ( get_user_meta( $user_id, $flag_key, true ) ) {
				continue;
			}

			update_user_meta( $user_id, $flag_key, 1 );

			/**
			 * Fired when a candidate should receive a deadline reminder.
			 *
			 * @since 1.1.0
			 *
			 * @param int $user_id   Candidate user ID.
			 * @param int $job_id    Job post ID.
			 * @param int $days_left Days remaining (3 or 1).
			 */
			do_action( 'wcb_deadline_reminder', $user_id, $job_id, $days_left );
		}
	}
}
