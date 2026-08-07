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
use WCB\Auth\AppCredentials;
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

// Password sign-in is OFF by default since 1.7.2 (it accepts real account
// passwords, and most sites never install the app). Sections 1 and 5 test what
// the exchange DOES when a site has opted in, so they opt in here and restore
// the site's own value at the end. Section 7 owns the default itself.
$wcb_settings_snapshot                       = (array) get_option( 'wcb_settings', array() );
$wcb_settings_on                             = $wcb_settings_snapshot;
$wcb_settings_on['app_password_login']       = true;
update_option( 'wcb_settings', $wcb_settings_on );
wp_cache_flush();

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

// Sections 1-6 are done with the opt-in; hand the site back before section 7,
// which asserts the DEFAULT and must not see a value we set.
update_option( 'wcb_settings', $wcb_settings_snapshot );
wp_cache_flush();

// ─────────────────────────────────────────────────────────
// 7. The owner switch, the revoke route, and the IP bucket.
//
// Added with the 1.7.1 hardening. Each case guards a thing that was wrong:
// the exchange shipped ON with no settings field to turn it off, the app could
// mint a credential but never destroy one, and the per-IP throttle keyed on
// REMOTE_ADDR, which behind a proxy is one bucket for the whole membership.
// ─────────────────────────────────────────────────────────
{
	$wcb_settings_before = get_option( 'wcb_settings', array() );

	// --- 7a. Default OFF. -------------------------------------------------
	$wcb_s = (array) $wcb_settings_before;
	unset( $wcb_s['app_password_login'] );
	update_option( 'wcb_settings', $wcb_s );
	wp_cache_flush();

	wcb_auth_check(
		'switch.default-off',
		false === AppCredentials::is_enabled(),
		'a site that never saved the setting must not accept passwords'
	);

	$wcb_user_id = wp_insert_user(
		array(
			'user_login' => 'wcb_auth_qa_' . wp_generate_password( 6, false ),
			'user_pass'  => 'QaPass!' . wp_generate_password( 8, false ),
			'user_email' => 'wcb_auth_qa_' . wp_generate_password( 6, false ) . '@example.test',
			'role'       => 'wcb_candidate',
		)
	);
	$wcb_user = get_user_by( 'ID', $wcb_user_id );
	$wcb_pw   = 'QaPass!Known123';
	wp_set_password( $wcb_pw, $wcb_user_id );

	$wcb_off = AppCredentials::exchange( $wcb_user->user_login, $wcb_pw, 'QA', 'qa-app' );
	wcb_auth_check(
		'switch.refuses-while-off',
		is_wp_error( $wcb_off ) && 'wcb_app_passwords_off' === $wcb_off->get_error_code(),
		'a CORRECT password must not mint anything while the switch is off'
	);
	wcb_auth_check(
		'switch.mints-nothing-while-off',
		0 === count( \WP_Application_Passwords::get_user_application_passwords( $wcb_user_id ) ),
		'no credential row may exist after a refused exchange'
	);

	// --- 7b. The setting is what flips it. --------------------------------
	$wcb_s['app_password_login'] = true;
	update_option( 'wcb_settings', $wcb_s );
	wp_cache_flush();
	wcb_auth_check( 'switch.setting-enables', true === AppCredentials::is_enabled() );

	add_filter( 'wcb_app_password_login_enabled', '__return_false', 99 );
	wcb_auth_check( 'switch.filter-overrides', false === AppCredentials::is_enabled() );
	remove_filter( 'wcb_app_password_login_enabled', '__return_false', 99 );

	// --- 7c. Revocation. --------------------------------------------------
	// wp_authenticate_application_password() early-returns outside a REST
	// context, so without this filter the before and after read the same and
	// the case proves nothing.
	add_filter( 'application_password_is_api_request', '__return_true' );
	add_filter( 'wp_is_application_passwords_available', '__return_true' );

	$wcb_a = \WP_Application_Passwords::create_new_application_password( $wcb_user_id, array( 'name' => 'QA A', 'app_id' => 'qa-a' ) );
	$wcb_b = \WP_Application_Passwords::create_new_application_password( $wcb_user_id, array( 'name' => 'QA B', 'app_id' => 'qa-b' ) );

	wp_set_current_user( 0 );
	$wcb_authed = wp_authenticate_application_password( null, $wcb_user->user_login, $wcb_a[0] );
	wcb_auth_check( 'revoke.can-authenticate-first', $wcb_authed instanceof WP_User );

	if ( $wcb_authed instanceof WP_User ) {
		wp_set_current_user( $wcb_authed->ID );

		$wcb_res  = rest_do_request( new WP_REST_Request( 'DELETE', '/wcb/v1/auth/app-password' ) );
		$wcb_data = (array) $wcb_res->get_data();
		wcb_auth_check(
			'revoke.reports-success',
			200 === $wcb_res->get_status() && true === ( $wcb_data['revoked'] ?? false ),
			'status ' . $wcb_res->get_status() . ' ' . wp_json_encode( $wcb_data )
		);

		$wcb_left = wp_list_pluck( \WP_Application_Passwords::get_user_application_passwords( $wcb_user_id ), 'uuid' );
		wcb_auth_check( 'revoke.kills-the-authenticating-row', ! in_array( $wcb_a[1]['uuid'], $wcb_left, true ) );
		wcb_auth_check( 'revoke.spares-the-other-row', in_array( $wcb_b[1]['uuid'], $wcb_left, true ) );

		wp_set_current_user( 0 );
		wcb_auth_check(
			'revoke.dead-credential-cannot-authenticate',
			is_wp_error( wp_authenticate_application_password( null, $wcb_user->user_login, $wcb_a[0] ) )
		);
	}

	// A cookie-authenticated caller has no app password; that is not a failure.
	// Sign-out must never error, or the app is stuck signed in.
	wp_set_current_user( $wcb_user_id );
	$wcb_noop = rest_do_request( new WP_REST_Request( 'DELETE', '/wcb/v1/auth/app-password' ) );
	wcb_auth_check(
		'revoke.safe-noop-under-cookie-auth',
		200 === $wcb_noop->get_status() && false === ( (array) $wcb_noop->get_data() )['revoked'],
		'status ' . $wcb_noop->get_status()
	);

	remove_filter( 'application_password_is_api_request', '__return_true' );
	remove_filter( 'wp_is_application_passwords_available', '__return_true' );

	// --- 7d. The IP bucket is the client, not the proxy. ------------------
	$wcb_remote_before = $_SERVER['REMOTE_ADDR'] ?? null;
	$_SERVER['REMOTE_ADDR']            = '203.0.113.9';
	$_SERVER['HTTP_CF_CONNECTING_IP']  = '198.51.100.7';

	wcb_auth_check(
		'ip.forwarded-header-ignored-by-default',
		'203.0.113.9' === AppCredentials::client_ip(),
		'an unvalidated forwarded header is attacker-controlled'
	);

	add_filter( 'wcb_app_password_client_ip_header', static fn() => 'HTTP_CF_CONNECTING_IP' );
	wcb_auth_check( 'ip.opted-in-header-used', '198.51.100.7' === AppCredentials::client_ip() );

	$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.7, 203.0.113.1';
	wcb_auth_check( 'ip.leftmost-of-chain', '198.51.100.7' === AppCredentials::client_ip() );

	$_SERVER['HTTP_CF_CONNECTING_IP'] = 'not-an-ip; DROP TABLE';
	wcb_auth_check(
		'ip.malformed-falls-back',
		'203.0.113.9' === AppCredentials::client_ip(),
		'a bad header must never become a rate-limit bucket key'
	);
	remove_all_filters( 'wcb_app_password_client_ip_header' );

	unset( $_SERVER['HTTP_CF_CONNECTING_IP'] );
	if ( null !== $wcb_remote_before ) {
		$_SERVER['REMOTE_ADDR'] = $wcb_remote_before;
	}

	// --- 7e. Both throttle ceilings are tunable. --------------------------
	add_filter( 'wcb_app_password_max_attempts_per_ip', static fn() => 2 );
	$wcb_ip = 'qa-' . wp_generate_password( 6, false );
	$wcb_seq = array(
		AppCredentials::record_attempt( $wcb_ip ),
		AppCredentials::record_attempt( $wcb_ip ),
		AppCredentials::record_attempt( $wcb_ip ),
	);
	wcb_auth_check(
		'throttle.per-ip-ceiling-filterable',
		array( true, true, false ) === $wcb_seq,
		wp_json_encode( $wcb_seq )
	);
	remove_all_filters( 'wcb_app_password_max_attempts_per_ip' );

	add_filter( 'wcb_app_password_max_failures', static fn() => 2 );
	$wcb_bucket = 'user:qa-' . wp_generate_password( 6, false );
	AppCredentials::record_failure( $wcb_bucket );
	$wcb_after_one = AppCredentials::is_locked_out( $wcb_bucket );
	AppCredentials::record_failure( $wcb_bucket );
	$wcb_after_two = AppCredentials::is_locked_out( $wcb_bucket );
	wcb_auth_check( 'throttle.failure-ceiling-filterable', ! $wcb_after_one && $wcb_after_two );
	AppCredentials::clear_failures( $wcb_bucket );
	wcb_auth_check( 'throttle.clear-releases-lock', ! AppCredentials::is_locked_out( $wcb_bucket ) );
	remove_all_filters( 'wcb_app_password_max_failures' );

	wp_delete_user( $wcb_user_id );
	update_option( 'wcb_settings', $wcb_settings_before );
	wp_cache_flush();
}

$wcb_tally = wcb_auth_check();

echo "\n{$wcb_tally['pass']} passed, {$wcb_tally['fail']} failed\n";

exit( $wcb_tally['fail'] > 0 ? 1 : 0 );
