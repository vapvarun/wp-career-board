<?php
/**
 * Single source of truth for serialising a company's brand meta for REST.
 *
 * Previously the four brand fields (tagline, industry, size + size_label, hq)
 * and the `size_label()` map were duplicated in both the companies and jobs
 * REST endpoints (R2). A new brand field used to mean editing two places. Both
 * endpoints now consume this one shape.
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
	 * @return array{tagline:string,industry:string,size:string,size_label:string,hq:string}
	 */
	public static function serialize( int $company_id ): array {
		$size = (string) get_post_meta( $company_id, '_wcb_company_size', true );

		return array(
			'tagline'    => (string) get_post_meta( $company_id, '_wcb_tagline', true ),
			'industry'   => (string) get_post_meta( $company_id, '_wcb_industry', true ),
			'size'       => $size,
			'size_label' => self::size_label( $size ),
			'hq'         => (string) get_post_meta( $company_id, '_wcb_hq_location', true ),
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
}
