<?php
/**
 * Default listing length is held to 1-365 days on every write and read (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-jobs-expire-days-bound.php
 *
 * Pins: the settings form, the setup wizard REST route and the schema all
 * store `jobs_expire_days` between 1 and 365 (the field's own max), and a
 * value above 365 left by an older version never pushes a default deadline
 * past a year.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Admin\AdminSettings;
use WCB\Admin\Settings;
use WCB\Admin\SettingsSchema;
use WCB\Core\JobDeadline;

$pass = 0;
$fail = 0;
$ok   = static function ( bool $c, string $l ) use ( &$pass, &$fail ): void {
	$c ? ++$pass : ++$fail;
	WP_CLI::log( ( $c ? '  PASS: ' : '  FAIL: ' ) . $l );
};

wp_set_current_user( 1 );
$had_option = false !== get_option( 'wcb_settings', false );
$saved      = get_option( 'wcb_settings', array() );

$ok( 365 === SettingsSchema::sanitize( 'jobs_expire_days', 9999 ), 'schema: 9999 is stored as 365' );
$ok( 1 === SettingsSchema::sanitize( 'jobs_expire_days', 0 ), 'schema: 0 is stored as 1' );
$ok( 45 === SettingsSchema::sanitize( 'jobs_expire_days', 45 ), 'schema: 45 is kept' );

$form = ( new AdminSettings() )->sanitize(
	array(
		'_wcb_form'        => '1',
		'jobs_expire_days' => '999999',
	)
);
$ok( 365 === (int) $form['jobs_expire_days'], 'settings form: 999999 is stored as 365' );

$req = new WP_REST_Request( 'POST', '/wcb/v1/wizard/settings' );
$req->set_header( 'content-type', 'application/json' );
$req->set_body( (string) wp_json_encode( array( 'settings' => array( 'jobs_expire_days' => 5000 ) ) ) );
$res = rest_do_request( $req );
Settings::flush_cache();
$ok( 200 === $res->get_status() && 365 === Settings::int( 'jobs_expire_days' ), 'setup wizard: 5000 is stored as 365' );

// A value above the limit stored by an older version.
update_option( 'wcb_settings', array_merge( (array) $saved, array( 'jobs_expire_days' => 99999 ) ) );
Settings::flush_cache();
$end  = JobDeadline::default_end( new WP_REST_Request( 'POST', '/wcb/v1/jobs' ) );
$days = (int) round( ( strtotime( $end ) - time() ) / DAY_IN_SECONDS );
$ok( $days >= 364 && $days <= 367, 'a stored 99999 gives a default deadline about a year out (got ' . $days . ' days)' );

// A board's own listing length (Pro filters it in) is held to the same limit.
$long_board = static fn (): int => 5000;
add_filter( 'wcb_job_default_expiry_days', $long_board );
$end  = JobDeadline::default_end( new WP_REST_Request( 'POST', '/wcb/v1/jobs' ) );
remove_filter( 'wcb_job_default_expiry_days', $long_board );
$days = (int) round( ( strtotime( $end ) - time() ) / DAY_IN_SECONDS );
$ok( $days >= 364 && $days <= 367, 'a board length of 5000 days gives a deadline about a year out (got ' . $days . ' days)' );

if ( $had_option ) {
	update_option( 'wcb_settings', $saved );
} else {
	delete_option( 'wcb_settings' );
}
Settings::flush_cache();

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Listing length bound tests passed.' );
