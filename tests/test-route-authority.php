<?php
/**
 * Route authority: every Free REST route declares who may reach it, and the
 * declaration is enforced against the live registry and by dispatch.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-route-authority.php
 *
 * Standard: ~/.claude/skills/wp-plugin-release/references/authorization-guards.md
 * (release Gate G2). The manifest is audit/route-authority.json; it is the
 * expected value of the assertions below, not documentation.
 *
 *   1. A live route missing from the manifest fails.
 *   2. A manifest entry with no live route fails (unless `conditional_on` names
 *      a setting that is off right now).
 *   3. The resolved permission_callback must be the declared one, and a
 *      `__return_true` route must be declared public with a reason taken from
 *      an observed dispatch.
 *   4. Every public GET route that takes an object id is dispatched signed out
 *      against a private fixture and must not return it.
 *
 * Then a runtime matrix: owner vs another member of the same role vs signed
 * out, for reads, writes and listing totals, with schema-valid payloads (a 400
 * proves nothing, so it fails the matrix). Test data is prefixed "sec18" and
 * removed at the end.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$GLOBALS['wcb_test_pass'] = 0;
$GLOBALS['wcb_test_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_test_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_test_fail'];
		WP_CLI::warning( "  FAIL: {$label}" );
	}
}

/**
 * Name a permission callback the way the manifest does.
 *
 * Wrappers that keep the real check in a static `$inner` (Pro's pro_check())
 * are unwrapped; other closures are named by the file they live in.
 *
 * @param mixed  $cb   Callback.
 * @param string $root Normalised plugin root with trailing slash.
 * @return array{0:string,1:string} Name and defining file.
 */
function wcb_ra_resolve( $cb, string $root ): array {
	if ( $cb instanceof Closure ) {
		$ref  = new ReflectionFunction( $cb );
		$vars = $ref->getStaticVariables();
		if ( isset( $vars['inner'] ) && is_callable( $vars['inner'] ) ) {
			return wcb_ra_resolve( $vars['inner'], $root );
		}
		$file = wp_normalize_path( (string) realpath( (string) $ref->getFileName() ) );
		return array( 'Closure@' . str_replace( $root, '', $file ), $file );
	}
	if ( is_array( $cb ) && isset( $cb[0], $cb[1] ) ) {
		$class = is_object( $cb[0] ) ? get_class( $cb[0] ) : (string) $cb[0];
		$file  = wp_normalize_path( (string) realpath( (string) ( new ReflectionMethod( $class, (string) $cb[1] ) )->getFileName() ) );
		return array( $class . '::' . $cb[1], $file );
	}
	return array( is_string( $cb ) ? $cb : 'NONE', '' );
}

/**
 * Live wcb/v1 routes whose callback is defined in this plugin.
 *
 * Ownership is decided by reflection on the callback's file, never by a
 * namespace prefix (Free and Pro share wcb/v1).
 *
 * @param string $root Normalised plugin root with trailing slash.
 * @return array<string,array{route:string,methods:array,permission:string,object_id:bool,writes:bool}>
 */
function wcb_ra_live( string $root ): array {
	$out = array();
	foreach ( rest_get_server()->get_routes() as $route => $handlers ) {
		if ( 0 !== stripos( $route, '/wcb/v1/' ) ) {
			continue;
		}
		foreach ( $handlers as $handler ) {
			list( , $file ) = wcb_ra_resolve( $handler['callback'] ?? null, $root );
			if ( '' === $file || 0 !== strpos( $file, $root ) ) {
				continue;
			}
			list( $perm ) = wcb_ra_resolve( $handler['permission_callback'] ?? null, $root );
			$methods      = array_keys( array_filter( (array) ( $handler['methods'] ?? array() ) ) );
			$out[ implode( ',', $methods ) . ' ' . $route ] = array(
				'route'      => $route,
				'methods'    => $methods,
				'permission' => $perm,
				'object_id'  => (bool) preg_match( '/\(\?P<[a-z_]*id>/', $route ),
				'writes'     => (bool) array_intersect( $methods, array( 'POST', 'PUT', 'PATCH', 'DELETE' ) ),
			);
		}
	}
	return $out;
}

