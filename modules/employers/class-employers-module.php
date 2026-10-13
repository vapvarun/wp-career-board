<?php
/**
 * Employers module — registers wcb_company CPT.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Employers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Employers module class.
 *
 * @since 1.0.0
 */
final class EmployersModule {

	/**
	 * Boot the module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_filter( 'the_content', array( $this, 'inject_company_profile' ) );
		add_filter( 'body_class', array( $this, 'add_company_body_class' ) );
		add_filter( 'template_include', array( $this, 'archive_template' ) );
		add_filter( 'template_include', array( $this, 'single_company_template' ) );
		add_filter( 'login_redirect', array( $this, 'employer_login_redirect' ), 10, 3 );

		// Distinct-industry lookup is cached for an hour on the Companies
		// archive (see blocks/company-archive/render.php). When an admin saves
		// a company - including industry meta changes through the editor or
		// REST - bust the cache so the filter dropdown reflects reality on
		// the next request. Same hook runs whether the post enters via wp-admin,
		// REST, or wp-cli, so we don't need to scatter cache resets per
		// surface.
		add_action(
			'save_post_wcb_company',
			static function (): void {
				wp_cache_delete( 'wcb_distinct_industries', 'wcb_companies' );
				// Bump the /companies REST list-cache version so the next request
				// rebuilds from fresh rows (see CompaniesEndpoint::get_items_cache_key).
				update_option( 'wcb_companies_cache_v', (int) get_option( 'wcb_companies_cache_v', 0 ) + 1, false );
			}
		);
		add_action(
			'delete_post',
			static function ( $post_id ): void {
				if ( 'wcb_company' === get_post_type( $post_id ) ) {
					wp_cache_delete( 'wcb_distinct_industries', 'wcb_companies' );
					update_option( 'wcb_companies_cache_v', (int) get_option( 'wcb_companies_cache_v', 0 ) + 1, false );
				}
			}
		);

		add_action( 'delete_user', array( self::class, 'retire_owned_listings' ), 10, 2 );
	}

	/**
	 * Take a deleted employer's jobs and companies off the site.
	 *
	 * WordPress moves them to the chosen user when the admin picks "Attribute
	 * all content to"; without that, neither post type is deleted with its
	 * author, so they stayed public with no owner. Jobs close (applicants are
	 * told, the history stays) and companies go back to draft: off the site
	 * but recoverable (owner decision, 1.8.1).
	 *
	 * @since 1.8.1
	 *
	 * @param int      $user_id  User being deleted.
	 * @param int|null $reassign User receiving the content, or null.
	 * @return void
	 */
	public static function retire_owned_listings( int $user_id, ?int $reassign ): void {
		if ( $reassign ) {
			return;
		}
		$targets = array(
			'wcb_job'     => array( 'wcb_closed', array( 'publish', 'pending', 'draft', 'future', 'wcb_expired' ) ),
			'wcb_company' => array( 'draft', array( 'publish', 'pending', 'future' ) ),
		);
		foreach ( $targets as $post_type => list( $new_status, $from ) ) {
			// Each pass moves rows out of $from, so page 1 always holds the next batch.
			do {
				$ids = get_posts(
					array(
						'post_type'      => $post_type,
						'post_status'    => $from,
						'author'         => $user_id,
						'fields'         => 'ids',
						'posts_per_page' => 100,
						'no_found_rows'  => true,
					)
				);
				foreach ( $ids as $id ) {
					wp_update_post(
						array(
							'ID'          => (int) $id,
							'post_status' => $new_status,
						)
					);
				}
				$full = 100 === count( $ids );
			} while ( $full );
		}
	}

	/* Note: the `wcb-company-sidebar` widget area was retired in 1.2.x.
	 * The company-profile block now always renders its three default
	 * sidebar blocks (Similar Companies, Recent Jobs, Job Alert) so the
	 * sidebar shape stays product-coherent. Site admins frequently
	 * misassigned generic / footer widgets to the company sidebar, which
	 * produced white-on-white footer columns inside the company profile.
	 * If you need the widget-area pattern back, hook `register_sidebar()`
	 * yourself and call `dynamic_sidebar()` via a filter on the rendered
	 * block output - this plugin no longer exposes the slot. */

