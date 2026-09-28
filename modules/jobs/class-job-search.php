<?php
/**
 * Job search - the one place a job list query is built.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters, keyword matching and sort for published jobs.
 *
 * Used by GET /jobs, the job listings block's first paint, the job archive
 * and job alerts, so a filter means the same thing on every surface.
 * Keyword: every word must appear in the title, the description or the
 * company name (AND); title matches rank first. Filters take one value or a
 * comma-joined list (any of). Pro adds radius through `wcb_job_search_args`.
 *
 * @since 1.8.0
 */
final class JobSearch {

	/**
	 * URL/REST aliases mapped to their canonical parameter.
	 *
	 * @var array<string, string>
	 */
	private const ALIASES = array(
		'wcb_search'     => 'search',
		's'              => 'search',
		'wcb_category'   => 'category',
		'wcb_job_type'   => 'type',
		'wcb_location'   => 'location',
		'wcb_experience' => 'experience',
		'wcb_tag'        => 'tag',
		'wcb_remote'     => 'remote',
		'board_id'       => 'board',
		'wcb_sort'       => 'sort',
	);

	/**
	 * Taxonomy filters.
	 *
	 * @var array<string, string>
	 */
	private const TAXONOMIES = array(
		'category'   => 'wcb_category',
		'type'       => 'wcb_job_type',
		'location'   => 'wcb_location',
		'experience' => 'wcb_experience',
		'tag'        => 'wcb_tag',
	);

	/**
	 * Sort values: relevance (keyword searches), newest (featured first),
	 * oldest, salary (highest first), closing (soonest deadline first).
	 *
	 * @var string[]
	 */
	public const SORTS = array( 'relevance', 'newest', 'oldest', 'salary', 'closing' );

	/**
	 * Most keyword words used; more only slow the query down.
	 *
	 * @var int
	 */
	private const MAX_WORDS = 6;

