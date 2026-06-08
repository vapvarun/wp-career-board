<?php
/**
 * Job auto-expiry via WP-Cron.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the wcb_expired post status and schedules a daily cron to
 * transition past-deadline jobs from publish to expired.
 *
 * @since 1.0.0
 */
final class JobsExpiry {

	/**
	 * Boot the expiry handler.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'register_expired_status' ) );
		add_action( 'init', array( $this, 'register_closed_status' ) );
		add_action( 'wcb_check_job_expiry', array( $this, 'expire_jobs' ) );

		if ( ! wp_next_scheduled( 'wcb_check_job_expiry' ) ) {
			wp_schedule_event( time(), 'daily', 'wcb_check_job_expiry' );
		}
	}

	/**
	 * Register the wcb_expired custom post status.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_expired_status(): void {
		register_post_status(
			'wcb_expired',
			array(
				'label'                     => _x( 'Expired', 'job post status', 'wp-career-board' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of expired jobs */
				'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'wp-career-board' ),
			)
		);
	}

	/**
	 * Register the wcb_closed custom post status.
	 *
	 * Used when an employer manually closes a job listing (filled, withdrawn,
	 * etc.). Distinct from `wcb_expired` (auto-transition past deadline) and
	 * `draft` (unsaved/rejected) — keeping these separate lets the dashboard
	 * filter and label each lifecycle outcome correctly.
	 *
	 * @since 1.2.5
	 * @return void
	 */
	public function register_closed_status(): void {
		register_post_status(
			'wcb_closed',
			array(
				'label'                     => _x( 'Closed', 'job post status', 'wp-career-board' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of closed jobs */
				'label_count'               => _n_noop( 'Closed <span class="count">(%s)</span>', 'Closed <span class="count">(%s)</span>', 'wp-career-board' ),
			)
		);
	}

	/**
	 * Expire all published jobs whose deadline has passed.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function expire_jobs(): void {
		if ( ! \WCB\Admin\Settings::bool( 'deadline_auto_close', false ) ) {
			return;
		}

		// Bounded batch: each row triggers wp_update_post() (full save cycle) and
		// a `wcb_job_expired` email, so a `posts_per_page => -1` sweep would blow
		// memory and time out on a large backlog (e.g. first run over an imported
		// board). Process a capped batch and re-arm the cron immediately when the
		// batch is full so the backlog drains across ticks. Mirrors the bounded
		// batch idiom in class-deadline-reminders.php / class-featured-expiry.php.
		$batch = 200;
		$jobs  = get_posts(
			array(
				'post_type'      => 'wcb_job',
				'post_status'    => 'publish',
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded batch; cron re-arms below when full.
				'posts_per_page' => $batch,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_wcb_deadline',
						'value'   => current_time( 'Y-m-d' ),
						'compare' => '<',
						'type'    => 'DATE',
					),
				),
			)
		);

		foreach ( $jobs as $job_id ) {
			wp_update_post(
				array(
					'ID'          => (int) $job_id,
					'post_status' => 'wcb_expired',
				)
			);

			do_action( 'wcb_job_expired', (int) $job_id );
		}

		// Full batch means more past-deadline jobs remain. Each pass flips rows
		// out of `publish`, so the next run's WHERE naturally returns the next
		// set — re-fire the same hook shortly to drain without waiting a day.
		if ( count( $jobs ) === $batch ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'wcb_check_job_expiry' );
		}
	}
}
