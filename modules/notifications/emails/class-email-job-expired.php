<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated email class name is intentional.
/**
 * Email: employer's job listing has expired.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Notifications\Emails;

use WCB\Modules\Notifications\AbstractEmail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notifies employer when their job listing has expired.
 *
 * @since 1.0.0
 */
class EmailJobExpired extends AbstractEmail {

	/**
	 * Returns the unique email ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'job-expired';
	}

	/**
	 * Returns the human-readable email title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Job Expired', 'wp-career-board' );
	}

	/**
	 * Returns a description of who receives this email.
	 *
	 * @return string
	 */
	public function get_recipient(): string {
		return 'employer';
	}

	/**
	 * Returns the default subject line.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( 'Your job listing has expired', 'wp-career-board' );
	}

	/**
	 * Registers action hooks that trigger this email.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'wcb_job_expired', array( $this, 'handle' ), 10, 1 );
	}

	/**
	 * Sends the job-expired notification to the employer.
	 *
	 * @param int $job_id Expired job post ID.
	 * @return void
	 */
	public function handle( int $job_id ): void {
		$job      = get_post( $job_id );
		$employer = $job instanceof \WP_Post ? get_user_by( 'ID', (int) $job->post_author ) : false;
		if ( ! $job instanceof \WP_Post || ! $employer instanceof \WP_User ) {
			return;
		}

		$wcb_s      = (array) get_option( 'wcb_settings', array() );
		$dashboard  = ! empty( $wcb_s['employer_dashboard_page'] ) ? (int) $wcb_s['employer_dashboard_page'] : 0;
		$repost_url = $dashboard > 0 ? (string) get_permalink( $dashboard ) : '#';

		$this->send(
			$employer->user_email,
			array(
				'job_title'  => $job->post_title,
				'repost_url' => $repost_url,
			),
			$employer->ID
		);
	}
}
