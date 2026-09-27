<?php
/**
 * The Career Board pages - Post a Job, the two dashboards, the archives,
 * registration - and everything that creates or resolves them.
 *
 * @package WP_Career_Board
 * @since   1.2.4
 */

declare( strict_types=1 );

namespace WCB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One definition per page: its settings key, title, slug, block content and
 * Pages-tab copy. The setup wizard, Settings > Pages "Create Missing Pages",
 * the Pages tab and the resolver all read it, so a page's title and slug can
 * no longer differ by which screen created it. Pro adds its pages through
 * `wcb_page_definitions`.
 *
 * @since 1.2.4
 */
final class Pages {

	/**
	 * Per-request cache.
	 *
	 * @var array<string, array{title: string, slug: string, content: string, label: string, desc: string, aliases: string[]}>|null
	 */
	private static ?array $definitions = null;

	/**
	 * Every page definition, keyed by its wcb_settings key.
	 *
	 * @since 1.8.0
	 * @return array<string, array{title: string, slug: string, content: string, label: string, desc: string, aliases: string[]}>
	 */
	public static function definitions(): array {
		// Cached only once `init` has run, for the same reason as SettingsSchema.
		if ( null !== self::$definitions && did_action( 'init' ) ) {
			return self::$definitions;
		}

		$defs = array(
			'jobs_archive_page'          => array(
				'title'   => __( 'Find Jobs', 'wp-career-board' ),
				// "find-jobs", not "jobs": the wcb_job CPT archive owns /jobs/.
				'slug'    => 'find-jobs',
				'content' => '<!-- wp:heading {"level":1,"className":"wcb-page-heading"} --><h1 class="wp-block-heading wcb-page-heading">' . esc_html__( 'Find Jobs', 'wp-career-board' ) . '</h1><!-- /wp:heading --><!-- wp:wp-career-board/job-search /--><!-- wp:wp-career-board/job-listings /-->',
				'label'   => __( 'Jobs Archive Page', 'wp-career-board' ),
				'desc'    => __( 'Contains the wcb/job-listings block. Used as the main job board.', 'wp-career-board' ),
			),
			'employer_dashboard_page'    => array(
				'title'   => __( 'Employer Dashboard', 'wp-career-board' ),
				'slug'    => 'employer-dashboard',
				'content' => '<!-- wp:wp-career-board/employer-dashboard /-->',
				'label'   => __( 'Employer Dashboard Page', 'wp-career-board' ),
				'desc'    => __( 'Contains the wcb/employer-dashboard block. Employers manage jobs and profiles here.', 'wp-career-board' ),
			),
			'candidate_dashboard_page'   => array(
				'title'   => __( 'Candidate Dashboard', 'wp-career-board' ),
				'slug'    => 'candidate-dashboard',
				'content' => '<!-- wp:wp-career-board/candidate-dashboard /-->',
				'label'   => __( 'Candidate Dashboard Page', 'wp-career-board' ),
				'desc'    => __( 'Contains the wcb/candidate-dashboard block. Candidates track applications and saved jobs.', 'wp-career-board' ),
			),
			'company_archive_page'       => array(
				'title'   => __( 'Find Companies', 'wp-career-board' ),
				// "find-companies", not "companies": the wcb_company CPT archive owns /companies/.
				'slug'    => 'find-companies',
				'content' => '<!-- wp:wp-career-board/company-archive /-->',
				'label'   => __( 'Company Directory Page', 'wp-career-board' ),
				'desc'    => __( 'Contains the wcb/company-archive block. Lists all employer company profiles.', 'wp-career-board' ),
			),
			'post_job_page'              => array(
				'title'   => __( 'Post a Job', 'wp-career-board' ),
				'slug'    => 'post-a-job',
				'content' => '<!-- wp:wp-career-board/job-form /-->',
				'label'   => __( 'Post a Job Page', 'wp-career-board' ),
				'desc'    => __( 'Contains the wcb/job-form block. Employers use this to post new jobs.', 'wp-career-board' ),
			),
			'employer_registration_page' => array(
				// The block is a dual role picker (Find a Job / Hire Talent); the
				// key and slug stay "employer" because the docs link to them.
				'title'   => __( 'Employer Registration', 'wp-career-board' ),
				'slug'    => 'employer-registration',
				'content' => '<!-- wp:wp-career-board/employer-registration /-->',
				'label'   => __( 'Employer Registration Page', 'wp-career-board' ),
				'desc'    => __( 'Contains the wcb/employer-registration block. New employers sign up and gain posting access here.', 'wp-career-board' ),
			),
		);

		/**
		 * Filter the Career Board page definitions.
		 *
		 * Add a page: `$defs['my_page'] = array( 'title' => ..., 'slug' => ..., 'content' => '<!-- wp:my/block /-->', 'label' => ..., 'desc' => ... );`
		 * and register `my_page` in `wcb_settings_schema`. Optional `aliases`
		 * lists older slugs the resolver still accepts.
		 *
		 * @since 1.8.0
		 *
		 * @param array<string, array<string, mixed>> $defs Definitions keyed by wcb_settings key.
		 */
		$defs = (array) apply_filters( 'wcb_page_definitions', $defs );

		// Replaced by wcb_page_definitions; still honoured so an add-on on the
		// old filters keeps its pages.
		$defs = (array) apply_filters_deprecated( 'wcb_wizard_required_pages', array( $defs ), '1.8.0', 'wcb_page_definitions' );
		foreach ( (array) apply_filters_deprecated( 'wcb_page_settings', array( array() ), '1.8.0', 'wcb_page_definitions' ) as $key => $info ) {
			$defs[ $key ] = array_merge( $defs[ $key ] ?? array(), (array) $info );
		}

		$out = array();
		foreach ( $defs as $key => $def ) {
			$title       = (string) ( $def['title'] ?? $def['label'] ?? '' );
			$out[ $key ] = array(
				'title'   => $title,
				'slug'    => (string) ( $def['slug'] ?? sanitize_title( $title ) ),
				'content' => (string) ( $def['content'] ?? '' ),
				'label'   => (string) ( $def['label'] ?? $title ),
				'desc'    => (string) ( $def['desc'] ?? '' ),
				'aliases' => array_map( 'strval', (array) ( $def['aliases'] ?? array() ) ),
			);
		}

		self::$definitions = $out;
		return $out;
	}

