<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated module name is intentional.
/**
 * Moderation Module — approve / reject job listings via REST endpoints.
 *
 * Routes (registered on rest_api_init):
 *   POST /wcb/v1/jobs/{id}/approve
 *   POST /wcb/v1/jobs/{id}/reject
 *
 * Permission: wcb_moderate_jobs ability (board-scoped moderators + admins).
 * The wcb_expired post status is owned by JobsExpiry — not re-registered here.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Moderation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers REST-based approve / reject endpoints for admin moderation.
 *
 * Extends RestController to inherit shared helpers: check_ability(),
 * permission_error(), and the wcb/v1 REST namespace.
 *
 * @since 1.0.0
 */
class ModerationModule extends \WCB\Api\RestController {

	/**
	 * Boot the module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes for moderation actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		$id_arg = array(
			'description'       => __( 'Job post ID.', 'wp-career-board' ),
			'type'              => 'integer',
			'required'          => true,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
			'minimum'           => 1,
		);

		register_rest_route(
			$this->namespace,
			'/jobs/(?P<id>\d+)/approve',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'approve_job' ),
				'permission_callback' => array( $this, 'moderate_permissions_check' ),
				'args'                => array(
					'id' => $id_arg,
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/jobs/(?P<id>\d+)/reject',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reject_job' ),
				'permission_callback' => array( $this, 'moderate_permissions_check' ),
				'args'                => array(
					'id'     => $id_arg,
					'reason' => array(
						'description'       => __( 'Rejection reason sent to the employer.', 'wp-career-board' ),
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);
	}

	/**
	 * Permission check: user must hold the wcb_moderate_jobs ability.
	 *
	 * Also exposes a `wcb_moderate_jobs_ability_check` filter so
	 * context-scoped moderators (e.g. BuddyPress group admins for jobs
	 * posted to their group's board) can be granted moderation rights
	 * without holding the global cap.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request|null $request Current request, when available.
	 * @return bool|\WP_Error
	 */
	public function moderate_permissions_check( ?\WP_REST_Request $request = null ): bool|\WP_Error {
		if ( $this->check_ability( 'wcb_moderate_jobs' ) ) {
			return true;
		}

		$job_id = $request instanceof \WP_REST_Request ? (int) $request['id'] : 0;
		/**
		 * Filter — allow extensions to grant moderation for a specific job.
		 *
		 * @since 1.1.0
		 *
		 * @param bool $allowed Default false (no global cap).
		 * @param int  $job_id  Target job post id (0 when no job context).
		 */
		if ( (bool) apply_filters( 'wcb_moderate_jobs_ability_check', false, $job_id ) ) {
			return true;
		}

		return $this->permission_error();
	}

	/**
	 * Approve a pending job listing.
	 *
	 * Publishes the job and fires the wcb_job_approved action.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request data.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function approve_job( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$job_id = (int) $request['id'];
		$job    = get_post( $job_id );

		if ( ! $job instanceof \WP_Post || 'wcb_job' !== $job->post_type ) {
			return new \WP_Error(
				'wcb_job_not_found',
				__( 'Job not found.', 'wp-career-board' ),
				array( 'status' => 404 )
			);
		}

		$result = wp_update_post(
			array(
				'ID'          => $job_id,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// wcb_job_approved is fired by EmailJobApproved::on_status_transition()
		// via the transition_post_status hook triggered by wp_update_post() above.
		// No explicit do_action() needed here — firing it twice would send duplicate emails.

		return rest_ensure_response(
			array(
				'id'     => $job_id,
				'status' => 'publish',
			)
		);
	}

	/**
	 * Reject a pending job listing.
	 *
	 * Sets the job to draft, stores the rejection reason, and fires wcb_job_rejected.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request data.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function reject_job( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$job_id = (int) $request['id'];
		$reason = (string) $request->get_param( 'reason' );
		$job    = get_post( $job_id );

		if ( ! $job instanceof \WP_Post || 'wcb_job' !== $job->post_type ) {
			return new \WP_Error(
				'wcb_job_not_found',
				__( 'Job not found.', 'wp-career-board' ),
				array( 'status' => 404 )
			);
		}

		$result = wp_update_post(
			array(
				'ID'          => $job_id,
				'post_status' => 'draft',
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		update_post_meta( $job_id, '_wcb_rejection_reason', $reason );

		/**
		 * Fires after a job listing is rejected by a moderator.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $job_id The rejected job post ID.
		 * @param string $reason The rejection reason provided by the moderator.
		 */
		do_action( 'wcb_job_rejected', $job_id, $reason );

		return rest_ensure_response(
			array(
				'id'     => $job_id,
				'status' => 'draft',
				'reason' => $reason,
			)
		);
	}
}
