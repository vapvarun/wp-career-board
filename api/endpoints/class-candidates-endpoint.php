<?php
/**
 * Candidates REST endpoint — profile read, update, and bookmarks.
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
 * Handles /wcb/v1/candidates REST routes.
 *
 * Candidate profile get/update and bookmarked-job listing.
 * Profile visibility is enforced inside the callback (public by default).
 * All admin fallbacks use the Abilities API, not raw current_user_can().
 *
 * @since 1.0.0
 */
final class CandidatesEndpoint extends RestController {

	/**
	 * Register all candidate routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/candidates/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/candidates/(?P<id>\d+)/bookmarks',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_bookmarks' ),
				'permission_callback' => array( $this, 'self_permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/candidates/(?P<id>\d+)/saved-companies',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_saved_companies' ),
				'permission_callback' => array( $this, 'self_permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/candidates/(?P<id>\d+)/saved-resumes',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_saved_resumes' ),
				'permission_callback' => array( $this, 'self_permissions_check' ),
			)
		);

		// `required: true` is intentionally NOT declared on these args — the
		// REST server would short-circuit with 400 before `register_candidate()`
		// runs, breaking the logged-in promote-only path (where the user is
		// already authenticated and we don't need new credentials). The
		// callback enforces the requirements explicitly for the not-logged-in
		// branch instead.
		register_rest_route(
			$this->namespace,
			'/candidates/register',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'register_candidate' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'first_name' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'last_name'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'email'      => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'password'   => array(
						'type' => 'string',
					),
				),
			)
		);

		// User-facing GDPR self-service: candidate requests an export OR erase of their
		// own data. Routes wrap WP's wp_create_user_request() so the request enters the
		// standard admin queue at Tools → Export/Erase Personal Data — admins still
		// confirm, but candidates can self-trigger from their dashboard instead of
		// emailing support.
		register_rest_route(
			$this->namespace,
			'/candidates/me/privacy/(?P<action>export|erase)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'request_privacy_action' ),
				'permission_callback' => array( $this, 'me_logged_in_check' ),
				'args'                => array(
					'action' => array(
						'type'              => 'string',
						'required'          => true,
						'enum'              => array( 'export', 'erase' ),
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	// --- Route callbacks --------------------------------------------------------

	/**
	 * Register a new candidate user.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function register_candidate( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		// Logged-in user without the candidate role: just promote them.
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( in_array( 'wcb_candidate', (array) $user->roles, true ) ) {
				return new \WP_Error(
					'wcb_already_candidate',
					__( 'You are already registered as a candidate.', 'wp-career-board' ),
					array( 'status' => 409 )
				);
			}
			if ( in_array( 'wcb_employer', (array) $user->roles, true ) ) {
				return new \WP_Error(
					'wcb_employer_account',
					__( 'Your account is already registered as an employer. Use a different account to apply for jobs.', 'wp-career-board' ),
					array( 'status' => 409 )
				);
			}
			// Replace existing roles, not stack. See same note in
			// EmployersEndpoint::register_employer().
			$user->set_role( 'wcb_candidate' );
			do_action( 'wcb_candidate_registered', $user->ID );

			$dashboard_id  = \WCB\Admin\Settings::int( 'candidate_dashboard_page', 0 );
			$dashboard_url = $dashboard_id > 0
				? (string) get_permalink( $dashboard_id )
				: home_url( '/' );

			return rest_ensure_response(
				array(
					'user_id'       => $user->ID,
					'dashboard_url' => $dashboard_url,
				)
			);
		}

		if ( ! get_option( 'users_can_register', false ) && ! ( defined( 'MULTISITE' ) && MULTISITE ) ) {
			return new \WP_Error(
				'wcb_registration_disabled',
				__( 'User registration is currently disabled.', 'wp-career-board' ),
				array( 'status' => 403 )
			);
		}

		$first_name = (string) $request->get_param( 'first_name' );
		$last_name  = (string) $request->get_param( 'last_name' );
		$email      = (string) $request->get_param( 'email' );
		$password   = (string) $request->get_param( 'password' );

		$missing = array();
		if ( '' === $first_name ) {
			$missing[] = 'first_name';
		}
		if ( '' === $last_name ) {
			$missing[] = 'last_name';
		}
		if ( '' === $email ) {
			$missing[] = 'email';
		}
		if ( '' === $password ) {
			$missing[] = 'password';
		}
		if ( ! empty( $missing ) ) {
			return new \WP_Error(
				'wcb_missing_params',
				sprintf(
					/* translators: %s: comma-separated list of missing field names. */
					__( 'Missing required fields: %s', 'wp-career-board' ),
					implode( ', ', $missing )
				),
				array( 'status' => 400 )
			);
		}

		if ( email_exists( $email ) ) {
			return new \WP_Error(
				'wcb_email_exists',
				__( 'An account with this email address already exists.', 'wp-career-board' ),
				array( 'status' => 409 )
			);
		}

		if ( strlen( $password ) < 8 ) {
			return new \WP_Error(
				'wcb_weak_password',
				__( 'Password must be at least 8 characters.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		$username = sanitize_user( strtolower( $first_name . '.' . $last_name ), true );
		if ( ! $username ) {
			$username = sanitize_user( strtolower( $email ), true );
		}
		if ( username_exists( $username ) ) {
			$username = $username . wp_rand( 100, 999 );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => $password,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => trim( $first_name . ' ' . $last_name ),
				'role'         => 'wcb_candidate',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return new \WP_Error(
				'wcb_registration_failed',
				$user_id->get_error_message(),
				array( 'status' => 500 )
			);
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, false );

		do_action( 'wcb_candidate_registered', $user_id );

		$dashboard_id  = \WCB\Admin\Settings::int( 'candidate_dashboard_page', 0 );
		$dashboard_url = $dashboard_id > 0
			? (string) get_permalink( $dashboard_id )
			: home_url( '/' );

		return rest_ensure_response(
			array(
				'user_id'       => $user_id,
				'dashboard_url' => $dashboard_url,
			)
		);
	}

	/**
	 * Retrieve a candidate profile.
	 *
	 * Respects the _wcb_profile_visibility usermeta setting.
	 * Private profiles are hidden unless the requester is the profile owner or an admin.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ): \WP_REST_Response|\WP_Error {
		$user_id = (int) $request['id'];
		$user    = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return new \WP_Error(
				'wcb_not_found',
				__( 'Candidate not found.', 'wp-career-board' ),
				array( 'status' => 404 )
			);
		}

		// Enforce profile visibility.
		$raw_visibility = (string) get_user_meta( $user_id, '_wcb_profile_visibility', true );
		$visibility     = $raw_visibility ? $raw_visibility : 'public';
		$is_self        = get_current_user_id() === $user_id;
		$is_admin       = $this->check_ability( 'wcb/manage-settings' );
		if ( 'private' === $visibility && ! $is_self && ! $is_admin ) {
			return new \WP_Error(
				'wcb_private',
				__( 'This profile is private.', 'wp-career-board' ),
				array( 'status' => 403 )
			);
		}

		return rest_ensure_response( $this->prepare_candidate( $user ) );
	}

	/**
	 * Update a candidate profile (bio, visibility, resume data).
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ): \WP_REST_Response|\WP_Error {
		$user_id = (int) $request['id'];
		$user    = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return new \WP_Error(
				'wcb_not_found',
				__( 'Candidate not found.', 'wp-career-board' ),
				array( 'status' => 404 )
			);
		}

		$bio = $request->get_param( 'bio' );
		if ( null !== $bio ) {
			wp_update_user(
				array(
					'ID'          => $user_id,
					'description' => sanitize_textarea_field( (string) $bio ),
				)
			);
		}

		$visibility_param = $request->get_param( 'profile_visibility' );
		if ( null !== $visibility_param ) {
			$allowed_vis = array( 'public', 'private' );
			$visibility  = in_array( $visibility_param, $allowed_vis, true ) ? $visibility_param : 'public';
			update_user_meta( $user_id, '_wcb_profile_visibility', $visibility );
		}

		// Legacy parameter name (`resume`) — full replace, used by older clients.
		$resume_data = $request->get_param( 'resume' );
		if ( null !== $resume_data ) {
			// Accept only an array; sanitize each string value.
			$safe_resume = array();
			if ( is_array( $resume_data ) ) {
				foreach ( $resume_data as $key => $value ) {
					$safe_resume[ sanitize_key( (string) $key ) ] = sanitize_textarea_field( (string) $value );
				}
			}
			update_user_meta( $user_id, '_wcb_resume_data', $safe_resume );
		}

		// `resume_data` — partial-update key used by the candidate dashboard
		// profile form (phone + location). Merge with existing meta so we
		// don't wipe headline/linkedin/github/website/twitter set elsewhere.
		$resume_data_partial = $request->get_param( 'resume_data' );
		if ( is_array( $resume_data_partial ) ) {
			$existing = get_user_meta( $user_id, '_wcb_resume_data', true );
			$existing = is_array( $existing ) ? $existing : array();
			foreach ( $resume_data_partial as $key => $value ) {
				$existing[ sanitize_key( (string) $key ) ] = sanitize_textarea_field( (string) $value );
			}
			update_user_meta( $user_id, '_wcb_resume_data', $existing );
		}

		// Persist filter-injected custom fields (Pro Field Builder + add-ons).
		$custom = $request->get_param( 'custom_fields' );
		if ( is_array( $custom ) ) {
			$groups = (array) apply_filters( 'wcb_candidate_form_fields', array(), (int) $user_id );
			\WCB\Core\FormCustomFields::save_values( $groups, (int) $user_id, $custom, 'user_meta' );
		}

		return rest_ensure_response( $this->prepare_candidate( get_user_by( 'ID', $user_id ) ) );
	}

	/**
	 * List bookmarked jobs for the given candidate.
	 *
	 * Uses the non-unique _wcb_bookmark usermeta pattern (one row per bookmark).
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response
	 */
	public function get_bookmarks( \WP_REST_Request $request ): \WP_REST_Response {
		// Non-unique meta pattern: get_user_meta with $single=false returns all values.
		$raw_bookmarks = get_user_meta( (int) $request['id'], '_wcb_bookmark', false );
		$bookmark_ids  = array_map( 'intval', (array) $raw_bookmarks );

		$items = array();

		// Prime caches once for every bookmarked job before the loop.
		if ( ! empty( $bookmark_ids ) ) {
			update_meta_cache( 'post', $bookmark_ids );
			update_object_term_cache( $bookmark_ids, array( 'wcb_location', 'wcb_job_type' ) );
		}

		foreach ( $bookmark_ids as $job_id ) {
			$post = get_post( $job_id );
			if ( ! $post instanceof \WP_Post || 'wcb_job' !== $post->post_type ) {
				continue;
			}

			$loc_term_objs  = get_the_terms( $post->ID, 'wcb_location' );
			$type_term_objs = get_the_terms( $post->ID, 'wcb_job_type' );
			$loc_names      = is_array( $loc_term_objs ) ? wp_list_pluck( $loc_term_objs, 'name' ) : array();
			$type_names     = is_array( $type_term_objs ) ? wp_list_pluck( $type_term_objs, 'name' ) : array();

			$items[] = array(
				'id'           => $post->ID,
				'title'        => $post->post_title,
				'permalink'    => get_permalink( $post->ID ),
				'company'      => (string) get_post_meta( $post->ID, '_wcb_company_name', true ),
				'location'     => implode( ', ', $loc_names ),
				'type'         => implode( ', ', $type_names ),
				// Match the jobs-list card so a saved job reads as richly as the
				// same job in the main list (no more "Salary not disclosed" when
				// the job actually has a salary).
				'salary_label' => \WCB\Core\SalaryFormat::format(
					(string) get_post_meta( $post->ID, '_wcb_salary_min', true ),
					(string) get_post_meta( $post->ID, '_wcb_salary_max', true ),
					// Default currency to USD when unset, exactly as the jobs list
					// card does, so the two render the same salary string.
					(string) ( get_post_meta( $post->ID, '_wcb_salary_currency', true ) ?: 'USD' ),
					(string) ( get_post_meta( $post->ID, '_wcb_salary_type', true ) ?: 'yearly' )
				),
				'remote'       => '1' === (string) get_post_meta( $post->ID, '_wcb_remote', true ),
			);
		}

		// Bookmarks aren't paginated server-side — surface the total + pages
		// fields anyway so the envelope shape stays consistent with paged
		// list endpoints (callers can rely on response.bookmarks etc).
		$count    = count( $items );
		$response = rest_ensure_response(
			array(
				'bookmarks' => $items,
				'total'     => $count,
				'pages'     => $count > 0 ? 1 : 0,
				'has_more'  => false,
			)
		);
		$response->header( 'X-WCB-Total', (string) $count );
		return $response;
	}

	/**
	 * List bookmarked companies for the given user.
	 *
	 * Mirrors `get_bookmarks` shape so the same dashboard list pattern can
	 * render the saved Companies tab without any extra envelope mapping.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response
	 */
	public function get_saved_companies( \WP_REST_Request $request ): \WP_REST_Response {
		$raw_ids     = get_user_meta( (int) $request['id'], '_wcb_company_bookmark', false );
		$company_ids = array_map( 'intval', (array) $raw_ids );
		$items       = array();

		if ( ! empty( $company_ids ) ) {
			update_meta_cache( 'post', $company_ids );
		}

		foreach ( $company_ids as $company_id ) {
			$post = get_post( $company_id );
			if ( ! $post instanceof \WP_Post || 'wcb_company' !== $post->post_type ) {
				continue;
			}

			$items[] = array(
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'permalink' => get_permalink( $post->ID ),
				'industry'  => \WCB\Core\Industries::label( (string) get_post_meta( $post->ID, '_wcb_industry', true ) ),
				'hq'        => (string) get_post_meta( $post->ID, '_wcb_hq_location', true ),
				'tagline'   => (string) get_post_meta( $post->ID, '_wcb_tagline', true ),
			);
		}

		$count    = count( $items );
		$response = rest_ensure_response(
			array(
				'items'    => $items,
				'total'    => $count,
				'pages'    => $count > 0 ? 1 : 0,
				'has_more' => false,
			)
		);
		$response->header( 'X-WCB-Total', (string) $count );
		return $response;
	}

	/**
	 * List bookmarked resumes for the given user. Resumes are a Pro CPT;
	 * Free returns an empty list when the CPT isn't registered so the
	 * dashboard tab can render its empty state without a 404.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response
	 */
	public function get_saved_resumes( \WP_REST_Request $request ): \WP_REST_Response {
		$items = array();

		if ( post_type_exists( 'wcb_resume' ) ) {
			$raw_ids    = get_user_meta( (int) $request['id'], '_wcb_resume_bookmark', false );
			$resume_ids = array_map( 'intval', (array) $raw_ids );

			if ( ! empty( $resume_ids ) ) {
				update_meta_cache( 'post', $resume_ids );
			}

			foreach ( $resume_ids as $resume_id ) {
				$post = get_post( $resume_id );
				if ( ! $post instanceof \WP_Post || 'wcb_resume' !== $post->post_type ) {
					continue;
				}

				$candidate_id   = (int) $post->post_author;
				$candidate_name = $candidate_id ? get_the_author_meta( 'display_name', $candidate_id ) : $post->post_title;

				// Job title + location live in `_wcb_resume_experience` (an
				// array of work entries); the first entry is the most recent
				// role. Same shape the public archive uses in
				// `WCB\Pro\Modules\Resume\ResumeModule::build_archive_item`,
				// so the dashboard meta line matches what users saw when they
				// bookmarked the resume.
				$experience = (array) get_post_meta( $post->ID, '_wcb_resume_experience', true );
				$job_title  = '';
				$location   = '';
				if ( ! empty( $experience ) ) {
					$first     = reset( $experience );
					$job_title = isset( $first['job_title'] ) ? (string) $first['job_title'] : '';
					$location  = isset( $first['location'] ) ? (string) $first['location'] : '';
				}

				$items[] = array(
					'id'        => $post->ID,
					'title'     => $candidate_name ? $candidate_name : $post->post_title,
					'permalink' => get_permalink( $post->ID ),
					'role'      => $job_title,
					'location'  => $location,
				);
			}
		}

		$count    = count( $items );
		$response = rest_ensure_response(
			array(
				'items'    => $items,
				'total'    => $count,
				'pages'    => $count > 0 ? 1 : 0,
				'has_more' => false,
			)
		);
		$response->header( 'X-WCB-Total', (string) $count );
		return $response;
	}

	// --- Permission callbacks ---------------------------------------------------

	/**
	 * Anyone may attempt to GET a candidate profile (visibility enforced in callback).
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool
	 */
	public function get_item_permissions_check( $request ): bool {
		return true;
	}

	/**
	 * Check if the current user can update the given candidate profile.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function update_item_permissions_check( $request ): bool|\WP_Error {
		$same_user = get_current_user_id() === (int) $request['id'];
		$is_admin  = $this->check_ability( 'wcb/manage-settings' );
		return ( $same_user || $is_admin ) ? true : $this->permission_error();
	}

	/**
	 * Check if the current user is the candidate (or an admin) for self-only routes.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function self_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
		$same_user = get_current_user_id() === (int) $request['id'];
		$is_admin  = $this->check_ability( 'wcb/manage-settings' );
		return ( $same_user || $is_admin ) ? true : $this->permission_error();
	}

	// --- Helpers ----------------------------------------------------------------

	/**
	 * Shape a WP_User into the REST response array.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User|false $user Candidate user object.
	 * @return array<string, mixed>
	 */
	private function prepare_candidate( \WP_User|false $user ): array {
		if ( ! $user instanceof \WP_User ) {
			return array();
		}
		$raw_visibility  = (string) get_user_meta( $user->ID, '_wcb_profile_visibility', true );
		$raw_resume_data = get_user_meta( $user->ID, '_wcb_resume_data', true );
		$data            = array(
			'id'                 => $user->ID,
			'display_name'       => $user->display_name,
			'bio'                => $user->description,
			'profile_visibility' => $raw_visibility ? $raw_visibility : 'public',
			'avatar'             => get_avatar_url( $user->ID ),
			'resume_data'        => $raw_resume_data ? $raw_resume_data : array(),
		);

		/**
		 * Canonical wcb_rest_prepare_* filter for the candidate resource.
		 *
		 * @since 1.1.1
		 *
		 * @param array    $data Candidate response array.
		 * @param \WP_User $user The candidate user object.
		 * @param \WP_REST_Request|null $request The originating REST request, when available.
		 */
		return (array) apply_filters( 'wcb_rest_prepare_candidate', $data, $user, null );
	}

	/**
	 * GDPR self-service: trigger an export or erase request for the current user.
	 *
	 * Wraps WP's wp_create_user_request() so the request enters the standard
	 * admin queue at Tools → Export/Erase Personal Data. The site admin still
	 * has to confirm and process the request — this just removes the "email
	 * support" friction for the candidate.
	 *
	 * The actual data shaping is delegated to GdprModule's exporter and eraser
	 * (already registered with WP's privacy API as `wp-career-board`). The user
	 * receives a confirmation email at their account address.
	 *
	 * @since 1.1.1
	 *
	 * @param  \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function request_privacy_action( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$action  = (string) $request->get_param( 'action' );
		$user    = wp_get_current_user();
		$wp_type = 'erase' === $action ? 'remove_personal_data' : 'export_personal_data';

		$request_id = wp_create_user_request( $user->user_email, $wp_type );
		if ( is_wp_error( $request_id ) ) {
			return new \WP_Error(
				'wcb_privacy_request_failed',
				$request_id->get_error_message(),
				array( 'status' => 400 )
			);
		}

		// Send the user the standard confirmation email so they know to expect it.
		wp_send_user_request( $request_id );

		return rest_ensure_response(
			array(
				'request_id' => (int) $request_id,
				'action'     => $action,
				'email'      => $user->user_email,
				'pending'    => true,
			)
		);
	}

	/**
	 * Permission check for the privacy self-service route.
	 *
	 * @since 1.1.1
	 *
	 * @return bool|\WP_Error
	 */
	public function me_logged_in_check(): bool|\WP_Error {
		return is_user_logged_in() ? true : $this->permission_error();
	}
}
