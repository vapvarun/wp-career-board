<?php
/**
 * Turning email verification off lets pending members sign in (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-email-verification-off-releases.php
 *
 * Pins: the sign-in block for an unconfirmed account applies only while
 * EmailVerification::is_required() is true. With the setting off (or the
 * "Confirm your email" email off) the member signs in; turning it back on
 * blocks them again, because the pending mark is kept.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Admin\Settings;
use WCB\Modules\Account\EmailVerification;

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

// The setting and the email toggle are forced per case and never written.
$require  = true;
$email_on = true;
$force    = static function ( $settings ) use ( &$require, &$email_on ) {
	$settings                               = is_array( $settings ) ? $settings : array();
	$settings['require_email_verification'] = $require;
	$emails                                 = is_array( $settings['emails'] ?? null ) ? $settings['emails'] : (array) get_option( 'wcb_email_settings', array() );
	$emails['verify-account']               = array_merge( (array) ( $emails['verify-account'] ?? array() ), array( 'enabled' => $email_on ) );
	$settings['emails']                     = $emails;
	return $settings;
};
$no_mail = static fn () => true;
add_filter( 'pre_wp_mail', $no_mail );
// A site's own login rate limiter would answer before ours on repeated runs.
$gate = has_filter( 'authenticate', 'wbcom_essential_login_security_gate' );
if ( false !== $gate ) {
	remove_filter( 'authenticate', 'wbcom_essential_login_security_gate', $gate );
}
add_filter( 'option_wcb_settings', $force );
add_filter( 'default_option_wcb_settings', $force );
$set = static function ( bool $req, bool $em ) use ( &$require, &$email_on ): void {
	$require  = $req;
	$email_on = $em;
	Settings::flush_cache();
};

$login = 'maya-okafor-' . wp_generate_password( 6, false, false );
$pw    = wp_generate_password( 16, false, false );
$uid   = (int) wp_insert_user(
	array(
		'user_login' => $login,
		'user_pass'  => $pw,
		'user_email' => $login . '@example.test',
		'role'       => 'subscriber',
	)
);
EmailVerification::start( $uid );

$set( true, true );
$ok( EmailVerification::is_required(), 'setup: verification is required' );
$auth = wp_authenticate( $login, $pw );
$ok( is_wp_error( $auth ) && 'wcb_email_unverified' === $auth->get_error_code(), 'setting on: a pending member is blocked' );

$set( false, true );
$auth = wp_authenticate( $login, $pw );
$ok( $auth instanceof WP_User && $uid === $auth->ID, 'setting off: the same pending member signs in' );
$ok( EmailVerification::is_pending( $uid ), 'setting off: the pending mark is kept, not deleted' );

$set( true, false );
$auth = wp_authenticate( $login, $pw );
$ok( $auth instanceof WP_User, 'confirmation email off: the pending member signs in (no link can be sent)' );

$set( true, true );
$auth = wp_authenticate( $login, $pw );
$ok( is_wp_error( $auth ) && 'wcb_email_unverified' === $auth->get_error_code(), 'setting back on: blocked again' );

$wrong = wp_authenticate( $login, 'not-the-password' );
$ok( is_wp_error( $wrong ) && 'incorrect_password' === $wrong->get_error_code(), 'a wrong password still gets core\'s error' );

remove_filter( 'option_wcb_settings', $force );
remove_filter( 'default_option_wcb_settings', $force );
remove_filter( 'pre_wp_mail', $no_mail );
if ( false !== $gate ) {
	add_filter( 'authenticate', 'wbcom_essential_login_security_gate', $gate, 3 );
}
Settings::flush_cache();
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $uid );

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Email verification release tests passed.' );
