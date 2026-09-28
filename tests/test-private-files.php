<?php
/**
 * Private candidate-file tests.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-private-files.php
 *
 * Covers the 1.8.0 move of resumes/CVs out of public uploads: the migration
 * cron, the attachment leaving core REST, who may download, and the guest
 * apply path that used to accept anyone's attachment id. Allowed downloads are
 * checked through can_download() because the route streams the file and exits.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::warning( "  FAIL: {$label}" );
	}
}

/**
 * Dispatch an internal REST request.
 *
 * @param string   $method  HTTP method.
 * @param string   $route   REST route path.
 * @param array    $params  Request parameters.
 * @param int|null $user_id User ID to set (null = leave unchanged, 0 = anonymous).
 * @return WP_REST_Response
 */
function wcb_rest( string $method, string $route, array $params = array(), ?int $user_id = null ): WP_REST_Response {
	if ( null !== $user_id ) {
		wp_set_current_user( $user_id );
	}
	$request = new WP_REST_Request( $method, $route );
	if ( 'GET' === $method ) {
		foreach ( $params as $k => $v ) {
			$request->set_param( $k, $v );
		}
	} else {
		$request->set_body_params( $params );
	}
	return rest_do_request( $request );
}

rest_get_server();

WP_CLI::log( '' );
WP_CLI::log( '========================================' );
WP_CLI::log( '  Private Candidate File Tests' );
WP_CLI::log( '========================================' );

$wcb_suffix    = wp_generate_password( 6, false );
$wcb_candidate = wp_insert_user( array( 'user_login' => 'pf-cand-' . $wcb_suffix, 'user_pass' => wp_generate_password(), 'user_email' => 'pf-cand-' . $wcb_suffix . '@example.test', 'role' => 'wcb_candidate' ) );
$wcb_employer  = wp_insert_user( array( 'user_login' => 'pf-emp-' . $wcb_suffix, 'user_pass' => wp_generate_password(), 'user_email' => 'pf-emp-' . $wcb_suffix . '@example.test', 'role' => 'wcb_employer' ) );
$wcb_other     = wp_insert_user( array( 'user_login' => 'pf-other-' . $wcb_suffix, 'user_pass' => wp_generate_password(), 'user_email' => 'pf-other-' . $wcb_suffix . '@example.test', 'role' => 'wcb_employer' ) );

$wcb_job = (int) wp_insert_post( array( 'post_type' => 'wcb_job', 'post_status' => 'publish', 'post_title' => 'PF job ' . $wcb_suffix, 'post_author' => $wcb_employer ) );
update_post_meta( $wcb_job, '_wcb_deadline', gmdate( 'Y-m-d', strtotime( '+10 days' ) ) );
$wcb_app = (int) wp_insert_post( array( 'post_type' => 'wcb_application', 'post_status' => 'publish', 'post_title' => 'PF app ' . $wcb_suffix, 'post_author' => $wcb_candidate ) );
update_post_meta( $wcb_app, '_wcb_job_id', $wcb_job );
update_post_meta( $wcb_app, '_wcb_candidate_id', $wcb_candidate );

// A resume stored the pre-1.8.0 way: a public attachment in uploads/YYYY/MM.
$wcb_upload = wp_upload_bits( 'pf-cv-' . $wcb_suffix . '.pdf', null, "%PDF-1.4\nfixture\n" );
$wcb_file   = (int) wp_insert_attachment(
	array(
		'post_mime_type' => 'application/pdf',
		'post_title'     => 'PF CV',
		'post_status'    => 'inherit',
		'post_author'    => $wcb_candidate,
	),
	$wcb_upload['file'],
	$wcb_app
);
update_post_meta( $wcb_app, '_wcb_resume_attachment_id', $wcb_file );
// WordPress's page-1 preview of a PDF sits beside it and shows the CV.
$wcb_preview = str_replace( '.pdf', '-pdf.jpg', $wcb_upload['file'] );
file_put_contents( $wcb_preview, 'jpg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
wp_update_attachment_metadata( $wcb_file, array( 'sizes' => array( 'full' => array( 'file' => basename( $wcb_preview ) ) ) ) );

WP_CLI::log( '--- migration cron: wcb_private_files_migrate ---' );
do_action( 'wcb_private_files_migrate' );
$wcb_path = (string) get_attached_file( $wcb_file );
wcb_assert( false !== strpos( $wcb_path, '/wcb-private/' ) && file_exists( $wcb_path ), 'legacy resume moved into uploads/wcb-private/' );
wcb_assert( ! file_exists( $wcb_upload['file'] ), 'old public copy is gone' );
wcb_assert( ! file_exists( $wcb_preview ) && file_exists( dirname( $wcb_path ) . '/' . basename( $wcb_preview ) ), 'the PDF preview image moved with it, not left public' );
wcb_assert( 'private' === get_post_status( $wcb_file ), 'attachment status is private' );
wcb_assert( false !== strpos( \WCB\Core\PrivateFiles::url( $wcb_file ), 'wcb_file=' . $wcb_file ), 'url() hands out the gated handler' );

WP_CLI::log( '--- core REST no longer lists it ---' );
$wcb_media = wcb_rest( 'GET', '/wp/v2/media', array( 'media_type' => 'application', 'per_page' => 100 ), 0 );
wcb_assert( ! in_array( $wcb_file, array_map( static fn ( $m ) => (int) $m['id'], (array) $wcb_media->get_data() ), true ), 'anonymous /wp/v2/media omits the resume' );

WP_CLI::log( '--- GET /wcb/v1/files/{id} ---' );
wcb_assert( 401 === wcb_rest( 'GET', "/wcb/v1/files/{$wcb_file}", array(), 0 )->get_status(), 'anonymous is refused' );
wcb_assert( 403 === wcb_rest( 'GET', "/wcb/v1/files/{$wcb_file}", array(), $wcb_other )->get_status(), 'an unrelated employer is refused' );
wp_set_current_user( $wcb_candidate );
wcb_assert( \WCB\Core\PrivateFiles::can_download( $wcb_file ), 'the candidate may download' );
wp_set_current_user( $wcb_employer );
wcb_assert( \WCB\Core\PrivateFiles::can_download( $wcb_file ), "the job's employer may download" );
wp_set_current_user( 0 );

WP_CLI::log( '--- guest apply cannot borrow an attachment ---' );
$wcb_guest = wcb_rest(
	'POST',
	"/wcb/v1/jobs/{$wcb_job}/apply",
	array(
		'guest_name'           => 'PF Guest',
		'guest_email'          => 'pf-guest-' . $wcb_suffix . '@example.test',
		'resume_attachment_id' => $wcb_file,
	),
	0
);
wcb_assert( 400 === $wcb_guest->get_status(), 'guest apply with someone else\'s attachment is rejected' );

// Teardown.
foreach ( get_posts( array( 'post_type' => 'wcb_application', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1, 'meta_key' => '_wcb_job_id', 'meta_value' => $wcb_job ) ) as $wcb_id ) { // phpcs:ignore WordPress.DB.SlowDBQuery
	wp_delete_post( (int) $wcb_id, true );
}
wp_delete_attachment( $wcb_file, true );
wp_delete_post( $wcb_job, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( $wcb_candidate, $wcb_employer, $wcb_other ) as $wcb_user ) {
	wp_delete_user( (int) $wcb_user );
}

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All private-file tests passed.' );
}
