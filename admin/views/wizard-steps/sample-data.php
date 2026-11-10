<?php
/**
 * Wizard step partial: Sample Data.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

// Demo content defaults on only for an empty board, so finishing setup on a
// live site never publishes demo jobs next to real ones.
$wcb_job_counts = (array) wp_count_posts( 'wcb_job' );
unset( $wcb_job_counts['trash'], $wcb_job_counts['auto-draft'] );
$wcb_has_jobs  = array_sum( array_map( 'intval', $wcb_job_counts ) ) > 0;
$wcb_demo_url  = \WCB\Admin\SetupWizard::demo_installer_url();
$wcb_has_menus = array() !== get_registered_nav_menus();
?>
<div class="wcb-settings-row">
	<div class="wcb-settings-row-label"><?php esc_html_e( 'Demo Content', 'wp-career-board' ); ?></div>
	<div class="wcb-settings-row-control">
		<?php if ( '' !== $wcb_demo_url ) : ?>
			<?php // The Demo Installer builds the whole demo from one cast for every Wbcom product; no second set here. ?>
			<p><?php esc_html_e( 'Wbcom Demo Installer is active. It builds the full demo for every Wbcom plugin on this site, with sample people, applications and company logos, and removes it all in one click.', 'wp-career-board' ); ?></p>
			<p><a class="wcb-btn" href="<?php echo esc_url( $wcb_demo_url ); ?>"><?php esc_html_e( 'Open Demo Installer', 'wp-career-board' ); ?></a></p>
		<?php else : ?>
			<label class="wcb-toggle-label">
				<span class="wcb-toggle">
					<input type="checkbox" id="wcb-install-sample" <?php checked( ! $wcb_has_jobs ); ?>>
					<span class="wcb-toggle-slider"></span>
				</span>
				<?php esc_html_e( 'Add sample companies and jobs', 'wp-career-board' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Helps you see how everything looks before adding real data. No user accounts are created, and you can remove it all later from Settings.', 'wp-career-board' ); ?></p>
		<?php endif; ?>
	</div>
</div>
<?php if ( $wcb_has_menus ) : ?>
	<div class="wcb-settings-row">
		<div class="wcb-settings-row-label"><?php esc_html_e( 'Menu', 'wp-career-board' ); ?></div>
		<div class="wcb-settings-row-control">
			<label class="wcb-toggle-label">
				<span class="wcb-toggle">
					<input type="checkbox" id="wcb-add-menu">
					<span class="wcb-toggle-slider"></span>
				</span>
				<?php esc_html_e( 'Create a Jobs menu with Find Jobs, Companies and Post a Job', 'wp-career-board' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'It is placed in your main menu location only if that location is empty. Otherwise assign it in Appearance > Menus.', 'wp-career-board' ); ?></p>
		</div>
	</div>
<?php endif; ?>
<div class="wcb-settings-footer wcb-wizard-footer">
	<button type="button" class="wcb-btn wcb-btn--primary" id="wcb-finish-wizard" data-wcb-wizard-action="sample-data">
		<?php esc_html_e( 'Finish Setup', 'wp-career-board' ); ?>
	</button>
</div>
