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
 * The list is owner-editable from Settings → Industries and persists in the
 * `wcb_industries` option; sites that never touch it keep running on
 * {@see Industries::defaults()}. Third-party code can still extend the map
 * through the `wcb_industries` filter, which runs last and therefore wins.
 *
 * Slugs are stored in `_wcb_industry` post meta, so changing an existing slug
 * orphans stored data — the Settings screen renames labels in place and routes
 * slug removal through {@see Industries::reassign()} instead.
 *
 * @since 1.0.2
 */
final class Industries {

	/**
	 * Option holding the owner-managed slug → label map (no placeholder).
	 *
	 * @since 1.7.1
	 * @var string
	 */
	public const OPTION = 'wcb_industries';

	/**
	 * Object-cache group shared with the company archive's derived lists.
	 *
	 * @since 1.7.1
	 * @var string
	 */
	private const CACHE_GROUP = 'wcb_companies';

	/**
	 * Companies rewritten per pass by {@see Industries::reassign()}.
	 *
	 * Bounded so a site with 50k companies cannot time out the request that
	 * removes an industry.
	 *
	 * @since 1.7.1
	 * @var int
	 */
	private const REASSIGN_BATCH = 200;

	/**
	 * Ship-with defaults — the map every site starts on.
	 *
	 * @since 1.7.1
	 * @return array<string,string>
	 */
	public static function defaults(): array {
		return array(
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
	}

	/**
	 * The owner-managed map, without the select placeholder.
	 *
	 * Falls back to the shipped defaults until the owner saves the screen for
	 * the first time, so an untouched site behaves exactly as it did before
	 * the list became editable.
	 *
	 * @since 1.7.1
	 * @return array<string,string>
	 */
	public static function registry(): array {
		$stored = get_option( self::OPTION, null );
		if ( ! is_array( $stored ) ) {
			return self::defaults();
		}
		return self::clean( $stored );
	}

	/**
	 * Slug → translated label for every supported industry.
	 *
	 * Includes the empty placeholder at index 0 for use as a select-element
	 * default option. Filterable via the `wcb_industries` hook.
	 *
	 * @since 1.0.2
	 * @return array<string,string>
	 */
	public static function all(): array {
		$industries = array( '' => __( ' -  Select Industry  - ', 'wp-career-board' ) ) + self::registry();

		/**
		 * Filter the industry slug → label map.
		 *
		 * Runs after the owner's saved list, so a filter still has the last
		 * word — third-party industries stay available even on a site that
		 * has customised the registry.
		 *
		 * @since 1.0.2
		 * @param array<string,string> $industries Slug => label pairs (includes '' placeholder).
		 */
		return (array) apply_filters( 'wcb_industries', $industries );
	}

	/**
	 * Slugs only (no placeholder), suitable for REST `enum` validation and
	 * for the `_wcb_industry` write guard.
	 *
	 * @since 1.0.2
	 * @return array<int,string>
	 */
	public static function slugs(): array {
		return array_values( array_filter( array_keys( self::all() ), static fn( string $slug ): bool => '' !== $slug ) );
	}

	/**
	 * Resolve a stored slug to its display label, falling back to a humanised
	 * form of the raw value so legacy free-text entries stay readable.
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
		if ( isset( $map[ $slug ] ) ) {
			return (string) $map[ $slug ];
		}
		// Not in the registry — an import, or a pre-1.7.1 free-text write. A raw
		// machine slug ("fin-tech") is not a label, so humanise it rather than
		// painting it verbatim on company profiles and REST payloads.
		return ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
	}

	/**
	 * Persist the owner-managed map.
	 *
	 * @since 1.7.1
	 * @param array<string,string> $map Slug => label pairs.
	 * @return void
	 */
	public static function save( array $map ): void {
		update_option( self::OPTION, self::clean( $map ) );
		self::flush_cache();
	}

	/**
	 * How many published companies store each industry slug.
	 *
	 * One grouped query rather than a count per row — the Settings screen
	 * needs every count at once to tell the owner what a removal will touch.
	 * Includes slugs no longer in the registry, which is exactly the set the
	 * owner needs to see.
	 *
	 * @since 1.7.1
	 * @return array<string,int> Slug => company count.
	 */
	public static function usage_counts(): array {
		$cached = wp_cache_get( 'wcb_industry_counts', self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT pm.meta_value AS slug, COUNT(*) AS total
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_wcb_industry'
			   AND p.post_type = 'wcb_company'
			   AND p.post_status = 'publish'
			   AND pm.meta_value <> ''
			 GROUP BY pm.meta_value",
			ARRAY_A
		);
		// phpcs:enable

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (string) $row['slug'] ] = (int) $row['total'];
		}

