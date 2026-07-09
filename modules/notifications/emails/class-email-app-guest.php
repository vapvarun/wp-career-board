<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated email class name is intentional.
/**
 * Email: guest applicant confirmation.
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
 * Confirms to a guest (unauthenticated) applicant that their application was received.
 *
 * @since 1.0.0
 */
class EmailAppGuest extends AbstractEmail {

	/**
	 * Unique email ID used as settings key and template slug.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'application-guest-confirmation';
	}

	/**
	 * Human-readable title shown in the Emails settings page.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Application Confirmation (Guest)', 'wp-career-board' );
	}

	/**
	 * Recipient type: 'employer', 'candidate', 'admin', or 'guest'.
	 *
	 * @return string
	 */
	public function get_recipient(): string {
		return 'guest';
	}

	/**
	 * Default subject line used when no admin override is saved.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( 'Your application has been received', 'wp-career-board' );
	}

	/**
	 * Default message body. Production-ready — ships usable without edits.
	 *
	 * @return string
	 */
	public function get_default_body(): string {
		return self::heading( __( 'Application received', 'wp-career-board' ) )
			. '<p>' . sprintf(
				/* translators: 1: applicant name, 2: job title (bold) */
				esc_html__( 'Hi %1$s, thanks for applying for %2$s. We have received your application and shared it with the employer.', 'wp-career-board' ),
				'{guest_name}',
				'<strong>{job_title}</strong>'
			) . '</p>'
			. '<p>' . esc_html__( 'As you applied as a guest, the employer will contact you directly using the details you provided.', 'wp-career-board' ) . '</p>'
			. self::button( __( 'View Job', 'wp-career-board' ), '{job_url}' );
	}

	/**
	 * Merge tags available to this email's subject and body.
	 *
	 * @return array<string, string>
	 */
	public function get_merge_tags(): array {
		return array(
			'guest_name' => __( 'Applicant name', 'wp-career-board' ),
			'job_title'  => __( 'Job title', 'wp-career-board' ),
			'job_url'    => __( 'Job listing URL', 'wp-career-board' ),
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
	 * Sends the application-received confirmation to a guest applicant.
	 *
	 * @param int $app_id       Application post ID.
	 * @param int $job_id       Job post ID.
	 * @param int $candidate_id WP user ID; this handler fires only when 0 (guest).
	 * @return void
	 */
	public function handle( int $app_id, int $job_id, int $candidate_id ): void {
		if ( 0 !== $candidate_id ) {
			return;
		}

		$to = (string) get_post_meta( $app_id, '_wcb_guest_email', true );
		if ( '' === $to ) {
			return;
		}

		$meta_name  = (string) get_post_meta( $app_id, '_wcb_guest_name', true );
		$guest_name = $meta_name ? $meta_name : __( 'Guest', 'wp-career-board' );

		$job = get_post( $job_id );
		if ( ! $job instanceof \WP_Post ) {
			return;
		}

		$this->send(
			$to,
			array(
				'guest_name' => $guest_name,
				'job_title'  => $job->post_title,
				'job_url'    => (string) get_permalink( $job_id ),
			),
			0
		);
	}
}
