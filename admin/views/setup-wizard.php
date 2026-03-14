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
<div class="wcb-wizard wrap">
	<div class="wcb-wizard-header">
		<h1><?php esc_html_e( 'Welcome to WP Career Board', 'wp-career-board' ); ?></h1>
		<p><?php esc_html_e( 'Let\'s get your job board set up in 2 minutes.', 'wp-career-board' ); ?></p>
	</div>

	<div class="wcb-wizard-steps" id="wcb-wizard-steps">

		<div class="wcb-wizard-step active" data-step="1">
			<h2><?php esc_html_e( 'Create Pages', 'wp-career-board' ); ?></h2>
			<p><?php esc_html_e( 'We\'ll create the required pages automatically:', 'wp-career-board' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Find Jobs (with search, filters, and listings)', 'wp-career-board' ); ?></li>
				<li><?php esc_html_e( 'Post a Job', 'wp-career-board' ); ?></li>
				<li><?php esc_html_e( 'Employer Dashboard', 'wp-career-board' ); ?></li>
				<li><?php esc_html_e( 'Candidate Dashboard', 'wp-career-board' ); ?></li>
			</ul>
			<button type="button" class="button button-primary" id="wcb-create-pages">
				<?php esc_html_e( 'Create Pages & Continue', 'wp-career-board' ); ?>
			</button>
		</div>

		<div class="wcb-wizard-step" data-step="2">
			<h2><?php esc_html_e( 'Sample Data', 'wp-career-board' ); ?></h2>
			<p><?php esc_html_e( 'Install sample categories, job types, and a demo job to see how everything looks?', 'wp-career-board' ); ?></p>
			<label>
				<input type="checkbox" id="wcb-install-sample" checked>
				<?php esc_html_e( 'Yes, install sample data', 'wp-career-board' ); ?>
			</label>
			<br><br>
			<button type="button" class="button button-primary" id="wcb-finish-wizard">
				<?php esc_html_e( 'Finish Setup', 'wp-career-board' ); ?>
			</button>
		</div>

	</div>
</div>
