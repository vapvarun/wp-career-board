<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- legacy file name kept for backwards compatibility.
/**
 * Legacy email notification class — superseded by NotificationsModule.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 * @deprecated 1.0.0 Use NotificationsModule and the wcb_registered_emails filter instead.
 */

declare( strict_types=1 );

namespace WCB\Modules\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deprecated email driver — kept for backwards compatibility only.
 *
 * All email logic now lives in the individual email classes under
 * WCB\Modules\Notifications\Emails\ and is booted via NotificationsModule.
 *
 * @since      1.0.0
 * @deprecated 1.0.0
 */
final class NotificationsEmail {

	/**
	 * No-op — replaced by NotificationsModule.
	 *
	 * @since      1.0.0
	 * @deprecated 1.0.0
	 *
	 * @return void
	 */
	public function boot(): void {
		_doing_it_wrong(
			__CLASS__ . '::boot',
			esc_html__( 'Use NotificationsModule and the wcb_registered_emails filter instead.', 'wp-career-board' ),
			'1.0.0'
		);
	}
}
