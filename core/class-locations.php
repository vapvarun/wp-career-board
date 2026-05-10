<?php
/**
 * Location taxonomy helper — reserved-term seeding and HQ → term sync.
 *
 * Two responsibilities:
 *
 *   1. Guarantee the reserved `remote` and `other` slugs exist in the
 *      `wcb_location` taxonomy so the employer-facing job-form dropdown can
 *      always offer them alongside the company HQ.
 *   2. Mirror an employer-supplied free-text HQ string into a `wcb_location`
 *      term and attach that term to the company post, so the job-form lookup
 *      `wp_get_object_terms( $company_id, 'wcb_location' )` finds something.
 *
 * Idempotent on slug: re-saving the same HQ value does not create duplicate
 * terms, and the seeding routine is safe to invoke on every install/upgrade.
 *
 * @package WP_Career_Board
 * @since   1.2.3
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates the `wcb_location` taxonomy with company HQ data.
 *
 * @since 1.2.3
 */
final class Locations {

	/**
	 * Reserved slugs that must always exist for the dropdown to function.
	 *
	 * Slug => label. Labels go through translation at insert time.
	 *
	 * @since 1.2.3
	 * @var   array<string,string>
	 */
	private const RESERVED = array(
		'remote' => 'Remote',
		'other'  => 'Other',
	);

	/**
	 * Prevent instantiation — all methods are static.
	 *
	 * @since 1.2.3
	 */
	private function __construct() {}

