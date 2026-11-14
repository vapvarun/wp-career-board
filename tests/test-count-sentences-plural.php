<?php
/**
 * Count sentences in the admin use the right plural form (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-count-sentences-plural.php
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

$line = static fn ( int $n ): string => \WCB\Admin\AdminImport::preview_sentence( array( 'jobs' => $n, 'filled' => $n, 'companies_new' => $n, 'applications' => $n ) );

foreach ( array( 0, 1, 2, 5 ) as $n ) {
	$s = $line( $n );
	if ( 1 === $n ) {
		$ok( str_contains( $s, '1 job to import' ) && str_contains( $s, '1 filled job will be closed' ) && str_contains( $s, '1 new company page' ) && str_contains( $s, '1 application' ), 'import preview with 1: singular forms (' . $s . ')' );
		$ok( ! preg_match( '/\b1 (jobs|companies|filled jobs|new company pages|applications)\b/', $s ), 'import preview with 1: no "1 jobs" or "1 applications"' );
	} else {
		$ok( str_contains( $s, $n . ' jobs to import' ) && str_contains( $s, $n . ' applications' ), 'import preview with ' . $n . ': plural forms' );
	}
}

// Industries save: the endpoint builds the finished sentence.
$ok( 'Industries saved.' === \WCB\Api\Endpoints\AdminEndpoint::industries_saved_message( 0 ), 'industries toast, 0: "Industries saved."' );
$ok( 'Industries saved. 1 company updated.' === \WCB\Api\Endpoints\AdminEndpoint::industries_saved_message( 1 ), 'industries toast, 1: "1 company updated"' );
$ok( 'Industries saved. 5 companies updated.' === \WCB\Api\Endpoints\AdminEndpoint::industries_saved_message( 5 ), 'industries toast, 5: "5 companies updated"' );

wp_set_current_user( 1 );
$cur = rest_do_request( new WP_REST_Request( 'GET', '/wcb/v1/admin/industries' ) )->get_data();
$req = new WP_REST_Request( 'POST', '/wcb/v1/admin/industries' );
$req->set_header( 'content-type', 'application/json' );
$req->set_body( wp_json_encode( array( 'industries' => $cur['industries'] ?? array() ) ) );
$data = rest_do_request( $req )->get_data();
$ok( 'Industries saved.' === ( $data['message'] ?? '' ), 'the route returns the message (nothing moved)' );

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Plural count sentence tests passed.' );