/**
 * Dispatch a REST request as a given user.
 *
 * @param string $method  HTTP method.
 * @param string $path    Route path.
 * @param array  $params  Query (GET) or JSON body params.
 * @param int    $user_id User to act as (0 = signed out).
 * @return WP_REST_Response
 */
function wcb_ra_call( string $method, string $path, array $params, int $user_id ): WP_REST_Response {
	wp_set_current_user( $user_id );
	$request = new WP_REST_Request( $method, $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $params );
	} elseif ( $params ) {
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $params ) );
	}
	return rest_ensure_response( rest_do_request( $request ) );
}

/**
 * Assert an actor's outcome on one route.
 *
 * Allowed means 2xx. Denied means 401/403/404. Anything else (a 400 above
 * all) means the request never reached the authority question.
 *
 * @param string $label   Label.
 * @param bool   $allowed Expected outcome.
 * @param string $method  HTTP method.
 * @param string $path    Route path.
 * @param array  $params  Params.
 * @param int    $user_id Actor.
 * @return WP_REST_Response
 */
function wcb_ra_expect( string $label, bool $allowed, string $method, string $path, array $params, int $user_id ): WP_REST_Response {
	$res    = wcb_ra_call( $method, $path, $params, $user_id );
	$status = $res->get_status();
	$ok     = $allowed ? ( $status >= 200 && $status < 300 ) : in_array( $status, array( 401, 403, 404 ), true );
	wcb_assert( $ok, sprintf( '%s: %s %s -> %d (expected %s)', $label, $method, $path, $status, $allowed ? '2xx' : '401/403/404' ) );
	return $res;
}

/**
 * Ask a route's own permission_callback, without running the route.
 *
 * For routes whose callback streams a file and exits: dispatching them as an
 * allowed actor would end the test run, so their authority is read directly.
 *
 * @param string $label   Label.
 * @param bool   $allowed Expected outcome.
 * @param string $method  HTTP method.
 * @param string $path    Route path.
 * @param int    $user_id Actor.
 * @return void
 */
function wcb_ra_expect_permission( string $label, bool $allowed, string $method, string $path, int $user_id ): void {
	wp_set_current_user( $user_id );
	$request = new WP_REST_Request( $method, $path );
	$handler = null;
	foreach ( rest_get_server()->get_routes() as $route => $handlers ) {
		if ( ! preg_match( '@^' . $route . '$@i', $path, $params ) ) {
			continue;
		}
		foreach ( $handlers as $candidate ) {
			if ( ! empty( $candidate['methods'][ $method ] ) ) {
				$handler = $candidate;
				$request->set_url_params( array_filter( $params, 'is_string', ARRAY_FILTER_USE_KEY ) );
				break 2;
			}
		}
	}
	if ( null === $handler ) {
		wcb_assert( false, "{$label}: {$method} {$path} matched no route, so it proved nothing" );
		return;
	}
	$result = call_user_func( $handler['permission_callback'], $request );
	$got    = true === $result;
	wcb_assert( $got === $allowed, sprintf( '%s: %s %s permission -> %s (expected %s)', $label, $method, $path, $got ? 'allowed' : 'refused', $allowed ? 'allowed' : 'refused' ) );
}

/**
 * The listing total header.
 *
 * @param WP_REST_Response $res Response.
 * @return int -1 when the header is missing.
 */
function wcb_ra_total( WP_REST_Response $res ): int {
	$headers = $res->get_headers();
	return isset( $headers['X-WCB-Total'] ) ? (int) $headers['X-WCB-Total'] : -1;
}

rest_get_server();

WP_CLI::log( '' );
WP_CLI::log( '========================================' );
WP_CLI::log( '  Route Authority Tests (Free)' );
WP_CLI::log( '========================================' );

$wcb_root     = trailingslashit( wp_normalize_path( (string) realpath( dirname( __DIR__ ) ) ) );
$wcb_manifest = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/audit/route-authority.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
$wcb_declared = (array) ( $wcb_manifest['routes'] ?? array() );
$wcb_live     = wcb_ra_live( $wcb_root );

// --- Tripwires: a guard that walks an empty set passes vacuously. -----------
WP_CLI::log( '--- tripwires ---' );
wcb_assert( count( $wcb_live ) >= 50, 'the live registry returned this plugin\'s routes (' . count( $wcb_live ) . ')' );
wcb_assert( count( $wcb_declared ) >= 50, 'the manifest loaded (' . count( $wcb_declared ) . ' routes)' );

