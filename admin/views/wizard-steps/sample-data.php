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
		<label class="wcb-toggle">
			<input type="checkbox" id="wcb-install-sample" checked>
			<span class="wcb-toggle-slider"></span>
		</label>
		<span style="margin-left:10px;vertical-align:middle"><?php esc_html_e( 'Install sample categories, job types, and a demo job', 'wp-career-board' ); ?></span>
		<p class="description" style="margin-top:6px"><?php esc_html_e( 'Helps you see how everything looks before adding real data.', 'wp-career-board' ); ?></p>
	</div>
</div>
<div class="wcb-settings-footer">
	<button type="button" class="wcb-btn wcb-btn--primary" id="wcb-finish-wizard" data-wcb-wizard-action="sample-data">
		<?php esc_html_e( 'Finish Setup', 'wp-career-board' ); ?>
	</button>
</div>
