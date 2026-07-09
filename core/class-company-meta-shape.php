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
	 * Human-readable label for a company-size bucket.
	 *
	 * @since 1.2.1
	 *
	 * @param string $size Raw size bucket (e.g. '51-200').
	 * @return string
	 */
	public static function size_label( string $size ): string {
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
}
