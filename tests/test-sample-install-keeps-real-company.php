<?php
/**
 * Install Sample Data never adopts the owner's real company; the removal notice names every job (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-sample-install-keeps-real-company.php
 *
 * Runs inside a transaction that is rolled back, because removing sample data
 * deletes every sample post on the site.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

global $wpdb;
$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
wp_set_current_user( 1 );

$wizard = new \WCB\Admin\SetupWizard();
$run    = new ReflectionMethod( $wizard, 'install_sample_data' );
$run->setAccessible( true );

// Learn how many sample companies a clean install makes.
$run->invoke( $wizard );
$tracked = (array) get_option( 'wcb_sample_data_ids', array() );
$wizard->remove_sample_data();

$real_co  = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => 'SIK Roastworks', 'post_name' => 'roastworks' ) );
$real_job = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'SIK real job', 'post_name' => 'sik-real-job' ) );
update_post_meta( $real_job, '_wcb_company_id', $real_co );

$run->invoke( $wizard );
$ids = (array) get_option( 'wcb_sample_data_ids', array() );

$ok( ! in_array( $real_co, array_map( 'intval', (array) ( $ids['companies'] ?? array() ) ), true ), 'the real company is not tracked as sample data' );
$ok( ! \WCB\Admin\SetupWizard::is_sample_post( $real_co ), 'the real company is not a sample post' );
$ok( count( (array) ( $ids['companies'] ?? array() ) ) === count( (array) ( $tracked['companies'] ?? array() ) ), 'every sample company was still created' );

// Second run adds no duplicates.
$count_before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'wcb_company' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$run->invoke( $wizard );
$count_after = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'wcb_company' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$ok( $count_before === $count_after, 'running the installer again adds no duplicate company' );

$wizard->remove_sample_data();
$ok( $real_co && null !== get_post( $real_co ) && 'publish' === get_post_status( $real_co ), 'Remove Sample Data leaves the real company published' );
$ok( (string) $real_co === (string) get_post_meta( $real_job, '_wcb_company_id', true ), 'the real job still points at its company' );

// The notice names every job, or says how many more.
$sample_co = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => 'SIK Sample Co' ) );
update_post_meta( $sample_co, '_wcb_sample', 1 );
foreach ( array( 'A', 'B', 'C', 'D', 'E' ) as $t ) {
	$j = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'SIK Own ' . $t ) );
	update_post_meta( $j, '_wcb_company_id', $sample_co );
}
$result = $wizard->remove_sample_data();
$notice = (string) ( $result['notice'] ?? '' );
$ok( str_contains( $notice, '5 of your own jobs' ) && str_contains( $notice, 'and 2 more' ), 'five detached jobs: three named plus "and 2 more"' );
$one = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => 'SIK Sample Co 2' ) );
update_post_meta( $one, '_wcb_sample', 1 );
$j = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'SIK Only One' ) );
update_post_meta( $j, '_wcb_company_id', $one );
$result = $wizard->remove_sample_data();
$ok( ! str_contains( (string) ( $result['notice'] ?? '' ), 'more' ), 'a single detached job has no "and N more"' );

$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
wp_cache_flush();

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'All sample-install tests passed.' );
