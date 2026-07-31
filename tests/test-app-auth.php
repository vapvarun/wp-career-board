<?php
/**
 * Live verification for Career Board's mobile-app authentication surface.
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-app-auth.php
 *
 * Proves on the running site that:
 *
 *   1. `GET /wcb/v1/settings/app-config` publishes the Wbcom App Auth standard
 *      `auth` block the fleet-wide app reader parses, plus `password_login`.
 *   2. The scheme seams work: `careerboardapp` in the site's own allowlist, the
 *      `wcb_app_connect_schemes` sibling seam, and — on a combined site —
 *      BuddyNext's allowlist accepting `careerboardapp` (one door per site).
 *   3. Standalone, `connect_url` is EMPTY by decision: Career Board builds no
 *      bridge; core's repaired authorize screen is its interactive door. The
 *      `wcb_app_connect_bridge` filter can redirect the door.
 *   4. The kses repair is SCOPED: the app scheme is added on the authorize
 *      screen and nowhere else.
 *   5. `POST /auth/app-password` mints a working Basic credential for a good
 *      password, refuses a wrong one with the uniform 401, and REFUSES a
 *      member whose account is scheduled for deletion — the grace window
 *      revokes credentials, so an exchange that ignored it would hand back a
 *      fresh working one and undo the revocation.
 *   6. Reconnect replaces on EVERY door: two mints with one app_id leave one
 *      row; a hand-made row (no app_id) is never touched.
 *
 * Exits non-zero if any case fails, so it is CI-friendly.
 *
 * @package WP_Career_Board
 */

declare( strict_types=1 );

use WCB\Auth\AppAuthorizeAccess;
use WCB\Auth\AppConnect;
use WCB\Modules\Account\AccountDeletionService;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

/**
 * Record and print one check result, and report the running tally.
 *
 * Counters are function-static, NOT globals: `wp eval-file` executes this file
 * inside a function scope, so a top-level `$pass` is a local there and a
 * `global $pass` in here would bind to a different, always-zero variable —
 * making the suite exit 0 no matter what failed.
 *
 * @param string|null $id     Check id, or null to read the tally.
 * @param bool        $ok     Whether it passed.
 * @param string      $detail Optional failure detail.
 * @return array{pass:int,fail:int}
 */
function wcb_auth_check( ?string $id = null, bool $ok = false, string $detail = '' ): array {
	static $pass = 0;
	static $fail = 0;

	if ( null === $id ) {
		return array(
			'pass' => $pass,
			'fail' => $fail,
		);
	}

	if ( $ok ) {
		++$pass;
		echo "  PASS  {$id}\n";
	} else {
		++$fail;
		echo "  FAIL  {$id}" . ( '' !== $detail ? " — {$detail}" : '' ) . "\n";
	}

	return array(
		'pass' => $pass,
		'fail' => $fail,
	);
}

/**
 * Loopback request against the Career Board REST namespace.
 *
 * @param string $method HTTP method.
 * @param string $path   Path under the namespace.
 * @param array  $body   Request body.
 * @param string $basic  Optional "user:pass" for HTTP Basic.
 * @return array{0:int,1:array}
 */
function wcb_auth_request( string $method, string $path, array $body = array(), string $basic = '' ): array {
	$base = untrailingslashit( home_url() ) . '/wp-json/wcb/v1';

	$args = array(
		'method'    => $method,
		'timeout'   => 15,
		'sslverify' => false, // Local dev cert.
		'headers'   => array(),
	);

	if ( $body ) {
		$args['body'] = $body;
	}

	if ( '' !== $basic ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth encoding.
		$args['headers']['Authorization'] = 'Basic ' . base64_encode( $basic );
	}

	$response = wp_remote_request( $base . $path, $args );

	if ( is_wp_error( $response ) ) {
		return array( 0, array( 'error' => $response->get_error_message() ) );
	}

	$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

	return array( (int) wp_remote_retrieve_response_code( $response ), is_array( $decoded ) ? $decoded : array() );
}

echo "Career Board app-auth contract suite\n";

$wcb_bn_active = class_exists( '\BuddyNext\App\AppConnectService' );
echo $wcb_bn_active
	? "  (topology: BuddyNext ACTIVE — combined)\n"
	: "  (topology: standalone — no BuddyNext)\n";

// ── 1. auth block + password_login on the live app-config ────────────────
list( $wcb_status, $wcb_config ) = wcb_auth_request( 'GET', '/settings/app-config' );
$wcb_auth_block                  = $wcb_config['auth'] ?? null;

