<?php
/**
 * My Resumes status badge tests (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-resume-badge.php
 *
 * Pins: each My Resumes row says whether the resume is Public or Private,
 * toggled with the wcb-hidden class (a `hidden` attribute loses to the
 * badge's display rule), and opening the tab re-reads the rows so a change
 * made in the builder shows without a reload.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_rb_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

WP_CLI::log( '' );
WP_CLI::log( '  My Resumes Badge Tests' );

$wcb_dir    = dirname( __DIR__ ) . '/blocks/candidate-dashboard/';
$wcb_render = (string) file_get_contents( $wcb_dir . 'render.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
$wcb_view   = (string) file_get_contents( $wcb_dir . 'view.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.

preg_match( '/<div class="wcb-resume-card-info">(.*?)<\/div>/s', $wcb_render, $wcb_info );
$wcb_info = $wcb_info[1] ?? '';
wcb_rb_assert( (bool) preg_match( '/class="wcb-badge wcb-badge--success" data-wp-class--wcb-hidden="!context\.resume\.isPublic"[^>]*>.*Public/', $wcb_info ), 'a public resume shows the Public badge' );
wcb_rb_assert( (bool) preg_match( '/class="wcb-badge wcb-badge--neutral" data-wp-class--wcb-hidden="context\.resume\.isPublic"[^>]*>.*Private/', $wcb_info ), 'a private resume shows the Private badge' );
wcb_rb_assert( ! preg_match( '/wcb-badge[^>]*\shidden[\s>]/', $wcb_info ), 'the badges never rely on the hidden attribute' );

preg_match( '/\*switchToResumes\(\) \{(.*?)\n\t\t\},/s', $wcb_view, $wcb_fn );
$wcb_fn = $wcb_fn[1] ?? '';
wcb_rb_assert( '' !== $wcb_fn && ! preg_match( '/if \( state\.resumes\.length \) \{\s*return;/', $wcb_fn ), 'opening My Resumes re-reads the rows' );

WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
}
WP_CLI::success( 'All My Resumes badge tests passed.' );
