<?php
/**
 * Wizard step partial: Create Pages.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

defined('ABSPATH') || exit;
?>
<div class="wcb-settings-row">
    <div class="wcb-settings-row-label"><?php esc_html_e('Required Pages', 'wp-career-board'); ?></div>
    <div class="wcb-settings-row-control">
        <p><?php esc_html_e('We\'ll create the following pages automatically:', 'wp-career-board'); ?></p>
        <ul style="margin: 8px 0 0 16px; list-style: disc;">
            <li><?php esc_html_e('Find Jobs (with search, filters, and listings)', 'wp-career-board'); ?></li>
            <li><?php esc_html_e('Employer Registration (sign-up form for new employers)', 'wp-career-board'); ?></li>
            <li><?php esc_html_e('Employer Dashboard (includes job posting)', 'wp-career-board'); ?></li>
            <li><?php esc_html_e('Candidate Dashboard (includes resume builder)', 'wp-career-board'); ?></li>
        </ul>
    </div>
</div>
<div class="wcb-settings-footer">
    <button type="button" class="wcb-btn wcb-btn--primary" id="wcb-create-pages" data-wcb-wizard-action="create-pages">
        <?php esc_html_e('Create Pages & Continue', 'wp-career-board'); ?>
    </button>
</div>
