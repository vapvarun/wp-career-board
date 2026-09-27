<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated email class name is intentional.
/**
 * Email: a candidate withdrew their application.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Notifications\Emails;

use WCB\Modules\Notifications\AbstractEmail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells the employer a candidate withdrew, so they stop reviewing them.
 *
 * @since 1.8.0
 */
class EmailAppWithdrawn extends AbstractEmail {

	/**
	 * Unique email ID used as settings key and template slug.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'application-withdrawn';
	}

	/**
	 * Human-readable title shown in the Emails settings page.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Application Withdrawn (Employer)', 'wp-career-board' );
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
		return __( 'A candidate withdrew their application', 'wp-career-board' );
	}

	/**
	 * Default message body. Production-ready — ships usable without edits.
	 *
	 * @return string
	 */
	public function get_default_body(): string {
		return self::heading( __( 'Application withdrawn', 'wp-career-board' ) )
			. '<p>' . sprintf(
				/* translators: 1: candidate name (bold), 2: job title (bold) */
				esc_html__( '%1$s withdrew their application for %2$s. No action is needed.', 'wp-career-board' ),
				'<strong>{candidate_name}</strong>',
				'<strong>{job_title}</strong>'
			) . '</p>'
			. self::button( __( 'View in Dashboard', 'wp-career-board' ), '{dashboard_url}' );
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
			'dashboard_url'  => __( 'Employer dashboard URL', 'wp-career-board' ),
		);
	}

	/**
	 * Registers action hooks that trigger this email.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'wcb_application_withdrawn', array( $this, 'handle' ), 10, 3 );
	}

	/**
	 * Sends the withdrawal notice to the job's employer.
	 *
	 * @param int $app_id       Application post ID.
	 * @param int $job_id       Job post ID.
	 * @param int $candidate_id WP user ID of the candidate.
	 * @return void
	 */
	public function handle( int $app_id, int $job_id, int $candidate_id ): void {
		unset( $app_id );
		$job      = get_post( $job_id );
		$employer = $job instanceof \WP_Post ? get_user_by( 'ID', (int) $job->post_author ) : false;
		if ( ! $job instanceof \WP_Post || ! $employer instanceof \WP_User ) {
			return;
		}

		$candidate = get_userdata( $candidate_id );
		$dashboard = \WCB\Admin\Pages::get_id( 'employer_dashboard_page' );

		$this->send(
			$employer->user_email,
			array(
				'job_title'      => $job->post_title,
				'candidate_name' => $candidate instanceof \WP_User ? $candidate->display_name : __( 'A candidate', 'wp-career-board' ),
				'dashboard_url'  => $dashboard > 0 ? (string) get_permalink( $dashboard ) : home_url( '/' ),
			),
			$employer->ID
		);
	}
}
