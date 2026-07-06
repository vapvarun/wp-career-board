<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated email class name is intentional.
/**
 * Email: candidate reminder that a bookmarked job's application deadline is near.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Notifications\Emails;

use WCB\Modules\Notifications\AbstractEmail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notifies a candidate that the apply-by date on a bookmarked job is approaching.
 *
 * @since 1.1.0
 */
class EmailDeadlineReminder extends AbstractEmail {

	/**
	 * Unique email ID used as settings key and template slug.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function get_id(): string {
		return 'deadline-reminder';
	}

	/**
	 * Human-readable title shown in the Emails settings page.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Application Deadline Reminder', 'wp-career-board' );
	}

	/**
	 * Recipient role.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function get_recipient(): string {
		return 'candidate';
	}

	/**
	 * Default subject line.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( 'Application deadline approaching for {job_title}', 'wp-career-board' );
	}

	/**
	 * Default message body. Production-ready — ships usable without edits.
	 *
	 * @return string
	 */
	public function get_default_body(): string {
		return self::heading( __( 'Your saved job is closing soon', 'wp-career-board' ) )
			. '<p>' . sprintf(
				/* translators: 1: job title (bold), 2: days left, 3: deadline date */
				esc_html__( 'The application window for %1$s is closing in %2$s day(s). It closes on %3$s.', 'wp-career-board' ),
				'<strong>{job_title}</strong>',
				'{days_left}',
				'{deadline_date}'
			) . '</p>'
			. self::button( __( 'Apply now', 'wp-career-board' ), '{job_url}' );
	}

	/**
	 * Merge tags available to this email's subject and body.
	 *
	 * @return array<string, string>
	 */
	public function get_merge_tags(): array {
		return array(
			'job_title'     => __( 'Job title', 'wp-career-board' ),
			'company_name'  => __( 'Company name', 'wp-career-board' ),
			'days_left'     => __( 'Days until deadline', 'wp-career-board' ),
			'deadline_date' => __( 'Deadline date (formatted)', 'wp-career-board' ),
			'job_url'       => __( 'Job listing URL', 'wp-career-board' ),
		);
	}

	/**
	 * Wire up the action hook.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'wcb_deadline_reminder', array( $this, 'handle' ), 10, 3 );
	}

	/**
	 * Send the reminder.
	 *
	 * @since 1.1.0
	 *
	 * @param int $user_id   Candidate user ID.
	 * @param int $job_id    Job post ID.
	 * @param int $days_left Days until the deadline.
	 * @return void
	 */
	public function handle( int $user_id, int $job_id, int $days_left ): void {
		$user = get_user_by( 'ID', $user_id );
		$job  = get_post( $job_id );
		if ( ! $user instanceof \WP_User || ! $job instanceof \WP_Post || 'wcb_job' !== $job->post_type ) {
			return;
		}

		$deadline_raw  = (string) get_post_meta( $job_id, '_wcb_deadline', true );
		$deadline_date = '' !== $deadline_raw
			? (string) mysql2date( (string) get_option( 'date_format', 'F j, Y' ), $deadline_raw )
			: '';

		$this->send(
			$user->user_email,
			array(
				'job_title'     => $job->post_title,
				'job_url'       => (string) get_permalink( $job_id ),
				'days_left'     => $days_left,
				'deadline_iso'  => $deadline_raw,
				'deadline_date' => $deadline_date,
				'company_name'  => (string) get_post_meta( $job_id, '_wcb_company_name', true ),
			),
			$user->ID
		);
	}
}
