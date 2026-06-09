<?php
/**
 * Account REST endpoint — current-user self-service for display name, email,
 * and password. Shared by the candidate and employer dashboard Account panels.
 *
 * @package WP_Career_Board
 * @since   1.2.0
 */

declare( strict_types=1 );

namespace WCB\Api\Endpoints;

use WCB\Api\RestController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read + update the logged-in user's own account.
 *
 * Always operates on the current user (no id in the route), so a candidate or
 * an employer hits the same endpoint. Writes are cookie-authenticated; core
 * enforces the X-WP-Nonce the dashboards already send.
 */
final class AccountEndpoint extends RestController {

	/**
	 * Register the account routes.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/account',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_account' ),
					'permission_callback' => array( $this, 'logged_in_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_account' ),
					'permission_callback' => array( $this, 'logged_in_check' ),
					'args'                => array(
						'display_name'     => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'email'            => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_email',
						),
						'current_password' => array(
							'type' => 'string',
						),
						'new_password'     => array(
							'type' => 'string',
						),
					),
				),
			)
		);
	}

	/**
	 * Any logged-in user may read/update their own account.
	 *
	 * @since 1.2.0
	 * @return bool|\WP_Error
	 */
	public function logged_in_check(): bool|\WP_Error {
		return is_user_logged_in() ? true : $this->permission_error();
	}

	/**
	 * Return the current user's account fields.
	 *
	 * @since 1.2.0
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_account( \WP_REST_Request $request ): \WP_REST_Response {
		$user = wp_get_current_user();

		return rest_ensure_response(
			array(
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
			)
		);
	}

	/**
	 * Update display name / email / password for the current user.
	 *
	 * Password changes require the current password and re-issue the auth cookie
	 * so the dashboard session survives (wp_update_user() invalidates session
	 * tokens on a password change). A fresh REST nonce is returned for the JS.
	 *
	 * @since 1.2.0
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_account( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );
		$update  = array( 'ID' => $user_id );

		$display_name = $request->get_param( 'display_name' );
		if ( null !== $display_name && '' !== trim( (string) $display_name ) ) {
			$update['display_name'] = trim( (string) $display_name );
		}

		$email = $request->get_param( 'email' );
		if ( null !== $email && (string) $email !== $user->user_email ) {
			if ( ! is_email( $email ) ) {
				return new \WP_Error(
					'wcb_invalid_email',
					__( 'Please enter a valid email address.', 'wp-career-board' ),
					array( 'status' => 400 )
				);
			}
			$existing = email_exists( $email );
			if ( $existing && (int) $existing !== $user_id ) {
				return new \WP_Error(
					'wcb_email_taken',
					__( 'That email address is already in use.', 'wp-career-board' ),
					array( 'status' => 409 )
				);
			}
			$update['user_email'] = $email;
		}

		$new_password = (string) $request->get_param( 'new_password' );
		$password_changed = false;
		if ( '' !== $new_password ) {
			$current = (string) $request->get_param( 'current_password' );
			if ( '' === $current || ! wp_check_password( $current, $user->user_pass, $user_id ) ) {
				return new \WP_Error(
					'wcb_bad_current_password',
					__( 'Your current password is incorrect.', 'wp-career-board' ),
					array( 'status' => 403 )
				);
			}
			if ( strlen( $new_password ) < 8 ) {
				return new \WP_Error(
					'wcb_weak_password',
					__( 'Your new password must be at least 8 characters.', 'wp-career-board' ),
					array( 'status' => 400 )
				);
			}
			$update['user_pass'] = $new_password;
			$password_changed    = true;
		}

		if ( 1 === count( $update ) ) {
			return new \WP_Error(
				'wcb_nothing_to_update',
				__( 'There are no changes to save.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		$result = wp_update_user( $update );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$fresh    = get_userdata( $user_id );
		$response = array(
			'display_name'     => $fresh->display_name,
			'email'            => $fresh->user_email,
			'password_changed' => $password_changed,
		);

		if ( $password_changed ) {
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, true );
			$response['nonce'] = wp_create_nonce( 'wp_rest' );
		}

		return rest_ensure_response( $response );
	}
}
