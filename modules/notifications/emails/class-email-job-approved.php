<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated email class name is intentional.
/**
 * Email: employer's job was approved.
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
 * Notifies employer when their job listing has been approved.
 *
 * @since 1.0.0
 */
class EmailJobApproved extends AbstractEmail {

	/**
	 * Unique email ID used as settings key and template slug.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'job-approved';
	}

	/**
	 * Human-readable title shown in the Emails settings page.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Job Approved', 'wp-career-board' );
	}

	/**
	 * Recipient type: 'employer', 'candidate', 'admin', or 'guest'.
	 *
	 * @return string
	 */
	public function get_recipient(): string {
		return 'employer';
	}

	/**
	 * Default subject line used when no admin override is saved.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( 'Your job has been approved', 'wp-career-board' );
	}

	/**
	 * Registers action hooks that trigger this email.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'wcb_job_approved', array( $this, 'handle' ), 10, 1 );
		add_action( 'transition_post_status', array( $this, 'on_status_transition' ), 10, 3 );
	}

	/**
	 * Fires wcb_job_approved when an admin manually publishes a pending job.
	 *
	 * Covers the case where the admin uses the standard WP edit screen
	 * instead of the Approve button (which triggers the REST endpoint).
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Previous post status.
	 * @param \WP_Post $post       Post object.
	 * @return void
	 */
	public function on_status_transition( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( 'publish' !== $new_status || 'pending' !== $old_status || 'wcb_job' !== $post->post_type ) {
			return;
		}

		do_action( 'wcb_job_approved', $post->ID );
	}

	/**
	 * Sends the job-approved notification to the employer.
	 *
	 * @param int $job_id Approved job post ID.
	 * @return void
	 */
	public function handle( int $job_id ): void {
		$job      = get_post( $job_id );
		$employer = $job instanceof \WP_Post ? get_user_by( 'ID', (int) $job->post_author ) : false;
		if ( ! $job instanceof \WP_Post || ! $employer instanceof \WP_User ) {
			return;
		}
		$this->send(
			$employer->user_email,
			array(
				'job_title' => $job->post_title,
				'job_url'   => (string) get_permalink( $job_id ),
			),
			$employer->ID
		);
	}
}
