<?php
/**
 * Career Board content has its own capabilities (1.8.6).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-cpt-capabilities.php
 *
 * Jobs, applications, companies, resumes and boards used capability_type
 * 'post', so an Author could publish a job on a paid board through
 * /wp/v2/wcb_job (no payment, no moderation) and an Editor could edit anyone's
 * application. Now only roles holding the plugin's own caps (administrators)
 * write them through core; members keep working through /wcb/v1.
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

require_once ABSPATH . 'wp-admin/includes/user.php';
$made    = array();
$mk_user = static function ( string $n, string $role ) use ( &$made ): int {
	$id     = (int) wp_insert_user( array( 'user_login' => 'cc_' . $n . wp_generate_password( 4, false ), 'user_pass' => wp_generate_password(), 'user_email' => 'cc' . $n . wp_generate_password( 4, false ) . '@example.test', 'role' => $role ) );
	$made[] = $id;
	return $id;
};
$author      = $mk_user( 'a', 'author' );
$editor      = $mk_user( 'e', 'editor' );
$contributor = $mk_user( 'c', 'contributor' );
$candidate   = $mk_user( 'k', 'wcb_candidate' );
$admin       = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );

$created = array();
$write   = static function ( string $method, string $route, array $body ) use ( &$created ): int {
	$r = new WP_REST_Request( $method, $route );
	$r->set_body_params( $body );
	$res = rest_do_request( $r );
	if ( 201 === $res->get_status() ) {
		$created[] = (int) ( $res->get_data()['id'] ?? 0 );
	}
	return $res->get_status();
};

// Someone else's application, to try to edit.
$application = (int) wp_insert_post( array( 'post_type' => 'wcb_application', 'post_status' => 'publish', 'post_title' => 'Amara Okafor for Data Analyst', 'post_author' => $candidate ) );

$types = array( 'wcb_job', 'wcb_application', 'wcb_company', 'wcb_resume', 'wcb_board' );
foreach ( array( 'author' => $author, 'editor' => $editor, 'contributor' => $contributor ) as $role => $uid ) {
	wp_set_current_user( $uid );
	foreach ( $types as $type ) {
		$status = $write( 'POST', "/wp/v2/{$type}", array( 'title' => 'Head of Growth, Lagos (Hybrid)', 'status' => 'contributor' === $role ? 'pending' : 'publish' ) );
		$ok( in_array( $status, array( 401, 403 ), true ), "{$role} cannot create a {$type} through /wp/v2 (got {$status})" );
	}
}

wp_set_current_user( $editor );
$status = $write( 'POST', "/wp/v2/wcb_application/{$application}", array( 'title' => 'Edited by an editor' ) );
$ok( in_array( $status, array( 401, 403 ), true ) && 'Amara Okafor for Data Analyst' === get_post_field( 'post_title', $application ), "editor cannot edit another user's application (got {$status})" );
$ok( ! current_user_can( 'delete_post', $application ), 'editor cannot delete it' );

if ( $admin > 0 ) {
	wp_set_current_user( $admin );
	$ok( 201 === $write( 'POST', '/wp/v2/wcb_job', array( 'title' => 'Admin draft job', 'status' => 'draft' ) ), 'admin can still create a job in the block editor' );
	$ok( current_user_can( 'edit_post', $application ) && current_user_can( 'delete_post', $application ), 'admin can still edit and delete any application' );
}

wp_set_current_user( 0 );
foreach ( array_merge( $created, array( $application ) ) as $id ) {
	if ( $id > 0 ) {
		wp_delete_post( $id, true );
	}
}
foreach ( $made as $uid ) {
	wp_delete_user( $uid );
}

WP_CLI::log( "{$pass} passed, {$fail} failed" );
exit( $fail ? 1 : 0 );
