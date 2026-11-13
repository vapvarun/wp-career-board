<?php
/**
 * My Jobs list: the number of queries must not grow with the number of rows.
 *
 * Run: wp eval-file tests/test-employer-jobs-queries.php
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

global $wpdb;
$wcb_pass = 0;
$wcb_fail = 0;
$wcb_chk  = static function ( bool $ok, string $label ) use ( &$wcb_pass, &$wcb_fail ): void {
	$ok ? ++$wcb_pass : ++$wcb_fail;
	WP_CLI::log( ( $ok ? '  PASS: ' : '  FAIL: ' ) . $label );
};

$wcb_user = wp_insert_user( array( 'user_login' => 'qa_myjobs_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'wcb_employer' ) );
$wcb_jobs = array();
for ( $i = 0; $i < 30; $i++ ) {
	$id = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_author' => $wcb_user, 'post_title' => 'Queries probe ' . $i ) );
	wp_set_object_terms( $id, 'Probe city ' . $i, 'wcb_location' );
	wp_set_object_terms( $id, 'Probe type ' . ( $i % 3 ), 'wcb_job_type' );
	$wcb_jobs[] = $id;
}
wp_set_current_user( $wcb_user );

$wcb_run = static function ( int $per_page ) use ( $wpdb ): array {
	$before = $wpdb->num_queries;
	$req    = new WP_REST_Request( 'GET', '/wcb/v1/employers/me/jobs' );
	$req->set_param( 'per_page', $per_page );
	$data = rest_do_request( $req )->get_data();
	return array( $wpdb->num_queries - $before, count( $data['jobs'] ?? array() ), $data['jobs'] ?? array() );
};
$wcb_run( 1 ); // Warm one-off option and capability reads.
list( $q5, $n5 )   = $wcb_run( 5 );
list( $q30, $n30, $items ) = $wcb_run( 30 );

$wcb_chk( 5 === $n5 && 30 === $n30, 'the list returns the requested page sizes' );
$wcb_chk( $q30 - $q5 <= 3, "queries do not grow per row (5 rows: {$q5}, 30 rows: {$q30})" );
$wcb_chk( '' !== (string) ( $items[0]['location'] ?? '' ), 'location is still shown' );

foreach ( $wcb_jobs as $id ) {
	wp_delete_post( $id, true );
}
wp_delete_user( $wcb_user );

WP_CLI::log( "  Total: {$wcb_pass}+{$wcb_fail}  Pass: {$wcb_pass}  Fail: {$wcb_fail}" );
$wcb_fail > 0 ? WP_CLI::error( "{$wcb_fail} test(s) failed." ) : WP_CLI::success( 'My Jobs queries stay flat.' );
