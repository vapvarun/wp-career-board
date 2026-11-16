<?php
/**
 * WordPress Jobs list (edit.php?post_type=wcb_job): Company and Applications (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-admin-jobs-core-columns.php
 *
 * Pins: both columns sit after the title; the Applications cell counts
 * published applications and links to them; the counts for the whole page
 * come from one query, not one per row.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_ajc_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

// The hooks are wired in Admin::boot(), which only runs in wp-admin.
( new \WCB\Admin\Admin() )->boot();

$wcb_columns = (array) apply_filters( 'manage_wcb_job_posts_columns', array( 'cb' => '', 'title' => 'Title', 'date' => 'Date' ) );
wcb_ajc_assert( array( 'cb', 'title', 'wcb_company', 'wcb_applications', 'date' ) === array_keys( $wcb_columns ), 'Company and Applications follow the title on the WordPress Jobs list' );

$wcb_jobs = array();
foreach ( array( 'Senior Product Designer' => 'Harbor Ledger', 'Data Analyst' => 'Northwind Labs', 'Platform Engineer' => 'Copperleaf' ) as $wcb_title => $wcb_company ) {
	$wcb_jobs[] = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => $wcb_title, 'meta_input' => array( '_wcb_company_name' => $wcb_company ) ) );
}
$wcb_apps = array();
foreach ( array( array( 0, 'publish' ), array( 0, 'publish' ), array( 1, 'publish' ), array( 1, 'trash' ) ) as $wcb_app ) {
	$wcb_apps[] = (int) wp_insert_post( array( 'post_type' => 'wcb_application', 'post_status' => $wcb_app[1], 'post_title' => 'Application', 'meta_input' => array( '_wcb_job_id' => $wcb_jobs[ $wcb_app[0] ] ) ) );
}

$GLOBALS['wp_query']        = new WP_Query(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the list's main query.
$GLOBALS['wp_query']->posts = array_map( 'get_post', $wcb_jobs );

$wcb_cell = static function ( string $column, int $job_id ): string {
	ob_start();
	do_action( 'manage_wcb_job_posts_custom_column', $column, $job_id );
	return (string) ob_get_clean();
};

global $wpdb;
$wcb_before = $wpdb->num_queries;
$wcb_cells  = array_map( static fn ( int $id ): string => $wcb_cell( 'wcb_applications', $id ), $wcb_jobs );
wcb_ajc_assert( 1 === $wpdb->num_queries - $wcb_before, 'three rows, one count query (got ' . ( $wpdb->num_queries - $wcb_before ) . ')' );
wcb_ajc_assert( str_contains( $wcb_cells[0], '>2</a>' ) && str_contains( $wcb_cells[0], 'job_id=' . $wcb_jobs[0] ), 'two published applications, linked to that job' );
wcb_ajc_assert( str_contains( $wcb_cells[1], '>1</a>' ), 'a trashed application is not counted' );
wcb_ajc_assert( '0' === $wcb_cells[2], 'control: a job with no applications shows 0' );
wcb_ajc_assert( 'Harbor Ledger' === $wcb_cell( 'wcb_company', $wcb_jobs[0] ), 'the Company cell shows the company name' );

foreach ( array_merge( $wcb_apps, $wcb_jobs ) as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}

WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
}
WP_CLI::success( 'WordPress Jobs list column tests passed.' );
