<?php
/**
 * Centralized accessor for the wcb_settings option.
 *
 * Single read path across Free + Pro. The array_key_exists vs ! empty
 * semantic is uniform across get/bool/int/string accessors so the
 * "absent key default" cannot drift between reader sites again
 * (the bug class behind Basecamp 9863100490).
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
	 * is invalidated via flush_cache() from updated_option / added_option
	 * hooks wired in core/class-plugin.php.
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
	public static function get( string $key, mixed $fallback = null ): mixed {
		$settings = self::all();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
	}

	/**
	 * Read a boolean setting.
	 *
	 * The "absent key" case returns $fallback — never conflates "not set"
	 * with "set to false". When the key is present, ! empty() is used so
	 * '0', 0, '', and false all read as false.
	 *
	 * @param string $key      Setting key.
	 * @param bool   $fallback Value returned when the key is missing.
	 * @return bool
	 */
	public static function bool( string $key, bool $fallback ): bool {
		$settings = self::all();
		return array_key_exists( $key, $settings ) ? ! empty( $settings[ $key ] ) : $fallback;
	}

	/**
	 * Read an integer setting.
	 *
	 * @param string $key      Setting key.
	 * @param int    $fallback Value returned when the key is missing.
	 * @return int
	 */
	public static function int( string $key, int $fallback ): int {
		$settings = self::all();
		return array_key_exists( $key, $settings ) ? (int) $settings[ $key ] : $fallback;
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
	 * Called from updated_option / added_option hooks registered in
	 * core/class-plugin.php so writes to wcb_settings invalidate the cache
	 * for the rest of the request.
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		self::$cache = null;
	}
}
