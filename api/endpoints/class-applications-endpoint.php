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
	 * Meta-key prefix for answers to `wcb_application_form_fields_groups` fields.
	 *
	 * @since 1.7.1
	 * @var string
	 */
	public const FIELD_META_PREFIX = '_wcb_application_field_';

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

		// CSV of one job's applicants, for its employer (and staff).
		register_rest_route(
			$this->namespace,
			'/jobs/(?P<id>\d+)/applications/export',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static function ( \WP_REST_Request $r ): void {
					\WCB\Core\ApplicationsCsv::stream(
						array(
							'post_type'   => 'wcb_application',
							'post_status' => 'any',
							'meta_key'    => '_wcb_job_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
							'meta_value'  => (string) (int) $r['id'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
						),
						static fn ( int $app_id ): bool => true
					);
				},
				'permission_callback' => function ( \WP_REST_Request $r ): bool|\WP_Error {
					$job = get_post( (int) $r['id'] );
					return ( $job instanceof \WP_Post && 'wcb_job' === $job->post_type && ( (int) $job->post_author === get_current_user_id() || $this->check_ability( 'wcb/manage-settings' ) ) ) ? true : $this->permission_error();
				},
			)
		);

		// Hiring-team notes and rating: the job's employer and staff only.
		register_rest_route(
			$this->namespace,
			'/applications/(?P<id>\d+)/notes',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static fn ( \WP_REST_Request $r ): \WP_REST_Response => rest_ensure_response( \WCB\Modules\Applications\ApplicationNotes::notes( (int) $r['id'] ) ),
					'permission_callback' => array( $this, 'update_permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => static function ( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
						$text = trim( sanitize_textarea_field( (string) $r->get_param( 'text' ) ) );
						if ( '' === $text ) {
							return new \WP_Error( 'wcb_note_empty', __( 'Write a note first.', 'wp-career-board' ), array( 'status' => 400 ) );
						}
						\WCB\Modules\Applications\ApplicationNotes::add( (int) $r['id'], get_current_user_id(), mb_substr( $text, 0, 5000 ) );
						return rest_ensure_response( \WCB\Modules\Applications\ApplicationNotes::notes( (int) $r['id'] ) );
					},
					'permission_callback' => array( $this, 'update_permissions_check' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/applications/(?P<id>\d+)/notes/(?P<note>[A-Za-z0-9]+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => function ( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
					$done = \WCB\Modules\Applications\ApplicationNotes::delete( (int) $r['id'], (string) $r['note'], get_current_user_id(), $this->check_ability( 'wcb/manage-settings' ) );
					return $done ? rest_ensure_response( \WCB\Modules\Applications\ApplicationNotes::notes( (int) $r['id'] ) ) : new \WP_Error( 'wcb_note_not_found', __( 'That note was already removed, or is not yours.', 'wp-career-board' ), array( 'status' => 404 ) );
				},
				'permission_callback' => array( $this, 'update_permissions_check' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/applications/(?P<id>\d+)/rating',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => static fn ( \WP_REST_Request $r ): \WP_REST_Response => rest_ensure_response( array( 'rating' => \WCB\Modules\Applications\ApplicationNotes::set_rating( (int) $r['id'], (int) $r->get_param( 'rating' ) ) ) ),
				'permission_callback' => array( $this, 'update_permissions_check' ),
				'args'                => array(
					'rating' => array(
						'type'     => 'integer',
						'minimum'  => 0,
						'maximum'  => 5,
						'required' => true,
					),
				),
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

		// Download a private candidate file. The website links to the
		// `?wcb_file=` handler (cookie session); the app authenticates only on
		// REST, so it uses this route. Same check either way.
		register_rest_route(
			$this->namespace,
			'/files/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static function ( \WP_REST_Request $request ): void {
					\WCB\Core\PrivateFiles::send( (int) $request['id'] );
				},
				'permission_callback' => static function ( \WP_REST_Request $request ): bool {
					return \WCB\Core\PrivateFiles::can_download( (int) $request['id'] );
				},
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Upload a resume file (Free mode — no wcb_resume post). Requires login.
		register_rest_route(
			$this->namespace,
			'/candidates/resume-upload',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'upload_resume_file' ),
				// wcb/manage-resume. This one accepted a file upload from ANY
				// logged-in user, including a banned one, while the ability that
				// exists to gate it went unused.
				'permission_callback' => static function (): bool {
					return wp_is_ability_granted( 'wcb/manage-resume' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
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

		// A job past its advertised deadline stops taking applications, whether or
		// not deadline_auto_close has flipped its post status yet. Without this the
		// endpoint accepted submissions for closed roles indefinitely on the
		// default install, and the candidate got a success response.
		if ( \WCB\Core\JobDeadline::has_passed( $job_id ) ) {
			return new \WP_Error(
				'wcb_job_deadline_passed',
				__( 'Applications for this job have closed.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $is_guest && (int) $job->post_author === get_current_user_id() ) {
			return new \WP_Error( 'wcb_own_job', __( 'You cannot apply to your own job.', 'wp-career-board' ), array( 'status' => 403 ) );
		}

		// Required screening questions, checked here and not only in the
		// browser (a request without JavaScript skipped them).
		$wcb_missing = \WCB\Core\FormCustomFields::missing_required(
			(array) apply_filters( 'wcb_application_form_fields_groups', array(), $job_id ),
			(array) ( $request->get_param( 'custom_fields' ) ?? array() )
		);
		if ( $wcb_missing ) {
			return new \WP_Error(
				'wcb_required_fields',
				/* translators: %s: comma-separated field labels. */
				sprintf( __( 'Please answer: %s', 'wp-career-board' ), implode( ', ', $wcb_missing ) ),
				array(
					'status' => 400,
					'fields' => array_keys( $wcb_missing ),
				)
			);
		}

		if ( $is_guest ) {
			// A guest request can carry a 20 MB upload: at most 10 an hour per IP.
			$wcb_ip_key = 'wcb_guest_apply_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- hashed, never output.
			$wcb_tries  = (int) get_transient( $wcb_ip_key );
			if ( $wcb_tries >= 10 ) {
				return new \WP_Error( 'wcb_rate_limited', __( 'Too many applications from this connection. Please try again in an hour.', 'wp-career-board' ), array( 'status' => 429 ) );
			}
			set_transient( $wcb_ip_key, $wcb_tries + 1, HOUR_IN_SECONDS );

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
							// A withdrawn application does not block applying again.
							array(
								'key'     => '_wcb_status',
								'value'   => \WCB\Modules\Applications\ApplicationStatus::WITHDRAWN,
								'compare' => '!=',
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

		// Snapshot the job title + company at apply time so the candidate's
		// history stays readable if the job post is deleted later.
		$snapshot_job = get_post( $job_id );
		if ( $snapshot_job instanceof \WP_Post ) {
			update_post_meta( $app_id, '_wcb_job_title_snapshot', (string) $snapshot_job->post_title );
			update_post_meta( $app_id, '_wcb_company_name_snapshot', (string) get_post_meta( $job_id, '_wcb_company_name', true ) );
		}
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
		} elseif ( $resume_id > 0 ) {
			// Candidate picked a saved resume with no uploaded PDF — common when
			// the resume was built in the manual builder. Auto-generate the PDF so
			// applying stays one tap: Pro renders the structured resume to a PDF and
			// caches it on the resume. Falls back to the upload/export error only
			// when generation isn't available (e.g. Pro inactive).
			$generated_id = (int) apply_filters( 'wcb_resume_pdf_attachment_id', 0, $resume_id, $candidate_id );
			if ( $generated_id > 0 ) {
				update_post_meta( $app_id, '_wcb_resume_attachment_id', $generated_id );
			} elseif ( $this->resume_required() ) {
				wp_delete_post( $app_id, true );
				return new \WP_Error(
					'wcb_resume_no_pdf',
					__( "We couldn't attach this resume. Open it in the resume builder and use 'Download as PDF', or upload a file below, before applying.", 'wp-career-board' ),
					array( 'status' => 400 )
				);
			}
		} elseif ( $this->resume_required() ) {
			wp_delete_post( $app_id, true );
			return new \WP_Error(
				'wcb_resume_required',
				__( 'A resume is required to apply for this job.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		update_post_meta( $app_id, '_wcb_status', \WCB\Modules\Applications\ApplicationStatus::SUBMITTED );

		// Custom application fields registered via wcb_application_form_fields_groups
		// filter. The job-single block's view.js captures values into state.customFields
		// as the user types and POSTs them as custom_fields[<key>] = <value>. Read via
		// get_param() rather than $_POST so JSON REST clients work too — WP populates
		// body params from $_POST for multipart/form-data, so the browser path is
		// unchanged. Persistence goes through the shared renderer's writer, which
		// validates every submitted key against the active filter output (a
		// hand-crafted POST can't write arbitrary postmeta), applies the same
		// type-aware sanitiser the forms use, and honours `wcb_save_custom_field`.
		// The `_wcb_application_field_` prefix keeps the established meta convention.
		$wcb_custom_input = $request->get_param( 'custom_fields' );

		if ( is_array( $wcb_custom_input ) && ! empty( $wcb_custom_input ) ) {
			$wcb_field_groups = (array) apply_filters( 'wcb_application_form_fields_groups', array(), $job_id );
			\WCB\Core\FormCustomFields::save_values( $wcb_field_groups, $app_id, $wcb_custom_input, 'post_meta', self::FIELD_META_PREFIX );
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

		// Employer-actionable statuses only — `withdrawn` is candidate-only,
		// `job_removed` is system-only (set by ApplicationLifecycle).
		$allowed    = \WCB\Modules\Applications\ApplicationStatus::employer_actionable();
		$new_status = sanitize_text_field( (string) $request->get_param( 'status' ) );
		if ( ! in_array( $new_status, $allowed, true ) ) {
			return new \WP_Error(
				'wcb_invalid_status',
				__( 'Invalid status.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		// Withdrawn, position-closed and job-removed are final outcomes; the
		// employer sees them but cannot reopen them.
		$current = (string) get_post_meta( $post->ID, '_wcb_status', true );
		if ( '' !== $current && ! in_array( $current, $allowed, true ) ) {
			return new \WP_Error(
				'wcb_application_closed',
				__( 'This application is closed (withdrawn, position closed or job removed), so its status can no longer change.', 'wp-career-board' ),
				array( 'status' => 409 )
			);
		}

		$note    = sanitize_textarea_field( (string) $request->get_param( 'note' ) );
		$changed = \WCB\Modules\Applications\ApplicationLifecycle::transition( $post->ID, $new_status, 'employer_update', get_current_user_id(), $note );

		return rest_ensure_response(
			array_merge(
				array(
					'id'       => $post->ID,
					'changed'  => $changed,
					// Guests have no account, so the status email never reaches them.
					'notified' => $changed && null !== \WCB\Modules\Notifications\Emails\EmailAppStatus::recipient( $post->ID ),
				),
				\WCB\Modules\Applications\ApplicationStatus::payload( $new_status, $this->audience_for( $post ) )
			)
		);
	}

	/**
	 * Which wording the current user should read for this application.
	 *
	 * @since 1.8.0
	 *
	 * @param \WP_Post $post Application post.
	 * @return string ApplicationStatus::AUDIENCE_* constant.
	 */
	private function audience_for( \WP_Post $post ): string {
		$user_id = get_current_user_id();
		if ( $user_id > 0 && (int) get_post_meta( $post->ID, '_wcb_candidate_id', true ) === $user_id ) {
			return \WCB\Modules\Applications\ApplicationStatus::AUDIENCE_CANDIDATE;
		}
		return $this->check_ability( 'wcb/manage-settings' )
			? \WCB\Modules\Applications\ApplicationStatus::AUDIENCE_ADMIN
			: \WCB\Modules\Applications\ApplicationStatus::AUDIENCE_EMPLOYER;
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
			$job_id      = (int) get_post_meta( $app->ID, '_wcb_job_id', true );
			$job         = $job_id ? get_post( $job_id ) : null;
			$status      = (string) get_post_meta( $app->ID, '_wcb_status', true );
			$status      = $status ? $status : \WCB\Modules\Applications\ApplicationStatus::SUBMITTED;
			$job_removed = \WCB\Modules\Applications\ApplicationStatus::JOB_REMOVED === $status;

			// Snapshot meta (saved at apply-time) preserves the title/company
			// when the job post is no longer fetchable. The endpoint prefers
			// the live job, then the snapshot, then a translated fallback so
			// pre-snapshot rows still render readably.
			$title_snapshot   = (string) get_post_meta( $app->ID, '_wcb_job_title_snapshot', true );
			$company_snapshot = (string) get_post_meta( $app->ID, '_wcb_company_name_snapshot', true );
			$job_exists       = $job instanceof \WP_Post;

			$row = array(
				'id'           => $app->ID,
				'jobTitle'     => $job_exists ? $job->post_title : ( $title_snapshot ? $title_snapshot : __( 'Job no longer available', 'wp-career-board' ) ),
				'jobPermalink' => $job_exists ? (string) get_permalink( $job_id ) : '',
				'company'      => $job_exists ? (string) get_post_meta( $job_id, '_wcb_company_name', true ) : $company_snapshot,
				'jobRemoved'   => $job_removed || ! $job_exists,
				// Withdraw is offered until the application has an outcome.
				'canWithdraw'  => $job_exists && ! in_array( $status, \WCB\Modules\Applications\ApplicationStatus::terminal(), true ),
				'created_at'   => mysql_to_rfc3339( $app->post_date_gmt ),
				'updated_at'   => mysql_to_rfc3339( $app->post_modified_gmt ),
				// Legacy `date` key, still rendered by the candidate dashboard.
				// Use the site's configured date format, not a hardcoded ISO string.
				'date'         => get_the_date( (string) get_option( 'date_format' ), $app ),
			) + \WCB\Modules\Applications\ApplicationStatus::payload( $status, \WCB\Modules\Applications\ApplicationStatus::AUDIENCE_CANDIDATE );

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
				'counts'       => \WCB\Modules\Applications\ApplicationStatus::counts( 'candidate', $candidate_id ),
			)
		);
		$response->header( 'X-WCB-Total', (string) $total );
		$response->header( 'X-WCB-TotalPages', (string) $pages );
		return $response;
	}

	/**
	 * Withdraw an application — candidate owner only.
	 *
	 * Since 1.8.0 the application is kept with status `withdrawn` instead of
	 * being deleted, so the employer's list and the candidate's history both
	 * stay truthful. Allowed until the application reaches an outcome (hired,
	 * rejected, job removed). Fires wcb_application_withdrawn, which emails
	 * the employer.
	 *
	 * @since 1.0.0
	 * @since 1.8.0 Keeps the application as `withdrawn`.
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

		$status = (string) get_post_meta( $post->ID, '_wcb_status', true );
		$job_id = (int) get_post_meta( $post->ID, '_wcb_job_id', true );

		// The job is gone: nobody else sees this row, so "Remove" really deletes
		// it and the candidate can tidy their history.
		if ( \WCB\Modules\Applications\ApplicationStatus::JOB_REMOVED === $status || 'wcb_job' !== get_post_type( $job_id ) ) {
			wp_delete_post( $post->ID, true );
			return rest_ensure_response(
				array(
					'id'        => $post->ID,
					'withdrawn' => false,
					'deleted'   => true,
				)
			);
		}

		if ( in_array( $status, \WCB\Modules\Applications\ApplicationStatus::terminal(), true ) ) {
			return new \WP_Error(
				'wcb_withdraw_closed',
				__( 'This application already has an outcome and can no longer be withdrawn.', 'wp-career-board' ),
				array( 'status' => 409 )
			);
		}

		$app_id       = $post->ID;
		$candidate_id = (int) get_post_meta( $app_id, '_wcb_candidate_id', true );

		\WCB\Modules\Applications\ApplicationLifecycle::transition( $app_id, \WCB\Modules\Applications\ApplicationStatus::WITHDRAWN, 'candidate_withdrew', get_current_user_id() );

		/**
		 * Fires after a candidate withdraws an application.
		 *
		 * @since 1.0.0
		 *
		 * @param int $app_id       Application post ID (kept, status withdrawn, since 1.8.0).
		 * @param int $job_id       Job post ID.
		 * @param int $candidate_id Candidate user ID.
		 */
		do_action( 'wcb_application_withdrawn', $app_id, $job_id, $candidate_id );

		return rest_ensure_response(
			array_merge(
				array(
					'id'        => $app_id,
					'withdrawn' => true,
					// Legacy key: clients before 1.8.0 treated `deleted` as success.
					'deleted'   => true,
				),
				\WCB\Modules\Applications\ApplicationStatus::payload( \WCB\Modules\Applications\ApplicationStatus::WITHDRAWN, \WCB\Modules\Applications\ApplicationStatus::AUDIENCE_CANDIDATE )
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
			// A guest has no uploads of their own to point at: any id they send
			// belongs to someone else, and attaching it handed that person's CV
			// to the job's employer. Guests upload the file on this request.
			if ( $author_id <= 0 ) {
				return new \WP_Error(
					'wcb_invalid_resume',
					__( 'Invalid resume attachment.', 'wp-career-board' ),
					array( 'status' => 400 )
				);
			}
			$attachment = get_post( $pre_uploaded );
			if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
				return new \WP_Error(
					'wcb_invalid_resume',
					__( 'Invalid resume attachment.', 'wp-career-board' ),
					array( 'status' => 400 )
				);
			}
			if ( (int) $attachment->post_author !== $author_id ) {
				return new \WP_Error(
					'wcb_invalid_resume',
					__( 'Invalid resume attachment.', 'wp-career-board' ),
					array( 'status' => 400 )
				);
			}
			return $pre_uploaded;
		}

		// A saved resume's PDF is resolved by the `wcb_resume_pdf_attachment_id`
		// filter in the caller, which returns the candidate's uploaded PDF or a
		// generated one that still matches the resume. Reading the stored
		// attachment here instead skipped that check and kept sending employers
		// the CV as it was before the candidate edited it.
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

		$attachment_id = \WCB\Core\PrivateFiles::in_private_dir(
			static fn () => media_handle_upload( 'resume_file', $parent_id, array(), $overrides )
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}
		\WCB\Core\PrivateFiles::protect( (int) $attachment_id );

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
		// board. Site owners who explicitly turn it off keep their saved value.
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
		// Guests can apply unless Settings > Applications requires an account.
		if ( ! is_user_logged_in() ) {
			return \WCB\Admin\Settings::bool( 'apply_require_login', false )
				? new \WP_Error( 'wcb_login_required', __( 'Please sign in to apply for this job.', 'wp-career-board' ), array( 'status' => 401 ) )
				: true;
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

		$data = array_merge( $data, \WCB\Modules\Applications\ApplicationStatus::payload( (string) ( $data['status'] ?? '' ), $viewer_role ) );

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
		$resume_id            = (int) get_post_meta( $post->ID, '_wcb_resume_id', true );
		$job_id               = (int) get_post_meta( $post->ID, '_wcb_job_id', true );

		// Public on-site resume profile — lets the employer review the candidate's
		// full resume (experience, education, skills) on the site instead of relying
		// only on a downloaded file, which may not exist for builder/imported resumes.
		$resume_permalink = '';
		if ( $resume_id > 0 && '1' === (string) get_post_meta( $resume_id, '_wcb_resume_public', true ) ) {
			$resume_post = get_post( $resume_id );
			if ( $resume_post instanceof \WP_Post && 'wcb_resume' === $resume_post->post_type ) {
				$resume_permalink = (string) get_permalink( $resume_id );
			}
		}

		return array(
			'id'               => $post->ID,
			'job_id'           => $job_id,
			'candidate_id'     => (int) get_post_meta( $post->ID, '_wcb_candidate_id', true ),
			'cover_letter'     => (string) get_post_meta( $post->ID, '_wcb_cover_letter', true ),
			// Answers to the questions the job asked. The candidate seeing their
			// own submission back is correct; employer + admin inherit this key
			// through prepare_for_employer()/prepare_for_admin().
			'custom_fields'    => \WCB\Core\FormCustomFields::labelled_values(
				(array) apply_filters( 'wcb_application_form_fields_groups', array(), $job_id ),
				$post->ID,
				'post_meta',
				self::FIELD_META_PREFIX
			),
			'resume_id'        => $resume_id,
			'resume_url'       => $resume_attachment_id ? \WCB\Core\PrivateFiles::url( (int) $resume_attachment_id ) : '',
			'resume_permalink' => $resume_permalink,
			'status'           => '' !== $status ? $status : 'submitted',
			'submitted_at'     => $post->post_date,
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
		$log = \WCB\Modules\Applications\ApplicationLifecycle::log( $post->ID );
		return array_map(
			static fn( array $entry ): array => array(
				'status'    => $entry['to'],
				'from'      => $entry['from'],
				'timestamp' => $entry['at'],
				'reviewer'  => __( 'Hiring team', 'wp-career-board' ),
			),
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
		$log = \WCB\Modules\Applications\ApplicationLifecycle::log( $post->ID );
		return array_map(
			static fn( array $entry ): array => array(
				'status'           => $entry['to'],
				'from'             => $entry['from'],
				'timestamp'        => $entry['at'],
				'reviewer_user_id' => $entry['by'],
			),
			$log
		);
	}
}
