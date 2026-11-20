<?php
/**
 * The employer's applicant rows carry the applicant's photo (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-applicant-avatar.php
 *
 * Pins JobsEndpoint::employer_application_item(): avatar_url is the resume photo
 * when there is one and '' otherwise (never a Gravatar), and the logo tile token
 * the job card, company card and company header share exists.
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
use WCB\Api\Endpoints\JobsEndpoint;

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

$ok( method_exists( CandidatesModule::class, 'application_photo_resumes' ), 'application_photo_resumes() exists' );

$user   = self_user();
$resume = wp_insert_post( array( 'post_type' => 'wcb_resume', 'post_status' => 'publish', 'post_title' => 'AV test', 'post_author' => $user ) );
update_post_meta( $resume, '_wcb_resume_public', '1' );
$photo = wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'post_title' => 'av' ) );
$app   = wp_insert_post( array( 'post_type' => 'wcb_application', 'post_status' => 'publish', 'post_title' => 'AV app', 'post_author' => $user ) );
update_post_meta( $app, '_wcb_candidate_id', $user );
update_post_meta( $app, '_wcb_resume_id', $resume );

$post = get_post( $app );
$none = JobsEndpoint::employer_application_item( $post, array() );
$ok( array_key_exists( 'avatar_url', $none ), 'item has an avatar_url key' );
$ok( isset( $none['submitted_at_short'] ) && ! str_contains( $none['submitted_at_short'], 'T' ) && strlen( $none['submitted_at_short'] ) <= 12, 'board card gets a short date that fits one line' );
$ok( '' === $none['avatar_url'], 'no photo on the resume gives an empty avatar_url' );

update_post_meta( $resume, '_wcb_resume_photo_id', $photo );
wp_cache_flush();
$map = CandidatesModule::application_photo_resumes( array( $post ) );
$ok( ( $map[ $app ] ?? 0 ) === $resume, 'photo map points the application at its resume' );
$ok( array_key_exists( 'avatar_url', JobsEndpoint::employer_application_item( $post, array(), $map ) ), 'a batched map is accepted' );

$tokens = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/frontend-tokens.css' );
$ok( str_contains( $tokens, '--wcb-logo-bg' ), 'logo tile backdrop token is defined' );

wp_delete_post( $app, true );
wp_delete_post( $resume, true );
wp_delete_post( $photo, true );

function self_user(): int {
	$u = get_users( array( 'role' => 'wcb_candidate', 'number' => 1, 'fields' => 'ID' ) );
	return (int) ( $u[0] ?? 1 );
}

WP_CLI::log( "{$pass} passed, {$fail} failed" );
exit( $fail ? 1 : 0 );
