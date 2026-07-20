<?php
/**
 * Release gate: every registered REST route must have a reachable caller.
 *
 * The "orphaned route" bug class — a route fully implemented server-side but
 * with no JS/PHP caller anywhere, so the feature is unreachable from the
 * product (e.g. the pipeline-stage CRUD and /fields/reorder routes that shipped
 * with no admin UI). Journeys and the browser smoke walk the UI *forward* and
 * never notice a route the UI doesn't lead to; this scans *backward* from the
 * code's registered surface and asserts each route is wired.
 *
 * Detection:
 *   1. Enumerate every register_rest_route( ns, PATH, ... ) and its file.
 *   2. For each route derive a needle: the last LITERAL path segment with a
 *      leading slash ("/stages", "/reorder"), or the whole literal path when
 *      the route has no regex params. The leading slash keeps it a URL match,
 *      not a bare word — a "Stages" column label never matches "/stages".
 *   3. A route is CALLED if that needle appears in a caller surface: any .js
 *      under assets/ or blocks/, or any PHP that builds a URL (rest_url() /
 *      apiFetch / rest_do_request) OTHER than the file that registers it.
 *   4. Routes with no caller and not in bin/route-callers-allowlist.txt fail.
 *
 * Allowlist (one route path per line, '#' comments) is for routes that are
 * legitimately server-only: inbound webhooks, cross-plugin dispatch, etc.
 *
 * Usage:  php bin/check-route-callers.php [/abs/plugin/dir]
 * Exit:   0 = every route reachable (or allowlisted); 1 = orphaned route(s).
 *
 * @package WP_Career_Board
 */

declare( strict_types=1 );

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : getcwd();

/**
 * Recursively collect files under $dir with one of the given extensions,
 * skipping vendor/node_modules/dist/tests.
 *
 * @param string        $dir  Directory.
 * @param array<string> $exts Extensions without the dot.
 * @return array<int,string>
 */
function wcb_rc_files( string $dir, array $exts ): array {
	if ( ! is_dir( $dir ) ) {
		return array();
	}
	$out = array();
	$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$path = $file->getPathname();
		if ( preg_match( '#/(vendor|node_modules|dist|tests|bin|\.git|\.playwright-mcp)/#', $path ) ) {
			continue;
		}
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, $exts, true ) ) {
			$out[] = $path;
		}
	}
	return $out;
}

// ── 1. Enumerate routes ─────────────────────────────────────────────────────
$php_files = wcb_rc_files( $root, array( 'php' ) );
$routes    = array(); // path => registering file.

foreach ( $php_files as $file ) {
	$src = (string) file_get_contents( $file );
	if ( false === strpos( $src, 'register_rest_route' ) ) {
		continue;
	}
	// register_rest_route( <ns>, '<path>', ... ) — capture the quoted path arg.
	if ( preg_match_all( '/register_rest_route\s*\(\s*[^,]+,\s*([\'"])(.+?)\1/s', $src, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $hit ) {
			$path = $hit[2];
			if ( '' !== $path && ! isset( $routes[ $path ] ) ) {
				$routes[ $path ] = $file;
			}
		}
	}
}

if ( empty( $routes ) ) {
	fwrite( STDOUT, "check-route-callers: no register_rest_route calls found — nothing to gate.\n" );
	exit( 0 );
}