// --- Rule 1: every live route is declared. ---------------------------------
WP_CLI::log( '--- rule 1: no undeclared route ---' );
$wcb_undeclared = array_keys( array_diff_key( $wcb_live, $wcb_declared ) );
wcb_assert( array() === $wcb_undeclared, 'every live route is declared in audit/route-authority.json' . ( $wcb_undeclared ? ': ' . implode( ', ', $wcb_undeclared ) : '' ) );

// --- Rule 2: no stale entry. ------------------------------------------------
WP_CLI::log( '--- rule 2: no stale manifest entry ---' );
$wcb_stale = array();
foreach ( array_diff_key( $wcb_declared, $wcb_live ) as $wcb_key => $wcb_entry ) {
	$wcb_toggle = (string) ( $wcb_entry['conditional_on'] ?? '' );
	if ( '' !== $wcb_toggle && ! \WCB\Admin\Settings::bool( $wcb_toggle, true ) ) {
		continue; // Absent because its feature is off right now.
	}
	$wcb_stale[] = $wcb_key;
}
wcb_assert( array() === $wcb_stale, 'every manifest entry is a live route' . ( $wcb_stale ? ': ' . implode( ', ', $wcb_stale ) : '' ) );

// --- Rule 3: declared authority is the one in the code. --------------------
WP_CLI::log( '--- rule 3: declared authority matches, public routes say why ---' );
$wcb_audiences = array( 'public', 'member', 'owner', 'admin', 'guarded' );
$wcb_problems  = array();
foreach ( $wcb_live as $wcb_key => $wcb_route ) {
	$wcb_entry = $wcb_declared[ $wcb_key ] ?? null;
	if ( null === $wcb_entry ) {
		continue; // Rule 1 reports it.
	}
	$wcb_aud = (string) ( $wcb_entry['audience'] ?? '' );
	if ( ! in_array( $wcb_aud, $wcb_audiences, true ) ) {
		$wcb_problems[] = "{$wcb_key}: unknown audience '{$wcb_aud}'";
	}
	if ( ( $wcb_entry['permission_callback'] ?? '' ) !== $wcb_route['permission'] ) {
		$wcb_problems[] = "{$wcb_key}: code has {$wcb_route['permission']}, manifest says " . ( $wcb_entry['permission_callback'] ?? '?' );
	}
	if ( (bool) ( $wcb_entry['object_id'] ?? false ) !== $wcb_route['object_id'] || (bool) ( $wcb_entry['writes'] ?? false ) !== $wcb_route['writes'] ) {
		$wcb_problems[] = "{$wcb_key}: object_id/writes flags do not match the route";
	}
	if ( '__return_true' === $wcb_route['permission'] && 'public' !== $wcb_aud ) {
		$wcb_problems[] = "{$wcb_key}: __return_true but declared '{$wcb_aud}'";
	}
	if ( 'public' === $wcb_aud ) {
		$wcb_reason   = trim( (string) ( $wcb_entry['reason'] ?? '' ) );
		$wcb_observed = trim( (string) ( $wcb_entry['observed'] ?? '' ) );
		if ( '' === $wcb_reason || 0 === stripos( $wcb_reason, 'TODO' ) || '' === $wcb_observed ) {
			$wcb_problems[] = "{$wcb_key}: public without a reason and an observed signed-out dispatch";
		}
	}
}
wcb_assert( array() === $wcb_problems, 'every route\'s declared authority matches the code' . ( $wcb_problems ? ":\n    " . implode( "\n    ", $wcb_problems ) : '' ) );

// --- Fixtures. ---------------------------------------------------------------
$wcb_users = array();
foreach ( array( 'emp-a' => 'wcb_employer', 'emp-b' => 'wcb_employer', 'cand-a' => 'wcb_candidate', 'cand-b' => 'wcb_candidate', 'cand-private' => 'wcb_candidate' ) as $wcb_slug => $wcb_role ) {
	$wcb_login = 'sec18-ra-' . $wcb_slug;
	$wcb_old   = get_user_by( 'login', $wcb_login );
	if ( $wcb_old ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $wcb_old->ID );
	}
	$wcb_users[ $wcb_slug ] = (int) wp_insert_user(
		array(
			'user_login'   => $wcb_login,
			'user_pass'    => wp_generate_password(),
			'user_email'   => $wcb_login . '@example.test',
			'display_name' => 'cand-private' === $wcb_slug ? 'SEC18-SECRET-CANDIDATE' : $wcb_login,
			'role'         => $wcb_role,
		)
	);
}
update_user_meta( $wcb_users['cand-private'], '_wcb_profile_visibility', 'private' );

