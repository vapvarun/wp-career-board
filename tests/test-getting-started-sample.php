<?php
/**
 * Overview "Getting started" card vs sample jobs (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-getting-started-sample.php
 *
 * Pins: the wizard's sample jobs do not count as the owner's first job, so
 * the checklist stays until a job without the _wcb_sample marker is published.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

global $wpdb;

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_gs_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

/**
 * The overview screen as an administrator sees it.
 *
 * @return string Rendered HTML.
 */
function wcb_gs_overview(): string {
	ob_start();
	( new \WCB\Admin\Admin() )->render_dashboard();
	return (string) ob_get_clean();
}

WP_CLI::log( '' );
WP_CLI::log( '  Getting Started vs Sample Jobs Tests' );

wp_set_current_user( 1 );
$wcb_was_done = get_option( 'wcb_setup_complete', false );
update_option( 'wcb_setup_complete', 1 );

// Park every published job as a draft for the run; restored at the end.
$wcb_published = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wcb_job' AND post_status = 'publish'" );
if ( $wcb_published ) {
	$wpdb->query( "UPDATE {$wpdb->posts} SET post_status = 'draft' WHERE ID IN (" . implode( ',', array_map( 'intval', $wcb_published ) ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integers only.
	wp_cache_flush();
}

$wcb_sample = wp_insert_post(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'publish',
		'post_title'  => 'Sample Barista',
		'post_author' => 1,
	)
);
update_post_meta( $wcb_sample, '_wcb_sample', 1 );
wcb_gs_assert( str_contains( wcb_gs_overview(), 'Getting started' ), 'only a sample job published: the checklist still shows' );

$wcb_own = wp_insert_post(
	array(
		'post_type'   => 'wcb_job',
		'post_status' => 'publish',
		'post_title'  => 'Own Roaster',
		'post_author' => 1,
	)
);
wcb_gs_assert( ! str_contains( wcb_gs_overview(), 'Getting started' ), 'a job the owner published: the checklist is gone' );

wp_delete_post( $wcb_own, true );
wp_delete_post( $wcb_sample, true );
if ( $wcb_published ) {
	$wpdb->query( "UPDATE {$wpdb->posts} SET post_status = 'publish' WHERE ID IN (" . implode( ',', array_map( 'intval', $wcb_published ) ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integers only.
	wp_cache_flush();
}

update_option( 'wcb_setup_complete', $wcb_was_done );

WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
}
WP_CLI::success( 'All getting-started tests passed.' );
