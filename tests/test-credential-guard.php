<?php
/**
 * Current-password check on core's user route (1.8.0).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-credential-guard.php
 *
 * Pins: a member cannot change their own email or password through
 * /wp/v2/users/me (or /wp/v2/users/{their id}) without the current password,
 * the refusal is a real 403 (core answers 200 and changes nothing if the check
 * sits on rest_pre_insert_user), harmless edits and a correct current
 * password still work, and an administrator editing someone else is left to
 * core.
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
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

/**
 * Dispatch a core users request as a given user.
 *
 * @param string              $route  REST route.
 * @param array<string,mixed> $params Request params.
 * @param int                 $as     Acting user ID.
 * @return WP_REST_Response
 */
function wcb_cg_post( string $route, array $params, int $as ): WP_REST_Response {
	wp_set_current_user( $as );
	$request = new WP_REST_Request( 'POST', $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_do_request( $request );
}

WP_CLI::log( 'Credential guard on /wp/v2/users' );

$wcb_pass  = 'Cg-Start-Pass-123';
$wcb_login = 'wcb_cg_' . wp_generate_password( 6, false );
$wcb_mail  = $wcb_login . '@example.test';
$wcb_user  = (int) wp_insert_user(
	array(
		'user_login' => $wcb_login,
		'user_pass'  => $wcb_pass,
		'user_email' => $wcb_mail,
		'role'       => 'wcb_candidate',
	)
);
$wcb_email = static fn (): string => (string) get_userdata( $wcb_user )->user_email;

$wcb_r = wcb_cg_post( '/wp/v2/users/me', array( 'email' => 'hijack-' . $wcb_mail ), $wcb_user );
wcb_assert( 403 === $wcb_r->get_status() && 'wcb_bad_current_password' === ( $wcb_r->get_data()['code'] ?? '' ), 'email change with no current password is a real 403' );
wcb_assert( $wcb_mail === $wcb_email(), '... and the email did not change' );

$wcb_r = wcb_cg_post( '/wp/v2/users/me', array( 'email' => 'hijack-' . $wcb_mail, 'current_password' => 'wrong' ), $wcb_user );
wcb_assert( 403 === $wcb_r->get_status(), 'email change with a wrong current password is refused' );

$wcb_r = wcb_cg_post( '/wp/v2/users/me', array( 'password' => 'Cg-Hijack-Pass-9' ), $wcb_user );
wcb_assert( 403 === $wcb_r->get_status() && wp_check_password( $wcb_pass, get_userdata( $wcb_user )->user_pass, $wcb_user ), 'password change with no current password is refused and the password is unchanged' );

$wcb_r = wcb_cg_post( '/wp/v2/users/' . $wcb_user, array( 'email' => 'hijack-' . $wcb_mail ), $wcb_user );
wcb_assert( 403 === $wcb_r->get_status(), 'the same refusal applies through the numeric id route' );

// WordPress matches routes case-insensitively: capitals must not slip past the guard.
foreach ( array( '/wp/v2/Users/me', '/wp/v2/USERS/ME', '/wp/V2/users/' . $wcb_user) as $wcb_variant ) {
	$wcb_r = wcb_cg_post( $wcb_variant, array( 'email' => 'hijack-' . $wcb_mail ), $wcb_user );
	wcb_assert( 403 === $wcb_r->get_status() && $wcb_mail === $wcb_email(), "email change with no password is refused on {$wcb_variant}" );
	$wcb_r = wcb_cg_post( $wcb_variant, array( 'password' => 'Cg-Hijack-Pass-9' ), $wcb_user );
	wcb_assert( 403 === $wcb_r->get_status() && wp_check_password( $wcb_pass, get_userdata( $wcb_user )->user_pass, $wcb_user ), "password change with no current password is refused on {$wcb_variant}" );
}

$wcb_r = wcb_cg_post( '/wp/v2/users/me', array( 'name' => 'Cg Renamed' ), $wcb_user );
wcb_assert( 200 === $wcb_r->get_status(), 'a change that touches neither email nor password needs no password' );

$wcb_new = 'new-' . $wcb_mail;
$wcb_r   = wcb_cg_post( '/wp/v2/users/me', array( 'email' => $wcb_new, 'current_password' => $wcb_pass ), $wcb_user );
wcb_assert( 200 === $wcb_r->get_status() && $wcb_new === $wcb_email(), 'the correct current password lets the email change' );

$wcb_r = wcb_cg_post( '/wp/v2/users/me', array( 'password' => 'Cg-New-Pass-456', 'current_password' => $wcb_pass ), $wcb_user );
wcb_assert( 200 === $wcb_r->get_status() && wp_check_password( 'Cg-New-Pass-456', get_userdata( $wcb_user )->user_pass, $wcb_user ), 'the correct current password lets the password change' );

$wcb_admin = (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];
$wcb_r     = wcb_cg_post( '/wp/v2/users/' . $wcb_user, array( 'email' => 'by-admin-' . $wcb_mail ), $wcb_admin );
wcb_assert( 200 === $wcb_r->get_status(), 'an administrator editing another member is left to core' );

// Cleanup.
wp_set_current_user( 0 );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $wcb_user );

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All credential guard tests passed.' );
}
