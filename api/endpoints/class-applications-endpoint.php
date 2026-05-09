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
	 * @since  1.0.0
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
	 * @param  \WP_REST_Request $request Full request object.
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

		$wcb_app_data = array(
			'post_type'   => 'wcb_application',
			'post_title'  => $post_title,
			'post_status' => 'publish',
			'post_author' => $is_guest ? 0 : $candidate_id,
		);

		/**
		 * Filter — abort or modify an application-create write before it happens.
		 *
		 * Return WP_Error to abort (e.g. fail anti-spam check). Return the
		 * (possibly modified) post-data array to continue.
		 *
		 * @since 1.1.1
		 *
		 * @param array            $post_data    wp_insert_post arg array.
		 * @param int              $job_id       The job being applied to.
		 * @param int              $candidate_id The applying user (0 for guest).
		 * @param \WP_REST_Request $request      The originating REST request.
		 */
		$wcb_app_data = apply_filters( 'wcb_before_create_application', $wcb_app_data, $job_id, $is_guest ? 0 : $candidate_id, $request );
		if ( is_wp_error( $wcb_app_data ) ) {
			return $wcb_app_data;
		}

		$app_id = wp_insert_post( $wcb_app_data, true );

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

		$resume_id = 0;

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
		} elseif ( $resume_id > 0 && $this->resume_required() ) {
			// Candidate picked a saved resume but it has no PDF attached yet —
			// happens when the resume was created in the manual builder but
			// never exported. Tell the candidate exactly what to do next
			// instead of letting the application land with a blank file.
			wp_delete_post( $app_id, true );
			return new \WP_Error(
				'wcb_resume_no_pdf',
				__( "This resume doesn't have a PDF attached yet. Open it in the resume builder and use 'Download as PDF' (or upload a file below) before applying.", 'wp-career-board' ),
				array( 'status' => 400 )
			);
		} elseif ( $this->resume_required() && $resume_id <= 0 ) {
			wp_delete_post( $app_id, true );
			return new \WP_Error(
				'wcb_resume_required',
				__( 'A resume is required to apply for this job.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		update_post_meta( $app_id, '_wcb_status', 'submitted' );

		// Custom application fields registered via wcb_application_form_fields_groups
		// filter. The job-single block's view.js captures values into state.customFields
		// as the user types and POSTs them as custom_fields[<key>] = <value>. We
		// validate every submitted key against the active filter output (so a
		// hand-crafted POST can't write arbitrary postmeta) and persist per-key
		// as `_wcb_application_field_<key>`. Closes Basecamp 9874915447.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- REST nonce checked by infrastructure.
		$wcb_custom_input = isset( $_POST['custom_fields'] ) && is_array( $_POST['custom_fields'] )
			? wp_unslash( $_POST['custom_fields'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- per-key sanitized below.
			: array();

		if ( ! empty( $wcb_custom_input ) ) {
			$wcb_field_groups = (array) apply_filters( 'wcb_application_form_fields_groups', array(), $job_id );
			$wcb_known_fields = array();
			foreach ( $wcb_field_groups as $wcb_group ) {
				foreach ( (array) ( $wcb_group['fields'] ?? array() ) as $wcb_field ) {
					$wcb_field_key = (string) ( $wcb_field['key'] ?? '' );
					if ( '' !== $wcb_field_key ) {
						$wcb_known_fields[ $wcb_field_key ] = (string) ( $wcb_field['type'] ?? 'text' );
					}
				}
			}

			$wcb_persisted = array();
			foreach ( $wcb_custom_input as $wcb_key => $wcb_value ) {
				$wcb_key = (string) $wcb_key;
				if ( ! isset( $wcb_known_fields[ $wcb_key ] ) ) {
					continue; // Drop keys the active filter doesn't declare.
				}
				$wcb_clean = match ( $wcb_known_fields[ $wcb_key ] ) {
					'textarea' => sanitize_textarea_field( (string) $wcb_value ),
					'email'    => sanitize_email( (string) $wcb_value ),
					'url'      => esc_url_raw( (string) $wcb_value ),
					'number'   => (string) (float) $wcb_value,
					default    => sanitize_text_field( (string) $wcb_value ),
				};
				update_post_meta( $app_id, '_wcb_application_field_' . sanitize_key( $wcb_key ), $wcb_clean );
				$wcb_persisted[ $wcb_key ] = $wcb_clean;
			}
			if ( ! empty( $wcb_persisted ) ) {
				update_post_meta( $app_id, '_wcb_application_custom_fields', $wcb_persisted );
			}
		}

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
	 * @param  \WP_REST_Request $request Full request object.
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
		return rest_ensure_response( $this->prepare_application( $post, $request ) );
	}

	/**
	 * Update the status of an application.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request Full request object.
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
	 * @param  \WP_REST_Request $request Full request object.
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
			$job_id = (int) get_post_meta( $app->ID, '_wcb_job_id', true );
			$job    = $job_id ? get_post( $job_id ) : null;
			$status = (string) get_post_meta( $app->ID, '_wcb_status', true );
			$row    = array(
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

			/** This filter is documented in api/endpoints/class-applications-endpoint.php */
			$items[] = (array) apply_filters( 'wcb_rest_prepare_application', $row, $app, $request, 'candidate' );
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
	 * @param  \WP_REST_Request $request Full request object.
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
	 * @since  1.0.0
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
	 * @param  \WP_REST_Request $request   Full request.
	 * @param  int              $author_id Owner user id (0 for guests).
	 * @param  int              $parent_id Parent application id for the attachment.
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

		// Fall back to the attachment stored on the selected resume CPT post.
		// Without this, picking a saved resume leaves the application's
		// _wcb_resume_attachment_id empty and the preview renders blank.
		$resume_id = (int) $request->get_param( 'resume_id' );
		if ( $resume_id > 0 && $author_id > 0 ) {
			$resume = get_post( $resume_id );
			if ( $resume && 'wcb_resume' === $resume->post_type && (int) $resume->post_author === $author_id ) {
				$resume_attachment_id = (int) get_post_meta( $resume_id, '_wcb_resume_attachment_id', true );
				if ( $resume_attachment_id > 0 && get_post( $resume_attachment_id ) ) {
					return $resume_attachment_id;
				}
			}
		}

		return 0;
	}

	/**
	 * Validate $_FILES['resume_file'] and sideload it to the media library.
	 *
	 * @since 1.1.0
	 *
	 * @param  int $author_id Attachment owner (0 for guest uploads).
	 * @param  int $parent_id Parent post id (0 when uploading standalone).
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

		include_once ABSPATH . 'wp-admin/includes/file.php';
		include_once ABSPATH . 'wp-admin/includes/media.php';
		include_once ABSPATH . 'wp-admin/includes/image.php';

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
	 * @since  1.1.0
	 * @return bool
	 */
	private function resume_required(): bool {
		// Default to true on installs that have never written the setting:
		// candidate-side validation is the customer expectation for a job
		// board (see Basecamp 9818132111). Site owners who explicitly turn
		// it off keep their saved value.
		return \WCB\Admin\Settings::bool( 'apply_resume_required', true );
	}

	/**
	 * Maximum resume size in megabytes (defaults to 5 MB).
	 *
	 * @since  1.1.0
	 * @return int
	 */
	private function resume_max_mb(): int {
		$mb = \WCB\Admin\Settings::int( 'apply_resume_max_mb', 5 );
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
	 * @param  \WP_REST_Request $request Full request object.
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
		if ( $this->check_ability( 'wcb/apply-jobs' ) ) {
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
	 * @param  \WP_REST_Request $request Full request object.
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
		$is_admin        = $this->check_ability( 'wcb/manage-settings' );
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
	 * @param  \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function update_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
		if ( ! $this->check_ability( 'wcb/view-applications' ) ) {
			return $this->permission_error();
		}
		// Admins may update any application.
		if ( $this->check_ability( 'wcb/manage-settings' ) ) {
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
	 * Gates on the wcb_withdraw_application ability + ownership of the
	 * application post. Site owners revoke the ability from wcb_candidate to
	 * disable the feature site-wide, or grant it to a custom role for niche
	 * deployments. The legacy allow_withdraw setting still controls the UI
	 * visibility (CandidateDashboard reads it through the ability), so existing
	 * sites that turned the feature off keep that intent through the migration.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function withdraw_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
		// Site setting toggle — when admins turn this off, the REST endpoint
		// must also refuse so a hidden button + curl call can't bypass the UI.
		// Intended default is ON: a fresh install (no saved value) lets
		// candidates withdraw, matching the customer-friendly default. Only an
		// explicit `false` from the admin settings turns it off.
		if ( ! \WCB\Admin\Settings::bool( 'allow_withdraw', true ) ) {
			return new \WP_Error(
				'wcb_withdraw_disabled',
				__( 'Application withdrawal is not enabled on this site.', 'wp-career-board' ),
				array( 'status' => 403 )
			);
		}

		if ( ! $this->check_ability( 'wcb/withdraw-application' ) ) {
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
		$is_admin = $this->check_ability( 'wcb/manage-settings' );
		return ( $is_owner || $is_admin ) ? true : $this->permission_error();
	}

	/**
	 * Check if the current user can list a candidate's applications.
	 *
	 * Allows the candidate themselves or an admin.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function candidate_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
		$same_user = get_current_user_id() === (int) $request['id'];
		$is_admin  = $this->check_ability( 'wcb/manage-settings' );
		return ( $same_user || $is_admin ) ? true : $this->permission_error();
	}

	// --- Helpers ----------------------------------------------------------------

	/**
	 * Shape a WP_Post (wcb_application) into a role-aware REST response array.
	 *
	 * Three viewer roles, three response shapes (F-3 in
	 * plan/role-data-baseline-2026-05-07.md):
	 *
	 * - candidate (own application): submission + current status + simple
	 *   timestamps. NO status_history (audit trail), NO reviewer identity.
	 * - employer (job owner): full applicant + status_history with reviewer
	 *   identity redacted to "Hiring team".
	 * - admin: everything, including reviewer user_ids in status_history.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_Post              $post    Application post object.
	 * @param  \WP_REST_Request|null $request The originating REST request, when available.
	 * @return array<string, mixed>
	 */
	private function prepare_application( \WP_Post $post, ?\WP_REST_Request $request = null ): array {
		$current_user_id = get_current_user_id();
		$is_admin        = $this->check_ability( 'wcb/manage-settings' );
		$viewer_role     = 'candidate';

		if ( $is_admin ) {
			$data        = $this->prepare_for_admin( $post );
			$viewer_role = 'admin';
		} else {
			$candidate_id = (int) get_post_meta( $post->ID, '_wcb_candidate_id', true );
			$job_id       = (int) get_post_meta( $post->ID, '_wcb_job_id', true );
			$job          = $job_id ? get_post( $job_id ) : null;
			$is_owner     = $candidate_id > 0 && $candidate_id === $current_user_id;
			$is_employer  = $job instanceof \WP_Post && $current_user_id === (int) $job->post_author;

			if ( $is_employer ) {
				$data        = $this->prepare_for_employer( $post );
				$viewer_role = 'employer';
			} elseif ( $is_owner ) {
				$data = $this->prepare_for_candidate( $post );
			} else {
				// Permission_callback already gated; defensive fallback to the
				// most-redacted shape rather than leaking the audit trail.
				$data = $this->prepare_for_candidate( $post );
			}
		}

		/**
		 * Canonical wcb_rest_prepare_* filter for the application resource.
		 *
		 * Pro and third-party extensions can decorate the prepared response.
		 * The `viewer_role` arg indicates which role-aware shape was produced
		 * (candidate / employer / admin) so consumers can tailor decoration
		 * safely without leaking employer-only fields back to the candidate
		 * view (see Task 3.7A's role-split in
		 * plan/role-data-baseline-2026-05-07.md).
		 *
		 * @since 1.1.1
		 * @since 1.2.2 Added `viewer_role` argument.
		 *
		 * @param array                 $data        Application response array.
		 * @param \WP_Post              $post        The application post object.
		 * @param \WP_REST_Request|null $request     The originating REST request, when available.
		 * @param string                $viewer_role 'candidate' | 'employer' | 'admin'.
		 */
		return (array) apply_filters( 'wcb_rest_prepare_application', $data, $post, $request, $viewer_role );
	}

	/**
	 * Candidate view — own submission + status, no audit trail.
	 *
	 * @since 1.2.0
	 *
	 * @param  \WP_Post $post Application post object.
	 * @return array<string, mixed>
	 */
	private function prepare_for_candidate( \WP_Post $post ): array {
		$status               = (string) get_post_meta( $post->ID, '_wcb_status', true );
		$resume_attachment_id = (int) get_post_meta( $post->ID, '_wcb_resume_attachment_id', true );
		return array(
			'id'           => $post->ID,
			'job_id'       => (int) get_post_meta( $post->ID, '_wcb_job_id', true ),
			'candidate_id' => (int) get_post_meta( $post->ID, '_wcb_candidate_id', true ),
			'cover_letter' => (string) get_post_meta( $post->ID, '_wcb_cover_letter', true ),
			'resume_id'    => (int) get_post_meta( $post->ID, '_wcb_resume_id', true ),
			'resume_url'   => $resume_attachment_id ? wp_get_attachment_url( $resume_attachment_id ) : '',
			'status'       => '' !== $status ? $status : 'submitted',
			'submitted_at' => $post->post_date,
			// status_history intentionally omitted — internal employer audit
			// trail. F-3 in plan/role-data-baseline-2026-05-07.md.
		);
	}

	/**
	 * Employer view — full applicant + redacted status history.
	 *
	 * Reviewer identity is redacted to "Hiring team" so a fellow reviewer's
	 * user_id never reaches the employer who owns the job. Admins still see
	 * the raw user_ids via prepare_for_admin().
	 *
	 * @since 1.2.0
	 *
	 * @param  \WP_Post $post Application post object.
	 * @return array<string, mixed>
	 */
	private function prepare_for_employer( \WP_Post $post ): array {
		$base = $this->prepare_for_candidate( $post );
		return array_merge(
			$base,
			array(
				'status_history' => $this->status_history_for_employer( $post ),
			)
		);
	}

	/**
	 * Admin view — everything, including raw reviewer user_ids.
	 *
	 * @since 1.2.0
	 *
	 * @param  \WP_Post $post Application post object.
	 * @return array<string, mixed>
	 */
	private function prepare_for_admin( \WP_Post $post ): array {
		$base = $this->prepare_for_employer( $post );
		return array_merge(
			$base,
			array(
				'status_history' => $this->status_history_for_admin( $post ),
			)
		);
	}

	/**
	 * Status history rows with reviewer identity redacted.
	 *
	 * The on-disk shape is `{from, to, by: <user_id>, at}`. The employer
	 * sees `{status, timestamp, reviewer: 'Hiring team'}` — same audit trail
	 * minus the reviewer's user_id.
	 *
	 * @since 1.2.0
	 *
	 * @param  \WP_Post $post Application post object.
	 * @return array<int, array<string, string>>
	 */
	private function status_history_for_employer( \WP_Post $post ): array {
		$log = (array) get_post_meta( $post->ID, '_wcb_status_log', true );
		return array_map(
			static function ( $entry ): array {
				$entry = is_array( $entry ) ? $entry : array();
				return array(
					'status'    => isset( $entry['to'] ) ? (string) $entry['to'] : '',
					'from'      => isset( $entry['from'] ) ? (string) $entry['from'] : '',
					'timestamp' => isset( $entry['at'] ) ? (string) $entry['at'] : '',
					'reviewer'  => __( 'Hiring team', 'wp-career-board' ),
				);
			},
			$log
		);
	}

	/**
	 * Status history rows with reviewer user_ids preserved.
	 *
	 * @since 1.2.0
	 *
	 * @param  \WP_Post $post Application post object.
	 * @return array<int, array<string, mixed>>
	 */
	private function status_history_for_admin( \WP_Post $post ): array {
		$log = (array) get_post_meta( $post->ID, '_wcb_status_log', true );
		return array_map(
			static function ( $entry ): array {
				$entry = is_array( $entry ) ? $entry : array();
				return array(
					'status'           => isset( $entry['to'] ) ? (string) $entry['to'] : '',
					'from'             => isset( $entry['from'] ) ? (string) $entry['from'] : '',
					'timestamp'        => isset( $entry['at'] ) ? (string) $entry['at'] : '',
					'reviewer_user_id' => isset( $entry['by'] ) ? (int) $entry['by'] : 0,
				);
			},
			$log
		);
	}
}
