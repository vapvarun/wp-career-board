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
}
