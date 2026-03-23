<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated email class name is intentional.
/**
 * Email: candidate application confirmation.
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
 * Confirms to a registered candidate that their application was submitted.
 *
 * @since 1.0.0
 */
class EmailAppConfirmation extends AbstractEmail {

	/**
	 * Unique email ID used as settings key and template slug.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'application-confirmation';
	}

	/**
	 * Human-readable title shown in the Emails settings page.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Application Confirmation (Candidate)', 'wp-career-board' );
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
		return __( 'Application submitted successfully', 'wp-career-board' );
	}

	/**
	 * Registers action hooks that trigger this email.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'wcb_application_submitted', array( $this, 'handle' ), 10, 3 );
	}

	/**
	 * Sends the application-confirmation email to the candidate.
	 *
	 * @param int $app_id       Application post ID.
	 * @param int $job_id       Job post ID.
	 * @param int $candidate_id WP user ID of the candidate; skipped when 0 (guest).
	 * @return void
	 */
	public function handle( int $app_id, int $job_id, int $candidate_id ): void {
		if ( $candidate_id <= 0 ) {
			return;
		}

		$candidate = get_userdata( $candidate_id );
		if ( ! $candidate instanceof \WP_User ) {
			return;
		}

		$job = get_post( $job_id );
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
				'dashboard_url' => $dashboard_url,
			),
			$candidate_id
		);
	}
}
