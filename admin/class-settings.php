<?php
/**
 * Centralized accessor for the wcb_settings option.
 *
 * Single read path across Free + Pro. The array_key_exists vs ! empty
 * semantic is uniform across get/bool/int/string accessors so the
 * "absent key default" cannot drift between reader sites again.
 *
 * Writers stay on get_option/update_option directly:
 *   - admin/class-admin-settings.php (sanitizer + page renderer)
 *   - api/endpoints/class-settings-endpoint.php (REST exposes raw shape)
 *
 * @package WP_Career_Board
 * @since   1.2.0
 */

declare( strict_types=1 );

namespace WCB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accessor wrapper around the wcb_settings option.
 *
 * @since 1.2.0
 */
final class Settings {

	private const OPTION_KEY = 'wcb_settings';

	/**
	 * Per-request cache.
	 *
	 * The autoloaded option is already in WP's option cache; this static
	 * avoids the (array) cast + null-coalesce on every accessor call. It
	 * is invalidated via flush_cache() from updated_option / added_option /
	 * deleted_option hooks wired in core/class-plugin.php.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Full settings array. Empty array if the option has never been written.
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		return self::$cache ??= (array) get_option( self::OPTION_KEY, array() );
	}

	/**
	 * Read a setting, returning $fallback when the key is absent.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Value returned when the key is missing.
	 * @return mixed
	 */
	/**
	 * Canonical default for every setting that has one.
	 *
	 * The accessors below used to require a fallback at every call site, so the
	 * default for a key lived in as many places as it was read - and they drifted.
	 * `allow_withdraw` was documented and read as ON in the dashboard and the
	 * applications endpoint, but FALSE in the app-config endpoint, so the mobile
	 * app was told withdrawal was disabled while the website allowed it.
	 * `jobs_per_page` was 10 in the block and 15 in the REST collection, so an
	 * unconfigured site paginated differently depending on which surface asked.
	 *
	 * Values here match the documented defaults in class-admin-settings.php.
	 * Pass a fallback explicitly only when a call site genuinely needs to differ.
	 *
	 * @since 1.7.1
	 * @var array<string,mixed>
	 */
	public const DEFAULTS = array(
		'auto_publish_jobs'     => false,
		'jobs_per_page'         => 10,
		'jobs_expire_days'      => 30,
		'deadline_auto_close'   => false,
		'allow_withdraw'        => true,
		'salary_currency'       => 'USD',
		'apply_resume_required' => true,
		'max_resumes'           => 2,
		'resume_archive_page'   => 0,
	);

	/**
	 * The canonical default for a key, or $given when the caller supplied one.
	 *
	 * @since 1.7.1
	 *
	 * @param  string $key   Setting key.
	 * @param  mixed  $given Caller-supplied fallback, or null to use DEFAULTS.
	 * @return mixed
	 */
	private static function fallback_for( string $key, mixed $given ): mixed {
		if ( null !== $given ) {
			return $given;
		}
		return self::DEFAULTS[ $key ] ?? null;
	}

	public static function get( string $key, mixed $fallback = null ): mixed {
		$settings = self::all();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
	}

	/**
	 * Read a boolean setting.
	 *
	 * The "absent key" case returns $fallback — never conflates "not set"
	 * with "set to false". When the key is present, ! empty() is used so
	 * '0', 0, '', and false all read as false. This matches the WP Settings
	 * API idiom where unchecked checkboxes persist as the string '0' rather
	 * than being omitted.
	 *
	 * @param string $key      Setting key.
	 * @param bool   $fallback Value returned when the key is missing.
	 * @return bool
	 */
	public static function bool( string $key, ?bool $fallback = null ): bool {
		$settings = self::all();
		return array_key_exists( $key, $settings )
			? ! empty( $settings[ $key ] )
			: (bool) self::fallback_for( $key, $fallback );
	}

	/**
	 * Read an integer setting.
	 *
	 * Cast semantics: when the key is present, the stored value is hard-cast
	 * via (int). A stored boolean true reads as 1, false as 0, and numeric
	 * strings like '15' read as 15 — matching the WP Settings API idiom
	 * where checkbox + number fields are persisted interchangeably.
	 *
	 * @param string $key      Setting key.
	 * @param int    $fallback Value returned when the key is missing.
	 * @return int
	 */
	public static function int( string $key, ?int $fallback = null ): int {
		$settings = self::all();
		return array_key_exists( $key, $settings )
			? (int) $settings[ $key ]
			: (int) self::fallback_for( $key, $fallback );
	}

	/**
	 * Read a string setting.
	 *
	 * @param string $key      Setting key.
	 * @param string $fallback Value returned when the key is missing.
	 * @return string
	 */
	public static function string( string $key, string $fallback ): string {
		$settings = self::all();
		return array_key_exists( $key, $settings ) ? (string) $settings[ $key ] : $fallback;
	}

	/**
	 * Drop the per-request cache so the next read re-fetches from the option.
	 *
	 * Public because tests and the option-write hooks (updated_option /
	 * added_option / deleted_option in core/class-plugin.php) need to invoke
	 * it; not intended for Pro consumers or third-party integrations.
	 *
	 * @internal
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		self::$cache = null;
	}
}
