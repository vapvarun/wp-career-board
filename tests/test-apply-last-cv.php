<?php
/**
 * Apply panel reuses the candidate's last CV (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-apply-last-cv.php
 *
 * Pins: the panel finds the CV on the candidate's latest application, and the
 * apply endpoint accepts it as resume_attachment_id only for its owner.
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

$mine  = (int) wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'ALC cv', 'post_author' => 1, 'post_mime_type' => 'application/pdf' ) );
$other = (int) wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'ALC other', 'post_author' => 2, 'post_mime_type' => 'application/pdf' ) );
$job   = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'ALC Job', 'post_author' => 2 ) );

wp_set_current_user( 1 );
// Site-defined required application fields are not what this test is about.
add_filter( 'wcb_application_form_fields_groups', '__return_empty_array', 99 );
$call = static function ( int $attachment ) use ( $job ): array {
	$r = new WP_REST_Request( 'POST', '/wcb/v1/jobs/' . $job . '/apply' );
	$r->set_param( 'id', $job );
	$r->set_param( 'resume_attachment_id', $attachment );
	$res = rest_do_request( $r );
	return array( $res->get_status(), $res->get_data() );
};

list( $st, $d ) = $call( $other );
$ok( 400 === $st && 'wcb_invalid_resume' === ( $d['code'] ?? '' ), 'someone else\'s attachment is refused' );

list( $st, $d ) = $call( $mine );
$app_id = (int) ( $d['id'] ?? 0 );
$ok( in_array( $st, array( 200, 201 ), true ) && $app_id > 0, 'own attachment is accepted' );
$ok( $app_id > 0 && $mine === (int) get_post_meta( $app_id, '_wcb_resume_attachment_id', true ), 'application stores the reused CV' );

$found = get_posts( array( 'post_type' => 'wcb_application', 'post_status' => 'any', 'author' => 1, 'posts_per_page' => 5, 'no_found_rows' => true, 'meta_key' => '_wcb_resume_attachment_id', 'fields' => 'ids' ) ); // phpcs:ignore
$ok( in_array( $app_id, $found, true ), 'panel lookup finds the latest application with a CV' );

if ( $app_id ) {
	wp_delete_post( $app_id, true );
}
wp_delete_post( $job, true );
wp_delete_post( $mine, true );
wp_delete_post( $other, true );

WP_CLI::log( "  Total: " . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'All last-CV tests passed.' );
