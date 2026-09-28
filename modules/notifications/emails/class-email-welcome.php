<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated email class name is intentional.
/**
 * Email: welcome a new member.
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
 * Welcome a new member.
 *
 * @since 1.8.0
 */
class EmailWelcome extends AbstractEmail {

	/**
	 * Unique email ID used as settings key and template slug.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'welcome';
	}

	/**
	 * Human-readable title shown in the Emails settings page.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Welcome', 'wp-career-board' );
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
		return __( 'Welcome to {site_name}', 'wp-career-board' );
	}

	/**
	 * Default message body.
	 *
	 * @return string
	 */
	public function get_default_body(): string {
		return self::heading( __( 'Welcome aboard', 'wp-career-board' ) )
			. '<p>' . esc_html__( 'Hi {user_name}, your account is ready. Sign in any time to manage your profile, applications and saved jobs.', 'wp-career-board' ) . '</p>'
			. self::button( __( 'Sign in', 'wp-career-board' ), '{login_url}' );
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
		// With email verification on, the verification email is the welcome.
		if ( ! \WCB\Admin\Settings::bool( 'require_email_verification', false ) ) {
			add_action( 'wcb_candidate_registered', array( $this, 'handle' ), 10, 1 );
			add_action( 'wcb_employer_registered', array( $this, 'handle' ), 10, 1 );
		}
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
