<?php
/**
 * Setup wizard settings-step tests (1.8.0).
 *
 * Run: wp eval-file wp-content/plugins/wp-career-board/tests/test-wizard-settings.php
 *
 * Pins: POST /wcb/v1/wizard/settings saves through the settings schema,
 * merges over what is stored, drops unknown keys, writes WordPress's
 * users_can_register, and is refused to non-admins.
 *
 * @package WP_Career_Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$GLOBALS['wcb_wz_pass'] = 0;
$GLOBALS['wcb_wz_fail'] = 0;

/**
 * Assert a condition and log the result.
 *
 * @param bool   $condition Test condition.
 * @param string $label     Human-readable test label.
 * @return void
 */
function wcb_wz_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		++$GLOBALS['wcb_wz_pass'];
		WP_CLI::log( "  PASS: {$label}" );
	} else {
		++$GLOBALS['wcb_wz_fail'];
		WP_CLI::log( "  FAIL: {$label}" );
	}
}

/**
 * Dispatch a REST request as a given user.
 *
 * @param string   $method  HTTP method.
 * @param string   $route   Route.
 * @param array    $params  Body params.
 * @param int|null $user_id User to act as (null keeps the current one).
 * @return WP_REST_Response
 */
function wcb_rest( string $method, string $route, array $params = array(), ?int $user_id = null ): WP_REST_Response {
	if ( null !== $user_id ) {
		wp_set_current_user( $user_id );
	}
	$request = new WP_REST_Request( $method, $route );
	$request->set_body_params( $params );
	return rest_do_request( $request );
}

WP_CLI::log( '=== Setup wizard settings step ===' );

$wcb_wz_settings = get_option( 'wcb_settings', array() );
$wcb_wz_register = get_option( 'users_can_register' );
$wcb_wz_admin    = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ids',
	)
);

update_option(
	'wcb_settings',
	array(
		'max_resumes'       => 3,
		'jobs_archive_page' => 9,
		'salary_currency'   => 'USD',
	)
);
update_option( 'users_can_register', 0 );
\WCB\Admin\Settings::flush_cache();

// Anonymous: refused, nothing written.
$wcb_wz_res = wcb_rest( 'POST', '/wcb/v1/wizard/settings', array( 'settings' => array( 'salary_currency' => 'EUR' ) ), 0 );
wcb_wz_assert( in_array( $wcb_wz_res->get_status(), array( 401, 403 ), true ), 'anonymous caller is refused' );
wcb_wz_assert( 'USD' === get_option( 'wcb_settings' )['salary_currency'], 'refused call writes nothing' );

$wcb_wz_res = wcb_rest(
	'POST',
	'/wcb/v1/wizard/settings',
	array(
		'settings' => array(
			'users_can_register'         => 1,
			'require_email_verification' => 1,
			'jobs_expire_days'           => '45',
			'salary_currency'            => 'eur',
			'not_a_setting'              => 'x',
		),
	),
	(int) $wcb_wz_admin[0]
);
$wcb_wz_after = get_option( 'wcb_settings' );

wcb_wz_assert( 200 === $wcb_wz_res->get_status(), 'admin save returns 200' );
wcb_wz_assert( '1' === (string) get_option( 'users_can_register' ), 'users_can_register is written to the WordPress option' );
wcb_wz_assert( true === $wcb_wz_after['require_email_verification'], 'checkbox 1 saves as true' );
wcb_wz_assert( 45 === $wcb_wz_after['jobs_expire_days'], 'number is cleaned to int' );
wcb_wz_assert( 'EUR' === $wcb_wz_after['salary_currency'], 'currency is cleaned against the catalog' );
wcb_wz_assert( ! array_key_exists( 'not_a_setting', $wcb_wz_after ), 'unknown key is dropped' );
wcb_wz_assert( ! array_key_exists( 'users_can_register', $wcb_wz_after ), 'users_can_register does not leak into wcb_settings' );
wcb_wz_assert( 3 === (int) $wcb_wz_after['max_resumes'] && 9 === (int) $wcb_wz_after['jobs_archive_page'], 'keys the step did not send are kept' );

// Unticked box: the step sends 0, which must save false, not be ignored.
wcb_rest( 'POST', '/wcb/v1/wizard/settings', array( 'settings' => array( 'require_email_verification' => 0 ) ) );
wcb_wz_assert( false === get_option( 'wcb_settings' )['require_email_verification'], 'checkbox 0 saves as false' );

update_option( 'wcb_settings', $wcb_wz_settings );
update_option( 'users_can_register', $wcb_wz_register );
\WCB\Admin\Settings::flush_cache();

WP_CLI::log( '' );
WP_CLI::log( sprintf( '=== Results: %d passed, %d failed ===', (int) $GLOBALS['wcb_wz_pass'], (int) $GLOBALS['wcb_wz_fail'] ) );
if ( (int) $GLOBALS['wcb_wz_fail'] > 0 ) {
	WP_CLI::error( 'Wizard settings tests failed.' );
}
