<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated email class name is intentional.
/**
 * Email: the candidate was not selected.
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
 * A respectful "not selected" message, its own template so owners can word
 * it for their board (the generic status email said only "status changed").
 *
 * @since 1.8.0
 */
class EmailAppRejected extends AbstractEmail {

	/**
	 * Unique email ID used as settings key and template slug.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'application-not-selected';
	}

	/**
	 * Human-readable title shown in the Emails settings page.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Application Not Selected', 'wp-career-board' );
	}

	/**
	 * Recipient type.
	 *
	 * @return string
	 */
	public function get_recipient(): string {
		return 'candidate';
	}

	/**
	 * Default subject line.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( 'An update on your application for {job_title}', 'wp-career-board' );
	}

	/**
	 * Default message body.
	 *
	 * @return string
	 */
	public function get_default_body(): string {
		return self::heading( __( 'Thank you for applying', 'wp-career-board' ) )
			. '<p>' . sprintf(
				/* translators: 1: candidate name, 2: job title (bold) */
				esc_html__( 'Hi %1$s, thank you for your interest in %2$s and for the time you put into your application. After careful review, the team has decided to move forward with other candidates for this role.', 'wp-career-board' ),
				'{candidate_name}',
				'<strong>{job_title}</strong>'
			) . '</p>'
			. '<p>' . esc_html__( 'This is not a judgement of your abilities. We encourage you to apply for other roles that fit your experience, and we wish you every success in your search.', 'wp-career-board' ) . '</p>'
			. self::button( __( 'Browse open jobs', 'wp-career-board' ), '{jobs_url}' );
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
			'jobs_url'       => __( 'Job listings URL', 'wp-career-board' ),
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
	 * Send when an application moves to Rejected.
	 *
	 * @param int    $app_id     Application ID.
	 * @param string $old_status Previous status.
	 * @param string $new_status New status.
	 * @return void
	 */
	public function handle( int $app_id, string $old_status, string $new_status ): void {
		if ( \WCB\Modules\Applications\ApplicationStatus::REJECTED !== $new_status ) {
			return;
		}
		$to   = EmailAppStatus::recipient( $app_id );
		$vars = $to ? EmailAppStatus::vars( $app_id, $to ) : null;
		if ( ! $to || ! $vars ) {
			return;
		}
		$archive          = \WCB\Admin\Pages::get_id( 'jobs_archive_page' );
		$vars['jobs_url'] = $archive > 0 ? (string) get_permalink( $archive ) : home_url( '/' );
		$this->send( $to['email'], $vars, $to['user_id'] );
	}
}
