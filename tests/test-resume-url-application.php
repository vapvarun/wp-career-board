<?php
/**
 * View resume opens the resume sent with the application (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-resume-url-application.php
 *
 * Pins: an application sent with only a file has no View resume link (it used
 * to open the candidate's latest resume, an empty page when that was an
 * uploaded CV); an application sent with a resume links that resume, not a
 * newer one; the application screen's Resume box shows the CV of a resume
 * sent without its own file. Needs Pro (the wcb_resume post type).
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Modules\Candidates\CandidatesModule;

if ( ! post_type_exists( 'wcb_resume' ) ) {
	WP_CLI::success( 'Skipped: resumes need Pro.' );
	return;
}

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

$cand  = wp_insert_user( array( 'user_login' => 'cb85t.hana.ito', 'user_email' => 'hana.ito@cb85t.example', 'user_pass' => wp_generate_password(), 'display_name' => 'Hana Ito', 'role' => 'wcb_candidate' ) );
$emp   = wp_insert_user( array( 'user_login' => 'cb85t.paul.reyes', 'user_email' => 'paul.reyes@cb85t.example', 'user_pass' => wp_generate_password(), 'display_name' => 'Paul Reyes', 'role' => 'wcb_employer' ) );
$job   = wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'Supply Chain Analyst', 'post_author' => $emp ) );
$built = wp_insert_post( array( 'post_type' => 'wcb_resume', 'post_status' => 'publish', 'post_title' => 'Hana Ito - Supply Chain', 'post_author' => $cand, 'post_date' => gmdate( 'Y-m-d H:i:s', time() - 3600 ) ) );
update_post_meta( $built, '_wcb_resume_public', '1' );
// The candidate's newest resume: an uploaded CV.
$cv     = wp_insert_post( array( 'post_type' => 'wcb_resume', 'post_status' => 'publish', 'post_title' => 'Hana Ito - CV', 'post_author' => $cand ) );
$cvfile = (int) wp_insert_attachment( array( 'post_mime_type' => 'application/pdf', 'post_title' => 'hana-ito-cv', 'post_status' => 'inherit', 'post_author' => $cand ), 'hana-ito-cv.pdf', $cv );
update_post_meta( $cv, '_wcb_resume_attachment_id', $cvfile );

$apply = static function ( array $meta ) use ( $cand, $job ): int {
	$id = wp_insert_post( array( 'post_type' => 'wcb_application', 'post_status' => 'publish', 'post_title' => 'Hana Ito', 'post_author' => $cand ) );
	foreach ( array( '_wcb_candidate_id' => $cand, '_wcb_job_id' => $job ) + $meta as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	return $id;
};
$app_file   = $apply( array( '_wcb_resume_attachment_id' => $cvfile ) );
$app_built  = $apply( array( '_wcb_resume_id' => $built ) );
$app_cvpost = $apply( array( '_wcb_resume_id' => $cv ) );

wp_set_current_user( 1 );
$ok( '' === CandidatesModule::resume_url( $cand, $app_file ), 'an application sent with only a file has no View resume link' );
$ok( get_permalink( $built ) === CandidatesModule::resume_url( $cand, $app_built ), 'an application sent with a resume links that resume, not the newer one' );
$ok( get_permalink( $cv ) === CandidatesModule::resume_url( $cand, $app_cvpost ), 'an application sent with an uploaded-CV resume links it' );
$ok( '' === CandidatesModule::resume_url( $cand, 0 ), 'no application, no link' );

wp_set_current_user( $emp );
$ok( get_permalink( $cv ) === CandidatesModule::resume_url( $cand, $app_cvpost ), 'the employer applied to also gets the private CV resume link' );

wp_set_current_user( 1 );
$box = ( new \WCB\Modules\Applications\Widgets\ResumePreview() )->render( array( 'application_id' => $app_cvpost ) );
$ok( str_contains( $box, 'hana-ito-cv.pdf' ) && ! str_contains( $box, 'No resume uploaded' ), "the Resume box shows the CV of a resume sent without its own file\n" );

wp_set_current_user( 0 );
foreach ( array( $app_file, $app_built, $app_cvpost, $cv, $built, $job ) as $id ) {
	wp_delete_post( $id, true );
}
wp_delete_attachment( $cvfile, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $cand );
wp_delete_user( $emp );

WP_CLI::log( sprintf( '%d passed, %d failed', $pass, $fail ) );
if ( $fail > 0 ) {
	WP_CLI::error( 'Resume URL test failed.' );
}
WP_CLI::success( 'Resume URL test passed.' );