wcb_auth_check( 'config.200', 200 === $wcb_status, "status {$wcb_status}" );
wcb_auth_check( 'config.auth-present', is_array( $wcb_auth_block ), 'no auth block on app-config' );
wcb_auth_check( 'config.password-login-flag', true === ( $wcb_config['password_login'] ?? null ) );

if ( is_array( $wcb_auth_block ) ) {
	foreach ( array( 'social_providers', 'twofactor', 'register', 'app_passwords_available', 'connect_url', 'connect_schemes' ) as $wcb_key ) {
		wcb_auth_check( "config.auth.{$wcb_key}", array_key_exists( $wcb_key, $wcb_auth_block ) );
	}
	wcb_auth_check( 'config.auth.social-empty', array() === $wcb_auth_block['social_providers'] );
	wcb_auth_check(
		'config.auth.schemes-carry-app',
		in_array( AppAuthorizeAccess::app_scheme(), (array) $wcb_auth_block['connect_schemes'], true ),
		'careerboardapp missing from connect_schemes'
	);
}

// ── 2. Scheme seams ──────────────────────────────────────────────────────
wcb_auth_check( 'schemes.own', in_array( 'careerboardapp', AppConnect::schemes(), true ) );

$wcb_sibling = static function ( $schemes ) {
	$schemes[] = 'siblingapp';
	return $schemes;
};
add_filter( 'wcb_app_connect_schemes', $wcb_sibling );
wcb_auth_check( 'schemes.sibling-seam', in_array( 'siblingapp', AppConnect::schemes(), true ) );
remove_filter( 'wcb_app_connect_schemes', $wcb_sibling );

// ── 3. One-door deference + override ─────────────────────────────────────
$wcb_bridge = AppConnect::bridge_info();

if ( $wcb_bn_active ) {
	wcb_auth_check( 'bridge.owner-bn', 'buddynext' === $wcb_bridge['owner'] );
	wcb_auth_check( 'bridge.bn-url', '' !== (string) $wcb_bridge['connect_url'] );
	wcb_auth_check(
		'bridge.bn-allowlist-accepts-us',
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reading BuddyNext's OWN allowlist is the point; production code only add_filter()s it.
		in_array( 'careerboardapp', (array) apply_filters( 'buddynext_app_connect_schemes', array() ), true ),
		'BN allowlist does not carry careerboardapp — join filter not registered?'
	);
} else {
	wcb_auth_check( 'bridge.owner-self', 'wp-career-board' === $wcb_bridge['owner'] );
	wcb_auth_check( 'bridge.no-own-bridge', '' === (string) $wcb_bridge['connect_url'], 'standalone Career Board must NOT advertise a bridge' );
}

$wcb_override = static function () {
	return array(
		'owner'           => 'test',
		'connect_url'     => 'https://example.test/connect-app/',
		'connect_schemes' => array( 'careerboardapp' ),
	);
};
add_filter( 'wcb_app_connect_bridge', $wcb_override );
$wcb_forced = AppConnect::bridge_info();
remove_filter( 'wcb_app_connect_bridge', $wcb_override );
wcb_auth_check( 'bridge.filter-override', 'https://example.test/connect-app/' === $wcb_forced['connect_url'] );

// ── 4. kses repair is SCOPED to the authorize screen ─────────────────────
// esc_url() cannot be exercised both ways in-process: wp_allowed_protocols()
// freezes its static list long before this runs. On a REAL authorize request
// SCRIPT_FILENAME is that screen from the first line, so the filter fires
// during boot — the browser walk proves that end-to-end. Here we prove the
// filter's own scoping decision.
wcb_auth_check( 'kses.stripped-in-content', '' === esc_url( 'careerboardapp://auth?x=1' ), 'app scheme must NOT be linkable in ordinary content' );

$wcb_access                 = new AppAuthorizeAccess();
$wcb_prev_script            = isset( $_SERVER['SCRIPT_FILENAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_FILENAME'] ) ) : '';
$_SERVER['SCRIPT_FILENAME'] = '/wp-admin/authorize-application.php';
$wcb_on_authorize           = $wcb_access->allow_app_scheme( array( 'http', 'https' ) );
$_SERVER['SCRIPT_FILENAME'] = '/index.php';
$wcb_off_authorize          = $wcb_access->allow_app_scheme( array( 'http', 'https' ) );
$_SERVER['SCRIPT_FILENAME'] = $wcb_prev_script;

wcb_auth_check( 'kses.filter-adds-on-authorize', in_array( 'careerboardapp', (array) $wcb_on_authorize, true ) );
wcb_auth_check( 'kses.filter-inert-elsewhere', ! in_array( 'careerboardapp', (array) $wcb_off_authorize, true ) );

