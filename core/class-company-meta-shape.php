<?php
/**
 * Single source of truth for a company's identity: brand-meta serialisation
 * (for REST) and employer→company ownership resolution.
 *
 * Previously the four brand fields (tagline, industry, size + size_label, hq)
 * and the `size_label()` map were duplicated in both the companies and jobs
 * REST endpoints (R2). A new brand field used to mean editing two places. Both
 * endpoints now consume this one shape. `resolve_company_id()` likewise gives
 * every surface one answer to "which company does this employer own?".
 *
 * @package WP_Career_Board
 * @since   1.2.1
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Company brand-meta serializer.
 *
 * @since 1.2.1
 */
final class CompanyMetaShape {

	/**
	 * Serialize a company's brand meta for a REST response.
	 *
	 * @since 1.2.1
	 *
	 * @param int $company_id Company (wcb_company) post ID.
	 * @return array{tagline:string,industry:string,industry_label:string,size:string,size_label:string,hq:string}
	 */
	public static function serialize( int $company_id ): array {
		$size     = (string) get_post_meta( $company_id, '_wcb_company_size', true );
		$industry = (string) get_post_meta( $company_id, '_wcb_industry', true );

		return array(
			'tagline'        => (string) get_post_meta( $company_id, '_wcb_tagline', true ),
			// Ship BOTH the raw slug (for enum/filtering) and the localised label.
			// Before 1.5.1 only the slug was returned, so every REST consumer that
			// displayed it (the company-archive chip, employer cards) showed the
			// bare slug 'technology' after any client-side re-fetch, in every
			// locale. Mirrors the size / size_label pair directly below.
			'industry'       => $industry,
			'industry_label' => \WCB\Core\Industries::label( $industry ),
			'size'           => $size,
			'size_label'     => self::size_label( $size ),
			'hq'             => (string) get_post_meta( $company_id, '_wcb_hq_location', true ),
		);
	}

