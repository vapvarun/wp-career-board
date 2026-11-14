<?php
/**
 * Remove Sample Data keeps the owner's real job that was saved with a sample company (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-remove-sample-keeps-real-jobs.php
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

$co   = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => 'RSK Sample Co' ) );
update_post_meta( $co, '_wcb_sample', 1 );
$mk   = static function ( string $title, bool $sample ) use ( $co ): int {
	$id = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => $title ) );
	update_post_meta( $id, '_wcb_company_id', $co );
	update_post_meta( $id, '_wcb_company_name', 'RSK Sample Co' );
	if ( $sample ) {
		update_post_meta( $id, '_wcb_sample', 1 );
	}
	return $id;
};
$real   = $mk( 'RSK My first job', false );
$sample = $mk( 'RSK Sample job', true );

( new \WCB\Admin\SetupWizard() )->remove_sample_data();

$ok( null === get_post( $co ), 'the sample company is deleted' );
$ok( null === get_post( $sample ), 'the sample job is deleted' );
$ok( 'publish' === get_post_status( $real ), 'the real job is still published' );
$ok( '' === (string) get_post_meta( $real, '_wcb_company_id', true ), 'the real job no longer points at the deleted company' );
$ok( '' === (string) get_post_meta( $real, '_wcb_closed_reason', true ), 'the real job has no closed reason' );

$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
wp_cache_flush();

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Remove Sample Data keeps real jobs.' );
