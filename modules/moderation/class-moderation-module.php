<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated module name is intentional.
/**
 * Moderation Module — approve / reject / report job listings via REST endpoints.
 *
 * Routes (registered on rest_api_init):
 *   POST /wcb/v1/jobs/{id}/approve        — wcb_moderate_jobs
 *   POST /wcb/v1/jobs/{id}/reject         — wcb_moderate_jobs
 *   POST /wcb/v1/jobs/{id}/report         — any logged-in user (anti-spam flag)
 *   POST /wcb/v1/jobs/{id}/resolve-flag   — wcb_moderate_jobs
 *
 * Permission: wcb_moderate_jobs ability (moderators + admins) for approve /
 * reject / resolve; report is open to any authenticated visitor.
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

		register_rest_route(
			$this->namespace,
			'/jobs/(?P<id>\d+)/report',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'report_job' ),
				'permission_callback' => array( $this, 'report_permissions_check' ),
				'args'                => array(
					'id'     => $id_arg,
					'reason' => array(
						'description'       => __( 'Why the visitor is reporting this job.', 'wp-career-board' ),
						'type'              => 'string',
						'required'          => true,
						'enum'              => array_keys( self::report_reasons() ),
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/jobs/(?P<id>\d+)/resolve-flag',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'resolve_flag' ),
				'permission_callback' => array( $this, 'moderate_permissions_check' ),
				'args'                => array(
					'id'     => $id_arg,
					'action' => array(
						'description'       => __( 'How to resolve the flag: dismiss the reports or unpublish the job.', 'wp-career-board' ),
						'type'              => 'string',
						'default'           => 'dismiss',
						'enum'              => array( 'dismiss', 'unpublish' ),
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);
	}

	/**
	 * Reason slugs a visitor can pick when reporting a job, with labels.
	 *
	 * Single source of truth shared by REST validation, the job-single
	 * report control, and the admin flagged-jobs column.
	 *
	 * @since 1.2.1
	 *
	 * @return array<string,string> Slug => translated label.
	 */
	public static function report_reasons(): array {
		return array(
			'scam'       => __( 'Scam or fraudulent', 'wp-career-board' ),
			'spam'       => __( 'Spam or advertisement', 'wp-career-board' ),
			'expired'    => __( 'Expired or already filled', 'wp-career-board' ),
			'inaccurate' => __( 'Inaccurate or misleading', 'wp-career-board' ),
			'offensive'  => __( 'Offensive or inappropriate', 'wp-career-board' ),
		);
	}

	/**
	 * Permission check: user must hold the wcb_moderate_jobs ability.
	 *
	 * Also exposes a `wcb_moderate_jobs_ability_check` filter so an extension
	 * can grant per-job moderation rights (given the target job id) without
	 * the global cap. Defaults false — no scoping is enforced out of the box.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request|null $request Current request, when available.
	 * @return bool|\WP_Error
	 */
	public function moderate_permissions_check( ?\WP_REST_Request $request = null ): bool|\WP_Error {
		if ( $this->check_ability( 'wcb/moderate-jobs' ) ) {
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

	/**
	 * Permission check for reporting: any authenticated visitor.
	 *
	 * REST cookie auth already requires a valid nonce, so a logged-in check
	 * is sufficient — anonymous/forged requests resolve to user 0 and 401.
	 *
	 * @since 1.2.1
	 *
	 * @return bool|\WP_Error
	 */
	public function report_permissions_check(): bool|\WP_Error {
		return is_user_logged_in() ? true : $this->permission_error();
	}

	/**
	 * Report a published job (anti-spam flag).
	 *
	 * One report per user per job — repeat reports are idempotent. Flags are
	 * stored on the job as postmeta and surface in the admin Jobs queue's
	 * "Flagged" view; the job stays live until a moderator resolves it.
	 *
	 * @since 1.2.1
	 *
	 * @param \WP_REST_Request $request Full request data.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function report_job( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$job_id = (int) $request['id'];
		$reason = (string) $request->get_param( 'reason' );
		$job    = get_post( $job_id );

		if ( ! $job instanceof \WP_Post || 'wcb_job' !== $job->post_type || 'publish' !== $job->post_status ) {
			return new \WP_Error(
				'wcb_job_not_found',
				__( 'Job not found.', 'wp-career-board' ),
				array( 'status' => 404 )
			);
		}

		$user_id        = $this->current_user_id();
		$reporters_meta = get_post_meta( $job_id, '_wcb_flag_reporters', true );
		$reporters      = is_array( $reporters_meta ) ? array_map( 'intval', $reporters_meta ) : array();

		if ( in_array( $user_id, $reporters, true ) ) {
			return rest_ensure_response(
				array(
					'id'               => $job_id,
					'count'            => count( $reporters ),
					'already_reported' => true,
				)
			);
		}

		$reporters[]        = $user_id;
		$reasons_meta       = get_post_meta( $job_id, '_wcb_flag_reasons', true );
		$reasons            = is_array( $reasons_meta ) ? $reasons_meta : array();
		$reasons[ $reason ] = (int) ( $reasons[ $reason ] ?? 0 ) + 1;

		update_post_meta( $job_id, '_wcb_flag_reporters', $reporters );
		update_post_meta( $job_id, '_wcb_flag_reasons', $reasons );
		update_post_meta( $job_id, '_wcb_flag_count', count( $reporters ) );
		update_post_meta( $job_id, '_wcb_flag_status', 'open' );

		/**
		 * Fires after a visitor reports a job listing.
		 *
		 * @since 1.2.1
		 *
		 * @param int    $job_id  The reported job post ID.
		 * @param string $reason  Reason slug (see ModerationModule::report_reasons()).
		 * @param int    $user_id Reporting user ID.
		 */
		do_action( 'wcb_job_reported', $job_id, $reason, $user_id );

		return rest_ensure_response(
			array(
				'id'               => $job_id,
				'count'            => count( $reporters ),
				'already_reported' => false,
			)
		);
	}

	/**
	 * Resolve the flags on a reported job.
	 *
	 * @since 1.2.1
	 *
	 * @param \WP_REST_Request $request Full request data.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function resolve_flag( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$job_id = (int) $request['id'];
		$action = (string) $request->get_param( 'action' );
		$job    = get_post( $job_id );

		if ( ! $job instanceof \WP_Post || 'wcb_job' !== $job->post_type ) {
			return new \WP_Error(
				'wcb_job_not_found',
				__( 'Job not found.', 'wp-career-board' ),
				array( 'status' => 404 )
			);
		}

		$result = self::resolve_job_flags( $job_id, $action );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'id'     => $job_id,
				'action' => $action,
				'status' => get_post_status( $job_id ),
			)
		);
	}

	/**
	 * Clear a job's report flags, optionally unpublishing it.
	 *
	 * Shared by the resolve-flag REST route and the admin Jobs queue so both
	 * surfaces apply identical logic. `dismiss` keeps the job live; `unpublish`
	 * sends it back to pending for the employer to revise.
	 *
	 * @since 1.2.1
	 *
	 * @param int    $job_id Job post ID.
	 * @param string $action Either 'dismiss' or 'unpublish'.
	 * @return true|\WP_Error
	 */
	public static function resolve_job_flags( int $job_id, string $action ) {
		if ( 'unpublish' === $action ) {
			$updated = wp_update_post(
				array(
					'ID'          => $job_id,
					'post_status' => 'pending',
				),
				true
			);
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}

		delete_post_meta( $job_id, '_wcb_flag_reporters' );
		delete_post_meta( $job_id, '_wcb_flag_reasons' );
		delete_post_meta( $job_id, '_wcb_flag_count' );
		update_post_meta( $job_id, '_wcb_flag_status', 'resolved' );

		/**
		 * Fires after a moderator resolves the flags on a job.
		 *
		 * @since 1.2.1
		 *
		 * @param int    $job_id Job post ID.
		 * @param string $action Either 'dismiss' or 'unpublish'.
		 */
		do_action( 'wcb_job_flag_resolved', $job_id, $action );

		return true;
	}
}
