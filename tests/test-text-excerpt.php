<?php
/**
 * Excerpt tests (1.8.0).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-text-excerpt.php
 *
 * Pins: an excerpt is plain text with entities decoded, and the server-rendered
 * job cards (first paint) show the same words as the REST cards ("Load more").
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WCB\Core\Text;

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

WP_CLI::log( 'Text excerpt' );

wcb_assert( "What you'll do & more" === Text::excerpt( '<p>What you&#039;ll do &amp; more</p>', 25, '…' ), 'numeric and named entities are decoded to plain text' );
wcb_assert( 'Senior C# developer, snake_case and **bold** stay as written' === Text::excerpt( 'Senior C# developer, snake_case and **bold** stay as written', 25, '…' ), 'characters that look like markdown are not stripped' );
wcb_assert( 'One two…' === Text::excerpt( '<p>One two three</p>', 2, '…' ), 'trimming and the trailing marker still work' );

$wcb_tag = 'txt' . strtolower( wp_generate_password( 5, false, false ) );
$wcb_job = (int) wp_insert_post(
	array(
		'post_type'    => 'wcb_job',
		'post_status'  => 'publish',
		'post_title'   => "{$wcb_tag} entity job",
		'post_content' => "<p>What you&#039;ll do at {$wcb_tag} with C#</p>",
		'post_author'  => 1,
	)
);
$_GET     = array( 'wcb_search' => $wcb_tag );
$wcb_html = do_blocks( '<!-- wp:wp-career-board/job-listings /-->' );
$_GET     = array();
wcb_assert( str_contains( $wcb_html, 'What you\'ll do' ) || str_contains( $wcb_html, 'What you&#039;ll do' ) || str_contains( $wcb_html, 'What you&#39;ll do' ), 'the first-paint card shows the apostrophe, not a broken entity' );
wcb_assert( ! str_contains( $wcb_html, '&amp;039;' ) && ! str_contains( $wcb_html, '&039;' ), '... and never the broken "&039;" form' );
wcb_assert( str_contains( $wcb_html, 'with C#' ), '... and keeps the # in "C#"' );

wp_delete_post( $wcb_job, true );

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All text excerpt tests passed.' );
}
