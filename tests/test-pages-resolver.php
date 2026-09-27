<?php
/**
 * Tests for WCB\Admin\Pages resolver.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-pages-resolver.php
 *
 * Covers the assigned-ID-or-slug-fallback contract introduced in 1.2.4 so
 * admin Pages-tab dropdowns and frontend renders can stop drifting when
 * wcb_settings is missing entries that pages on disk could satisfy.
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

use WCB\Admin\Pages;
use WCB\Admin\Settings;

$GLOBALS['wcb_pages_test_pass'] = 0;
$GLOBALS['wcb_pages_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_pages_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		WP_CLI::log( "  PASS: {$label}" );
		++$GLOBALS['wcb_pages_test_pass'];
	} else {
		WP_CLI::warning( "  FAIL: {$label}" );
		++$GLOBALS['wcb_pages_test_fail'];
	}
}

/**
 * Insert a page with a fixed slug and return its ID.
 *
 * @param string $slug  Page slug.
 * @param string $title Page title.
 * @return int
 */
function wcb_pages_test_insert_page( string $slug, string $title ): int {
	return (int) wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => '',
		)
	);
}

/**
 * Set wcb_settings to a known value and flush the accessor cache.
 *
 * @param array<string,mixed> $value Settings value.
 * @return void
 */
function wcb_pages_test_set_settings( array $value ): void {
	update_option( 'wcb_settings', $value );
	Settings::flush_cache();
}

WP_CLI::log( '=== WCB\\Admin\\Pages resolver tests ===' );

wcb_pages_assert( class_exists( Pages::class ), 'WCB\\Admin\\Pages class is loaded' );

// Snapshot original settings — restored at the end.
$wcb_pages_settings_snapshot = (array) get_option( 'wcb_settings', array() );

// Pick a canonical key whose slug is not already populated on this install,
// so the slug-fallback assertions are deterministic regardless of how the
// site was set up. resume_archive_page (slug "find-resumes") is the safest
// pick because Pro is the only thing that ever creates that page, and the
// dev install starts without it.
$wcb_slug_for_fallback = Pages::canonical_slug( 'resume_archive_page' );
$wcb_existing_at_slug  = get_page_by_path( $wcb_slug_for_fallback );
if ( $wcb_existing_at_slug instanceof WP_Post ) {
	WP_CLI::warning( "  Skipping slug-fallback assertions: '{$wcb_slug_for_fallback}' already exists on this install (id {$wcb_existing_at_slug->ID})" );
	$wcb_canonical_fallback_id = (int) $wcb_existing_at_slug->ID;
	$wcb_created_fixture       = false;
} else {
	$wcb_canonical_fallback_id = wcb_pages_test_insert_page( $wcb_slug_for_fallback, 'WCB Test Find Resumes' );
	$wcb_created_fixture       = true;
}
wcb_pages_assert( $wcb_canonical_fallback_id > 0, 'Fixture: slug-fallback page available' );

// Fixture: a separate published page used as the "assigned ID" path. Slug
// is randomised so it never collides with the canonical map.
$wcb_assigned_post_id = wcb_pages_test_insert_page(
	'wcb-test-post-job-' . wp_rand( 1000, 9999 ),
	'WCB Test Post Job'
);
wcb_pages_assert( $wcb_assigned_post_id > 0, 'Fixture: assigned-ID page created' );

// 1. get_id() returns the assigned ID when present and published.
wcb_pages_test_set_settings( array( 'post_job_page' => $wcb_assigned_post_id ) );
wcb_pages_assert(
	$wcb_assigned_post_id === Pages::get_id( 'post_job_page' ),
	'get_id() returns assigned ID when present and published'
);

// 2. get_id() falls back to slug match when assigned ID is 0.
wcb_pages_test_set_settings( array( 'resume_archive_page' => 0 ) );
wcb_pages_assert(
	$wcb_canonical_fallback_id === Pages::get_id( 'resume_archive_page' ),
	'get_id() falls back to slug when assigned ID is 0'
);

