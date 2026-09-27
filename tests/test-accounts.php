<?php
/**
 * Account and registration tests (1.8.0).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-accounts.php
 *
 * Pins: signing up never strips an existing role, cancelling a deletion never
 * lifts a ban, the registration spam gate, email verification, and the
 * password re-check on email change.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Core\Roles;
use WCB\Modules\Account\EmailVerification;

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
	foreach ( $params as $k => $v ) {
		$request->set_param( $k, $v );
	}
	return rest_do_request( $request );
}

rest_get_server();

WP_CLI::log( '' );
WP_CLI::log( '  Account Tests' );

require_once ABSPATH . 'wp-admin/includes/user.php';
$wcb_s     = wp_generate_password( 6, false );
$wcb_pw    = 'Acc!' . wp_generate_password( 12, false );
$wcb_users = array();
$wcb_mk    = static function ( string $slug, string $role ) use ( $wcb_s, $wcb_pw, &$wcb_users ): int {
	$id          = (int) wp_insert_user( array( 'user_login' => "acc-{$slug}-{$wcb_s}", 'user_pass' => $wcb_pw, 'user_email' => "acc-{$slug}-{$wcb_s}@example.test", 'role' => $role ) );
	$wcb_users[] = $id;
	return $id;
};

WP_CLI::log( '--- grant_member_role ---' );
$wcb_sub = $wcb_mk( 'sub', 'subscriber' );
Roles::grant_member_role( new WP_User( $wcb_sub ), 'wcb_employer' );
wcb_assert( array( 'wcb_employer' ) === array_values( ( new WP_User( $wcb_sub ) )->roles ), 'a subscriber becomes an employer only' );
$wcb_ed = $wcb_mk( 'ed', 'editor' );
Roles::grant_member_role( new WP_User( $wcb_ed ), 'wcb_candidate' );
$wcb_roles = ( new WP_User( $wcb_ed ) )->roles;
wcb_assert( in_array( 'editor', $wcb_roles, true ) && in_array( 'wcb_candidate', $wcb_roles, true ), 'an editor keeps editor and gains candidate' );

WP_CLI::log( '--- deletion request + cancel keeps a ban ---' );
$wcb_emp = $wcb_mk( 'emp', 'wcb_employer' );
update_user_meta( $wcb_emp, '_wcb_employer_banned', '1' );
wcb_rest( 'DELETE', '/wcb/v1/me', array( 'confirm' => 'DELETE', 'password' => $wcb_pw ), $wcb_emp );
wcb_assert( 200 === wcb_rest( 'DELETE', '/wcb/v1/me/deletion', array(), $wcb_emp )->get_status(), 'cancel succeeds' );
wcb_assert( '1' === get_user_meta( $wcb_emp, '_wcb_employer_banned', true ), 'ban is still set after cancel' );
wcb_assert( ! wp_is_ability_granted( 'wcb/post-jobs' ), 'banned employer still cannot post jobs' );

WP_CLI::log( '--- registration spam gate ---' );
$wcb_ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
$wcb_reg_key = 'wcb_reg_' . md5( wp_salt() . $wcb_ip );
$wcb_open    = static fn () => 1;
add_filter( 'pre_option_users_can_register', $wcb_open );
$wcb_limit = static fn () => 1;
add_filter( 'wcb_registration_rate_limit', $wcb_limit );
$wcb_reg = static fn ( string $slug ) => wcb_rest(
	'POST',
	'/wcb/v1/candidates/register',
	array(
		'first_name' => 'Acc',
		'last_name'  => $slug,
		'email'      => "acc-reg{$slug}-{$wcb_s}@example.test",
		'password'   => $wcb_pw,
	),
	0
);
delete_transient( $wcb_reg_key );
wcb_assert( 200 === $wcb_reg( 'a' )->get_status(), 'first sign-up from an address is allowed' );
wcb_assert( 429 === $wcb_reg( 'b' )->get_status(), 'sign-ups over the limit get 429' );
remove_filter( 'wcb_registration_rate_limit', $wcb_limit );
delete_transient( $wcb_reg_key );
$wcb_reject = static fn () => new WP_Error( 'spam', 'no' );
add_filter( 'wcb_pre_registration', $wcb_reject );
wcb_assert( 400 <= $wcb_reg( 'c' )->get_status(), 'wcb_pre_registration can refuse a sign-up' );
remove_filter( 'wcb_pre_registration', $wcb_reject );
remove_filter( 'pre_option_users_can_register', $wcb_open );
foreach ( array( 'a', 'b', 'c' ) as $wcb_x ) {
	$wcb_u = get_user_by( 'email', "acc-reg{$wcb_x}-{$wcb_s}@example.test" );
	if ( $wcb_u ) {
		$wcb_users[] = $wcb_u->ID;
	}
}

WP_CLI::log( '--- email verification ---' );
$wcb_new = $wcb_mk( 'new', 'wcb_candidate' );
EmailVerification::start( $wcb_new );
$wcb_auth = wp_authenticate( "acc-new-{$wcb_s}", $wcb_pw );
wcb_assert( is_wp_error( $wcb_auth ) && 'wcb_email_unverified' === $wcb_auth->get_error_code(), 'an unconfirmed account cannot sign in' );
delete_transient( 'wcb_verify_resend_' . md5( wp_salt() . $wcb_ip ) );
$wcb_resend = wcb_rest( 'POST', '/wcb/v1/auth/verify-email/resend', array( 'email' => 'nobody-' . $wcb_s . '@example.test' ), 0 );
wcb_assert( 200 === $wcb_resend->get_status(), 'resend answers 200 for an unknown address' );
delete_user_meta( $wcb_new, EmailVerification::META );
wcb_assert( ! is_wp_error( wp_authenticate( "acc-new-{$wcb_s}", $wcb_pw ) ), 'a confirmed account signs in' );

WP_CLI::log( '--- email change needs the current password ---' );
$wcb_c = $wcb_mk( 'c', 'wcb_candidate' );
wcb_assert( 403 === wcb_rest( 'POST', '/wcb/v1/account', array( 'email' => "acc-c2-{$wcb_s}@example.test" ), $wcb_c )->get_status(), 'no password: 403' );
wcb_assert( 200 === wcb_rest( 'POST', '/wcb/v1/account', array( 'email' => "acc-c2-{$wcb_s}@example.test", 'current_password' => $wcb_pw ), $wcb_c )->get_status(), 'right password: 200' );
wcb_assert( 200 === wcb_rest( 'POST', '/wcb/v1/account', array( 'display_name' => 'Acc C' ), $wcb_c )->get_status(), 'name-only change needs no password' );
wp_set_current_user( 0 );

// Teardown.
foreach ( array_unique( $wcb_users ) as $wcb_u ) {
	wp_delete_user( (int) $wcb_u );
}

WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All account tests passed.' );
}
