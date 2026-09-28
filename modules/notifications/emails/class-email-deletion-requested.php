<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated email class name is intentional.
/**
 * Email: confirm an account deletion request.
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
 * Confirm an account deletion request.
 *
 * @since 1.8.0
 */
class EmailDeletionRequested extends AbstractEmail {

	/**
	 * Unique email ID used as settings key and template slug.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'account-deletion-requested';
	}

	/**
	 * Human-readable title shown in the Emails settings page.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Account Deletion Requested', 'wp-career-board' );
	}

	/**
	 * Recipient type.
	 *
	 * @return string
	 */
	public function get_recipient(): string {
		return 'member';
	}

	/**
	 * Default subject line.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( 'Your account will be deleted on {delete_date}', 'wp-career-board' );
	}

	/**
	 * Default message body.
	 *
	 * @return string
	 */
	public function get_default_body(): string {
		return self::heading( __( 'We received your request to delete your account', 'wp-career-board' ) )
			. '<p>' . esc_html__( 'Hi {user_name}, your account is locked and will be deleted on {delete_date}. Changed your mind? Sign in again before then to cancel it. If you did not ask for this, contact the site team.', 'wp-career-board' ) . '</p>'
			. self::button( __( 'Sign in to cancel', 'wp-career-board' ), '{login_url}' );
	}

	/**
	 * Merge tags available to this email's subject and body.
	 *
	 * @return array<string, string>
	 */
	public function get_merge_tags(): array {
		return array(
			'user_name' => __( 'Member name', 'wp-career-board' ),
			'delete_date' => __( 'Deletion date', 'wp-career-board' ),
			'login_url' => __( 'Sign-in URL', 'wp-career-board' ),
		);
	}

	/**
	 * Registers action hooks that trigger this email.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'wcb_account_deletion_requested', array( $this, 'handle' ), 10, 2 );
	}

	/**
	 * Send the confirmation with the deletion date.
	 *
	 * @param int $user_id Member.
	 * @param int $when    Deletion time (Unix).
	 * @return void
	 */
	public function handle( int $user_id, int $when = 0 ): void {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return;
		}
		$this->send(
			$user->user_email,
			static fn(): array => array(
				'user_name'   => $user->display_name,
				'delete_date' => wp_date( (string) get_option( 'date_format' ), $when ?: time() ),
				'login_url'   => wp_login_url(),
			),
			$user_id
		);
	}
}