// 3. get_id() falls back to slug when assigned ID points at a draft post.
$wcb_draft_id = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'draft',
		'post_title'  => 'WCB Test Draft',
	)
);
wcb_pages_test_set_settings( array( 'resume_archive_page' => (int) $wcb_draft_id ) );
wcb_pages_assert(
	$wcb_canonical_fallback_id === Pages::get_id( 'resume_archive_page' ),
	'get_id() falls back to slug when assigned ID points at a non-published post'
);

// 4. get_id() returns 0 when neither assigned nor slug match.
wcb_pages_test_set_settings( array() );
// Use a guaranteed-empty key by temporarily renaming the fallback fixture
// out of the way. We'll re-insert it before the backfill assertion.
if ( $wcb_created_fixture ) {
	wp_delete_post( $wcb_canonical_fallback_id, true );
	wcb_pages_assert(
		0 === Pages::get_id( 'resume_archive_page' ),
		'get_id() returns 0 when neither assignment nor slug match'
	);
	// Re-create the fixture for the backfill assertion below.
	$wcb_canonical_fallback_id = wcb_pages_test_insert_page( $wcb_slug_for_fallback, 'WCB Test Find Resumes' );
} else {
	WP_CLI::log( "  SKIP: 'returns 0 when neither match' (canonical slug '{$wcb_slug_for_fallback}' already populated by install)" );
}

// 5. get_id() returns 0 for an unknown key.
wcb_pages_assert(
	0 === Pages::get_id( 'totally_made_up_key' ),
	'get_id() returns 0 for unknown key'
);

// 6. known_keys(): Free's six, plus Pro's two when Pro is active.
$wcb_expected_keys = array(
	'post_job_page',
	'employer_dashboard_page',
	'candidate_dashboard_page',
	'jobs_archive_page',
	'company_archive_page',
	'employer_registration_page',
);
if ( defined( 'WCBP_VERSION' ) ) {
	$wcb_expected_keys[] = 'resume_archive_page';
	$wcb_expected_keys[] = 'job_map_page';
}
wcb_pages_assert(
	count( array_diff( $wcb_expected_keys, Pages::known_keys() ) ) === 0
		&& count( array_diff( Pages::known_keys(), $wcb_expected_keys ) ) === 0,
	'known_keys() returns exactly the defined pages'
);

// 6b. Every definition carries a title, slug and block content, so the
// wizard, Create Missing Pages and the Pages tab can all use it.
$wcb_complete = true;
foreach ( Pages::definitions() as $wcb_def ) {
	$wcb_complete = $wcb_complete && '' !== $wcb_def['title'] && '' !== $wcb_def['slug'] && str_contains( $wcb_def['content'], '<!-- wp:' );
}
wcb_pages_assert( $wcb_complete, 'every page definition has title, slug and block content' );

// 7. canonical_slug() returns the expected slug for a known key.
wcb_pages_assert(
	'employer-dashboard' === Pages::canonical_slug( 'employer_dashboard_page' ),
	'canonical_slug() returns the expected slug for employer_dashboard_page'
);

// 8. canonical_slug() returns empty string for unknown key.
wcb_pages_assert(
	'' === Pages::canonical_slug( 'totally_made_up_key' ),
	'canonical_slug() returns empty string for unknown key'
);

// 9. backfill_from_slugs() writes only missing keys and returns the map.
wcb_pages_test_set_settings(
	array(
		// Already-set: must NOT be overwritten.
		'post_job_page'       => $wcb_assigned_post_id,
		// Missing keys will be backfilled where a slug match exists.
		'resume_archive_page' => 0,
	)
);
$wcb_written = Pages::backfill_from_slugs();
wcb_pages_assert(
	! isset( $wcb_written['post_job_page'] ),
	'backfill_from_slugs() leaves already-assigned key untouched'
);
wcb_pages_assert(
	isset( $wcb_written['resume_archive_page'] )
		&& $wcb_canonical_fallback_id === $wcb_written['resume_archive_page'],
	'backfill_from_slugs() writes resume_archive_page from slug match'
);

