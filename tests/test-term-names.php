<?php
/**
 * Term names reach text surfaces as plain text (1.8.3).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-term-names.php
 *
 * WordPress stores "R&D" as "R&amp;D". PHP output hid that, but the filter
 * pills (data-wp-text), job cards, REST and the app showed the entity.
 * Pins: Taxonomy::options(), REST job items and the job-listings render
 * carry plain names, and PHP output still escapes them once.
 *
 * Self-contained: creates its own terms and job and deletes them at the end.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WCB\Core\Taxonomy;

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::warning( "  FAIL: {$label}" );
	}
}

wp_set_current_user( 0 );
$wcb_s   = strtolower( wp_generate_password( 6, false ) );
$wcb_loc = wp_insert_term( "Lismore & Casino {$wcb_s}", 'wcb_location', array( 'slug' => "tn-loc-{$wcb_s}" ) );
$wcb_cat = wp_insert_term( "Data Entry & Word {$wcb_s}", 'wcb_category', array( 'slug' => "tn-cat-{$wcb_s}" ) );
$wcb_sub = wp_insert_term( "Typing {$wcb_s}", 'wcb_category', array( 'slug' => "tn-sub-{$wcb_s}", 'parent' => $wcb_cat['term_id'] ) );
$wcb_job = wp_insert_post(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'publish',
		'post_title'  => "Term names {$wcb_s}",
	)
);
wp_set_object_terms( $wcb_job, array( (int) $wcb_loc['term_id'] ), 'wcb_location' );
wp_set_object_terms( $wcb_job, array( (int) $wcb_cat['term_id'] ), 'wcb_category' );

WP_CLI::log( '--- precondition ---' );
wcb_assert( str_contains( get_term( $wcb_loc['term_id'] )->name, '&amp;' ), 'WordPress stored the name HTML-encoded' );

WP_CLI::log( '--- shared helper ---' );
$wcb_opts = array_column( Taxonomy::options( 'wcb_category' ), null, 'slug' );
$wcb_row  = $wcb_opts[ "tn-cat-{$wcb_s}" ] ?? null;
$wcb_kid  = $wcb_opts[ "tn-sub-{$wcb_s}" ] ?? null;
wcb_assert( $wcb_row && "Data Entry & Word {$wcb_s}" === $wcb_row->name, 'options() name is plain text' );
wcb_assert( $wcb_kid && "Data Entry & Word {$wcb_s}" === $wcb_kid->parent_name, 'options() parent_name is plain text' );
wcb_assert( $wcb_kid && str_ends_with( $wcb_kid->label, "Typing {$wcb_s}" ), 'options() label keeps its depth prefix' );
wcb_assert( $wcb_row && "Data Entry &amp; Word {$wcb_s}" === esc_html( $wcb_row->name ), 'PHP output escapes it exactly once' );
wcb_assert( array( "Lismore & Casino {$wcb_s}" ) === Taxonomy::names( wp_get_object_terms( $wcb_job, 'wcb_location', array( 'fields' => 'names' ) ) ), 'names() decodes a names-only term list' );
wcb_assert( array() === Taxonomy::names( new WP_Error( 'x' ) ), 'names() answers an empty list for an error' );

WP_CLI::log( '--- REST job item ---' );
$wcb_res  = rest_do_request( new WP_REST_Request( 'GET', "/wcb/v1/jobs/{$wcb_job}" ) );
$wcb_data = $wcb_res->get_data();
wcb_assert( 200 === $wcb_res->get_status(), 'job item answers 200' );
wcb_assert( str_contains( (string) ( $wcb_data['location'] ?? '' ), "Lismore & Casino {$wcb_s}" ), 'REST location is plain text' );

WP_CLI::log( '--- job-listings render ---' );
$wcb_html  = do_blocks( '<!-- wp:wp-career-board/job-listings /-->' );
$wcb_state = wp_interactivity_state( 'wcb-job-listings' );
$wcb_locs  = wp_list_pluck( (array) ( $wcb_state['filterOptions']['locations'] ?? array() ), 'name', 'slug' );
wcb_assert( "Lismore & Casino {$wcb_s}" === ( $wcb_locs[ "tn-loc-{$wcb_s}" ] ?? '' ), 'filter pill state carries the plain name' );
wcb_assert( ! str_contains( $wcb_html, '&amp;amp;' ), 'no double-encoded entity in the listing markup' );

// Teardown.
wp_delete_post( $wcb_job, true );
wp_delete_term( $wcb_sub['term_id'], 'wcb_category' );
wp_delete_term( $wcb_cat['term_id'], 'wcb_category' );
wp_delete_term( $wcb_loc['term_id'], 'wcb_location' );

WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All term name tests passed.' );
}
