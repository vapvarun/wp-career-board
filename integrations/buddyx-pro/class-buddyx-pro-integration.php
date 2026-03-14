<?php
/**
 * BuddyX Pro theme integration for WP Career Board.
 *
 * Activated automatically when the active theme slug is 'buddyx-pro'.
 * Provides:
 *  - Template overrides for single and archive wcb_job pages
 *  - #OpenToWork badge on candidate BuddyX Pro member profiles
 *  - A lightweight compatibility stylesheet for WCB blocks inside BuddyX Pro
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Integrations\BuddyxPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BuddyX Pro theme integration.
 *
 * @since 1.0.0
 */
class BuddyxProIntegration {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_filter( 'single_template', array( $this, 'single_template' ) );
		add_filter( 'archive_template', array( $this, 'archive_template' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		// Add job-seeking status badge to BuddyX Pro member profiles.
		add_action( 'buddyx_pro_after_member_name', array( $this, 'show_job_seeking_badge' ) );
	}

	/**
	 * Return BuddyX Pro-compatible single job template when viewing a wcb_job post.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template Default template path.
	 * @return string
	 */
	public function single_template( string $template ): string {
		if ( is_singular( 'wcb_job' ) ) {
			$tpl = WCB_DIR . 'integrations/buddyx-pro/templates/single-wcb_job.php';
			if ( file_exists( $tpl ) ) {
				return $tpl;
			}
		}
		return $template;
	}

	/**
	 * Return BuddyX Pro-compatible archive template for wcb_job post-type archives.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template Default template path.
	 * @return string
	 */
	public function archive_template( string $template ): string {
		if ( is_post_type_archive( 'wcb_job' ) ) {
			$tpl = WCB_DIR . 'integrations/buddyx-pro/templates/archive-wcb_job.php';
			if ( file_exists( $tpl ) ) {
				return $tpl;
			}
		}
		return $template;
	}

	/**
	 * Output an #OpenToWork badge on BuddyX Pro member profiles for candidates
	 * who have opted in to job-seeking visibility.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The displayed member's user ID.
	 * @return void
	 */
	public function show_job_seeking_badge( int $user_id ): void {
		$seeking = get_user_meta( $user_id, '_wcb_open_to_work', true );
		if ( $seeking ) {
			echo '<span class="wcb-open-badge">' . esc_html__( '#OpenToWork', 'wp-career-board' ) . '</span>';
		}
	}

	/**
	 * Enqueue BuddyX Pro compatibility stylesheet on WCB job pages.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_styles(): void {
		if ( ! is_singular( 'wcb_job' ) && ! is_post_type_archive( 'wcb_job' ) ) {
			return;
		}
		wp_enqueue_style(
			'wcb-buddyx-compat',
			WCB_URL . 'integrations/buddyx-pro/assets/buddyx-compat.css',
			array(),
			WCB_VERSION
		);
	}
}
