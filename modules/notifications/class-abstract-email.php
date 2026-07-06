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
	 * Returns the default HTML message body, used when no admin override is
	 * saved. Contains `{merge_tag}` placeholders substituted at send time and
	 * is wrapped in the branded header/footer — it must NOT include <html>,
	 * header, or footer markup. Written to be production-ready so a site can
	 * ship it untouched.
	 *
	 * @return string
	 */
	abstract public function get_default_body(): string;

	/**
	 * Returns the merge tags this email exposes, as `tag => human label`.
	 * Drives the clickable tag chips on the Emails settings page.
	 *
	 * @return array<string, string>
	 */
	abstract public function get_merge_tags(): array;

	/**
	 * Merge-tag keys whose substituted value is trusted HTML (e.g. a
	 * plugin-rendered list) and must NOT be HTML-escaped. All other scalar
	 * values are escaped before substitution into the body. Defaults to none.
	 *
	 * @return string[]
	 */
	public function get_html_merge_tags(): array {
		return array();
	}

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
		$settings = wcb_get_email_settings();
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
		$settings = wcb_get_email_settings();
		$saved    = $settings[ $this->get_id() ]['subject'] ?? '';
		return $saved ? (string) $saved : $this->get_default_subject();
	}

	/**
	 * Returns the active message body, falling back to get_default_body().
	 *
	 * @return string
	 */
	public function get_body(): string {
		$settings = wcb_get_email_settings();
		$saved    = $settings[ $this->get_id() ]['body'] ?? '';
		return '' !== trim( (string) $saved ) ? (string) $saved : $this->get_default_body();
	}

	/**
	 * Styled heading row for a default body. Shared so the 12 default bodies
	 * stay visually consistent.
	 *
	 * @param string $text Already-translated heading text.
	 * @return string
	 */
	protected static function heading( string $text ): string {
		return '<h2 style="margin:0 0 16px;font-size:18px;color:#111827;">' . esc_html( $text ) . '</h2>';
	}

	/**
	 * Styled call-to-action button for a default body.
	 *
	 * @param string $label Already-translated button label.
	 * @param string $href  URL or `{merge_tag}` placeholder for the link.
	 * @return string
	 */
	protected static function button( string $label, string $href ): string {
		return '<p style="margin-top:24px;"><a href="' . $href . '" style="display:inline-block;padding:10px 20px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">' . esc_html( $label ) . '</a></p>';
	}

	/**
	 * Sends the email and writes a row to wcb_notifications_log.
	 *
	 * No-ops when the template is disabled in settings.
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
		$this->dispatch( $to, $vars, $user_id );
	}

	/**
	 * Public bridge for the admin "Send Test Email" button.
	 *
	 * Bypasses is_enabled() so an admin can preview the rendered template even
	 * when its toggle is off in settings, and always writes a log row marked
	 * 'sent_test' / 'failed_test' so the endpoint's row-count delta detects
	 * the dispatch and reports {sent: true}.
	 *
	 * @since 1.1.1
	 *
	 * @param string               $to      Recipient email address.
	 * @param array<string, mixed> $vars    Template variables passed to render_template().
	 * @param int                  $user_id Optional WP user ID for the log row.
	 * @return bool True when wp_mail() reported a successful handoff.
	 */
	public function test_send( string $to, array $vars, int $user_id = 0 ): bool {
		return $this->dispatch( $to, $vars, $user_id, true );
	}

	/**
	 * Subject substitution + body render + wp_mail + log-row insert. Shared
	 * by send() and test_send().
	 *
	 * @param string               $to       Recipient email address.
	 * @param array<string, mixed> $vars     Template variables.
	 * @param int                  $user_id  Optional WP user ID for the log row.
	 * @param bool                 $is_test  True when called via test_send() —
	 *                                       writes a *_test status so admin
	 *                                       previews don't pollute production
	 *                                       delivery metrics.
	 * @return bool True when wp_mail() reported a successful handoff.
	 */
	private function dispatch( string $to, array $vars, int $user_id, bool $is_test = false ): bool {
		// Subject placeholders (both {key} and {{key}} forms) get substituted
		// from $vars here. The body template already runs through
		// render_template() which extracts $vars into PHP scope and the
		// template echoes them via $candidate_name etc. — different mechanism,
		// same result. Without this line, subjects like "Application deadline
		// approaching for {job_title}" reach recipients verbatim, both in
		// production and via AdminEndpoint::test_send_email (same code path).
		$subject = self::render_string( $this->get_subject(), $vars );
		$body    = $this->render_body( $vars );
		$sent    = wp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );

		$status = $sent ? 'sent' : 'failed';
		if ( $is_test ) {
			$status .= '_test';
		}

		global $wpdb;
		self::ensure_log_table();
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
						'is_test' => $is_test,
					)
				),
				'status'     => $status,
				'sent_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		/*
		 * Fire the centralised notification signal for real sends only (not admin
		 * test previews). Free has no in-app bell, so the email-trigger point is
		 * the notification-worthy moment - this gives BuddyNext parity with Pro's
		 * bell hook on Free-only sites. Additive: the email itself is unaffected.
		 */
		if ( ! $is_test ) {
			$link = '';
			foreach ( array( 'dashboard_url', 'job_url', 'approve_url', 'repost_url', 'link' ) as $wcb_link_key ) {
				if ( ! empty( $vars[ $wcb_link_key ] ) && is_scalar( $vars[ $wcb_link_key ] ) ) {
					$link = (string) $vars[ $wcb_link_key ];
					break;
				}
			}

			/**
			 * Fires after a Career Board notification is created (Free fires this
			 * at the email-trigger point; Pro also fires it from the bell insert).
			 *
			 * @since 1.4.3
			 *
			 * @param array{user_id:int,event_type:string,message:string,link:string,id:int} $notification Notification payload.
			 */
			do_action(
				'wcb_notification_created',
				array(
					'user_id'    => $user_id,
					'event_type' => $this->get_id(),
					'message'    => $subject,
					'link'       => $link,
					'id'         => 0,
				)
			);
		}

		return (bool) $sent;
	}

	/**
	 * Self-heal wp_wcb_notifications_log when it does not exist.
	 *
	 * The activation hook in Install::activate() creates this table via
	 * dbDelta, but a customer who installed Free pre-1.0.x and upgraded
	 * past the install routine, or whose host nuked a custom table during
	 * a migration, can end up missing it. The dispatch path used to insert
	 * silently and email-activity-log row counts stayed at zero. Re-running
	 * dbDelta is idempotent and creates the table when needed.
	 *
	 * @return void
	 */
	private static function ensure_log_table(): void {
		global $wpdb;
		static $checked = false;
		if ( $checked ) {
			return;
		}
		$checked = true;
		$table   = $wpdb->prefix . 'wcb_notifications_log';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return;
		}
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE {$table} (
				id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id      BIGINT UNSIGNED NOT NULL,
				event_type   VARCHAR(80)     NOT NULL,
				channel      VARCHAR(20)     NOT NULL DEFAULT 'email',
				payload      LONGTEXT,
				status       VARCHAR(20)     NOT NULL DEFAULT 'sent',
				sent_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY user_id  (user_id),
				KEY event_type  (event_type)
			) ENGINE=InnoDB {$charset};"
		);
	}

	/**
	 * Substitute `{key}` and `{{key}}` placeholders in a string.
	 *
	 * Used for subject lines and any other plain-string template fragment
	 * that needs merge-tag substitution. The body templates (PHP files
	 * loaded via render_template) use the older `extract($vars)` mechanism
	 * with `<?php echo $candidate_name; ?>` style — different mechanism,
	 * same outcome.
	 *
	 * Both `{job_title}` (single brace, used by deadline-reminder default)
	 * and `{{job_title}}` (double brace, used by other templates) are
	 * handled to keep the existing template authoring conventions working.
	 *
	 * @since 1.1.1
	 *
	 * @param string               $template Template string with placeholders.
	 * @param array<string, mixed> $vars     Map of merge-tag keys to values.
	 * @return string The string with placeholders replaced. Non-scalar
	 *                values are skipped (left as the literal placeholder
	 *                so the renderer can inspect what didn't substitute).
	 */
	public static function render_string( string $template, array $vars ): string {
		foreach ( $vars as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$replacement = (string) $value;
			$template    = str_replace(
				array( '{{' . $key . '}}', '{' . $key . '}' ),
				$replacement,
				$template
			);
		}
		return $template;
	}

	/**
	 * Resolves and renders the message body, wrapped in the branded header/footer.
	 *
	 * Precedence:
	 *   1. Admin-saved body (Emails settings) — the primary configuration surface.
	 *   2. Theme override file ({theme}/wp-career-board/emails/{id}.php) — back-compat.
	 *   3. get_default_body() — the shipped, production-ready default.
	 *
	 * Cases 1 and 3 are merge-tag strings run through render_string(); scalar
	 * values are HTML-escaped first (except get_html_merge_tags() keys, which
	 * carry trusted plugin-rendered HTML such as the job-alert list).
	 *
	 * @param array<string, mixed> $vars Merge-tag values.
	 * @return string Full HTML email.
	 */
	private function render_body( array $vars ): string {
		$settings = wcb_get_email_settings();
		$saved    = isset( $settings[ $this->get_id() ]['body'] ) ? (string) $settings[ $this->get_id() ]['body'] : '';

		if ( '' !== trim( $saved ) ) {
			return self::wrap_body( self::render_string( $saved, $this->body_vars( $vars ) ) );
		}

		$theme_override = get_stylesheet_directory() . '/wp-career-board/emails/' . $this->get_id() . '.php';
		if ( file_exists( $theme_override ) ) {
			return self::render_template( $this->get_id(), $vars );
		}

		return self::wrap_body( self::render_string( $this->get_default_body(), $this->body_vars( $vars ) ) );
	}

	/**
	 * Escapes scalar merge values for safe substitution into the HTML body.
	 * Keys listed in get_html_merge_tags() pass through unescaped (trusted HTML).
	 *
	 * @param array<string, mixed> $vars Raw merge-tag values.
	 * @return array<string, string>
	 */
	private function body_vars( array $vars ): array {
		$html_keys = $this->get_html_merge_tags();
		$out       = array();
		foreach ( $vars as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$out[ $key ] = in_array( $key, $html_keys, true ) ? (string) $value : esc_html( (string) $value );
		}
		return $out;
	}

	/**
	 * Wraps a pre-rendered inner body with the branded header and footer partials.
	 *
	 * @param string $inner Pre-escaped/sanitized inner body HTML.
	 * @return string
	 */
	private static function wrap_body( string $inner ): string {
		$header = WCB_DIR . 'modules/notifications/templates/emails/email-header.php';
		$footer = WCB_DIR . 'modules/notifications/templates/emails/email-footer.php';
		ob_start();
		if ( file_exists( $header ) ) {
			include $header;
		}
		echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inner body is wp_kses_post-sanitized on save (admin) or plugin-authored (default), merge values escaped in body_vars().
		if ( file_exists( $footer ) ) {
			include $footer;
		}
		return (string) ob_get_clean();
	}

	/**
	 * Renders an email template by slug, wrapping it with header and footer partials.
	 *
	 * Retained as the theme-override render path (see render_body()) and for any
	 * theme calling it directly. Theme overrides load from
	 * {theme}/wp-career-board/emails/{slug}.php; additional plugin directories
	 * register via the wcb_email_template_dirs filter.
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
