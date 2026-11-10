<?php
/**
 * Text::dash_lists(): dash lists typed in the editor show as bullets (card 10401139250).
 * Text::from_editor(): cover letter and bio keep their paragraphs and lists (card 10442120272).
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

// from_editor(): the old save (sanitize_textarea_field on the editor's HTML)
// returned "PHP 8React" and "QuoteLine oneline two"; each case below fails on it.
$wcb_editor_cases = array(
	'paragraphs become blank-line blocks' => array( '<p>First.</p><p>Second.</p>', "First.\n\nSecond." ),
	'list items become "- " lines'        => array( '<p>Skills:</p><ul><li>PHP <b>8</b></li><li>React</li></ul>', "Skills:\n\n- PHP 8\n- React" ),
	'line breaks and entities kept'       => array( '<p>Tom &amp; Jerry<br>line two</p>', "Tom & Jerry\nline two" ),
	'heading and quote stay separate'     => array( '<h3>Why me</h3><blockquote>Quote</blockquote><p>End</p>', "Why me\n\nQuote\n\nEnd" ),
	'plain text passes through'           => array( "plain\n\n- a\n- b", "plain\n\n- a\n- b" ),
	'CRLF from a multipart form'          => array( "<p>One</p>\r\n<ul><li>a</li></ul>\r\n<p>Two</p>", "One\n\n- a\n\nTwo" ),
);
foreach ( $wcb_editor_cases as $wcb_label => list( $wcb_in, $wcb_want ) ) {
	$wcb_got = sanitize_textarea_field( \WCB\Core\Text::from_editor( $wcb_in ) );
	wcb_assert( $wcb_want === $wcb_got, 'from_editor: ' . $wcb_label . ( $wcb_want === $wcb_got ? '' : ' (got ' . wp_json_encode( $wcb_got ) . ')' ) );
}

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All Text helper tests passed.' );
}
