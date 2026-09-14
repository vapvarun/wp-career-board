<?php
/**
 * Candidates module — registers wcb_resume CPT.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Candidates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Candidates module class.
 *
 * @since 1.0.0
 */
final class CandidatesModule {

	/**
	 * Boot the module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 999 );
		add_action( 'update_option_wcb_settings', array( $this, 'on_settings_updated' ), 10, 2 );

		// wcb_resume registers public + show_in_rest so the block editor and the
		// single profile template work. That also hands WordPress core two
		// listings this plugin does not otherwise control - the wp/v2 REST
		// collection and the sitemap - and neither knows about the per-candidate
		// `_wcb_resume_public` opt-in, so both listed resumes their owner had
		// never agreed to publish (Basecamp 10301167163). Both are narrowed to
		// the opt-in here, in the module that registers the post type, so the
		// guarantee holds whether or not Pro is active.
		add_filter( 'rest_wcb_resume_query', array( $this, 'restrict_rest_query_to_listed' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'restrict_sitemap_to_listed' ), 10, 2 );
	}

	/**
	 * Flag a rewrite-rule flush when `resume_archive_enabled` toggles.
	 *
	 * Re-registering the CPT alone isn't enough — rewrite rules are cached and
	 * must be flushed for `/resume/{slug}` to resolve (or stop resolving). We
	 * defer the flush until the next request's init so the new CPT args are
	 * already in place when WordPress rebuilds the rules.
	 *
	 * @since 1.2.0
	 *
	 * @param  array<string,mixed> $old_settings Previous settings array.
	 * @param  array<string,mixed> $new_settings New settings array.
	 * @return void
	 */
	public function on_settings_updated( $old_settings, $new_settings ): void {
		$old_value = (bool) ( is_array( $old_settings ) ? ( $old_settings['resume_archive_enabled'] ?? false ) : false );
		$new_value = (bool) ( is_array( $new_settings ) ? ( $new_settings['resume_archive_enabled'] ?? false ) : false );
		if ( $old_value !== $new_value ) {
			update_option( 'wcb_flush_rewrite_rules', 1 );
		}
	}

	/**
	 * Run a deferred rewrite-rule flush when the flag is set.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function maybe_flush_rewrites(): void {
		if ( get_option( 'wcb_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_option( 'wcb_flush_rewrite_rules' );
		}
	}

	/**
	 * Register the wcb_resume post type.
	 *
	 * The CPT is always publicly queryable with a `/resume/{slug}/` permalink
	 * so single-resume URLs always resolve — they are required by the
	 * resume-archive block, the BuddyPress profile resume tab, the employer
	 * "view candidate" CTA, and Pro's resume-search hero. Earlier versions
	 * coupled CPT visibility to `wcb_settings.resume_archive_enabled`, which
	 * silently 404'd every "View Resume" link as soon as the archive page
	 * was disabled. That setting (and its `wcb_resume_archive_enabled`
	 * filter) now gates only the archive listing surface (see the
	 * resume-archive block render), not the CPT contract itself.
	 *
	 * `has_archive` stays false: the public listing is driven by the site
	 * owner's dedicated archive page (the `resume_archive_page` setting,
	 * which renders the `wcb/resume-archive` block) rather than WP's
	 * /resume/ index.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			'wcb_resume',
			array(
				'labels'             => array(
					'name'               => __( 'Resumes', 'wp-career-board' ),
					'singular_name'      => __( 'Resume', 'wp-career-board' ),
					'add_new_item'       => __( 'Add New Resume', 'wp-career-board' ),
					'edit_item'          => __( 'Edit Resume', 'wp-career-board' ),
					'not_found'          => __( 'No resumes found.', 'wp-career-board' ),
					'not_found_in_trash' => __( 'No resumes found in Trash.', 'wp-career-board' ),
					// Without these WP falls back to the generic post labels and the
					// list table's search button reads "Search Posts" on a screen
					// that only ever contains resumes.
					'search_items'       => __( 'Search Resumes', 'wp-career-board' ),
					'all_items'          => __( 'All Resumes', 'wp-career-board' ),
					'view_item'          => __( 'View Resume', 'wp-career-board' ),
					'new_item'           => __( 'New Resume', 'wp-career-board' ),
					'add_new'            => __( 'Add New Resume', 'wp-career-board' ),
				),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'show_in_menu'       => false,
				'has_archive'        => false,
				'show_in_nav_menus'  => false,
				'rewrite'            => array(
					'slug'       => 'resume',
					'with_front' => false,
				),
				'supports'           => array( 'title', 'custom-fields' ),
				'capability_type'    => 'post',
				'map_meta_cap'       => true,
			)
		);
	}

	/**
	 * Whether the current user may see resumes their owner has not listed.
	 *
	 * Whoever administers the plugin legitimately needs the full set - otherwise
	 * they cannot see, in wp-admin or the block editor, resumes the site holds.
	 * Everybody else gets only what candidates opted into publishing.
	 *
	 * @since 1.7.1
	 * @return bool
	 */
	private function can_see_unlisted_resumes(): bool {
		return wp_is_ability_granted( 'wcb/manage-settings' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
	}

	/**
	 * Limit the core wp/v2 resume collection to resumes the owner listed.
	 *
	 * @since 1.7.1
	 *
	 * @param  array            $args    WP_Query args assembled by the REST controller.
	 * @param  \WP_REST_Request $request The request.
	 * @return array
	 */
	public function restrict_rest_query_to_listed( $args, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( $this->can_see_unlisted_resumes() ) {
			return $args;
		}

		$args = is_array( $args ) ? $args : array();

		$args['meta_query'] = array_merge( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one indexed meta key on a bounded collection.
			isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array(),
			array(
				array(
					'key'   => '_wcb_resume_public',
					'value' => '1',
				),
			)
		);

		/**
		 * Filters the core REST args for the resume collection.
		 *
		 * Pro narrows this further when the candidate directory is not public.
		 *
		 * @since 1.7.1
		 *
		 * @param array            $args    Query args, already limited to listed resumes.
		 * @param \WP_REST_Request $request The request.
		 */
		return apply_filters( 'wcb_resume_rest_query_args', $args, $request );
	}

	/**
	 * Keep resumes the owner never listed out of the sitemap.
	 *
	 * They 404 for anonymous visitors, so advertising them is both a privacy
	 * leak - real sites carry the candidate's name in the slug - and a sitemap
	 * full of dead URLs.
	 *
	 * @since 1.7.1
	 *
	 * @param  array  $args      WP_Query args for the sitemap provider.
	 * @param  string $post_type Post type being listed.
	 * @return array
	 */
	public function restrict_sitemap_to_listed( $args, $post_type ) {
		if ( 'wcb_resume' !== $post_type ) {
			return $args;
		}

		$args = is_array( $args ) ? $args : array();

		$args['meta_query'] = array_merge( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one indexed meta key, sitemap pages are bounded.
			isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array(),
			array(
				array(
					'key'   => '_wcb_resume_public',
					'value' => '1',
				),
			)
		);

		/**
		 * Filters the sitemap args for resumes.
		 *
		 * Pro drops the post type entirely when the directory is not public.
		 *
		 * @since 1.7.1
		 *
		 * @param array $args Query args, already limited to listed resumes.
		 */
		return apply_filters( 'wcb_resume_sitemap_query_args', $args );
	}
}
