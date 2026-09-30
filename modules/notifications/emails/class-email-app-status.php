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
		add_action( 'wcb_application_status_changed', array( $this, 'handle' ), 10, 5 );
	}

	/**
	 * Who hears about an application: the member, or the guest by email.
	 *
	 * @since 1.8.0
	 *
	 * @param int $app_id Application ID.
	 * @return array{email:string, name:string, user_id:int}|null
	 */
	public static function recipient( int $app_id ): ?array {
		$candidate = get_userdata( (int) get_post_meta( $app_id, '_wcb_candidate_id', true ) );
		if ( $candidate instanceof \WP_User ) {
			return array(
				'email'   => $candidate->user_email,
				'name'    => $candidate->display_name,
				'user_id' => (int) $candidate->ID,
			);
		}
		$email = (string) get_post_meta( $app_id, '_wcb_guest_email', true );
		return is_email( $email ) ? array(
			'email'   => $email,
			'name'    => (string) get_post_meta( $app_id, '_wcb_guest_name', true ),
			'user_id' => 0,
		) : null;
	}

	/**
	 * Merge values shared by the status emails.
	 *
	 * @param int                 $app_id Application ID.
	 * @param array{name:string} $to     Recipient.
	 * @return array<string, string>|null Null when the job title is gone.
	 */
	public static function vars( int $app_id, array $to ): ?array {
		// The job may already be deleted (job_removed runs after the delete).
		$job_title = \WCB\Modules\Applications\ApplicationLifecycle::job_title( $app_id );
		if ( '' === $job_title ) {
			return null;
		}
		$dashboard = \WCB\Admin\Pages::get_id( 'candidate_dashboard_page' );
		return array(
			'candidate_name' => $to['name'],
			'job_title'      => $job_title,
			'dashboard_url'  => $dashboard > 0 ? (string) get_permalink( $dashboard ) : home_url( '/' ),
		);
	}

	/**
	 * Tell the candidate (member or guest) about a new status.
	 *
	 * @param int    $app_id     Application ID.
	 * @param string $old_status Previous status.
	 * @param string $new_status New status.
	 * @param string $reason     Machine-readable reason (unused in this email).
	 * @param int    $actor      User who made the change, 0 = system.
	 * @return void
	 */
	public function handle( int $app_id, string $old_status, string $new_status, string $reason = '', int $actor = 0 ): void {
		// Withdrawn: the employer gets EmailAppWithdrawn. Rejected: its own,
		// gentler email (EmailAppRejected).
		if ( in_array( $new_status, array( \WCB\Modules\Applications\ApplicationStatus::WITHDRAWN, \WCB\Modules\Applications\ApplicationStatus::REJECTED ), true ) ) {
			return;
		}
		$to   = self::recipient( $app_id );
		$vars = $to ? self::vars( $app_id, $to ) : null;
		if ( ! $to || ! $vars ) {
			return;
		}
		$this->send(
			$to['email'],
			static fn(): array => $vars + array( 'new_status' => \WCB\Modules\Applications\ApplicationStatus::label( $new_status, \WCB\Modules\Applications\ApplicationStatus::AUDIENCE_CANDIDATE ) ),
			$to['user_id'],
			array(
				'object_type' => 'application',
				'object_id'   => $app_id,
				'actor_id'    => $actor,
			)
		);
	}
}
