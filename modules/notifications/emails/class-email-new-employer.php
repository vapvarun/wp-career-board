<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated email class name is intentional.
/**
 * Email: a new employer signed up.
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
 * Tells the site owner that a new employer signed up. On by default: employers post jobs, so the owner wants to know.
 *
 * @since 1.8.4
 */
class EmailNewEmployer extends AbstractEmail {

	/**
	 * Unique email ID used as settings key and template slug.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'new-employer';
	}

	/**
	 * Human-readable title shown in the Emails settings page.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'New Employer', 'wp-career-board' );
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
	 * Default subject line.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( '[New employer] {user_name} joined {site_name}', 'wp-career-board' );
	}

	/**
	 * Default message body.
	 *
	 * @return string
	 */
	public function get_default_body(): string {
		return self::heading( __( 'A new employer signed up', 'wp-career-board' ) )
			. '<p>' . sprintf(
				/* translators: 1: member name (bold), 2: member email */
				esc_html__( '%1$s (%2$s) just created an account.', 'wp-career-board' ),
				'<strong>{user_name}</strong>',
				'{user_email}'
			) . '</p>'
			. self::button( __( 'View employers', 'wp-career-board' ), '{review_url}' );
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
			'review_url' => __( 'Admin list of employers', 'wp-career-board' ),
		);
	}

	/**
	 * Registers action hooks that trigger this email.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'wcb_employer_registered', array( $this, 'handle' ), 20, 1 );
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
				'review_url' => admin_url( 'admin.php?page=wcb-employers' ),
			),
			0
		);
	}
}