$wcb_post = static function ( string $type, string $status, string $title, int $author, array $meta = array() ): int {
	$id = (int) wp_insert_post(
		array(
			'post_type'   => $type,
			'post_status' => $status,
			'post_title'  => $title,
			'post_author' => $author,
		)
	);
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	return $id;
};

$wcb_emp_a      = $wcb_users['emp-a'];
$wcb_emp_b      = $wcb_users['emp-b'];
$wcb_cand_a     = $wcb_users['cand-a'];
$wcb_cand_b     = $wcb_users['cand-b'];
$wcb_co_a       = $wcb_post( 'wcb_company', 'publish', 'sec18 Company A', $wcb_emp_a );
$wcb_co_draft   = $wcb_post( 'wcb_company', 'draft', 'SEC18-SECRET-COMPANY', $wcb_emp_a );
$wcb_marker     = 'sec18ramarker' . wp_generate_password( 6, false, false );
$wcb_job_a      = $wcb_post( 'wcb_job', 'publish', "sec18 {$wcb_marker} live", $wcb_emp_a, array( '_wcb_company_id' => $wcb_co_a ) );
$wcb_job_secret = array();
foreach ( array( 'pending', 'draft', 'private' ) as $wcb_status ) {
	$wcb_job_secret[ $wcb_status ] = $wcb_post( 'wcb_job', $wcb_status, "SEC18-SECRET-JOB {$wcb_marker} {$wcb_status}", $wcb_emp_a, array( '_wcb_company_id' => $wcb_co_a ) );
}
$wcb_job_draftco = $wcb_post( 'wcb_job', 'pending', "SEC18-SECRET-JOB {$wcb_marker} draftco", $wcb_emp_a, array( '_wcb_company_id' => $wcb_co_draft ) );
update_user_meta( $wcb_emp_a, '_wcb_company_id', $wcb_co_a );
$wcb_app_a     = $wcb_post( 'wcb_application', 'publish', 'sec18 app a', $wcb_cand_a, array( '_wcb_job_id' => $wcb_job_a, '_wcb_candidate_id' => $wcb_cand_a, '_wcb_status' => 'submitted' ) );
$wcb_app_guest = $wcb_post( 'wcb_application', 'publish', 'sec18 guest app', 0, array( '_wcb_job_id' => $wcb_job_a, '_wcb_candidate_id' => 0, '_wcb_status' => 'submitted', '_wcb_guest_email' => 'sec18-guest@example.test' ) );

// --- Rule 4: public object routes against private fixtures. ----------------
WP_CLI::log( '--- rule 4: public object routes refuse a private fixture signed out ---' );
$wcb_fixtures = array(
	'job'       => array_merge( array_values( $wcb_job_secret ), array( $wcb_job_draftco ) ),
	'company'   => array( $wcb_co_draft ),
	'candidate' => array( $wcb_users['cand-private'] ),
);
$wcb_probed   = 0;
$wcb_expected = 0;
foreach ( $wcb_live as $wcb_key => $wcb_route ) {
	$wcb_entry = $wcb_declared[ $wcb_key ] ?? array();
	if ( 'public' !== ( $wcb_entry['audience'] ?? '' ) || ! $wcb_route['object_id'] || ! in_array( 'GET', $wcb_route['methods'], true ) ) {
		continue;
	}
	++$wcb_expected;
	$wcb_probe = (string) ( $wcb_entry['probe'] ?? '' );
	if ( 0 === strpos( $wcb_probe, 'none:' ) ) {
		++$wcb_probed;
		continue; // Declared as carrying nothing that can leak, with the reason.
	}
	if ( ! isset( $wcb_fixtures[ $wcb_probe ] ) ) {
		wcb_assert( false, "{$wcb_key}: public object route without a known probe fixture ('{$wcb_probe}')" );
		continue;
	}
	foreach ( $wcb_fixtures[ $wcb_probe ] as $wcb_id ) {
		$wcb_path = (string) preg_replace( '/\(\?P<id>[^)]*\)/', (string) $wcb_id, $wcb_route['route'] );
		if ( $wcb_path === $wcb_route['route'] || false !== strpos( $wcb_path, '(?P<' ) ) {
			wcb_assert( false, "{$wcb_key}: could not build a probe path, so it proved nothing" );
			continue 2;
		}
		$wcb_res  = wcb_ra_call( 'GET', $wcb_path, array(), 0 );
		$wcb_body = (string) wp_json_encode( $wcb_res->get_data() );
		wcb_assert( false === strpos( $wcb_body, 'SEC18-SECRET' ), "{$wcb_key}: signed-out GET {$wcb_path} -> {$wcb_res->get_status()} does not disclose the private fixture" );
	}
	++$wcb_probed;
}
wcb_assert( $wcb_expected > 0 && $wcb_probed === $wcb_expected, "every public object route was probed ({$wcb_probed}/{$wcb_expected})" );

