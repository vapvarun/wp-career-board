<?php
/**
 * REST reachability + permission smoke over EVERY route this plugin owns.
 *
 * Cloned from the Learnomy reference. The catalogue (docs/api/openapi.json) is
 * generated from these same routes (scoping shared via bin/rest-scope.php), so
 * this is the coverage counterpart: instead of documenting the surface, it
 * exercises it. For every owned route it asserts
 *
 *   1. the handler carries a permission_callback (a missing one defaults a route
 *      to public), and
 *   2. every GET responds WITHOUT a 5xx / fatal when dispatched (a concrete
 *      value is substituted for each path param). 4xx is fine - a 400/401/403/
 *      404 means the endpoint answered; only a 500 (or a thrown error) is a bug.
 *
 * Mutating methods (POST/PUT/PATCH/DELETE) are permission-checked but NOT
 * dispatched, so the smoke never writes or deletes data.
 *
 * RUN:   wp eval-file tests/audit/rest-reachability.php   (from the plugin directory;
 *        on a combo install so shared-namespace ownership is exercised)
 * EXIT:  non-zero if any route 5xxs / throws / lacks a permission_callback.
 *
 * @package WP_Career_Board
 */

namespace WCB\Tests\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

list( , $owned ) = ( require dirname( __DIR__, 2 ) . '/bin/rest-scope.php' )( dirname( __DIR__, 2 ) );

// Act as an administrator so admin-gated GETs are reachable (we are testing that
// they RESPOND cleanly, not that they reject - rejection is a 4xx, which passes).
wp_set_current_user( 1 );

$checked_routes = 0;
$get_ok         = 0;
$get_failed     = 0;
$no_permission  = 0;
$skipped_mutate = 0;
$skipped_stream = 0;
$failures       = array();

// GETs that stream a file and exit() (CSV export, private-file download, resume
// PDF). They can't be dispatched in-process without halting the run, so they are
// permission-checked but not fetched. Add a pattern for a new streaming endpoint.
$no_dispatch = '#(/export$|/files/|/pdf$|\.csv$)#i';

/** Build a concrete path by substituting a value that matches each param regex. */
$concrete_path = static function ( string $route ): string {
	return (string) preg_replace_callback(
		'/\(\?P<([a-z0-9_]+)>([^)]*)\)/i',
		static function ( $m ) {
			$name = $m[1];
			$re   = $m[2];
			if ( false !== strpos( $re, '{36}' ) || false !== stripos( $name, 'uuid' ) ) {
				return '00000000-0000-0000-0000-000000000000';
			}
			if ( false !== strpos( $re, '\\d' ) || false !== strpos( $re, '[0-9' ) || false !== strpos( $re, '\\\\d' ) ) {
				return '1';
			}
			if ( 0 === strpos( $re, '[a-z]' ) ) {
				return 'stripe';
			}
			return 'test';
		},
		$route
	);
};

echo "REST reachability + permission smoke\n====================================\n";

foreach ( $owned as $route => $handlers ) {
	++$checked_routes;

	// (1) permission contract — every handler must carry a permission_callback.
	$serves_get = false;
	foreach ( (array) $handlers as $h ) {
		if ( ! array_key_exists( 'permission_callback', $h ) || null === $h['permission_callback'] ) {
			++$no_permission;
			$failures[] = "NO permission_callback: {$route}";
		}
		foreach ( (array) ( $h['methods'] ?? array() ) as $m => $on ) {
			if ( $on && 'GET' === strtoupper( (string) $m ) ) {
				$serves_get = true;
			}
		}
	}

	// (2) reachability — dispatch a GET (read-only) and require no 5xx.
	if ( ! $serves_get ) {
		++$skipped_mutate;
		continue;
	}
	if ( preg_match( $no_dispatch, $route ) ) {
		++$skipped_stream; // streaming/terminating GET — permission-checked above, not fetched
		continue;
	}

	$path = $concrete_path( $route );
	try {
		$response = rest_do_request( new \WP_REST_Request( 'GET', $path ) );
		$status   = (int) $response->get_status();
		if ( $status >= 500 ) {
			++$get_failed;
			$failures[] = "GET {$path} -> {$status}";
		} else {
			++$get_ok;
		}
	} catch ( \Throwable $e ) {
		++$get_failed;
		$failures[] = "GET {$path} -> THROWN: " . $e->getMessage();
	}
}

echo "  routes checked:            {$checked_routes}\n";
echo "  GET endpoints reachable:   {$get_ok}\n";
echo "  GET endpoints 5xx/threw:   {$get_failed}\n";
echo "  routes w/o permission cb:  {$no_permission}\n";
echo "  mutation-only (perm-only): {$skipped_mutate}\n";
echo "  streaming GET (perm-only): {$skipped_stream}\n";

if ( $failures ) {
	echo "\nFAILURES:\n";
	foreach ( $failures as $f ) {
		echo "  FAIL  {$f}\n";
	}
}

echo "====================================\n";
$fail_total = $get_failed + $no_permission;
echo 0 === $fail_total ? "OK - every route is permission-gated and every GET responds without a 5xx.\n" : "FAILED - {$fail_total} problem(s) above.\n";
exit( $fail_total > 0 ? 1 : 0 );
