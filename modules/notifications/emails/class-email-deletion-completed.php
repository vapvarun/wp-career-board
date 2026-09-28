<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated email class name is intentional.
/**
 * Email: confirm an account was deleted.
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
 * Confirm an account was deleted.
 *
 * @since 1.8.0
 */
class EmailDeletionCompleted extends AbstractEmail {

	/**
	 * Unique email ID used as settings key and template slug.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'account-deletion-completed';
	}

	/**
	 * Human-readable title shown in the Emails settings page.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Account Deleted', 'wp-career-board' );
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
		return __( 'Your account has been deleted', 'wp-career-board' );
	}

	/**
	 * Default message body.
	 *
	 * @return string
	 */
	public function get_default_body(): string {
		return self::heading( __( 'Your account has been deleted', 'wp-career-board' ) )
			. '<p>' . esc_html__( 'Hi {user_name}, as you asked, your account and personal data on {site_name} have been deleted. Employers keep past applications without your name or contact details.', 'wp-career-board' ) . '</p>';
	}

	/**
	 * Merge tags available to this email's subject and body.
	 *
	 * @return array<string, string>
	 */
	public function get_merge_tags(): array {
		return array(
			'user_name' => __( 'Member name', 'wp-career-board' ),
			'site_name' => __( 'Site name', 'wp-career-board' ),
			'login_url' => __( 'Sign-in URL', 'wp-career-board' ),
		);
	}

	/**
	 * Registers action hooks that trigger this email.
	 *
	 * @return void
	 */
	public function boot(): void {
		// Fires before the user row goes, while the address is still known.
		add_action( 'wcb_account_deletion_executing', array( $this, 'handle' ), 10, 1 );
	}

	/**
	 * Send to the member.
	 *
	 * @param int $user_id Member.
	 * @return void
	 */
	public function handle( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return;
		}
		$this->send(
			$user->user_email,
			array(
				'user_name' => $user->display_name,
				'site_name' => (string) get_bloginfo( 'name' ),
				'login_url' => wp_login_url(),
			),
			$user_id
		);
	}
}
