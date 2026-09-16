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
				// See the note on /jobs/{id}/bookmark - same ability, same gap.
				'permission_callback' => static function (): bool {
					return wp_is_ability_granted( 'wcb/bookmark-jobs' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
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
		// Sort parity with /jobs and /resumes - accepts orderby=date and
		// order=ASC|DESC. Default newest-first to match the SSR first paint.
		$orderby = sanitize_key( (string) ( $request->get_param( 'orderby' ) ?? 'date' ) );
		$order   = strtoupper( sanitize_key( (string) ( $request->get_param( 'order' ) ?? 'DESC' ) ) );
		$orderby = 'date' === $orderby ? 'date' : 'date'; // only `date` allowed for now.
		$order   = 'ASC' === $order ? 'ASC' : 'DESC';

		$args = array(
			'post_type'      => 'wcb_company',
			'post_status'    => 'publish',
			'posts_per_page' => min( (int) ( $request->get_param( 'per_page' ) ?? 20 ), 100 ),
			'paged'          => max( (int) ( $request->get_param( 'page' ) ?? 1 ), 1 ),
			'orderby'        => $orderby,
			'order'          => $order,
			'meta_query'     => array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);

		$search = $request->get_param( 'search' );
		if ( $search ) {
			$search_term = sanitize_text_field( $search );
			// WP's native `s` parameter LIKEs across post_title +
			// post_content + post_excerpt which is too wide for companies
			// (descriptions get noisy) and full-scans wp_posts at 100k.
			// Route through our own posts_where filter so we (a) scope the
			// match to post_title, (b) use FULLTEXT when available, (c)
			// fall back to LIKE otherwise. Same pattern as the jobs search.
			$args['wcb_company_search_term'] = $search_term;
			// NOTE: the posts_where filter is added below, only on a cache MISS,
			// so a cache hit returns without ever hooking (and leaking) it.
		}

		// Industry + size accept either a single slug (legacy) or an array
		// of slugs (multi-select sidebar). Normalize to an array and use
		// `compare => IN` so callers can OR across multiple values.
		$industry = $request->get_param( 'industry' );
		if ( $industry ) {
			$industry_values = array_map( 'sanitize_text_field', (array) $industry );
			$industry_values = array_values( array_filter( $industry_values, static fn( string $v ): bool => '' !== $v ) );
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
			$size_values = array_values( array_filter( $size_values, static fn( string $v ): bool => '' !== $v ) );
			if ( ! empty( $size_values ) ) {
				$args['meta_query'][] = array(
					'key'     => '_wcb_company_size',
					'value'   => $size_values,
					'compare' => 'IN',
				);
			}
		}

		$paged = max( (int) $args['paged'], 1 );

		// Version-keyed list cache — mirrors the /jobs endpoint. The Companies
		// directory is a public, paginated, filterable, searchable list (the
		// highest-fan-out uncached read); the version bumps on every company
		// save/delete so this never serves stale rows. See CACHING.md.
		$cache_key    = $this->get_items_cache_key( $args );
		$cached_value = get_transient( $cache_key );
		if ( false !== $cached_value && is_array( $cached_value ) ) {
			return $this->build_companies_response(
				(array) ( $cached_value['companies'] ?? array() ),
				(int) ( $cached_value['total'] ?? 0 ),
				(int) ( $cached_value['pages'] ?? 0 ),
				$paged
			);
		}

		if ( ! empty( $args['wcb_company_search_term'] ) ) {
			add_filter( 'posts_where', array( $this, 'restrict_company_search_to_title' ), 10, 2 );
		}

		$query = new \WP_Query( $args );
		// Always unhook the search filter even when no rows were returned,
		// so subsequent WP_Query calls in the request lifecycle don't pick
		// up our custom WHERE on unrelated post types.
		remove_filter( 'posts_where', array( $this, 'restrict_company_search_to_title' ), 10 );

		$total = (int) $query->found_posts;
		$pages = (int) $query->max_num_pages;

		if ( empty( $query->posts ) ) {
			set_transient(
				$cache_key,
				array(
					'companies' => array(),
					'total'     => 0,
					'pages'     => 0,
				),
				5 * MINUTE_IN_SECONDS
			);
			return $this->build_companies_response( array(), 0, 0, $paged );
		}

		// Build company → job count map scoped to companies on this page.
		$wcb_company_ids = array_unique(
			array_map(
				static function ( \WP_Post $p ): int {
					return (int) $p->ID; },
				$query->posts
			)
		);
		$job_counts      = $this->job_counts_by_company( $wcb_company_ids );

		$companies = array_map(
			function ( \WP_Post $post ) use ( $job_counts ): array {
				return $this->prepare_item( $post, $job_counts );
			},
			$query->posts
		);

		set_transient(
			$cache_key,
			array(
				'companies' => $companies,
				'total'     => $total,
				'pages'     => $pages,
			),
			5 * MINUTE_IN_SECONDS
		);

		return $this->build_companies_response( $companies, $total, $pages, $paged );
	}

	/**
	 * Version-keyed cache key for a companies list page.
	 *
	 * Mirrors JobsEndpoint::get_items_cache_key(). The version segment
	 * (`wcb_companies_cache_v`) is bumped on every company save/delete, so a
	 * stale page is impossible without a manual transient flush. The key varies
	 * by locale because prepare_item() can embed localised labels.
	 *
	 * @since 1.7.0
	 *
	 * @param array<string, mixed> $args WP_Query args (includes filters/search).
	 * @return string
	 */
	private function get_items_cache_key( array $args ): string {
		$version = (int) get_option( 'wcb_companies_cache_v', 0 );
		$locale  = determine_locale();

		return 'wcb_companies_' . $version . '_' . $locale . '_' . md5( (string) wp_json_encode( $args ) );
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
	/**
	 * Restrict company search to post_title with FULLTEXT preferred.
	 *
	 * Mirrors the jobs-endpoint posts_where pattern so both archives use
	 * the same MATCH() AGAINST() path when the wp_posts FULLTEXT index is
	 * present and falls back to a tighter LIKE on title only otherwise.
	 * Native WP `s` was too noisy (LIKE'd post_content + post_excerpt).
	 *
	 * @since 1.2.6
	 *
	 * @param string    $where Existing WHERE clause.
	 * @param \WP_Query $query Current WP_Query instance.
	 * @return string
	 */
	public function restrict_company_search_to_title( string $where, \WP_Query $query ): string {
		global $wpdb;

		if ( 'wcb_company' !== $query->get( 'post_type' ) ) {
			return $where;
		}

		$search_term = (string) $query->get( 'wcb_company_search_term' );
		if ( '' === $search_term ) {
			return $where;
		}

		$title_clause = \WCB\Core\TitleSearch::title_clause( $search_term );
		if ( '' === $title_clause ) {
			return $where;
		}

		// $title_clause is already prepared.
		$where .= " AND {$title_clause}";
		return $where;
	}

	private function build_companies_response( array $companies, int $total, int $pages, int $paged ): \WP_REST_Response {
		$response = rest_ensure_response(
			array(
				'companies'     => $companies,
				'total'         => $total,
				'pages'         => $pages,
				'has_more'      => $paged < $pages,
				/*
				 * Additive since 1.5.1. Resolved server-side because _n() handles
				 * any number of plural forms; the block previously picked between
				 * two seeded keys with `count === 1`, which is wrong for Polish,
				 * Russian and Arabic. It also labelled state.companies.length (the
				 * rows loaded so far) rather than the matches found.
				 */
				'results_label' => sprintf(
					/* translators: %s: number of companies found, already localised. */
					_n( '%s company found', '%s companies found', $total, 'wp-career-board' ),
					number_format_i18n( $total )
				),
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
	 * @param array    $job_counts company_id → job_count map.
	 * @return array<string, mixed>
	 */
	private function prepare_item( \WP_Post $post, array $job_counts ): array {
		$logo_url     = (string) get_the_post_thumbnail_url( $post->ID, 'thumbnail' );
		$trust        = sanitize_key( (string) get_post_meta( $post->ID, '_wcb_trust_level', true ) );
		$trust_info   = \WCB\Core\CompanyMetaShape::trust_badge_info( $trust );
		$company_meta = \WCB\Core\CompanyMetaShape::serialize( $post->ID );
		$job_count    = $job_counts[ (int) $post->ID ] ?? 0;
		$name         = $post->post_title;

		// Build up-to-2-letter initials.
		$words    = array_filter( explode( ' ', trim( $name ) ) );
		$initials = '';
		foreach ( array_slice( $words, 0, 2 ) as $word ) {
			$initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
		}
		$initials = $initials ? $initials : '?';

		$data = array(
			'id'             => $post->ID,
			'name'           => $name,
			'initials'       => $initials,
			'has_logo'       => '' !== $logo_url,
			'no_logo'        => '' === $logo_url,
			'logo'           => $logo_url,
			'tagline'        => $company_meta['tagline'],
			// Ship the localised industry label alongside the raw slug so the
			// card chip shows "Technology & Software", not "technology", after a
			// client-side re-fetch. SSR already used Industries::label().
			'industry'       => $company_meta['industry'],
			'industry_label' => $company_meta['industry_label'],
			'size'           => $company_meta['size'],
			'size_label'     => $company_meta['size_label'],
			'hq'             => $company_meta['hq'],
			'trust'          => $trust,
			'trust_label'    => $trust_info['label'] ?? '',
			'trust_icon'     => $trust_info['icon'] ?? '',
			'verified'       => null !== $trust_info,
			'permalink'      => get_permalink( $post->ID ),
			'job_count'      => $job_count,
			'jobs_label'     => $this->jobs_label( $job_count ),
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
	 * Build a map of company_id → published job count.
	 *
	 * Scoped to the company IDs on the current page to avoid counting all jobs.
	 *
	 * @since 1.0.0
	 *
	 * @param int[] $company_ids Company post IDs to count for.
	 * @return array<int, int>
	 */
	private function job_counts_by_company( array $company_ids ): array {
		if ( empty( $company_ids ) ) {
			return array();
		}

		// Count through the company LINK (_wcb_company_id postmeta), which is
		// how a job is actually attached to a company — the same relationship
		// /employers/{id}/jobs and CompanyMetaShape::resolve_company_id() use.
		//
		// This previously grouped by post_author and keyed the result on the
		// COMPANY's author, so it answered "how many jobs did the user who
		// created this company post publish?". Wherever one admin, importer or
		// the setup wizard created the company posts, every company inherited
		// that one user's entire job count — six companies on a seeded site all
		// reported 24 while really having 5, 4, 5, 3, 7 and 2.
		//
		// Still one aggregate query rather than materialising job rows. The
		// value is bound as a string so the wcb_meta_key_value composite index
		// stays eligible; an unquoted %d would make MySQL convert the column.
		global $wpdb;
		$company_ids  = array_map( 'intval', $company_ids );
		$placeholders = implode( ',', array_fill( 0, count( $company_ids ), '%s' ) );
		$cache_key    = 'wcb_job_counts_by_company_' . md5( implode( ',', $company_ids ) );
		$cached       = wp_cache_get( $cache_key, 'wcb_companies' );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS company_id, COUNT(*) AS c
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p
					        ON p.ID = pm.post_id
					       AND p.post_type = 'wcb_job'
					       AND p.post_status = 'publish'
					WHERE pm.meta_key = '_wcb_company_id'
					  AND pm.meta_value IN ({$placeholders})
					GROUP BY pm.meta_value",
				...array_map( 'strval', $company_ids )
			)
		);
		// phpcs:enable

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->company_id ] = (int) $row->c;
		}
		// TTL-only cache (CACHING §4b): no write-time invalidation. The key is
		// an md5 of the author-id SET, so a single save_post_wcb_job can't
		// cheaply target the right entry — only a full-group flush would, which
		// costs more than the staleness is worth. A company's "open positions"
		// count tolerates up to 5 minutes of lag (a newly published job appears
		// within one TTL window); we accept that bounded staleness rather than
		// bust on every job write.
		wp_cache_set( $cache_key, $counts, 'wcb_companies', 5 * MINUTE_IN_SECONDS );
		return $counts;
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
			/* translators: %s: number of open positions, already localised. */
			_n( '%s open position', '%s open positions', $count, 'wp-career-board' ),
			number_format_i18n( $count )
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
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'validate_callback' => 'rest_validate_request_arg',
			),
			'per_page' => array(
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}
