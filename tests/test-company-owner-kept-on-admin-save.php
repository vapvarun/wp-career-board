<?php
/**
 * An admin saving a company in wp-admin keeps its owner (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-company-owner-kept-on-admin-save.php
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

require_once ABSPATH . 'wp-admin/includes/post.php';

$employer = (int) wp_insert_user( array( 'user_login' => 'cok_emp_' . wp_generate_password( 5, false ), 'user_pass' => wp_generate_password( 20 ), 'user_email' => 'cok' . wp_generate_password( 5, false ) . '@example.test', 'role' => 'wcb_employer' ) );
$company  = (int) wp_insert_post( array( 'post_type' => 'wcb_company', 'post_status' => 'publish', 'post_title' => 'COK Co', 'post_author' => $employer ) );
$owner    = static fn (): int => (int) get_post_field( 'post_author', $company );

// The save hook lives on the admin meta box module; boot it as wp-admin does.
( new \WCB\Admin\AdminMetaBoxes() )->boot();
wp_set_current_user( 1 );

$form = static function ( string $action, array $extra = array() ) use ( $company ): void {
	$_POST = array_merge(
		array( 'post_ID' => $company, 'post_type' => 'wcb_company', 'post_title' => 'COK Co ' . wp_generate_password( 3, false ), 'post_status' => 'publish', 'user_ID' => 1, 'action' => $action, 'originalaction' => $action ),
		$extra
	);
	$_REQUEST = $_POST;
	edit_post();
};

$form( 'editpost' );
$ok( $employer === $owner(), 'admin saving the company in the editor keeps the employer as owner' );

$form( 'inline-save', array( 'post_ID' => $company ) );
$ok( $employer === $owner(), 'Quick Edit keeps the employer as owner' );

$form( 'editpost', array( 'post_author_override' => 1 ) );
$ok( 1 === $owner(), 'an explicit owner change still goes through' );

// Code that sets the owner on purpose (no form) is untouched.
$_POST = array();
$_REQUEST = array();
wp_update_post( array( 'ID' => $company, 'post_author' => $employer ) );
$ok( $employer === $owner(), 'code can set the owner' );

// Other post types are untouched: the guard only acts on a company.
$_POST    = array( 'action' => 'editpost' );
$data     = array( 'post_type' => 'wcb_job', 'post_author' => 1 );
$guarded  = ( new \WCB\Admin\AdminMetaBoxes() )->keep_company_owner( $data, array( 'ID' => $company ) );
$ok( $data === $guarded, 'a job save is left alone' );

// The employer can still edit their own company through the API.
$req = new WP_REST_Request( 'PUT', '/wcb/v1/employers/' . $company );
wp_set_current_user( $employer );
$req->set_header( 'content-type', 'application/json' );
$req->set_body( wp_json_encode( array( 'tagline' => 'COK tagline' ) ) );
$req->set_param( 'id', $company );
$ok( 200 === rest_do_request( $req )->get_status(), 'the employer can still PUT their company after an admin save' );

$_POST = array();
$_REQUEST = array();
wp_delete_post( $company, true );
wp_delete_user( $employer );
WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Company owner kept on admin save.' );
