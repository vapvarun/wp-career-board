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
	 * Default message body. Production-ready — ships usable without edits.
	 *
	 * @return string
	 */
	public function get_default_body(): string {
		return self::heading( __( 'Your application status changed', 'wp-career-board' ) )
			. '<p>' . sprintf(
				/* translators: 1: candidate name, 2: job title (bold), 3: new status (bold) */
				esc_html__( 'Hi %1$s, the status of your application for %2$s is now %3$s.', 'wp-career-board' ),
				'{candidate_name}',
				'<strong>{job_title}</strong>',
				'<strong>{new_status}</strong>'
			) . '</p>'
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
			'new_status'     => __( 'New application status', 'wp-career-board' ),
			'dashboard_url'  => __( 'Candidate dashboard URL', 'wp-career-board' ),
		);
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

		$dashboard     = \WCB\Admin\Settings::int( 'candidate_dashboard_page', 0 );
		$dashboard_url = $dashboard > 0 ? (string) get_permalink( $dashboard ) : home_url( '/' );

		$this->send(
			$candidate->user_email,
			array(
				'candidate_name' => $candidate->display_name,
				'job_title'      => $job->post_title,
				'new_status'     => $new_status,
				'dashboard_url'  => $dashboard_url,
			),
			$candidate_id
		);
	}
}
