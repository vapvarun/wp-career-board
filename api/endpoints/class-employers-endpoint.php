<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated endpoint name follows project autoloader convention.
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
			'/employers/register',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'register_employer' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'first_name'   => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'last_name'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'email'        => array(
						'type'              => 'string',
						'required'          => true,
						'format'            => 'email',
						'sanitize_callback' => 'sanitize_email',
					),
					'company_name' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'password'     => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

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

		register_rest_route(
			$this->namespace,
			'/employers/(?P<id>\d+)/applications',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_applications' ),
				'permission_callback' => array( $this, 'get_applications_permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/employers/(?P<id>\d+)/logo',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'upload_logo' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/employers/me/jobs',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_my_jobs' ),
				'permission_callback' => array( $this, 'get_my_jobs_permissions_check' ),
			)
		);
	}

	// --- Route callbacks --------------------------------------------------------

	/**
	 * Register a new employer user with a company profile.
	 *
	 * Open endpoint — requires no authentication.
	 * Creates a WordPress user, assigns the wcb_employer role, creates a
	 * wcb_company post, and logs the new user in via auth cookies.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function register_employer( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! get_option( 'users_can_register', false ) && ! ( defined( 'MULTISITE' ) && MULTISITE ) ) {
			return new \WP_Error(
				'wcb_registration_disabled',
				__( 'User registration is currently disabled.', 'wp-career-board' ),
				array( 'status' => 403 )
			);
		}

		$first_name   = (string) $request->get_param( 'first_name' );
		$last_name    = (string) $request->get_param( 'last_name' );
		$email        = (string) $request->get_param( 'email' );
		$company_name = (string) $request->get_param( 'company_name' );
		$password     = (string) $request->get_param( 'password' );

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
				'role'         => 'wcb_employer',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return new \WP_Error(
				'wcb_registration_failed',
				$user_id->get_error_message(),
				array( 'status' => 500 )
			);
		}

		// Create the employer's company profile and link it to the user.
		$company_id = wp_insert_post(
			array(
				'post_type'   => 'wcb_company',
				'post_title'  => $company_name,
				'post_status' => 'publish',
				'post_author' => $user_id,
			)
		);

		if ( $company_id && ! is_wp_error( $company_id ) ) {
			update_user_meta( $user_id, '_wcb_company_id', $company_id );

			$reg_meta = array(
				'website'  => '_wcb_website',
				'industry' => '_wcb_industry',
				'size'     => '_wcb_company_size',
				'hq'       => '_wcb_hq_location',
			);
			foreach ( $reg_meta as $param => $meta_key ) {
				$val = $request->get_param( $param );
				if ( $val ) {
					update_post_meta( $company_id, $meta_key, sanitize_text_field( (string) $val ) );
				}
			}
		}

		// Authenticate the new user immediately.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, false );

		$resolved_company_id = ( $company_id && ! is_wp_error( $company_id ) ) ? (int) $company_id : 0;
		do_action( 'wcb_employer_registered', $user_id, $resolved_company_id );

		$settings      = (array) get_option( 'wcb_settings', array() );
		$dashboard_url = ! empty( $settings['employer_dashboard_page'] )
			? (string) get_permalink( (int) $settings['employer_dashboard_page'] )
			: home_url( '/' );

		return rest_ensure_response(
			array(
				'user_id'       => $user_id,
				'company_id'    => $resolved_company_id,
				'dashboard_url' => $dashboard_url,
			)
		);
	}

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
			'website'      => '_wcb_website',
			'industry'     => '_wcb_industry',
			'size'         => '_wcb_company_size',
			'tagline'      => '_wcb_tagline',
			'hq'           => '_wcb_hq_location',
			'company_type' => '_wcb_company_type',
			'founded'      => '_wcb_founded',
			'linkedin'     => '_wcb_linkedin',
			'twitter'      => '_wcb_twitter',
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
			'website'      => '_wcb_website',
			'industry'     => '_wcb_industry',
			'size'         => '_wcb_company_size',
			'tagline'      => '_wcb_tagline',
			'hq'           => '_wcb_hq_location',
			'company_type' => '_wcb_company_type',
			'founded'      => '_wcb_founded',
			'linkedin'     => '_wcb_linkedin',
			'twitter'      => '_wcb_twitter',
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
	 * List jobs for the currently authenticated employer.
	 *
	 * When the employer has a linked company, delegates to get_jobs() so the
	 * response shape is identical (includes appCount, editUrl, etc.). When no
	 * company exists yet the employer may still have pending/published jobs
	 * (posted before they created a profile), so we query directly by author.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_my_jobs( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id    = get_current_user_id();
		$company_id = (int) get_user_meta( $user_id, '_wcb_company_id', true );
		if ( $company_id ) {
			$company = get_post( $company_id );
			if ( $company instanceof \WP_Post && 'wcb_company' === $company->post_type ) {
				$request->set_param( 'id', $company_id );
				return $this->get_jobs( $request );
			}
		}

		// No company yet — return jobs authored by the current user.
		$per_page     = min( (int) ( $request->get_param( 'per_page' ) ?? 20 ), 100 );
		$paged        = max( (int) ( $request->get_param( 'page' ) ?? 1 ), 1 );
		$wcb_form_url = $this->get_job_form_page_url();

		$query = new \WP_Query(
			array(
				'post_type'      => 'wcb_job',
				'author'         => (int) $user_id,
				'post_status'    => array( 'publish', 'pending', 'draft' ),
				'posts_per_page' => $per_page,
				'paged'          => $paged,
			)
		);

		$items = array_map(
			static function ( \WP_Post $p ) use ( $wcb_form_url ): array {
				$location_terms = wp_get_object_terms( $p->ID, 'wcb_location', array( 'fields' => 'names' ) );
				$type_terms     = wp_get_object_terms( $p->ID, 'wcb_job_type', array( 'fields' => 'names' ) );
				$deadline_raw   = (string) get_post_meta( $p->ID, '_wcb_deadline', true );
				$status_labels  = array(
					'publish' => 'Published',
					'draft'   => 'Draft',
					'pending' => 'Pending',
					'private' => 'Private',
				);
				return array(
					'id'          => $p->ID,
					'title'       => $p->post_title,
					'status'      => $p->post_status,
					'statusLabel' => $status_labels[ $p->post_status ] ?? ucfirst( $p->post_status ),
					'permalink'   => get_permalink( $p->ID ),
					'editUrl'     => add_query_arg( 'edit', $p->ID, $wcb_form_url ),
					'appCount'    => 0,
					'appLabel'    => __( 'No applicants', 'wp-career-board' ),
					'location'    => is_wp_error( $location_terms ) ? '' : implode( ', ', $location_terms ),
					'type'        => is_wp_error( $type_terms ) ? '' : implode( ', ', $type_terms ),
					'deadline'    => '' !== $deadline_raw ? $deadline_raw : null,
				);
			},
			$query->posts
		);

		$response = rest_ensure_response( $items );
		$response->header( 'X-WCB-Total', (string) $query->found_posts );
		$response->header( 'X-WCB-TotalPages', (string) $query->max_num_pages );
		return $response;
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
		$is_owner    = is_user_logged_in() && (int) get_user_meta( get_current_user_id(), '_wcb_company_id', true ) === (int) $company->ID;
		$is_admin    = $this->check_ability( 'wcb_manage_settings' );
		$post_status = ( $is_owner || $is_admin )
			? array( 'publish', 'pending', 'draft' )
			: array( 'publish' );

		$per_page = min( (int) ( $request->get_param( 'per_page' ) ?? 20 ), 100 );
		$paged    = max( (int) ( $request->get_param( 'page' ) ?? 1 ), 1 );

		$query = new \WP_Query(
			array(
				'post_type'      => 'wcb_job',
				'post_status'    => $post_status,
				'posts_per_page' => $per_page,
				'paged'          => $paged,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_wcb_company_id',
						'value'   => (int) $company->ID,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		// Fetch application counts for all retrieved jobs in one query.
		$job_ids    = wp_list_pluck( $query->posts, 'ID' );
		$app_counts = array();
		if ( ! empty( $job_ids ) ) {
			global $wpdb;
			$placeholders = implode( ',', array_fill( 0, count( $job_ids ), '%d' ) );
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$sql  = $wpdb->prepare( "SELECT meta_value AS job_id, COUNT(*) AS cnt FROM {$wpdb->postmeta} WHERE meta_key = '_wcb_job_id' AND meta_value IN ({$placeholders}) GROUP BY meta_value", ...$job_ids );
			$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			foreach ( $rows as $row ) {
				$app_counts[ (int) $row->job_id ] = (int) $row->cnt;
			}
		}

		$wcb_job_form_url = $this->get_job_form_page_url();

		$items = array_map(
			static function ( \WP_Post $p ) use ( $app_counts, $wcb_job_form_url ): array {
				$location_terms = wp_get_object_terms( $p->ID, 'wcb_location', array( 'fields' => 'names' ) );
				$type_terms     = wp_get_object_terms( $p->ID, 'wcb_job_type', array( 'fields' => 'names' ) );
				$app_count      = $app_counts[ $p->ID ] ?? 0;
				$deadline_raw   = (string) get_post_meta( $p->ID, '_wcb_deadline', true );

				$status_labels = array(
					'publish' => 'Published',
					'draft'   => 'Draft',
					'pending' => 'Pending',
					'private' => 'Private',
				);

				return array(
					'id'          => $p->ID,
					'title'       => $p->post_title,
					'status'      => $p->post_status,
					'statusLabel' => $status_labels[ $p->post_status ] ?? ucfirst( $p->post_status ),
					'permalink'   => get_permalink( $p->ID ),
					'editUrl'     => add_query_arg( 'edit', $p->ID, $wcb_job_form_url ),
					'appCount'    => $app_count,
					'appLabel'    => $app_count > 0
						? sprintf( '%d %s', $app_count, _n( 'applicant', 'applicants', $app_count, 'wp-career-board' ) )
						: __( 'No applicants', 'wp-career-board' ),
					'location'    => is_wp_error( $location_terms ) ? '' : implode( ', ', $location_terms ),
					'type'        => is_wp_error( $type_terms ) ? '' : implode( ', ', $type_terms ),
					'deadline'    => '' !== $deadline_raw ? $deadline_raw : null,
				);
			},
			$query->posts
		);

		$response = rest_ensure_response( $items );
		$response->header( 'X-WCB-Total', (string) $query->found_posts );
		$response->header( 'X-WCB-TotalPages', (string) $query->max_num_pages );
		return $response;
	}

	/**
	 * Return all applications across all jobs owned by the given company.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_applications( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$company = get_post( (int) $request['id'] );
		if ( ! $company || 'wcb_company' !== $company->post_type ) {
			return new \WP_Error(
				'wcb_not_found',
				__( 'Company not found.', 'wp-career-board' ),
				array( 'status' => 404 )
			);
		}

		// Fetch all jobs for this employer (any status).
		$job_ids = get_posts(
			array(
				'post_type'      => 'wcb_job',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_wcb_company_id',
						'value'   => (int) $company->ID,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
				'post_status'    => array( 'publish', 'pending', 'draft' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		if ( empty( $job_ids ) ) {
			return rest_ensure_response( array() );
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $job_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$sql  = $wpdb->prepare(
			"SELECT p.ID, p.post_date FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wcb_job_id' WHERE p.post_type = 'wcb_application' AND p.post_status = 'publish' AND pm.meta_value IN ({$placeholders}) ORDER BY p.post_date DESC LIMIT 20",
			...$job_ids
		);
		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$items = array_map(
			static function ( object $row ): array {
				$app_id         = (int) $row->ID;
				$candidate_id   = (int) get_post_meta( $app_id, '_wcb_candidate_id', true );
				$candidate_user = $candidate_id > 0 ? get_user_by( 'ID', $candidate_id ) : null;
				$status_raw     = (string) get_post_meta( $app_id, '_wcb_status', true );
				$job_id         = (int) get_post_meta( $app_id, '_wcb_job_id', true );

				return array(
					'id'              => $app_id,
					'job_id'          => $job_id,
					'job_title'       => $job_id > 0 ? get_the_title( $job_id ) : '',
					'applicant_name'  => $candidate_user
						? $candidate_user->display_name
						: (string) get_post_meta( $app_id, '_wcb_guest_name', true ),
					'applicant_email' => $candidate_user
						? $candidate_user->user_email
						: (string) get_post_meta( $app_id, '_wcb_guest_email', true ),
					'status'          => '' !== $status_raw ? $status_raw : 'submitted',
					'submitted_at'    => get_the_date( 'M j, Y', $app_id ),
				);
			},
			$rows
		);

		return rest_ensure_response( $items );
	}

	/**
	 * Check if the current user can view applications for the given company.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function get_applications_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
		$post     = get_post( (int) $request['id'] );
		$is_owner = $post && get_current_user_id() === (int) $post->post_author
			&& $this->check_ability( 'wcb_view_applications' );
		$is_admin = $this->check_ability( 'wcb_manage_settings' );
		return ( $is_owner || $is_admin ) ? true : $this->permission_error();
	}

	/**
	 * Check if the current user can list their own jobs.
	 *
	 * Intended for the employer dashboard "My Jobs" tab.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function get_my_jobs_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
		return $this->check_ability( 'wcb_access_employer_dashboard' ) ? true : $this->permission_error();
	}

	/**
	 * Upload a logo image and set it as the company post thumbnail.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function upload_logo( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || 'wcb_company' !== $post->post_type ) {
			return new \WP_Error(
				'wcb_not_found',
				__( 'Company not found.', 'wp-career-board' ),
				array( 'status' => 404 )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- REST API verifies nonce via X-WP-Nonce header in permission_callback.
		if ( empty( $_FILES['logo'] ) ) {
			return new \WP_Error(
				'wcb_no_file',
				__( 'No file uploaded.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'logo', $post->ID );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		set_post_thumbnail( $post->ID, $attachment_id );

		return rest_ensure_response(
			array( 'logo_url' => (string) get_the_post_thumbnail_url( $post->ID, 'medium' ) )
		);
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
	 * Resolve the permalink of the page containing the wcb/job-form block.
	 *
	 * Searches published pages for the block comment. Falls back to home_url()
	 * when no matching page is found. Called once per get_jobs() invocation.
	 *
	 * @since 1.0.0
	 * @return string Absolute URL.
	 */
	private function get_job_form_page_url(): string {
		$settings = (array) get_option( 'wcb_settings', array() );
		if ( ! empty( $settings['post_job_page'] ) ) {
			return (string) get_permalink( (int) $settings['post_job_page'] );
		}

		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				's'              => '<!-- wp:wcb/job-form',
			)
		);
		return ! empty( $pages ) ? (string) get_permalink( $pages[0]->ID ) : home_url( '/' );
	}

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
			'id'           => $post->ID,
			'name'         => $post->post_title,
			'description'  => $post->post_content,
			'logo'         => $logo ? $logo : '',
			'tagline'      => (string) get_post_meta( $post->ID, '_wcb_tagline', true ),
			'website'      => (string) get_post_meta( $post->ID, '_wcb_website', true ),
			'industry'     => (string) get_post_meta( $post->ID, '_wcb_industry', true ),
			'size'         => (string) get_post_meta( $post->ID, '_wcb_company_size', true ),
			'hq'           => (string) get_post_meta( $post->ID, '_wcb_hq_location', true ),
			'company_type' => (string) get_post_meta( $post->ID, '_wcb_company_type', true ),
			'founded'      => (string) get_post_meta( $post->ID, '_wcb_founded', true ),
			'linkedin'     => (string) get_post_meta( $post->ID, '_wcb_linkedin', true ),
			'twitter'      => (string) get_post_meta( $post->ID, '_wcb_twitter', true ),
			'trust_level'  => $trust_level ? $trust_level : 'new',
			'permalink'    => get_permalink( $post->ID ),
		);
	}
}
