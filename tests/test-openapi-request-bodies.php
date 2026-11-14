<?php
/**
 * Write routes document their request bodies in the OpenAPI file (1.8.4).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-openapi-request-bodies.php
 *
 * Pins: every listed write route has a requestBody with the fields its handler
 * reads, and the documentation adds no runtime validation to the route.
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

$spec = json_decode( (string) file_get_contents( WP_PLUGIN_DIR . '/wp-career-board/docs/api/openapi.json' ), true );

$expect = array(
	'/wcb/v1/applications/{id}/notes'  => array( 'post', array( 'text' ) ),
	'/wcb/v1/applications/{id}/status' => array( 'put', array( 'status', 'note' ) ),
	'/wcb/v1/candidates/{id}'          => array( 'patch', array( 'bio', 'profile_visibility', 'resume', 'resume_data', 'custom_fields' ) ),
	'/wcb/v1/employers'                => array( 'post', array( 'name', 'description', 'hq', 'make_main' ) ),
	'/wcb/v1/employers/{id}'           => array( 'put', array( 'name', 'description', 'hq', 'custom_fields' ) ),
	'/wcb/v1/jobs/{id}/apply'          => array( 'post', array( 'cover_letter', 'resume_id', 'resume_attachment_id', 'guest_name', 'guest_email', 'custom_fields' ) ),
	'/wcb/v1/jobs'                     => array( 'post', array( 'title', 'description', 'remote', 'location_custom', 'locations', 'job_types', 'experience', 'tags', 'custom_fields', 'apply_url' ) ),
	'/wcb/v1/jobs/{id}'                => array( 'put', array( 'title', 'description', 'remote', 'locations', 'apply_url' ) ),
);
foreach ( $expect as $path => list( $verb, $fields ) ) {
	$props   = $spec['paths'][ $path ][ $verb ]['requestBody']['content']['application/json']['schema']['properties'] ?? array();
	$missing = array_diff( $fields, array_keys( $props ) );
	$ok( ! $missing, "$verb $path documents " . implode( ', ', $fields ) . ( $missing ? ' (missing ' . implode( ', ', $missing ) . ')' : '' ) );
}

// Documentation only: the route itself must not gain typed args.
$routes = rest_get_server()->get_routes( 'wcb/v1' );
foreach ( $routes['/wcb/v1/applications/(?P<id>\d+)/status'] as $handler ) {
	$typed = array_filter( (array) ( $handler['args'] ?? array() ), static fn( $a ) => isset( $a['type'] ) && 'id' !== ( $a['name'] ?? '' ) );
	$ok( ! array_diff( array_keys( $typed ), array( 'id' ) ), 'status route gained no typed args' );
}

WP_CLI::log( '  Total: ' . ( $pass + $fail ) . "  Pass: {$pass}  Fail: {$fail}" );
if ( $fail ) {
	WP_CLI::error( "{$fail} test(s) failed." );
}
WP_CLI::success( 'All request-body doc tests passed.' );
