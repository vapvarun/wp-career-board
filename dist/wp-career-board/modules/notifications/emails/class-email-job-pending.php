<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated email class name is intentional.
/**
 * Email: new job pending admin review.
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
 * Notifies admin when a new job needs approval.
 *
 * @since 1.0.0
 */
class EmailJobPending extends AbstractEmail {

	/**
	 * Unique email ID used as settings key and template slug.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'job-pending-review';
	}

	/**
	 * Human-readable title shown in the Emails settings page.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'New Job Pending Review', 'wp-career-board' );
	}

	/**
	 * Recipient type: 'employer', 'candidate', 'admin', or 'guest'.
	 *
	 * @return string
	 */
	public function get_recipient(): string {
		return 'admin';
	}

	/**
	 * Default subject line used when no admin override is saved.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		/* translators: %site_name% is replaced at send time */
		return __( '[Action Required] New job pending approval', 'wp-career-board' );
	}

	/**
	 * Registers action hooks that trigger this email.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'wcb_job_created', array( $this, 'handle' ), 10, 1 );
	}

	/**
	 * Sends the pending-review notification to the admin.
	 *
	 * @param int $job_id Newly created job post ID.
	 * @return void
	 */
	public function handle( int $job_id ): void {
		$job = get_post( $job_id );
		if ( ! $job instanceof \WP_Post || 'pending' !== $job->post_status ) {
			return;
		}

		$wcb_s = (array) get_option( 'wcb_settings', array() );
		$to    = ! empty( $wcb_s['notification_email'] ) ? $wcb_s['notification_email'] : (string) get_option( 'admin_email', '' );

		$this->send(
			$to,
			array(
				'job_title'   => $job->post_title,
				'approve_url' => admin_url( 'post.php?post=' . $job_id . '&action=edit' ),
			),
			0
		);
	}
}
