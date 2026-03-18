# Email Template System Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the monolithic `NotificationsEmail` class with a proper branded email template system — abstract base class, individual email classes, admin settings page under WCB menu, themed header/footer, and theme override support.

**Architecture:** Each email is a self-registering class extending `AbstractEmail`. The `wcb_registered_emails` filter aggregates all emails (Free + Pro) into a single settings page. Templates are PHP files wrapped in a shared `email-header.php`/`email-footer.php`. Brand settings (logo, color, footer text) and per-email settings (enabled, subject) are stored in the `wcb_email_settings` option.

**Tech Stack:** PHP 8.1+, WordPress `wp_mail()`, WordPress Customizer-style settings pattern, WP Options API

---

## File Structure

```
modules/notifications/
├── class-abstract-email.php          MODIFY (rename from class-notifications-email.php)
├── class-email-settings.php          CREATE — admin settings page renderer + saver
├── class-notifications-module.php    CREATE — boots all 8 email classes via filter
├── emails/
│   ├── class-email-job-pending.php           CREATE
│   ├── class-email-job-approved.php          CREATE
│   ├── class-email-job-rejected.php          CREATE
│   ├── class-email-job-expired.php           CREATE
│   ├── class-email-app-received.php          CREATE
│   ├── class-email-app-confirmation.php      CREATE
│   ├── class-email-app-guest.php             CREATE
│   └── class-email-app-status.php            CREATE
└── templates/emails/
    ├── email-header.php              CREATE — branded wrapper top
    ├── email-footer.php              CREATE — branded wrapper bottom
    ├── job-pending-review.php        MODIFY — use header/footer
    ├── job-approved.php              MODIFY
    ├── job-rejected.php              MODIFY
    ├── job-expired.php               MODIFY
    ├── application-received.php      MODIFY
    ├── application-confirmation.php  MODIFY
    ├── application-guest-confirmation.php  MODIFY
    └── application-status-changed.php     MODIFY

admin/
└── class-admin.php                   MODIFY — add Emails submenu at priority 20
```

---

## Task 1: Abstract base class

**Files:**
- Create: `modules/notifications/class-abstract-email.php`
- Modify: `modules/notifications/class-notifications-email.php` (keep only `boot()` temporarily)

- [ ] Create `modules/notifications/class-abstract-email.php`:

```php
<?php
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
	 * Machine-readable ID matching the template slug.
	 * e.g. 'application-received'
	 *
	 * @since 1.0.0
	 */
	abstract public function get_id(): string;

	/**
	 * Human-readable title shown in the Emails settings page.
	 *
	 * @since 1.0.0
	 */
	abstract public function get_title(): string;

	/**
	 * Who receives this email: 'employer', 'candidate', or 'admin'.
	 *
	 * @since 1.0.0
	 */
	abstract public function get_recipient(): string;

	/**
	 * Default subject line — overridable in admin.
	 *
	 * @since 1.0.0
	 */
	abstract public function get_default_subject(): string;

	/**
	 * Register WordPress action hooks that trigger this email.
	 *
	 * @since 1.0.0
	 */
	abstract public function boot(): void;

	/**
	 * Whether this email is enabled.
	 *
	 * @since 1.0.0
	 */
	public function is_enabled(): bool {
		$settings = (array) get_option( 'wcb_email_settings', array() );
		return isset( $settings[ $this->get_id() ]['enabled'] )
			? (bool) $settings[ $this->get_id() ]['enabled']
			: true;
	}

	/**
	 * Subject line — admin-defined or default.
	 *
	 * @since 1.0.0
	 */
	public function get_subject(): string {
		$settings = (array) get_option( 'wcb_email_settings', array() );
		$saved    = $settings[ $this->get_id() ]['subject'] ?? '';
		return $saved ? (string) $saved : $this->get_default_subject();
	}

	/**
	 * Send email and log result.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $to   Recipient address.
	 * @param array<string, mixed> $vars Template variables.
	 * @param int                  $user_id WP user ID (0 = admin/guest).
	 */
	protected function send( string $to, array $vars, int $user_id = 0 ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$body = self::render_template( $this->get_id(), $vars );
		$sent = wp_mail( $to, $this->get_subject(), $body, array( 'Content-Type: text/html; charset=UTF-8' ) );

		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'wcb_notifications_log',
			array(
				'user_id'    => $user_id,
				'event_type' => $this->get_id(),
				'channel'    => 'email',
				'payload'    => (string) wp_json_encode( array( 'to' => $to, 'subject' => $this->get_subject() ) ),
				'status'     => $sent ? 'sent' : 'failed',
				'sent_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Render a template file wrapped in the email header and footer.
	 *
	 * Lookup order:
	 *   1. yourtheme/wp-career-board/emails/{slug}.php
	 *   2. plugin modules/notifications/templates/emails/{slug}.php
	 *
	 * @since 1.0.0
	 *
	 * @param string               $slug Template slug (without .php).
	 * @param array<string, mixed> $vars Variables extracted into template scope.
	 * @return string Rendered HTML.
	 */
	public static function render_template( string $slug, array $vars ): string {
		$theme_override = get_stylesheet_directory() . '/wp-career-board/emails/' . $slug . '.php';
		$plugin_default = WCB_DIR . 'modules/notifications/templates/emails/' . $slug . '.php';
		$template_file  = file_exists( $theme_override ) ? $theme_override : $plugin_default;

		$header = WCB_DIR . 'modules/notifications/templates/emails/email-header.php';
		$footer = WCB_DIR . 'modules/notifications/templates/emails/email-footer.php';

		ob_start();
		if ( file_exists( $header ) ) {
			include $header;
		}
		if ( file_exists( $template_file ) ) {
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
```

