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
	 * A job whose Location is Remote is a remote job.
	 *
	 * Kept true where the flag is written, so every reader of `_wcb_remote`
	 * (job page, filters, search, JobPosting schema, RSS, REST, alerts) agrees
	 * without a second check (owner decision, 1.8.1).
	 *
	 * @since 1.8.1
	 *
	 * @param int    $object_id Object the terms were set on.
	 * @param array  $terms     Terms passed in.
	 * @param array  $tt_ids    Term taxonomy IDs now set.
	 * @param string $taxonomy  Taxonomy slug.
	 * @return void
	 */
	public static function flag_remote_on_terms( int $object_id, array $terms, array $tt_ids, string $taxonomy ): void {
		unset( $terms, $tt_ids );
		if ( 'wcb_location' === $taxonomy && has_term( 'remote', 'wcb_location', $object_id ) ) {
			update_post_meta( $object_id, '_wcb_remote', '1' );
		}
	}

	/**
	 * Job ids whose request already checked the location itself.
	 *
	 * @var int[]
	 */
	private static array $trusted = array();

	/**
	 * Whether "Require a location" is on and the job has none stored.
	 *
	 * @since 1.8.4
	 *
	 * @param int $job_id Job post ID.
	 * @return bool
	 */
	public static function job_lacks_location( int $job_id ): bool {
		if ( ! \WCB\Admin\Settings::bool( 'require_job_location', false ) ) {
			return false;
		}
		$slugs = wp_get_object_terms( $job_id, 'wcb_location', array( 'fields' => 'slugs' ) );
		return ! self::has_usable_location(
			'1' === get_post_meta( $job_id, '_wcb_remote', true ),
			is_array( $slugs ) ? $slugs : array(),
			(string) get_post_meta( $job_id, '_wcb_location_custom', true )
		);
	}

	/**
	 * The one refusal every entry point returns for a job with no location.
	 *
	 * @since 1.8.4
	 * @return \WP_Error
	 */
	public static function required_error(): \WP_Error {
		return new \WP_Error(
			'wcb_location_required',
			__( 'Add a location, or mark the job as remote.', 'wp-career-board' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Mark a job whose request validated its own location, so the publish guard
	 * leaves it alone (the request applies the new terms after the status).
	 *
	 * @since 1.8.4
	 *
	 * @param int $job_id Job post ID.
	 * @return void
	 */
	public static function trust( int $job_id ): void {
		self::$trusted[] = $job_id;
	}

	/**
	 * Whether this is a core /wp/v2 request, which is how the block editor saves.
	 * Its location lives in meta boxes saved by a second request, so the guard
	 * cannot judge the publish step on its own.
	 *
	 * @since 1.8.4
	 * @return bool
	 */
	private static function is_core_rest_request(): bool {
		return defined( 'REST_REQUEST' ) && str_starts_with( (string) ( $GLOBALS['wp']->query_vars['rest_route'] ?? '' ), '/wp/v2/' );
	}

	/**
	 * Keep an existing job out of Published while it has no location.
	 *
	 * Covers every path that flips a stored job to publish without the editor or
	 * REST having checked it: Quick Edit, Bulk Edit and other callers of
	 * wp_update_post(). The wp-admin editor holds its own save later, once its
	 * terms are written (AdminMetaBoxes::hold_job_without_location).
	 *
	 * @since 1.8.4
	 *
	 * @param array<string, mixed> $data    Slashed post data about to be stored.
	 * @param array<string, mixed> $postarr Post array as passed in.
	 * @return array<string, mixed>
	 */
	public static function guard_publish( array $data, array $postarr ): array {
		$id = (int) ( $postarr['ID'] ?? 0 );
		if ( 'wcb_job' !== ( $data['post_type'] ?? '' ) || 'publish' !== ( $data['post_status'] ?? '' ) || $id <= 0 || isset( $_POST[ \WCB\Admin\AdminMetaBoxes::NONCE_NAME ] ) || in_array( $id, self::$trusted, true ) || self::is_core_rest_request() ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- only detects the editor request; the editor verifies its own nonce.
			return $data;
		}
		$before = (string) get_post_status( $id );
		if ( 'publish' === $before || 'auto-draft' === $before || ! self::job_lacks_location( $id ) ) {
			return $data;
		}
		$data['post_status'] = in_array( $before, array( 'pending', 'draft' ), true ) ? $before : 'draft';
		return $data;
	}

	/**
	 * Hold a freshly imported job as a draft when it has no location.
	 *
	 * Importers write the terms after the post, so they call this once the row
	 * is complete.
	 *
	 * @since 1.8.4
	 *
	 * @param int $job_id Job post ID.
	 * @return bool True when the job was held.
	 */
	public static function hold_if_unlocated( int $job_id ): bool {
		if ( 'publish' !== get_post_status( $job_id ) || ! self::job_lacks_location( $job_id ) ) {
			return false;
		}
		wp_update_post(
			array(
				'ID'          => $job_id,
				'post_status' => 'draft',
			)
		);
		return true;
	}

	/**
	 * Whether a job has a location that satisfies "Require a location".
	 *
	 * One rule for REST, the frontend forms and the admin editor: Remote, a
	 * term that exists, or a typed location. The reserved Other term only
	 * stands for a typed location, and an unknown slug counts for nothing.
	 *
	 * @since 1.8.4
	 *
	 * @param bool     $remote    Remote flag.
	 * @param string[] $slugs     wcb_location term slugs.
	 * @param string   $custom    Typed "Other" location.
	 * @return bool
	 */
	public static function has_usable_location( bool $remote, array $slugs, string $custom ): bool {
		if ( $remote || '' !== trim( $custom ) ) {
			return true;
		}
		foreach ( $slugs as $slug ) {
			if ( 'remote' === $slug || ( 'other' !== $slug && term_exists( $slug, 'wcb_location' ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Refuse to store "not remote" on a job located Remote.
	 *
	 * Forms and the admin editor write the checkbox after the terms, so an
	 * unticked box would otherwise undo flag_remote_on_terms().
	 *
	 * @since 1.8.1
	 *
	 * @param null|bool $check     Short-circuit value.
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key  Meta key.
	 * @param mixed     $value     Value being stored.
	 * @return null|bool
	 */
	public static function keep_remote_flag( $check, int $object_id, string $meta_key, $value ) {
		if ( '_wcb_remote' !== $meta_key || '1' === (string) $value || 'wcb_job' !== get_post_type( $object_id ) || ! has_term( 'remote', 'wcb_location', $object_id ) ) {
			return $check;
		}
		update_post_meta( $object_id, '_wcb_remote', '1' );
		return true;
	}

	/**
	 * Flag every job located Remote as remote (1.8.1 upgrade repair).
	 *
	 * @since 1.8.1
	 * @return void
	 */
	public static function backfill_remote_flags(): void {
		$ids = get_posts(
			array(
				'post_type'      => 'wcb_job',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- one-off upgrade repair.
					array(
						'taxonomy' => 'wcb_location',
						'field'    => 'slug',
						'terms'    => 'remote',
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			update_post_meta( (int) $id, '_wcb_remote', '1' );
		}
	}

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

		// Literal __() calls so the strings reach the POT; keys match self::RESERVED.
		$labels = array(
			/* translators: location dropdown label for jobs with no fixed place. */
			'remote' => __( 'Remote', 'wp-career-board' ),
			/* translators: location dropdown label for any place not listed. */
			'other'  => __( 'Other', 'wp-career-board' ),
		);
		foreach ( self::RESERVED as $slug => $label ) {
			if ( ! term_exists( $slug, 'wcb_location' ) ) {
				wp_insert_term(
					$labels[ $slug ] ?? $label,
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
	 * Unlink a company's HQ location when the company is deleted.
	 *
	 * wcb_location is not registered for wcb_company, so wp_delete_post()
	 * leaves the link behind and the term keeps a count from a post that is
	 * gone. Runs on before_delete_post.
	 *
	 * @since 1.8.3
	 *
	 * @param int $post_id Post being deleted.
	 * @return void
	 */
	public static function forget_company_hq( int $post_id ): void {
		if ( 'wcb_company' === get_post_type( $post_id ) ) {
			wp_delete_object_term_relationships( $post_id, 'wcb_location' );
		}
	}

	/**
	 * Remove HQ links left by companies deleted before 1.8.3 (upgrade repair).
	 *
	 * @since 1.8.3
	 * @return void
	 */
	public static function remove_orphan_hq_links(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off upgrade repair.
		$term_ids = array_map(
			'intval',
			$wpdb->get_col(
				"SELECT DISTINCT tt.term_taxonomy_id FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'wcb_location'
				LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id
				WHERE p.ID IS NULL"
			)
		);
		if ( array() === $term_ids ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off upgrade repair; only links to posts that no longer exist.
		$wpdb->query(
			"DELETE tr FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'wcb_location'
			LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id
			WHERE p.ID IS NULL"
		);
		wp_update_term_count_now( $term_ids, 'wcb_location' );
		clean_term_cache( $term_ids, 'wcb_location' );
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
	 * Place names of a job's location terms for a card: the reserved Remote and
	 * Other terms are a switch and a placeholder, never a place to print (the
	 * card shows Remote as its own chip).
	 *
	 * @since 1.8.4
	 * @param mixed $terms get_the_terms() result.
	 * @return string[]
	 */
	public static function place_names( $terms ): array {
		return Taxonomy::names( self::place_terms( $terms ) );
	}

	/**
	 * The location terms that are real places, as term objects for links.
	 *
	 * @since 1.8.4
	 * @param mixed $terms get_the_terms() or wp_get_object_terms() result.
	 * @return \WP_Term[]
	 */
	public static function place_terms( $terms ): array {
		if ( ! is_array( $terms ) ) {
			return array();
		}
		return array_values(
			array_filter(
				$terms,
				static fn( $t ): bool => ! ( $t instanceof \WP_Term ) || ! isset( self::RESERVED[ $t->slug ] )
			)
		);
	}

	/**
	 * Build the location-dropdown choices for a job-form render.
	 *
	 * Returns the terms an employer should see when posting a job:
	 *
	 *   - The HQ term of each company the employer runs.
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

		// Every company the employer runs, so a job posted for any of them can use its HQ.
		$company_ids = array_keys( \WCB\Modules\Employers\EmployersModule::owned_companies( $user_id ) );

		$terms    = array();
		$seen_ids = array();

		if ( $company_ids ) {
			$hq_terms = wp_get_object_terms( $company_ids, 'wcb_location', array( 'fields' => 'all' ) );
			if ( ! is_wp_error( $hq_terms ) ) {
				foreach ( (array) $hq_terms as $hq_term ) {
					if ( $hq_term instanceof \WP_Term && ! isset( $seen_ids[ $hq_term->term_id ] ) ) {
						$terms[]                       = $hq_term;
						$seen_ids[ $hq_term->term_id ] = true;
					}
				}
			}
		}

		// The forms add their own "Other (enter manually)" choice, so the reserved Other term stays out.
		foreach ( array_keys( self::RESERVED ) as $slug ) {
			if ( 'other' === $slug ) {
				continue;
			}
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

		return Taxonomy::sort( $terms );
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
		return Taxonomy::sort(
			array_values(
				array_filter(
					(array) $all,
					static fn( $t ): bool => 'other' !== $t->slug
				)
			)
		);
	}
}