	/**
	 * Register the SQL for keyword, relevance and the meta sorts.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_filter( 'posts_clauses', array( self::class, 'clauses' ), 10, 2 );
	}

	/**
	 * Canonical filter params from any mix of names (REST, URL, block).
	 *
	 * @param array<string, mixed> $raw Raw params.
	 * @return array<string, mixed>
	 */
	public static function normalise( array $raw ): array {
		$params = array();
		foreach ( $raw as $key => $value ) {
			$key = self::ALIASES[ $key ] ?? $key;
			if ( is_array( $value ) ) {
				$value = implode( ',', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) );
			}
			if ( is_scalar( $value ) && '' !== (string) $value && ! isset( $params[ $key ] ) ) {
				$params[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $params;
	}

	/**
	 * Filter params from the current URL (listing block, archive).
	 *
	 * @return array<string, mixed>
	 */
	public static function from_url(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only filters.
		return self::normalise( wp_unslash( $_GET ) );
	}

	/**
	 * The sort a request gets: the one asked for, else relevance for a
	 * keyword search, else the owner's default (Settings > Jobs).
	 *
	 * @param array<string, mixed> $params Canonical params.
	 * @return string
	 */
	public static function sort( array $params ): string {
		$sort = (string) ( $params['sort'] ?? '' );
		// Clients from before 1.8.0 send orderby=date&order=ASC|DESC.
		// DESC was their default, so only ASC means a choice.
		if ( '' === $sort && 'ASC' === strtoupper( (string) ( $params['order'] ?? '' ) ) ) {
			$sort = 'oldest';
		}
		if ( in_array( $sort, self::SORTS, true ) && ( 'relevance' !== $sort || '' !== self::term( $params ) ) ) {
			return $sort;
		}
		if ( '' !== self::term( $params ) ) {
			return 'relevance';
		}
		$default = \WCB\Admin\Settings::string( 'jobs_default_sort', 'newest' );
		return in_array( $default, self::SORTS, true ) && 'relevance' !== $default ? $default : 'newest';
	}

	/**
	 * WP_Query args for the params, merged over base args.
	 *
	 * @param array<string, mixed> $raw  Params (any names).
	 * @param array<string, mixed> $base Base args (paging, status).
	 * @return array<string, mixed>
	 */
	public static function query_args( array $raw, array $base = array() ): array {
		$params             = self::normalise( $raw );
		$args               = $base + array(
			'post_type'   => 'wcb_job',
			'post_status' => 'publish',
		);
		$args['tax_query']  = (array) ( $args['tax_query'] ?? array() ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		$args['meta_query'] = (array) ( $args['meta_query'] ?? array() ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		foreach ( self::TAXONOMIES as $param => $taxonomy ) {
			if ( ! empty( $params[ $param ] ) ) {
				$args['tax_query'][] = array(
					'taxonomy' => $taxonomy,
					'terms'    => array_values( array_filter( array_map( 'sanitize_title', explode( ',', (string) $params[ $param ] ) ) ) ),
					'field'    => 'slug',
				);
			}
		}
		if ( ! empty( $params['board'] ) ) {
			$args['meta_query'][] = array(
				'key'   => '_wcb_board_id',
				'value' => absint( $params['board'] ),
				'type'  => 'NUMERIC',
			);
		}
		if ( ! empty( $params['remote'] ) && rest_sanitize_boolean( $params['remote'] ) ) {
			$args['meta_query'][] = array(
				'key'   => '_wcb_remote',
				'value' => '1',
			);
		}
		// A range overlaps the job's pay: its top reaches the minimum asked for,
		// its bottom is under the maximum.
		if ( ! empty( $params['salary_min'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_wcb_salary_max',
				'value'   => (int) $params['salary_min'],
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}
		if ( ! empty( $params['salary_max'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_wcb_salary_min',
				'value'   => (int) $params['salary_max'],
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
		}
		if ( ! empty( $params['company'] ) ) {
			$args['meta_query'][] = array(
				'key'   => '_wcb_company_id',
				'value' => (string) absint( $params['company'] ),
			);
		}
		if ( ! empty( $params['open'] ) && rest_sanitize_boolean( $params['open'] ) ) {
			$args['meta_query'][] = \WCB\Core\JobDeadline::open_jobs_meta_query();
		}

		$term = self::term( $params );
		if ( '' !== $term ) {
			$args['wcb_search_term'] = $term;
		}
		$sort = self::sort( $params );
		unset( $args['orderby'], $args['order'] );
		if ( 'newest' === $sort ) {
			$args = JobsMeta::featured_first( $args );
		} elseif ( 'oldest' === $sort ) {
			$args['orderby'] = array(
				'date' => 'ASC',
				'ID'   => 'ASC',
			);
		} else {
			$args['wcb_sort'] = $sort;
			$args['orderby']  = array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			);
		}

		/**
		 * Filter a job search before it runs (Pro adds radius here).
		 *
		 * Everything that changes the result set must go into the args: the
		 * REST cache key is built from them.
		 *
		 * @since 1.8.0
		 *
		 * @param array $args   WP_Query args.
		 * @param array $params Canonical search params.
		 */
		return (array) apply_filters( 'wcb_job_search_args', $args, $params );
	}

	/**
	 * Run a search (WP_Query, so the clauses and found_posts apply).
	 *
	 * @param array<string, mixed> $args Args from query_args().
	 * @return \WP_Query
	 */
	public static function run( array $args ): \WP_Query {
		$args['suppress_filters'] = false;
		return new \WP_Query( $args );
	}

	/**
	 * Whether a job's text matches a keyword the same way the search does.
	 *
	 * For alerts, which check one new job against many saved searches
	 * without a query each.
	 *
	 * @param int    $job_id Job.
	 * @param string $term   Keyword.
	 * @return bool
	 */
	public static function text_matches( int $job_id, string $term ): bool {
		$words = self::words( $term );
		if ( ! $words ) {
			return true;
		}
		$haystack = mb_strtolower(
			get_post_field( 'post_title', $job_id ) . ' ' . get_post_field( 'post_content', $job_id ) . ' ' . get_post_meta( $job_id, '_wcb_company_name', true )
		);
		foreach ( $words as $word ) {
			if ( ! str_contains( $haystack, mb_strtolower( $word ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * `posts_clauses`: keyword AND-match, relevance, salary and closing sorts.
	 *
	 * @param array<string, string> $clauses Clauses.
	 * @param \WP_Query             $query   Query.
	 * @return array<string, string>
	 */
	public static function clauses( array $clauses, \WP_Query $query ): array {
		$term = (string) $query->get( 'wcb_search_term' );
		$sort = (string) $query->get( 'wcb_sort' );
		if ( ( '' === $term && '' === $sort ) || 'wcb_job' !== $query->get( 'post_type' ) ) {
			return $clauses;
		}
		global $wpdb;
		$posts = $wpdb->posts;
		$score = array();
		foreach ( self::words( $term ) as $word ) {
			$like              = '%' . $wpdb->esc_like( $word ) . '%';
			$clauses['where'] .= $wpdb->prepare(
				" AND ( {$posts}.post_title LIKE %s OR {$posts}.post_content LIKE %s OR EXISTS ( SELECT 1 FROM {$wpdb->postmeta} wcb_cn WHERE wcb_cn.post_id = {$posts}.ID AND wcb_cn.meta_key = '_wcb_company_name' AND wcb_cn.meta_value LIKE %s ) )",
				$like,
				$like,
				$like
			);
			$score[]           = $wpdb->prepare( "( {$posts}.post_title LIKE %s ) * 3 + ( {$posts}.post_content LIKE %s )", $like, $like );
		}
		if ( 'relevance' === $sort && $score ) {
			$clauses['fields'] .= ', ( ' . implode( ' + ', $score ) . ' ) AS wcb_relevance';
			$clauses['orderby'] = "wcb_relevance DESC, {$posts}.post_date DESC, {$posts}.ID DESC";
		} elseif ( 'salary' === $sort ) {
			$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} wcb_sal ON ( wcb_sal.post_id = {$posts}.ID AND wcb_sal.meta_key = '_wcb_salary_max' )";
			$clauses['orderby'] = "CAST( wcb_sal.meta_value AS DECIMAL(12,2) ) DESC, {$posts}.post_date DESC, {$posts}.ID DESC";
		} elseif ( 'closing' === $sort ) {
			// Jobs without a deadline go last, not first.
			$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} wcb_dl ON ( wcb_dl.post_id = {$posts}.ID AND wcb_dl.meta_key = '_wcb_deadline' AND wcb_dl.meta_value <> '' )";
			$clauses['orderby'] = "wcb_dl.meta_value IS NULL, wcb_dl.meta_value ASC, {$posts}.post_date DESC, {$posts}.ID DESC";
		}
		return $clauses;
	}

	/**
	 * The keyword of a param set.
	 *
	 * @param array<string, mixed> $params Canonical params.
	 * @return string
	 */
	private static function term( array $params ): string {
		return trim( (string) ( $params['search'] ?? '' ) );
	}

	/**
	 * Keyword words: unique, 2+ characters, at most MAX_WORDS.
	 *
	 * @param string $term Keyword.
	 * @return string[]
	 */
	private static function words( string $term ): array {
		$words = preg_split( '/\s+/u', trim( $term ) );
		$words = array_filter( (array) $words, static fn ( $w ): bool => mb_strlen( (string) $w ) >= 2 );
		return array_slice( array_values( array_unique( array_map( 'strval', $words ) ) ), 0, self::MAX_WORDS );
	}
}
