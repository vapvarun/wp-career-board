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
	}

	// --- Route callbacks --------------------------------------------------------

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
		foreach ( $bookmark_ids as $job_id ) {
			$post = get_post( $job_id );
			if ( $post instanceof \WP_Post && 'wcb_job' === $post->post_type ) {
				$items[] = array(
					'id'        => $post->ID,
					'title'     => $post->post_title,
					'permalink' => get_permalink( $post->ID ),
					'company'   => (string) get_post_meta( $post->ID, '_wcb_company_name', true ),
				);
			}
		}

		return rest_ensure_response( $items );
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
		return array(
			'id'                 => $user->ID,
			'display_name'       => $user->display_name,
			'bio'                => $user->description,
			'profile_visibility' => $raw_visibility ? $raw_visibility : 'public',
			'avatar'             => get_avatar_url( $user->ID ),
			'resume_data'        => $raw_resume_data ? $raw_resume_data : array(),
		);
	}
}
