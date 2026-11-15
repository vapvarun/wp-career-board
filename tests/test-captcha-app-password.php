<?php
/**
 * An application-password client is not held to the CAPTCHA (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-captcha-app-password.php
 *
 * Pins: with a CAPTCHA provider in force, a browser request without a token is
 * refused, a request signed in with an application password passes, the honeypot
 * still refuses, and wcb_captcha_skip can hold API clients to the CAPTCHA too.
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

$old = get_option( 'wcb_settings' );
update_option( 'wcb_settings', array_merge( (array) $old, array( 'captcha_provider' => 'turnstile', 'turnstile_site_key' => 'site-key', 'turnstile_secret_key' => 'secret-key' ) ) );
$module = new \WCB\Modules\AntiSpam\AntiSpamModule();
$module->boot();
$req = static fn( array $params = array() ) => ( static function ( array $p ): WP_REST_Request {
	$r = new WP_REST_Request( 'POST', '/wcb/v1/jobs' );
	foreach ( $p as $k => $v ) {
		$r->set_param( $k, $v );
	}
	return $r;
} )( $params );
$code = static fn( $res ) => is_wp_error( $res ) ? $res->get_error_code() : 'pass';

unset( $GLOBALS['wp_rest_application_password_uuid'] );
$ok( 'wcb_captcha_failed' === $code( $module->verify_request( null, $req() ) ), 'a browser request with no token is refused' );

$GLOBALS['wp_rest_application_password_uuid'] = 'test-uuid';
$ok( 'pass' === $code( $module->verify_request( null, $req() ) ), 'an application-password request passes with no token' );
$ok( 'wcb_spam' === $code( $module->verify_request( null, $req( array( 'hp' => 'bot' ) ) ) ), 'the honeypot still refuses it' );

add_filter( 'wcb_captcha_skip', '__return_false' );
$ok( 'wcb_captcha_failed' === $code( $module->verify_request( null, $req() ) ), 'wcb_captcha_skip returning false holds API clients to the CAPTCHA' );
remove_filter( 'wcb_captcha_skip', '__return_false' );

unset( $GLOBALS['wp_rest_application_password_uuid'] );
foreach ( array( 'wcb_pre_job_submit', 'wcb_pre_application_submit', 'wcb_pre_registration' ) as $hook ) {
	remove_filter( $hook, array( $module, 'verify_request' ), 10 );
}
update_option( 'wcb_settings', $old );

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'CAPTCHA and application passwords hold.' );
