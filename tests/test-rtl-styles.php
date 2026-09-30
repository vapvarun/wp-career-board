<?php
/**
 * RTL stylesheet opt-in tests (1.8.0).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-rtl-styles.php
 *
 * Pins: on an RTL request a style of ours with a generated -rtl twin prints the
 * twin, including a block style that core registered with rtl=replace and a
 * `.min` suffix (the SCRIPT_DEBUG-off case that left the job form on its
 * left-to-right file and scrolled the page 10,000px); a style with no twin
 * keeps its own file; a style outside our folders is untouched; and nothing
 * changes on an LTR request.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Core\Rtl;

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
 * The <link> tag WordPress prints for a handle, printed fresh each call.
 *
 * @param string $handle Style handle.
 * @return string
 */
function wcb_rtl_link( string $handle ): string {
	$styles       = wp_styles();
	$styles->done = array_values( array_diff( $styles->done, array( $handle ) ) );
	ob_start();
	$styles->do_item( $handle );
	return (string) ob_get_clean();
}

WP_CLI::log( 'RTL style opt-in' );

$wcb_css_dir  = WCB_DIR . 'assets/css/';
$wcb_fixtures = array( 'zz-rtl-fixture.css', 'zz-rtl-fixture-rtl.css', 'zz-rtl-none.css' );
foreach ( $wcb_fixtures as $wcb_file ) {
	file_put_contents( $wcb_css_dir . $wcb_file, ".x { margin-left: 1px; }\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- throwaway fixture inside the plugin folder.
}

$wcb_locale   = $GLOBALS['wp_locale'];
$wcb_was_dir  = $wcb_locale->text_direction;
$wcb_style_dr = wp_styles()->text_direction;

wp_register_style( 'wcb-test-rtl-block', WCB_URL . 'assets/css/zz-rtl-fixture.css', array(), '1' );
// What core does for a block.json style when SCRIPT_DEBUG is off.
wp_style_add_data( 'wcb-test-rtl-block', 'rtl', 'replace' );
wp_style_add_data( 'wcb-test-rtl-block', 'suffix', '.min' );
wp_register_style( 'wcb-test-rtl-plain', WCB_URL . 'assets/css/zz-rtl-fixture.css', array(), '1' );
wp_register_style( 'wcb-test-rtl-none', WCB_URL . 'assets/css/zz-rtl-none.css', array(), '1' );
wp_register_style( 'wcb-test-rtl-other', 'https://example.com/plugins/other/x.css', array(), '1' );

$wcb_locale->text_direction  = 'rtl';
wp_styles()->text_direction  = 'rtl';

wcb_assert( false === strpos( wcb_rtl_link( 'wcb-test-rtl-block' ), 'zz-rtl-fixture-rtl.css' ), 'before opt-in, core leaves a .min-suffixed block style on its LTR file (the bug)' );

Rtl::enable();

wcb_assert( false !== strpos( wcb_rtl_link( 'wcb-test-rtl-block' ), 'zz-rtl-fixture-rtl.css' ), 'a block style core marked rtl=replace with a .min suffix now prints its -rtl twin' );
wcb_assert( false !== strpos( wcb_rtl_link( 'wcb-test-rtl-plain' ), 'zz-rtl-fixture-rtl.css' ), 'a plain enqueued style with a twin prints the twin' );
wcb_assert( false === strpos( wcb_rtl_link( 'wcb-test-rtl-none' ), '-rtl.css' ), 'a style with no twin keeps its own file (no 404)' );
wcb_assert( false === strpos( wcb_rtl_link( 'wcb-test-rtl-other' ), '-rtl.css' ), 'a style outside our plugin folders is not touched' );

// LTR request: nothing is opted in.
$wcb_locale->text_direction = 'ltr';
wp_styles()->text_direction = 'ltr';
wp_register_style( 'wcb-test-rtl-ltr', WCB_URL . 'assets/css/zz-rtl-fixture.css', array(), '1' );
Rtl::enable();
$wcb_ltr_data = wp_styles()->get_data( 'wcb-test-rtl-ltr', 'rtl' );
wcb_assert( empty( $wcb_ltr_data ), 'on an LTR request no style is opted into RTL' );

// Cleanup.
foreach ( array( 'wcb-test-rtl-block', 'wcb-test-rtl-plain', 'wcb-test-rtl-none', 'wcb-test-rtl-other', 'wcb-test-rtl-ltr' ) as $wcb_handle ) {
	wp_deregister_style( $wcb_handle );
}
foreach ( $wcb_fixtures as $wcb_file ) {
	unlink( $wcb_css_dir . $wcb_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- removing the throwaway fixture.
}
$wcb_locale->text_direction = $wcb_was_dir;
wp_styles()->text_direction = $wcb_style_dr;

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All RTL style tests passed.' );
}
