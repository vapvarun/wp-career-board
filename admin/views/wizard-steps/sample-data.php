<?php
/**
 * Wizard step partial: Sample Data.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wcb-settings-row">
	<div class="wcb-settings-row-label"><?php esc_html_e( 'Demo Content', 'wp-career-board' ); ?></div>
	<div class="wcb-settings-row-control">
		<label class="wcb-toggle-label">
			<span class="wcb-toggle">
				<input type="checkbox" id="wcb-install-sample" checked>
				<span class="wcb-toggle-slider"></span>
			</span>
			<?php esc_html_e( 'Install sample categories, job types, companies, and demo jobs', 'wp-career-board' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Helps you see how everything looks before adding real data.', 'wp-career-board' ); ?></p>
	</div>
</div>
<div class="wcb-settings-footer wcb-wizard-footer">
	<button type="button" class="wcb-btn wcb-btn--primary" id="wcb-finish-wizard" data-wcb-wizard-action="sample-data">
		<?php esc_html_e( 'Finish Setup', 'wp-career-board' ); ?>
	</button>
</div>
