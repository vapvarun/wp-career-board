<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated email class name is intentional.
/**
 * Email: an employer's job ends in 3 days.
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
 * Warns the employer before a job stops taking applications, with a way to
 * extend it, so a listing does not lapse unnoticed.
 *
 * @since 1.8.0
 */
class EmailJobExpiring extends AbstractEmail {

	/**
	 * Unique email ID used as settings key and template slug.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'job-expiring';
	}

	/**
	 * Human-readable title shown in the Emails settings page.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Job Ending Soon (Employer)', 'wp-career-board' );
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
		return __( 'Your job listing ends soon', 'wp-career-board' );
	}

	/**
	 * Default message body. Production-ready — ships usable without edits.
	 *
	 * @return string
	 */
	public function get_default_body(): string {
		return self::heading( __( 'Your job listing ends soon', 'wp-career-board' ) )
			. '<p>' . sprintf(
				/* translators: 1: job title (bold), 2: end date (bold) */
				esc_html__( '%1$s stops taking applications after %2$s and then leaves the job listings.', 'wp-career-board' ),
				'<strong>{job_title}</strong>',
				'<strong>{deadline_date}</strong>'
			) . '</p>'
			. '<p>' . esc_html__( 'Still hiring? Edit the job and move the deadline to keep it open.', 'wp-career-board' ) . '</p>'
			. self::button( __( 'Extend the listing', 'wp-career-board' ), '{edit_url}' );
	}

	/**
	 * Merge tags available to this email's subject and body.
	 *
	 * @return array<string, string>
	 */
	public function get_merge_tags(): array {
		return array(
			'job_title'     => __( 'Job title', 'wp-career-board' ),
			'deadline_date' => __( 'Last day it takes applications', 'wp-career-board' ),
			'days_left'     => __( 'Days left', 'wp-career-board' ),
			'edit_url'      => __( 'Edit job URL (employer dashboard)', 'wp-career-board' ),
			'job_url'       => __( 'Job page URL', 'wp-career-board' ),
		);
	}

	/**
	 * Registers action hooks that trigger this email.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'wcb_job_expiring_soon', array( $this, 'handle' ), 10, 2 );
	}

	/**
	 * Sends the ending-soon notice to the job's employer.
	 *
	 * @param int $job_id    Job post ID.
	 * @param int $days_left Days until the deadline.
	 * @return void
	 */
	public function handle( int $job_id, int $days_left ): void {
		$job      = get_post( $job_id );
		$employer = $job instanceof \WP_Post ? get_user_by( 'ID', (int) $job->post_author ) : false;
		if ( ! $job instanceof \WP_Post || ! $employer instanceof \WP_User ) {
			return;
		}

		$dashboard = \WCB\Admin\Pages::get_id( 'employer_dashboard_page' );
		$edit_url  = $dashboard > 0 ? add_query_arg( 'edit', $job_id, (string) get_permalink( $dashboard ) ) : home_url( '/' );
		$deadline  = \WCB\Core\JobDeadline::get( $job_id );

		$this->send(
			$employer->user_email,
			array(
				'job_title'     => $job->post_title,
				'deadline_date' => '' !== $deadline ? date_i18n( (string) get_option( 'date_format' ), (int) strtotime( $deadline ) ) : '',
				'days_left'     => $days_left,
				'edit_url'      => $edit_url,
				'job_url'       => (string) get_permalink( $job_id ),
			),
			$employer->ID
		);
	}
}
