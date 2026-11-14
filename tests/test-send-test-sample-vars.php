<?php
/**
 * Send test / preview sample values cover every merge tag (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-send-test-sample-vars.php
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

$user = get_userdata( 1 );
$vars = \WCB\Api\Endpoints\AdminEndpoint::sample_vars( $user, true );

$emails = (array) apply_filters( 'wcb_registered_emails', array() );
$ok( count( $emails ) > 0, 'emails are registered' );

foreach ( $emails as $email ) {
	if ( ! $email instanceof \WCB\Modules\Notifications\AbstractEmail ) {
		continue;
	}
	$missing = array_values( array_diff( array_keys( $email->get_merge_tags() ), array_keys( $vars ) ) );
	$ok( array() === $missing, $email->get_id() . ' has a sample value for every tag' . ( $missing ? ' (missing: ' . implode( ', ', $missing ) . ')' : '' ) );
}

$test = \WCB\Api\Endpoints\AdminEndpoint::sample_vars( $user, false );
$ok( str_contains( (string) $test['job_title'], '(test send)' ) && ! str_contains( (string) $vars['job_title'], '(test send)' ), 'only the sent test is marked' );

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Send-test sample vars passed.' );
