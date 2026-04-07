<?php
/**
 * Admin view: setup wizard — already completed state.
 *
 * Shown when an admin navigates to the setup wizard after initial setup.
 * Offers a deliberate "Re-run" link that reloads with ?wcb_rerun=1.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wcb-admin wcb-wizard-wrap">
	<h1 class="screen-reader-text"><?php esc_html_e( 'WP Career Board Setup', 'wp-career-board' ); ?></h1>

	<div class="wcb-page-header">
		<div class="wcb-page-header__left">
			<h2 class="wcb-page-header__title">
				<i data-lucide="briefcase" class="wcb-icon--lg"></i>
				<?php esc_html_e( 'WP Career Board', 'wp-career-board' ); ?>
			</h2>
			<p class="wcb-page-header__desc"><?php esc_html_e( 'Setup wizard', 'wp-career-board' ); ?></p>
		</div>
	</div>

	<div class="wcb-settings-card">
		<div class="wcb-settings-card-header">
			<h2 class="wcb-settings-card-title"><?php esc_html_e( 'Setup Already Completed', 'wp-career-board' ); ?></h2>
		</div>
		<div class="wcb-settings-row">
			<div class="wcb-settings-row-label"><?php esc_html_e( 'Status', 'wp-career-board' ); ?></div>
			<div class="wcb-settings-row-control">
				<p><?php esc_html_e( 'The initial setup wizard has already been completed. Running it again will re-create any missing pages.', 'wp-career-board' ); ?></p>
			</div>
		</div>
		<div class="wcb-settings-footer">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-career-board' ) ); ?>" class="wcb-btn">
				<?php esc_html_e( 'Back to Dashboard', 'wp-career-board' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcb-setup&wcb_rerun=1' ) ); ?>" class="wcb-btn wcb-btn--primary">
				<?php esc_html_e( 'Re-run Setup Wizard', 'wp-career-board' ); ?>
			</a>
		</div>
	</div>
</div>
