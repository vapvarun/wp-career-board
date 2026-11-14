<?php
/**
 * A page holding an unregistered Career Board block says so (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-missing-block-notice.php
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

/**
 * Render a page's content through the_content as the main query would.
 *
 * @param int $page_id Page.
 * @return string
 */
function wcb_mbn_render( int $page_id ): string {
	$q                      = new WP_Query( array( 'page_id' => $page_id ) );
	$GLOBALS['wp_the_query'] = $q;
	$GLOBALS['wp_query']     = $q;
	$q->the_post();
	$html = apply_filters( 'the_content', get_post_field( 'post_content', $page_id ) );
	wp_reset_postdata();
	return (string) $html;
}

$gone = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'MBN gone', 'post_content' => '<!-- wp:wcb/never-registered-block /-->' ) );
$live = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'MBN live', 'post_content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->' ) );

wp_set_current_user( 0 );
$ok( false !== strpos( wcb_mbn_render( $gone ), 'not available right now' ), 'visitor sees the short notice' );
$ok( false === strpos( wcb_mbn_render( $live ), 'wcb-missing-block' ), 'an ordinary page gets nothing' );

wp_set_current_user( 1 );
$ok( false !== strpos( wcb_mbn_render( $gone ), 'needs WP Career Board Pro' ), 'site owner is told what to do' );

wp_delete_post( $gone, true );
wp_delete_post( $live, true );

WP_CLI::log( "  Total: " . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'Missing block notice tests passed.' );