- [ ] Commit: `feat(wcb): email abstract base class`

---

## Task 2: Email header and footer templates

**Files:**
- Create: `modules/notifications/templates/emails/email-header.php`
- Create: `modules/notifications/templates/emails/email-footer.php`

- [ ] Create `modules/notifications/templates/emails/email-header.php`:

```php
<?php
/**
 * Email header template — branded wrapper top.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$wcb_email_s      = (array) get_option( 'wcb_email_settings', array() );
$wcb_header_color = ! empty( $wcb_email_s['brand']['header_color'] ) ? $wcb_email_s['brand']['header_color'] : '#4f46e5';
$wcb_logo_id      = ! empty( $wcb_email_s['brand']['logo_id'] ) ? (int) $wcb_email_s['brand']['logo_id'] : 0;
$wcb_logo_url     = $wcb_logo_id ? (string) wp_get_attachment_image_url( $wcb_logo_id, 'medium' ) : '';
$wcb_site_name    = (string) get_bloginfo( 'name' );
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html( $wcb_site_name ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:32px 16px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;">
      <!-- Header bar -->
      <tr>
        <td style="background:<?php echo esc_attr( $wcb_header_color ); ?>;padding:24px 32px;text-align:center;">
          <?php if ( $wcb_logo_url ) : ?>
            <img src="<?php echo esc_url( $wcb_logo_url ); ?>" alt="<?php echo esc_attr( $wcb_site_name ); ?>" style="max-height:48px;display:inline-block;">
          <?php else : ?>
            <span style="color:#ffffff;font-size:20px;font-weight:700;"><?php echo esc_html( $wcb_site_name ); ?></span>
          <?php endif; ?>
        </td>
      </tr>
      <!-- Body -->
      <tr>
        <td style="padding:32px;">
          <div style="font-size:15px;line-height:1.6;color:#374151;">
```

- [ ] Create `modules/notifications/templates/emails/email-footer.php`:

```php
<?php
/**
 * Email footer template — branded wrapper bottom.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$wcb_email_s   = (array) get_option( 'wcb_email_settings', array() );
$wcb_footer_text = ! empty( $wcb_email_s['brand']['footer_text'] )
	? $wcb_email_s['brand']['footer_text']
	/* translators: %s: site name */
	: sprintf( __( 'You are receiving this email because you have an account on %s.', 'wp-career-board' ), get_bloginfo( 'name' ) );
?>
          </div>
        </td>
      </tr>
      <!-- Footer -->
      <tr>
        <td style="background:#f9fafb;padding:20px 32px;border-top:1px solid #e5e7eb;text-align:center;">
          <p style="margin:0;font-size:12px;color:#6b7280;"><?php echo wp_kses_post( $wcb_footer_text ); ?></p>
          <p style="margin:8px 0 0;font-size:12px;color:#9ca3af;">
            <a href="<?php echo esc_url( home_url() ); ?>" style="color:#6b7280;"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>
          </p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
```

- [ ] Commit: `feat(wcb): email header and footer templates`

---

## Task 3: Eight individual email classes

**Files:**
- Create: `modules/notifications/emails/class-email-job-pending.php`
- Create: `modules/notifications/emails/class-email-job-approved.php`
- Create: `modules/notifications/emails/class-email-job-rejected.php`
- Create: `modules/notifications/emails/class-email-job-expired.php`
- Create: `modules/notifications/emails/class-email-app-received.php`
- Create: `modules/notifications/emails/class-email-app-confirmation.php`
- Create: `modules/notifications/emails/class-email-app-guest.php`
- Create: `modules/notifications/emails/class-email-app-status.php`

