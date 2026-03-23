<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated abstract name is intentional.
/**
 * Abstract base class for all WCB email notifications.
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
 * Base email — subclasses implement get_id(), get_title(), get_recipient(),
 * get_default_subject(), and boot() (which adds action hooks).
 *
 * @since 1.0.0
 */
abstract class AbstractEmail {

	/**
	 * Returns the unique email ID (used as settings key and log event_type).
	 *
	 * @return string
	 */
	abstract public function get_id(): string;

	/**
	 * Returns the human-readable email title shown in settings.
	 *
	 * @return string
	 */
	abstract public function get_title(): string;

	/**
	 * Returns a description of who receives this email.
	 *
	 * @return string
	 */
	abstract public function get_recipient(): string;

	/**
	 * Returns the default subject line used when no override is saved.
	 *
	 * @return string
	 */
	abstract public function get_default_subject(): string;

	/**
	 * Registers action hooks that trigger this email.
	 *
	 * @return void
	 */
	abstract public function boot(): void;

	/**
	 * Returns true when this email type is enabled in wcb_email_settings.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		$settings = (array) get_option( 'wcb_email_settings', array() );
		return isset( $settings[ $this->get_id() ]['enabled'] )
			? (bool) $settings[ $this->get_id() ]['enabled']
			: true;
	}

	/**
	 * Returns the active subject line, falling back to get_default_subject().
	 *
	 * @return string
	 */
	public function get_subject(): string {
		$settings = (array) get_option( 'wcb_email_settings', array() );
		$saved    = $settings[ $this->get_id() ]['subject'] ?? '';
		return $saved ? (string) $saved : $this->get_default_subject();
	}

	/**
	 * Sends the email and writes a row to wcb_notifications_log.
	 *
	 * @param string               $to      Recipient email address.
	 * @param array<string, mixed> $vars    Template variables passed to render_template().
	 * @param int                  $user_id Optional WP user ID for the log row.
	 * @return void
	 */
	protected function send( string $to, array $vars, int $user_id = 0 ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$subject = $this->get_subject();
		$body    = self::render_template( $this->get_id(), $vars );
		$sent    = wp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );

		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'wcb_notifications_log',
			array(
				'user_id'    => $user_id,
				'event_type' => $this->get_id(),
				'channel'    => 'email',
				'payload'    => (string) wp_json_encode(
					array(
						'to'      => $to,
						'subject' => $subject,
					)
				),
				'status'     => $sent ? 'sent' : 'failed',
				'sent_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Renders an email template by slug, wrapping it with header and footer partials.
	 *
	 * Theme overrides are loaded from {theme}/wp-career-board/emails/{slug}.php.
	 * Additional plugin directories can be registered via the wcb_email_template_dirs filter.
	 *
	 * @param string               $slug Template slug (filename without .php extension).
	 * @param array<string, mixed> $vars Variables extracted into template scope.
	 * @return string Rendered HTML.
	 */
	public static function render_template( string $slug, array $vars ): string {
		$theme_override = get_stylesheet_directory() . '/wp-career-board/emails/' . $slug . '.php';
		$plugin_dirs    = (array) apply_filters(
			'wcb_email_template_dirs',
			array(
				WCB_DIR . 'modules/notifications/templates/emails/',
			)
		);

		// Resolve template file: theme override first, then plugin dirs in order.
		$template_file = '';
		if ( file_exists( $theme_override ) ) {
			$template_file = $theme_override;
		} else {
			foreach ( $plugin_dirs as $dir ) {
				$candidate = trailingslashit( $dir ) . $slug . '.php';
				if ( file_exists( $candidate ) ) {
					$template_file = $candidate;
					break;
				}
			}
		}

		$header = WCB_DIR . 'modules/notifications/templates/emails/email-header.php';
		$footer = WCB_DIR . 'modules/notifications/templates/emails/email-footer.php';

		ob_start();
		if ( file_exists( $header ) ) {
			include $header;
		}
		if ( $template_file ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- intentional template pattern.
			extract( $vars, EXTR_SKIP );
			include $template_file;
		}
		if ( file_exists( $footer ) ) {
			include $footer;
		}
		return (string) ob_get_clean();
	}
}
