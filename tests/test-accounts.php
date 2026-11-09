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
$wcb_roles = ( new WP_User( $wcb_sub ) )->roles;
wcb_assert( in_array( 'subscriber', $wcb_roles, true ) && in_array( 'wcb_employer', $wcb_roles, true ), 'a subscriber keeps subscriber and gains employer' );
Roles::grant_member_role( new WP_User( $wcb_sub ), 'wcb_candidate' );
wcb_assert( 3 === count( array_intersect( array( 'subscriber', 'wcb_candidate', 'wcb_employer' ), ( new WP_User( $wcb_sub ) )->roles ) ), 'an employer who applies keeps both roles and subscriber' );

$wcb_swap = static fn () => true;
add_filter( 'wcb_member_role_replaces_default', $wcb_swap );
$wcb_old = $wcb_mk( 'old', 'subscriber' );
Roles::grant_member_role( new WP_User( $wcb_old ), 'wcb_employer' );
wcb_assert( array( 'wcb_employer' ) === array_values( ( new WP_User( $wcb_old ) )->roles ), 'wcb_member_role_replaces_default restores the old swap' );
$wcb_ed2 = $wcb_mk( 'ed2', 'editor' );
Roles::grant_member_role( new WP_User( $wcb_ed2 ), 'wcb_employer' );
wcb_assert( in_array( 'editor', ( new WP_User( $wcb_ed2 ) )->roles, true ), 'the swap filter never demotes an editor' );

WP_CLI::log( '--- restore_default_role repair ---' );
$wcb_lost   = $wcb_mk( 'lost', 'wcb_candidate' );
$wcb_lost2  = $wcb_mk( 'lost2', 'wcb_employer' );
( new WP_User( $wcb_lost2 ) )->add_role( 'wcb_candidate' );
Roles::restore_default_role( 1 );
wcb_assert( in_array( 'wcb_candidate', ( new WP_User( $wcb_lost ) )->roles, true ) && ! in_array( 'subscriber', ( new WP_User( $wcb_lost ) )->roles, true ), 'repair respects the swap filter' );
remove_filter( 'wcb_member_role_replaces_default', $wcb_swap );
Roles::restore_default_role( 1 );
$wcb_roles = ( new WP_User( $wcb_lost ) )->roles;
wcb_assert( in_array( 'subscriber', $wcb_roles, true ) && in_array( 'wcb_candidate', $wcb_roles, true ), 'repair gives a candidate-only member subscriber back' );
$wcb_roles = ( new WP_User( $wcb_lost2 ) )->roles;
wcb_assert( in_array( 'subscriber', $wcb_roles, true ) && in_array( 'wcb_employer', $wcb_roles, true ) && in_array( 'wcb_candidate', $wcb_roles, true ), 'repair gives an employer + candidate member subscriber back' );
$wcb_ed_roles = ( new WP_User( $wcb_ed2 ) )->roles;
wcb_assert( ! in_array( 'subscriber', $wcb_ed_roles, true ), 'repair leaves a member with another role alone' );
$wcb_ed = $wcb_mk( 'ed', 'editor' );
Roles::grant_member_role( new WP_User( $wcb_ed ), 'wcb_candidate' );
$wcb_roles = ( new WP_User( $wcb_ed ) )->roles;
wcb_assert( in_array( 'editor', $wcb_roles, true ) && in_array( 'wcb_candidate', $wcb_roles, true ), 'an editor keeps editor and gains candidate' );

WP_CLI::log( '--- upgrade from 1.8.2 repairs without waiting on WP-Cron ---' );
$wcb_up     = $wcb_mk( 'up', 'wcb_candidate' );
$wcb_db_was = get_option( 'wcb_db_version' );
update_option( 'wcb_db_version', '1.4.3', false );
\WCB\Core\Install::maybe_migrate();
wcb_assert( in_array( 'subscriber', ( new WP_User( $wcb_up ) )->roles, true ), 'the 1.4.4 upgrade gives a candidate-only member the default role at once' );
\WCB\Core\Install::maybe_migrate();
wcb_assert( array( 'wcb_candidate', 'subscriber' ) === array_values( ( new WP_User( $wcb_up ) )->roles ), 'running the upgrade again changes nothing' );
update_option( 'wcb_db_version', $wcb_db_was, false );
update_option( 'wcb_version', '1.8.2' );
\WCB\Core\Install::maybe_migrate();
wcb_assert( WCB_VERSION === get_option( 'wcb_version' ), 'an in-place update stamps wcb_version with the running version' );

