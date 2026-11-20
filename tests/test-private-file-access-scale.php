<?php
/**
 * Private CV access holds when a file is attached to more than 100 applications (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-private-file-access-scale.php
 *
 * 101 applications carry one CV; the oldest belongs to employer A's job, the 100
 * newer ones to employer B's job. The old code read only the newest 100, so A was
 * refused. Everyone with a real application sees the file; a stranger does not.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Core\PrivateFiles;

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

require_once ABSPATH . 'wp-admin/includes/user.php';
$made    = array();
$mk_user = static function ( string $n ) use ( &$made ): int {
	$id     = (int) wp_insert_user( array( 'user_login' => 'pf_' . $n . wp_generate_password( 4, false ), 'user_pass' => wp_generate_password(), 'user_email' => $n . wp_generate_password( 4, false ) . '@example.test', 'role' => 'subscriber' ) );
	$made[] = $id;
	return $id;
};
$emp_a   = $mk_user( 'a' );
$emp_b   = $mk_user( 'b' );
$cand    = $mk_user( 'c' );
$nobody  = $mk_user( 'n' );

$job_a = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'PF job A', 'post_author' => $emp_a ) );
$job_b = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'PF job B', 'post_author' => $emp_b ) );
$file  = (int) wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'application/pdf', 'post_title' => 'cv', 'post_author' => $cand ) );
update_post_meta( $file, PrivateFiles::META, '1' );

$apps = array();
$add  = static function ( int $job, int $candidate, string $date = '' ) use ( $file ): int {
	$post = array( 'post_type' => 'wcb_application', 'post_status' => 'publish', 'post_title' => 'PF app', 'post_author' => $candidate );
	if ( '' !== $date ) {
		$post['post_date'] = $date;
		$post['post_date_gmt'] = $date;
	}
	$id = (int) wp_insert_post( $post );
	update_post_meta( $id, '_wcb_job_id', $job );
	update_post_meta( $id, '_wcb_candidate_id', $candidate );
	update_post_meta( $id, '_wcb_resume_attachment_id', $file );
	return $id;
};
$apps[] = $add( $job_a, $cand, '2020-01-01 00:00:00' ); // Oldest.
for ( $i = 0; $i < 100; $i++ ) {
	$apps[] = $add( $job_b, $mk_user( 'x' ) ); // 100 newer, other employer.
}

$check = static function ( int $uid ) use ( $file ): bool {
	wp_set_current_user( $uid );
	return PrivateFiles::can_download( $file );
};

$ok( $check( $emp_a ), 'employer of the OLDEST of 101 applications can still download' );
$ok( $check( $emp_b ), 'employer of the newer applications can download' );
$ok( $check( $cand ), 'the candidate who sent it can download' );
$ok( ! $check( $nobody ), 'a stranger cannot' );

// A trashed application gives no access (the old get_posts 'any' skipped trash).
$trashed_job = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'PF job T', 'post_author' => $nobody ) );
$trashed_app = $add( $trashed_job, $nobody );
wp_trash_post( $trashed_app );
$ok( ! $check( $nobody ), 'a trashed application does not grant the file' );
wp_delete_post( $trashed_app, true );
wp_delete_post( $trashed_job, true );

wp_set_current_user( 0 );
$ok( ! PrivateFiles::can_download( $file ), 'logged out cannot' );

foreach ( $apps as $a ) {
	wp_delete_post( $a, true );
}
wp_delete_post( $file, true );
wp_delete_post( $job_a, true );
wp_delete_post( $job_b, true );
foreach ( $made as $uid ) {
	wp_delete_user( $uid );
}

WP_CLI::log( "{$pass} passed, {$fail} failed" );
exit( $fail ? 1 : 0 );
