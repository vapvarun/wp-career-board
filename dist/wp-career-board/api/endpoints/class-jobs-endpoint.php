<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated endpoint name follows project autoloader convention.
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

		add_action(
			'save_post_wcb_job',
			static function (): void {
				$v = (int) get_option( 'wcb_jobs_cache_v', 0 );
				update_option( 'wcb_jobs_cache_v', $v + 1, false );
			}
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

		$author = $request->get_param( 'author' );
		if ( $author ) {
			$args['author'] = (int) $author;
		}

		$orderby = $request->get_param( 'orderby' );
		if ( $orderby ) {
			$args['orderby'] = (string) $orderby;
			$args['order']   = 'ASC' === strtoupper( (string) $request->get_param( 'order' ) ) ? 'ASC' : 'DESC';
		}

		$cache_key    = $this->get_items_cache_key( $args );
		$cached_value = get_transient( $cache_key );

		if ( false !== $cached_value && is_array( $cached_value ) ) {
			$response = rest_ensure_response( $cached_value['jobs'] );
			$response->header( 'X-WCB-Total', $cached_value['total'] );
			$response->header( 'X-WCB-TotalPages', $cached_value['pages'] );
			$response->header( 'Cache-Control', 'public, max-age=300' );
			return $response;
		}

		if ( ! empty( $args['s'] ) ) {
			add_filter( 'posts_search', array( $this, 'extend_search_to_company' ), 10, 2 );
		}

		$query = new \WP_Query( $args );

		remove_filter( 'posts_search', array( $this, 'extend_search_to_company' ), 10 );

		$jobs = array_map( array( $this, 'prepare_item_for_response_array' ), $query->posts );
		$jobs = (array) apply_filters( 'wcb_jobs_post_filter', $jobs, $query, $request );

		set_transient(
			$cache_key,
			array(
				'jobs'  => $jobs,
				'total' => (string) $query->found_posts,
				'pages' => (string) $query->max_num_pages,
			),
			5 * MINUTE_IN_SECONDS
		);

		$response = rest_ensure_response( $jobs );
		$response->header( 'X-WCB-Total', (string) $query->found_posts );
		$response->header( 'X-WCB-TotalPages', (string) $query->max_num_pages );
		$response->header( 'Cache-Control', 'public, max-age=300' );
		return $response;
	}

	/**
	 * Extend keyword search to include the denormalized company name meta.
	 *
	 * Appended as a temporary posts_search filter in get_items() so the
	 * subquery only fires on wcb_job searches that have an 's' param.
	 *
	 * @since 1.0.0
	 *
	 * @param string    $search Existing SQL search clause.
	 * @param \WP_Query $query  Current WP_Query instance.
	 * @return string
	 */
	public function extend_search_to_company( string $search, \WP_Query $query ): string {
		global $wpdb;

		if ( ! $search || 'wcb_job' !== $query->get( 'post_type' ) ) {
			return $search;
		}

		$term = (string) $query->get( 's' );
		$like = '%' . $wpdb->esc_like( $term ) . '%';

		$search .= $wpdb->prepare(
			" OR EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} pm
				WHERE pm.post_id = {$wpdb->posts}.ID
				  AND pm.meta_key = '_wcb_company_name'
				  AND pm.meta_value LIKE %s
			)",
			$like
		);

		return $search;
	}

	/**
	 * Build a version-namespaced transient key for a job listing query.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args WP_Query args array.
	 * @return string
	 */
	private function get_items_cache_key( array $args ): string {
		$version = (int) get_option( 'wcb_jobs_cache_v', 0 );
		return 'wcb_jobs_' . $version . '_' . md5( (string) wp_json_encode( $args ) );
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
		$single_response = rest_ensure_response( $this->prepare_item_for_response_array( $post ) );
		$single_response->header( 'Cache-Control', 'public, max-age=3600' );
		return $single_response;
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
		// Honeypot check — bots that fill all form fields will include this; real browsers leave it empty.
		if ( ! empty( $request->get_param( 'hp' ) ) ) {
			return new \WP_Error( 'wcb_spam', __( 'Spam detected.', 'wp-career-board' ), array( 'status' => 400 ) );
		}

		$wcb_spam = apply_filters( 'wcb_pre_job_submit', null, $request );
		if ( is_wp_error( $wcb_spam ) ) {
			return $wcb_spam;
		}

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
		$salary_type_raw  = $request->get_param( 'salary_type' );
		$wcb_deadline_raw = $request->get_param( 'deadline' );
		if ( empty( $wcb_deadline_raw ) ) {
			$expire_days      = ! empty( $settings['jobs_expire_days'] ) ? (int) $settings['jobs_expire_days'] : 30;
			$wcb_deadline_raw = gmdate( 'Y-m-d', strtotime( '+' . $expire_days . ' days' ) );
		}
		$meta = array(
			'_wcb_deadline'        => $wcb_deadline_raw,
			'_wcb_salary_min'      => $request->get_param( 'salary_min' ),
			'_wcb_salary_max'      => $request->get_param( 'salary_max' ),
			'_wcb_salary_currency' => $request->get_param( 'salary_currency' ) ?? 'USD',
			'_wcb_salary_type'     => in_array( $salary_type_raw, array( 'yearly', 'monthly', 'hourly' ), true ) ? $salary_type_raw : 'yearly',
			'_wcb_remote'          => $request->get_param( 'remote' ) ? '1' : '0',
			'_wcb_board_id'        => $request->get_param( 'board_id' ) ?? BoardsModule::get_default_board_id(),
		);
		foreach ( $meta as $key => $value ) {
			if ( null !== $value ) {
				update_post_meta( $job_id, $key, $value );
			}
		}

		// Apply destination.
		$wcb_apply_url = esc_url_raw( (string) ( $request->get_param( 'apply_url' ) ?? '' ) );
		if ( $wcb_apply_url ) {
			update_post_meta( $job_id, '_wcb_apply_url', $wcb_apply_url );
		}
		$wcb_apply_email = sanitize_email( (string) ( $request->get_param( 'apply_email' ) ?? '' ) );
		if ( $wcb_apply_email ) {
			update_post_meta( $job_id, '_wcb_apply_email', $wcb_apply_email );
		}

		// Link employer's company CPT to the job so the single page can render description and website.
		$wcb_company_id = (int) get_user_meta( get_current_user_id(), '_wcb_company_id', true );
		if ( $wcb_company_id ) {
			$wcb_company = get_post( $wcb_company_id );
			if ( $wcb_company instanceof \WP_Post ) {
				update_post_meta( $job_id, '_wcb_company_id', $wcb_company_id );
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

		// Remember the employer's preferred currency for future job posts.
		update_user_meta( get_current_user_id(), '_wcb_preferred_currency', $meta['_wcb_salary_currency'] );

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
		$status = $request->get_param( 'status' );
		if ( null !== $status && in_array( $status, array( 'publish', 'draft' ), true ) ) {
			$data['post_status'] = $status;
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
			'salary_type'     => '_wcb_salary_type',
			'board_id'        => '_wcb_board_id',
		);
		foreach ( $meta_map as $param => $meta_key ) {
			$value = $request->get_param( $param );
			if ( null !== $value ) {
				update_post_meta( $post->ID, $meta_key, $value );
			}
		}

		// Apply destination — sanitize identically to create_item().
		$apply_url = $request->get_param( 'apply_url' );
		if ( null !== $apply_url ) {
			update_post_meta( $post->ID, '_wcb_apply_url', esc_url_raw( (string) $apply_url ) );
		}
		$apply_email = $request->get_param( 'apply_email' );
		if ( null !== $apply_email ) {
			update_post_meta( $post->ID, '_wcb_apply_email', sanitize_email( (string) $apply_email ) );
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

		// Update employer's preferred currency if they changed it.
		$updated_currency = $request->get_param( 'salary_currency' );
		if ( $updated_currency ) {
			update_user_meta( (int) $post->post_author, '_wcb_preferred_currency', sanitize_text_field( $updated_currency ) );
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
		// Allow add-ons to clean up when a job is trashed.
		do_action( 'wcb_job_deleted', $post->ID );
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
				$candidate_id   = (int) get_post_meta( $p->ID, '_wcb_candidate_id', true );
				$candidate_user = $candidate_id > 0 ? get_user_by( 'ID', $candidate_id ) : null;
				$status_raw     = (string) get_post_meta( $p->ID, '_wcb_status', true );

				return array(
					'id'              => $p->ID,
					'candidate_id'    => $candidate_id,
					'applicant_name'  => $candidate_user
						? $candidate_user->display_name
						: (string) get_post_meta( $p->ID, '_wcb_guest_name', true ),
					'applicant_email' => $candidate_user
						? $candidate_user->user_email
						: (string) get_post_meta( $p->ID, '_wcb_guest_email', true ),
					'cover_letter'    => (string) get_post_meta( $p->ID, '_wcb_cover_letter', true ),
					'status'          => '' !== $status_raw ? $status_raw : 'submitted',
					'submitted_at'    => get_the_date( 'M j, Y', $p ),
					'resume_url'      => ( static function () use ( $p ): ?string {
						$att_id = (int) get_post_meta( $p->ID, '_wcb_resume_attachment_id', true );
						if ( $att_id <= 0 ) {
							return null;
						}
						$url = wp_get_attachment_url( $att_id );
						return false !== $url ? $url : null;
					} )(),
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
	 * Returns both slug-indexed taxonomy arrays (for filtering) and
	 * display-name strings (for card rendering) so the frontend never needs
	 * secondary lookups.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Job post object.
	 * @return array<string, mixed>
	 */
	private function prepare_item_for_response_array( \WP_Post $post ): array {
		$currency     = (string) get_post_meta( $post->ID, '_wcb_salary_currency', true );
		$currency     = '' !== $currency ? $currency : 'USD';
		$salary_min   = (string) get_post_meta( $post->ID, '_wcb_salary_min', true );
		$salary_max   = (string) get_post_meta( $post->ID, '_wcb_salary_max', true );
		$salary_type  = (string) get_post_meta( $post->ID, '_wcb_salary_type', true );
		$salary_type  = in_array( $salary_type, array( 'yearly', 'monthly', 'hourly' ), true ) ? $salary_type : 'yearly';
		$company_name = (string) get_post_meta( $post->ID, '_wcb_company_name', true );
		$author_id    = (int) $post->post_author;
		$company_id   = (int) get_user_meta( $author_id, '_wcb_company_id', true );
		$trust        = $company_id ? (string) get_post_meta( $company_id, '_wcb_trust_level', true ) : '';

		// Human-readable taxonomy labels for card display.
		$loc_terms  = wp_get_object_terms( $post->ID, 'wcb_location', array( 'fields' => 'names' ) );
		$type_terms = wp_get_object_terms( $post->ID, 'wcb_job_type', array( 'fields' => 'names' ) );
		$exp_terms  = wp_get_object_terms( $post->ID, 'wcb_experience', array( 'fields' => 'names' ) );
		$cat_terms  = wp_get_object_terms( $post->ID, 'wcb_category', array( 'fields' => 'names' ) );

		$thumbnail_url = get_the_post_thumbnail_url( $post->ID, 'medium' );
		$board_id      = (int) apply_filters( 'wcb_job_board_id', (int) get_post_meta( $post->ID, '_wcb_board_id', true ), $post->ID );

		return array(
			'id'               => $post->ID,
			'title'            => $post->post_title,
			'description'      => $post->post_content,
			'excerpt'          => wp_trim_words( wp_strip_all_tags( $post->post_content ), 25, '…' ),
			'status'           => $post->post_status,
			'author'           => $author_id,
			'date'             => $post->post_date,
			'permalink'        => get_permalink( $post->ID ),
			// Company fields.
			'company'          => $company_name,
			'initials'         => $this->company_initials( $company_name ),
			'verified'         => in_array( $trust, array( 'verified', 'trusted', 'premium' ), true ),
			// Job meta.
			'deadline'         => get_post_meta( $post->ID, '_wcb_deadline', true ),
			'salary_min'       => $salary_min,
			'salary_max'       => $salary_max,
			'salary_currency'  => $currency,
			'salary_type'      => $salary_type,
			'salary_label'     => $this->format_salary( $salary_min, $salary_max, $currency, $salary_type ),
			'remote'           => '1' === get_post_meta( $post->ID, '_wcb_remote', true ),
			'featured'         => '1' === get_post_meta( $post->ID, '_wcb_featured', true ),
			'board_id'         => $board_id,
			'board_currency'   => function_exists( 'wcbp_get_board_currency' ) ? wcbp_get_board_currency( $board_id ) : 'USD',
			// Display-name strings for cards.
			'location'         => is_wp_error( $loc_terms ) ? '' : implode( ', ', $loc_terms ),
			'type'             => is_wp_error( $type_terms ) ? '' : implode( ', ', $type_terms ),
			'experience'       => is_wp_error( $exp_terms ) ? '' : implode( ', ', $exp_terms ),
			'category'         => is_wp_error( $cat_terms ) ? '' : implode( ', ', $cat_terms ),
			// Relative time.
			'days_ago'         => human_time_diff( (int) strtotime( $post->post_date ), time() ) . ' ago',
			// Slug arrays for filter/API consumers.
			'categories'       => wp_get_object_terms( $post->ID, 'wcb_category', array( 'fields' => 'slugs' ) ),
			'job_types'        => wp_get_object_terms( $post->ID, 'wcb_job_type', array( 'fields' => 'slugs' ) ),
			'locations'        => wp_get_object_terms( $post->ID, 'wcb_location', array( 'fields' => 'slugs' ) ),
			'experience_slugs' => wp_get_object_terms( $post->ID, 'wcb_experience', array( 'fields' => 'slugs' ) ),
			'tags'             => wp_get_object_terms( $post->ID, 'wcb_tag', array( 'fields' => 'slugs' ) ),
			'thumbnail'        => false !== $thumbnail_url ? (string) $thumbnail_url : '',
			'apply_url'        => (string) get_post_meta( $post->ID, '_wcb_apply_url', true ),
			'apply_email'      => (string) get_post_meta( $post->ID, '_wcb_apply_email', true ),
			'lat'              => (float) get_post_meta( $post->ID, '_wcb_lat', true ),
			'lng'              => (float) get_post_meta( $post->ID, '_wcb_lng', true ),
		);
	}

	/**
	 * Get company initials (up to 2 chars) from a company name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Company name.
	 * @return string
	 */
	private function company_initials( string $name ): string {
		$words = array_filter( explode( ' ', trim( $name ) ) );
		$init  = '';
		foreach ( array_slice( $words, 0, 2 ) as $word ) {
			$init .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
		}
		return '' !== $init ? $init : '?';
	}

	/**
	 * Format a salary range as a human-readable label.
	 *
	 * @since 1.0.0
	 *
	 * @param string $min      Minimum salary.
	 * @param string $max      Maximum salary.
	 * @param string $currency Currency code.
	 * @param string $type     Salary type: yearly, monthly, or hourly.
	 * @return string
	 */
	private function format_salary( string $min, string $max, string $currency, string $type = 'yearly' ): string {
		if ( ! $min && ! $max ) {
			return '';
		}
		$symbols = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'CAD' => 'CA$',
			'AUD' => 'A$',
			'INR' => '₹',
			'SGD' => 'S$',
		);
		$symbol  = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : $currency . ' ';
		$suffix  = match ( $type ) {
			'monthly' => '/mo',
			'hourly'  => '/hr',
			default   => '/yr',
		};
		$fmt = static function ( string $n ) use ( $symbol ): string {
			$val = (int) $n;
			return $val >= 1000 ? $symbol . round( $val / 1000 ) . 'k' : $symbol . $val;
		};
		if ( $min && $max ) {
			return $fmt( $min ) . '–' . $fmt( $max ) . $suffix;
		}
		return $min ? $fmt( $min ) . '+' . $suffix : 'Up to ' . $fmt( $max ) . $suffix;
	}

	/**
	 * Describe the shape of a single job item.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'wcb_job',
			'type'       => 'object',
			'properties' => array(
				'id'          => array(
					'description' => __( 'Unique identifier for the job.', 'wp-career-board' ),
					'type'        => 'integer',
					'readonly'    => true,
					'context'     => array( 'view', 'embed' ),
				),
				'title'       => array(
					'description' => __( 'Job title.', 'wp-career-board' ),
					'type'        => 'string',
					'context'     => array( 'view', 'embed' ),
				),
				'description' => array(
					'description' => __( 'Full job description.', 'wp-career-board' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
				),
				'excerpt'     => array(
					'description' => __( 'Short excerpt of the job description.', 'wp-career-board' ),
					'type'        => 'string',
					'readonly'    => true,
					'context'     => array( 'view', 'embed' ),
				),
			),
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
		return (array) apply_filters(
			'wcb_jobs_collection_params',
			array(
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
				'author'         => array( 'type' => 'integer' ),
				'orderby'        => array(
					'description'       => __( 'Sort jobs by attribute.', 'wp-career-board' ),
					'type'              => 'string',
					'default'           => 'date',
					'enum'              => array( 'date' ),
					'sanitize_callback' => 'sanitize_key',
					'validate_callback' => 'rest_validate_request_arg',
				),
				'order'          => array(
					'description'       => __( 'Order jobs ascending or descending.', 'wp-career-board' ),
					'type'              => 'string',
					'default'           => 'DESC',
					'enum'              => array( 'ASC', 'DESC' ),
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				),
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
			)
		);
	}
}
