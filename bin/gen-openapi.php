<?php
/**
 * Generate an OpenAPI 3.1 spec from the LIVE route registrations, scoped to the
 * routes THIS plugin owns.
 *
 * Cloned from the Learnomy reference (the logic is generic; everything
 * plugin-specific lives in docs/api/openapi.config.json). Route scoping is
 * shared with tests/audit/rest-reachability.php through bin/rest-scope.php:
 * namespace allowlist from the config, then per-handler ownership by callback
 * file, because WP Career Board Free and Pro share the wcb/v1 namespace. The
 * output never contains another plugin's routes, and the same commit produces
 * the same spec whether or not the sibling plugin is active.
 *
 * RUN:   wp eval-file bin/gen-openapi.php   (from the plugin directory)
 * OUT:   docs/api/openapi.json   (+ merges docs/api/openapi.overlay.json if present)
 * GATE:  wp eval-file bin/gen-openapi.php && git diff --exit-code docs/api/openapi.json
 *
 * @package WP_Career_Board
 */

namespace WCB\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through wp eval-file (WordPress must be loaded).\n" );
	exit( 1 );
}

$plugin_dir         = dirname( __DIR__ );
$api_dir            = $plugin_dir . '/docs/api';
list( $config, $owned ) = ( require __DIR__ . '/rest-scope.php' )( $plugin_dir );

/**
 * Resolve info.version from the plugin itself, never from a hand-kept literal.
 *
 * Order (Eventonomy's, the portfolio reference): the main-file Version header,
 * which is what WordPress itself reads for update checks; then the plugin's
 * *_VERSION constant named in config `version_constant`, for a checkout whose
 * directory is not named after the plugin; then a config `version` only if a
 * spec ever needs pinning. The freshness gate cannot catch a stale stamp (the
 * regenerate and the committed copy read the same source), so the literal is
 * the last resort, not the first.
 */
$resolve_version = static function () use ( $plugin_dir, $config ): string {
	$main_file = $plugin_dir . '/' . basename( $plugin_dir ) . '.php';
	if ( is_readable( $main_file ) ) {
		$headers = get_file_data( $main_file, array( 'version' => 'Version' ), 'plugin' );
		if ( '' !== trim( (string) $headers['version'] ) ) {
			return trim( (string) $headers['version'] );
		}
	}
	$constant = (string) ( $config['version_constant'] ?? '' );
	if ( '' !== $constant && defined( $constant ) ) {
		return (string) constant( $constant );
	}
	if ( ! empty( $config['version'] ) ) {
		return (string) $config['version'];
	}
	fwrite( STDERR, "Cannot resolve the spec version: no Version header at {$main_file}, no version_constant, no config version.\n" );
	exit( 1 );
};

/**
 * Turn a WP route regex into an OpenAPI path template + its path-parameter names.
 * `/wcb/v1/jobs/(?P<id>[\d]+)` -> ['/wcb/v1/jobs/{id}', ['id']]
 */
$to_openapi_path = static function ( string $route ): array {
	$params = array();
	$path   = preg_replace_callback(
		'/\(\?P<([a-zA-Z0-9_]+)>[^)]*\)/',
		static function ( $m ) use ( &$params ) {
			$params[] = $m[1];
			return '{' . $m[1] . '}';
		},
		$route
	);
	// Drop any remaining bare regex groups (optional trailing slashes etc.).
	$path = preg_replace( '/\(\?[^)]*\)\??/', '', (string) $path );
	return array( rtrim( (string) $path, '/' ) ?: '/', $params );
};

/** Map a WP arg definition to an OpenAPI schema object. */
$arg_to_schema = static function ( array $arg ) use ( &$arg_to_schema ): array {
	$schema = array();
	$type   = $arg['type'] ?? null;
	if ( is_array( $type ) ) {
		$type = $type[0] ?? 'string';
	}
	if ( $type ) {
		$schema['type'] = $type;
	}
	foreach ( array( 'format', 'minimum', 'maximum', 'minLength', 'maxLength', 'pattern' ) as $k ) {
		if ( isset( $arg[ $k ] ) ) {
			$schema[ $k ] = $arg[ $k ];
		}
	}
	if ( isset( $arg['enum'] ) && is_array( $arg['enum'] ) ) {
		$schema['enum'] = array_values( $arg['enum'] );
	}
	if ( array_key_exists( 'default', $arg ) ) {
		$schema['default'] = $arg['default'];
	}
	if ( 'array' === ( $schema['type'] ?? '' ) ) {
		$schema['items'] = isset( $arg['items'] ) && is_array( $arg['items'] )
			? $arg_to_schema( $arg['items'] )
			: array( 'type' => 'string' );
	}
	if ( 'object' === ( $schema['type'] ?? '' ) && isset( $arg['properties'] ) && is_array( $arg['properties'] ) ) {
		$schema['properties'] = array();
		foreach ( $arg['properties'] as $pname => $pdef ) {
			$schema['properties'][ $pname ] = is_array( $pdef ) ? $arg_to_schema( $pdef ) : array();
		}
	}
	return $schema ?: array( 'type' => 'string' );
};

