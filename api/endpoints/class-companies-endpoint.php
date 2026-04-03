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
		if ( $this->check_ability( 'wcb_manage_settings' ) ) {
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

		$industry = $request->get_param( 'industry' );
		if ( $industry ) {
			$args['meta_query'][] = array(
				'key'     => '_wcb_industry',
				'value'   => sanitize_text_field( $industry ),
				'compare' => '=',
			);
		}

		$size = $request->get_param( 'size' );
		if ( $size ) {
			$args['meta_query'][] = array(
				'key'     => '_wcb_company_size',
				'value'   => sanitize_text_field( $size ),
				'compare' => '=',
			);
		}

		$query = new \WP_Query( $args );

		if ( empty( $query->posts ) ) {
			$response = rest_ensure_response( array() );
			$response->header( 'X-WCB-Total', '0' );
			$response->header( 'X-WCB-TotalPages', '0' );
			return $response;
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

		$response = rest_ensure_response( $companies );
		$response->header( 'X-WCB-Total', (string) $query->found_posts );
		$response->header( 'X-WCB-TotalPages', (string) $query->max_num_pages );
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

		return array(
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

		$jobs = get_posts(
			array(
				'post_type'     => 'wcb_job',
				'post_status'   => 'publish',
				'numberposts'   => -1,
				'author__in'    => $author_ids,
				'no_found_rows' => true,
				'orderby'       => 'none',
			)
		);

		$counts = array();
		foreach ( $jobs as $job ) {
			$aid            = (int) $job->post_author;
			$counts[ $aid ] = ( $counts[ $aid ] ?? 0 ) + 1;
		}
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
			'1-10'      => __( '1–10 employees', 'wp-career-board' ),
			'11-50'     => __( '11–50 employees', 'wp-career-board' ),
			'51-200'    => __( '51–200 employees', 'wp-career-board' ),
			'201-500'   => __( '201–500 employees', 'wp-career-board' ),
			'501-1000'  => __( '501–1,000 employees', 'wp-career-board' ),
			'1001-5000' => __( '1,001–5,000 employees', 'wp-career-board' ),
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
			'industry' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'size'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
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