WP_CLI::log( '--- Edit User screen keeps Career Board roles ---' );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_set_current_user( 1 );
$wcb_eu = $wcb_mk( 'eu', 'subscriber' );
( new WP_User( $wcb_eu ) )->add_role( 'wcb_employer' );
// Saves the core Edit User form the way user-edit.php does: edit_user() reads $_POST.
$wcb_save  = static function ( int $id, string $role, ?array $boxes ): array {
	$_POST = array(
		'role'     => $role,
		'email'    => get_userdata( $id )->user_email,
		'nickname' => get_userdata( $id )->nickname,
	);
	if ( null !== $boxes ) {
		$_POST['wcb_member_roles']       = $boxes;
		$_POST['wcb_member_roles_nonce'] = wp_create_nonce( 'wcb_member_roles_' . $id );
	}
	edit_user( $id );
	$_POST = array();
	clean_user_cache( $id );
	return array_values( ( new WP_User( $id ) )->roles );
};
$wcb_roles = $wcb_save( $wcb_eu, 'subscriber', array( 'wcb_employer' ) );
wcb_assert( in_array( 'subscriber', $wcb_roles, true ) && in_array( 'wcb_employer', $wcb_roles, true ), 'saving Edit User unchanged keeps subscriber and employer' );
$wcb_roles = $wcb_save( $wcb_eu, 'subscriber', array( 'wcb_employer', 'wcb_candidate' ) );
wcb_assert( 3 === count( array_intersect( array( 'subscriber', 'wcb_employer', 'wcb_candidate' ), $wcb_roles ) ), 'ticking Candidate adds it on top' );
$wcb_roles = $wcb_save( $wcb_eu, 'subscriber', array() );
wcb_assert( array( 'subscriber' ) === $wcb_roles, 'unticking both takes them away and keeps subscriber' );
$wcb_roles = $wcb_save( $wcb_eu, 'wcb_employer', array() );
wcb_assert( in_array( 'wcb_employer', $wcb_roles, true ), 'Employer picked in the dropdown counts as ticked' );
( new WP_User( $wcb_eu ) )->set_role( 'subscriber' );
( new WP_User( $wcb_eu ) )->add_role( 'wcb_candidate' );
$wcb_roles = $wcb_save( $wcb_eu, 'subscriber', null );
wcb_assert( array( 'subscriber' ) === $wcb_roles, 'control: without the boxes (no nonce) core behaviour is untouched' );
wp_set_current_user( 0 );

WP_CLI::log( '--- core filters survive a null from another plugin ---' );
// A role saved without a name reaches gettext as null; it took wp-admin > Users down in 1.8.2.
wcb_assert( null === translate_user_role( null ), 'a role with no name translates to null, no fatal' );
wcb_assert( 'Employer' === translate_user_role( 'Employer' ), 'control: our role names still translate' );
$wcb_null_safe = array(
	'gettext_with_context' => array( null, null, 'User role', 'default' ),
	'login_redirect'       => array( null, null, new WP_User( 1 ) ),
	'the_content'          => array( null ),
	'the_content_feed'     => array( null ),
	'the_excerpt_rss'      => array( null ),
	'template_include'     => array( null ),
	'single_template'      => array( null ),
	'archive_template'     => array( null ),
	'parent_file'          => array( null ),
	'admin_body_class'     => array( null ),
	'login_message'        => array( null ),
	'redirect_post_location' => array( null ),
);
foreach ( $wcb_null_safe as $wcb_hook => $wcb_args ) {
	$wcb_err = '';
	try {
		apply_filters( $wcb_hook, ...$wcb_args );
	} catch ( \TypeError $e ) {
		$wcb_err = str_contains( $e->getFile(), 'wp-career-board' ) ? $e->getMessage() : '';
	}
	wcb_assert( '' === $wcb_err, "{$wcb_hook} passes a null through ({$wcb_err})" );
}

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
$wcb_wrong = wp_authenticate( "acc-new-{$wcb_s}", 'not-the-password' );
wcb_assert( is_wp_error( $wcb_wrong ) && 'incorrect_password' === $wcb_wrong->get_error_code(), 'a wrong password gets core\'s normal error, so it does not reveal the account is unconfirmed' );
wcb_assert( is_wp_error( $wcb_wrong ) && false === strpos( html_entity_decode( $wcb_wrong->get_error_message() ), 'wcb_resend' ), '... and offers no confirmation email to someone who does not know the password' );

// Losing the sign-up screen is not the end: the sign-in message and the expired-link page offer a new link.
$wcb_sent = 0;
$wcb_hook = static function () use ( &$wcb_sent ): void {
	++$wcb_sent;
};
add_action( 'wcb_email_verification_requested', $wcb_hook );
$wcb_link = static function () use ( $wcb_s, $wcb_pw ): string {
	$msg = wp_authenticate( "acc-new-{$wcb_s}", $wcb_pw )->get_error_message();
	return preg_match( '/wcb_resend=([^"&\s]+)/', html_entity_decode( $msg ), $m ) ? urldecode( $m[1] ) : '';
};
wcb_assert( '' !== $wcb_link(), 'the sign-in message offers "Send me a new link"' );
wcb_assert( false === EmailVerification::resend_by_link( $wcb_link() ) && 0 === $wcb_sent, 'a link sent a moment ago is not sent again (no inbox flooding)' );
$wcb_pending         = get_user_meta( $wcb_new, EmailVerification::META, true );
$wcb_pending['time'] = time() - 120;
update_user_meta( $wcb_new, EmailVerification::META, $wcb_pending );
$wcb_req = $wcb_link();
wcb_assert( false === EmailVerification::resend_by_link( $wcb_new . '.not-the-token' ) && 0 === $wcb_sent, 'a forged request sends nothing' );
wcb_assert( true === EmailVerification::resend_by_link( $wcb_req ) && 1 === $wcb_sent, 'the link sends a new confirmation, once' );
wcb_assert( false === EmailVerification::resend_by_link( $wcb_req ) && 1 === $wcb_sent, 'the same link cannot be replayed' );
remove_action( 'wcb_email_verification_requested', $wcb_hook );
$wcb_page = wp_remote_get( home_url( '/?wcb_verify=' . $wcb_new . '.wrong' ), array( 'timeout' => 20, 'sslverify' => false ) );
wcb_assert( 400 === (int) wp_remote_retrieve_response_code( $wcb_page ) && str_contains( (string) wp_remote_retrieve_body( $wcb_page ), 'wcb_resend=' ), 'the expired-link page offers a new link instead of "try signing in"' );
$wcb_gone = wp_remote_get( home_url( '/?wcb_verify=999999999.wrong' ), array( 'timeout' => 20, 'sslverify' => false ) );
wcb_assert( ! str_contains( (string) wp_remote_retrieve_body( $wcb_gone ), 'wcb_resend=' ), 'control: no link is offered for an account that does not exist' );
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
