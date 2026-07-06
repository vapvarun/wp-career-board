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
	 * Default message body. Production-ready — ships usable without edits.
	 *
	 * @return string
	 */
	public function get_default_body(): string {
		return self::heading( __( 'Application submitted', 'wp-career-board' ) )
			. '<p>' . sprintf(
				/* translators: 1: candidate name, 2: job title (bold) */
				esc_html__( 'Hi %1$s, your application for %2$s has been submitted successfully. The employer will review it and get back to you.', 'wp-career-board' ),
				'{candidate_name}',
				'<strong>{job_title}</strong>'
			) . '</p>'
			. '<p>' . esc_html__( 'You can track this and all of your applications from your dashboard at any time.', 'wp-career-board' ) . '</p>'
			. self::button( __( 'View My Applications', 'wp-career-board' ), '{dashboard_url}' );
	}

	/**
	 * Merge tags available to this email's subject and body.
	 *
	 * @return array<string, string>
	 */
	public function get_merge_tags(): array {
		return array(
			'candidate_name' => __( 'Candidate name', 'wp-career-board' ),
			'job_title'      => __( 'Job title', 'wp-career-board' ),
			'dashboard_url'  => __( 'Candidate dashboard URL', 'wp-career-board' ),
		);
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

		$dashboard     = \WCB\Admin\Settings::int( 'candidate_dashboard_page', 0 );
		$dashboard_url = $dashboard > 0 ? (string) get_permalink( $dashboard ) : home_url( '/' );

		$this->send(
			$candidate->user_email,
			array(
				'candidate_name' => $candidate->display_name,
				'job_title'      => $job->post_title,
				'dashboard_url'  => $dashboard_url,
			),
			$candidate_id
		);
	}
}
