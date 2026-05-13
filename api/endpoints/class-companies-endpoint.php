<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated name matches WP convention for multi-word classes.
/**
 * Companies REST endpoint — public directory listing with industry and size filters.
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
 * Handles /wcb/v1/companies REST routes.
 *
 * Public GET /companies with industry, size, search, page and per_page
 * parameters. Designed to power the company-archive Interactivity API block.
 *
 * @since 1.0.0
 */
final class CompaniesEndpoint extends RestController {

	/**
	 * Register all company directory routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/companies',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => '__return_true',
				'args'                => $this->get_collection_params(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/companies/(?P<id>\d+)/bookmark',
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
			'/companies/(?P<id>\d+)/trust',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_trust' ),
				'permission_callback' => array( $this, 'manage_permissions_check' ),
				'args'                => array(
					'id'          => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
						'minimum'           => 1,
					),
					'trust_level' => array(
						'type'              => 'string',
						'enum'              => array( '', 'verified', 'trusted', 'premium' ),
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);
	}

	/**
	 * Permission check: user must hold the wcb_manage_settings ability.
	 *
	 * @since 1.0.0
	 * @return bool|\WP_Error
	 */
	public function manage_permissions_check(): bool|\WP_Error {
		if ( $this->check_ability( 'wcb/manage-settings' ) ) {
			return true;
		}

		return $this->permission_error();
	}

	/**
	 * Update the trust level for a company.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request data.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_trust( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$company_id  = (int) $request['id'];
		$trust_level = (string) $request->get_param( 'trust_level' );
		$company     = get_post( $company_id );

		if ( ! $company instanceof \WP_Post || 'wcb_company' !== $company->post_type ) {
			return new \WP_Error(
				'wcb_not_found',
				__( 'Company not found.', 'wp-career-board' ),
				array( 'status' => 404 )
			);
		}

		if ( '' === $trust_level ) {
			delete_post_meta( $company_id, '_wcb_trust_level' );
		} else {
			update_post_meta( $company_id, '_wcb_trust_level', $trust_level );
		}

		return rest_ensure_response(
			array(
				'id'          => $company_id,
				'trust_level' => $trust_level,
			)
		);
	}

	/**
	 * Toggle company bookmark using non-unique usermeta (one row per
	 * bookmarked company). Mirrors the `_wcb_bookmark` pattern Jobs uses
	 * for saved listings - any logged-in user can save any company.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response
	 */
	public function toggle_bookmark( \WP_REST_Request $request ): \WP_REST_Response {
		$user_id    = get_current_user_id();
		$company_id = (int) $request['id'];

		$existing = get_user_meta( $user_id, '_wcb_company_bookmark', false );
		$existing = array_map( 'intval', (array) $existing );

		if ( in_array( $company_id, $existing, true ) ) {
			delete_user_meta( $user_id, '_wcb_company_bookmark', $company_id );
			$bookmarked = false;
		} else {
			add_user_meta( $user_id, '_wcb_company_bookmark', $company_id, false );
			$bookmarked = true;
		}

		return rest_ensure_response(
			array(
				'bookmarked' => $bookmarked,
				'company_id' => $company_id,
			)
		);
	}

