<?php
/**
 * check-journey-routes.php — journey REST-route drift gate.
 *
 * Every `/wcb/v1/...` path referenced in a journey under audit/journeys/ must
 * resolve to a route the plugin actually registers. Catches two failure modes
 * a release should never ship:
 *   1. a journey asserting a dead/renamed route (the gate would pass-by-luck or
 *      false-fail forever), and
 *   2. manifest/journey drift after a route is renamed or removed.
 *
 * Needs the live REST server, so it runs under WP-CLI:
 *   wp eval-file bin/check-journey-routes.php
 * (wired as `composer journeys:routes` and as a pre-flight in run-journeys.sh).
 *
 * Exit: 0 = every referenced route resolves; 1 = one or more dead routes.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "check-journey-routes: must run under WP-CLI (wp eval-file).\n" );
	exit( 2 );
}

$root         = dirname( __DIR__ );
$journeys_dir = $root . '/audit/journeys';
if ( ! is_dir( $journeys_dir ) ) {
	echo "check-journey-routes: no audit/journeys/ — nothing to check.\n";
	exit( 0 );
}

// 1. Registered /wcb/v1 route patterns (PCRE-ready).
$registered = array();
foreach ( array_keys( rest_get_server()->get_routes() ) as $route ) {
	if ( 0 === strpos( $route, '/wcb/v1/' ) ) {
		$registered[] = $route;
	}
}

// 2. Collect every /wcb/v1 path referenced in the journeys.
$refs = array();
$it   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $journeys_dir, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file ) {
	if ( 'md' !== strtolower( $file->getExtension() ) ) {
		continue;
	}
	$content = (string) file_get_contents( $file->getPathname() );
	// Capture the whole path token (stop only at whitespace / backtick / quote),
	// then trim trailing prose punctuation. This keeps query strings, $shell vars
	// and raw `(?P<id>\d+)` regex forms intact so normalisation can handle them.
	if ( preg_match_all( '~/wcb/v1/[^\s`"\']+~', $content, $m ) ) {
		foreach ( $m[0] as $ref ) {
			$ref = rtrim( $ref, ").,;:" );
			$refs[ $ref ][ str_replace( $journeys_dir . '/', '', $file->getPathname() ) ] = true;
		}
	}
}

// 3. Reduce a referenced path to a concrete instance, then match. A path segment
// that is a placeholder (`<id>`, `{id}`, `(?P<id>\d+)`), a $shell var, a pure
// number, or carries a query (`?q=`) collapses to "1" so it matches the route's
// dynamic parameter.
$concrete = static function ( string $p ): string {
	$p   = preg_replace( '~\?.*$~', '', $p ); // drop query string
	$out = array();
	foreach ( explode( '/', $p ) as $seg ) {
		if ( '' === $seg || 'wcb' === $seg || 'v1' === $seg ) {
			$out[] = $seg;
			continue;
		}
		if ( ctype_digit( $seg ) || preg_match( '~[<{($=]~', $seg ) ) {
			$out[] = '1';
			continue;
		}
		$out[] = $seg;
	}
	return implode( '/', $out );
};

$matches = static function ( string $ref ) use ( $registered, $concrete ): bool {
	$c = $concrete( $ref );
	// Prefix used for bare-collection or wildcard mentions (e.g. `/candidates`
	// or `/fields/*` standing in for the whole route group). Strip a trailing
	// wildcard and slash.
	$prefix = rtrim( (string) preg_replace( '~\*+.*$~', '', $ref ), '/' );
	foreach ( $registered as $route ) {
		if ( preg_match( '~^' . $route . '$~', $c ) ) {
			return true; // exact endpoint match
		}
		if ( '/wcb/v1' !== $prefix && ( $route === $prefix || 0 === strpos( $route, $prefix . '/' ) ) ) {
			return true; // collection / wildcard prefix mention
		}
	}
	return false;
};

$dead = array();
foreach ( $refs as $ref => $files ) {
	if ( ! $matches( $ref ) ) {
		$dead[ $ref ] = array_keys( $files );
	}
}

if ( empty( $dead ) ) {
	printf( "check-journey-routes: OK — all %d referenced /wcb/v1 paths resolve to registered routes.\n", count( $refs ) );
	exit( 0 );
}

fwrite( STDERR, "check-journey-routes: DEAD route references (journey asserts a route the plugin does not register):\n" );
foreach ( $dead as $ref => $files ) {
	fwrite( STDERR, sprintf( "  ✗ %s\n      in: %s\n", $ref, implode( ', ', $files ) ) );
}
fwrite( STDERR, "Fix the journey to a registered route, or add the route to the plugin.\n" );
exit( 1 );