// --- Runtime matrix: owner vs same-role member vs signed out. --------------
WP_CLI::log( '--- matrix: applications ---' );
wcb_ra_expect( 'candidate reads own application', true, 'GET', "/wcb/v1/applications/{$wcb_app_a}", array(), $wcb_cand_a );
wcb_ra_expect( 'job owner reads the application', true, 'GET', "/wcb/v1/applications/{$wcb_app_a}", array(), $wcb_emp_a );
wcb_ra_expect( 'another candidate', false, 'GET', "/wcb/v1/applications/{$wcb_app_a}", array(), $wcb_cand_b );
wcb_ra_expect( 'another employer', false, 'GET', "/wcb/v1/applications/{$wcb_app_a}", array(), $wcb_emp_b );
wcb_ra_expect( 'signed out', false, 'GET', "/wcb/v1/applications/{$wcb_app_a}", array(), 0 );
wcb_ra_expect( 'signed out, guest application', false, 'GET', "/wcb/v1/applications/{$wcb_app_guest}", array(), 0 );

wcb_ra_expect( 'job owner sets status', true, 'PATCH', "/wcb/v1/applications/{$wcb_app_a}/status", array( 'status' => 'reviewing' ), $wcb_emp_a );
wcb_ra_expect( 'another employer sets status', false, 'PATCH', "/wcb/v1/applications/{$wcb_app_a}/status", array( 'status' => 'shortlisted' ), $wcb_emp_b );
wcb_ra_expect( 'the candidate sets status', false, 'PATCH', "/wcb/v1/applications/{$wcb_app_a}/status", array( 'status' => 'hired' ), $wcb_cand_a );
wcb_assert( 'reviewing' === get_post_meta( $wcb_app_a, '_wcb_status', true ), 'refused status writes changed nothing' );

wcb_ra_expect( 'job owner adds a note', true, 'POST', "/wcb/v1/applications/{$wcb_app_a}/notes", array( 'text' => 'sec18 note' ), $wcb_emp_a );
wcb_ra_expect( 'another employer adds a note', false, 'POST', "/wcb/v1/applications/{$wcb_app_a}/notes", array( 'text' => 'sec18 intruder' ), $wcb_emp_b );
wcb_ra_expect( 'another employer reads notes', false, 'GET', "/wcb/v1/applications/{$wcb_app_a}/notes", array(), $wcb_emp_b );
wcb_ra_expect( 'the candidate reads notes', false, 'GET', "/wcb/v1/applications/{$wcb_app_a}/notes", array(), $wcb_cand_a );
wcb_ra_expect( 'another employer rates', false, 'PATCH', "/wcb/v1/applications/{$wcb_app_a}/rating", array( 'rating' => 1 ), $wcb_emp_b );
wcb_ra_expect( 'job owner rates', true, 'PATCH', "/wcb/v1/applications/{$wcb_app_a}/rating", array( 'rating' => 4 ), $wcb_emp_a );
wcb_ra_expect( 'another candidate withdraws', false, 'DELETE', "/wcb/v1/applications/{$wcb_app_a}", array(), $wcb_cand_b );
wcb_ra_expect( 'signed out withdraws a guest application', false, 'DELETE', "/wcb/v1/applications/{$wcb_app_guest}", array(), 0 );