// ── 5. The credentials exchange, live over loopback ──────────────────────
$wcb_login    = 'wcb_appauth_contract_user';
$wcb_password = wp_generate_password( 24 );
$wcb_existing = get_user_by( 'login', $wcb_login );

if ( $wcb_existing ) {
	wp_delete_user( $wcb_existing->ID );
}

$wcb_user_id = wp_insert_user(
	array(
		'user_login' => $wcb_login,
		'user_pass'  => $wcb_password,
		'user_email' => $wcb_login . '@example.test',
		'role'       => 'subscriber',
	)
);

if ( is_wp_error( $wcb_user_id ) ) {
	wcb_auth_check( 'exchange.fixture', false, $wcb_user_id->get_error_message() );
} else {
	$wcb_app_id = wp_generate_uuid4();

	list( $wcb_status, $wcb_body ) = wcb_auth_request(
		'POST',
		'/auth/app-password',
		array(
			'username' => $wcb_login,
			'password' => $wcb_password,
			'app_name' => 'Career Board contract',
			'app_id'   => $wcb_app_id,
		)
	);
	$wcb_minted                    = (string) ( $wcb_body['password'] ?? '' );

	wcb_auth_check( 'exchange.mints', 200 === $wcb_status && '' !== $wcb_minted, "status {$wcb_status}" );

	if ( '' !== $wcb_minted ) {
		list( $wcb_status ) = wcb_auth_request( 'GET', '/settings/app-config', array(), $wcb_login . ':' . $wcb_minted );
		wcb_auth_check( 'exchange.credential-authenticates', 200 === $wcb_status, "status {$wcb_status}" );
	}

	list( $wcb_status, $wcb_body ) = wcb_auth_request(
		'POST',
		'/auth/app-password',
		array(
			'username' => $wcb_login,
			'password' => 'definitely-wrong-password',
		)
	);
	wcb_auth_check(
		'exchange.wrong-password-401',
		401 === $wcb_status && 'wcb_login_failed' === ( $wcb_body['code'] ?? '' ),
		"status {$wcb_status} code " . ( $wcb_body['code'] ?? '-' )
	);

	// A member awaiting account deletion must not mint a fresh credential:
	// the grace window revoked theirs on purpose.
	update_user_meta( $wcb_user_id, AccountDeletionService::META_SCHEDULED, time() + DAY_IN_SECONDS );
	list( $wcb_status, $wcb_body ) = wcb_auth_request(
		'POST',
		'/auth/app-password',
		array(
			'username' => $wcb_login,
			'password' => $wcb_password,
		)
	);
	wcb_auth_check(
		'exchange.pending-deletion-refused',
		403 === $wcb_status && 'wcb_account_pending_deletion' === ( $wcb_body['code'] ?? '' ),
		"status {$wcb_status} code " . ( $wcb_body['code'] ?? '-' )
	);
	delete_user_meta( $wcb_user_id, AccountDeletionService::META_SCHEDULED );

	// ── 6. Reconnect replaces, on EVERY door ─────────────────────────────
	\WP_Application_Passwords::create_new_application_password(
		$wcb_user_id,
		array(
			'name'   => 'Career Board',
			'app_id' => $wcb_app_id,
		)
	);

	$wcb_rows = array();
	foreach ( \WP_Application_Passwords::get_user_application_passwords( $wcb_user_id ) as $wcb_row ) {
		if ( $wcb_app_id === ( $wcb_row['app_id'] ?? '' ) ) {
			$wcb_rows[] = $wcb_row;
		}
	}
	wcb_auth_check( 'replace.one-row-per-install', 1 === count( $wcb_rows ), count( $wcb_rows ) . ' rows for one app_id' );

	\WP_Application_Passwords::create_new_application_password( $wcb_user_id, array( 'name' => 'Hand-made' ) );
	\WP_Application_Passwords::create_new_application_password(
		$wcb_user_id,
		array(
			'name'   => 'Career Board',
			'app_id' => $wcb_app_id,
		)
	);
	$wcb_all = \WP_Application_Passwords::get_user_application_passwords( $wcb_user_id );
	wcb_auth_check( 'replace.handmade-untouched', 2 === count( $wcb_all ), count( $wcb_all ) . ' rows total (want hand-made + one app row)' );

	wp_delete_user( $wcb_user_id );
}

$wcb_tally = wcb_auth_check();

echo "\n{$wcb_tally['pass']} passed, {$wcb_tally['fail']} failed\n";

exit( $wcb_tally['fail'] > 0 ? 1 : 0 );
