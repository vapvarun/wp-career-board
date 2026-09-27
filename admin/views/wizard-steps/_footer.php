<?php
/**
 * Wizard step partial: Save & Continue / Skip footer for settings steps.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wcb-settings-footer wcb-wizard-footer">
	<button type="button" class="wcb-btn wcb-btn--primary" data-wcb-wizard-save><?php esc_html_e( 'Save & Continue', 'wp-career-board' ); ?></button>
	<button type="button" class="wcb-btn wcb-btn--secondary" data-wcb-wizard-skip><?php esc_html_e( 'Skip for now', 'wp-career-board' ); ?></button>
	<span class="wcb-wizard-error" role="alert"></span>
</div>
