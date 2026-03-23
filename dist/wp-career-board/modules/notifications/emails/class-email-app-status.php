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
	 * Unique email ID used as settings key and template slug.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'application-status-changed';
	}

	/**
	 * Human-readable title shown in the Emails settings page.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Application Status Changed', 'wp-career-board' );
	}

	/**
	 * Recipient type: 'employer', 'candidate', 'admin', or 'guest'.
	 *
	 * @return string
	 */
	public function get_recipient(): string {
		return 'candidate';
	}

	/**
	 * Default subject line used when no admin override is saved.
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

		$job_id = (int) get_post_meta( $app_id, '_wcb_job_id', true );
		$job    = $job_id > 0 ? get_post( $job_id ) : null;
		if ( ! $job instanceof \WP_Post ) {
			return;
		}

		$wcb_s         = (array) get_option( 'wcb_settings', array() );
		$dashboard     = ! empty( $wcb_s['candidate_dashboard_page'] ) ? (int) $wcb_s['candidate_dashboard_page'] : 0;
		$dashboard_url = $dashboard > 0 ? (string) get_permalink( $dashboard ) : home_url( '/' );

		$this->send(
			$candidate->user_email,
			array(
				'job_title'     => $job->post_title,
				'new_status'    => $new_status,
				'dashboard_url' => $dashboard_url,
			),
			$candidate_id
		);
	}
}
