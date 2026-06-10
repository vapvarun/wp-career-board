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
				'args'                => array(
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 20,
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
					),
				),
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
		if ( ! $request->has_param( 'per_page' ) ) {
			$wcb_per_page = \WCB\Admin\Settings::int( 'jobs_per_page', 15 );
			$request->set_param( 'per_page', $wcb_per_page > 0 ? $wcb_per_page : 15 );
		}

		// Clamp per_page in the handler (the schema declares maximum: 100 but
		// schema validation only emits a warning; clamp here to enforce it).
		$per_page = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?? 15 ) ) );
		$paged    = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );

		$args = array(
			'post_type'      => 'wcb_job',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'tax_query'      => array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'meta_query'     => array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);

		// Support wcb_* prefixed aliases so URL filter params forward transparently to the REST API.
		$search = $request->get_param( 'search' ) ?? $request->get_param( 'wcb_search' );
		if ( $search ) {
			// Store search term for later use in posts_where filter.
			$args['wcb_search_term'] = sanitize_text_field( $search );
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

		$tag = $request->get_param( 'tag' ) ?? $request->get_param( 'wcb_tag' );
		if ( $tag ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'wcb_tag',
				'terms'    => array_map( 'sanitize_text_field', explode( ',', $tag ) ),
				'field'    => 'slug',
			);
		}

		// Accept BOTH `board` and `board_id`. The listings block's view.js sends
		// `board` (url.searchParams.set('board', ...)); other callers + the
		// schema use `board_id`. Reading only one silently dropped the other —
		// the board chip sent `board` and the API ignored it, so the filter did
		// nothing (Basecamp 9976414471). Mirrors the `category`/`wcb_category`
		// dual-read above.
		$board_id = $request->get_param( 'board_id' ) ?? $request->get_param( 'board' );
		if ( $board_id ) {
			$args['meta_query'][] = array(
				'key'   => '_wcb_board_id',
				'value' => absint( $board_id ),
				'type'  => 'NUMERIC',
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

		// Scope to a specific user's bookmarks when the caller passes
		// `saved_by=<user_id>`. Mirrors the Saved tab SSR scope so Load
		// More pages keep returning only bookmarked jobs instead of the
		// site-wide list. An empty bookmark set produces post__in=[0]
		// so WP_Query returns zero rows (not all rows).
		$saved_by = (int) $request->get_param( 'saved_by' );
		if ( $saved_by > 0 ) {
			$bookmark_ids     = array_map( 'intval', (array) get_user_meta( $saved_by, '_wcb_bookmark', false ) );
			$args['post__in'] = ! empty( $bookmark_ids ) ? $bookmark_ids : array( 0 );
		}

		// Post-meta filters via ?meta_<key>=<value>.
		// Any `_wcb_*` namespaced key is allowed by default (the plugin
		// owns that namespace, no probe risk). Custom or non-WCB meta
		// still needs opt-in via the `wcb_jobs_allowed_meta_filters`
		// filter.
		$allowed_meta = (array) apply_filters( 'wcb_jobs_allowed_meta_filters', array() );
		$seen_keys    = array();
		foreach ( $allowed_meta as $meta_key ) {
			if ( ! is_string( $meta_key ) || '' === $meta_key ) {
				continue;
			}
			$raw = $request->get_param( 'meta_' . $meta_key );
			if ( null === $raw || '' === $raw ) {
				continue;
			}
			$args['meta_query'][]   = array(
				'key'   => $meta_key,
				'value' => is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '',
			);
			$seen_keys[ $meta_key ] = true;
		}
		// Scan request params for the _wcb_* namespace default-allow path.
		foreach ( (array) $request->get_params() as $param_name => $raw ) {
			if ( ! is_string( $param_name ) || ! str_starts_with( $param_name, 'meta__wcb_' ) ) {
				continue;
			}
			$meta_key = substr( $param_name, 5 );
			if ( '' === $meta_key || isset( $seen_keys[ $meta_key ] ) ) {
				continue;
			}
			if ( null === $raw || '' === $raw ) {
				continue;
			}
			$args['meta_query'][] = array(
				'key'   => $meta_key,
				'value' => is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '',
			);
		}

		$orderby = $request->get_param( 'orderby' );
		if ( $orderby ) {
			$primary_order   = 'ASC' === strtoupper( (string) $request->get_param( 'order' ) ) ? 'ASC' : 'DESC';
			$args['orderby'] = array(
				(string) $orderby => $primary_order,
				'ID'              => 'DESC', // ID tiebreaker for stable infinite-scroll pagination.
			);
		}

		$cache_key    = $this->get_items_cache_key( $args );
		$cached_value = get_transient( $cache_key );

		if ( false !== $cached_value && is_array( $cached_value ) ) {
			return $this->build_jobs_response(
				(array) ( $cached_value['jobs'] ?? array() ),
				(int) ( $cached_value['total'] ?? 0 ),
				(int) ( $cached_value['pages'] ?? 0 ),
				$paged
			);
		}

		if ( ! empty( $args['wcb_search_term'] ) ) {
			add_filter( 'posts_where', array( $this, 'restrict_search_to_title_and_company' ), 10, 2 );
		}

		$query = new \WP_Query( $args );

		remove_filter( 'posts_where', array( $this, 'restrict_search_to_title_and_company' ), 10 );

		// Prime caches before the prepare loop so per-post get_post_meta() and
		// get_the_terms() inside prepare_item_for_response_array() hit the
		// object cache instead of round-tripping the DB N times.
		$wcb_post_ids = wp_list_pluck( $query->posts, 'ID' );
		if ( ! empty( $wcb_post_ids ) ) {
			update_meta_cache( 'post', $wcb_post_ids );
			update_object_term_cache(
				$wcb_post_ids,
				array( 'wcb_category', 'wcb_job_type', 'wcb_location', 'wcb_experience', 'wcb_tag' )
			);
		}

		$jobs = array_map( array( $this, 'prepare_item_for_response_array' ), $query->posts );
		$jobs = (array) apply_filters( 'wcb_jobs_post_filter', $jobs, $query, $request );

		set_transient(
			$cache_key,
			array(
				'jobs'  => $jobs,
				'total' => (int) $query->found_posts,
				'pages' => (int) $query->max_num_pages,
			),
			5 * MINUTE_IN_SECONDS
		);

		return $this->build_jobs_response( $jobs, (int) $query->found_posts, (int) $query->max_num_pages, $paged );
	}

	/**
	 * Wrap a jobs list page in the standard {jobs, total, pages, has_more} envelope.
	 *
	 * Keeps the legacy X-WCB-Total / X-WCB-TotalPages headers populated for one
	 * release cycle so any external consumers that still read them keep working;
	 * remove the headers in 1.2.0 once known consumers are migrated.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, array<string, mixed>> $jobs  Prepared job rows.
	 * @param int                              $total Total matching posts.
	 * @param int                              $pages Total page count.
	 * @param int                              $paged Current page number.
	 * @return \WP_REST_Response
	 */
	private function build_jobs_response( array $jobs, int $total, int $pages, int $paged ): \WP_REST_Response {
		$response = rest_ensure_response(
			array(
				'jobs'     => $jobs,
				'total'    => $total,
				'pages'    => $pages,
				'has_more' => $paged < $pages,
			)
		);
		$response->header( 'X-WCB-Total', (string) $total );
		$response->header( 'X-WCB-TotalPages', (string) $pages );
		$response->header( 'Cache-Control', 'public, max-age=300' );
		return $response;
	}

	/**
	 * Restrict search to only post_title and company name for wcb_job post types.
	 *
	 * Uses posts_where filter to completely control the search logic,
	 * excluding post_content from job searches.
	 *
	 * @since 1.0.0
	 *
	 * @param string    $where Existing WHERE clause.
	 * @param \WP_Query $query Current WP_Query instance.
	 * @return string
	 */
	public function restrict_search_to_title_and_company( string $where, \WP_Query $query ): string {
		global $wpdb;

		if ( 'wcb_job' !== $query->get( 'post_type' ) ) {
			return $where;
		}

		$search_term = (string) $query->get( 'wcb_search_term' );
		if ( '' === $search_term ) {
			return $where;
		}

		$like = '%' . $wpdb->esc_like( $search_term ) . '%';

		// FULLTEXT path - O(log n) when the term clears MySQL's default
		// `ft_min_word_len = 3`. Below that floor MATCH() returns no rows
		// even when a LIKE would match, so fall back to LIKE on the title
		// for 1-2 character terms.
		$fulltext_supported = (bool) get_option( 'wcb_posts_fulltext_supported', false );
		$use_fulltext       = $fulltext_supported && strlen( $search_term ) >= 3;

		if ( $use_fulltext ) {
			// IN BOOLEAN MODE so the term doesn't need to clear the 50%
			// document threshold IN NATURAL LANGUAGE MODE uses, and so we
			// can opt into prefix matching with a trailing `*`. Escape the
			// boolean operators a user might type so they can't break the
			// query.
			$bool_term = preg_replace( '/[+\-><()~*\"@&|]/', ' ', $search_term );
			$bool_term = trim( (string) $bool_term );
			if ( '' === $bool_term ) {
				return $where;
			}
			$bool_term .= '*';
			$where     .= $wpdb->prepare(
				" AND ( MATCH ({$wpdb->posts}.post_title) AGAINST (%s IN BOOLEAN MODE) OR EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} pm
					WHERE pm.post_id = {$wpdb->posts}.ID
					  AND pm.meta_key = '_wcb_company_name'
					  AND pm.meta_value LIKE %s
				) )",
				$bool_term,
				$like
			);
			return $where;
		}

		// Fallback - LIKE on title + company name. Used when FULLTEXT is
		// unsupported (MyISAM `wp_posts`, replicated read-only, etc.) or
		// when the term is shorter than ft_min_word_len.
		$where .= $wpdb->prepare(
			" AND ( {$wpdb->posts}.post_title LIKE %s OR EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} pm
				WHERE pm.post_id = {$wpdb->posts}.ID
				  AND pm.meta_key = '_wcb_company_name'
				  AND pm.meta_value LIKE %s
			) )",
			$like,
			$like
		);

		return $where;
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

		// Credit gate — if board has a credit cost, check employer balance.
		$wcb_credit_cost = (int) apply_filters( 'wcb_board_credit_cost', 0, (int) ( $request->get_param( 'board_id' ) ?? 0 ) );
		if ( $wcb_credit_cost > 0 ) {
			$wcb_employer_balance = (int) apply_filters( 'wcb_employer_credit_balance', 0, get_current_user_id() );
			if ( $wcb_employer_balance < $wcb_credit_cost ) {
				return new \WP_Error(
					'wcb_insufficient_credits',
					sprintf(
						/* translators: 1: credit cost, 2: current balance */
						__( 'This board requires %1$d credits to post a job. Your balance: %2$d credits.', 'wp-career-board' ),
						$wcb_credit_cost,
						$wcb_employer_balance
					),
					array( 'status' => 402 )
				);
			}
		}

		$auto_publish = \WCB\Admin\Settings::bool( 'auto_publish_jobs', false );
		$status       = $auto_publish ? 'publish' : 'pending';

		/**
		 * Filter the default post status for a newly submitted job.
		 *
		 * Pro hooks this to honor the per-board <code>moderation</code>
		 * setting (auto / approval) so a board configured as
		 * approval-required forces pending even when the global default is
		 * auto-publish, and vice versa. Free is the source of truth for the
		 * global default; Pro adds the per-board override.
		 *
		 * Allowed return values: <code>publish</code>, <code>pending</code>,
		 * <code>draft</code>. Anything else is coerced back to the input.
		 *
		 * @since 1.2.5
		 *
		 * @param string           $status  Resolved default ('publish' | 'pending').
		 * @param \WP_REST_Request $request The originating REST request.
		 */
		$status = (string) apply_filters( 'wcb_job_default_status', $status, $request );
		if ( ! in_array( $status, array( 'publish', 'pending', 'draft' ), true ) ) {
			$status = $auto_publish ? 'publish' : 'pending';
		}

		$wcb_post_data = array(
			'post_type'    => 'wcb_job',
			'post_title'   => $title,
			'post_content' => wp_kses_post( (string) ( $request->get_param( 'description' ) ?? '' ) ),
			'post_status'  => $status,
			'post_author'  => get_current_user_id(),
		);

		/**
		 * Filter — abort or modify a job-create write before it happens.
		 *
		 * Return a WP_Error to abort. Return the (possibly modified) post-data
		 * array to continue. Skill §1.2 lifecycle pattern.
		 *
		 * @since 1.1.1
		 *
		 * @param array            $post_data wp_insert_post arg array.
		 * @param \WP_REST_Request $request   The originating REST request.
		 */
		$wcb_post_data = apply_filters( 'wcb_before_create_job', $wcb_post_data, $request );
		if ( is_wp_error( $wcb_post_data ) ) {
			return $wcb_post_data;
		}

		$job_id = wp_insert_post( $wcb_post_data, true );

		if ( is_wp_error( $job_id ) ) {
			return $job_id;
		}

		// Postmeta.
		$salary_type_raw  = $request->get_param( 'salary_type' );
		$wcb_deadline_raw = $request->get_param( 'deadline' );
		if ( empty( $wcb_deadline_raw ) ) {
			$expire_days = \WCB\Admin\Settings::int( 'jobs_expire_days', 30 );
			$expire_days = $expire_days > 0 ? $expire_days : 30;

			/**
			 * Filter the default expiry window (in days) for a newly submitted
			 * job when the request did not supply an explicit deadline.
			 *
			 * Pro hooks this to honor the per-board <code>expiry_days</code>
			 * setting so each board can run its own posting cadence (e.g. a
			 * "weekend gigs" board with 7-day listings vs a "permanent roles"
			 * board with 60-day listings).
			 *
			 * @since 1.2.5
			 *
			 * @param int              $expire_days Resolved default (positive integer).
			 * @param \WP_REST_Request $request     The originating REST request.
			 */
			$expire_days      = (int) apply_filters( 'wcb_job_default_expiry_days', $expire_days, $request );
			$expire_days      = $expire_days > 0 ? $expire_days : 30;
			$wcb_deadline_raw = gmdate( 'Y-m-d', strtotime( '+' . $expire_days . ' days' ) );
		}
		$wcb_currency_input   = strtoupper( (string) ( $request->get_param( 'salary_currency' ) ?? 'USD' ) );
		$wcb_currency_catalog = \WCB\Admin\AdminSettings::get_currency_catalog();
		$wcb_currency_param   = array_key_exists( $wcb_currency_input, $wcb_currency_catalog ) ? $wcb_currency_input : 'USD';
		$meta                 = array(
			'_wcb_deadline'        => $wcb_deadline_raw,
			'_wcb_salary_min'      => $request->get_param( 'salary_min' ),
			'_wcb_salary_max'      => $request->get_param( 'salary_max' ),
			'_wcb_salary_currency' => $wcb_currency_param,
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
		// Manual one-off location string from the form's "Other (enter
		// manually)" path. Insert/attach a matching wcb_location term so the
		// listings filter still indexes the job, and stash the raw label in
		// post meta for display fidelity (term names get sanitized).
		$wcb_loc_custom = sanitize_text_field( (string) ( $request->get_param( 'location_custom' ) ?? '' ) );
		if ( '' !== $wcb_loc_custom ) {
			$wcb_term = term_exists( $wcb_loc_custom, 'wcb_location' );
			if ( ! $wcb_term ) {
				$wcb_term = wp_insert_term( $wcb_loc_custom, 'wcb_location' );
			}
			if ( ! is_wp_error( $wcb_term ) && isset( $wcb_term['term_id'] ) ) {
				wp_set_object_terms( $job_id, array( (int) $wcb_term['term_id'] ), 'wcb_location', true );
				update_post_meta( $job_id, '_wcb_location_custom', $wcb_loc_custom );
			}
		}
		$experience_param = $request->get_param( 'experience' );
		if ( $experience_param ) {
			wp_set_object_terms( $job_id, (array) $experience_param, 'wcb_experience' );
		}
		$tags = $request->get_param( 'tags' );
		if ( $tags ) {
			wp_set_object_terms( $job_id, (array) $tags, 'wcb_tag' );
		}

		// Persist filter-injected custom fields (Pro Field Builder + add-ons
		// hook wcb_job_form_fields). Mirrors the company / resume custom-field
		// save flow shared via WCB\Core\FormCustomFields — without this the
		// job form posts custom_fields and the endpoint silently drops them.
		$this->save_job_custom_fields( $job_id, $request );

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
		if ( null !== $status && in_array( $status, array( 'publish', 'draft', 'closed' ), true ) ) {
			// Map the public 'closed' value to the internal wcb_closed post
			// status — keeps the JS layer free of the wcb_ prefix while still
			// using the registered custom status under the hood.
			$data['post_status'] = 'closed' === $status ? 'wcb_closed' : $status;

			// A rejected listing is kept as a draft carrying _wcb_rejection_reason.
			// When the employer resubmits it, it must go back through moderation
			// (pending) — NOT straight live — otherwise rejection is trivially
			// bypassed. Override the requested 'publish' and clear the marker.
			if (
				'publish' === $status
				&& 'draft' === $post->post_status
				&& '' !== (string) get_post_meta( $post->ID, '_wcb_rejection_reason', true )
			) {
				$data['post_status'] = 'pending';
				delete_post_meta( $post->ID, '_wcb_rejection_reason' );
			}

			// Republish gate — when an employer flips an expired or closed
			// listing back to publish, treat it as a fresh post for billing
			// purposes so paid boards re-charge instead of giving free
			// extensions. Skipped when the post never carried a cost (free
			// boards, boardless posts) since the gate filter returns 0 there.
			$republish_from = array( 'wcb_expired', 'wcb_closed' );
			if ( 'publish' === $status && in_array( $post->post_status, $republish_from, true ) ) {
				$republish_board_id = (int) get_post_meta( $post->ID, '_wcb_board_id', true );
				$republish_cost     = (int) apply_filters( 'wcb_board_credit_cost', 0, $republish_board_id );

				/**
				 * Filter the credit cost charged when an expired or closed job
				 * is brought back to publish status. Pro hooks this to apply a
				 * republish discount (e.g. 50% of the original board cost) so
				 * site owners can offer "renew listings cheaper than re-post"
				 * pricing without rewriting the board cost callable.
				 *
				 * @since 1.2.5
				 *
				 * @param int     $cost     Credits required to republish.
				 * @param \WP_Post $post     The job being republished.
				 * @param string  $previous The post status the job is leaving.
				 */
				$republish_cost = (int) apply_filters( 'wcb_job_republish_credit_cost', $republish_cost, $post, $post->post_status );

				if ( $republish_cost > 0 ) {
					$republish_balance = (int) apply_filters( 'wcb_employer_credit_balance', 0, get_current_user_id() );
					if ( $republish_balance < $republish_cost ) {
						return new \WP_Error(
							'wcb_insufficient_credits',
							sprintf(
								/* translators: 1: credit cost, 2: current balance */
								__( 'Republishing this job requires %1$d credits. Your balance: %2$d credits.', 'wp-career-board' ),
								$republish_cost,
								$republish_balance
							),
							array( 'status' => 402 )
						);
					}
				}
			}
		}
		if ( ! empty( $data ) ) {
			$data['ID'] = $post->ID;

			/**
			 * Filter — abort or modify a job-update write before it happens.
			 *
			 * @since 1.1.1
			 *
			 * @param array            $data    wp_update_post arg array (with ID set).
			 * @param \WP_Post         $post    The existing job post.
			 * @param \WP_REST_Request $request The originating REST request.
			 */
			$data = apply_filters( 'wcb_before_update_job', $data, $post, $request );
			if ( is_wp_error( $data ) ) {
				return $data;
			}

			wp_update_post( $data );

			// Notify when an expired or closed listing was just republished —
			// Pro hooks this to debit credits and refresh the listing window.
			// Free emits the signal; the actual ledger work lives in Pro.
			if (
				isset( $data['post_status'] )
				&& 'publish' === $data['post_status']
				&& in_array( $post->post_status, array( 'wcb_expired', 'wcb_closed' ), true )
			) {
				/**
				 * Fires after an expired or closed job is republished.
				 *
				 * @since 1.2.5
				 *
				 * @param int    $job_id   The republished job's post ID.
				 * @param string $previous The post status the job left.
				 */
				do_action( 'wcb_job_republished', $post->ID, $post->post_status );
			}
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

		// Manual location override — same flow as create_item().
		$wcb_loc_custom = $request->get_param( 'location_custom' );
		if ( null !== $wcb_loc_custom ) {
			$wcb_loc_custom = sanitize_text_field( (string) $wcb_loc_custom );
			if ( '' !== $wcb_loc_custom ) {
				$wcb_term = term_exists( $wcb_loc_custom, 'wcb_location' );
				if ( ! $wcb_term ) {
					$wcb_term = wp_insert_term( $wcb_loc_custom, 'wcb_location' );
				}
				if ( ! is_wp_error( $wcb_term ) && isset( $wcb_term['term_id'] ) ) {
					wp_set_object_terms( $post->ID, array( (int) $wcb_term['term_id'] ), 'wcb_location', true );
					update_post_meta( $post->ID, '_wcb_location_custom', $wcb_loc_custom );
				}
			} else {
				delete_post_meta( $post->ID, '_wcb_location_custom' );
			}
		}

		// Persist filter-injected custom fields — same flow as create_item().
		// Only touched when the request actually carries custom_fields so a
		// partial PATCH doesn't wipe values the editor didn't resubmit.
		$this->save_job_custom_fields( $post->ID, $request );

		do_action( 'wcb_job_updated', $post->ID, $request );
		return rest_ensure_response( $this->prepare_item_for_response_array( get_post( $post->ID ) ) );
	}

	/**
	 * Persist custom-field values posted alongside a job create/update.
	 *
	 * The job form posts `custom_fields` keyed by sanitized field key. The
	 * `wcb_job_form_fields` filter resolves field groups per board id, and
	 * board 0 holds the global groups shown on boardless job-form pages.
	 * A given form render shows exactly one of those sets, but the endpoint
	 * can't know which `boardId` the block was rendered with — so we validate
	 * the submitted values against the union of board 0 and the job's own
	 * board. WCB\Core\FormCustomFields::save_values() only writes keys that
	 * appear in the resolved groups, so the union never persists stray keys.
	 *
	 * @since 1.1.1
	 *
	 * @param int              $job_id  Job post ID.
	 * @param \WP_REST_Request $request Originating REST request.
	 * @return void
	 */
	private function save_job_custom_fields( int $job_id, \WP_REST_Request $request ): void {
		$custom_fields = $request->get_param( 'custom_fields' );
		if ( ! is_array( $custom_fields ) || ! class_exists( '\WCB\Core\FormCustomFields' ) ) {
			return;
		}

		$board_id = (int) get_post_meta( $job_id, '_wcb_board_id', true );
		$groups   = (array) apply_filters( 'wcb_job_form_fields', array(), 0 );
		if ( $board_id > 0 ) {
			$groups = array_merge(
				$groups,
				(array) apply_filters( 'wcb_job_form_fields', array(), $board_id )
			);
		}

		\WCB\Core\FormCustomFields::save_values( $groups, $job_id, $custom_fields );
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
		/**
		 * Filter — abort a job-trash operation before it happens.
		 *
		 * Return WP_Error to abort. Default `true` to allow.
		 *
		 * @since 1.1.1
		 *
		 * @param bool|\WP_Error   $can     Whether to proceed.
		 * @param \WP_Post         $post    The job post.
		 * @param \WP_REST_Request $request The originating REST request.
		 */
		$wcb_can_delete = apply_filters( 'wcb_before_delete_job', true, $post, $request );
		if ( is_wp_error( $wcb_can_delete ) ) {
			return $wcb_can_delete;
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
		$job_id   = (int) $request['id'];
		$per_page = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
		$paged    = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );

		$query = new \WP_Query(
			array(
				'post_type'      => 'wcb_application',
				'post_status'    => 'any',
				'posts_per_page' => $per_page,
				'paged'          => $paged,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_wcb_job_id',
						'value' => $job_id,
					),
				),
			)
		);
		$posts = $query->posts;
		if ( $posts ) {
			// Prime meta cache once so the prepare loop's get_post_meta calls
			// don't issue a query each. Mirrors the job-listings render pattern.
			update_postmeta_cache( wp_list_pluck( $posts, 'ID' ) );

			// Batch-prime the candidate users so the per-row get_user_by() below
			// is a cache hit instead of one user query per application (N+1).
			$wcb_candidate_ids = array();
			foreach ( $posts as $wcb_app ) {
				$wcb_cid = (int) get_post_meta( $wcb_app->ID, '_wcb_candidate_id', true );
				if ( $wcb_cid > 0 ) {
					$wcb_candidate_ids[] = $wcb_cid;
				}
			}
			if ( $wcb_candidate_ids ) {
				cache_users( array_unique( $wcb_candidate_ids ) );
			}
		}

		$items = array_map(
			static function ( \WP_Post $p ): array {
				$candidate_id   = (int) get_post_meta( $p->ID, '_wcb_candidate_id', true );
				$candidate_user = $candidate_id > 0 ? get_user_by( 'ID', $candidate_id ) : null;
				$status_raw     = (string) get_post_meta( $p->ID, '_wcb_status', true );

				return array(
					'id'               => $p->ID,
					'candidate_id'     => $candidate_id,
					'applicant_name'   => $candidate_user
						? $candidate_user->display_name
						: (string) get_post_meta( $p->ID, '_wcb_guest_name', true ),
					'applicant_email'  => $candidate_user
						? $candidate_user->user_email
						: (string) get_post_meta( $p->ID, '_wcb_guest_email', true ),
					'cover_letter'     => (string) get_post_meta( $p->ID, '_wcb_cover_letter', true ),
					'ai_score'         => '' !== (string) get_post_meta( $p->ID, '_wcbp_ai_scored_at', true ) ? (int) get_post_meta( $p->ID, '_wcbp_ai_fit_score', true ) : null,
					'ai_reason'        => (string) get_post_meta( $p->ID, '_wcbp_ai_fit_reason', true ),
					'ai_summary'       => (string) get_post_meta( $p->ID, '_wcbp_ai_summary', true ),
					'status'           => '' !== $status_raw ? $status_raw : 'submitted',
					'submitted_at'     => get_the_date( 'M j, Y', $p ),
					'resume_url'       => ( static function () use ( $p ): ?string {
						$att_id = (int) get_post_meta( $p->ID, '_wcb_resume_attachment_id', true );
						if ( $att_id <= 0 ) {
							return null;
						}
						$url = wp_get_attachment_url( $att_id );
						return false !== $url ? $url : null;
					} )(),
					'resume_permalink' => ( static function () use ( $p ): ?string {
						$resume_id = (int) get_post_meta( $p->ID, '_wcb_resume_id', true );
						if ( $resume_id <= 0 || '1' !== (string) get_post_meta( $resume_id, '_wcb_resume_public', true ) ) {
							return null;
						}
						$resume_post = get_post( $resume_id );
						if ( ! $resume_post instanceof \WP_Post || 'wcb_resume' !== $resume_post->post_type ) {
							return null;
						}
						$url = get_permalink( $resume_id );
						return false !== $url ? (string) $url : null;
					} )(),
				);
			},
			$posts
		);

		$total    = (int) $query->found_posts;
		$pages    = (int) $query->max_num_pages;
		$has_more = ( $paged * $per_page ) < $total;

		return rest_ensure_response(
			array(
				'applications' => $items,
				'total'        => $total,
				'pages'        => $pages,
				'has_more'     => $has_more,
			)
		);
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
		return $this->check_ability( 'wcb/post-jobs' ) ? true : $this->permission_error();
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
		$user_id           = $this->current_user_id();
		$is_author         = (int) $post->post_author === $user_id
			&& $this->check_ability( 'wcb/post-jobs' );
		$user_company      = (int) get_user_meta( $user_id, '_wcb_company_id', true );
		$job_company       = (int) get_post_meta( $post->ID, '_wcb_company_id', true );
		$is_company_member = $user_company > 0
			&& $user_company === $job_company
			&& $this->check_ability( 'wcb/post-jobs' );
		$is_admin          = $this->check_ability( 'wcb/manage-settings' );
		return ( $is_author || $is_company_member || $is_admin ) ? true : $this->permission_error();
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
		return $this->check_ability( 'wcb/view-applications' ) ? true : $this->permission_error();
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
		// Prefer the job's own _wcb_company_id postmeta (the explicit link
		// stored at job-create time). Fall back to the author's user-meta
		// only when the job has no postmeta — covers legacy rows that
		// pre-date the postmeta convention. The reverse priority would
		// surface the admin's own "their company" when admin posts a job
		// for someone else, leaking the wrong company's brand metadata.
		$company_id = (int) get_post_meta( $post->ID, '_wcb_company_id', true );
		if ( ! $company_id ) {
			$company_id = (int) get_user_meta( $author_id, '_wcb_company_id', true );
		}
		$trust      = $company_id ? sanitize_key( (string) get_post_meta( $company_id, '_wcb_trust_level', true ) ) : '';
		$trust_info = $this->trust_badge_info( $trust );
		// Shared brand-meta shape (R2) — serialize(0) returns empty strings, so
		// this is safe when the job has no linked company.
		$company_meta = \WCB\Core\CompanyMetaShape::serialize( $company_id );

		// Human-readable taxonomy labels for card display.
		// get_the_terms() honours object_term_cache primed in get_items() before the loop.
		$loc_term_objs  = get_the_terms( $post->ID, 'wcb_location' );
		$type_term_objs = get_the_terms( $post->ID, 'wcb_job_type' );
		$exp_term_objs  = get_the_terms( $post->ID, 'wcb_experience' );
		$cat_term_objs  = get_the_terms( $post->ID, 'wcb_category' );
		$tag_term_objs  = get_the_terms( $post->ID, 'wcb_tag' );

		$loc_names  = is_array( $loc_term_objs ) ? wp_list_pluck( $loc_term_objs, 'name' ) : array();
		$type_names = is_array( $type_term_objs ) ? wp_list_pluck( $type_term_objs, 'name' ) : array();
		$exp_names  = is_array( $exp_term_objs ) ? wp_list_pluck( $exp_term_objs, 'name' ) : array();
		$cat_names  = is_array( $cat_term_objs ) ? wp_list_pluck( $cat_term_objs, 'name' ) : array();
		$cat_slugs  = is_array( $cat_term_objs ) ? wp_list_pluck( $cat_term_objs, 'slug' ) : array();
		$type_slugs = is_array( $type_term_objs ) ? wp_list_pluck( $type_term_objs, 'slug' ) : array();
		$loc_slugs  = is_array( $loc_term_objs ) ? wp_list_pluck( $loc_term_objs, 'slug' ) : array();
		$exp_slugs  = is_array( $exp_term_objs ) ? wp_list_pluck( $exp_term_objs, 'slug' ) : array();
		$tag_slugs  = is_array( $tag_term_objs ) ? wp_list_pluck( $tag_term_objs, 'slug' ) : array();

		$thumbnail_url = get_the_post_thumbnail_url( $post->ID, 'medium' );
		$board_id      = (int) apply_filters( 'wcb_job_board_id', (int) get_post_meta( $post->ID, '_wcb_board_id', true ), $post->ID );

		$rejection_reason = '';
		if ( 'draft' === $post->post_status || 'trash' === $post->post_status ) {
			$rejection_reason = (string) get_post_meta( $post->ID, '_wcb_rejection_reason', true );
		}

		$data = array(
			'id'                 => $post->ID,
			'title'              => $post->post_title,
			'description'        => $post->post_content,
			'excerpt'            => wp_trim_words( wp_strip_all_tags( $post->post_content ), 25, '…' ),
			// Map internal wcb_closed → public 'closed' so the dashboard JS
			// can keep its prefix-free status comparisons (mirrors the inverse
			// mapping in update_item()).
			'status'             => 'wcb_closed' === $post->post_status ? 'closed' : $post->post_status,
			// A rejected job is kept as a draft carrying a rejection reason; expose
			// a flag so the dashboard labels/filters it as "Rejected", not "Draft".
			'rejected'           => ( 'draft' === $post->post_status && '' !== (string) $rejection_reason ),
			'author'             => $author_id,
			'created_at'         => mysql_to_rfc3339( $post->post_date_gmt ),
			'updated_at'         => mysql_to_rfc3339( $post->post_modified_gmt ),
			// Deprecated alias for the legacy `date` key. Removed in 1.2.0.
			'date'               => $post->post_date,
			'permalink'          => get_permalink( $post->ID ),
			'rejection_reason'   => $rejection_reason,
			// Company fields.
			'company'            => $company_name,
			'initials'           => $this->company_initials( $company_name ),
			'trust'              => $trust,
			'trust_label'        => $trust_info['label'] ?? '',
			'trust_icon'         => $trust_info['icon'] ?? '',
			'verified'           => null !== $trust_info,
			'company_tagline'    => $company_meta['tagline'],
			'company_industry'   => $company_meta['industry'],
			'company_size'       => $company_meta['size'],
			'company_size_label' => $company_meta['size_label'],
			'company_hq'         => $company_meta['hq'],
			// Job meta.
			'deadline'           => get_post_meta( $post->ID, '_wcb_deadline', true ),
			'salary_min'         => $salary_min,
			'salary_max'         => $salary_max,
			'salary_currency'    => $currency,
			'salary_type'        => $salary_type,
			'salary_label'       => $this->format_salary( $salary_min, $salary_max, $currency, $salary_type ),
			'remote'             => '1' === get_post_meta( $post->ID, '_wcb_remote', true ),
			'featured'           => '1' === get_post_meta( $post->ID, '_wcb_featured', true ),
			'board_id'           => $board_id,
			'board_currency'     => (string) apply_filters( 'wcb_board_currency', 'USD', $board_id ),
			// Display-name strings for cards.
			'location'           => implode( ', ', $loc_names ),
			'type'               => implode( ', ', $type_names ),
			'experience'         => implode( ', ', $exp_names ),
			'category'           => implode( ', ', $cat_names ),
			// Relative time.
			'days_ago'           => human_time_diff( (int) strtotime( $post->post_date ), time() ) . ' ago',
			// Slug arrays for filter/API consumers.
			'categories'         => $cat_slugs,
			'job_types'          => $type_slugs,
			'locations'          => $loc_slugs,
			'experience_slugs'   => $exp_slugs,
			'tags'               => $tag_slugs,
			'thumbnail'          => false !== $thumbnail_url ? (string) $thumbnail_url : '',
			'apply_url'          => (string) get_post_meta( $post->ID, '_wcb_apply_url', true ),
			// apply_email intentionally NOT exposed via REST. Anonymous scrapers
			// were harvesting recruiter inboxes in bulk (F-1 in
			// plan/role-data-baseline-2026-05-07.md). The apply submission
			// posts to /wcb/v1/jobs/{id}/apply which delivers email
			// server-side; no client needs the literal address. Postmeta
			// `_wcb_apply_email` remains for the apply handler + RSS feed.
			'lat'                => (float) get_post_meta( $post->ID, '_wcb_lat', true ),
			'lng'                => (float) get_post_meta( $post->ID, '_wcb_lng', true ),
		);

		/**
		 * Filter the job REST response array.
		 *
		 * Pro (and other extensions) hook here to append extra data such as
		 * custom field values without touching the Free codebase.
		 *
		 * @since 1.0.0
		 *
		 * @param array    $data Job response array.
		 * @param \WP_Post $post The job post object.
		 */
		$data = (array) apply_filters( 'wcb_job_response', $data, $post );

		/**
		 * Canonical wcb_rest_prepare_* filter — fires on every prepared resource.
		 *
		 * Customer-extension surface for theme integrators and add-ons. Mirrors
		 * the WP core `rest_prepare_<post_type>` pattern.
		 *
		 * @since 1.1.1
		 *
		 * @param array $data Job response array.
		 * @param \WP_Post $post The job post object.
		 * @param \WP_REST_Request|null $request The originating REST request, when available.
		 */
		return (array) apply_filters( 'wcb_rest_prepare_job', $data, $post, null );
	}

	/**
	 * Get trust badge info for a trust level.
	 *
	 * @since 1.0.0
	 *
	 * @param string $trust_level Trust level meta value.
	 * @return array{label:string,icon:string}|null
	 */
	private function trust_badge_info( string $trust_level ): ?array {
		$trust_level = sanitize_key( $trust_level );

		$map = array(
			'verified' => array(
				'label' => __( 'Verified', 'wp-career-board' ),
				'icon'  => '✓',
			),
			'trusted'  => array(
				'label' => __( 'Trusted', 'wp-career-board' ),
				'icon'  => '✓',
			),
			'premium'  => array(
				'label' => __( 'Premium', 'wp-career-board' ),
				'icon'  => '★',
			),
		);

		return $map[ $trust_level ] ?? null;
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
		$catalog = \WCB\Admin\AdminSettings::get_currency_catalog();
		$code    = strtoupper( $currency );
		$symbol  = isset( $catalog[ $code ]['symbol'] ) ? (string) $catalog[ $code ]['symbol'] : $code . ' ';
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
