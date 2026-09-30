<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated email class name is intentional.
/**
 * "Confirm your email" message sent to new candidate and employer accounts.
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
 * Sent when EmailVerification starts, i.e. when "Require email verification"
 * is on and someone signs up through the plugin's forms or the app.
 *
 * @since 1.8.0
 */
class EmailVerifyAccount extends AbstractEmail {

	/**
	 * Unique email ID.
	 *
	 * @since 1.8.0
	 * @return string
	 */
	public function get_id(): string {
		return 'verify-account';
	}

	/**
	 * Admin-facing title.
	 *
	 * @since 1.8.0
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Confirm Your Email', 'wp-career-board' );
	}

	/**
	 * Recipient type label.
	 *
	 * @since 1.8.0
	 * @return string
	 */
	public function get_recipient(): string {
		return 'member';
	}

	/**
	 * Default subject line.
	 *
	 * @since 1.8.0
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( 'Confirm your email address', 'wp-career-board' );
	}

	/**
	 * Default HTML body.
	 *
	 * @since 1.8.0
	 * @return string
	 */
	public function get_default_body(): string {
		return self::heading( __( 'Confirm your email address', 'wp-career-board' ) )
			. '<p>' . sprintf(
				/* translators: %s: member display name */
				esc_html__( 'Hi %s, thanks for signing up. Confirm this is your email address to activate your account.', 'wp-career-board' ),
				'{display_name}'
			) . '</p>'
			. self::button( __( 'Confirm Email', 'wp-career-board' ), '{verify_url}' )
			. '<p>' . esc_html__( 'The link works for 7 days. If you did not sign up, you can ignore this email.', 'wp-career-board' ) . '</p>';
	}

	/**
	 * Merge tags available in this email.
	 *
	 * @since 1.8.0
	 * @return array<string,string>
	 */
	public function get_merge_tags(): array {
		return array(
			'display_name' => __( 'Member name', 'wp-career-board' ),
			'verify_url'   => __( 'Confirmation link', 'wp-career-board' ),
		);
	}

	/**
	 * Hook into the verification start.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'wcb_email_verification_requested', array( $this, 'handle' ), 10, 2 );
	}

	/**
	 * Send the confirmation link.
	 *
	 * @since 1.8.0
	 * @param int    $user_id    New account.
	 * @param string $verify_url Confirmation link.
	 * @return void
	 */
	public function handle( int $user_id, string $verify_url ): void {
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user instanceof \WP_User ) {
			return;
		}
		$this->send(
			$user->user_email,
			array(
				'display_name' => $user->display_name,
				'verify_url'   => $verify_url,
			),
			$user_id
		);
	}
}
