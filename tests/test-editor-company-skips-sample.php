<?php
/**
 * The job editor does not guess a wizard sample company when a real one exists (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-editor-company-skips-sample.php
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

$selected = static function ( int $job ): array {
	ob_start();
	( new \WCB\Admin\AdminMetaBoxes() )->render_job_details_box( get_post( $job ) );
	preg_match_all( '/<option value="(\d+)"[^>]*selected/', (string) ob_get_clean(), $m );
	return array_map( 'intval', $m[1] );
};

$sample = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => 'ECS Sample Co', 'post_author' => 1 ) );
update_post_meta( $sample, '_wcb_sample', 1 );
update_user_meta( 1, '_wcb_company_id', $sample );
$job = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'draft', 'post_title' => 'ECS job', 'post_author' => 1 ) );

// This site already has real companies; one more makes the case explicit.
$real = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => 'ECS Real Co', 'post_author' => 2 ) );
$ok( array() === $selected( $job ), 'once a real company exists, a sample one is not pre-selected' );

update_post_meta( $job, '_wcb_company_id', $sample );
$ok( array( $sample ) === $selected( $job ), 'a job the owner linked to it explicitly keeps it' );

$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
wp_cache_flush();

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Editor skips sample companies.' );
