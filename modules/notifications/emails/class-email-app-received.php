<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated email class name is intentional.
/**
 * Email: employer receives a new application.
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
 * Notifies employer when a new application is submitted for their job.
 *
 * @since 1.0.0
 */
class EmailAppReceived extends AbstractEmail {

	/**
	 * Unique email ID used as settings key and template slug.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'application-received';
	}

	/**
	 * Human-readable title shown in the Emails settings page.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Application Received (Employer)', 'wp-career-board' );
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
		return __( 'New application for your job', 'wp-career-board' );
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
	 * Sends the new-application notification to the employer.
	 *
	 * @param int $app_id       Application post ID.
	 * @param int $job_id       Job post ID.
	 * @param int $candidate_id WP user ID of the candidate, or 0 for guests.
	 * @return void
	 */
	public function handle( int $app_id, int $job_id, int $candidate_id ): void {
		$job      = get_post( $job_id );
		$employer = $job instanceof \WP_Post ? get_user_by( 'ID', (int) $job->post_author ) : false;
		if ( ! $job instanceof \WP_Post || ! $employer instanceof \WP_User ) {
			return;
		}

		$wcb_s         = (array) get_option( 'wcb_settings', array() );
		$dashboard     = ! empty( $wcb_s['employer_dashboard_page'] ) ? (int) $wcb_s['employer_dashboard_page'] : 0;
		$dashboard_url = $dashboard > 0 ? (string) get_permalink( $dashboard ) : home_url( '/' );

		if ( $candidate_id > 0 ) {
			$userdata       = get_userdata( $candidate_id );
			$candidate_name = $userdata instanceof \WP_User ? $userdata->display_name : __( 'Guest', 'wp-career-board' );
		} else {
			$meta_name      = (string) get_post_meta( $app_id, '_wcb_guest_name', true );
			$candidate_name = $meta_name ? $meta_name : __( 'Guest', 'wp-career-board' );
		}

		$this->send(
			$employer->user_email,
			array(
				'job_title'      => $job->post_title,
				'candidate_name' => $candidate_name,
				'dashboard_url'  => $dashboard_url,
			),
			$employer->ID
		);
	}
}
