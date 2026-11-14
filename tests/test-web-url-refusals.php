<?php
/**
 * A refused web address is reported and never wipes the stored one (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-web-url-refusals.php
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

use WCB\Core\Text;

// One rule, one sentence.
$ok( Text::is_refused_url( 'name@company.com' ) && Text::is_refused_url( 'https://name@company.com' ), 'an email, bare or with a scheme, is refused' );
$ok( ! Text::is_refused_url( '' ) && ! Text::is_refused_url( '   ' ), 'empty and whitespace-only mean not set, not refused' );
$ok( ! Text::is_refused_url( 'careers.example.com/x' ), 'a web address is not refused' );

// REST: apply_url.
$params = ( new \WCB\Api\Endpoints\JobsEndpoint() )->get_write_params();
$check  = $params['apply_url']['validate_callback'];
$ok( true === $check( '   ' ), 'REST accepts a whitespace-only Apply URL, as the form does' );
$err = $check( 'name@company.com' );
$ok( $err instanceof WP_Error && Text::web_url_error( true ) === $err->get_error_message(), 'REST refuses an email with the Apply Email sentence' );

// REST: company links keep the stored value and say so.
wp_set_current_user( 1 );
$company = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => 'WUR Co', 'post_author' => 1 ) );
update_post_meta( $company, '_wcb_website', 'https://harbor.example' );
$req = new WP_REST_Request( 'PUT', '/wcb/v1/employers/' . $company );
$req->set_param( 'id', $company );
$req->set_param( 'website', 'name@company.com' );
$res = rest_do_request( $req );
$ok( 400 === $res->get_status(), 'PUT website=email returns 400, not 200' );
$ok( Text::web_url_error() === ( $res->get_data()['message'] ?? '' ), 'the 400 carries the web-address sentence' );
$ok( 'https://harbor.example' === get_post_meta( $company, '_wcb_website', true ), 'the stored website is kept' );

// wp-admin editors, through the real save hooks.
$boxes = new \WCB\Admin\AdminMetaBoxes();
$boxes->boot();
$_POST['wcb_job_meta_nonce'] = wp_create_nonce( 'wcb_job_meta_boxes' );
$job = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'draft', 'post_title' => 'WUR Job', 'post_author' => 1 ) );
update_post_meta( $job, '_wcb_apply_url', 'https://careers.example/apply' );
$_POST['wcb_apply_url'] = 'https://name@company.com';
wp_update_post( array( 'ID' => $job, 'post_title' => 'WUR Job 2' ) );
$ok( 'https://careers.example/apply' === get_post_meta( $job, '_wcb_apply_url', true ), 'job editor keeps the stored Apply URL when the typed one is refused' );
$ok( false !== strpos( $boxes->flag_held_job( 'post.php?post=1' ), 'wcb_bad_url=apply_url' ), 'job editor redirect carries the refusal flag' );

$boxes2 = new \WCB\Admin\AdminMetaBoxes();
$boxes2->boot();
$_POST['wcb_company_meta_nonce'] = wp_create_nonce( 'wcb_company_meta_boxes' );
$_POST['wcb_website']            = 'name@company.com';
wp_update_post( array( 'ID' => $company, 'post_title' => 'WUR Co 2' ) );
$ok( 'https://harbor.example' === get_post_meta( $company, '_wcb_website', true ), 'company editor keeps the stored website when the typed one is refused' );
$ok( false !== strpos( $boxes2->flag_held_job( 'post.php?post=1' ), 'wcb_bad_url=wcb_website' ), 'company editor redirect carries the refusal flag' );

unset( $_POST['wcb_apply_url'], $_POST['wcb_website'], $_POST['wcb_job_meta_nonce'], $_POST['wcb_company_meta_nonce'] );
wp_delete_post( $job, true );
wp_delete_post( $company, true );

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Web-address refusal tests passed.' );