WP_CLI::log( '--- matrix: per-job and per-candidate lists ---' );
wcb_ra_expect( 'job owner lists applicants', true, 'GET', "/wcb/v1/jobs/{$wcb_job_a}/applications", array(), $wcb_emp_a );
wcb_ra_expect( 'another employer lists applicants', false, 'GET', "/wcb/v1/jobs/{$wcb_job_a}/applications", array(), $wcb_emp_b );
wcb_ra_expect( 'signed out lists applicants', false, 'GET', "/wcb/v1/jobs/{$wcb_job_a}/applications", array(), 0 );
wcb_ra_expect_permission( 'job owner exports applicants', true, 'GET', "/wcb/v1/jobs/{$wcb_job_a}/applications/export", $wcb_emp_a );
wcb_ra_expect_permission( 'another employer exports applicants', false, 'GET', "/wcb/v1/jobs/{$wcb_job_a}/applications/export", $wcb_emp_b );
wcb_ra_expect_permission( 'signed out exports applicants', false, 'GET', "/wcb/v1/jobs/{$wcb_job_a}/applications/export", 0 );
wcb_ra_expect( 'company owner lists company applications', true, 'GET', "/wcb/v1/employers/{$wcb_co_a}/applications", array(), $wcb_emp_a );
wcb_ra_expect( 'another employer lists company applications', false, 'GET', "/wcb/v1/employers/{$wcb_co_a}/applications", array(), $wcb_emp_b );

$wcb_res = wcb_ra_expect( 'candidate lists own applications', true, 'GET', "/wcb/v1/candidates/{$wcb_cand_a}/applications", array(), $wcb_cand_a );
wcb_assert( 1 === wcb_ra_total( $wcb_res ), 'candidate application total counts only their own (' . wcb_ra_total( $wcb_res ) . ')' );
wcb_ra_expect( 'another candidate lists them', false, 'GET', "/wcb/v1/candidates/{$wcb_cand_a}/applications", array(), $wcb_cand_b );
wcb_ra_expect( 'signed out lists them', false, 'GET', "/wcb/v1/candidates/{$wcb_cand_a}/applications", array(), 0 );
wcb_ra_expect( 'signed out lists user 0 (every guest application)', false, 'GET', '/wcb/v1/candidates/0/applications', array(), 0 );
wcb_ra_expect( 'signed out reads user 0 bookmarks', false, 'GET', '/wcb/v1/candidates/0/bookmarks', array(), 0 );
wcb_ra_expect( 'another candidate reads bookmarks', false, 'GET', "/wcb/v1/candidates/{$wcb_cand_a}/bookmarks", array(), $wcb_cand_b );
wcb_ra_expect( 'candidate reads own bookmarks', true, 'GET', "/wcb/v1/candidates/{$wcb_cand_a}/bookmarks", array(), $wcb_cand_a );

$wcb_res = wcb_ra_expect( 'job owner lists own applications', true, 'GET', '/wcb/v1/employers/me/applications', array(), $wcb_emp_a );
wcb_assert( 1 === wcb_ra_total( $wcb_res ) || 2 === wcb_ra_total( $wcb_res ), 'owner application total covers their job (' . wcb_ra_total( $wcb_res ) . ')' );
$wcb_res = wcb_ra_expect( 'another employer lists own applications', true, 'GET', '/wcb/v1/employers/me/applications', array(), $wcb_emp_b );
wcb_assert( 0 === wcb_ra_total( $wcb_res ), 'another employer\'s total does not count A\'s applicants (' . wcb_ra_total( $wcb_res ) . ')' );
$wcb_res = wcb_ra_expect( 'another employer lists own jobs', true, 'GET', '/wcb/v1/employers/me/jobs', array(), $wcb_emp_b );
wcb_assert( 0 === wcb_ra_total( $wcb_res ), 'another employer\'s job total does not count A\'s jobs (' . wcb_ra_total( $wcb_res ) . ')' );

