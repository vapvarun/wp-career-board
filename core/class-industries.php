<?php
/**
 * Industry options registry — single source of truth for company industry slugs.
 *
 * @package WP_Career_Board
 * @since   1.0.2
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical industry slug → label map shared by admin metabox, employer
 * forms, company archive filter, and REST endpoints.
 *
 * Adding a new industry: extend the array here OR hook the `wcb_industries`
 * filter from a third-party plugin. Slugs are stored in `_wcb_industry`
 * post meta and validated as REST enum values, so changing an existing
 * slug will orphan stored data — add new entries instead.
 *
 * @since 1.0.2
 */
final class Industries {

	/**
	 * Slug → translated label for every supported industry.
	 *
	 * Includes the empty placeholder at index 0 for use as a `<select>`
	 * default option. Filterable via the `wcb_industries` hook.
	 *
	 * @since 1.0.2
	 * @return array<string,string>
	 */
	public static function all(): array {
		$industries = array(
			''               => __( '— Select Industry —', 'wp-career-board' ),
			'technology'     => __( 'Technology & Software', 'wp-career-board' ),
			'healthcare'     => __( 'Healthcare & Life Sciences', 'wp-career-board' ),
			'finance'        => __( 'Finance & Banking', 'wp-career-board' ),
			'education'      => __( 'Education', 'wp-career-board' ),
			'retail'         => __( 'Retail & E-commerce', 'wp-career-board' ),
			'manufacturing'  => __( 'Manufacturing', 'wp-career-board' ),
			'media'          => __( 'Media & Entertainment', 'wp-career-board' ),
			'consulting'     => __( 'Consulting & Professional Services', 'wp-career-board' ),
			'nonprofit'      => __( 'Non-profit & NGO', 'wp-career-board' ),
			'government'     => __( 'Government & Public Sector', 'wp-career-board' ),
			'real-estate'    => __( 'Real Estate & Construction', 'wp-career-board' ),
			'transportation' => __( 'Transportation & Logistics', 'wp-career-board' ),
			'energy'         => __( 'Energy & Utilities', 'wp-career-board' ),
			'hospitality'    => __( 'Hospitality & Tourism', 'wp-career-board' ),
			'design'         => __( 'Design & Creative', 'wp-career-board' ),
			'other'          => __( 'Other', 'wp-career-board' ),
		);

		/**
		 * Filter the industry slug → label map.
		 *
		 * @since 1.0.2
		 * @param array<string,string> $industries Slug => label pairs (includes '' placeholder).
		 */
		return (array) apply_filters( 'wcb_industries', $industries );
	}

	/**
	 * Slugs only (no placeholder), suitable for REST `enum` validation.
	 *
	 * @since 1.0.2
	 * @return array<int,string>
	 */
	public static function slugs(): array {
		return array_values( array_filter( array_keys( self::all() ), static fn( string $slug ): bool => '' !== $slug ) );
	}

	/**
	 * Resolve a stored slug to its display label, falling back to the
	 * raw value so legacy free-text entries remain readable until migrated.
	 *
	 * @since 1.0.2
	 * @param string $slug Stored industry value.
	 * @return string
	 */
	public static function label( string $slug ): string {
		if ( '' === $slug ) {
			return '';
		}
		$map = self::all();
		return isset( $map[ $slug ] ) ? (string) $map[ $slug ] : $slug;
	}
}