// ── Allowlist ────────────────────────────────────────────────────────────────
$allow_file = $root . '/bin/route-callers-allowlist.txt';
$allow      = array();
if ( is_readable( $allow_file ) ) {
	foreach ( file( $allow_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
		$line = trim( $line );
		if ( '' === $line || str_starts_with( $line, '#' ) ) {
			continue;
		}
		$allow[ $line ] = true;
	}
}

// ── 2. Caller surfaces ───────────────────────────────────────────────────────
// Every .js anywhere in the plugin (assets/, blocks/, modules/, integrations/)
// — features ship view/admin scripts outside assets/, and any of them may be
// the caller. vendor/node_modules/dist/tests are already excluded.
$js_files  = wcb_rc_files( $root, array( 'js' ) );
$js_blob   = '';
foreach ( $js_files as $f ) {
	$js_blob .= file_get_contents( $f ) . "\n";
}

/**
 * Derive the needle for a route path: the last LITERAL (non-regex) segment,
 * bare (no slash). '' when the path has no literal segment.
 *
 * @param string $path Route path.
 * @return string
 */
function wcb_rc_needle( string $path ): string {
	$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
	$literals = array();
	foreach ( $segments as $seg ) {
		if ( str_contains( $seg, '(?P<' ) || str_contains( $seg, '(' ) ) {
			continue;
		}
		$literals[] = $seg;
	}
	return empty( $literals ) ? '' : (string) end( $literals );
}

/**
 * Does $haystack reference route segment $seg in a URL-token context —
 * "/seg" (path concat), "'seg'" or "\"seg\"" (a quoted path/action token, e.g.
 * `restUrl + 'activate-license'`)? Deliberately NOT a bare-word match, so a
 * "Stages" column label never counts as a caller of "/…/stages".
 *
 * @param string $haystack Concatenated caller source.
 * @param string $seg      Bare route segment.
 * @return bool
 */
function wcb_rc_referenced( string $haystack, string $seg ): bool {
	if ( '' === $seg ) {
		return false;
	}
	return str_contains( $haystack, '/' . $seg )
		|| str_contains( $haystack, "'" . $seg . "'" )
		|| str_contains( $haystack, '"' . $seg . '"' );
}

$orphans = array();

foreach ( $routes as $path => $reg_file ) {
	if ( isset( $allow[ $path ] ) ) {
		continue;
	}
	$needle = wcb_rc_needle( $path );
	if ( '' === $needle ) {
		// Path is entirely dynamic (no literal segment) — cannot statically
		// verify; treat as allowlisted-by-shape rather than fail noisily.
		continue;
	}

	// JS caller?
	$called = wcb_rc_referenced( $js_blob, $needle );

	// PHP caller (rest_url()/apiFetch/rest_do_request link) in any file but the
	// one that registers the route.
	if ( ! $called ) {
		foreach ( $php_files as $file ) {
			if ( $file === $reg_file ) {
				continue;
			}
			$src = (string) file_get_contents( $file );
			// A PHP file is a caller surface if it builds a REST URL (rest_url /
			// rest_do_request) OR embeds an inline <script> that fetches one
			// (apiFetch / fetch( / a localized ...restUrl base — the WCB admin
			// screens ship inline-script fetch callers).
			if ( ! preg_match( '/rest_url\s*\(|rest_do_request|apiFetch|fetch\s*\(|restUrl/', $src ) ) {
				continue;
			}
			if ( wcb_rc_referenced( $src, $needle ) ) {
				$called = true;
				break;
			}
		}
	}

	if ( ! $called ) {
		$orphans[ $path ] = str_replace( $root . '/', '', $reg_file );
	}
}

// ── 3. Report ────────────────────────────────────────────────────────────────
if ( empty( $orphans ) ) {
	$n = count( $routes );
	fwrite( STDOUT, "check-route-callers OK: all {$n} registered REST route(s) are reachable (or allowlisted).\n" );
	exit( 0 );
}

fwrite( STDERR, "check-route-callers FAILED — orphaned REST route(s) with no caller:\n" );
foreach ( $orphans as $path => $file ) {
	fwrite( STDERR, "  - {$path}   (registered in {$file})\n" );
}
fwrite( STDERR, "\nEither wire the route to a UI/JS caller, or — if it is legitimately server-only\n" );
fwrite( STDERR, "(inbound webhook, cross-plugin dispatch) — add its path to bin/route-callers-allowlist.txt with a reason.\n" );
exit( 1 );