WP_CLI::log( '--- matrix: jobs, companies, candidates ---' );
wcb_ra_expect( 'job owner edits the job', true, 'PATCH', "/wcb/v1/jobs/{$wcb_job_a}", array( 'title' => "sec18 {$wcb_marker} live" ), $wcb_emp_a );
wcb_ra_expect( 'another employer edits it', false, 'PATCH', "/wcb/v1/jobs/{$wcb_job_a}", array( 'title' => 'sec18 hijacked' ), $wcb_emp_b );
wcb_ra_expect( 'signed out edits it', false, 'PATCH', "/wcb/v1/jobs/{$wcb_job_a}", array( 'title' => 'sec18 hijacked' ), 0 );
wcb_ra_expect( 'another employer deletes it', false, 'DELETE', "/wcb/v1/jobs/{$wcb_job_a}", array(), $wcb_emp_b );
wcb_assert( false === strpos( (string) get_the_title( $wcb_job_a ), 'hijacked' ) && 'publish' === get_post_status( $wcb_job_a ), 'refused job writes changed nothing' );
wcb_ra_expect( 'owner reads own pending job', true, 'GET', "/wcb/v1/jobs/{$wcb_job_secret['pending']}", array(), $wcb_emp_a );
wcb_ra_expect( 'another employer reads the pending job', false, 'GET', "/wcb/v1/jobs/{$wcb_job_secret['pending']}", array(), $wcb_emp_b );

$wcb_res = wcb_ra_call( 'PATCH', "/wcb/v1/jobs/{$wcb_job_secret['pending']}", array( 'status' => 'closed' ), $wcb_emp_a );
wcb_assert( 409 === $wcb_res->get_status() && 'pending' === get_post_status( $wcb_job_secret['pending'] ), 'owner cannot close a pending job (the close-then-reopen moderation bypass)' );
wcb_ra_call( 'PATCH', "/wcb/v1/jobs/{$wcb_job_secret['pending']}", array( 'status' => 'publish' ), $wcb_emp_a );
$wcb_job_trash = $wcb_post( 'wcb_job', 'trash', 'SEC18-SECRET-JOB trashed', $wcb_emp_a );
wcb_ra_call( 'PATCH', "/wcb/v1/jobs/{$wcb_job_trash}", array( 'status' => 'publish' ), $wcb_emp_a );
if ( ! \WCB\Admin\Settings::bool( 'auto_publish_jobs', false ) ) {
	wcb_assert( 'pending' === get_post_status( $wcb_job_secret['pending'] ), 'owner publishing a pending job still goes through moderation' );
	wcb_assert( 'pending' === get_post_status( $wcb_job_trash ), 'owner publishing a trashed job goes through moderation (' . get_post_status( $wcb_job_trash ) . ')' );
}
wp_delete_post( $wcb_job_trash, true );

wcb_ra_expect( 'company owner edits the company', true, 'PATCH', "/wcb/v1/employers/{$wcb_co_a}", array( 'name' => 'sec18 Company A' ), $wcb_emp_a );
wcb_ra_expect( 'another employer edits the company', false, 'PATCH', "/wcb/v1/employers/{$wcb_co_a}", array( 'name' => 'sec18 hijacked' ), $wcb_emp_b );
wcb_ra_expect( 'signed out edits the company', false, 'PATCH', "/wcb/v1/employers/{$wcb_co_a}", array( 'name' => 'sec18 hijacked' ), 0 );
wcb_assert( 'sec18 Company A' === get_the_title( $wcb_co_a ), 'refused company writes changed nothing' );

wcb_ra_expect( 'candidate edits own profile', true, 'PATCH', "/wcb/v1/candidates/{$wcb_cand_a}", array( 'bio' => 'sec18 bio' ), $wcb_cand_a );
wcb_ra_expect( 'another candidate edits it', false, 'PATCH', "/wcb/v1/candidates/{$wcb_cand_a}", array( 'bio' => 'sec18 hijacked' ), $wcb_cand_b );
wcb_ra_expect( 'signed out edits user 0', false, 'PATCH', '/wcb/v1/candidates/0', array( 'bio' => 'sec18 hijacked' ), 0 );

