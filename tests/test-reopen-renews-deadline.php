<?php
/**
 * Reopening an ended job is a new listing period (1.8.4 check, behaviour from 1.8.0).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-reopen-renews-deadline.php
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$wcb_pass = 0;
$wcb_fail = 0;
$wcb_chk  = static function ( bool $ok, string $label ) use ( &$wcb_pass, &$wcb_fail ): void {
	$ok ? ++$wcb_pass : ++$wcb_fail;
	WP_CLI::log( ( $ok ? '  PASS: ' : '  FAIL: ' ) . $label );
};

wp_set_current_user( 1 );
foreach ( array( 'wcb_expired', 'wcb_closed' ) as $wcb_from ) {
	$wcb_job = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => $wcb_from, 'post_title' => 'Reopen probe ' . $wcb_from, 'post_author' => 1 ) );
	update_post_meta( $wcb_job, '_wcb_deadline', gmdate( 'Y-m-d', strtotime( '-10 days' ) ) );
	$wcb_chk( \WCB\Core\JobDeadline::has_passed( $wcb_job ), "{$wcb_from}: the old deadline is in the past" );
	wp_update_post( array( 'ID' => $wcb_job, 'post_status' => 'publish' ) );
	$wcb_chk( 'publish' === get_post_status( $wcb_job ) && ! \WCB\Core\JobDeadline::has_passed( $wcb_job ), "{$wcb_from}: reopened with a fresh deadline" );
	wp_delete_post( $wcb_job, true );
}

WP_CLI::log( "  Total: " . ( $wcb_pass + $wcb_fail ) . "  Pass: {$wcb_pass}  Fail: {$wcb_fail}" );
$wcb_fail > 0 ? WP_CLI::error( "{$wcb_fail} test(s) failed." ) : WP_CLI::success( 'Reopen renews the deadline.' );