All classes follow the same pattern. Shown in full for the first two; remaining follow identical structure.

- [ ] Create `modules/notifications/emails/class-email-job-pending.php`:

```php
<?php
/**
 * Email: new job pending admin review.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Notifications\Emails;

use WCB\Modules\Notifications\AbstractEmail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notifies admin when a new job needs approval.
 *
 * @since 1.0.0
 */
class EmailJobPending extends AbstractEmail {

	public function get_id(): string {
		return 'job-pending-review';
	}

	public function get_title(): string {
		return __( 'New Job Pending Review', 'wp-career-board' );
	}

	public function get_recipient(): string {
		return 'admin';
	}

	public function get_default_subject(): string {
		/* translators: %site_name% is replaced at send time */
		return __( '[Action Required] New job pending approval', 'wp-career-board' );
	}

	public function boot(): void {
		add_action( 'wcb_job_created', array( $this, 'handle' ), 10, 1 );
	}

	/**
	 * @param int $job_id Newly created job post ID.
	 */
	public function handle( int $job_id ): void {
		$job = get_post( $job_id );
		if ( ! $job instanceof \WP_Post || 'pending' !== $job->post_status ) {
			return;
		}

		$wcb_s = (array) get_option( 'wcb_settings', array() );
		$to    = ! empty( $wcb_s['notification_email'] ) ? $wcb_s['notification_email'] : (string) get_option( 'admin_email', '' );

		$this->send(
			$to,
			array(
				'job_title'   => $job->post_title,
				'approve_url' => admin_url( 'post.php?post=' . $job_id . '&action=edit' ),
			),
			0
		);
	}
}
```

- [ ] Create `modules/notifications/emails/class-email-job-approved.php`:

```php
<?php
/**
 * Email: employer's job was approved.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Notifications\Emails;

use WCB\Modules\Notifications\AbstractEmail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 1.0.0
 */
class EmailJobApproved extends AbstractEmail {

	public function get_id(): string { return 'job-approved'; }
	public function get_title(): string { return __( 'Job Approved', 'wp-career-board' ); }
	public function get_recipient(): string { return 'employer'; }
	public function get_default_subject(): string { return __( 'Your job has been approved', 'wp-career-board' ); }

	public function boot(): void {
		add_action( 'wcb_job_approved', array( $this, 'handle' ), 10, 1 );
	}

	public function handle( int $job_id ): void {
		$job      = get_post( $job_id );
		$employer = $job instanceof \WP_Post ? get_user_by( 'ID', (int) $job->post_author ) : false;
		if ( ! $job instanceof \WP_Post || ! $employer instanceof \WP_User ) {
			return;
		}
		$this->send(
			$employer->user_email,
			array( 'job_title' => $job->post_title, 'job_url' => (string) get_permalink( $job_id ) ),
			$employer->ID
		);
	}
}
```

- [ ] Create remaining 6 email classes following the same pattern:

`class-email-job-rejected.php` — hook `wcb_job_rejected(int $job_id, string $reason)`, recipient = employer, vars = `['job_title', 'reason']`

`class-email-job-expired.php` — hook `wcb_job_expired(int $job_id)`, recipient = employer, vars = `['job_title', 'repost_url']` (repost_url from employer dashboard page setting)

`class-email-app-received.php` — hook `wcb_application_submitted(int $app_id, int $job_id, int $candidate_id)`, recipient = employer, vars = `['job_title', 'candidate_name', 'dashboard_url']`

`class-email-app-confirmation.php` — same hook, fires only when candidate_id > 0, recipient = candidate, vars = `['job_title', 'dashboard_url']`

`class-email-app-guest.php` — same hook, fires only when candidate_id === 0, recipient = guest email from meta, vars = `['guest_name', 'job_title', 'job_url']`

`class-email-app-status.php` — hook `wcb_application_status_changed(int $app_id, string $old, string $new)`, recipient = candidate, vars = `['job_title', 'new_status', 'dashboard_url']`

- [ ] Commit: `feat(wcb): individual email classes`

---

## Task 4: Notifications module — boot all email classes via filter

**Files:**
- Create: `modules/notifications/class-notifications-module.php`
- Modify: `modules/notifications/class-notifications-email.php` — gut the class body, delegate to new module

- [ ] Create `modules/notifications/class-notifications-module.php`:

