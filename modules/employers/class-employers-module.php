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

		$dashboard_id = \WCB\Admin\Settings::int( 'employer_dashboard_page', 0 );

		if ( ! $dashboard_id ) {
			return $redirect_to;
		}

		$dashboard_url = get_permalink( $dashboard_id );
		return $dashboard_url ? (string) $dashboard_url : $redirect_to;
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
		if ( ! is_post_type_archive( 'wcb_company' ) ) {
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
		if ( str_contains( $template, 'wp-career-board' ) ) {
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
}
