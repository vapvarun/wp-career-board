<?php
/**
 * Jobs REST endpoint — CRUD + bookmark + applications.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Api\Endpoints;

use WCB\Api\RestController;
use WCB\Modules\Boards\BoardsModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles /wcb/v1/jobs REST routes.
 *
 * Provides full CRUD for job listings, bookmark toggling (non-unique usermeta
 * pattern to avoid race conditions), and per-job application listing.
 *
 * @since 1.0.0
 */
final class JobsEndpoint extends RestController {

	/**
	 * Register all /jobs routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/jobs',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/jobs/(?P<id>\d+)',
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
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/jobs/(?P<id>\d+)/bookmark',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'toggle_bookmark' ),
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
			)
		);

		register_rest_route(
			$this->namespace,
			'/jobs/(?P<id>\d+)/applications',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_applications' ),
				'permission_callback' => array( $this, 'view_applications_permissions_check' ),
			)
		);
	}

	/**
	 * List published jobs with optional filters.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ): \WP_REST_Response {
		$args = array(
			'post_type'      => 'wcb_job',
			'post_status'    => 'publish',
			'posts_per_page' => (int) ( $request->get_param( 'per_page' ) ?? 20 ),
			'paged'          => (int) ( $request->get_param( 'page' ) ?? 1 ),
			'tax_query'      => array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'meta_query'     => array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);

		// Support wcb_* prefixed aliases so URL filter params forward transparently to the REST API.
		$search = $request->get_param( 'search' ) ?? $request->get_param( 'wcb_search' );
		if ( $search ) {
			$args['s'] = sanitize_text_field( $search );
		}

		$category = $request->get_param( 'category' ) ?? $request->get_param( 'wcb_category' );
		if ( $category ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'wcb_category',
				'terms'    => array_map( 'sanitize_text_field', explode( ',', $category ) ),
				'field'    => 'slug',
			);
		}

		$type = $request->get_param( 'type' ) ?? $request->get_param( 'wcb_job_type' );
		if ( $type ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'wcb_job_type',
				'terms'    => array_map( 'sanitize_text_field', explode( ',', $type ) ),
				'field'    => 'slug',
			);
		}

		$location = $request->get_param( 'location' ) ?? $request->get_param( 'wcb_location' );
		if ( $location ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'wcb_location',
				'terms'    => array_map( 'sanitize_text_field', explode( ',', $location ) ),
				'field'    => 'slug',
			);
		}

		$experience = $request->get_param( 'experience' ) ?? $request->get_param( 'wcb_experience' );
		if ( $experience ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'wcb_experience',
				'terms'    => array_map( 'sanitize_text_field', explode( ',', $experience ) ),
				'field'    => 'slug',
			);
		}

		if ( $request->get_param( 'remote' ) ) {
			$args['meta_query'][] = array(
				'key'   => '_wcb_remote',
				'value' => '1',
			);
		}

		$salary_min = $request->get_param( 'salary_min' );
		if ( $salary_min ) {
			$args['meta_query'][] = array(
				'key'     => '_wcb_salary_max',
				'value'   => (int) $salary_min,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}

		$salary_max = $request->get_param( 'salary_max' );
		if ( $salary_max ) {
			$args['meta_query'][] = array(
				'key'     => '_wcb_salary_min',
				'value'   => (int) $salary_max,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
		}

		$query = new \WP_Query( $args );
		$jobs  = array_map( array( $this, 'prepare_item_for_response_array' ), $query->posts );

		$response = rest_ensure_response( $jobs );
		$response->header( 'X-WCB-Total', (string) $query->found_posts );
		$response->header( 'X-WCB-TotalPages', (string) $query->max_num_pages );
		return $response;
	}

	/**
	 * Retrieve a single job by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ): \WP_REST_Response|\WP_Error {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || 'wcb_job' !== $post->post_type ) {
			return new \WP_Error(
				'wcb_not_found',
				__( 'Job not found.', 'wp-career-board' ),
				array( 'status' => 404 )
			);
		}
		$this->record_job_view( $post->ID );
		return rest_ensure_response( $this->prepare_item_for_response_array( $post ) );
	}

	/**
	 * Create a new job listing.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ): \WP_REST_Response|\WP_Error {
		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		if ( empty( $title ) ) {
			return new \WP_Error(
				'wcb_missing_title',
				__( 'Job title is required.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		$settings     = get_option( 'wcb_settings', array() );
		$auto_publish = ! empty( $settings['auto_publish_jobs'] );
		$status       = $auto_publish ? 'publish' : 'pending';

		$job_id = wp_insert_post(
			array(
				'post_type'    => 'wcb_job',
				'post_title'   => $title,
				'post_content' => wp_kses_post( (string) ( $request->get_param( 'description' ) ?? '' ) ),
				'post_status'  => $status,
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $job_id ) ) {
			return $job_id;
		}

		// Postmeta.
		$meta = array(
			'_wcb_deadline'        => $request->get_param( 'deadline' ),
			'_wcb_salary_min'      => $request->get_param( 'salary_min' ),
			'_wcb_salary_max'      => $request->get_param( 'salary_max' ),
			'_wcb_salary_currency' => $request->get_param( 'salary_currency' ) ?? 'USD',
			'_wcb_remote'          => $request->get_param( 'remote' ) ? '1' : '0',
			'_wcb_board_id'        => $request->get_param( 'board_id' ) ?? BoardsModule::get_default_board_id(),
		);
		foreach ( $meta as $key => $value ) {
			if ( null !== $value ) {
				update_post_meta( $job_id, $key, $value );
			}
		}

		// Denormalize company name from the employer's linked wcb_company CPT.
		$wcb_company_id = (int) get_user_meta( get_current_user_id(), '_wcb_company_id', true );
		if ( $wcb_company_id ) {
			$wcb_company = get_post( $wcb_company_id );
			if ( $wcb_company instanceof \WP_Post ) {
				update_post_meta( $job_id, '_wcb_company_name', $wcb_company->post_title );
			}
		}

		// Taxonomies.
		$categories = $request->get_param( 'categories' );
		if ( $categories ) {
			wp_set_object_terms( $job_id, (array) $categories, 'wcb_category' );
		}
		$job_types = $request->get_param( 'job_types' );
		if ( $job_types ) {
			wp_set_object_terms( $job_id, (array) $job_types, 'wcb_job_type' );
		}
		$locations = $request->get_param( 'locations' );
		if ( $locations ) {
			wp_set_object_terms( $job_id, (array) $locations, 'wcb_location' );
		}
		$experience_param = $request->get_param( 'experience' );
		if ( $experience_param ) {
			wp_set_object_terms( $job_id, (array) $experience_param, 'wcb_experience' );
		}
		$tags = $request->get_param( 'tags' );
		if ( $tags ) {
			wp_set_object_terms( $job_id, (array) $tags, 'wcb_tag' );
		}

		do_action( 'wcb_job_created', $job_id, $request );

		return rest_ensure_response( $this->prepare_item_for_response_array( get_post( $job_id ) ) );
	}

	/**
	 * Update an existing job listing.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ): \WP_REST_Response|\WP_Error {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || 'wcb_job' !== $post->post_type ) {
			return new \WP_Error(
				'wcb_not_found',
				__( 'Job not found.', 'wp-career-board' ),
				array( 'status' => 404 )
			);
		}

		$data  = array();
		$title = $request->get_param( 'title' );
		if ( null !== $title ) {
			$data['post_title'] = sanitize_text_field( $title );
		}
		$desc = $request->get_param( 'description' );
		if ( null !== $desc ) {
			$data['post_content'] = wp_kses_post( $desc );
		}
		if ( ! empty( $data ) ) {
			$data['ID'] = $post->ID;
			wp_update_post( $data );
		}

		// Postmeta — only update keys present in the request.
		$meta_map = array(
			'deadline'        => '_wcb_deadline',
			'salary_min'      => '_wcb_salary_min',
			'salary_max'      => '_wcb_salary_max',
			'salary_currency' => '_wcb_salary_currency',
			'board_id'        => '_wcb_board_id',
		);
		foreach ( $meta_map as $param => $meta_key ) {
			$value = $request->get_param( $param );
			if ( null !== $value ) {
				update_post_meta( $post->ID, $meta_key, $value );
			}
		}
		$remote = $request->get_param( 'remote' );
		if ( null !== $remote ) {
			update_post_meta( $post->ID, '_wcb_remote', $remote ? '1' : '0' );
		}

		// Taxonomies — only update when parameter is present.
		$taxonomy_map = array(
			'categories' => 'wcb_category',
			'job_types'  => 'wcb_job_type',
			'locations'  => 'wcb_location',
			'experience' => 'wcb_experience',
			'tags'       => 'wcb_tag',
		);
		foreach ( $taxonomy_map as $param => $taxonomy ) {
			$terms = $request->get_param( $param );
			if ( null !== $terms ) {
				wp_set_object_terms( $post->ID, (array) $terms, $taxonomy );
			}
		}

		do_action( 'wcb_job_updated', $post->ID, $request );
		return rest_ensure_response( $this->prepare_item_for_response_array( get_post( $post->ID ) ) );
	}

	/**
	 * Trash a job listing.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ): \WP_REST_Response|\WP_Error {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || 'wcb_job' !== $post->post_type ) {
			return new \WP_Error(
				'wcb_not_found',
				__( 'Job not found.', 'wp-career-board' ),
				array( 'status' => 404 )
			);
		}
		wp_trash_post( $post->ID );
		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $post->ID,
			)
		);
	}

	/**
	 * Toggle bookmark using non-unique usermeta (one row per bookmarked job).
	 *
	 * Avoids race conditions from reading/writing a single array meta value.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response
	 */
	public function toggle_bookmark( \WP_REST_Request $request ): \WP_REST_Response {
		$user_id = get_current_user_id();
		$job_id  = (int) $request['id'];

		// Check if already bookmarked (non-unique key pattern).
		$existing = get_user_meta( $user_id, '_wcb_bookmark', false );
		$existing = array_map( 'intval', (array) $existing );

		if ( in_array( $job_id, $existing, true ) ) {
			delete_user_meta( $user_id, '_wcb_bookmark', $job_id );
			$bookmarked = false;
		} else {
			add_user_meta( $user_id, '_wcb_bookmark', $job_id, false );
			$bookmarked = true;
		}

		return rest_ensure_response(
			array(
				'bookmarked' => $bookmarked,
				'job_id'     => $job_id,
			)
		);
	}

