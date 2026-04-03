<?php
/**
 * Admin view: setup wizard — two-step first-run configuration.
 *
 * Step 1: Create required pages via REST POST /wcb/v1/wizard/create-pages.
 * Step 2: Optionally install sample data via REST POST /wcb/v1/wizard/sample-data,
 *         then finalize via REST POST /wcb/v1/wizard/complete.
 *
 * All interactivity is handled by assets/js/wizard.js using wp-api-fetch.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wcb-wizard-wrap">
	<h1 class="screen-reader-text"><?php esc_html_e( 'WP Career Board Setup', 'wp-career-board' ); ?></h1>

	<div class="wcb-page-header">
		<div class="wcb-page-header__left">
			<h2 class="wcb-page-header__title">
				<i data-lucide="briefcase" class="wcb-icon--lg"></i>
				<?php esc_html_e( 'WP Career Board', 'wp-career-board' ); ?>
			</h2>
			<p class="wcb-page-header__desc"><?php esc_html_e( 'Quick setup — takes about 2 minutes', 'wp-career-board' ); ?></p>
		</div>
	</div>

	<div class="wcb-wizard-steps" id="wcb-wizard-steps">

		<div class="wcb-wizard-step wcb-settings-card active" data-step="1">
			<div class="wcb-settings-card-header">
				<h2 class="wcb-settings-card-title"><?php esc_html_e( 'Create Pages', 'wp-career-board' ); ?></h2>
			</div>
			<div class="wcb-settings-row">
				<div class="wcb-settings-row-label"><?php esc_html_e( 'Required Pages', 'wp-career-board' ); ?></div>
				<div class="wcb-settings-row-control">
					<p><?php esc_html_e( 'We\'ll create the following pages automatically:', 'wp-career-board' ); ?></p>
					<ul style="margin: 8px 0 0 16px; list-style: disc;">
						<li><?php esc_html_e( 'Find Jobs (with search, filters, and listings)', 'wp-career-board' ); ?></li>
						<li><?php esc_html_e( 'Employer Registration (sign-up form for new employers)', 'wp-career-board' ); ?></li>
						<li><?php esc_html_e( 'Employer Dashboard (includes job posting)', 'wp-career-board' ); ?></li>
						<li><?php esc_html_e( 'Candidate Dashboard (includes resume builder)', 'wp-career-board' ); ?></li>
					</ul>
				</div>
			</div>
			<div class="wcb-settings-footer">
				<button type="button" class="wcb-btn wcb-btn--primary" id="wcb-create-pages">
					<?php esc_html_e( 'Create Pages & Continue', 'wp-career-board' ); ?>
				</button>
			</div>
		</div>

		<div class="wcb-wizard-step wcb-settings-card" data-step="2">
			<div class="wcb-settings-card-header">
				<h2 class="wcb-settings-card-title"><?php esc_html_e( 'Sample Data', 'wp-career-board' ); ?></h2>
			</div>
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
				<button type="button" class="wcb-btn wcb-btn--primary" id="wcb-finish-wizard">
					<?php esc_html_e( 'Finish Setup', 'wp-career-board' ); ?>
				</button>
			</div>
		</div>

	</div>
</div>
