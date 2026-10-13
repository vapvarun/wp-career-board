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
		add_action( 'template_redirect', array( $this, 'guard_single_resume' ) );

		// Applications and resumes are not "posts" that make someone a public
		// author: counting them let anonymous /wp/v2/users list every
		// applicant, and author archives showed their names.
		add_filter( 'rest_user_query', array( $this, 'hide_applicants_from_user_rest' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'guard_candidate_author_archive' ), 0 );

		// A candidate is anyone who has applied or has a resume (owner decision,
		// 1.8.1), so the role follows the first of either and every screen can
		// keep its fast role query. Saves cover REST, import, CLI and guest claims alike.
		foreach ( array( 'save_post_wcb_application', 'save_post_wcb_resume' ) as $wcb_hook ) {
			add_action(
				$wcb_hook,
				static function ( int $post_id, \WP_Post $post ): void {
					unset( $post_id );
					self::grant_role( (int) $post->post_author );
				},
				10,
				2
			);
		}
		add_action( self::BACKFILL_HOOK, array( self::class, 'backfill_roles' ) );
	}

	/**
	 * Cron hook that grants the candidate role to applicants from before 1.8.1.
	 *
	 * @since 1.8.1
	 */
	public const BACKFILL_HOOK = 'wcb_backfill_candidate_role';

	/**
	 * Give a member the candidate role once they apply or create a resume.
	 *
	 * Administrators are left alone: their own test applications should not
	 * add them to the Candidates screen.
	 *
	 * @since 1.8.1
	 *
	 * @param int $user_id Applicant or resume author.
	 * @return void
	 */
	public static function grant_role( int $user_id ): void {
		$user = $user_id > 0 ? get_userdata( $user_id ) : false;
		if ( ! $user || in_array( 'wcb_candidate', (array) $user->roles, true ) || $user->has_cap( 'manage_options' ) ) {
			return;
		}
		\WCB\Core\Roles::grant_member_role( $user, 'wcb_candidate' );
	}

	/**
	 * Grant the candidate role to existing applicants and resume authors.
	 *
	 * Runs in the background after the 1.8.1 upgrade, 200 authors a batch,
	 * walking author IDs upward so skipped users (admins) never stall it.
	 *
	 * @since 1.8.1
	 *
	 * @param int $after Last author ID handled by the previous batch.
	 * @return void
	 */
	public static function backfill_roles( int $after = 0 ): void {
		global $wpdb;
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off background batch.
			$wpdb->prepare(
				"SELECT DISTINCT post_author FROM {$wpdb->posts}
				WHERE post_type IN ( 'wcb_application', 'wcb_resume' ) AND post_author > %d
				ORDER BY post_author ASC LIMIT 200",
				$after
			)
		);
		foreach ( $ids as $id ) {
			self::grant_role( (int) $id );
		}
		if ( 200 === count( $ids ) ) {
			wp_schedule_single_event( time() + 30, self::BACKFILL_HOOK, array( (int) end( $ids ) ) );
		}
	}

	/**
	 * Keep application and resume authors out of core's public user list.
	 *
	 * Core lists users who have published posts in any REST-visible type.
	 * Admins keep the full list.
	 *
	 * @since 1.8.0
	 * @param array<string,mixed> $args    WP_User_Query args.
	 * @param \WP_REST_Request    $request Request.
	 * @return array<string,mixed>
	 */
	public function hide_applicants_from_user_rest( array $args, \WP_REST_Request $request ): array {
		unset( $request );
		if ( empty( $args['has_published_posts'] ) || wp_is_ability_granted( 'wcb/manage-settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
			return $args;
		}
		$types                       = true === $args['has_published_posts'] ? get_post_types( array( 'show_in_rest' => true ) ) : (array) $args['has_published_posts'];
		$args['has_published_posts'] = array_values( array_diff( $types, array( 'wcb_application', 'wcb_resume' ) ) );
		return $args;
	}

	/**
	 * 404 the author archive of a member who is only a candidate.
	 *
	 * Candidates publish nothing that belongs on an author page; the archive
	 * only confirmed the account exists and printed its name.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function guard_candidate_author_archive(): void {
		if ( ! is_author() ) {
			return;
		}
		$author = get_queried_object();
		if ( ! $author instanceof \WP_User || array_diff( (array) $author->roles, array( 'wcb_candidate', 'subscriber' ) ) ) {
			return;
		}
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
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
				'labels'                => array(
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
				'public'                => true,
				'publicly_queryable'    => true,
				'show_ui'               => true,
				'show_in_rest'          => true,
				// Core's controller would serve an unlisted resume to anyone who
				// guessed its id; see ResumeRestController.
				'rest_controller_class' => ResumeRestController::class,
				'show_in_menu'          => false,
				'has_archive'           => false,
				'show_in_nav_menus'     => false,
				'rewrite'               => array(
					'slug'       => 'resume',
					'with_front' => false,
				),
				'supports'              => array( 'title', 'custom-fields' ),
				'capability_type'       => 'post',
				'map_meta_cap'          => true,
			)
		);
	}

	/**
	 * A candidate's picture: the photo on their resume, else their avatar.
	 *
	 * The resume photo is the one the candidate chose for job seeking, so every
	 * Career Board surface (cards, applications, profiles) shows it. Pass the
	 * resume when the caller already has it; otherwise their latest published
	 * resume is used.
	 *
	 * @since 1.8.1
	 *
	 * @param int $user_id   Candidate user ID.
	 * @param int $size      Size in pixels.
	 * @param int $resume_id Resume to take the photo from (optional).
	 * @return string Image URL.
	 */
	public static function avatar_url( int $user_id, int $size = 96, int $resume_id = 0 ): string {
		if ( ! $resume_id && $user_id ) {
			$latest    = get_posts(
				array(
					'post_type'      => 'wcb_resume',
					'post_status'    => 'publish',
					'author'         => $user_id,
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			$resume_id = (int) ( $latest[0] ?? 0 );
		}
		$photo = $resume_id ? (int) get_post_meta( $resume_id, '_wcb_resume_photo_id', true ) : 0;
		$url   = $photo ? wp_get_attachment_image_url( $photo, array( $size, $size ) ) : '';

		return $url ? (string) $url : (string) get_avatar_url( $user_id, array( 'size' => $size ) );
	}

	/**
	 * Frontend resume an admin or employer should open for a candidate: the
	 * one attached to the application, else their latest published resume.
	 *
	 * @since 1.8.1
	 *
	 * @param int $user_id        Candidate user ID.
	 * @param int $application_id Application the link is for (optional).
	 * @return string Resume URL, or '' when there is none.
	 */
	public static function resume_url( int $user_id, int $application_id = 0 ): string {
		$resume_id = $application_id ? (int) get_post_meta( $application_id, '_wcb_resume_id', true ) : 0;
		if ( ! $resume_id || 'publish' !== get_post_status( $resume_id ) ) {
			$latest    = $user_id ? get_posts(
				array(
					'post_type'      => 'wcb_resume',
					'post_status'    => 'publish',
					'author'         => $user_id,
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			) : array();
			$resume_id = (int) ( $latest[0] ?? 0 );
		}
		$url = $resume_id && self::resume_is_readable( $resume_id ) ? (string) get_permalink( $resume_id ) : '';

		/**
		 * Filters the resume link shown for a candidate in admin.
		 *
		 * @since 1.8.1
		 *
		 * @param string $url            Resume URL ('' hides the link).
		 * @param int    $user_id        Candidate user ID.
		 * @param int    $application_id Application ID, or 0.
		 */
		return (string) apply_filters( 'wcb_candidate_resume_url', $url, $user_id, $application_id );
	}

	/**
	 * Whether one resume may be read by the current user.
	 *
	 * The single decision behind every read path: the core REST controller, the
	 * permalink, and anything else that resolves one resume. A resume is
	 * readable when its owner listed it, when the viewer administers the plugin,
	 * or when the viewer is the candidate it belongs to - a candidate must
	 * always be able to see their own resume whether or not they published it.
	 *
	 * @since 1.7.1
	 *
	 * @param  int $post_id Resume post ID.
	 * @return bool
	 */
	public static function resume_is_readable( int $post_id ): bool {
		$viewer = get_current_user_id();
		$owner  = (int) get_post_field( 'post_author', $post_id );

		if ( $viewer > 0 && $viewer === $owner ) {
			return true;
		}
		if ( wp_is_ability_granted( 'wcb/manage-settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
			return true;
		}
		// A resume moderation took down (a suspended candidate's) is private, listed or not.
		if ( '' !== \WCB\Modules\Moderation\HiddenContent::reason( $post_id ) ) {
			return false;
		}
		// A block hides the candidate from the blocker (and back), whatever the
		// resume's visibility.
		if ( $viewer > 0 && \WCB\Core\Blocks::is_hidden( $viewer, $owner ) ) {
			return false;
		}

		if ( '1' === (string) get_post_meta( $post_id, '_wcb_resume_public', true ) ) {
			/**
			 * Filter whether the current viewer may open a public resume.
			 *
			 * Pro applies the owner's "Who can open a resume" setting here.
			 *
			 * @since 1.8.0
			 *
			 * @param bool $allowed   Default true.
			 * @param int  $post_id   Resume post ID.
			 * @param int  $viewer_id Current user ID (0 when logged out).
			 */
			if ( (bool) apply_filters( 'wcb_can_view_public_resume', true, $post_id, $viewer ) ) {
				return true;
			}
		}

		// An employer always sees the resume of someone who applied to them.
		return self::has_applied_to( $owner, $viewer );
	}

	/**
	 * Whether a candidate has applied to a job owned by this employer.
	 *
	 * Owned = the job's author, or the job's company is the employer's company.
	 * One indexed query, no cap (the previous check stopped at the candidate's
	 * first 100 applications and ran a query per row).
	 *
	 * @since 1.8.0
	 *
	 * @param int $candidate_id Candidate user ID.
	 * @param int $employer_id  Employer user ID.
	 * @return bool
	 */
	public static function has_applied_to( int $candidate_id, int $employer_id ): bool {
		if ( $candidate_id <= 0 || $employer_id <= 0 ) {
			return false;
		}
		global $wpdb;
		$company_id = \WCB\Core\CompanyMetaShape::resolve_company_id( $employer_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed existence check; cached per request by callers' call frequency.
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->postmeta} cand
				INNER JOIN {$wpdb->posts} app ON app.ID = cand.post_id AND app.post_type = 'wcb_application'
				INNER JOIN {$wpdb->postmeta} jm ON jm.post_id = app.ID AND jm.meta_key = '_wcb_job_id'
				INNER JOIN {$wpdb->posts} job ON job.ID = jm.meta_value
				LEFT JOIN {$wpdb->postmeta} jc ON jc.post_id = job.ID AND jc.meta_key = '_wcb_company_id'
				WHERE cand.meta_key = '_wcb_candidate_id' AND cand.meta_value = %s
				AND ( job.post_author = %d OR ( %d > 0 AND jc.meta_value = %s ) )
				LIMIT 1",
				(string) $candidate_id,
				$employer_id,
				$company_id,
				(string) $company_id
			)
		);
	}

	/**
	 * Send an unlisted resume's permalink to a 404.
	 *
	 * The REST controller closes the API read; this closes the page. Without it
	 * the profile still rendered at /resume/{slug}/ with the candidate's name in
	 * the title tag, which is the same disclosure by a different door.
	 *
	 * A 404 rather than a redirect or a sign-in wall: to anyone not entitled to
	 * it the resource genuinely does not exist, and a wall would confirm it does.
	 *
	 * @since 1.7.1
	 * @return void
	 */
	public function guard_single_resume(): void {
		if ( ! is_singular( 'wcb_resume' ) ) {
			return;
		}

		$post_id = (int) get_queried_object_id();

		// A public resume the viewer may not open (e.g. "logged-in members
		// only") is left to the resume block, which shows a sign-in wall, unless
		// the candidate blocked this viewer: then it does not exist for them.
		$viewer  = get_current_user_id();
		$owner   = (int) get_post_field( 'post_author', $post_id );
		$blocked = $viewer > 0 && \WCB\Core\Blocks::is_hidden( $viewer, $owner );
		if ( $post_id <= 0 || self::resume_is_readable( $post_id ) || ( ! $blocked && '1' === (string) get_post_meta( $post_id, '_wcb_resume_public', true ) ) ) {
			return;
		}

		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
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