	/**
	 * List published companies with optional industry / size / search filters.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ): \WP_REST_Response {
		$args = array(
			'post_type'      => 'wcb_company',
			'post_status'    => 'publish',
			'posts_per_page' => min( (int) ( $request->get_param( 'per_page' ) ?? 20 ), 100 ),
			'paged'          => max( (int) ( $request->get_param( 'page' ) ?? 1 ), 1 ),
			'meta_query'     => array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);

		$search = $request->get_param( 'search' );
		if ( $search ) {
			$args['s'] = sanitize_text_field( $search );
		}

		// Industry + size accept either a single slug (legacy) or an array
		// of slugs (multi-select sidebar). Normalize to an array and use
		// `compare => IN` so callers can OR across multiple values.
		$industry = $request->get_param( 'industry' );
		if ( $industry ) {
			$industry_values = array_map( 'sanitize_text_field', (array) $industry );
			$industry_values = array_values( array_filter( $industry_values, 'strlen' ) );
			if ( ! empty( $industry_values ) ) {
				$args['meta_query'][] = array(
					'key'     => '_wcb_industry',
					'value'   => $industry_values,
					'compare' => 'IN',
				);
			}
		}

		$size = $request->get_param( 'size' );
		if ( $size ) {
			$size_values = array_map( 'sanitize_text_field', (array) $size );
			$size_values = array_values( array_filter( $size_values, 'strlen' ) );
			if ( ! empty( $size_values ) ) {
				$args['meta_query'][] = array(
					'key'     => '_wcb_company_size',
					'value'   => $size_values,
					'compare' => 'IN',
				);
			}
		}

		$query = new \WP_Query( $args );

		$paged = (int) $args['paged'];
		$total = (int) $query->found_posts;
		$pages = (int) $query->max_num_pages;

		if ( empty( $query->posts ) ) {
			return $this->build_companies_response( array(), 0, 0, $paged );
		}

		// Build author → job count map scoped to companies on this page.
		$author_ids = array_unique(
			array_map(
				static function ( \WP_Post $p ): int {
					return (int) $p->post_author; },
				$query->posts
			)
		);
		$job_counts = $this->job_counts_by_author( $author_ids );

		$companies = array_map(
			function ( \WP_Post $post ) use ( $job_counts ): array {
				return $this->prepare_item( $post, $job_counts );
			},
			$query->posts
		);

		return $this->build_companies_response( $companies, $total, $pages, $paged );
	}

	/**
	 * Wrap a companies list page in the standard envelope.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, array<string, mixed>> $companies Prepared rows.
	 * @param int                              $total     Total matches.
	 * @param int                              $pages     Total page count.
	 * @param int                              $paged     Current page.
	 * @return \WP_REST_Response
	 */
	private function build_companies_response( array $companies, int $total, int $pages, int $paged ): \WP_REST_Response {
		$response = rest_ensure_response(
			array(
				'companies' => $companies,
				'total'     => $total,
				'pages'     => $pages,
				'has_more'  => $paged < $pages,
			)
		);
		$response->header( 'X-WCB-Total', (string) $total );
		$response->header( 'X-WCB-TotalPages', (string) $pages );
		return $response;
	}

