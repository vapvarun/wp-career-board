<?php
/**
 * Location display (1.8.4): reserved terms never print as places, and a place
 * with no country stays out of Google for Jobs.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-location-display.php
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_ld_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

$wcb_old = get_option( 'wcb_settings' );
$wcb_set = is_array( $wcb_old ) ? $wcb_old : array();
unset( $wcb_set['default_country'] );
update_option( 'wcb_settings', $wcb_set );

$wcb_ids = array();
foreach ( array( 'remote', 'other' ) as $wcb_slug ) {
	if ( ! term_exists( $wcb_slug, 'wcb_location' ) ) {
		wp_insert_term( ucfirst( $wcb_slug ), 'wcb_location', array( 'slug' => $wcb_slug ) );
	}
}
$wcb_t   = wp_insert_term( 'LD Berlin', 'wcb_location' );
$wcb_tid = (int) $wcb_t['term_id'];

$wcb_job = (int) wp_insert_post(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'publish',
		'post_title'  => 'LD Job',
		'post_author' => 1,
	)
);
wp_set_object_terms( $wcb_job, array( 'remote', 'ld-berlin' ), 'wcb_location' );

$wcb_names = \WCB\Core\Locations::place_names( get_the_terms( $wcb_job, 'wcb_location' ) );
wcb_ld_assert( array( 'LD Berlin' ) === $wcb_names, 'card string drops the Remote term' );

wp_set_object_terms( $wcb_job, array( 'other' ), 'wcb_location' );
wcb_ld_assert( array() === \WCB\Core\Locations::place_names( get_the_terms( $wcb_job, 'wcb_location' ) ), 'card string drops the Other term' );

$wcb_slugs = wp_list_pluck( \WCB\Core\Locations::get_dropdown_terms( 1 ), 'slug' );
wcb_ld_assert( ! in_array( 'other', $wcb_slugs, true ), 'dropdown has no reserved Other term' );
wcb_ld_assert( in_array( 'remote', $wcb_slugs, true ), 'dropdown keeps the Remote term' );

// No country anywhere: the on-site place is left out of Google.
wp_set_object_terms( $wcb_job, array( 'ld-berlin' ), 'wcb_location' );
$wcb_post = get_post( $wcb_job );
update_option( 'wcb_settings', array_merge( $wcb_set, array( 'default_country' => '' ) ) );
add_filter( 'locale', static fn(): string => 'en' );
list( $wcb_places ) = \WCB\Modules\Seo\SeoModule::job_places( $wcb_post );
wcb_ld_assert( array() === $wcb_places, 'place with no country is left out' );
wcb_ld_assert( false === \WCB\Modules\Seo\SeoModule::has_location( $wcb_post ), 'job with no country is flagged as left out' );

update_option( 'wcb_settings', array_merge( $wcb_set, array( 'default_country' => 'DE' ) ) );
list( $wcb_places ) = \WCB\Modules\Seo\SeoModule::job_places( $wcb_post );
wcb_ld_assert( 1 === count( $wcb_places ), 'with a default country the place is emitted' );

wp_delete_post( $wcb_job, true );
wp_delete_term( $wcb_tid, 'wcb_location' );
update_option( 'wcb_settings', $wcb_old );

WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
}
WP_CLI::success( 'All location display tests passed.' );
