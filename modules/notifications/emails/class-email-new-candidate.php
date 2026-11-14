<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated email class name is intentional.
/**
 * Email: a new candidate signed up.
 *
 * @package WP_Career_Board
 * @since   1.8.4
 */

declare( strict_types=1 );

namespace WCB\Modules\Notifications\Emails;

use WCB\Modules\Notifications\AbstractEmail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells the site owner that a new candidate signed up. Off by default.
 *
 * @since 1.8.4
 */
class EmailNewCandidate extends AbstractEmail {

	/**
	 * Unique email ID used as settings key and template slug.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'new-candidate';
	}

	/**
	 * Human-readable title shown in the Emails settings page.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'New Candidate', 'wp-career-board' );
	}

	/**
	 * Recipient type.
	 *
	 * @return string
	 */
	public function get_recipient(): string {
		return 'admin';
	}

	/**
	 * Off until the owner turns it on: a busy board would mail the owner for every candidate.
	 *
	 * @since 1.8.4
	 * @return bool
	 */
	public function default_enabled(): bool {
		return false;
	}

	/**
	 * Default subject line.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( '[New candidate] {user_name} joined {site_name}', 'wp-career-board' );
	}

	/**
	 * Default message body.
	 *
	 * @return string
	 */
	public function get_default_body(): string {
		return self::heading( __( 'A new candidate signed up', 'wp-career-board' ) )
			. '<p>' . sprintf(
				/* translators: 1: member name (bold), 2: member email */
				esc_html__( '%1$s (%2$s) just created an account.', 'wp-career-board' ),
				'<strong>{user_name}</strong>',
				'{user_email}'
			) . '</p>'
			. self::button( __( 'View candidates', 'wp-career-board' ), '{review_url}' );
	}

	/**
	 * Merge tags available to this email's subject and body.
	 *
	 * @return array<string, string>
	 */
	public function get_merge_tags(): array {
		return array(
			'user_name'  => __( 'Member name', 'wp-career-board' ),
			'user_email' => __( 'Member email', 'wp-career-board' ),
			'site_name'  => __( 'Site name', 'wp-career-board' ),
			'review_url' => __( 'Admin list of candidates', 'wp-career-board' ),
		);
	}

	/**
	 * Registers action hooks that trigger this email.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'wcb_candidate_registered', array( $this, 'handle' ), 20, 1 );
	}

	/**
	 * Send to the notification address (or the site admin email).
	 *
	 * @param int $user_id New member.
	 * @return void
	 */
	public function handle( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return;
		}
		$notification_email = \WCB\Admin\Settings::string( 'notification_email', '' );
		$this->send(
			'' !== $notification_email ? $notification_email : (string) get_option( 'admin_email', '' ),
			static fn(): array => array(
				'user_name'  => $user->display_name,
				'user_email' => $user->user_email,
				'site_name'  => (string) get_bloginfo( 'name' ),
				'review_url' => admin_url( 'admin.php?page=wcb-candidates' ),
			),
			0
		);
	}
}
