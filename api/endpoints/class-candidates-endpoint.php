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
			'/candidates/register',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'register_candidate' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'first_name' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'last_name'  => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'email'      => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_email',
					),
					'password'   => array(
						'type'     => 'string',
						'required' => true,
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

		$settings      = (array) get_option( 'wcb_settings', array() );
		$dashboard_url = ! empty( $settings['candidate_dashboard_page'] )
			? (string) get_permalink( (int) $settings['candidate_dashboard_page'] )
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
		$is_admin       = $this->check_ability( 'wcb_manage_settings' );
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
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'permalink' => get_permalink( $post->ID ),
				'company'   => (string) get_post_meta( $post->ID, '_wcb_company_name', true ),
				'location'  => implode( ', ', $loc_names ),
				'type'      => implode( ', ', $type_names ),
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
		$is_admin  = $this->check_ability( 'wcb_manage_settings' );
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
		$is_admin  = $this->check_ability( 'wcb_manage_settings' );
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
