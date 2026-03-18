<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated email class name is intentional.
/**
 * Email: application status changed.
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
 * Notifies candidate when their application status is updated.
 *
 * @since 1.0.0
 */
class EmailAppStatus extends AbstractEmail {

	/**
	 * Returns the unique email ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'application-status-changed';
	}

	/**
	 * Returns the human-readable email title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Application Status Changed', 'wp-career-board' );
	}

	/**
	 * Returns a description of who receives this email.
	 *
	 * @return string
	 */
	public function get_recipient(): string {
		return 'candidate';
	}

	/**
	 * Returns the default subject line.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( 'Your application status has been updated', 'wp-career-board' );
	}

	/**
	 * Registers action hooks that trigger this email.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'wcb_application_status_changed', array( $this, 'handle' ), 10, 3 );
	}

	/**
	 * Sends the status-change notification to the candidate.
	 *
	 * @param int    $app_id     Application post ID.
	 * @param string $old_status Previous application status.
	 * @param string $new_status New application status.
	 * @return void
	 */
	public function handle( int $app_id, string $old_status, string $new_status ): void {
		$candidate_id = (int) get_post_meta( $app_id, '_wcb_candidate_id', true );
		if ( $candidate_id <= 0 ) {
			return;
		}

		$candidate = get_userdata( $candidate_id );
		if ( ! $candidate instanceof \WP_User ) {
			return;
		}

		$job_id    = (int) get_post_meta( $app_id, '_wcb_job_id', true );
		$job       = $job_id > 0 ? get_post( $job_id ) : false;
		$job_title = $job instanceof \WP_Post ? $job->post_title : '';

		$wcb_s         = (array) get_option( 'wcb_settings', array() );
		$dashboard     = ! empty( $wcb_s['candidate_dashboard_page'] ) ? (int) $wcb_s['candidate_dashboard_page'] : 0;
		$dashboard_url = $dashboard > 0 ? (string) get_permalink( $dashboard ) : '#';

		$this->send(
			$candidate->user_email,
			array(
				'job_title'     => $job_title,
				'new_status'    => $new_status,
				'dashboard_url' => $dashboard_url,
			),
			$candidate_id
		);
	}
}