	/**
	 * Insert the reserved terms if absent. Safe to call repeatedly.
	 *
	 * Wired from Plugin::init on init@20 (after the Jobs module registers the
	 * taxonomy on init@10). On every pageload `term_exists` short-circuits the
	 * insert, so the cost is one query per reserved slug after first install.
	 *
	 * @since  1.2.3
	 * @return void
	 */
	public static function seed_reserved_terms(): void {
		if ( ! taxonomy_exists( 'wcb_location' ) ) {
			return;
		}

		foreach ( self::RESERVED as $slug => $label ) {
			if ( ! term_exists( $slug, 'wcb_location' ) ) {
				wp_insert_term(
					/* translators: location dropdown label — kept English-default to match the slug. */
					__( $label, 'wp-career-board' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- label list is bounded by self::RESERVED.
					'wcb_location',
					array( 'slug' => $slug )
				);
			}
		}
	}

	/**
	 * Sync a free-text HQ value into `wcb_location` and attach to the company.
	 *
	 * Empty input clears the company's location terms. Non-empty input gets
	 * sanitized to a slug; the matching term is reused if it exists, otherwise
	 * inserted with the original (un-slugged) input as the display name.
	 *
	 * Errors from `wp_insert_term` are swallowed: HQ persistence is a best-
	 * effort enhancement, not a hard requirement of company creation. Does not
	 * touch the reserved 'remote' / 'other' terms even if the HQ slugifies to
	 * one of those — same slug means the existing term is reused, which is the
	 * correct outcome.
	 *
	 * @since 1.2.3
	 *
	 * @param int    $company_id Company post ID.
	 * @param string $hq_input   Raw HQ text entered by the employer.
	 * @return void
	 */
	public static function sync_company_hq( int $company_id, string $hq_input ): void {
		if ( $company_id <= 0 || ! taxonomy_exists( 'wcb_location' ) ) {
			return;
		}

		$hq_input = trim( $hq_input );
		if ( '' === $hq_input ) {
			wp_set_object_terms( $company_id, array(), 'wcb_location', false );
			return;
		}

		$slug    = sanitize_title( $hq_input );
		$term_id = self::resolve_term_id( $hq_input, $slug );
		if ( $term_id > 0 ) {
			wp_set_object_terms( $company_id, array( $term_id ), 'wcb_location', false );
		}
	}

	/**
	 * Find or create the term for the given HQ input.
	 *
	 * @since 1.2.3
	 *
	 * @param string $name Display name for new terms.
	 * @param string $slug Slug for the term lookup/insert.
	 * @return int Term ID, or 0 on failure.
	 */
	private static function resolve_term_id( string $name, string $slug ): int {
		$existing = get_term_by( 'slug', $slug, 'wcb_location' );
		if ( $existing instanceof \WP_Term ) {
			return (int) $existing->term_id;
		}

		$created = wp_insert_term( $name, 'wcb_location', array( 'slug' => $slug ) );
		if ( is_wp_error( $created ) ) {
			// Race: another request may have created the term between the
			// term_by() lookup and the insert. Re-resolve on the slug.
			$retry = get_term_by( 'slug', $slug, 'wcb_location' );
			return $retry instanceof \WP_Term ? (int) $retry->term_id : 0;
		}

		return (int) ( $created['term_id'] ?? 0 );
	}

	/**
	 * One-shot migration: copy every `_wcb_hq_location` meta value into a
	 * matching `wcb_location` term and attach it to the company post.
	 *
	 * Triggered once from `Install::maybe_upgrade` when the stored DB version
	 * is older than 1.2.3. Companies that already have at least one term
	 * attached are skipped so a manually curated location set survives the
	 * migration. Companies with empty HQ meta are also skipped.
	 *
	 * @since  1.2.3
	 * @return void
	 */
	public static function backfill_existing_company_hq_terms(): void {
		if ( ! taxonomy_exists( 'wcb_location' ) || ! post_type_exists( 'wcb_company' ) ) {
			return;
		}

		$company_ids = get_posts(
			array(
				'post_type'      => 'wcb_company',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $company_ids as $company_id ) {
			$existing_terms = wp_get_object_terms( (int) $company_id, 'wcb_location', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $existing_terms ) && ! empty( $existing_terms ) ) {
				continue;
			}
			$hq = (string) get_post_meta( (int) $company_id, '_wcb_hq_location', true );
			if ( '' === trim( $hq ) ) {
				continue;
			}
			self::sync_company_hq( (int) $company_id, $hq );
		}
	}

	/**
	 * Build the location-dropdown choices for a job-form render.
	 *
	 * Returns the terms an employer should see when posting a job:
	 *
	 *   - The company HQ term (if attached).
	 *   - The reserved `remote` term.
	 *   - The reserved `other` term.
	 *
	 * Admins (anyone granted `wcb/manage-settings`) get the full taxonomy so
	 * the wp-admin Job edit screen and the front-end form for site managers
	 * keep working unchanged.
	 *
	 * Falls back to the full taxonomy when the current user has no linked
	 * company AND none of the reserved terms exist yet — this only happens
	 * on a pre-1.2.3 install before init@20 fires, so the form still works.
	 *
	 * @since 1.2.3
	 *
	 * @param int $user_id User whose company HQ should be returned.
	 * @return array<int,\WP_Term>
	 */
	public static function get_dropdown_terms( int $user_id ): array {
		if ( ! taxonomy_exists( 'wcb_location' ) ) {
			return array();
		}

		// Admins get every term — covers the wp-admin Job edit screen.
		if ( wp_is_ability_granted( 'wcb/manage-settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
			return self::all_terms();
		}

		$company_id = $user_id > 0 ? (int) get_user_meta( $user_id, '_wcb_company_id', true ) : 0;

		$terms    = array();
		$seen_ids = array();

		if ( $company_id > 0 ) {
			$hq_terms = wp_get_object_terms( $company_id, 'wcb_location', array( 'fields' => 'all' ) );
			if ( ! is_wp_error( $hq_terms ) ) {
				foreach ( (array) $hq_terms as $hq_term ) {
					if ( $hq_term instanceof \WP_Term && ! isset( $seen_ids[ $hq_term->term_id ] ) ) {
						$terms[]                       = $hq_term;
						$seen_ids[ $hq_term->term_id ] = true;
					}
				}
			}
		}

		foreach ( array_keys( self::RESERVED ) as $slug ) {
			$reserved = get_term_by( 'slug', $slug, 'wcb_location' );
			if ( $reserved instanceof \WP_Term && ! isset( $seen_ids[ $reserved->term_id ] ) ) {
				$terms[]                        = $reserved;
				$seen_ids[ $reserved->term_id ] = true;
			}
		}

		// No company AND no reserved terms = pre-seed install; serve the full
		// taxonomy so the form stays usable until the seed runs on init@20.
		if ( empty( $terms ) ) {
			return self::all_terms();
		}

		return $terms;
	}

	/**
	 * Fetch every `wcb_location` term for the admin/fallback path.
	 *
	 * @since 1.2.3
	 * @return array<int,\WP_Term>
	 */
	private static function all_terms(): array {
		$all = get_terms(
			array(
				'taxonomy'   => 'wcb_location',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $all ) ) {
			return array();
		}
		return array_values( (array) $all );
	}
}
