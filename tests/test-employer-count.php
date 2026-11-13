<?php
/**
 * Dashboard Employers tile and the Employers list count the same people.
 *
 * Run: wp eval-file tests/test-employer-count.php
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

$wcb_pass = 0;
$wcb_fail = 0;
$wcb_chk  = static function ( bool $ok, string $label ) use ( &$wcb_pass, &$wcb_fail ): void {
	$ok ? ++$wcb_pass : ++$wcb_fail;
	WP_CLI::log( ( $ok ? '  PASS: ' : '  FAIL: ' ) . $label );
};

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
$wcb_list = new \WCB\Admin\AdminEmployers();
$wcb_ids  = new ReflectionMethod( $wcb_list, 'employer_user_ids' );
$wcb_ids->setAccessible( true );

$wcb_same = static fn(): bool => count( $wcb_ids->invoke( new \WCB\Admin\AdminEmployers() ) ) === \WCB\Admin\AdminEmployers::count_employers();
$wcb_chk( $wcb_same(), 'tile count = list set on the current data (' . \WCB\Admin\AdminEmployers::count_employers() . ')' );

$wcb_author = wp_insert_user( array( 'user_login' => 'qa_empc_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$wcb_job    = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_author' => $wcb_author, 'post_title' => 'Employer count probe' ) );
$wcb_chk( $wcb_same(), 'a job author without the employer role is counted by both' );

$wcb_before = \WCB\Admin\AdminEmployers::count_employers();
wp_trash_post( $wcb_job );
$wcb_chk( \WCB\Admin\AdminEmployers::count_employers() === $wcb_before - 1 && $wcb_same(), 'a trashed job no longer makes its author an employer' );

wp_delete_post( $wcb_job, true );
wp_delete_user( $wcb_author );

WP_CLI::log( "  Total: {$wcb_pass}+{$wcb_fail}  Pass: {$wcb_pass}  Fail: {$wcb_fail}" );
$wcb_fail > 0 ? WP_CLI::error( "{$wcb_fail} test(s) failed." ) : WP_CLI::success( 'Employer counts agree.' );
