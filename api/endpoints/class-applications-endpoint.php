<?php
/**
 * Applications REST endpoint — submit, view, update status, candidate history.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Api\Endpoints;

use WCB\Api\RestController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles /wcb/v1/jobs/{id}/apply and /wcb/v1/applications/* REST routes.
 *
 * Enforces the Abilities API for all permission checks — no raw
 * current_user_can() except the wcb_manage_settings fallback provided by
 * RestController::check_ability().
 *
 * @since 1.0.0
 */
final class ApplicationsEndpoint extends RestController {

	/**
	 * Register all application routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		// Submit application to a job.
		register_rest_route(
			$this->namespace,
			'/jobs/(?P<id>\d+)/apply',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit_application' ),
				'permission_callback' => array( $this, 'submit_permissions_check' ),
			)
		);

		// Single application — candidate or employer owning the job.
		register_rest_route(
			$this->namespace,
			'/applications/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
			)
		);

		// Update application status — employer or admin.
		register_rest_route(
			$this->namespace,
			'/applications/(?P<id>\d+)/status',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_status' ),
				'permission_callback' => array( $this, 'update_permissions_check' ),
			)
		);

		// All applications for a specific candidate.
		register_rest_route(
			$this->namespace,
			'/candidates/(?P<id>\d+)/applications',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_candidate_applications' ),
				'permission_callback' => array( $this, 'candidate_permissions_check' ),
			)
		);
	}

	// --- Route callbacks --------------------------------------------------------

	/**
	 * Submit a new application to a job.
	 *
	 * Prevents duplicate applications (one per candidate per job).
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit_application( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$job_id       = (int) $request['id'];
		$candidate_id = get_current_user_id();

		$job = get_post( $job_id );
		if ( ! $job || 'wcb_job' !== $job->post_type || 'publish' !== $job->post_status ) {
			return new \WP_Error(
				'wcb_job_unavailable',
				__( 'This job is not available.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		// Prevent duplicate applications.
		$existing = get_posts(
			array(
				'post_type'      => 'wcb_application',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => '_wcb_job_id',
						'value' => $job_id,
					),
					array(
						'key'   => '_wcb_candidate_id',
						'value' => $candidate_id,
					),
				),
			)
		);
		if ( $existing ) {
			return new \WP_Error(
				'wcb_already_applied',
				__( 'You have already applied to this job.', 'wp-career-board' ),
				array( 'status' => 409 )
			);
		}

		$app_id = wp_insert_post(
			array(
				'post_type'   => 'wcb_application',
				/* translators: 1: candidate user ID, 2: job post ID */
				'post_title'  => sprintf( __( 'Application: User %1$d → Job %2$d', 'wp-career-board' ), $candidate_id, $job_id ),
				'post_status' => 'publish',
				'post_author' => $candidate_id,
			),
			true
		);

		if ( is_wp_error( $app_id ) ) {
			return $app_id;
		}

		update_post_meta( $app_id, '_wcb_job_id', $job_id );
		update_post_meta( $app_id, '_wcb_candidate_id', $candidate_id );
		update_post_meta(
			$app_id,
			'_wcb_cover_letter',
			sanitize_textarea_field( (string) ( $request->get_param( 'cover_letter' ) ?? '' ) )
		);
		update_post_meta( $app_id, '_wcb_resume_id', (int) $request->get_param( 'resume_id' ) );
		update_post_meta( $app_id, '_wcb_status', 'submitted' );

		do_action( 'wcb_application_submitted', $app_id, $job_id, $candidate_id );

		return rest_ensure_response(
			array(
				'id'     => $app_id,
				'job_id' => $job_id,
				'status' => 'submitted',
			)
		);
	}

	/**
	 * Retrieve a single application.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || 'wcb_application' !== $post->post_type ) {
			return new \WP_Error(
				'wcb_not_found',
				__( 'Application not found.', 'wp-career-board' ),
				array( 'status' => 404 )
			);
		}
		return rest_ensure_response( $this->prepare_application( $post ) );
	}

	/**
	 * Update the status of an application.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_status( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || 'wcb_application' !== $post->post_type ) {
			return new \WP_Error(
				'wcb_not_found',
				__( 'Application not found.', 'wp-career-board' ),
				array( 'status' => 404 )
			);
		}

		$allowed    = array( 'submitted', 'reviewed', 'closed' );
		$new_status = sanitize_text_field( (string) $request->get_param( 'status' ) );
		if ( ! in_array( $new_status, $allowed, true ) ) {
			return new \WP_Error(
				'wcb_invalid_status',
				__( 'Invalid status.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		$old_status = (string) get_post_meta( $post->ID, '_wcb_status', true );
		update_post_meta( $post->ID, '_wcb_status', $new_status );

		do_action( 'wcb_application_status_changed', $post->ID, $old_status, $new_status );

		return rest_ensure_response(
			array(
				'id'     => $post->ID,
				'status' => $new_status,
			)
		);
	}

	/**
	 * List all applications for a specific candidate.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response
	 */
	public function get_candidate_applications( \WP_REST_Request $request ): \WP_REST_Response {
		$candidate_id = (int) $request['id'];
		$posts        = get_posts(
			array(
				'post_type'      => 'wcb_application',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_wcb_candidate_id',
						'value' => $candidate_id,
					),
				),
			)
		);
		return rest_ensure_response( array_map( array( $this, 'prepare_application' ), $posts ) );
	}

	// --- Permission callbacks ---------------------------------------------------

	/**
	 * Check if the current user can submit an application.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function submit_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
		return $this->check_ability( 'wcb_apply_jobs' ) ? true : $this->permission_error();
	}

	/**
	 * Check if the current user can view the given application.
	 *
	 * Allows: the candidate who submitted, the employer who owns the job, or an admin.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function get_item_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
		$post = get_post( (int) $request['id'] );
		if ( ! $post ) {
			return $this->permission_error();
		}
		$is_candidate = (int) get_post_meta( $post->ID, '_wcb_candidate_id', true ) === get_current_user_id();
		$job_id       = (int) get_post_meta( $post->ID, '_wcb_job_id', true );
		$job          = get_post( $job_id );
		$is_employer  = $job instanceof \WP_Post && get_current_user_id() === (int) $job->post_author;
		$is_admin     = $this->check_ability( 'wcb_manage_settings' );
		return ( $is_candidate || $is_employer || $is_admin ) ? true : $this->permission_error();
	}

	/**
	 * Check if the current user can update an application status.
	 *
	 * Only users with the wcb_view_applications ability (employers, admins) may change status.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function update_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
		return $this->check_ability( 'wcb_view_applications' ) ? true : $this->permission_error();
	}

	/**
	 * Check if the current user can list a candidate's applications.
	 *
	 * Allows the candidate themselves or an admin.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function candidate_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
		$same_user = get_current_user_id() === (int) $request['id'];
		$is_admin  = $this->check_ability( 'wcb_manage_settings' );
		return ( $same_user || $is_admin ) ? true : $this->permission_error();
	}

	// --- Helpers ----------------------------------------------------------------

	/**
	 * Shape a WP_Post (wcb_application) into the REST response array.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Application post object.
	 * @return array<string, mixed>
	 */
	private function prepare_application( \WP_Post $post ): array {
		$status = (string) get_post_meta( $post->ID, '_wcb_status', true );
		return array(
			'id'           => $post->ID,
			'job_id'       => (int) get_post_meta( $post->ID, '_wcb_job_id', true ),
			'candidate_id' => (int) get_post_meta( $post->ID, '_wcb_candidate_id', true ),
			'cover_letter' => (string) get_post_meta( $post->ID, '_wcb_cover_letter', true ),
			'resume_id'    => (int) get_post_meta( $post->ID, '_wcb_resume_id', true ),
			'status'       => $status ? $status : 'submitted',
			'submitted_at' => $post->post_date,
		);
	}
}
