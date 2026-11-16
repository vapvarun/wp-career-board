<?php
/**
 * Applications admin search by job title (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-applications-title-search.php
 *
 * Pins: the job-title half of the search finds an application whatever the job's
 * status is, including the expired and closed statuses (excluded from core's 'any').
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
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/template.php';

$wcb_pass = 0;
$wcb_fail = 0;
$wcb_chk  = static function ( bool $ok, string $label ) use ( &$wcb_pass, &$wcb_fail ): void {
	$ok ? ++$wcb_pass : ++$wcb_fail;
	WP_CLI::log( ( $ok ? '  PASS: ' : '  FAIL: ' ) . $label );
};

$wcb_find = static function ( string $term ): array {
	$_GET['s'] = $term;
	$table     = new \WCB\Admin\AdminApplications();
	$method    = new ReflectionMethod( $table, 'query_args' );
	$method->setAccessible( true );
	$args                   = $method->invoke( $table );
	$args['fields']         = 'ids';
	$args['posts_per_page'] = 50;
	return get_posts( $args );
};

$wcb_apps = array();
$wcb_jobs = array();
foreach ( array( 'publish', 'wcb_expired', 'wcb_closed', 'draft' ) as $wcb_status ) {
	$wcb_job  = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => $wcb_status, 'post_title' => 'Zymurgist ' . $wcb_status . ' probe' ) );
	$wcb_app  = (int) wp_insert_post( array( 'post_type' => 'wcb_application', 'post_status' => 'publish', 'post_title' => 'Application ' . $wcb_status ) );
	update_post_meta( $wcb_app, '_wcb_job_id', (string) $wcb_job );
	$wcb_jobs[ $wcb_status ] = $wcb_job;
	$wcb_apps[ $wcb_status ] = $wcb_app;
}

$wcb_hits = $wcb_find( 'Zymurgist' );
foreach ( $wcb_apps as $wcb_status => $wcb_app ) {
	$wcb_chk( in_array( $wcb_app, $wcb_hits, true ), "an application to a {$wcb_status} job is found by the job title" );
}
$wcb_chk( array() === $wcb_find( 'Nonexistentterm' ), 'a title that matches no job finds nothing' );

unset( $_GET['s'] );
foreach ( array_merge( $wcb_apps, $wcb_jobs ) as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}

WP_CLI::log( "  Total: {$wcb_pass}+{$wcb_fail}  Pass: {$wcb_pass}  Fail: {$wcb_fail}" );
$wcb_fail > 0 ? WP_CLI::error( "{$wcb_fail} test(s) failed." ) : WP_CLI::success( 'Applications title search holds.' );