```php
<?php
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
	 * @param AbstractEmail[] $emails
	 * @return AbstractEmail[]
	 */
	public function register_emails( array $emails ): array {
		return array_merge( $emails, array(
			new Emails\EmailJobPending(),
			new Emails\EmailJobApproved(),
			new Emails\EmailJobRejected(),
			new Emails\EmailJobExpired(),
			new Emails\EmailAppReceived(),
			new Emails\EmailAppConfirmation(),
			new Emails\EmailAppGuest(),
			new Emails\EmailAppStatus(),
		) );
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
```

- [ ] Find where `NotificationsEmail` is currently booted in `class-plugin.php` and replace with `NotificationsModule`:

```php
// In class-plugin.php boot_modules() — replace:
( new \WCB\Modules\Notifications\NotificationsEmail() )->boot();
// With:
( new \WCB\Modules\Notifications\NotificationsModule() )->boot();
```

- [ ] Commit: `feat(wcb): notifications module — filter-based email registry`

---

## Task 5: Email settings admin page

**Files:**
- Create: `admin/class-email-settings.php`
- Modify: `admin/class-admin.php:111` — add Emails submenu at priority 20

- [ ] Create `admin/class-email-settings.php`:

```php
<?php
/**
 * Emails settings admin page.
 *
 * Renders brand settings and per-email enable/subject controls.
 * Populated by wcb_registered_emails filter — Pro emails appear automatically.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 1.0.0
 */
class EmailSettings {

	/**
	 * Register admin_init save handler.
	 *
	 * @since 1.0.0
	 */
	public function boot(): void {
		add_action( 'admin_init', array( $this, 'save' ) );
	}

	/**
	 * Render the Emails settings page.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		$settings = (array) get_option( 'wcb_email_settings', array() );
		$brand    = isset( $settings['brand'] ) ? (array) $settings['brand'] : array();
		$emails   = (array) apply_filters( 'wcb_registered_emails', array() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Email Notifications', 'wp-career-board' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'wcb_email_settings_save', 'wcb_email_nonce' ); ?>

				<h2><?php esc_html_e( 'Brand Settings', 'wp-career-board' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Header Color', 'wp-career-board' ); ?></th>
						<td>
							<input type="color" name="wcb_email[brand][header_color]"
								value="<?php echo esc_attr( $brand['header_color'] ?? '#4f46e5' ); ?>">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Logo', 'wp-career-board' ); ?></th>
						<td>
							<input type="number" name="wcb_email[brand][logo_id]"
								value="<?php echo (int) ( $brand['logo_id'] ?? 0 ); ?>"
								placeholder="<?php esc_attr_e( 'Attachment ID', 'wp-career-board' ); ?>">
							<p class="description"><?php esc_html_e( 'Enter the attachment ID of your logo image.', 'wp-career-board' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Footer Text', 'wp-career-board' ); ?></th>
						<td>
							<textarea name="wcb_email[brand][footer_text]" rows="2" style="width:400px"><?php
								echo esc_textarea( $brand['footer_text'] ?? '' );
							?></textarea>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Email Templates', 'wp-career-board' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Email', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Recipient', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Subject', 'wp-career-board' ); ?></th>
							<th><?php esc_html_e( 'Enabled', 'wp-career-board' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $emails as $email ) :
						if ( ! $email instanceof \WCB\Modules\Notifications\AbstractEmail ) { continue; }
						$id      = $email->get_id();
						$saved   = isset( $settings[ $id ] ) ? (array) $settings[ $id ] : array();
						$enabled = isset( $saved['enabled'] ) ? (bool) $saved['enabled'] : true;
						$subject = $saved['subject'] ?? '';
						?>
						<tr>
							<td><strong><?php echo esc_html( $email->get_title() ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( $email->get_recipient() ) ); ?></td>
							<td>
								<input type="text" name="wcb_email[<?php echo esc_attr( $id ); ?>][subject]"
									value="<?php echo esc_attr( $subject ); ?>"
									placeholder="<?php echo esc_attr( $email->get_default_subject() ); ?>"
									style="width:100%;max-width:400px;">
							</td>
							<td>
								<input type="checkbox" name="wcb_email[<?php echo esc_attr( $id ); ?>][enabled]"
									value="1" <?php checked( $enabled ); ?>>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<?php submit_button( __( 'Save Email Settings', 'wp-career-board' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save posted email settings.
	 *
	 * @since 1.0.0
	 */
	public function save(): void {
		if ( ! isset( $_POST['wcb_email_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wcb_email_nonce'] ) ), 'wcb_email_settings_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'wcb_manage_settings' ) ) {
			return;
		}

		$raw      = isset( $_POST['wcb_email'] ) ? (array) wp_unslash( $_POST['wcb_email'] ) : array();
		$settings = array();

		// Brand settings.
		if ( isset( $raw['brand'] ) ) {
			$brand = (array) $raw['brand'];
			$settings['brand'] = array(
				'header_color' => sanitize_hex_color( $brand['header_color'] ?? '#4f46e5' ) ?: '#4f46e5',
				'logo_id'      => absint( $brand['logo_id'] ?? 0 ),
				'footer_text'  => wp_kses_post( $brand['footer_text'] ?? '' ),
			);
		}

		// Per-email settings — only save keys that match registered emails.
		$emails = (array) apply_filters( 'wcb_registered_emails', array() );
		foreach ( $emails as $email ) {
			if ( ! $email instanceof \WCB\Modules\Notifications\AbstractEmail ) {
				continue;
			}
			$id = $email->get_id();
			$settings[ $id ] = array(
				'enabled' => ! empty( $raw[ $id ]['enabled'] ),
				'subject' => sanitize_text_field( $raw[ $id ]['subject'] ?? '' ),
			);
		}

		update_option( 'wcb_email_settings', $settings );
		add_action( 'admin_notices', static function () {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Email settings saved.', 'wp-career-board' ) . '</p></div>';
		} );
	}
}
```

