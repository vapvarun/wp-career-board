<?php
/**
 * Sample jobs never make their author an employer or the first own job (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-getting-started-sample-jobs.php
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

$mk = static fn( string $login ) => (int) wp_insert_user( array( 'user_login' => $login . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$a  = $mk( 'gs_marked_' );
$b  = $mk( 'gs_slug_' );
$c  = $mk( 'gs_real_' );
$job = static fn( int $author, string $slug ) => (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'GS ' . $slug, 'post_name' => $slug, 'post_author' => $author ) );

$before = \WCB\Admin\AdminEmployers::count_employers();
$j1 = $job( $a, 'gs-marked-' . wp_rand() );
update_post_meta( $j1, '_wcb_sample', '1' );
$j2 = $job( $b, 'wcb-sample-gs-' . wp_rand() );
$ok( \WCB\Admin\AdminEmployers::count_employers() === $before, 'authors of sample jobs (marker, or old wcb-sample- slug) are not employers' );

$j3 = $job( $c, 'gs-real-' . wp_rand() );
$ok( \WCB\Admin\AdminEmployers::count_employers() === $before + 1, 'the author of a real job is counted once' );

global $wpdb;
$mine = array_map( 'intval', (array) $wpdb->get_col( "SELECT p.ID FROM {$wpdb->posts} p WHERE p.ID IN ( {$j1}, {$j2}, {$j3} )" . \WCB\Admin\SetupWizard::own_job_sql() ) ); // phpcs:ignore
$ok( array( $j3 ) === $mine, 'own_job_sql keeps the real job and drops the marked and the old-slug sample jobs' );
$ok( \WCB\Admin\SetupWizard::count_own_published_jobs() >= 1, 'a real published job counts as the first own job' );
wp_delete_post( $j3, true );

$list = new \WCB\Admin\AdminEmployers();
$ids  = ( new ReflectionMethod( $list, 'employer_user_ids' ) );
$ids->setAccessible( true );
$set = $ids->invoke( $list );
$ok( ! in_array( $a, $set, true ) && ! in_array( $b, $set, true ), 'the Employers screen leaves sample-job authors out too' );

foreach ( array( $j1, $j2 ) as $p ) {
	wp_delete_post( $p, true );
}
foreach ( array( $a, $b, $c ) as $u ) {
	wp_delete_user( $u );
}

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Sample jobs do not count as an employer or an own job.' );
