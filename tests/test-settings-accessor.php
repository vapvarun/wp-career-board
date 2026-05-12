<?php
/**
 * Tests for WCB\Admin\Settings accessor (U9).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-settings-accessor.php
 *
 * Covers the array_key_exists vs ! empty semantic split that motivated the
 * accessor: apply_resume_required toggle defaulted to OFF on fresh install
 * because the page renderer used ! empty while the REST validator used
 * array_key_exists. Every accessor in the new class uses array_key_exists
 * for absent-key defaulting; bool() additionally uses ! empty for the
 * present-but-falsy case so '0' / 0 / '' read false.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	echo "This file must be run via wp eval-file.\n";
	exit( 1 );
}

use WCB\Admin\Settings;

$GLOBALS['wcb_settings_test_pass'] = 0;
$GLOBALS['wcb_settings_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_settings_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		WP_CLI::log( "  PASS: {$label}" );
		++$GLOBALS['wcb_settings_test_pass'];
	} else {
		WP_CLI::warning( "  FAIL: {$label}" );
		++$GLOBALS['wcb_settings_test_fail'];
	}
}

/**
 * Snapshot the live wcb_settings option so tests can mutate it freely
 * without leaking state into the dev install.
 *
 * @return array<string,mixed>
 */
function wcb_settings_test_snapshot(): array {
	return (array) get_option( 'wcb_settings', array() );
}

/**
 * Restore wcb_settings to a prior snapshot.
 *
 * @param array<string,mixed> $snapshot Settings snapshot.
 * @return void
 */
function wcb_settings_test_restore( array $snapshot ): void {
	update_option( 'wcb_settings', $snapshot );
	Settings::flush_cache();
}

/**
 * Set wcb_settings to a known value and flush the accessor cache.
 *
 * @param array<string,mixed> $value Settings value.
 * @return void
 */
function wcb_settings_test_set( array $value ): void {
	update_option( 'wcb_settings', $value );
	Settings::flush_cache();
}

WP_CLI::log( '=== WCB\\Admin\\Settings accessor tests ===' );

// Sanity: class loaded.
wcb_settings_assert( class_exists( Settings::class ), 'WCB\\Admin\\Settings class is loaded' );

// Snapshot live state — restored at the end.
$wcb_settings_snapshot = wcb_settings_test_snapshot();

// 1. bool() returns $default when key is absent.
wcb_settings_test_set( array() );
wcb_settings_assert(
	true === Settings::bool( 'apply_resume_required', true ),
	'bool() returns default true when key absent'
);
wcb_settings_assert(
	false === Settings::bool( 'auto_publish_jobs', false ),
	'bool() returns default false when key absent'
);

// 2. bool() respects an explicit OFF.
wcb_settings_test_set( array( 'apply_resume_required' => false ) );
wcb_settings_assert(
	false === Settings::bool( 'apply_resume_required', true ),
	'bool() returns false when key is explicitly false (despite default true)'
);

// 2b. bool() respects an explicit '0' string (Settings API checkbox idiom).
wcb_settings_test_set( array( 'apply_resume_required' => '0' ) );
wcb_settings_assert(
	false === Settings::bool( 'apply_resume_required', true ),
	"bool() returns false when key is '0' string"
);

// 3. get() returns default when missing.
wcb_settings_test_set( array() );
wcb_settings_assert(
	'USD' === Settings::get( 'salary_currency', 'USD' ),
	'get() returns default when key absent'
);

// 4. get() returns the value when present.
wcb_settings_test_set( array( 'salary_currency' => 'INR' ) );
wcb_settings_assert(
	'INR' === Settings::get( 'salary_currency', 'USD' ),
	'get() returns stored value when key present'
);

// 5. int() returns default and value with cast.
wcb_settings_test_set( array( 'jobs_per_page' => '15' ) );
wcb_settings_assert(
	15 === Settings::int( 'jobs_per_page', 10 ),
	"int() casts string '15' to int 15"
);
wcb_settings_assert(
	25 === Settings::int( 'missing_key', 25 ),
	'int() returns default when key absent'
);

// 6. string() returns default and value.
wcb_settings_test_set( array( 'from_email' => 'foo@bar.com' ) );
wcb_settings_assert(
	'foo@bar.com' === Settings::string( 'from_email', '' ),
	'string() returns stored value when key present'
);
wcb_settings_assert(
	'fallback' === Settings::string( 'missing_key', 'fallback' ),
	'string() returns default when key absent'
);

// 7. all() returns the full array.
wcb_settings_test_set( array( 'foo' => 'bar' ) );
wcb_settings_assert(
	array( 'foo' => 'bar' ) === Settings::all(),
	'all() returns the full settings array'
);

// 8. Per-request cache holds across reads but flushes on demand.
//
// Production wires updated_option / added_option to flush the cache so a
// concurrent settings save reflects on the next read. This test verifies
// the cache layer directly: the option is mutated below WordPress's
// option hooks (via the in-memory wp_cache_set) so the auto-flush doesn't
// fire and we can prove the per-request cache is the thing keeping the
// reads consistent.
wcb_settings_test_set( array( 'foo' => 'bar' ) );
$wcb_first = Settings::all();
// Mutate the option in WP's object cache directly (bypassing update_option's
// updated_option hook, which would otherwise flush the accessor cache).
wp_cache_set( 'wcb_settings', array( 'foo' => 'baz' ), 'options' );
$wcb_alloptions = wp_cache_get( 'alloptions', 'options' );
if ( is_array( $wcb_alloptions ) ) {
	$wcb_alloptions['wcb_settings'] = serialize( array( 'foo' => 'baz' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Mirrors WP's own alloptions cache shape.
	wp_cache_set( 'alloptions', $wcb_alloptions, 'options' );
}
$wcb_second = Settings::all();
wcb_settings_assert(
	$wcb_first === $wcb_second,
	'all() per-request cache holds until flush_cache() runs'
);
Settings::flush_cache();
$wcb_third = Settings::all();
wcb_settings_assert(
	array( 'foo' => 'baz' ) === $wcb_third,
	'all() returns the new value after flush_cache()'
);

// 9. Cache auto-flushes on update_option (the hook wired in core/class-plugin.php).
wcb_settings_test_set( array( 'auto_flush_test' => 'before' ) );
$wcb_before = Settings::get( 'auto_flush_test', '' );
update_option( 'wcb_settings', array( 'auto_flush_test' => 'after' ) );
$wcb_after = Settings::get( 'auto_flush_test', '' );
wcb_settings_assert(
	'before' === $wcb_before && 'after' === $wcb_after,
	'updated_option hook flushes the accessor cache automatically'
);

// 10. Cache auto-flushes on delete_option (uninstall / reset / debug tooling).
wcb_settings_test_set( array( 'foo' => 'bar' ) );
Settings::all(); // Populate the per-request cache.
delete_option( 'wcb_settings' );
$wcb_after_delete = Settings::all();
wcb_settings_assert(
	array() === $wcb_after_delete,
	'deleted_option hook flushes the accessor cache automatically'
);

// Restore the original option so this test does not leak settings state.
wcb_settings_test_restore( $wcb_settings_snapshot );

WP_CLI::log( '' );
WP_CLI::log(
	sprintf(
		'=== Results: %d passed, %d failed ===',
		(int) $GLOBALS['wcb_settings_test_pass'],
		(int) $GLOBALS['wcb_settings_test_fail']
	)
);

if ( (int) $GLOBALS['wcb_settings_test_fail'] > 0 ) {
	WP_CLI::error( 'Settings accessor tests failed.' );
}