// 10. backfill_from_slugs() is idempotent — second run is a no-op for the
// keys it already wrote.
$wcb_written_second = Pages::backfill_from_slugs();
wcb_pages_assert(
	! isset( $wcb_written_second['resume_archive_page'] ),
	'backfill_from_slugs() is idempotent — second run no-ops keys it wrote on first run'
);

// 11-13 use a throwaway definition so they run on any install.
$wcb_tag = 'wcb-test-' . wp_rand( 10000, 99999 );
$wcb_def = static function ( array $defs ) use ( $wcb_tag ): array {
	$defs['wcb_test_page'] = array(
		'title'   => 'WCB Test Page',
		'slug'    => $wcb_tag,
		'aliases' => array( $wcb_tag . '-old' ),
		'content' => '<!-- wp:wp-career-board/' . $wcb_tag . ' /-->',
	);
	return $defs;
};
add_filter( 'wcb_page_definitions', $wcb_def );
Pages::flush();
wcb_pages_test_set_settings( array() );

// 11. An alias slug resolves.
$wcb_alias_id = wcb_pages_test_insert_page( $wcb_tag . '-old', 'WCB Test Alias' );
wcb_pages_assert( $wcb_alias_id === Pages::get_id( 'wcb_test_page' ), 'get_id() resolves a definition alias slug' );
wp_delete_post( $wcb_alias_id, true );

// 12. create_missing() adopts a published page that already carries the
// block, instead of inserting a duplicate.
$wcb_own_page = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'WCB Test Own Page',
		'post_name'    => $wcb_tag . '-mine',
		'post_content' => '<!-- wp:wp-career-board/' . $wcb_tag . ' /-->',
	)
);
$wcb_made = Pages::create_missing( array( 'wcb_test_page' ) );
wcb_pages_assert( (int) $wcb_own_page === ( $wcb_made['wcb_test_page'] ?? 0 ), 'create_missing() adopts the page that already has the block' );
wcb_pages_assert( (int) $wcb_own_page === (int) ( get_option( 'wcb_settings' )['wcb_test_page'] ?? 0 ), 'create_missing() stores the adopted ID' );
wp_delete_post( (int) $wcb_own_page, true );

// 13. create_missing() inserts at the canonical slug when nothing resolves.
wcb_pages_test_set_settings( array() );
$wcb_made = Pages::create_missing( array( 'wcb_test_page' ) );
$wcb_new  = (int) ( $wcb_made['wcb_test_page'] ?? 0 );
wcb_pages_assert( $wcb_new > 0 && $wcb_tag === get_post_field( 'post_name', $wcb_new ), 'create_missing() creates the page at its canonical slug' );
wp_delete_post( $wcb_new, true );

remove_filter( 'wcb_page_definitions', $wcb_def );
Pages::flush();

// Cleanup fixtures.
wp_delete_post( $wcb_assigned_post_id, true );
if ( $wcb_created_fixture ) {
	wp_delete_post( $wcb_canonical_fallback_id, true );
}
if ( $wcb_draft_id && ! is_wp_error( $wcb_draft_id ) ) {
	wp_delete_post( (int) $wcb_draft_id, true );
}

// Restore original settings.
update_option( 'wcb_settings', $wcb_pages_settings_snapshot );
Settings::flush_cache();

WP_CLI::log( '' );
WP_CLI::log(
	sprintf(
		'=== Results: %d passed, %d failed ===',
		(int) $GLOBALS['wcb_pages_test_pass'],
		(int) $GLOBALS['wcb_pages_test_fail']
	)
);

if ( (int) $GLOBALS['wcb_pages_test_fail'] > 0 ) {
	WP_CLI::error( 'Pages resolver tests failed.' );
}
