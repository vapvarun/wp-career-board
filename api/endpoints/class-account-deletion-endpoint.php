<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated endpoint name is intentional.
/**
 * REST endpoint for self-service account deletion.
 *
 * Separate from AccountEndpoint (which owns /account profile edits): this owns
 * the destructive /me lifecycle the mobile app calls.
 *
 * Routes:
 *   DELETE /wcb/v1/me            — request deletion (password + confirm)
 *   GET    /wcb/v1/me/deletion   — is a deletion pending?
 *   DELETE /wcb/v1/me/deletion   — cancel a pending deletion
 *
 * @package WP_Career_Board
 * @since   1.7.0
 */

declare( strict_types=1 );

namespace WCB\Api\Endpoints;

use WCB\Api\RestController;
use WCB\Modules\Account\AccountDeletionService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets a member delete, check, and cancel deletion of their own account.
 *
 * @since 1.7.0
 */
class AccountDeletionEndpoint extends RestController {

	/**
	 * Register the account-deletion routes.
	 *
	 * @since 1.7.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/me',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'request_deletion' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
				'args'                => array(
					'password' => array(
						'type'    => 'string',
						'default' => '',
					),
					'confirm'  => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/me/deletion',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_status' ),
					'permission_callback' => array( $this, 'is_logged_in' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'cancel_deletion' ),
					'permission_callback' => array( $this, 'is_logged_in' ),
				),
			)
		);
	}

	/**
	 * DELETE /me — schedule (or immediately perform) deletion.
	 *
	 * @since 1.7.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function request_deletion( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( 'DELETE' !== strtoupper( trim( (string) $request->get_param( 'confirm' ) ) ) ) {
			return new \WP_Error(
				'wcb_delete_confirm_required',
				__( 'Type DELETE to confirm.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		$result = ( new AccountDeletionService() )->request(
			wp_get_current_user(),
			(string) $request->get_param( 'password' )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$status = 'deleted' === $result['status'] ? 200 : 202;
		return new \WP_REST_Response( $result, $status );
	}

	/**
	 * GET /me/deletion — pending status.
	 *
	 * @since 1.7.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function get_status( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( ( new AccountDeletionService() )->status( wp_get_current_user() ), 200 );
	}

	/**
	 * DELETE /me/deletion — cancel. Never gated by the suspension the schedule
	 * applied (the callback checks only login), so the grace period is not a
	 * one-way door.
	 *
	 * @since 1.7.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function cancel_deletion( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( ( new AccountDeletionService() )->cancel( wp_get_current_user() ), 200 );
	}

	/**
	 * Require a logged-in member.
	 *
	 * @since 1.7.0
	 * @return bool|\WP_Error
	 */
	public function is_logged_in(): bool|\WP_Error {
		return is_user_logged_in() ? true : $this->permission_error();
	}
}