	/**
	 * List applications for a specific job.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_applications( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$job_id = (int) $request['id'];
		$posts  = get_posts(
			array(
				'post_type'      => 'wcb_application',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_wcb_job_id',
						'value' => $job_id,
					),
				),
			)
		);

		$items = array_map(
			static function ( \WP_Post $p ): array {
				return array(
					'id'           => $p->ID,
					'candidate_id' => (int) get_post_meta( $p->ID, '_wcb_candidate_id', true ),
					'status'       => get_post_meta( $p->ID, '_wcb_status', true ),
					'submitted_at' => $p->post_date,
				);
			},
			$posts
		);

		return rest_ensure_response( $items );
	}

	// --- Permission callbacks ---------------------------------------------------

	/**
	 * Check if the current user can create jobs.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function create_item_permissions_check( $request ): bool|\WP_Error {
		return $this->check_ability( 'wcb_post_jobs' ) ? true : $this->permission_error();
	}

	/**
	 * Check if the current user can update the given job.
	 *
	 * Allows the post author (if they have wcb_post_jobs) or an admin
	 * (via wcb_manage_settings ability).
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
		$is_owner = (int) $post->post_author === $this->current_user_id()
			&& $this->check_ability( 'wcb_post_jobs' );
		$is_admin = $this->check_ability( 'wcb_manage_settings' );
		return ( $is_owner || $is_admin ) ? true : $this->permission_error();
	}

	/**
	 * Check if the current user can delete the given job.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function delete_item_permissions_check( $request ): bool|\WP_Error {
		return $this->update_item_permissions_check( $request );
	}

	/**
	 * Check if the current user can view applications.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function view_applications_permissions_check( \WP_REST_Request $request ) {
		return $this->check_ability( 'wcb_view_applications' ) ? true : $this->permission_error();
	}

	// --- Helpers ----------------------------------------------------------------

	/**
	 * Shape a WP_Post into the REST response array.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Job post object.
	 * @return array<string, mixed>
	 */
	private function prepare_item_for_response_array( \WP_Post $post ): array {
		$currency = get_post_meta( $post->ID, '_wcb_salary_currency', true );
		return array(
			'id'              => $post->ID,
			'title'           => $post->post_title,
			'description'     => $post->post_content,
			'status'          => $post->post_status,
			'author'          => (int) $post->post_author,
			'date'            => $post->post_date,
			'permalink'       => get_permalink( $post->ID ),
			'company'         => (string) get_post_meta( $post->ID, '_wcb_company_name', true ),
			'deadline'        => get_post_meta( $post->ID, '_wcb_deadline', true ),
			'salary_min'      => get_post_meta( $post->ID, '_wcb_salary_min', true ),
			'salary_max'      => get_post_meta( $post->ID, '_wcb_salary_max', true ),
			'salary_currency' => $currency ? $currency : 'USD',
			'remote'          => '1' === get_post_meta( $post->ID, '_wcb_remote', true ),
			'board_id'        => (int) get_post_meta( $post->ID, '_wcb_board_id', true ),
			'categories'      => wp_get_object_terms( $post->ID, 'wcb_category', array( 'fields' => 'slugs' ) ),
			'job_types'       => wp_get_object_terms( $post->ID, 'wcb_job_type', array( 'fields' => 'slugs' ) ),
			'locations'       => wp_get_object_terms( $post->ID, 'wcb_location', array( 'fields' => 'slugs' ) ),
			'experience'      => wp_get_object_terms( $post->ID, 'wcb_experience', array( 'fields' => 'slugs' ) ),
			'tags'            => wp_get_object_terms( $post->ID, 'wcb_tag', array( 'fields' => 'slugs' ) ),
			'thumbnail'       => get_the_post_thumbnail_url( $post->ID, 'medium' ) ? get_the_post_thumbnail_url( $post->ID, 'medium' ) : '',
		);
	}

	/**
	 * Define query parameters for the collection endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params(): array {
		return array(
			'search'         => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			// wcb_* aliases: accepted when URL filter params are forwarded directly to the API.
			'wcb_search'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'category'       => array( 'type' => 'string' ),
			'wcb_category'   => array( 'type' => 'string' ),
			'type'           => array( 'type' => 'string' ),
			'wcb_job_type'   => array( 'type' => 'string' ),
			'location'       => array( 'type' => 'string' ),
			'wcb_location'   => array( 'type' => 'string' ),
			'experience'     => array( 'type' => 'string' ),
			'wcb_experience' => array( 'type' => 'string' ),
			'remote'         => array( 'type' => 'boolean' ),
			'salary_min'     => array( 'type' => 'integer' ),
			'salary_max'     => array( 'type' => 'integer' ),
			'page'           => array(
				'type'    => 'integer',
				'default' => 1,
				'minimum' => 1,
			),
			'per_page'       => array(
				'type'    => 'integer',
				'default' => 20,
				'minimum' => 1,
				'maximum' => 100,
			),
		);
	}
}
