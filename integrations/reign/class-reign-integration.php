<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Reign theme integration for WP Career Board.
 *
 * Activated automatically when the active theme is 'reign-theme'.
 * Provides:
 *  - Template overrides for single and archive wcb_job pages
 *  - Customizer colour control under a dedicated "WP Career Board" panel
 *  - Tells Reign's title system which pages print their own h1
 *  - A lightweight compatibility stylesheet for WCB blocks inside Reign
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Integrations\Reign;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReignIntegration
 */
class ReignIntegration {

	/**
	 * Boot hooks.
	 */
	public function boot(): void {
		add_filter( 'single_template', array( $this, 'single_template' ) );
		add_filter( 'archive_template', array( $this, 'archive_template' ) );
		add_filter( 'reign_title_plugin_owned', array( self::class, 'owns_title' ) );
		// Reign's header opens <main id="content">; ours would nest a second one.
		add_filter( 'wcb_template_main_tag', static fn () => 'div' );
		\WCB\Core\ThemeCompat::register(
			'wcb-reign-compat',
			WCB_URL . 'integrations/reign/assets/reign-compat.css',
			array( 'reign_main_style' )
		);
	}

	/**
	 * Return Reign-compatible single job template when viewing a wcb_job post.
	 *
	 * @param mixed  $template Default template path.
	 * @return mixed
	 */
	public function single_template( $template ) {
		if ( ! is_string( $template ) ) {
			return $template; // Another filter handed back a non-string: pass it on untouched.
		}
		if ( ! is_singular( 'wcb_job' ) ) {
			return $template;
		}

		// A theme shipping its OWN single-wcb_job.php outranks the bundled
		// integration. This handler runs on single_template, which receives
		// whatever WordPress's hierarchy already resolved - a theme's generic
		// single.php does not count, only a file matching this exact slot.
		if ( \WCB\Core\TemplateOverride::is_theme_template( $template, 'single-wcb_job.php' ) ) {
			return $template;
		}

		$reign_tpl = WCB_DIR . 'integrations/reign/templates/single-wcb_job.php';
		return file_exists( $reign_tpl ) ? $reign_tpl : $template;
	}

	/**
	 * Return Reign-compatible archive template for wcb_job post-type archives.
	 *
	 * @param mixed  $template Default template path.
	 * @return mixed
	 */
	public function archive_template( $template ) {
		if ( ! is_string( $template ) ) {
			return $template; // Another filter handed back a non-string: pass it on untouched.
		}
		if ( ! is_post_type_archive( 'wcb_job' ) ) {
			return $template;
		}

		// A theme shipping its OWN archive-wcb_job.php outranks the bundled
		// integration; its generic archive.php does not count. See single_template().
		if ( \WCB\Core\TemplateOverride::is_theme_template( $template, 'archive-wcb_job.php' ) ) {
			return $template;
		}

		$reign_tpl = WCB_DIR . 'integrations/reign/templates/archive-wcb_job.php';
		return file_exists( $reign_tpl ) ? $reign_tpl : $template;
	}

	/**
	 * Blocks that print the page's own h1.
	 *
	 * @since 1.8.3
	 */
	private const HEADING_BLOCKS = array(
		'wp-career-board/job-single',
		'wp-career-board/company-profile',
		'wp-career-board/job-form',
		'wp-career-board/job-form-simple',
		'wp-career-board/candidate-dashboard',
		'wp-career-board/employer-dashboard',
		'wp-career-board/employer-registration',
		// Listing blocks print the page heading through ArchiveHeading::render().
		'wp-career-board/job-listings',
		'wp-career-board/job-search',
		'wp-career-board/company-archive',
	);

	/**
	 * Whether this page prints its own h1, so Reign adds no second one.
	 *
	 * Reign 8.0.9+ asks through reign_title_plugin_owned, the way it does for
	 * WooCommerce and WP Job Manager. Our single job and company templates,
	 * the job and company archives, job taxonomy archives, and every page
	 * holding a listing, dashboard, form or registration block (as a block or
	 * its shortcode) print the h1 themselves; Pro adds its blocks through
	 * wcb_title_owning_blocks.
	 *
	 * @since 1.8.3
	 *
	 * @param mixed $owned Whether a plugin already owns the title.
	 * @return mixed
	 */
	public static function owns_title( $owned ) {
		if ( $owned ) {
			return $owned;
		}
		// Job and company archives and every job taxonomy archive render through
		// Career Board blocks that print the page heading.
		if ( is_singular( array( 'wcb_job', 'wcb_company' ) ) || is_post_type_archive( array( 'wcb_job', 'wcb_company' ) ) || is_tax( \WCB\Core\Taxonomy::TAXONOMIES ) ) {
			return true;
		}
		$post = is_singular() ? get_queried_object() : null;
		if ( ! $post instanceof \WP_Post ) {
			return $owned;
		}
		$tags = array_flip( \WCB\Core\Plugin::SHORTCODES );
		/**
		 * Blocks that print the page's own h1, as block name => shortcode tag ('' for none).
		 * A page holding one tells the theme not to add a second heading.
		 *
		 * @since 1.8.3
		 *
		 * @param array<string, string> $blocks Block name => shortcode tag.
		 */
		$blocks = (array) apply_filters( 'wcb_title_owning_blocks', array_combine( self::HEADING_BLOCKS, array_map( static fn( string $b ): string => (string) ( $tags[ $b ] ?? '' ), self::HEADING_BLOCKS ) ) );
		foreach ( $blocks as $block => $tag ) {
			if ( has_block( (string) $block, $post ) || ( '' !== (string) $tag && has_shortcode( $post->post_content, (string) $tag ) ) ) {
				return true;
			}
		}
		return $owned;
	}
}