	/**
	 * Redirect employers to the employer dashboard after login.
	 *
	 * @since 1.0.0
	 *
	 * @param string             $redirect_to           The redirect destination URL.
	 * @param string             $requested_redirect_to The requested redirect destination URL passed as a parameter.
	 * @param \WP_User|\WP_Error $user                 WP_User object if login was successful, WP_Error otherwise.
	 * @return string
	 */
	public function employer_login_redirect( string $redirect_to, string $requested_redirect_to, \WP_User|\WP_Error $user ): string {
		if ( ! ( $user instanceof \WP_User ) || ! in_array( 'wcb_employer', (array) $user->roles, true ) ) {
			return $redirect_to;
		}

		/**
		 * Filters whether employers are sent to the employer dashboard on login.
		 *
		 * Return false to leave the login destination entirely alone, for sites
		 * that route employers somewhere else.
		 *
		 * @since 1.7.1
		 *
		 * @param bool      $enabled               Whether to apply the dashboard redirect.
		 * @param \WP_User  $user                  The user logging in.
		 * @param string    $redirect_to           Destination chosen so far.
		 * @param string    $requested_redirect_to Explicitly requested destination, if any.
		 */
		if ( ! apply_filters( 'wcb_employer_login_redirect_enabled', true, $user, $redirect_to, $requested_redirect_to ) ) {
			return $redirect_to;
		}

		// An explicit redirect_to wins: the member, or the login form, asked to
		// land somewhere specific and this plugin is not entitled to overrule it.
		if ( '' !== trim( $requested_redirect_to ) ) {
			return $redirect_to;
		}

		// Another plugin's login_redirect filter - BuddyPress Redirect, or the
		// site's own login-redirect setting - may already have chosen a
		// destination. Only step in while $redirect_to is still WordPress's
		// untouched default, so this is a default rather than an override
		// (Basecamp 10299127872, Zoho #41525).
		if ( ! $this->is_default_login_redirect( $redirect_to ) ) {
			return $redirect_to;
		}

		$dashboard_id = \WCB\Admin\Pages::get_id( 'employer_dashboard_page' );

		if ( ! $dashboard_id ) {
			return $redirect_to;
		}

		$dashboard_url = get_permalink( $dashboard_id );
		return $dashboard_url ? (string) $dashboard_url : $redirect_to;
	}

	/**
	 * Whether $redirect_to is still WordPress's own default login destination.
	 *
	 * Anything else means somebody - another plugin, or the site owner's
	 * setting - has already expressed a preference worth deferring to.
	 *
	 * @since 1.7.1
	 *
	 * @param string $redirect_to Destination chosen so far.
	 * @return bool
	 */
	private function is_default_login_redirect( string $redirect_to ): bool {
		$redirect_to = trim( $redirect_to );

		if ( '' === $redirect_to ) {
			return true;
		}

		$strip = static function ( string $url ): string {
			$url = explode( '?', $url, 2 )[0];
			return untrailingslashit( $url );
		};

		return $strip( $redirect_to ) === $strip( admin_url() );
	}

	/**
	 * Add wcb-company-page body class on wcb_company single pages.
	 *
	 * Enables the block stylesheet to suppress any active theme's sidebar and
	 * duplicate post title so the layout works consistently across all themes.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $classes Existing body classes.
	 * @return string[]
	 */
	public function add_company_body_class( array $classes ): array {
		if ( is_singular( 'wcb_company' ) ) {
			$classes[] = 'wcb-company-page';
		}
		return $classes;
	}

