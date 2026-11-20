<?php
/**
 * Company badge letters are the same on the list, the job page and the company page (1.8.5).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-company-initials.php
 *
 * The job page cut the first two letters ("LI") while lists used the word
 * initials ("LS"). Every surface now calls CompanyMetaShape::initials().
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

$ok( 'LS' === \WCB\Core\CompanyMetaShape::initials( 'Lighthouse Studio' ), 'two words give their first letters' );
$ok( 'MF' === \WCB\Core\CompanyMetaShape::initials( 'Marlow & Finch LLP' ), 'a symbol word is skipped' );

$root = dirname( __DIR__ );
foreach ( array( 'blocks/job-single/render.php', 'blocks/company-profile/render.php', 'api/endpoints/class-companies-endpoint.php' ) as $file ) {
	$src = (string) file_get_contents( $root . '/' . $file );
	$ok( ! preg_match( '/mb_substr\(\s*\$\w+(?:->\w+)?(?:\s*\?[^,]*:[^,]*)?,\s*0,\s*2\s*\)/', $src ) && str_contains( $src, 'CompanyMetaShape::initials' ), "$file uses the one initials helper" );
}

WP_CLI::log( "{$pass} passed, {$fail} failed" );
exit( $fail ? 1 : 0 );
