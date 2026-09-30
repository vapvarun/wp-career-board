<?php
/**
 * Shared route scoping for bin/gen-openapi.php and tests/audit/rest-reachability.php.
 *
 * Returns a closure that yields the routes THIS plugin owns, as
 * `route => handlers[]`, limited to the namespaces in docs/api/openapi.config.json.
 *
 * Why ownership, not just namespace: Free and Pro share `wcb/v1`. A namespace-only
 * filter would put Pro's routes into Free's catalogue on a combo install (and
 * drop them on a Free-only one, so the committed spec would depend on which
 * plugins the generating machine had active). Each handler is attributed to the
 * file that defines its callback; only handlers whose callback lives under this
 * plugin's directory are kept. A route key both plugins extend is split per
 * handler, so each spec documents only its own methods.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

return static function ( string $plugin_dir ): array {
	$config = json_decode( (string) file_get_contents( $plugin_dir . '/docs/api/openapi.config.json' ), true );
	if ( ! is_array( $config ) || empty( $config['namespaces'] ) ) {
		fwrite( STDERR, "Missing or invalid docs/api/openapi.config.json (need at least namespaces[]).\n" );
		exit( 1 );
	}

	$root = trailingslashit( wp_normalize_path( (string) realpath( $plugin_dir ) ) );

	$callback_file = static function ( $callback ): string {
		try {
			if ( is_string( $callback ) && str_contains( $callback, '::' ) ) {
				$callback = explode( '::', $callback, 2 );
			}
			$ref = is_array( $callback )
				? new \ReflectionMethod( $callback[0], (string) $callback[1] )
				: new \ReflectionFunction( \Closure::fromCallable( $callback ) );
			return wp_normalize_path( (string) $ref->getFileName() );
		} catch ( \Throwable $e ) {
			return '';
		}
	};

	$server = rest_get_server();
	$owned  = array();

	foreach ( $config['namespaces'] as $ns ) {
		$routes = $server->get_routes( $ns );
		if ( empty( $routes ) ) {
			fwrite( STDERR, "  (namespace {$ns} has no routes on this install - is the plugin active?)\n" );
			continue;
		}
		foreach ( $routes as $route => $handlers ) {
			if ( rtrim( $route, '/' ) === '/' . $ns ) {
				continue; // The namespace index route itself.
			}
			foreach ( (array) $handlers as $handler ) {
				if ( str_starts_with( $callback_file( $handler['callback'] ?? null ), $root ) ) {
					$owned[ $route ][] = $handler;
				}
			}
		}
	}

	return array( $config, $owned );
};
