<?php
/**
 * Setup wizard defaults (1.8.4): sample data is opt-in, and Skip on Sign-ups
 * stores what the screen shows.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-wizard-defaults.php
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

$dir = WCB_DIR . 'admin/views/wizard-steps/';
$ok( ! preg_match( '/id="wcb-install-sample"[^>]*checked/', (string) file_get_contents( $dir . 'sample-data.php' ) ), 'sample data box has no checked state' );
$ok( false !== strpos( (string) file_get_contents( $dir . 'registration.php' ), '$wcb_skip_saves = true' ), 'Sign-ups step asks Skip to save' );
$ok( false !== strpos( (string) file_get_contents( $dir . '_footer.php' ), 'data-wcb-skip-saves' ), 'footer marks Skip when it saves' );
$ok( false !== strpos( (string) file_get_contents( WCB_DIR . 'assets/js/wizard.js' ), "skip.hasAttribute( 'data-wcb-skip-saves' )" ), 'wizard.js saves before skipping when marked' );

WP_CLI::log( "  Total: " . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'All wizard default tests passed.' );
