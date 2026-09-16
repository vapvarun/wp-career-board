<?php
/**
 * Auth REST endpoint — how the mobile app gets its first credential.
 *
 * Routes:
 *   POST   /wcb/v1/auth/app-password — trade a WordPress login for a WP core
 *   Application Password.
 *   DELETE /wcb/v1/auth/app-password — revoke the credential making the
 *   request, so signing out of the app actually ends the credential rather
 *   than only forgetting it on the device.
 *
 * Every guard lives in `WCB\Auth\AppCredentials`; this endpoint owns only the
 * request shape, the throttle that must run BEFORE a credential is read, and
 * the `no-store` response.
 *
 * @package WP_Career_Board
 * @since   1.7.2
 */

declare( strict_types=1 );

namespace WCB\Api\Endpoints;

use WCB\Api\RestController;
use WCB\Auth\AppCredentials;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves the app-password exchange.
 *
 * @since 1.7.2
 */
final class AuthEndpoint extends RestController {

	/**
	 * Register auth routes.
	 *
	 * @since 1.7.2
	 * @return void
	 */
	public function register_routes(): void {
		// The member's FIRST credential, so there is nothing to authenticate
		// with yet and the route is public by necessity. Core will not do this
		// exchange: its Basic auth accepts application passwords only, so the
		// core route that mints them already requires one, and every core path
		// to a first credential runs through wp-admin.
		//
		// Guarded in AppCredentials: owner switch, TLS gate, the
		// scheduled-deletion refusal, uniform failures, and a 409 rather than
		// a silent 2FA bypass. Rate limiting happens in the callback BEFORE
		// any credential is read.
		register_rest_route(
			$this->namespace,
			'/auth/app-password',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'issue_app_password' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'username' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => __( 'Your email address or username.', 'wp-career-board' ),
					),
					'password' => array(
						'required'    => true,
						'type'        => 'string',
						'description' => __( 'Your account password.', 'wp-career-board' ),
					),
					'app_name' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => __( 'Name shown beside this credential in your profile.', 'wp-career-board' ),
					),
					'app_id'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => __( 'Stable per-install id, so a repeat sign-in replaces the row instead of adding another.', 'wp-career-board' ),
					),
				),
			)
		);

		// The other half of minting. Without this the app could create a
		// credential but never destroy one: signing out cleared the phone and
		// left the Application Password live on the server forever, so a lost
		// device stayed authorised until someone thought to open
		// wp-admin > Profile and delete the row by hand. Members do not do
		// that, which meant every sign-out quietly grew the set of live
		// credentials on the account.
		register_rest_route(
			$this->namespace,
			'/auth/app-password',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'revoke_app_password' ),
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
			)
		);
	}

	/**
	 * DELETE /auth/app-password - revoke the credential making this request.
	 *
	 * Deliberately takes no id. The only credential a client may revoke here is
	 * the one it is authenticating with, which core hands us directly - so
	 * there is no object reference to get wrong and no way to aim this at
	 * somebody else's row.
	 *
	 * Succeeds when there is nothing to revoke. The app calls this on the way
	 * out and must not be left holding a credential it believes is live because
	 * the response was an error; a cookie-authenticated caller has no app
	 * password to begin with, and that is not a failure either.
	 *
	 * @since 1.7.2
	 *
	 * @return \WP_REST_Response
	 */
	public function revoke_app_password(): \WP_REST_Response {
		$user_id = get_current_user_id();
		$used    = function_exists( 'rest_get_authenticated_app_password' )
			? rest_get_authenticated_app_password()
			: '';

		$revoked = false;

		if ( is_string( $used ) && '' !== $used ) {
			$revoked = true === \WP_Application_Passwords::delete_application_password( $user_id, $used );

			if ( $revoked ) {
				/**
				 * Fires after a member revokes their own app credential.
				 *
				 * @since 1.7.2
				 *
				 * @param int    $user_id Member who signed out.
				 * @param string $uuid    The credential that was deleted.
				 */
				do_action( 'wcb_app_credential_revoked', $user_id, $used );
			}
		}

		$response = new \WP_REST_Response( array( 'revoked' => $revoked ), 200 );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	/**
	 * POST /auth/app-password — hand back the credential the app will hold.
	 *
	 * The account password is read from the request, passed straight to
	 * `wp_authenticate()`, and never stored, logged or echoed. Only the minted
	 * Application Password comes back, with `no-store` so nothing on the path
	 * keeps a copy.
	 *
	 * @since 1.7.2
	 *
	 * @param \WP_REST_Request $request Full request data.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function issue_app_password( \WP_REST_Request $request ) {
		// Throttle BEFORE a credential is read. Two failure buckets, because
		// one alone is not enough: the IP bucket stops one host grinding
		// through passwords, the username bucket stops a distributed run at a
		// single account. Only rejected CREDENTIALS count against them.
		$username = (string) $request->get_param( 'username' );
		$ip       = AppCredentials::client_ip();
		$buckets  = array( 'ip:' . $ip, 'user:' . strtolower( $username ) );

		foreach ( $buckets as $bucket ) {
			if ( AppCredentials::is_locked_out( $bucket ) ) {
				return new \WP_Error(
					'wcb_too_many_attempts',
					__( 'Too many sign-in attempts. Please wait a few minutes and try again.', 'wp-career-board' ),
					array( 'status' => 429 )
				);
			}
		}

		// The per-IP ceiling on TOTAL attempts, so a slow probe that never
		// trips the failure lockout is still bounded.
		if ( ! AppCredentials::record_attempt( $ip ) ) {
			return new \WP_Error(
				'wcb_too_many_attempts',
				__( 'Too many sign-in attempts. Please wait a few minutes and try again.', 'wp-career-board' ),
				array( 'status' => 429 )
			);
		}

		$app_name = (string) $request->get_param( 'app_name' );
		$app_id   = (string) $request->get_param( 'app_id' );

		$result = AppCredentials::exchange(
			$username,
			(string) $request->get_param( 'password' ),
			'' !== $app_name ? $app_name : __( 'Career Board app', 'wp-career-board' ),
			$app_id
		);

		if ( is_wp_error( $result ) ) {
			if ( 'wcb_login_failed' === $result->get_error_code() ) {
				foreach ( $buckets as $bucket ) {
					AppCredentials::record_failure( $bucket );
				}
			}

			return $result;
		}

		foreach ( $buckets as $bucket ) {
			AppCredentials::clear_failures( $bucket );
		}

		$response = new \WP_REST_Response( $result, 200 );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}
}
