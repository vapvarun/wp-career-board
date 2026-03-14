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
	 * Expire all published jobs whose deadline has passed.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function expire_jobs(): void {
		$jobs = get_posts(
			array(
				'post_type'      => 'wcb_job',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
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

		foreach ( $jobs as $job ) {
			wp_update_post(
				array(
					'ID'          => $job->ID,
					'post_status' => 'wcb_expired',
				)
			);

			do_action( 'wcb_job_expired', $job->ID );
		}
	}
}
