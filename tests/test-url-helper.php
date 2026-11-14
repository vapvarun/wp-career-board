<?php
/**
 * Query parameters on plain-permalink URLs (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-url-helper.php
 *
 * Pins: wcbUrl() (@wcb/fetch) adds a parameter with "&" when the URL already
 * has a query string (plain permalinks: ?rest_route=, ?page_id=) and with "?"
 * otherwise, and no block glues "?name=" onto an API or dashboard URL itself.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$wcb_pass = 0;
$wcb_fail = 0;
$wcb_chk  = static function ( bool $ok, string $label ) use ( &$wcb_pass, &$wcb_fail ): void {
	$ok ? ++$wcb_pass : ++$wcb_fail;
	WP_CLI::log( ( $ok ? '  PASS: ' : '  FAIL: ' ) . $label );
};

$wcb_cases = array(
	array( 'https://site.test/wp-json/wcb/v1/notifications', array( 'per_page' => 20 ) ),
	array( 'https://site.test/?rest_route=/wcb/v1/notifications', array( 'per_page' => 20 ) ),
	array( 'https://site.test/?page_id=107', array( 'job_apps' => 5 ) ),
	array( 'https://site.test/dashboard/', array( 'resume_id' => 9 ) ),
);
$wcb_src  = 'globalThis.window={location:{href:"https://site.test/"}};globalThis.document={addEventListener(){}};const m=await import(' . wp_json_encode( 'file://' . dirname( __DIR__ ) . '/assets/js/modules/wcb-fetch.js' ) . ');console.log(JSON.stringify(' . wp_json_encode( $wcb_cases ) . '.map(([u,p])=>m.wcbUrl(u,p))));';
$wcb_out  = json_decode( (string) shell_exec( 'node --input-type=module -e ' . escapeshellarg( $wcb_src ) . ' 2>/dev/null' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test runner calls node on a repo file.
$wcb_chk( is_array( $wcb_out ) && 4 === count( $wcb_out ), 'wcbUrl runs (node)' );
if ( is_array( $wcb_out ) && 4 === count( $wcb_out ) ) {
	foreach ( $wcb_out as $wcb_i => $wcb_url ) {
		$wcb_chk( 1 === substr_count( $wcb_url, '?' ), 'one "?" in ' . $wcb_url );
	}
	parse_str( (string) wp_parse_url( $wcb_out[1], PHP_URL_QUERY ), $wcb_q );
	$wcb_chk( '/wcb/v1/notifications' === ( $wcb_q['rest_route'] ?? '' ) && '20' === ( $wcb_q['per_page'] ?? '' ), 'plain permalinks keep rest_route and add per_page' );
	parse_str( (string) wp_parse_url( $wcb_out[2], PHP_URL_QUERY ), $wcb_q );
	$wcb_chk( '107' === ( $wcb_q['page_id'] ?? '' ) && '5' === ( $wcb_q['job_apps'] ?? '' ), 'a ?page_id= dashboard URL keeps it and adds job_apps' );
}

// No block builds "<url>?name=" itself any more.
$wcb_root = dirname( dirname( __DIR__ ) ) . '/';
$wcb_bad  = array();
foreach ( array( 'wp-career-board/blocks', 'wp-career-board-pro/blocks' ) as $wcb_dir ) {
	if ( ! is_dir( $wcb_root . $wcb_dir ) ) {
		continue;
	}
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $wcb_root . $wcb_dir, FilesystemIterator::SKIP_DOTS ) ) as $wcb_file ) {
		if ( 'view.js' !== $wcb_file->getFilename() ) {
			continue;
		}
		if ( preg_match( "/(apiBase|dashboardUrl|geocodeUrl)[^;\\n]*\\+\\s*'[^']*\\?[a-z_]+=/", (string) file_get_contents( $wcb_file->getPathname() ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
			$wcb_bad[] = str_replace( $wcb_root, '', $wcb_file->getPathname() );
		}
	}
}
$wcb_chk( array() === $wcb_bad, 'no view.js glues a query string onto an API or dashboard URL' . ( $wcb_bad ? ': ' . implode( ', ', $wcb_bad ) : '' ) );

WP_CLI::log( "  Total: {$wcb_pass}+{$wcb_fail}  Pass: {$wcb_pass}  Fail: {$wcb_fail}" );
$wcb_fail > 0 ? WP_CLI::error( "{$wcb_fail} test(s) failed." ) : WP_CLI::success( 'Query strings are built safely.' );
