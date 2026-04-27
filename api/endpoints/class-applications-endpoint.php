<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
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
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'withdraw_application' ),
					'permission_callback' => array( $this, 'withdraw_permissions_check' ),
				),
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

		// Upload a resume file (Free mode — no wcb_resume post). Requires login.
		register_rest_route(
			$this->namespace,
			'/candidates/resume-upload',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'upload_resume_file' ),
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
			)
		);
	}

	// --- Route callbacks --------------------------------------------------------

	/**
	 * Submit a new application to a job.
	 *
	 * Supports both authenticated candidates and unauthenticated guests.
	 * Guests must supply guest_name + guest_email; a 24-hour duplicate guard
	 * prevents the same email address from applying twice per job.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit_application( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$wcb_spam = apply_filters( 'wcb_pre_application_submit', null, $request );
		if ( is_wp_error( $wcb_spam ) ) {
			return $wcb_spam;
		}

		$job_id   = (int) $request['id'];
		$is_guest = ! is_user_logged_in();

		$job = get_post( $job_id );
		if ( ! $job || 'wcb_job' !== $job->post_type || 'publish' !== $job->post_status ) {
			return new \WP_Error(
				'wcb_job_unavailable',
				__( 'This job is not available.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		if ( $is_guest ) {
			// Guest submission: require name + valid email.
			$guest_name  = sanitize_text_field( (string) ( $request->get_param( 'guest_name' ) ?? '' ) );
			$guest_email = sanitize_email( (string) ( $request->get_param( 'guest_email' ) ?? '' ) );

			if ( ! $guest_name ) {
				return new \WP_Error( 'wcb_guest_name_required', __( 'Name is required.', 'wp-career-board' ), array( 'status' => 400 ) );
			}
			if ( ! is_email( $guest_email ) ) {
				return new \WP_Error( 'wcb_guest_email_invalid', __( 'A valid email address is required.', 'wp-career-board' ), array( 'status' => 400 ) );
			}

			// Duplicate guard: one pending application per guest email + job within 24 h.
			$cutoff   = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
			$existing = get_posts(
				array(
					'post_type'      => 'wcb_application',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'date_query'     => array( array( 'after' => $cutoff ) ),
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'relation' => 'AND',
						array(
							'key'   => '_wcb_job_id',
							'value' => $job_id,
						),
						array(
							'key'   => '_wcb_guest_email',
							'value' => $guest_email,
						),
					),
				)
			);

			if ( $existing ) {
				return new \WP_Error(
					'wcb_already_applied',
					__( 'You have already applied to this job recently.', 'wp-career-board' ),
					array( 'status' => 409 )
				);
			}

			/* translators: 1: guest name, 2: job post ID */
			$post_title = sprintf( __( 'Application: %1$s → Job %2$d', 'wp-career-board' ), $guest_name, $job_id );
		} else {
			$candidate_id = get_current_user_id();

			// Prevent duplicate applications for logged-in candidates.
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

			/* translators: 1: candidate user ID, 2: job post ID */
			$post_title = sprintf( __( 'Application: User %1$d → Job %2$d', 'wp-career-board' ), $candidate_id, $job_id );
		}

		$app_id = wp_insert_post(
			array(
				'post_type'   => 'wcb_application',
				'post_title'  => $post_title,
				'post_status' => 'publish',
				'post_author' => $is_guest ? 0 : $candidate_id,
			),
			true
		);

		if ( is_wp_error( $app_id ) ) {
			return $app_id;
		}

		update_post_meta( $app_id, '_wcb_job_id', $job_id );
		update_post_meta( $app_id, '_wcb_candidate_id', $is_guest ? 0 : $candidate_id );
		update_post_meta(
			$app_id,
			'_wcb_cover_letter',
			sanitize_textarea_field( (string) ( $request->get_param( 'cover_letter' ) ?? '' ) )
		);

		if ( $is_guest ) {
			update_post_meta( $app_id, '_wcb_guest_name', $guest_name );
			update_post_meta( $app_id, '_wcb_guest_email', $guest_email );
		} else {
			// Validate resume belongs to the current candidate before storing.
			$resume_id = (int) $request->get_param( 'resume_id' );
			if ( $resume_id > 0 ) {
				$resume = get_post( $resume_id );
				if ( ! $resume || 'wcb_resume' !== $resume->post_type || $candidate_id !== (int) $resume->post_author ) {
					wp_delete_post( $app_id, true );
					return new \WP_Error(
						'wcb_invalid_resume',
						__( 'Invalid resume.', 'wp-career-board' ),
						array( 'status' => 400 )
					);
				}
			}
			update_post_meta( $app_id, '_wcb_resume_id', $resume_id );
		}

		// Resume file attachment — accepted from both guests and logged-in users
		// either as a multipart upload on this request or as a pre-uploaded
		// attachment id from the legacy /candidates/resume-upload flow.
		$attachment_id = $this->resolve_resume_attachment( $request, $is_guest ? 0 : $candidate_id, $app_id );
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_post( $app_id, true );
			return $attachment_id;
		}
		if ( $attachment_id > 0 ) {
			update_post_meta( $app_id, '_wcb_resume_attachment_id', $attachment_id );
		} elseif ( $this->resume_required() ) {
			wp_delete_post( $app_id, true );
			return new \WP_Error(
				'wcb_resume_required',
				__( 'A resume is required to apply for this job.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		update_post_meta( $app_id, '_wcb_status', 'submitted' );

		do_action( 'wcb_application_submitted', $app_id, $job_id, $is_guest ? 0 : $candidate_id );

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
	public function get_item( $request ): \WP_REST_Response|\WP_Error {
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

		$allowed    = array( 'submitted', 'reviewing', 'shortlisted', 'rejected', 'hired' );
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

		$log   = (array) get_post_meta( $post->ID, '_wcb_status_log', true );
		$log[] = array(
			'from' => $old_status,
			'to'   => $new_status,
			'by'   => get_current_user_id(),
			'at'   => gmdate( 'Y-m-d H:i:s' ),
		);
		update_post_meta( $post->ID, '_wcb_status_log', $log );

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
	 * Returns a frontend-friendly shape: id, jobTitle, jobPermalink, status, date.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response
	 */
	public function get_candidate_applications( \WP_REST_Request $request ): \WP_REST_Response {
		$candidate_id = (int) $request['id'];
		$per_page     = min( (int) ( $request->get_param( 'per_page' ) ?? 20 ), 100 );
		$paged        = max( (int) ( $request->get_param( 'page' ) ?? 1 ), 1 );

		$query = new \WP_Query(
			array(
				'post_type'      => 'wcb_application',
				'post_status'    => 'any',
				'posts_per_page' => $per_page,
				'paged'          => $paged,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_wcb_candidate_id',
						'value' => $candidate_id,
					),
				),
			)
		);

		$items = array();

		// Prime meta cache once for the application page so the per-row
		// get_post_meta() lookups inside the loop hit the object cache.
		$wcb_app_ids = wp_list_pluck( $query->posts, 'ID' );
		if ( ! empty( $wcb_app_ids ) ) {
			update_meta_cache( 'post', $wcb_app_ids );
		}

		foreach ( $query->posts as $app ) {
			$job_id  = (int) get_post_meta( $app->ID, '_wcb_job_id', true );
			$job     = $job_id ? get_post( $job_id ) : null;
			$status  = (string) get_post_meta( $app->ID, '_wcb_status', true );
			$items[] = array(
				'id'           => $app->ID,
				'jobTitle'     => $job instanceof \WP_Post ? $job->post_title : '',
				'jobPermalink' => $job instanceof \WP_Post ? (string) get_permalink( $job_id ) : '',
				'company'      => $job instanceof \WP_Post ? (string) get_post_meta( $job_id, '_wcb_company_name', true ) : '',
				'status'       => $status ? $status : 'submitted',
				'created_at'   => mysql_to_rfc3339( $app->post_date_gmt ),
				'updated_at'   => mysql_to_rfc3339( $app->post_modified_gmt ),
				// Deprecated alias for the legacy `date` key. Removed in 1.2.0.
				'date'         => get_the_date( 'Y-m-d', $app ),
			);
		}

		$total    = (int) $query->found_posts;
		$pages    = (int) $query->max_num_pages;
		$response = rest_ensure_response(
			array(
				'applications' => $items,
				'total'        => $total,
				'pages'        => $pages,
				'has_more'     => $paged < $pages,
			)
		);
		$response->header( 'X-WCB-Total', (string) $total );
		$response->header( 'X-WCB-TotalPages', (string) $pages );
		return $response;
	}

	/**
	 * Withdraw (delete) an application — candidate owner only.
	 *
	 * Respects the allow_withdraw site setting. Fires wcb_application_withdrawn
	 * so other modules (e.g. notifications) can react.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function withdraw_application( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || 'wcb_application' !== $post->post_type ) {
			return new \WP_Error(
				'wcb_not_found',
				__( 'Application not found.', 'wp-career-board' ),
				array( 'status' => 404 )
			);
		}

		$app_id       = $post->ID;
		$candidate_id = (int) get_post_meta( $app_id, '_wcb_candidate_id', true );
		$job_id       = (int) get_post_meta( $app_id, '_wcb_job_id', true );

		wp_delete_post( $app_id, true );
		// Allow add-ons to clean up when an application is permanently deleted.
		do_action( 'wcb_application_deleted', $app_id );

		do_action( 'wcb_application_withdrawn', $app_id, $job_id, $candidate_id );

		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $app_id,
			)
		);
	}

	/**
	 * Upload a resume file (PDF/DOC/DOCX) and return the attachment ID.
	 *
	 * @since 1.0.0
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function upload_resume_file(): \WP_REST_Response|\WP_Error {
		$attachment_id = $this->handle_resume_upload( get_current_user_id(), 0 );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}
		if ( 0 === $attachment_id ) {
			return new \WP_Error(
				'wcb_no_file',
				__( 'No file provided.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}
		return rest_ensure_response( array( 'attachment_id' => $attachment_id ) );
	}

	/**
	 * Resolve the resume attachment for an in-flight application.
	 *
	 * Prefers a freshly uploaded multipart file (atomic — no orphans on
	 * validation failure) and falls back to a pre-uploaded attachment id
	 * supplied by the legacy two-step flow.
	 *
	 * @since 1.1.0
	 *
	 * @param \WP_REST_Request $request   Full request.
	 * @param int              $author_id Owner user id (0 for guests).
	 * @param int              $parent_id Parent application id for the attachment.
	 * @return int|\WP_Error Attachment ID, 0 when none provided, or WP_Error on failure.
	 */
	private function resolve_resume_attachment( \WP_REST_Request $request, int $author_id, int $parent_id ): int|\WP_Error {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by WP REST infrastructure.
		if ( ! empty( $_FILES['resume_file'] ) ) {
			return $this->handle_resume_upload( $author_id, $parent_id );
		}

		$pre_uploaded = (int) $request->get_param( 'resume_attachment_id' );
		if ( $pre_uploaded > 0 ) {
			$attachment = get_post( $pre_uploaded );
			if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
				return new \WP_Error(
					'wcb_invalid_resume',
					__( 'Invalid resume attachment.', 'wp-career-board' ),
					array( 'status' => 400 )
				);
			}
			if ( $author_id > 0 && (int) $attachment->post_author !== $author_id ) {
				return new \WP_Error(
					'wcb_invalid_resume',
					__( 'Invalid resume attachment.', 'wp-career-board' ),
					array( 'status' => 400 )
				);
			}
			return $pre_uploaded;
		}

		return 0;
	}

	/**
	 * Validate $_FILES['resume_file'] and sideload it to the media library.
	 *
	 * @since 1.1.0
	 *
	 * @param int $author_id Attachment owner (0 for guest uploads).
	 * @param int $parent_id Parent post id (0 when uploading standalone).
	 * @return int|\WP_Error
	 */
	private function handle_resume_upload( int $author_id, int $parent_id ): int|\WP_Error {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by WP REST infrastructure.
		if ( empty( $_FILES['resume_file'] ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- media_handle_upload sanitizes internally.
		$file     = $_FILES['resume_file'];
		$mime     = isset( $file['type'] ) ? (string) $file['type'] : '';
		$size     = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$allowed  = array(
			'application/pdf',
			'application/msword',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		);
		$max_mb   = $this->resume_max_mb();
		$max_size = $max_mb * MB_IN_BYTES;

		if ( ! in_array( $mime, $allowed, true ) ) {
			return new \WP_Error(
				'wcb_invalid_file_type',
				__( 'Only PDF, DOC, and DOCX files are allowed.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		if ( $size <= 0 ) {
			return new \WP_Error(
				'wcb_no_file',
				__( 'No file provided.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		if ( $size > $max_size ) {
			return new \WP_Error(
				'wcb_file_too_large',
				/* translators: %d: max file size in MB */
				sprintf( __( 'File must be under %d MB.', 'wp-career-board' ), $max_mb ),
				array( 'status' => 400 )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$overrides = array(
			'test_form' => false,
			'mimes'     => array(
				'pdf'  => 'application/pdf',
				'doc'  => 'application/msword',
				'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			),
		);

		$attachment_id = media_handle_upload( 'resume_file', $parent_id, array(), $overrides );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		if ( $author_id > 0 ) {
			wp_update_post(
				array(
					'ID'          => $attachment_id,
					'post_author' => $author_id,
				)
			);
		}

		return (int) $attachment_id;
	}

	/**
	 * Whether a resume is required to submit an application.
	 *
	 * @since 1.1.0
	 * @return bool
	 */
	private function resume_required(): bool {
		$settings = (array) get_option( 'wcb_settings', array() );
		return ! empty( $settings['apply_resume_required'] );
	}

	/**
	 * Maximum resume size in megabytes (defaults to 5 MB).
	 *
	 * @since 1.1.0
	 * @return int
	 */
	private function resume_max_mb(): int {
		$settings = (array) get_option( 'wcb_settings', array() );
		$mb       = isset( $settings['apply_resume_max_mb'] ) ? (int) $settings['apply_resume_max_mb'] : 5;
		return max( 1, min( 20, $mb ) );
	}

	// --- Permission callbacks ---------------------------------------------------

	/**
	 * Check if the current user can submit an application.
	 *
	 * Guests (unauthenticated) are permitted — guest field validation happens
	 * in submit_application() after the auth check passes.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function submit_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
		// Guests can always apply (no account needed).
		if ( ! is_user_logged_in() ) {
			return true;
		}

		// Logged-in users must have the wcb_apply_jobs ability/cap.
		// This prevents employers from applying to jobs. Admins are granted
		// this ability automatically by Roles::register(), so no manage_options
		// fallback is needed (per CLAUDE.md: Abilities API only).
		if ( $this->check_ability( 'wcb_apply_jobs' ) ) {
			return true;
		}

		return new \WP_Error(
			'wcb_forbidden',
			__( 'You do not have permission to apply for jobs.', 'wp-career-board' ),
			array( 'status' => 403 )
		);
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
	public function get_item_permissions_check( $request ): bool|\WP_Error {
		$post = get_post( (int) $request['id'] );
		if ( ! $post ) {
			return $this->permission_error();
		}
		$current_user_id = get_current_user_id();
		$is_candidate    = $current_user_id > 0 && (int) get_post_meta( $post->ID, '_wcb_candidate_id', true ) === $current_user_id;
		$job_id          = (int) get_post_meta( $post->ID, '_wcb_job_id', true );
		$job             = get_post( $job_id );
		$is_employer     = $job instanceof \WP_Post && get_current_user_id() === (int) $job->post_author;
		$is_admin        = $this->check_ability( 'wcb_manage_settings' );
		return ( $is_candidate || $is_employer || $is_admin ) ? true : $this->permission_error();
	}

	/**
	 * Check if the current user can update an application status.
	 *
	 * Requires wcb_view_applications ability AND that the current user authored
	 * the job the application belongs to (prevents IDOR), or is an admin.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function update_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
		if ( ! $this->check_ability( 'wcb_view_applications' ) ) {
			return $this->permission_error();
		}
		// Admins may update any application.
		if ( $this->check_ability( 'wcb_manage_settings' ) ) {
			return true;
		}
		// Employers may only update applications belonging to their own jobs.
		$app = get_post( (int) $request['id'] );
		if ( ! $app ) {
			return $this->permission_error();
		}
		$job_id   = (int) get_post_meta( $app->ID, '_wcb_job_id', true );
		$job      = get_post( $job_id );
		$is_owner = $job instanceof \WP_Post && get_current_user_id() === (int) $job->post_author;
		return $is_owner ? true : $this->permission_error();
	}

	/**
	 * Check if the current user can withdraw the given application.
	 *
	 * Requires the allow_withdraw setting to be enabled and the current user
	 * to be the candidate who submitted the application.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function withdraw_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
		$settings = (array) get_option( 'wcb_settings', array() );
		if ( empty( $settings['allow_withdraw'] ) ) {
			return new \WP_Error(
				'wcb_withdraw_disabled',
				__( 'Application withdrawal is not enabled on this site.', 'wp-career-board' ),
				array( 'status' => 403 )
			);
		}

		$post = get_post( (int) $request['id'] );
		if ( ! $post ) {
			return $this->permission_error();
		}

		$is_owner = (int) get_post_meta( $post->ID, '_wcb_candidate_id', true ) === get_current_user_id();
		$is_admin = $this->check_ability( 'wcb_manage_settings' );
		return ( $is_owner || $is_admin ) ? true : $this->permission_error();
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
		$status               = (string) get_post_meta( $post->ID, '_wcb_status', true );
		$resume_attachment_id = (int) get_post_meta( $post->ID, '_wcb_resume_attachment_id', true );
		$status_log           = get_post_meta( $post->ID, '_wcb_status_log', true );
		return array(
			'id'             => $post->ID,
			'job_id'         => (int) get_post_meta( $post->ID, '_wcb_job_id', true ),
			'candidate_id'   => (int) get_post_meta( $post->ID, '_wcb_candidate_id', true ),
			'cover_letter'   => (string) get_post_meta( $post->ID, '_wcb_cover_letter', true ),
			'resume_id'      => (int) get_post_meta( $post->ID, '_wcb_resume_id', true ),
			'resume_url'     => $resume_attachment_id ? wp_get_attachment_url( $resume_attachment_id ) : '',
			'status'         => $status ? $status : 'submitted',
			'status_history' => is_array( $status_log ) ? $status_log : array(),
			'submitted_at'   => $post->post_date,
		);
	}
}
