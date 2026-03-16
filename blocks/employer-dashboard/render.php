<?php
/**
 * Block render: wcb/employer-dashboard — tabbed employer interface.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$wcb_can_manage = function_exists( 'wp_is_ability_granted' )
	? wp_is_ability_granted( 'wcb_manage_company' )
	: current_user_can( 'wcb_manage_company' );

if ( ! is_user_logged_in() ) {
	?>
	<div class="wcb-db-gate">
		<p><?php esc_html_e( 'Please sign in to access your dashboard.', 'wp-career-board' ); ?></p>
		<a href="<?php echo esc_url( wp_login_url( (string) get_permalink() ) ); ?>" class="wcb-db-btn wcb-db-btn--primary">
			<?php esc_html_e( 'Sign In', 'wp-career-board' ); ?>
		</a>
	</div>
	<?php
	return;
}

if ( ! $wcb_can_manage ) {
	echo '<p>' . esc_html__( 'You do not have permission to view this dashboard.', 'wp-career-board' ) . '</p>';
	return;
}

$wcb_employer_id = get_current_user_id();
$wcb_company_id  = (int) get_user_meta( $wcb_employer_id, '_wcb_company_id', true );
$wcb_company     = $wcb_company_id ? get_post( $wcb_company_id ) : null;

$wcb_company_name    = $wcb_company instanceof \WP_Post ? $wcb_company->post_title : '';
$wcb_company_desc    = $wcb_company instanceof \WP_Post ? $wcb_company->post_content : '';
$wcb_company_tagline = $wcb_company_id ? (string) get_post_meta( $wcb_company_id, '_wcb_tagline', true ) : '';
$wcb_company_site    = $wcb_company_id ? (string) get_post_meta( $wcb_company_id, '_wcb_website', true ) : '';
$wcb_company_ind     = $wcb_company_id ? (string) get_post_meta( $wcb_company_id, '_wcb_industry', true ) : '';
$wcb_company_size    = $wcb_company_id ? (string) get_post_meta( $wcb_company_id, '_wcb_company_size', true ) : '';
$wcb_company_hq      = $wcb_company_id ? (string) get_post_meta( $wcb_company_id, '_wcb_hq_location', true ) : '';

// "Post a Job" page URL from settings, fallback to #.
$wcb_settings        = (array) get_option( 'wcb_settings', array() );
$wcb_post_job_url    = ! empty( $wcb_settings['post_job_page'] )
	? (string) get_permalink( (int) $wcb_settings['post_job_page'] )
	: '#';
$wcb_company_dir_url = ! empty( $wcb_settings['company_archive_page'] )
	? (string) get_permalink( (int) $wcb_settings['company_archive_page'] )
	: '#';

wp_interactivity_state(
	'wcb-employer-dashboard',
	array(
		'tab'               => 'jobs',
		'jobs'              => array(),
		'loading'           => false,
		'error'             => '',
		'noCompany'         => false,
		'apiBase'           => rest_url( 'wcb/v1' ),
		'nonce'             => wp_create_nonce( 'wp_rest' ),
		'companyId'         => $wcb_company_id,
		'companyName'       => $wcb_company_name,
		'companyDesc'       => $wcb_company_desc,
		'companyTagline'    => $wcb_company_tagline,
		'companySite'       => $wcb_company_site,
		'companyIndustry'   => $wcb_company_ind,
		'companySize'       => $wcb_company_size,
		'companyHq'         => $wcb_company_hq,
		'saving'            => false,
		'saved'             => false,
		'companyDirUrl'     => $wcb_company_dir_url,
		'customFieldGroups' => apply_filters( 'wcb_company_form_fields', array(), $wcb_company_id ),
		'customFields'      => (object) array(),
	)
);
?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-employer-dashboard"
	data-wp-init="actions.init"
>

	<?php /* ── Dashboard header ──────────────────────────────────────── */ ?>
	<header class="wcb-db-header">
		<h1 class="wcb-db-title"><?php esc_html_e( 'Dashboard', 'wp-career-board' ); ?></h1>
		<a href="<?php echo esc_url( $wcb_post_job_url ); ?>" class="wcb-db-btn wcb-db-btn--primary">
			<span aria-hidden="true">+</span> <?php esc_html_e( 'Post a Job', 'wp-career-board' ); ?>
		</a>
	</header>

	<?php /* ── Stats row ────────────────────────────────────────────── */ ?>
	<div class="wcb-db-stats">
		<div class="wcb-stat-card">
			<span class="wcb-stat-value" data-wp-text="state.totalJobs">0</span>
			<span class="wcb-stat-label"><?php esc_html_e( 'Total Jobs', 'wp-career-board' ); ?></span>
		</div>
		<div class="wcb-stat-card">
			<span class="wcb-stat-value" data-wp-text="state.publishedJobs">0</span>
			<span class="wcb-stat-label"><?php esc_html_e( 'Published', 'wp-career-board' ); ?></span>
		</div>
		<div class="wcb-stat-card">
			<span class="wcb-stat-value" data-wp-text="state.totalApps">0</span>
			<span class="wcb-stat-label"><?php esc_html_e( 'Applications', 'wp-career-board' ); ?></span>
		</div>
	</div>

	<?php /* ── Tab navigation ──────────────────────────────────────── */ ?>
	<nav class="wcb-dashboard-tabs" aria-label="<?php esc_attr_e( 'Dashboard sections', 'wp-career-board' ); ?>">
		<button
			type="button"
			class="wcb-tab-btn"
			data-wp-class--wcb-tab-active="state.isTabJobs"
			data-wp-on--click="actions.switchToJobs"
		><?php esc_html_e( 'My Jobs', 'wp-career-board' ); ?></button>
		<button
			type="button"
			class="wcb-tab-btn"
			data-wp-class--wcb-tab-active="state.isTabProfile"
			data-wp-on--click="actions.switchToProfile"
		><?php esc_html_e( 'Company Profile', 'wp-career-board' ); ?></button>
	</nav>

	<?php /* ── Tab: My Jobs ─────────────────────────────────────────── */ ?>
	<div class="wcb-tab-panel" data-wp-class--wcb-tab-active="state.isTabJobs">

		<?php /* Loading skeleton */ ?>
		<div class="wcb-db-loading" data-wp-class--wcb-shown="state.loading">
			<div class="wcb-skeleton-row"></div>
			<div class="wcb-skeleton-row"></div>
			<div class="wcb-skeleton-row"></div>
		</div>

		<?php /* No company yet */ ?>
		<div class="wcb-db-empty" data-wp-class--wcb-shown="state.noCompany">
			<p class="wcb-db-empty-msg"><?php esc_html_e( 'Set up your company profile first before posting jobs.', 'wp-career-board' ); ?></p>
			<button type="button" class="wcb-db-btn wcb-db-btn--secondary" data-wp-on--click="actions.switchToProfile">
				<?php esc_html_e( 'Set Up Company Profile', 'wp-career-board' ); ?>
			</button>
		</div>

		<?php /* Error */ ?>
		<p class="wcb-db-error" data-wp-class--wcb-shown="state.error" data-wp-text="state.error"></p>

		<?php /* Empty state */ ?>
		<div class="wcb-db-empty" data-wp-class--wcb-shown="state.noJobs">
			<p class="wcb-db-empty-msg"><?php esc_html_e( 'No jobs posted yet.', 'wp-career-board' ); ?></p>
			<a href="<?php echo esc_url( $wcb_post_job_url ); ?>" class="wcb-db-btn wcb-db-btn--secondary">
				<?php esc_html_e( 'Post Your First Job', 'wp-career-board' ); ?>
			</a>
		</div>

		<?php /* Jobs list */ ?>
		<div class="wcb-jobs-list" data-wp-class--wcb-shown="state.hasJobs">
			<template data-wp-each--job="state.jobs" data-wp-each-key="context.job.id">
				<article class="wcb-emp-job-row">
					<div class="wcb-emp-job-main">
						<a
							class="wcb-emp-job-title"
							data-wp-bind--href="context.job.permalink"
							data-wp-text="context.job.title"
							target="_blank"
							rel="noopener noreferrer"
						></a>
						<div class="wcb-emp-job-chips">
							<span
								class="wcb-emp-job-type"
								data-wp-text="context.job.type"
								data-wp-class--wcb-hidden="!context.job.type"
							></span>
							<span
								class="wcb-emp-job-loc"
								data-wp-text="context.job.location"
								data-wp-class--wcb-hidden="!context.job.location"
							></span>
						</div>
					</div>
					<div class="wcb-emp-job-aside">
						<span
							class="wcb-job-status-badge"
							data-wp-text="context.job.statusLabel"
							data-wp-bind--data-status="context.job.status"
						></span>
						<span class="wcb-job-apps-chip" data-wp-text="context.job.appLabel"></span>
						<div class="wcb-emp-job-actions">
							<a
								class="wcb-db-link-btn"
								data-wp-bind--href="context.job.permalink"
								target="_blank"
								rel="noopener noreferrer"
							><?php esc_html_e( 'View ↗', 'wp-career-board' ); ?></a>
							<a
								class="wcb-db-link-btn wcb-db-link-btn--edit"
								data-wp-bind--href="context.job.editUrl"
							><?php esc_html_e( 'Edit', 'wp-career-board' ); ?></a>
						</div>
					</div>
				</article>
			</template>
		</div>
	</div>

	<?php /* ── Tab: Company Profile ─────────────────────────────────── */ ?>
	<div class="wcb-tab-panel" data-wp-class--wcb-tab-active="state.isTabProfile">

		<div class="wcb-profile-form">
			<h2 class="wcb-profile-form-title"><?php esc_html_e( 'Company Profile', 'wp-career-board' ); ?></h2>

			<div class="wcb-field-group">
				<label class="wcb-field-label" for="wcb-company-name"><?php esc_html_e( 'Company Name', 'wp-career-board' ); ?></label>
				<input
					id="wcb-company-name"
					type="text"
					class="wcb-field-input"
					data-wcb-field="companyName"
					data-wp-bind--value="state.companyName"
					data-wp-on--input="actions.updateField"
				/>
			</div>

			<div class="wcb-field-group">
				<label class="wcb-field-label" for="wcb-company-tagline">
					<?php esc_html_e( 'Tagline', 'wp-career-board' ); ?>
					<span class="wcb-field-hint"><?php esc_html_e( 'One-line description shown on listings', 'wp-career-board' ); ?></span>
				</label>
				<input
					id="wcb-company-tagline"
					type="text"
					class="wcb-field-input"
					data-wcb-field="companyTagline"
					data-wp-bind--value="state.companyTagline"
					data-wp-on--input="actions.updateField"
				/>
			</div>

			<div class="wcb-field-group">
				<label class="wcb-field-label" for="wcb-company-desc"><?php esc_html_e( 'About the Company', 'wp-career-board' ); ?></label>
				<textarea
					id="wcb-company-desc"
					class="wcb-field-input wcb-field-textarea"
					rows="5"
					data-wcb-field="companyDesc"
					data-wp-bind--value="state.companyDesc"
					data-wp-on--input="actions.updateField"
				></textarea>
			</div>

			<div class="wcb-field-row">
				<div class="wcb-field-group">
					<label class="wcb-field-label" for="wcb-company-ind"><?php esc_html_e( 'Industry', 'wp-career-board' ); ?></label>
					<input
						id="wcb-company-ind"
						type="text"
						class="wcb-field-input"
						placeholder="<?php esc_attr_e( 'e.g. Technology, Finance, Healthcare', 'wp-career-board' ); ?>"
						data-wcb-field="companyIndustry"
						data-wp-bind--value="state.companyIndustry"
						data-wp-on--input="actions.updateField"
					/>
				</div>
				<div class="wcb-field-group">
					<label class="wcb-field-label" for="wcb-company-size"><?php esc_html_e( 'Company Size', 'wp-career-board' ); ?></label>
					<select
						id="wcb-company-size"
						class="wcb-field-input wcb-field-select"
						data-wcb-field="companySize"
						data-wp-on--change="actions.updateField"
					>
						<option value=""><?php esc_html_e( '— Select size —', 'wp-career-board' ); ?></option>
						<?php
						$wcb_size_options = array(
							'1-10'      => __( '1–10 employees', 'wp-career-board' ),
							'11-50'     => __( '11–50 employees', 'wp-career-board' ),
							'51-200'    => __( '51–200 employees', 'wp-career-board' ),
							'201-500'   => __( '201–500 employees', 'wp-career-board' ),
							'501-1000'  => __( '501–1,000 employees', 'wp-career-board' ),
							'1001-5000' => __( '1,001–5,000 employees', 'wp-career-board' ),
							'5000+'     => __( '5,000+ employees', 'wp-career-board' ),
						);
						foreach ( $wcb_size_options as $wcb_val => $wcb_label ) {
							printf(
								'<option value="%s"%s>%s</option>',
								esc_attr( $wcb_val ),
								selected( $wcb_company_size, $wcb_val, false ),
								esc_html( $wcb_label )
							);
						}
						?>
					</select>
				</div>
			</div>

			<div class="wcb-field-group">
				<label class="wcb-field-label" for="wcb-company-hq"><?php esc_html_e( 'HQ Location', 'wp-career-board' ); ?></label>
				<input
					id="wcb-company-hq"
					type="text"
					class="wcb-field-input"
					placeholder="<?php esc_attr_e( 'e.g. San Francisco, CA', 'wp-career-board' ); ?>"
					data-wcb-field="companyHq"
					data-wp-bind--value="state.companyHq"
					data-wp-on--input="actions.updateField"
				/>
			</div>

			<div class="wcb-field-group">
				<label class="wcb-field-label" for="wcb-company-site"><?php esc_html_e( 'Website', 'wp-career-board' ); ?></label>
				<input
					id="wcb-company-site"
					type="url"
					class="wcb-field-input"
					placeholder="https://"
					data-wcb-field="companySite"
					data-wp-bind--value="state.companySite"
					data-wp-on--input="actions.updateField"
				/>
			</div>

			<div class="wcb-profile-actions">
				<p class="wcb-db-save-success" data-wp-class--wcb-shown="state.saved">
					<?php esc_html_e( '✓ Profile saved successfully.', 'wp-career-board' ); ?>
				</p>
				<p class="wcb-db-error" data-wp-class--wcb-shown="state.error" data-wp-text="state.error"></p>

				<button
					type="button"
					class="wcb-db-btn wcb-db-btn--primary"
					data-wp-on--click="actions.saveProfile"
					data-wp-bind--disabled="state.saving"
				>
					<span data-wp-class--wcb-hidden="state.saving"><?php esc_html_e( 'Save Profile', 'wp-career-board' ); ?></span>
					<span class="wcb-saving-label" data-wp-class--wcb-shown="state.saving"><?php esc_html_e( 'Saving…', 'wp-career-board' ); ?></span>
				</button>
			</div>
		</div>
	</div>

</div>