	/**
	 * Replace default post content with the company-profile block on single company pages.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Original post content.
	 * @return string Block-rendered output or original content.
	 */
	public function inject_company_profile( string $content ): string {
		if ( ! is_singular( 'wcb_company' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		return render_block(
			array(
				'blockName'    => 'wp-career-board/company-profile',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Serve the company-archive block template on the wcb_company archive.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template Path to the template file to include.
	 * @return string Overridden or original template path.
	 */
	public function archive_template( string $template ): string {
		if ( ! is_post_type_archive( 'wcb_company' ) || \WCB\Core\TemplateOverride::block_theme() ) {
			return $template;
		}

		$custom = WCB_DIR . 'modules/employers/templates/archive-wcb-company.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}

		return $template;
	}

	/**
	 * Serve the plugin-shipped single company template for `/companies/{slug}/`.
	 *
	 * Mirrors `Jobs_Module::single_job_template()` and Pro's
	 * `Resume_Module::single_resume_template()` so single company pages render
	 * with the same `get_header() + block + get_footer()` chrome as single
	 * jobs and single resumes — same width, same product family, same font
	 * (which is the active theme's body font; the plugin contributes zero
	 * font CSS of its own). Without this filter, `/companies/{slug}/` falls
	 * back to the theme's default `single.php` which uses different chrome
	 * (sidebar, narrower content column, theme single-page typography), so
	 * the archive and single visually drift apart.
	 *
	 * Theme integrations that ship their own `single-wcb_company.php` win via
	 * WP's template hierarchy — we only step in when the theme has not.
	 *
	 * @since 1.2.0
	 *
	 * @param string $template Template path WP would otherwise load.
	 * @return string
	 */
	public function single_company_template( string $template ): string {
		if ( ! is_singular( 'wcb_company' ) ) {
			return $template;
		}
		// Theme integrations (Reign, BuddyX Pro) set their own template via single_template.
		// See TemplateOverride - theme templates and the bundled integrations both win.
		if ( \WCB\Core\TemplateOverride::keep( $template, 'single-wcb_company.php' ) ) {
			return $template;
		}
		$override = WCB_DIR . 'modules/employers/templates/single-wcb_company.php';
		return file_exists( $override ) ? $override : $template;
	}

	/**
	 * Register the wcb_company post type.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			'wcb_company',
			array(
				'labels'          => array(
					'name'               => __( 'Companies', 'wp-career-board' ),
					'singular_name'      => __( 'Company', 'wp-career-board' ),
					'add_new_item'       => __( 'Add New Company', 'wp-career-board' ),
					'edit_item'          => __( 'Edit Company', 'wp-career-board' ),
					'view_item'          => __( 'View Company', 'wp-career-board' ),
					'not_found'          => __( 'No companies found.', 'wp-career-board' ),
					'not_found_in_trash' => __( 'No companies found in Trash.', 'wp-career-board' ),
				),
				'public'          => true,
				'show_in_rest'    => true,
				'show_in_menu'    => false,
				'supports'        => array( 'title', 'editor', 'thumbnail' ),
				'rewrite'         => array(
					'slug'       => 'companies',
					'with_front' => false,
				),
				'has_archive'     => 'companies',
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Companies an employer runs, as id => name, sorted by name.
	 *
	 * One employer can run many companies (agencies, recruiters): every company
	 * they created, every company naming them in `_wcb_company_owner`, and the
	 * company linked on their account.
	 *
	 * @since 1.8.1
	 *
	 * @param int $user_id Employer user ID.
	 * @return array<int, string>
	 */
	public static function owned_companies( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}
		global $wpdb;
		// shortcut: capped at 200 companies per employer; raise it if an agency outgrows that.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one indexed lookup (post_author / wcb_meta_key_value).
		$rows      = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts}
				 WHERE post_type = 'wcb_company' AND post_status IN ( 'publish', 'pending', 'draft' )
				   AND ( post_author = %d
				         OR ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wcb_company_owner' AND meta_value = %s ) )
				 ORDER BY post_title ASC
				 LIMIT 200",
				$user_id,
				(string) $user_id
			)
		);
		$companies = array();
		foreach ( (array) $rows as $row ) {
			$companies[ (int) $row->ID ] = (string) $row->post_title;
		}
		$linked = \WCB\Core\CompanyMetaShape::resolve_company_id( $user_id );
		if ( $linked > 0 && ! isset( $companies[ $linked ] ) ) {
			$companies[ $linked ] = (string) get_post_field( 'post_title', $linked );
		}
		return $companies;
	}

	/**
	 * Whether a job is in the employer's dashboard scope: they posted it, or it
	 * belongs to a company they run.
	 *
	 * @since 1.8.1
	 *
	 * @param int      $user_id Employer user ID.
	 * @param \WP_Post $job     Job post.
	 * @return bool
	 */
	public static function owns_job( int $user_id, \WP_Post $job ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		if ( (int) $job->post_author === $user_id ) {
			return true;
		}
		return isset( self::owned_companies( $user_id )[ (int) get_post_meta( $job->ID, '_wcb_company_id', true ) ] );
	}

	/**
	 * SQL condition selecting the jobs in an employer's dashboard scope.
	 *
	 * The single definition behind every employer-dashboard number and list:
	 * jobs the employer posted OR jobs whose `_wcb_company_id` is a company they
	 * run. Resolved in SQL (indexed `wcb_meta_key_value` lookup), so no job IDs
	 * are loaded into PHP.
	 *
	 * @since 1.8.1
	 *
	 * @param int    $user_id    Employer user ID.
	 * @param string $alias      Alias of the posts table holding the job rows.
	 * @param int    $company_id Narrow to one of the employer's companies, 0 for all.
	 *                           A company they do not run matches nothing.
	 * @return string Prepared SQL condition.
	 */
	public static function job_scope_sql( int $user_id, string $alias, int $company_id = 0 ): string {
		global $wpdb;
		$companies = array_keys( self::owned_companies( $user_id ) );
		if ( $company_id > 0 ) {
			$companies = in_array( $company_id, $companies, true ) ? array( $company_id ) : array();
		}
		$by_company = $companies
			? $wpdb->prepare(
				"%i.ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wcb_company_id' AND meta_value IN ( " . implode( ', ', array_fill( 0, count( $companies ), '%s' ) ) . ' ) )',
				array_merge( array( $alias ), array_map( 'strval', $companies ) )
			)
			: '';
		if ( $company_id > 0 ) {
			return '' !== $by_company ? $by_company : '1 = 0';
		}
		$by_author = $wpdb->prepare( '%i.post_author = %d', $alias, $user_id );
		return '' !== $by_company ? "( {$by_author} OR {$by_company} )" : $by_author;
	}

	/**
	 * FROM + WHERE for the applications on an employer's dashboard-scope jobs.
	 *
	 * Shared by GET /employers/me/applications and dashboard_counts() so the
	 * Applications list and the header numbers count the same rows.
	 *
	 * @since 1.8.1
	 *
	 * @param int $user_id    Employer user ID.
	 * @param int $company_id Narrow to one company, 0 for all.
	 * @return string Prepared SQL, aliases `app` and `job`.
	 */
	public static function applications_scope_sql( int $user_id, int $company_id = 0 ): string {
		global $wpdb;
		$statuses = \WCB\Api\Endpoints\EmployersEndpoint::owner_visible_statuses( true );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders built from the status count; the scope condition is already prepared.
		return $wpdb->prepare(
			"FROM {$wpdb->posts} app
			 INNER JOIN {$wpdb->postmeta} pm_job
			        ON pm_job.post_id = app.ID AND pm_job.meta_key = '_wcb_job_id'
			 INNER JOIN {$wpdb->posts} job
			        ON job.ID = CAST(pm_job.meta_value AS UNSIGNED) AND job.post_type = 'wcb_job'
			       AND job.post_status IN ( " . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . " )
			 WHERE app.post_type = 'wcb_application'
			   AND app.post_status = 'publish'",
			...$statuses
		) . ' AND ' . self::job_scope_sql( $user_id, 'job', $company_id );
		// phpcs:enable
	}

	/**
	 * Header numbers for the employer dashboard, all over job_scope_sql().
	 *
	 * `jobs_with_apps` is keyed by company ID (0 = jobs with no company) so the
	 * Applications hint can follow the company filter; its sum is the total.
	 *
	 * @since 1.8.1
	 *
	 * @param int $user_id Employer user ID.
	 * @return array{total_jobs:int, live_jobs:int, total_apps:int, new_apps:int, jobs_with_apps:array<int,int>}
	 */
	public static function dashboard_counts( int $user_id ): array {
		global $wpdb;
		$statuses = \WCB\Api\Endpoints\EmployersEndpoint::owner_visible_statuses( true );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- every fragment is prepared; COUNT(*) only.
		$jobs_where = $wpdb->prepare(
			"job.post_type = 'wcb_job' AND job.post_status IN ( " . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ' )',
			...$statuses
		) . ' AND ' . self::job_scope_sql( $user_id, 'job' );

		$jobs = $wpdb->get_row( "SELECT COUNT(*) AS total, COALESCE( SUM( job.post_status = 'publish' ), 0 ) AS live FROM {$wpdb->posts} job WHERE {$jobs_where}" );
		$apps = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT COUNT(*) AS total, COALESCE( SUM( app.post_date >= %s ), 0 ) AS recent ' . self::applications_scope_sql( $user_id ),
				gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS )
			)
		);
		// Same "has applications" rule as each tile's appCount: any _wcb_job_id row.
		$with_apps = $wpdb->get_results(
			"SELECT COALESCE( co.meta_value, '0' ) AS company_id, COUNT(*) AS cnt
			 FROM {$wpdb->posts} job
			 LEFT JOIN {$wpdb->postmeta} co ON co.post_id = job.ID AND co.meta_key = '_wcb_company_id'
			 WHERE {$jobs_where}
			   AND EXISTS ( SELECT 1 FROM {$wpdb->postmeta} aj WHERE aj.meta_key = '_wcb_job_id' AND aj.meta_value = CAST( job.ID AS CHAR ) )
			 GROUP BY company_id"
		);
		// phpcs:enable

		$by_company = array();
		foreach ( (array) $with_apps as $row ) {
			$by_company[ (int) $row->company_id ] = ( $by_company[ (int) $row->company_id ] ?? 0 ) + (int) $row->cnt;
		}

		return array(
			'total_jobs'     => (int) ( $jobs->total ?? 0 ),
			'live_jobs'      => (int) ( $jobs->live ?? 0 ),
			'total_apps'     => (int) ( $apps->total ?? 0 ),
			'new_apps'       => (int) ( $apps->recent ?? 0 ),
			'jobs_with_apps' => $by_company,
		);
	}
}
