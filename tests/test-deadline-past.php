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

wp_delete_post( $wcb_job, true );
wp_delete_user( $wcb_user );

WP_CLI::log( "  Total: {$wcb_pass}+{$wcb_fail}  Pass: {$wcb_pass}  Fail: {$wcb_fail}" );
$wcb_fail > 0 ? WP_CLI::error( "{$wcb_fail} test(s) failed." ) : WP_CLI::success( 'Past deadlines are refused.' );
