<?php
/**
 * Employers REST endpoint — company CRUD and job listing.
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
 * Handles /wcb/v1/employers REST routes.
 *
 * Employer profile (wcb_company) create, read, update, and job listing.
 * All permission checks use the Abilities API.
 *
 * @since 1.0.0
 */
final class EmployersEndpoint extends RestController {

	/**
	 * Register all employer routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/employers',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/employers/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
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
			'/employers/(?P<id>\d+)/jobs',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_jobs' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	// --- Route callbacks --------------------------------------------------------

	/**
	 * Create a new company profile for the current employer.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ): \WP_REST_Response|\WP_Error {
		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
		if ( empty( $name ) ) {
			return new \WP_Error(
				'wcb_missing_name',
				__( 'Company name is required.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		$company_id = wp_insert_post(
			array(
				'post_type'    => 'wcb_company',
				'post_title'   => $name,
				'post_content' => wp_kses_post( (string) ( $request->get_param( 'description' ) ?? '' ) ),
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $company_id ) ) {
			return $company_id;
		}

		$meta_map = array(
			'website'  => '_wcb_website',
			'industry' => '_wcb_industry',
			'size'     => '_wcb_size',
		);
		foreach ( $meta_map as $param => $meta_key ) {
			$value = $request->get_param( $param );
			if ( null !== $value ) {
				update_post_meta( $company_id, $meta_key, sanitize_text_field( (string) $value ) );
			}
		}
		// New companies start at trust level "new".
		update_post_meta( $company_id, '_wcb_trust_level', 'new' );

		// Link company to the employer user account.
		update_user_meta( get_current_user_id(), '_wcb_company_id', $company_id );

		return rest_ensure_response( $this->prepare_company( get_post( $company_id ) ) );
	}

	/**
	 * Retrieve a single company profile.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ): \WP_REST_Response|\WP_Error {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || 'wcb_company' !== $post->post_type ) {
			return new \WP_Error(
				'wcb_not_found',
				__( 'Company not found.', 'wp-career-board' ),
				array( 'status' => 404 )
			);
		}
		return rest_ensure_response( $this->prepare_company( $post ) );
	}

	/**
	 * Update a company profile.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ): \WP_REST_Response|\WP_Error {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || 'wcb_company' !== $post->post_type ) {
			return new \WP_Error(
				'wcb_not_found',
				__( 'Company not found.', 'wp-career-board' ),
				array( 'status' => 404 )
			);
		}

		$data = array( 'ID' => $post->ID );
		$name = $request->get_param( 'name' );
		if ( null !== $name ) {
			$data['post_title'] = sanitize_text_field( (string) $name );
		}
		$desc = $request->get_param( 'description' );
		if ( null !== $desc ) {
			$data['post_content'] = wp_kses_post( (string) $desc );
		}
		if ( count( $data ) > 1 ) {
			wp_update_post( $data );
		}

		$meta_map = array(
			'website'  => '_wcb_website',
			'industry' => '_wcb_industry',
			'size'     => '_wcb_size',
		);
		foreach ( $meta_map as $param => $meta_key ) {
			$value = $request->get_param( $param );
			if ( null !== $value ) {
				update_post_meta( $post->ID, $meta_key, sanitize_text_field( (string) $value ) );
			}
		}

		return rest_ensure_response( $this->prepare_company( get_post( $post->ID ) ) );
	}

	/**
	 * List published jobs belonging to a company.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_jobs( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$company = get_post( (int) $request['id'] );
		if ( ! $company || 'wcb_company' !== $company->post_type ) {
			return new \WP_Error(
				'wcb_not_found',
				__( 'Company not found.', 'wp-career-board' ),
				array( 'status' => 404 )
			);
		}

		// Public endpoint — only expose published jobs; owner/admin also see pending/draft.
		$is_owner    = get_current_user_id() === (int) $company->post_author;
		$is_admin    = $this->check_ability( 'wcb_manage_settings' );
		$post_status = ( $is_owner || $is_admin )
			? array( 'publish', 'pending', 'draft' )
			: array( 'publish' );

		$per_page = min( (int) ( $request->get_param( 'per_page' ) ?? 20 ), 100 );
		$paged    = max( (int) ( $request->get_param( 'page' ) ?? 1 ), 1 );

		$query = new \WP_Query(
			array(
				'post_type'      => 'wcb_job',
				'post_author'    => (int) $company->post_author,
				'post_status'    => $post_status,
				'posts_per_page' => $per_page,
				'paged'          => $paged,
			)
		);

		$items = array_map(
			static function ( \WP_Post $p ): array {
				return array(
					'id'     => $p->ID,
					'title'  => $p->post_title,
					'status' => $p->post_status,
				);
			},
			$query->posts
		);

		$response = rest_ensure_response( $items );
		$response->header( 'X-WCB-Total', (string) $query->found_posts );
		$response->header( 'X-WCB-TotalPages', (string) $query->max_num_pages );
		return $response;
	}

	// --- Permission callbacks ---------------------------------------------------

	/**
	 * Check if the current user can create a company.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function create_item_permissions_check( $request ): bool|\WP_Error {
		return $this->check_ability( 'wcb_manage_company' ) ? true : $this->permission_error();
	}

	/**
	 * Check if the current user can update the given company.
	 *
	 * Allows the company author or an admin.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function update_item_permissions_check( $request ): bool|\WP_Error {
		$post = get_post( (int) $request['id'] );
		if ( ! $post ) {
			return $this->permission_error();
		}
		$is_owner = get_current_user_id() === (int) $post->post_author
			&& $this->check_ability( 'wcb_manage_company' );
		$is_admin = $this->check_ability( 'wcb_manage_settings' );
		return ( $is_owner || $is_admin ) ? true : $this->permission_error();
	}

	// --- Helpers ----------------------------------------------------------------

	/**
	 * Shape a WP_Post (wcb_company) into the REST response array.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post|null $post Company post object.
	 * @return array<string, mixed>
	 */
	private function prepare_company( ?\WP_Post $post ): array {
		if ( ! $post ) {
			return array();
		}
		$logo        = get_the_post_thumbnail_url( $post->ID, 'medium' );
		$trust_level = (string) get_post_meta( $post->ID, '_wcb_trust_level', true );
		return array(
			'id'          => $post->ID,
			'name'        => $post->post_title,
			'description' => $post->post_content,
			'logo'        => $logo ? $logo : '',
			'website'     => (string) get_post_meta( $post->ID, '_wcb_website', true ),
			'industry'    => (string) get_post_meta( $post->ID, '_wcb_industry', true ),
			'size'        => (string) get_post_meta( $post->ID, '_wcb_size', true ),
			'trust_level' => $trust_level ? $trust_level : 'new',
			'permalink'   => get_permalink( $post->ID ),
		);
	}
}