		wp_cache_set( 'wcb_industry_counts', $counts, self::CACHE_GROUP, HOUR_IN_SECONDS );
		return $counts;
	}

	/**
	 * Move every company off one industry slug.
	 *
	 * Runs in bounded passes and writes through `update_post_meta()` rather
	 * than one bulk `UPDATE`, so post-meta caches invalidate and anything
	 * listening on the meta still fires.
	 *
	 * @since 1.7.1
	 * @param string $from Slug being retired.
	 * @param string $to   Replacement slug, or '' to clear the field.
	 * @return int Companies rewritten.
	 */
	public static function reassign( string $from, string $to ): int {
		if ( '' === $from || $from === $to ) {
			return 0;
		}

		global $wpdb;
		$moved = 0;

		do {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT pm.post_id
					 FROM {$wpdb->postmeta} pm
					 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					 WHERE pm.meta_key = '_wcb_industry'
					   AND pm.meta_value = %s
					   AND p.post_type = 'wcb_company'
					 LIMIT %d",
					$from,
					self::REASSIGN_BATCH
				)
			);
			// phpcs:enable

			$found = count( $ids );
			foreach ( $ids as $id ) {
				if ( '' === $to ) {
					delete_post_meta( (int) $id, '_wcb_industry' );
				} else {
					update_post_meta( (int) $id, '_wcb_industry', $to );
				}
				++$moved;
			}
		} while ( $found === self::REASSIGN_BATCH );

		self::flush_cache();
		return $moved;
	}

	/**
	 * Guard every `_wcb_industry` write against the registry.
	 *
	 * The admin metabox, four REST paths in the employers endpoint and the
	 * wizard seeder all reach the same `update_post_meta()`, and only the
	 * metabox's sibling fields (size / type / trust) ever checked their value
	 * against an allowlist. Rather than repeat an `in_array()` at six call
	 * sites — and miss the seventh — reject unknown slugs where every write
	 * converges.
	 *
	 * A rejected write is skipped, not blanked: an existing stored value
	 * survives untouched, so a site carrying imported free-text industries
	 * does not lose them the first time someone edits a company.
	 *
	 * @since 1.7.1
	 * @return void
	 */
	public static function boot(): void {
		add_filter( 'add_post_metadata', array( self::class, 'guard_write' ), 10, 4 );
		add_filter( 'update_post_metadata', array( self::class, 'guard_write' ), 10, 4 );
	}

	/**
	 * Short-circuit a `_wcb_industry` write carrying an unregistered slug.
	 *
	 * @since 1.7.1
	 *
	 * @param mixed  $check      Short-circuit value; null lets the write proceed.
	 * @param int    $object_id  Post ID being written to.
	 * @param string $meta_key   Meta key being written.
	 * @param mixed  $meta_value Value being written.
	 * @return mixed Null to proceed, false to skip the write.
	 */
	public static function guard_write( $check, int $object_id, string $meta_key, $meta_value ) {
		if ( null !== $check || '_wcb_industry' !== $meta_key ) {
			return $check;
		}
		if ( 'wcb_company' !== get_post_type( $object_id ) ) {
			return $check;
		}

		$value = is_string( $meta_value ) ? $meta_value : '';
		if ( '' === $value || in_array( $value, self::slugs(), true ) ) {
			return $check;
		}

		return false;
	}

	/**
	 * Drop the derived lists the company archive and Settings screen cache.
	 *
	 * @since 1.7.1
	 * @return void
	 */
	private static function flush_cache(): void {
		wp_cache_delete( 'wcb_industry_counts', self::CACHE_GROUP );
		wp_cache_delete( 'wcb_distinct_industries', self::CACHE_GROUP );
	}

	/**
	 * Normalise a submitted map: sanitised slugs, non-empty labels, no
	 * placeholder row, first occurrence of a duplicate slug wins.
	 *
	 * @since 1.7.1
	 * @param array<string,string> $map Raw slug => label pairs.
	 * @return array<string,string>
	 */
	private static function clean( array $map ): array {
		$clean = array();
		foreach ( $map as $slug => $label ) {
			$slug  = sanitize_key( (string) $slug );
			$label = sanitize_text_field( (string) $label );
			if ( '' === $slug || '' === $label || isset( $clean[ $slug ] ) ) {
				continue;
			}
			$clean[ $slug ] = $label;
		}
		return $clean;
	}
}
