<?php
/**
 * The built-in CAPTCHA is gone; the honeypot stays (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-captcha-removed.php
 *
 * Pins: the honeypot refuses a filled field on job, apply and sign-up; no token
 * is needed or checked; the upgrade step deletes stored provider keys; app-config
 * no longer advertises a CAPTCHA.
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

$req = static function ( array $p = array() ): WP_REST_Request {
	$r = new WP_REST_Request( 'POST', '/wcb/v1/jobs' );
	foreach ( $p as $k => $v ) {
		$r->set_param( $k, $v );
	}
	return $r;
};
$module = new \WCB\Modules\AntiSpam\AntiSpamModule();
$ok( null === $module->verify_request( null, $req() ), 'a request with no token passes the gate' );
$ok( 'wcb_spam' === ( $module->verify_request( null, $req( array( 'hp' => 'bot' ) ) )->get_error_code() ?? '' ), 'a filled honeypot is refused' );
foreach ( array( 'wcb_pre_job_submit', 'wcb_pre_application_submit', 'wcb_pre_registration' ) as $hook ) {
	$res = apply_filters( $hook, null, $req( array( 'hp' => 'bot' ) ) );
	$ok( is_wp_error( $res ) && 'wcb_spam' === $res->get_error_code(), "{$hook} runs the honeypot" );
}
$ok( ! class_exists( '\WCB\Modules\AntiSpam\TurnstileDriver' ) && ! function_exists( 'wcb_get_captcha_driver' ), 'no CAPTCHA driver or helper is left' );

$old = get_option( 'wcb_settings' );
update_option( 'wcb_settings', array_merge( (array) $old, array( 'captcha_provider' => 'turnstile', 'turnstile_secret_key' => 'secret', 'recaptcha_threshold' => 0.5, 'brand_name_keep' => 'x' ) ) );
\WCB\Core\Install::remove_captcha_settings();
$now = (array) get_option( 'wcb_settings' );
$ok( ! isset( $now['captcha_provider'] ) && ! isset( $now['turnstile_secret_key'] ) && ! isset( $now['recaptcha_threshold'] ), 'the upgrade step deletes the stored provider and keys' );
$ok( 'x' === ( $now['brand_name_keep'] ?? '' ), 'and leaves every other setting alone' );
update_option( 'wcb_settings', $old );

$cfg = rest_do_request( new WP_REST_Request( 'GET', '/wcb/v1/settings/app-config' ) )->get_data();
$ok( ! array_key_exists( 'captcha', $cfg ) && ! array_key_exists( 'captcha_required', $cfg ), 'app-config no longer advertises a CAPTCHA' );

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'The CAPTCHA is gone and the honeypot holds.' );