	/**
	 * Shape a wcb_company post into the REST response array.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post       Company post object.
	 * @param array    $job_counts author_id → job_count map.
	 * @return array<string, mixed>
	 */
	private function prepare_item( \WP_Post $post, array $job_counts ): array {
		$logo_url   = (string) get_the_post_thumbnail_url( $post->ID, 'thumbnail' );
		$trust      = sanitize_key( (string) get_post_meta( $post->ID, '_wcb_trust_level', true ) );
		$trust_info = $this->trust_badge_info( $trust );
		$size       = (string) get_post_meta( $post->ID, '_wcb_company_size', true );
		$job_count  = $job_counts[ (int) $post->post_author ] ?? 0;
		$name       = $post->post_title;

		// Build up-to-2-letter initials.
		$words    = array_filter( explode( ' ', trim( $name ) ) );
		$initials = '';
		foreach ( array_slice( $words, 0, 2 ) as $word ) {
			$initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
		}
		$initials = $initials ? $initials : '?';

		$data = array(
			'id'          => $post->ID,
			'name'        => $name,
			'initials'    => $initials,
			'has_logo'    => '' !== $logo_url,
			'no_logo'     => '' === $logo_url,
			'logo'        => $logo_url,
			'tagline'     => (string) get_post_meta( $post->ID, '_wcb_tagline', true ),
			'industry'    => (string) get_post_meta( $post->ID, '_wcb_industry', true ),
			'size'        => $size,
			'size_label'  => $this->size_label( $size ),
			'hq'          => (string) get_post_meta( $post->ID, '_wcb_hq_location', true ),
			'trust'       => $trust,
			'trust_label' => $trust_info['label'] ?? '',
			'trust_icon'  => $trust_info['icon'] ?? '',
			'verified'    => null !== $trust_info,
			'permalink'   => get_permalink( $post->ID ),
			'job_count'   => $job_count,
			'jobs_label'  => $this->jobs_label( $job_count ),
		);

		/**
		 * Canonical wcb_rest_prepare_* filter for the company resource.
		 *
		 * @since 1.1.1
		 *
		 * @param array    $data Company response array.
		 * @param \WP_Post $post The company post object.
		 * @param \WP_REST_Request|null $request The originating REST request, when available.
		 */
		return (array) apply_filters( 'wcb_rest_prepare_company', $data, $post, null );
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
	 * Build a map of author_id → published job count.
	 *
	 * Scoped to a specific set of author IDs to avoid fetching all jobs.
	 *
	 * @since 1.0.0
	 *
	 * @param int[] $author_ids Author user IDs to count for.
	 * @return array<int, int>
	 */
	private function job_counts_by_author( array $author_ids ): array {
		if ( empty( $author_ids ) ) {
			return array();
		}

		// One aggregate SQL via the (post_author, post_status, post_type) index
		// instead of materialising every job row into PHP just to count it.
		// At 100k jobs the previous numberposts=-1 path returned 100k WP_Post
		// objects per render; this is an index-only scan.
		global $wpdb;
		$author_ids   = array_map( 'intval', $author_ids );
		$placeholders = implode( ',', array_fill( 0, count( $author_ids ), '%d' ) );
		$cache_key    = 'wcb_job_counts_by_author_' . md5( implode( ',', $author_ids ) );
		$cached       = wp_cache_get( $cache_key, 'wcb_companies' );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_author, COUNT(*) AS c FROM {$wpdb->posts}
					WHERE post_type = 'wcb_job'
					  AND post_status = 'publish'
					  AND post_author IN ({$placeholders})
					GROUP BY post_author",
				...$author_ids
			)
		);
		// phpcs:enable

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->post_author ] = (int) $row->c;
		}
		wp_cache_set( $cache_key, $counts, 'wcb_companies', 5 * MINUTE_IN_SECONDS );
		return $counts;
	}

	/**
	 * Human-readable label for a company size code.
	 *
	 * @since 1.0.0
	 *
	 * @param string $size Size code (e.g. '1-10').
	 * @return string
	 */
	private function size_label( string $size ): string {
		$labels = array(
			'1-10'      => __( '1-10 employees', 'wp-career-board' ),
			'11-50'     => __( '11-50 employees', 'wp-career-board' ),
			'51-200'    => __( '51-200 employees', 'wp-career-board' ),
			'201-500'   => __( '201-500 employees', 'wp-career-board' ),
			'501-1000'  => __( '501-1,000 employees', 'wp-career-board' ),
			'1001-5000' => __( '1,001-5,000 employees', 'wp-career-board' ),
			'5000+'     => __( '5,000+ employees', 'wp-career-board' ),
		);
		return $labels[ $size ] ?? $size;
	}

	/**
	 * Plural label for an open positions count.
	 *
	 * @since 1.0.0
	 *
	 * @param int $count Number of open positions.
	 * @return string
	 */
	private function jobs_label( int $count ): string {
		if ( 0 === $count ) {
			return __( 'No open positions', 'wp-career-board' );
		}
		return sprintf(
			/* translators: %d: number of open positions */
			_n( '%d open position', '%d open positions', $count, 'wp-career-board' ),
			$count
		);
	}

	/**
	 * Define query parameters for the company listing endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params(): array {
		return array(
			'search'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			// `industry` + `size` accept an array of slugs so the sidebar's
			// multi-select checkboxes can OR across values. WP REST coerces
			// `industry[]=design&industry[]=tech` into an array when the
			// declared type is `array`; declaring it as `string` (as the
			// schema previously did) made WP silently flatten the array
			// to its last value, so only one slug was ever filtered on.
			'industry' => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
			'size'     => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
			'page'     => array(
				'type'    => 'integer',
				'default' => 1,
				'minimum' => 1,
			),
			'per_page' => array(
				'type'    => 'integer',
				'default' => 20,
				'minimum' => 1,
				'maximum' => 100,
			),
		);
	}
}
