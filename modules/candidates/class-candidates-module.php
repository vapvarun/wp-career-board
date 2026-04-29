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
	 * Public-archive visibility is controlled by the `resume_archive_enabled`
	 * setting (and its `wcb_resume_archive_enabled` filter). Pro flips the
	 * setting on activation rather than mutating CPT args at runtime — this
	 * keeps Free as the single source of truth for the CPT contract so single-
	 * resume URLs don't 404 silently if Pro is later deactivated and the site
	 * owner wants the archive to stay up.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_type(): void {
		$settings = (array) get_option( 'wcb_settings', array() );
		$public   = (bool) ( $settings['resume_archive_enabled'] ?? false );

		/**
		 * Filter the public-archive visibility of `wcb_resume`.
		 *
		 * Site owners can override the admin setting programmatically — useful
		 * for staging environments or addon plugins that need to toggle the
		 * archive based on their own conditions.
		 *
		 * @since 1.2.0
		 *
		 * @param bool $public Whether the resume archive should be public.
		 */
		$public = (bool) apply_filters( 'wcb_resume_archive_enabled', $public );

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
				'public'             => $public,
				'publicly_queryable' => $public,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'show_in_menu'       => false,
				'has_archive'        => false,
				'show_in_nav_menus'  => false,
				'rewrite'            => $public ? array(
					'slug'       => 'resume',
					'with_front' => false,
				) : false,
				'supports'           => array( 'title', 'custom-fields' ),
				'capability_type'    => 'post',
				'map_meta_cap'       => true,
			)
		);
	}
}
