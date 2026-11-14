<?php
/**
 * Saved Jobs lists only jobs a candidate can open.
 *
 * Run: wp eval-file tests/test-saved-jobs-viewable.php
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

$wcb_user = wp_insert_user( array( 'user_login' => 'qa_sv_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'wcb_candidate' ) );
$wcb_ids  = array();
foreach ( array( 'publish', 'wcb_closed', 'pending', 'draft' ) as $wcb_status ) {
	$wcb_ids[ $wcb_status ] = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => $wcb_status, 'post_title' => 'Saved probe ' . $wcb_status ) );
	add_user_meta( $wcb_user, '_wcb_bookmark', $wcb_ids[ $wcb_status ] );
}
wp_set_current_user( $wcb_user );

$wcb_req  = new WP_REST_Request( 'GET', '/wcb/v1/candidates/' . $wcb_user . '/bookmarks' );
$wcb_data = rest_do_request( $wcb_req )->get_data();
$wcb_list = (array) ( $wcb_data['items'] ?? $wcb_data['bookmarks'] ?? $wcb_data );
$wcb_got  = array_map( 'intval', wp_list_pluck( $wcb_list, 'id' ) );

$wcb_chk( in_array( $wcb_ids['publish'], $wcb_got, true ), 'a live job is listed' );
$wcb_chk( in_array( $wcb_ids['wcb_closed'], $wcb_got, true ), 'a closed job (its page still opens) is listed' );
$wcb_chk( ! in_array( $wcb_ids['pending'], $wcb_got, true ), 'a job back in Pending (404) is not listed' );
$wcb_chk( ! in_array( $wcb_ids['draft'], $wcb_got, true ), 'a draft job is not listed' );

foreach ( $wcb_ids as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}
wp_delete_user( $wcb_user );

WP_CLI::log( "  Total: {$wcb_pass}+{$wcb_fail}  Pass: {$wcb_pass}  Fail: {$wcb_fail}" );
$wcb_fail > 0 ? WP_CLI::error( "{$wcb_fail} test(s) failed." ) : WP_CLI::success( 'Saved jobs list only openable jobs.' );
