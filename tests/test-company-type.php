<?php
/**
 * Company type: a stored slug or a legacy label reads back as the slug (card 10401463764).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-company-type.php
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcb_pass = 0;
$wcb_fail = 0;
$wcb_cases = array(
	'private'        => 'private',
	'Private'        => 'private',
	'PRIVATE'        => 'private',
	'Privately Held' => 'private',
	'Public'         => 'public',
	'Non-profit'     => 'nonprofit',
	'self-employed'  => 'self-employed',
	'Startup'        => '',
	''               => '',
);
foreach ( $wcb_cases as $wcb_in => $wcb_want ) {
	$wcb_got = \WCB\Core\CompanyMetaShape::type_slug( (string) $wcb_in );
	if ( $wcb_want === $wcb_got ) {
		++$wcb_pass;
		WP_CLI::log( "  PASS: '{$wcb_in}' -> '{$wcb_got}'" );
	} else {
		++$wcb_fail;
		WP_CLI::warning( "  FAIL: '{$wcb_in}' -> '{$wcb_got}' (want '{$wcb_want}')" );
	}
}
WP_CLI::log( '  Total: ' . ( $wcb_pass + $wcb_fail ) . '  Pass: ' . $wcb_pass . '  Fail: ' . $wcb_fail );
if ( $wcb_fail ) {
	WP_CLI::error( $wcb_fail . ' test(s) failed.' );
}