/** First path segment after the namespace, mapped to a display tag. */
$tag_for = static function ( string $ns, string $route ) use ( $config ): string {
	$rest = trim( substr( $route, strlen( '/' . $ns ) ), '/' );
	$seg  = strtok( $rest, '/' );
	$seg  = $seg ? preg_replace( '/[^a-z0-9-].*$/', '', strtolower( $seg ) ) : 'misc';
	return $config['tags'][ $seg ] ?? ( ucfirst( $seg ) ?: 'General' );
};

$paths  = array();
$tagset = array();
$counts = array(
	'routes'     => 0,
	'operations' => 0,
);

foreach ( $owned as $route => $handlers ) {
	$ns = '';
	foreach ( $config['namespaces'] as $candidate ) {
		if ( str_starts_with( $route, '/' . $candidate . '/' ) ) {
			$ns = $candidate;
		}
	}

	list( $oapi_path, $path_params ) = $to_openapi_path( $route );
	$tag                             = $tag_for( $ns, $route );
	$tagset[ $tag ]                  = true;
	++$counts['routes'];

	foreach ( (array) $handlers as $handler ) {
		$methods = array();
		foreach ( (array) ( $handler['methods'] ?? array() ) as $m => $on ) {
			if ( $on ) {
				$methods[] = strtoupper( (string) $m );
			}
		}
		$args = is_array( $handler['args'] ?? null ) ? $handler['args'] : array();

		// Public when the permission callback is the literal __return_true;
		// anything else (a real gate or a closure we can't introspect) is
		// documented as requiring auth — a safe over-statement the overlay
		// can relax per route.
		$is_public = ( '__return_true' === ( $handler['permission_callback'] ?? null ) );

		foreach ( $methods as $method ) {
			if ( in_array( $method, array( 'OPTIONS', 'HEAD' ), true ) ) {
				continue;
			}
			$verb = strtolower( $method );

			// No auto "METHOD /path" summary — it just duplicates the path Swagger
			// already shows, in low-contrast grey. Real summaries come from the
			// overlay for the endpoints that have one.
			$op = array(
				'tags'        => array( $tag ),
				'operationId' => $verb . '_' . trim( preg_replace( '/[^a-zA-Z0-9]+/', '_', $oapi_path ), '_' ),
				'security'    => $is_public ? array() : ( $config['default_security'] ?? array() ),
				'parameters'  => array(),
				'responses'   => array(
					'200' => array( 'description' => 'Success' ),
					'401' => array( 'description' => 'Authentication required or invalid.' ),
					'403' => array( 'description' => 'Not permitted.' ),
					'404' => array( 'description' => 'Not found.' ),
				),
			);

			// Path parameters.
			foreach ( $path_params as $pname ) {
				$pschema            = isset( $args[ $pname ] ) ? $arg_to_schema( $args[ $pname ] ) : array( 'type' => 'string' );
				$op['parameters'][] = array(
					'name'        => $pname,
					'in'          => 'path',
					'required'    => true,
					'schema'      => $pschema,
					'description' => $args[ $pname ]['description'] ?? '',
				);
			}

			$body_args = array();
			foreach ( $args as $aname => $adef ) {
				if ( in_array( $aname, $path_params, true ) || ! is_array( $adef ) ) {
					continue;
				}
				if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
					$body_args[ $aname ] = $adef;
				} else {
					$op['parameters'][] = array(
						'name'        => $aname,
						'in'          => 'query',
						'required'    => ! empty( $adef['required'] ),
						'schema'      => $arg_to_schema( $adef ),
						'description' => $adef['description'] ?? '',
					);
				}
			}

			if ( $body_args ) {
				$props    = array();
				$required = array();
				foreach ( $body_args as $aname => $adef ) {
					$props[ $aname ] = $arg_to_schema( $adef );
					if ( ! empty( $adef['description'] ) ) {
						$props[ $aname ]['description'] = $adef['description'];
					}
					if ( ! empty( $adef['required'] ) ) {
						$required[] = $aname;
					}
				}
				$schema = array(
					'type'       => 'object',
					'properties' => $props,
				);
				if ( $required ) {
					$schema['required'] = $required;
				}
				$op['requestBody'] = array(
					'required' => (bool) $required,
					'content'  => array( 'application/json' => array( 'schema' => $schema ) ),
				);
			}

			if ( empty( $op['parameters'] ) ) {
				unset( $op['parameters'] );
			}

			$paths[ $oapi_path ][ $verb ] = $op;
			++$counts['operations'];
		}
	}
}

