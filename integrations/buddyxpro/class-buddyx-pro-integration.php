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
		// Depend on the WCB token stylesheet so the compat token bridge loads
		// AFTER the plugin defaults; equal-specificity :root rules then resolve
		// in the bridge's favor (BuddyX palette wins over the WCB fallbacks).
		\WCB\Core\ThemeCompat::register(
			'wcb-buddyx-compat',
			WCB_URL . 'integrations/buddyxpro/assets/buddyx-compat.css',
			array( 'wcb-frontend-tokens' )
		);
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
			$tpl = WCB_DIR . 'integrations/buddyxpro/templates/single-wcb_job.php';
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
			$tpl = WCB_DIR . 'integrations/buddyxpro/templates/archive-wcb_job.php';
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
}