	/**
	 * Badge label + icon for a company trust level.
	 *
	 * Lifted here from CompaniesEndpoint so the single-company route can show
	 * the same badge as the directory card. Returns null for an unrecognised or
	 * empty level, which callers read as "not verified" — `new` is a real stored
	 * value meaning exactly that, not a missing one.
	 *
	 * @since 1.7.2
	 *
	 * @param string $trust_level Raw trust level slug.
	 * @return array{label:string,icon:string}|null
	 */
	public static function trust_badge_info( string $trust_level ): ?array {
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
	 * Human-readable label for a company-size bucket.
	 *
	 * @since 1.2.1
	 *
	 * @param string $size Raw size bucket (e.g. '51-200').
	 * @return string
	 */
	/**
	 * Canonical company-size slugs, in display order.
	 *
	 * The labels already had a single home (size_label below); the SLUGS did not,
	 * and the two copies drifted: the admin meta box wrote `5001+` while the
	 * company-archive filter offered `5000+`, so the largest size filter matched
	 * nothing on any site (Basecamp 10074197007 item 2, resurfacing in the filter
	 * after the display half was fixed). Both consumers now read this list.
	 *
	 * Legacy note: `5000+` is intentionally NOT here. Nothing has written it since
	 * the allowlist settled on `5001+`; rows that still carry it keep rendering
	 * correctly because size_label() retains the key.
	 *
	 * @since 1.7.1
	 * @return array<int,string>
	 */
	public static function size_keys(): array {
		return array( '1-10', '11-50', '51-200', '201-500', '501-1000', '1001-5000', '5001+' );
	}

	public static function size_label( string $size ): string {
		$labels = array(
			'1-10'      => __( '1-10 employees', 'wp-career-board' ),
			'11-50'     => __( '11-50 employees', 'wp-career-board' ),
			'51-200'    => __( '51-200 employees', 'wp-career-board' ),
			'201-500'   => __( '201-500 employees', 'wp-career-board' ),
			'501-1000'  => __( '501-1,000 employees', 'wp-career-board' ),
			'1001-5000' => __( '1,001-5,000 employees', 'wp-career-board' ),
			// `5001+` is the ONLY top bucket the admin meta box writes
			// (admin/class-admin-meta-boxes.php $allowed_sizes). Its absence here
			// meant job-single, the company archive and every REST payload
			// printed the raw slug "5001+" to visitors, while a local copy in
			// blocks/company-profile/render.php had already been patched — the
			// exact hazard of keeping two maps (Basecamp 10074197007, items 2+3).
			// `5000+` stays for rows written by older releases.
			'5000+'     => __( '5,000+ employees', 'wp-career-board' ),
			'5001+'     => __( '5,001+ employees', 'wp-career-board' ),
		);
		return $labels[ $size ] ?? $size;
	}

	/**
	 * Resolve the company an employer owns, self-healing the reciprocal link.
	 *
	 * Employers own a `wcb_company` post (its `post_author`) and carry a
	 * reciprocal `_wcb_company_id` user meta written at registration. When only
	 * the post-side link exists — companies created by CSV/WP-CLI import, an
	 * admin, or a migration never wrote the user meta — dashboard surfaces that
	 * gate on the user meta behave as if the employer has no company, while the
	 * author-based Overview still shows their jobs. This resolver is the single
	 * answer used by every surface: read the user meta, else fall back to a
	 * bounded lookup of an owned company post and backfill the user meta so all
	 * surfaces agree from then on.
	 *
	 * @since 1.5.1
	 *
	 * @param int $user_id Employer user ID.
	 * @return int Owned company (wcb_company) post ID, or 0 if none.
	 */
	public static function resolve_company_id( int $user_id ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}

		$company_id = (int) get_user_meta( $user_id, '_wcb_company_id', true );
		if ( $company_id > 0 && 'wcb_company' === get_post_type( $company_id ) ) {
			return $company_id;
		}

		// Self-heal: find a company this employer owns and restore the link.
		$owned = get_posts(
			array(
				'post_type'      => 'wcb_company',
				'author'         => $user_id,
				'post_status'    => array( 'publish', 'pending', 'draft' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$found = $owned ? (int) $owned[0] : 0;
		if ( $found > 0 ) {
			update_user_meta( $user_id, '_wcb_company_id', $found );
		}

		return $found;
	}

	/**
	 * Open positions per company: published jobs whose deadline has not
	 * passed (the same rule as JobDeadline::accepts_applications()).
	 *
	 * One grouped query for a page of companies, through the job's
	 * `_wcb_company_id` link (not the company owner's authored jobs: an
	 * admin, a second recruiter or an importer can post for a company).
	 * Used by the company directory block and GET /companies so both count
	 * the same thing. Cached for 5 minutes (TTL only; see CACHING section 4b).
	 *
	 * @since 1.8.0
	 *
	 * @param array<int> $company_ids Company post IDs.
	 * @return array<int, int> company ID => open job count (absent = 0).
	 */
	public static function open_job_counts( array $company_ids ): array {
		$company_ids = array_values( array_filter( array_map( 'intval', $company_ids ) ) );
		if ( ! $company_ids ) {
			return array();
		}

		$today     = current_time( 'Y-m-d' );
		$cache_key = 'wcb_open_job_counts_' . md5( implode( ',', $company_ids ) . '|' . $today );
		$cached    = wp_cache_get( $cache_key, 'wcb_companies' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $company_ids ), '%s' ) );

		// Values bound as strings so the (meta_key, meta_value) index stays usable.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS company_id, COUNT(*) AS c
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p
				        ON p.ID = pm.post_id AND p.post_type = 'wcb_job' AND p.post_status = 'publish'
				LEFT JOIN {$wpdb->postmeta} dl
				       ON dl.post_id = p.ID AND dl.meta_key = '_wcb_deadline'
				WHERE pm.meta_key = '_wcb_company_id'
				  AND pm.meta_value IN ({$placeholders})
				  AND ( dl.meta_value IS NULL OR dl.meta_value = '' OR dl.meta_value >= %s )
				GROUP BY pm.meta_value",
				...array_merge( array_map( 'strval', $company_ids ), array( $today ) )
			)
		);
		// phpcs:enable

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->company_id ] = (int) $row->c;
		}
		wp_cache_set( $cache_key, $counts, 'wcb_companies', 5 * MINUTE_IN_SECONDS );
		return $counts;
	}
}
