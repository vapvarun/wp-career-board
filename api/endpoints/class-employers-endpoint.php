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
	 * @since  1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		// `required: true` is intentionally NOT declared on these args — same
		// reason as candidates/register: a logged-in user with no role should
		// be promote-only (no new credentials needed) and `required` would
		// short-circuit with 400 before `register_employer()` runs. The
		// callback validates the not-logged-in branch explicitly.
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
						'sanitize_callback' => 'sanitize_text_field',
					),
					'last_name'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'email'        => array(
						'type'              => 'string',
						'format'            => 'email',
						'sanitize_callback' => 'sanitize_email',
					),
					'company_name' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'password'     => array(
						'type' => 'string',
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
	 * @param  \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function register_employer( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$company_name = (string) $request->get_param( 'company_name' );

		// Logged-in user without the employer role: just promote them and create their company.
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( in_array( 'wcb_employer', (array) $user->roles, true ) ) {
				return new \WP_Error(
					'wcb_already_employer',
					__( 'You are already registered as an employer.', 'wp-career-board' ),
					array( 'status' => 409 )
				);
			}
			if ( in_array( 'wcb_candidate', (array) $user->roles, true ) ) {
				return new \WP_Error(
					'wcb_candidate_account',
					__( 'Your account is already registered as a candidate. Use a different account to post jobs.', 'wp-career-board' ),
					array( 'status' => 409 )
				);
			}
			if ( '' === trim( $company_name ) ) {
				return new \WP_Error(
					'wcb_missing_company',
					__( 'Company name is required.', 'wp-career-board' ),
					array( 'status' => 400 )
				);
			}
			// Replace existing roles, not stack on top. wp-admin role
			// assignment uses replace semantics (set_role); frontend
			// self-registration must match so a logged-in subscriber who
			// converts to Employer ends up with just wcb_employer, not
			// subscriber + wcb_employer. BuddyPress member-type sync hangs
			// off set_role() too.
			$user->set_role( 'wcb_employer' );
			$user_id = $user->ID;

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
				\WCB\Core\Locations::sync_company_hq( (int) $company_id, (string) $request->get_param( 'hq' ) );
			}
			$resolved_company_id = ( $company_id && ! is_wp_error( $company_id ) ) ? (int) $company_id : 0;
			do_action( 'wcb_employer_registered', $user_id, $resolved_company_id );

			$dashboard_id  = \WCB\Admin\Settings::int( 'employer_dashboard_page', 0 );
			$dashboard_url = $dashboard_id > 0
				? (string) get_permalink( $dashboard_id )
				: home_url( '/' );

			return rest_ensure_response(
				array(
					'user_id'       => $user_id,
					'company_id'    => $resolved_company_id,
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
		$cn_input   = (string) $request->get_param( 'company_name' );

		$missing = array();
		if ( '' === trim( $first_name ) ) {
			$missing[] = 'first_name';
		}
		if ( '' === trim( $email ) ) {
			$missing[] = 'email';
		}
		if ( '' === trim( $cn_input ) ) {
			$missing[] = 'company_name';
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
			\WCB\Core\Locations::sync_company_hq( (int) $company_id, (string) $request->get_param( 'hq' ) );
		}

		// Authenticate the new user immediately.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, false );

		$resolved_company_id = ( $company_id && ! is_wp_error( $company_id ) ) ? (int) $company_id : 0;
		do_action( 'wcb_employer_registered', $user_id, $resolved_company_id );

		$dashboard_id  = \WCB\Admin\Settings::int( 'employer_dashboard_page', 0 );
		$dashboard_url = $dashboard_id > 0
			? (string) get_permalink( $dashboard_id )
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
	 * @param  \WP_REST_Request $request Full request object.
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
		$hq_value = $request->get_param( 'hq' );
		if ( null !== $hq_value ) {
			\WCB\Core\Locations::sync_company_hq( (int) $company_id, (string) $hq_value );
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
	 * @param  \WP_REST_Request $request Full request object.
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
	 * @param  \WP_REST_Request $request Full request object.
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
		$hq_value = $request->get_param( 'hq' );
		if ( null !== $hq_value ) {
			\WCB\Core\Locations::sync_company_hq( (int) $post->ID, (string) $hq_value );
		}

		// Persist filter-injected custom fields (Pro Field Builder + add-ons).
		$custom = $request->get_param( 'custom_fields' );
		if ( is_array( $custom ) ) {
			$groups = (array) apply_filters( 'wcb_company_form_fields', array(), (int) $post->ID );
			\WCB\Core\FormCustomFields::save_values( $groups, (int) $post->ID, $custom );
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
	 * @param  \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	/**
	 * Job statuses an employer/admin may see for their own company vs the public.
	 *
	 * Single source of truth (R1) — a new lifecycle status is added here, not in
	 * each query. Previously this allowlist was duplicated across three sites in
	 * this endpoint, so a new status silently missed some views.
	 *
	 * @param bool $is_owner_or_admin Viewer owns the company or is an admin.
	 * @return string[]
	 */
	private function owner_visible_statuses( bool $is_owner_or_admin ): array {
		return $is_owner_or_admin
			? array( 'publish', 'pending', 'draft', 'wcb_closed', 'wcb_expired' )
			: array( 'publish' );
	}

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
				'post_status'    => $this->owner_visible_statuses( true ),
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
					'publish'     => 'Published',
					'draft'       => 'Draft',
					'pending'     => 'Pending',
					'private'     => 'Private',
					'wcb_closed'  => 'Closed',
					'wcb_expired' => 'Expired',
				);
				// Mirror the public-facing 'closed' / 'expired' status used by
				// the dashboard JS; matches the inverse mapping in
				// JobsEndpoint::get_item().
				$public_status = match ( $p->post_status ) {
					'wcb_closed'  => 'closed',
					'wcb_expired' => 'expired',
					default       => $p->post_status,
				};
				return array(
					'id'          => $p->ID,
					'title'       => $p->post_title,
					'status'      => $public_status,
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

		return $this->build_envelope( 'jobs', $items, (int) $query->found_posts, (int) $query->max_num_pages, $paged );
	}

	/**
	 * List published jobs belonging to a company.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request Full request object.
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
		$is_admin    = $this->check_ability( 'wcb/manage-settings' );
		$post_status = $this->owner_visible_statuses( $is_owner || $is_admin );

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
					'publish'     => 'Published',
					'draft'       => 'Draft',
					'pending'     => 'Pending',
					'private'     => 'Private',
					'wcb_closed'  => 'Closed',
					'wcb_expired' => 'Expired',
				);

				$public_status = match ( $p->post_status ) {
					'wcb_closed'  => 'closed',
					'wcb_expired' => 'expired',
					default       => $p->post_status,
				};

				return array(
					'id'          => $p->ID,
					'title'       => $p->post_title,
					'status'      => $public_status,
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

		return $this->build_envelope( 'jobs', $items, (int) $query->found_posts, (int) $query->max_num_pages, $paged );
	}

	/**
	 * Return all applications across all jobs owned by the given company.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request Full request object.
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

		// Previous implementation materialised every job ID for this company
		// (`posts_per_page = -1`) then `WHERE pm.meta_value IN (...)`. At an
		// enterprise scale (a company with 5000+ posted jobs) that path
		// allocated thousands of integers per request just to hand them
		// straight back into a SQL IN clause. The JOIN below jumps directly
		// from application -> job -> company via two indexed postmeta lookups
		// in one query. Statuses kept identical so closed/expired jobs still
		// surface their applicant pipeline in the employer dashboard.
		global $wpdb;
		$company_id = (int) $company->ID;
		// Owner viewing their own company's applications — same status allowlist
		// as the other employer views (R1: single source of truth).
		$wcb_status_in = "'" . implode( "','", $this->owner_visible_statuses( true ) ) . "'";
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql  = $wpdb->prepare(
			"SELECT app.ID, app.post_date
			 FROM {$wpdb->posts} app
			 INNER JOIN {$wpdb->postmeta} pm_job
			        ON pm_job.post_id = app.ID AND pm_job.meta_key = '_wcb_job_id'
			 INNER JOIN {$wpdb->posts} job
			        ON job.ID = pm_job.meta_value AND job.post_type = 'wcb_job'
			       AND job.post_status IN ({$wcb_status_in})
			 INNER JOIN {$wpdb->postmeta} pm_co
			        ON pm_co.post_id = job.ID AND pm_co.meta_key = '_wcb_company_id'
			 WHERE app.post_type   = 'wcb_application'
			   AND app.post_status = 'publish'
			   AND pm_co.meta_value = %d
			 ORDER BY app.post_date DESC
			 LIMIT 20",
			$company_id
		);
		$rows = $wpdb->get_results( $sql );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $rows ) ) {
			return $this->build_envelope( 'applications', array(), 0, 0, 1 );
		}

		// Prime meta cache once for the application IDs so the per-row
		// get_post_meta calls below resolve from cache instead of issuing a
		// query each. Matches the cache-priming pattern used by job-listings.
		$wcb_app_ids = array_map( static fn( $r ) => (int) $r->ID, $rows );
		update_postmeta_cache( $wcb_app_ids );

		// Batch-prime the related candidate users + job posts + application posts
		// so the loop's get_user_by() / get_the_title() / get_post() calls are
		// all cache hits instead of one query per row (N+1). The candidate/job
		// IDs are already in the meta cache primed above.
		$wcb_candidate_ids = array();
		$wcb_job_ids       = array();
		foreach ( $wcb_app_ids as $wcb_app_id ) {
			$wcb_cid = (int) get_post_meta( $wcb_app_id, '_wcb_candidate_id', true );
			$wcb_jid = (int) get_post_meta( $wcb_app_id, '_wcb_job_id', true );
			if ( $wcb_cid > 0 ) {
				$wcb_candidate_ids[] = $wcb_cid;
			}
			if ( $wcb_jid > 0 ) {
				$wcb_job_ids[] = $wcb_jid;
			}
		}
		if ( $wcb_candidate_ids ) {
			cache_users( array_unique( $wcb_candidate_ids ) );
		}
		if ( $wcb_job_ids ) {
			_prime_post_caches( array_unique( $wcb_job_ids ), false, false );
		}
		_prime_post_caches( $wcb_app_ids, false, false );

		$items = array_map(
			static function ( object $row ) use ( $request ): array {
				$app_id         = (int) $row->ID;
				$candidate_id   = (int) get_post_meta( $app_id, '_wcb_candidate_id', true );
				$candidate_user = $candidate_id > 0 ? get_user_by( 'ID', $candidate_id ) : null;
				$status_raw     = (string) get_post_meta( $app_id, '_wcb_status', true );
				$job_id         = (int) get_post_meta( $app_id, '_wcb_job_id', true );

				$prepared = array(
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

				$application_post = get_post( $app_id );

				/** This filter is documented in api/endpoints/class-applications-endpoint.php */
				return (array) apply_filters( 'wcb_rest_prepare_application', $prepared, $application_post, $request, 'employer' );
			},
			$rows
		);

		// This endpoint always returns the latest 20 with no pagination — total
		// equals returned count and pages is 1 so consumers can use the same
		// envelope shape as paginated lists.
		$count = count( $items );
		return $this->build_envelope( 'applications', $items, $count, $count > 0 ? 1 : 0, 1 );
	}

	/**
	 * Wrap a list page in the standard envelope. Shared across this endpoint's
	 * three list responses (me/jobs, company/jobs, employer/applications).
	 *
	 * @since 1.1.0
	 *
	 * @param  string                           $items_key Resource key, e.g. "jobs".
	 * @param  array<int, array<string, mixed>> $items     Prepared rows.
	 * @param  int                              $total     Total matches.
	 * @param  int                              $pages     Total page count.
	 * @param  int                              $paged     Current page.
	 * @return \WP_REST_Response
	 */
	private function build_envelope( string $items_key, array $items, int $total, int $pages, int $paged ): \WP_REST_Response {
		$response = rest_ensure_response(
			array(
				$items_key => $items,
				'total'    => $total,
				'pages'    => $pages,
				'has_more' => $paged < $pages,
			)
		);
		$response->header( 'X-WCB-Total', (string) $total );
		$response->header( 'X-WCB-TotalPages', (string) $pages );
		return $response;
	}

	/**
	 * Check if the current user can view applications for the given company.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function get_applications_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
		$post     = get_post( (int) $request['id'] );
		$is_owner = $post && get_current_user_id() === (int) $post->post_author
		&& $this->check_ability( 'wcb/view-applications' );
		$is_admin = $this->check_ability( 'wcb/manage-settings' );
		return ( $is_owner || $is_admin ) ? true : $this->permission_error();
	}

	/**
	 * Check if the current user can list their own jobs.
	 *
	 * Intended for the employer dashboard "My Jobs" tab.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function get_my_jobs_permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
		return $this->check_ability( 'wcb/access-employer-dashboard' ) ? true : $this->permission_error();
	}

	/**
	 * Upload a logo image and set it as the company post thumbnail.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request Full request object.
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

		include_once ABSPATH . 'wp-admin/includes/image.php';
		include_once ABSPATH . 'wp-admin/includes/file.php';
		include_once ABSPATH . 'wp-admin/includes/media.php';

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
	 * @param  \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function create_item_permissions_check( $request ): bool|\WP_Error {
		return $this->check_ability( 'wcb/manage-company' ) ? true : $this->permission_error();
	}

	/**
	 * Check if the current user can update the given company.
	 *
	 * Allows the company author or an admin.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request Full request object.
	 * @return bool|\WP_Error
	 */
	public function update_item_permissions_check( $request ): bool|\WP_Error {
		$post = get_post( (int) $request['id'] );
		if ( ! $post ) {
			return $this->permission_error();
		}
		$is_owner = get_current_user_id() === (int) $post->post_author
		&& $this->check_ability( 'wcb/manage-company' );
		$is_admin = $this->check_ability( 'wcb/manage-settings' );
		return ( $is_owner || $is_admin ) ? true : $this->permission_error();
	}

	// --- Helpers ----------------------------------------------------------------

	/**
	 * Resolve the permalink of the page containing the wcb/job-form block.
	 *
	 * Searches published pages for the block comment. Falls back to home_url()
	 * when no matching page is found. Called once per get_jobs() invocation.
	 *
	 * @since  1.0.0
	 * @return string Absolute URL.
	 */
	private function get_job_form_page_url(): string {
		$post_job_page_id = \WCB\Admin\Settings::int( 'post_job_page', 0 );
		if ( $post_job_page_id > 0 ) {
			return (string) get_permalink( $post_job_page_id );
		}

		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				's'              => '<!-- wp:wp-career-board/job-form',
			)
		);
		return ! empty( $pages ) ? (string) get_permalink( $pages[0]->ID ) : home_url( '/' );
	}

	/**
	 * Shape a WP_Post (wcb_company) into the REST response array.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_Post|null $post Company post object.
	 * @return array<string, mixed>
	 */
	private function prepare_company( ?\WP_Post $post ): array {
		if ( ! $post ) {
			return array();
		}
		$logo        = get_the_post_thumbnail_url( $post->ID, 'medium' );
		$trust_level = (string) get_post_meta( $post->ID, '_wcb_trust_level', true );
		$data        = array(
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

		// Employer endpoint shapes a company sub-resource — fires the same
		// canonical filter as class-companies-endpoint so extension authors
		// only have to hook one place.
		return (array) apply_filters( 'wcb_rest_prepare_company', $data, $post, null );
	}
}
