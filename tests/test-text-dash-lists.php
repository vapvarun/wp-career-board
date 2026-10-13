<?php
/**
 * Text::dash_lists(): dash lists typed in the editor show as bullets (card 10401139250).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-text-dash-lists.php
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
function wcb_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::warning( "  FAIL: {$label}" );
	}
}

$wcb_cases = array(
	'one-paragraph list after a colon'      => array( '<p><b>You bring:</b> - 8+ years in B2B - Deep experience with developer-first brands - Data-driven</p>', '<p><b>You bring:</b></p><ul><li>8+ years in B2B</li><li>Deep experience with developer-first brands</li><li>Data-driven</li></ul>' ),
	'editor paragraphs starting with "- "'  => array( '<p>What you will do</p><p>- Own the roadmap</p><p>- Ship weekly</p><p>Then more prose.</p>', '<p>What you will do</p><ul><li>Own the roadmap</li><li>Ship weekly</li></ul><p>Then more prose.</p>' ),
	'bullet character paragraphs'           => array( '<p>• Remote first</p><p>• Four-day week</p>', '<ul><li>Remote first</li><li>Four-day week</li></ul>' ),
	'a dash inside prose stays prose'       => array( '<p>Our Paris - London office is great.</p>', '<p>Our Paris - London office is great.</p>' ),
	'a salary range after a colon stays'    => array( '<p>Salary: 90k - 120k depending on experience.</p>', '<p>Salary: 90k - 120k depending on experience.</p>' ),
	'a real list is untouched'              => array( '<ul><li>Already a list</li></ul>', '<ul><li>Already a list</li></ul>' ),
);
foreach ( $wcb_cases as $wcb_label => list( $wcb_in, $wcb_want ) ) {
	$wcb_got = \WCB\Core\Text::dash_lists( $wcb_in );
	wcb_assert( $wcb_want === $wcb_got, $wcb_label . ( $wcb_want === $wcb_got ? '' : " (got {$wcb_got})" ) );
}

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All dash-list tests passed.' );
}