- [ ] Add Emails submenu to `admin/class-admin.php` — add a new method and hook it at priority 20:

```php
// Add to boot():
add_action( 'admin_menu', array( $this, 'register_emails_submenu' ), 20 );
// Also boot EmailSettings in boot():
( new EmailSettings() )->boot();

// New method:
public function register_emails_submenu(): void {
    add_submenu_page(
        'wp-career-board',
        __( 'Emails', 'wp-career-board' ),
        __( 'Emails', 'wp-career-board' ),
        'wcb_manage_settings',
        'wcb-emails',
        array( new EmailSettings(), 'render' )
    );
}
```

- [ ] Commit: `feat(wcb): email settings admin page with brand + per-email controls`

---

## Task 6: Upgrade all 8 email templates to use header/footer

**Files:** Modify all 8 in `modules/notifications/templates/emails/`

Note: The templates are now loaded by `AbstractEmail::render_template()` which wraps them in header/footer automatically. The templates themselves just output the body content — no `<html>` tags needed.

- [ ] Upgrade `modules/notifications/templates/emails/application-received.php`:

```php
<?php
/**
 * Email template: employer receives a new application.
 *
 * Available variables: $job_title (string), $candidate_name (string), $dashboard_url (string).
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<h2 style="margin:0 0 16px;font-size:18px;color:#111827;"><?php esc_html_e( 'New Application Received', 'wp-career-board' ); ?></h2>
<p><?php
	/* translators: 1: candidate name, 2: job title */
	printf( esc_html__( '%1$s has applied for %2$s.', 'wp-career-board' ), '<strong>' . esc_html( $candidate_name ) . '</strong>', '<strong>' . esc_html( $job_title ) . '</strong>' );
?></p>
<p style="margin-top:24px;">
	<a href="<?php echo esc_url( $dashboard_url ); ?>"
		style="display:inline-block;padding:10px 20px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">
		<?php esc_html_e( 'View in Dashboard', 'wp-career-board' ); ?>
	</a>
</p>
```

- [ ] Upgrade remaining 7 templates following the same pattern — proper `<h2>` heading, `<p>` body text using `printf()` with i18n, and a styled CTA button `<a>` where a URL is available. No `<html>` wrapper (handled by header/footer).

Template list and available vars:
- `job-pending-review.php` — vars: `$job_title`, `$approve_url`
- `job-approved.php` — vars: `$job_title`, `$job_url`
- `job-rejected.php` — vars: `$job_title`, `$reason`
- `job-expired.php` — vars: `$job_title`, `$repost_url`
- `application-confirmation.php` — vars: `$job_title`, `$dashboard_url`
- `application-guest-confirmation.php` — vars: `$guest_name`, `$job_title`, `$job_url`
- `application-status-changed.php` — vars: `$job_title`, `$new_status`, `$dashboard_url`

- [ ] Commit: `feat(wcb): branded email templates — header/footer + styled body`

---

## Verification

1. Navigate to **Career Board → Emails** in WP admin — settings page renders, brand settings save correctly
2. Change header color, save — send a test email (trigger a job pending event), verify the email arrives with the new color
3. Disable one email in admin, trigger its event — email should NOT be sent, but IS logged as `failed`
4. Edit a subject line, save — verify the custom subject appears in the sent email
5. Create `wp-content/themes/your-theme/wp-career-board/emails/application-received.php` — verify it overrides the plugin template
6. Activate a Pro plugin (if available) — verify Pro emails appear in the same settings page table via `wcb_registered_emails` filter