	/**
	 * Known wcb_settings page keys.
	 *
	 * @since 1.2.4
	 * @return array<int,string>
	 */
	public static function known_keys(): array {
		return array_keys( self::definitions() );
	}

	/**
	 * Canonical slug for a page key, or an empty string.
	 *
	 * @since 1.2.4
	 *
	 * @param string $key wcb_settings page key.
	 * @return string
	 */
	public static function canonical_slug( string $key ): string {
		return self::definitions()[ $key ]['slug'] ?? '';
	}

	/**
	 * Resolve the page ID for a key: the assigned page when it is published,
	 * else a published page at the canonical slug or an alias, else 0.
	 *
	 * @since 1.2.4
	 *
	 * @param string $key wcb_settings page key.
	 * @return int
	 */
	public static function get_id( string $key ): int {
		$def = self::definitions()[ $key ] ?? null;
		if ( null === $def ) {
			return 0;
		}

		$assigned = Settings::int( $key, 0 );
		if ( $assigned > 0 && 'publish' === get_post_status( $assigned ) ) {
			return $assigned;
		}

		foreach ( array_merge( array( $def['slug'] ), $def['aliases'] ) as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page instanceof \WP_Post && 'publish' === $page->post_status ) {
				return (int) $page->ID;
			}
		}
		return 0;
	}

	/**
	 * Permalink for a page key, or an empty string when none resolves.
	 *
	 * @since 1.8.0
	 *
	 * @param string $key wcb_settings page key.
	 * @return string
	 */
	public static function url( string $key ): string {
		$id = self::get_id( $key );
		return $id ? (string) get_permalink( $id ) : '';
	}

	/**
	 * Keys whose page does not resolve.
	 *
	 * @since 1.8.0
	 * @return string[]
	 */
	public static function missing(): array {
		return array_values( array_filter( self::known_keys(), static fn ( string $key ): bool => 0 === self::get_id( $key ) ) );
	}

	/**
	 * Create every missing page (or just `$keys`) and store the IDs.
	 *
	 * A page that already resolves is kept. Before inserting, a published
	 * page that already carries the definition's block is adopted, so a site
	 * that built its own "Jobs" page doesn't get a duplicate.
	 *
	 * @since 1.8.0
	 *
	 * @param string[]|null $keys Keys to create; null for all.
	 * @return array<string,int> Key => page ID, for every key now resolved.
	 */
	public static function create_missing( ?array $keys = null ): array {
		$defs = self::definitions();
		if ( null !== $keys ) {
			$defs = array_intersect_key( $defs, array_flip( $keys ) );
		}

		$settings = (array) get_option( 'wcb_settings', array() );
		$resolved = array();

		foreach ( $defs as $key => $def ) {
			$id = self::get_id( $key );

			if ( ! $id && '' !== $def['content'] ) {
				$id = self::find_page_with_block( $def['content'] );
			}

			if ( ! $id && '' !== $def['content'] ) {
				$inserted = wp_insert_post(
					array(
						'post_title'   => $def['title'],
						'post_name'    => $def['slug'],
						'post_content' => $def['content'],
						'post_status'  => 'publish',
						'post_type'    => 'page',
					),
					true
				);
				$id       = is_wp_error( $inserted ) ? 0 : (int) $inserted;
			}

			if ( $id ) {
				$settings[ $key ] = $id;
				$resolved[ $key ] = $id;
			}
		}

		update_option( 'wcb_settings', $settings );
		return $resolved;
	}

	/**
	 * Backfill assigned IDs from canonical slugs for keys with no published
	 * assignment. Idempotent; run from the install gate for sites whose
	 * wizard predates page IDs being stored.
	 *
	 * @since 1.2.4
	 * @return array<string,int> Key => ID for every key written.
	 */
	public static function backfill_from_slugs(): array {
		$settings = Settings::all();
		$written  = array();

		foreach ( self::known_keys() as $key ) {
			$current = (int) ( $settings[ $key ] ?? 0 );
			if ( $current > 0 && 'publish' === get_post_status( $current ) ) {
				continue;
			}
			$id = self::get_id( $key );
			if ( $id ) {
				$settings[ $key ] = $id;
				$written[ $key ]  = $id;
			}
		}

		if ( $written ) {
			update_option( 'wcb_settings', $settings );
		}
		return $written;
	}

	/**
	 * Drop the per-request cache (tests; late definition filters).
	 *
	 * @internal
	 * @return void
	 */
	public static function flush(): void {
		self::$definitions = null;
	}

	/**
	 * A published page containing the first Career Board block in `$content`.
	 *
	 * Matches the plugin's own block, not the first block: Find Jobs leads
	 * with a heading, and keying on "heading" adopted any page with one.
	 *
	 * @param string $content Definition block markup.
	 * @return int
	 */
	private static function find_page_with_block( string $content ): int {
		if ( ! preg_match( '/<!-- wp:((?:wp-career-board|wcb)\/[a-z0-9-]+)/', $content, $m ) ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				's'              => $m[1],
				'no_found_rows'  => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}
}
