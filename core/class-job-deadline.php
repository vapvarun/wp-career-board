<?php
/**
 * Application-deadline state for a job.
 *
 * @package WP_Career_Board
 * @since   1.7.1
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Answers whether a job is still accepting applications.
 *
 * `_wcb_deadline` was previously only ever formatted for display — every
 * seeker-facing surface printed the date and none compared it to today. A job
 * a day past its deadline therefore looked identical to an open one and the
 * apply endpoint accepted the submission, so candidates wrote applications for
 * roles that had closed.
 *
 * Deliberately independent of the `deadline_auto_close` setting. That setting
 * decides whether the post status also flips to `wcb_expired`; it should not
 * decide whether the UI tells the truth. With auto-close off — the default —
 * the listing stays reachable and simply shows that applications have closed,
 * which is the state the settings screen always described but never had.
 *
 * @since 1.7.1
 */
class JobDeadline {

	/**
	 * Read the stored deadline for a job.
	 *
	 * @since  1.7.1
	 * @param  int $job_id Job post ID.
	 * @return string `Y-m-d` date, or '' when the job carries no deadline.
	 */
	public static function get( int $job_id ): string {
		return (string) get_post_meta( $job_id, '_wcb_deadline', true );
	}

	/**
	 * Whether the job's application deadline is in the past.
	 *
	 * Compares dates, not timestamps: `_wcb_deadline` stores a `Y-m-d` date, so
	 * the deadline day itself is still open and only the day after it closes.
	 * Uses `current_time()` so the comparison happens in the site's timezone
	 * rather than the server's.
	 *
	 * @since  1.7.1
	 * @param  int $job_id Job post ID.
	 * @return bool True when the deadline has passed. False when it has not, and
	 *              whenever the job carries no deadline at all.
	 */
	public static function has_passed( int $job_id ): bool {
		$deadline = self::get( $job_id );

		if ( '' === $deadline ) {
			return false;
		}

		$passed = $deadline < current_time( 'Y-m-d' );

		/**
		 * Filter whether a job's application deadline counts as passed.
		 *
		 * Lets a site keep applications open past the advertised date, or close
		 * them early, without touching the stored deadline.
		 *
		 * @since 1.7.1
		 *
		 * @param bool   $passed   Whether the deadline has passed.
		 * @param int    $job_id   Job post ID.
		 * @param string $deadline Stored `Y-m-d` deadline.
		 */
		return (bool) apply_filters( 'wcb_job_deadline_passed', $passed, $job_id, $deadline );
	}

	/**
	 * Whether the job is still taking applications.
	 *
	 * @since  1.7.1
	 * @param  int $job_id Job post ID.
	 * @return bool
	 */
	public static function accepts_applications( int $job_id ): bool {
		return ! self::has_passed( $job_id );
	}

	/**
	 * Short label for a job whose deadline has passed.
	 *
	 * @since  1.7.1
	 * @return string
	 */
	public static function closed_label(): string {
		return __( 'Applications closed', 'wp-career-board' );
	}
}