WP_CLI::log( '--- matrix: public listings count only public rows ---' );
$wcb_res = wcb_ra_call( 'GET', '/wcb/v1/jobs', array( 'search' => $wcb_marker ), 0 );
wcb_assert( 1 === wcb_ra_total( $wcb_res ) && false === strpos( (string) wp_json_encode( $wcb_res->get_data() ), 'SEC18-SECRET' ), 'signed-out job list total is 1 of 5 fixtures (' . wcb_ra_total( $wcb_res ) . ')' );
$wcb_res = wcb_ra_call( 'GET', '/wcb/v1/jobs', array( 'search' => $wcb_marker ), $wcb_emp_b );
wcb_assert( 1 === wcb_ra_total( $wcb_res ) && false === strpos( (string) wp_json_encode( $wcb_res->get_data() ), 'SEC18-SECRET' ), 'another employer\'s job list total is 1 (' . wcb_ra_total( $wcb_res ) . ')' );
$wcb_res = wcb_ra_call( 'GET', "/wcb/v1/employers/{$wcb_co_a}/jobs", array(), 0 );
wcb_assert( 1 === wcb_ra_total( $wcb_res ) && false === strpos( (string) wp_json_encode( $wcb_res->get_data() ), 'SEC18-SECRET' ), 'signed-out company job list total is 1 (' . wcb_ra_total( $wcb_res ) . ')' );

WP_CLI::log( '--- matrix: private files ---' );
$wcb_file = $wcb_post( 'attachment', 'private', 'sec18 cv', $wcb_cand_a, array( \WCB\Core\PrivateFiles::META => '1' ) );
update_post_meta( $wcb_app_a, '_wcb_resume_attachment_id', $wcb_file );
$wcb_plain = $wcb_post( 'attachment', 'inherit', 'sec18 plain', $wcb_emp_b );
wp_set_current_user( $wcb_cand_a );
wcb_assert( \WCB\Core\PrivateFiles::can_download( $wcb_file ), 'candidate may download own CV' );
wp_set_current_user( $wcb_emp_a );
wcb_assert( \WCB\Core\PrivateFiles::can_download( $wcb_file ), 'job owner may download the applicant\'s CV' );
wp_set_current_user( $wcb_emp_b );
wcb_assert( ! \WCB\Core\PrivateFiles::can_download( $wcb_file ), 'another employer may not' );
wp_set_current_user( $wcb_cand_b );
wcb_assert( ! \WCB\Core\PrivateFiles::can_download( $wcb_file ), 'another candidate may not' );
wp_set_current_user( 0 );
wcb_assert( ! \WCB\Core\PrivateFiles::can_download( $wcb_file ), 'signed out may not' );
wp_set_current_user( $wcb_emp_b );
wcb_assert( ! \WCB\Core\PrivateFiles::can_download( $wcb_plain ), 'the gated handler does not serve attachments it never made private' );
wcb_ra_expect_permission( 'job owner via REST', true, 'GET', "/wcb/v1/files/{$wcb_file}", $wcb_emp_a );
wcb_ra_expect_permission( 'another employer via REST', false, 'GET', "/wcb/v1/files/{$wcb_file}", $wcb_emp_b );
wcb_ra_expect_permission( 'signed out via REST', false, 'GET', "/wcb/v1/files/{$wcb_file}", 0 );

// --- Teardown. ---------------------------------------------------------------
wp_set_current_user( 0 );
foreach ( array( $wcb_app_a, $wcb_app_guest, $wcb_job_a, $wcb_job_draftco, $wcb_co_a, $wcb_co_draft ) as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}
foreach ( $wcb_job_secret as $wcb_id ) {
	wp_delete_post( $wcb_id, true );
}
wp_delete_attachment( $wcb_file, true );
wp_delete_attachment( $wcb_plain, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( $wcb_users as $wcb_id ) {
	wp_delete_user( $wcb_id );
}

WP_CLI::log( '' );
WP_CLI::log( '  Total: ' . ( $GLOBALS['wcb_test_pass'] + $GLOBALS['wcb_test_fail'] ) . '  Pass: ' . $GLOBALS['wcb_test_pass'] . '  Fail: ' . $GLOBALS['wcb_test_fail'] );
if ( $GLOBALS['wcb_test_fail'] > 0 ) {
	WP_CLI::error( $GLOBALS['wcb_test_fail'] . ' test(s) failed.' );
} else {
	WP_CLI::success( 'All route authority tests passed.' );
}
