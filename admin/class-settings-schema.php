<?php
/**
 * Every key in the wcb_settings option: its type, default and sanitizer.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

declare( strict_types=1 );

namespace WCB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one definition of a setting. The sanitizer, the accessor's defaults and
 * install seeding all read from here, so a key's default and its cleaning
 * rule live in one place instead of three that drift.
 *
 * Pro and add-ons register their keys through `wcb_settings_schema`; a key
 * outside the schema is never written by a settings form.
 *
 * @since 1.8.0
 */
final class SettingsSchema {

	/**
	 * Per-request cache.
	 *
	 * @var array<string, array{default: mixed, sanitize: callable}>|null
	 */
	private static ?array $fields = null;

	/**
	 * All fields, keyed by setting key.
	 *
	 * @since 1.8.0
	 * @return array<string, array{default: mixed, sanitize: callable}>
	 */
	public static function fields(): array {
		// Cached only once `init` has run: Pro and add-ons register their keys
		// while plugins load, and an early read must not lock them out.
		if ( null !== self::$fields && did_action( 'init' ) ) {
			return self::$fields;
		}

		$bool     = static fn ( $v ): bool => ! empty( $v ); // '0' from a hidden input is empty().
		$page     = static fn ( $v ): int => max( 0, (int) $v );
		$text     = static fn ( $v ): string => sanitize_text_field( (string) $v );
		$email    = static fn ( $v ): string => sanitize_email( (string) $v );
		$url      = static fn ( $v ): string => esc_url_raw( (string) $v );
		$range    = static fn ( int $min, int $max ): \Closure => static fn ( $v ): int => max( $min, min( $max, (int) $v ) );
		$raw      = static fn ( $v ) => $v; // Already cleaned by its own screen (emails, brand).
		$currency = static function ( $v ): string {
			$code = strtoupper( (string) $v );
			return array_key_exists( $code, AdminSettings::get_currency_catalog() ) ? $code : 'USD';
		};

		$fields = array(
			// Jobs.
			'auto_publish_jobs'          => array( false, $bool ),
			'jobs_per_page'              => array( 10, $range( 1, 100 ) ),
			'jobs_expire_days'           => array( 30, static fn ( $v ): int => max( 1, (int) $v ) ),
			'deadline_auto_close'        => array( false, $bool ),
			'salary_currency'            => array( 'USD', $currency ),
			'apply_featured_days'        => array( 30, $range( 1, 365 ) ),
			// Applications.
			'allow_withdraw'             => array( true, $bool ),
			'apply_resume_required'      => array( true, $bool ),
			'apply_resume_max_mb'        => array( 5, $range( 1, 20 ) ),
			// Accounts.
			'candidate_requires_role'    => array( false, $bool ),
			'require_email_verification' => array( false, $bool ),
			'app_password_login'         => array( false, $bool ),
			// Layout.
			'container_max_width'        => array(
				0,
				// 0 inherits the theme's content width; otherwise clamped to the
				// range the resolver documents.
				static fn ( $v ): int => (int) $v > 0 ? max( 720, min( 1920, (int) $v ) ) : 0,
			),
			// Pages.
			'jobs_archive_page'          => array( 0, $page ),
			'employer_dashboard_page'    => array( 0, $page ),
			'candidate_dashboard_page'   => array( 0, $page ),
			'company_archive_page'       => array( 0, $page ),
			'post_job_page'              => array( 0, $page ),
			'employer_registration_page' => array( 0, $page ),
			'resume_archive_page'        => array( 0, $page ),
			// Emails.
			'notification_email'         => array( '', $email ),
			'from_name'                  => array( '', $text ),
			'from_email'                 => array( '', $email ),
			'emails'                     => array( array(), $raw ),
			'brand'                      => array( array(), $raw ),
			// Mobile app.
			'accent_color'               => array(
				'#2563EB',
				static fn ( $v ): string => preg_match( '/^#[0-9A-Fa-f]{6}$/', (string) $v ) ? strtoupper( (string) $v ) : '#2563EB',
			),
			'logo_url'                   => array( '', $url ),
			'login_bg_url'               => array( '', $url ),
			'dark_mode_default'          => array( false, $bool ),
			'terms_url'                  => array( '', $url ),
			'eula_url'                   => array( '', $url ),
			'guidelines_url'             => array( '', $url ),
			'abuse_contact_email'        => array( '', $email ),
			// Anti-spam.
			'captcha_provider'           => array(
				'none',
				static fn ( $v ): string => in_array( (string) $v, array( 'none', 'turnstile', 'recaptcha', 'recaptcha_v2' ), true ) ? (string) $v : 'none',
			),
			'turnstile_site_key'         => array( '', $text ),
			'turnstile_secret_key'       => array( '', $text ),
			'recaptcha_site_key'         => array( '', $text ),
			'recaptcha_secret_key'       => array( '', $text ),
			'recaptcha_threshold'        => array( 0.5, static fn ( $v ): float => max( 0.0, min( 1.0, (float) $v ) ) ),
			'recaptcha_v2_site_key'      => array( '', $text ),
			'recaptcha_v2_secret_key'    => array( '', $text ),
			// Data.
			'remove_data_on_uninstall'   => array( false, $bool ),
		);

		$out = array();
		foreach ( $fields as $key => $field ) {
			$out[ $key ] = array(
				'default'  => $field[0],
				'sanitize' => $field[1],
			);
		}

		/**
		 * Filter the settings schema.
		 *
		 * Add a key: `$fields['my_key'] = array( 'default' => false, 'sanitize' => 'rest_sanitize_boolean' );`
		 * Keys stay in the one wcb_settings option; a form posts them as
		 * `wcb_settings[my_key]`.
		 *
		 * @since 1.8.0
		 *
		 * @param array<string, array{default: mixed, sanitize: callable}> $out Fields.
		 */
		self::$fields = (array) apply_filters( 'wcb_settings_schema', $out );
		return self::$fields;
	}

	/**
	 * Default for a key (null when the key isn't in the schema).
	 *
	 * @since 1.8.0
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function default_for( string $key ): mixed {
		return self::fields()[ $key ]['default'] ?? null;
	}

	/**
	 * Clean a value for a key; unknown keys pass through untouched.
	 *
	 * @since 1.8.0
	 * @param string $key   Setting key.
	 * @param mixed  $value Raw value.
	 * @return mixed
	 */
	public static function sanitize( string $key, mixed $value ): mixed {
		$field = self::fields()[ $key ] ?? null;
		return null === $field ? $value : call_user_func( $field['sanitize'], $value );
	}

	/**
	 * Hidden inputs every settings form carries: the form marker (so the
	 * sanitizer merges only what this form posted), and a "0" ahead of each
	 * checkbox so an unticked box saves false.
	 *
	 * @since 1.8.0
	 * @param string[] $checkboxes Checkbox keys the form renders.
	 * @return void
	 */
	public static function form_fields( array $checkboxes = array() ): void {
		echo '<input type="hidden" name="wcb_settings[_wcb_form]" value="1">';
		foreach ( $checkboxes as $key ) {
			printf( '<input type="hidden" name="wcb_settings[%s]" value="0">', esc_attr( $key ) );
		}
	}

	/**
	 * Drop the per-request cache (tests; late schema filters).
	 *
	 * @internal
	 * @return void
	 */
	public static function flush(): void {
		self::$fields = null;
	}
}
