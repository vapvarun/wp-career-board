<?php
/**
 * Current-password check on core's user REST route.
 *
 * The dashboards change a member's email or password through POST
 * /wcb/v1/account, which asks for the current password so a hijacked session
 * cannot swap the email and then reset the password. WordPress's own
 * /wp/v2/users/me changes both without asking, so the check is repeated here
 * for that route.
 *
 * It runs on `rest_request_before_callbacks`, not `rest_pre_insert_user`: core
 * does not look at an error returned from that filter when updating a user, it
 * casts it to an array and answers 200 having changed nothing.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Account;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Requires the current password to change one's own email or password over REST.
 */
final class CredentialGuard {

	/**
	 * Hook in before core's user routes run their callbacks.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_filter( 'rest_request_before_callbacks', array( $this, 'require_current_password' ), 10, 3 );
	}

	/**
	 * Refuse a self-service email or password change without the current password.
	 *
	 * Changing someone else's account (core's capability check gates that) and
	 * administrators are left to core.
	 *
	 * @param mixed            $response Result so far (a \WP_Error stops the request).
	 * @param array            $handler  Matched route handler.
	 * @param \WP_REST_Request $request  Request being dispatched.
	 * @return mixed
	 */
	public function require_current_password( $response, $handler, \WP_REST_Request $request ) {
		unset( $handler );

		if ( is_wp_error( $response ) || ! in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			return $response;
		}
		// WordPress matches routes case-insensitively, so /wp/v2/Users/me reaches the same handler.
		if ( ! preg_match( '#^/wp/v2/users/(me|\d+)$#i', $request->get_route(), $target ) ) {
			return $response;
		}

		$user_id = 'me' === strtolower( $target[1] ) ? get_current_user_id() : (int) $target[1];
		if ( $user_id <= 0 || get_current_user_id() !== $user_id || wp_is_ability_granted( 'wcb/manage-settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
			return $response;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return $response;
		}

		$email            = $request->get_param( 'email' );
		$password         = $request->get_param( 'password' );
		$email_changed    = is_string( $email ) && '' !== $email && strtolower( $email ) !== strtolower( $user->user_email );
		$password_changed = is_string( $password ) && '' !== $password;
		if ( ! $email_changed && ! $password_changed ) {
			return $response;
		}

		$current = (string) $request->get_param( 'current_password' );
		if ( '' === $current || ! wp_check_password( $current, $user->user_pass, $user_id ) ) {
			return new \WP_Error(
				'wcb_bad_current_password',
				__( 'Enter your current password to change your email or password.', 'wp-career-board' ),
				array( 'status' => 403 )
			);
		}

		return $response;
	}
}