ksort( $paths );
$tags = array();
foreach ( array_keys( $tagset ) as $t ) {
	$tags[] = array( 'name' => $t );
}
sort( $tags );

$spec = array(
	'openapi'    => '3.1.0',
	'info'       => array_filter(
		array(
			'title'       => $config['title'] ?? 'REST API',
			'version'     => $resolve_version(),
			'description' => $config['description'] ?? '',
			'contact'     => $config['contact'] ?? null,
			'license'     => $config['license'] ?? null,
		)
	),
	'servers'    => $config['servers'] ?? array(),
	'tags'       => $tags,
	'security'   => $config['default_security'] ?? array(),
	'components' => array( 'securitySchemes' => $config['security_schemes'] ?? new \stdClass() ),
	'paths'      => $paths,
);

// Merge an optional hand-authored overlay (response bodies, richer descriptions,
// public-route corrections) deeply on top of the generated spec.
$overlay_file = $api_dir . '/openapi.overlay.json';
if ( is_readable( $overlay_file ) ) {
	$overlay = json_decode( (string) file_get_contents( $overlay_file ), true );
	if ( is_array( $overlay ) ) {
		$deep_merge = static function ( array $base, array $over ) use ( &$deep_merge ): array {
			foreach ( $over as $k => $v ) {
				$base[ $k ] = ( is_array( $v ) && isset( $base[ $k ] ) && is_array( $base[ $k ] ) )
					? $deep_merge( $base[ $k ], $v )
					: $v;
			}
			return $base;
		};
		$spec       = $deep_merge( $spec, $overlay );
	}
}

// Re-sort AFTER the overlay merge: a deep merge appends overlay-only paths at the
// end, so the file order would otherwise depend on the generating machine.
ksort( $spec['paths'] );

// Re-derive tags AFTER the overlay merge (union of declared + used, sorted), so an
// overlay-injected operation never carries an undeclared tag.
$used_tags = array();
foreach ( $spec['paths'] as $ops ) {
	foreach ( $ops as $op ) {
		if ( is_array( $op ) && ! empty( $op['tags'] ) ) {
			foreach ( (array) $op['tags'] as $t ) {
				$used_tags[ (string) $t ] = true;
			}
		}
	}
}
foreach ( (array) ( $spec['tags'] ?? array() ) as $t ) {
	if ( isset( $t['name'] ) ) {
		$used_tags[ (string) $t['name'] ] = true;
	}
}
$names = array_keys( $used_tags );
sort( $names );
$spec['tags'] = array_map( static fn( $n ) => array( 'name' => $n ), $names );

/*
 * Refuse to write a spec generated on an under-equipped machine: any route in
 * config `required_routes` (one that registers only when an optional plugin or
 * module is active) must be present, or the generator fails loudly instead of
 * writing a silently truncated catalogue that the freshness gate cannot catch.
 */
$required = (array) ( $config['required_routes'] ?? array() );
$missing  = array_values( array_diff( $required, array_keys( $spec['paths'] ) ) );

if ( $missing ) {
	fwrite(
		STDERR,
		"Refusing to write an incomplete catalogue.\n\n"
		. "These routes are declared in openapi.config.json required_routes but did not\n"
		. "register in this process:\n"
	);
	foreach ( $missing as $route ) {
		fwrite( STDERR, "  - {$route}\n" );
	}
	fwrite(
		STDERR,
		"\nThey almost certainly belong to an extension gated on an optional plugin that\n"
		. "is not active here. Activate it and re-run: generating without it would publish\n"
		. "a spec that silently drops routes the plugin really ships.\n"
	);
	exit( 1 );
}

if ( ! is_dir( $api_dir ) ) {
	mkdir( $api_dir, 0755, true );
}
file_put_contents(
	$api_dir . '/openapi.json',
	wp_json_encode( $spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

fwrite(
	STDOUT,
	sprintf(
		"OpenAPI written: docs/api/openapi.json\n  namespaces: %s\n  routes: %d  operations: %d  tags: %d\n",
		implode( ', ', $config['namespaces'] ),
		$counts['routes'],
		$counts['operations'],
		count( $spec['tags'] )
	)
);
