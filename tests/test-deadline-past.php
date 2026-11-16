<?php
/**
 * A past deadline chosen over REST is refused; keeping an existing one is not.
 *
 * Run: wp eval-file tests/test-deadline-past.php
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

use WCB\Core\JobDeadline;

$wcb_chk( JobDeadline::is_new_past( '2020-01-01' ), 'a past day is refused for a new job' );
$wcb_chk( ! JobDeadline::is_new_past( gmdate( 'Y-m-d', time() + 5 * DAY_IN_SECONDS ) ), 'a future day is accepted' );
$wcb_chk( ! JobDeadline::is_new_past( '' ), 'no deadline is not a past deadline' );
$wcb_chk( ! JobDeadline::is_new_past( '2020-01-01', \WCB\Core\Time::end_of_day_utc( '2020-01-01' ) ), 'keeping the job\'s existing past deadline is allowed' );
$wcb_chk( JobDeadline::is_new_past( '2020-01-02', \WCB\Core\Time::end_of_day_utc( '2020-01-01' ) ), 'moving it to another past day is refused' );

$wcb_user = wp_insert_user( array( 'user_login' => 'qa_dl_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'wcb_employer' ) );
$wcb_job  = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_author' => $wcb_user, 'post_title' => 'Deadline probe' ) );
update_post_meta( $wcb_job, '_wcb_deadline', gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS ) );
wp_set_current_user( $wcb_user );

$wcb_patch = static function ( string $day ) use ( $wcb_job ): WP_REST_Response {
	$req = new WP_REST_Request( 'PATCH', '/wcb/v1/jobs/' . $wcb_job );
	$req->set_param( 'deadline', $day );
	return rest_do_request( $req );
};
$res = $wcb_patch( '2020-01-01' );
$wcb_chk( 400 === $res->get_status() && 'wcb_deadline_past' === ( $res->get_data()['code'] ?? '' ), 'PATCH with a past deadline returns 400 wcb_deadline_past' );
$wcb_chk( strtotime( (string) get_post_meta( $wcb_job, '_wcb_deadline', true ) ) > time(), 'the stored deadline is unchanged after the refusal' );
$res = $wcb_patch( gmdate( 'Y-m-d', time() + 10 * DAY_IN_SECONDS ) );
$wcb_chk( 200 === $res->get_status(), 'PATCH with a later day still works (' . $res->get_status() . ')' );

// A refused request writes nothing: no job left behind on create, no half-applied update.
$wcb_all    = static fn (): int => (int) ( new WP_Query( array( 'post_type' => 'wcb_job', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => false ) ) )->found_posts;
wp_set_current_user( 1 ); // An administrator passes the other create checks, so the deadline is what decides.
$wcb_before = $wcb_all();
$req = new WP_REST_Request( 'POST', '/wcb/v1/jobs' );
foreach ( array( 'title' => 'Deadline refused create', 'description' => 'x', 'remote' => true, 'deadline' => '2020-01-01' ) as $k => $v ) {
	$req->set_param( $k, $v );
}
$res = rest_do_request( $req );
$wcb_chk( 400 === $res->get_status() && 'wcb_deadline_past' === ( $res->get_data()['code'] ?? '' ), 'POST with a past deadline returns 400 wcb_deadline_past' );
$wcb_chk( $wcb_all() === $wcb_before, 'a refused create leaves no Pending job behind' );
wp_set_current_user( $wcb_user );
$req = new WP_REST_Request( 'PATCH', '/wcb/v1/jobs/' . $wcb_job );
$req->set_param( 'title', 'Renamed with a past date' );
$req->set_param( 'deadline', '2020-01-01' );
rest_do_request( $req );
$wcb_chk( 'Deadline probe' === get_the_title( $wcb_job ), 'a refused update does not apply the title either' );

// The admin editor refuses a new past date too and keeps the stored deadline.
( new \WCB\Admin\AdminMetaBoxes() )->boot();
wp_set_current_user( 1 );
$_POST['wcb_job_meta_nonce'] = wp_create_nonce( 'wcb_job_meta_boxes' );
$_POST['wcb_deadline']       = '2020-01-01';
$wcb_kept                    = (string) get_post_meta( $wcb_job, '_wcb_deadline', true );
wp_update_post( array( 'ID' => $wcb_job, 'post_title' => 'Deadline probe' ) );
$wcb_chk( (string) get_post_meta( $wcb_job, '_wcb_deadline', true ) === $wcb_kept, 'the admin editor keeps the stored deadline when given a new past date' );
$_POST['wcb_deadline'] = gmdate( 'Y-m-d', time() + 20 * DAY_IN_SECONDS );
wp_update_post( array( 'ID' => $wcb_job, 'post_title' => 'Deadline probe' ) );
$wcb_chk( \WCB\Core\JobDeadline::day( $wcb_job ) === $_POST['wcb_deadline'], 'the admin editor saves a later day' );
// A new job with a refused past date still gets the board default, not no expiry.
$wcb_new = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'draft', 'post_title' => 'Deadline new', 'post_author' => $wcb_user ) );
delete_post_meta( $wcb_new, '_wcb_deadline' );
$_POST['wcb_deadline'] = '2020-05-05';
wp_update_post( array( 'ID' => $wcb_new, 'post_title' => 'Deadline new' ) );
$wcb_chk( '' !== (string) get_post_meta( $wcb_new, '_wcb_deadline', true ) && ! \WCB\Core\JobDeadline::has_passed( $wcb_new ), 'a new job with a refused past date gets the board default deadline' );
unset( $_POST['wcb_job_meta_nonce'], $_POST['wcb_deadline'] );
wp_set_current_user( $wcb_user );

foreach ( get_posts( array( 'post_type' => 'wcb_job', 'post_status' => 'any', 'author' => $wcb_user, 'fields' => 'ids', 'posts_per_page' => 50 ) ) as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}
wp_delete_user( $wcb_user );

WP_CLI::log( "  Total: {$wcb_pass}+{$wcb_fail}  Pass: {$wcb_pass}  Fail: {$wcb_fail}" );
$wcb_fail > 0 ? WP_CLI::error( "{$wcb_fail} test(s) failed." ) : WP_CLI::success( 'Past deadlines are refused.' );
