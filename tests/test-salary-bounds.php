<?php
/**
 * Salary bounds (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-salary-bounds.php
 *
 * Pins: a salary figure must be a number from 0 to JobsMeta::MAX_SALARY. A
 * negative or absurd figure is refused by the REST rules with a message that
 * names the range, and is not stored by the meta sanitizer.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Modules\Jobs\JobsMeta;

$wcb_pass = 0;
$wcb_fail = 0;
$wcb_chk  = static function ( bool $ok, string $label ) use ( &$wcb_pass, &$wcb_fail ): void {
	$ok ? ++$wcb_pass : ++$wcb_fail;
	WP_CLI::log( ( $ok ? '  PASS: ' : '  FAIL: ' ) . $label );
};

$wcb_chk( '45000' === JobsMeta::sanitize_amount( '45000' ), 'a normal figure is kept' );
$wcb_chk( '0' === JobsMeta::sanitize_amount( '0' ), 'zero is kept' );
$wcb_chk( (string) JobsMeta::MAX_SALARY === JobsMeta::sanitize_amount( JobsMeta::MAX_SALARY ), 'the cap itself is kept' );
$wcb_chk( '' === JobsMeta::sanitize_amount( '99999999999' ), 'a figure above the cap is dropped' );
$wcb_chk( '' === JobsMeta::sanitize_amount( '-5000' ), 'a negative figure is dropped' );
$wcb_chk( '' === JobsMeta::sanitize_amount( 'abc' ), 'text is dropped' );

$wcb_params = ( new \WCB\Api\Endpoints\JobsEndpoint() )->get_write_params();
foreach ( array( 'salary_min', 'salary_max' ) as $wcb_key ) {
	$wcb_cb = $wcb_params[ $wcb_key ]['validate_callback'];
	$wcb_chk( true === $wcb_cb( '60000' ), "{$wcb_key}: 60000 passes the route rule" );
	$wcb_res = $wcb_cb( '99999999999' );
	$wcb_chk( is_wp_error( $wcb_res ) && false !== strpos( $wcb_res->get_error_message(), number_format_i18n( JobsMeta::MAX_SALARY ) ), "{$wcb_key}: an absurd figure is refused and the message names the range" );
	$wcb_res = $wcb_cb( '-5000' );
	$wcb_chk( is_wp_error( $wcb_res ), "{$wcb_key}: a negative figure is refused" );
}

WP_CLI::log( "  Total: {$wcb_pass}+{$wcb_fail}  Pass: {$wcb_pass}  Fail: {$wcb_fail}" );
$wcb_fail > 0 ? WP_CLI::error( "{$wcb_fail} test(s) failed." ) : WP_CLI::success( 'Salary bounds hold.' );
