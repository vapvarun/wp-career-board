<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated module name is intentional.
/**
 * Notifications module — registers all Free email classes via wcb_registered_emails filter.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the filter-based email registry and wires each email's action hooks.
 *
 * @since 1.0.0
 */
class NotificationsModule {

	/**
	 * Boot all email notification classes.
	 *
	 * @since 1.0.0
	 */
	public function boot(): void {
		add_filter( 'wcb_registered_emails', array( $this, 'register_emails' ) );
		add_action( 'init', array( $this, 'boot_emails' ) );
	}

	/**
	 * Register Free email objects into the shared registry.
	 *
	 * @since 1.0.0
	 *
	 * @param AbstractEmail[] $emails Existing registered email objects.
	 * @return AbstractEmail[]
	 */
	public function register_emails( array $emails ): array {
		return array_merge(
			$emails,
			array(
				new Emails\EmailJobPending(),
				new Emails\EmailJobApproved(),
				new Emails\EmailJobRejected(),
				new Emails\EmailJobExpired(),
				new Emails\EmailAppReceived(),
				new Emails\EmailAppConfirmation(),
				new Emails\EmailAppGuest(),
				new Emails\EmailAppStatus(),
			)
		);
	}

	/**
	 * Call boot() on each registered email to wire up its action hooks.
	 *
	 * @since 1.0.0
	 */
	public function boot_emails(): void {
		$emails = (array) apply_filters( 'wcb_registered_emails', array() );
		foreach ( $emails as $email ) {
			if ( $email instanceof AbstractEmail ) {
				$email->boot();
			}
		}
	}
}
